<?php
declare( strict_types=1 );

namespace GreenWorld\Content;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * ContentTypes — registers the content-hub machinery that powers the topical
 * authority + semantic-network layer.
 *
 * - gw_guide       : educational guides. Their archive is /health-wellness-guide/
 *                    (the content hub) and singles live under that path.
 * - gw_guide_topic : the hub taxonomy (Natural Health, Nutrition, Wellness,
 *                    Healthy Living, Product Guides, FAQs).
 * - Landing template: template-landing.php for the commercial pillar pages.
 * - Guide meta     : _gw_guide_type (article|howto) and _gw_guide_pillar, which
 *                    drive HowTo structured data and product-to-guide linking.
 */
final class ContentTypes implements Bootable {

	public function boot(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'init', [ $this, 'register_tax' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_filter( 'theme_page_templates', [ $this, 'register_templates' ] );
		add_filter( 'template_include', [ $this, 'load_landing_template' ], 20 );
		add_action( 'add_meta_boxes', [ $this, 'guide_meta_box' ] );
		add_action( 'save_post_' . TopicMap::GUIDE_CPT, [ $this, 'save_guide_meta' ], 10, 2 );
	}

	public function register_cpt(): void {
		register_post_type(
			TopicMap::GUIDE_CPT,
			[
				'labels'        => [
					'name'          => __( 'Guides', 'greenworld' ),
					'singular_name' => __( 'Guide', 'greenworld' ),
					'menu_name'     => __( 'Health & Wellness Guide', 'greenworld' ),
					'add_new_item'  => __( 'Add New Guide', 'greenworld' ),
					'edit_item'     => __( 'Edit Guide', 'greenworld' ),
					'all_items'     => __( 'All Guides', 'greenworld' ),
				],
				'public'        => true,
				'has_archive'   => TopicMap::HUB_SLUG,
				'rewrite'       => [ 'slug' => TopicMap::HUB_SLUG, 'with_front' => false ],
				'menu_icon'     => 'dashicons-book-alt',
				'menu_position' => 26,
				'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
				'show_in_rest'  => true,
				'taxonomies'    => [ TopicMap::GUIDE_TAX ],
			]
		);
	}

	public function register_tax(): void {
		register_taxonomy(
			TopicMap::GUIDE_TAX,
			[ TopicMap::GUIDE_CPT ],
			[
				'labels'            => [
					'name'          => __( 'Guide Topics', 'greenworld' ),
					'singular_name' => __( 'Guide Topic', 'greenworld' ),
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => [ 'slug' => TopicMap::HUB_SLUG . '/topic', 'with_front' => false ],
			]
		);
	}

	public function register_meta(): void {
		$args = [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		];
		register_post_meta( TopicMap::GUIDE_CPT, '_gw_guide_type', $args );
		register_post_meta( TopicMap::GUIDE_CPT, '_gw_guide_pillar', $args );
	}

	/**
	 * @param array<string,string> $templates
	 * @return array<string,string>
	 */
	public function register_templates( array $templates ): array {
		$templates[ TopicMap::LANDING_TPL ] = __( 'GreenWorld Landing Page', 'greenworld' );
		return $templates;
	}

	public function load_landing_template( string $template ): string {
		if ( is_page() && TopicMap::LANDING_TPL === get_page_template_slug() ) {
			$located = locate_template( TopicMap::LANDING_TPL );
			if ( '' !== $located ) {
				return $located;
			}
		}
		return $template;
	}

	public function guide_meta_box(): void {
		add_meta_box(
			'gw_guide_meta',
			__( 'Guide Relationships', 'greenworld' ),
			[ $this, 'render_guide_meta' ],
			TopicMap::GUIDE_CPT,
			'side',
			'default'
		);
	}

	public function render_guide_meta( \WP_Post $post ): void {
		wp_nonce_field( 'gw_guide_meta', 'gw_guide_meta_nonce' );
		$type   = (string) get_post_meta( $post->ID, '_gw_guide_type', true );
		$pillar = (string) get_post_meta( $post->ID, '_gw_guide_pillar', true );

		echo '<p><label for="gw_guide_type"><strong>' . esc_html__( 'Guide type', 'greenworld' ) . '</strong></label><br>';
		echo '<select id="gw_guide_type" name="gw_guide_type" style="width:100%">';
		foreach ( [ 'article' => __( 'Article', 'greenworld' ), 'howto' => __( 'How-To (instructional)', 'greenworld' ) ] as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select></p>';

		echo '<p><label for="gw_guide_pillar"><strong>' . esc_html__( 'Related pillar', 'greenworld' ) . '</strong></label><br>';
		echo '<select id="gw_guide_pillar" name="gw_guide_pillar" style="width:100%"><option value="">' . esc_html__( '— none —', 'greenworld' ) . '</option>';
		foreach ( TopicMap::pillars() as $key => $p ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $pillar, $key, false ) . '>' . esc_html( (string) $p['label'] ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="description">' . esc_html__( 'How-To guides emit HowTo structured data only when they contain a numbered step list.', 'greenworld' ) . '</p>';
	}

	public function save_guide_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['gw_guide_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gw_guide_meta_nonce'] ) ), 'gw_guide_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type   = isset( $_POST['gw_guide_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['gw_guide_type'] ) ) : 'article';
		$pillar = isset( $_POST['gw_guide_pillar'] ) ? sanitize_key( (string) wp_unslash( $_POST['gw_guide_pillar'] ) ) : '';
		update_post_meta( $post_id, '_gw_guide_type', in_array( $type, [ 'article', 'howto' ], true ) ? $type : 'article' );
		update_post_meta( $post_id, '_gw_guide_pillar', $pillar );
	}
}
