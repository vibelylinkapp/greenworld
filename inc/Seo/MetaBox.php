<?php
declare( strict_types=1 );

namespace GreenWorld\Seo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * SEO Control Center — a lightweight per-entry meta box on posts, pages and
 * products for overriding the SEO title, meta description, focus topic,
 * canonical URL and index/noindex. Values are read by the Meta module.
 *
 * Kept intentionally dependency-free so it never conflicts with a full SEO
 * plugin; when Yoast / Rank Math is active the Meta/Schema modules defer to it,
 * so these fields simply act as a fallback control surface.
 */
final class MetaBox implements Bootable {

	private const FIELDS = [
		'_gw_seo_title'     => 'text',
		'_gw_seo_desc'      => 'textarea',
		'_gw_seo_focus'     => 'text',
		'_gw_seo_canonical' => 'url',
		'_gw_seo_noindex'   => 'checkbox',
	];

	public function boot(): void {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	public function register(): void {
		foreach ( [ 'post', 'page', 'product' ] as $type ) {
			add_meta_box( 'gw-seo-box', __( 'SEO — Green World', 'greenworld' ), [ $this, 'render' ], $type, 'normal', 'default' );
		}
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'gw_seo_save', 'gw_seo_nonce' );
		$title  = (string) get_post_meta( $post->ID, '_gw_seo_title', true );
		$desc   = (string) get_post_meta( $post->ID, '_gw_seo_desc', true );
		$focus  = (string) get_post_meta( $post->ID, '_gw_seo_focus', true );
		$canon  = (string) get_post_meta( $post->ID, '_gw_seo_canonical', true );
		$nodex  = '1' === (string) get_post_meta( $post->ID, '_gw_seo_noindex', true );
		?>
		<style>.gw-seo-field{margin:0 0 14px}.gw-seo-field label{display:block;font-weight:600;margin-bottom:4px}.gw-seo-field input[type=text],.gw-seo-field input[type=url],.gw-seo-field textarea{width:100%}.gw-seo-hint{color:#666;font-size:12px;margin-top:3px}</style>
		<div class="gw-seo-field">
			<label for="gw_seo_title"><?php esc_html_e( 'SEO title', 'greenworld' ); ?></label>
			<input type="text" id="gw_seo_title" name="_gw_seo_title" value="<?php echo esc_attr( $title ); ?>" maxlength="70" placeholder="<?php esc_attr_e( 'Leave blank to use the automatic title', 'greenworld' ); ?>" />
			<p class="gw-seo-hint"><?php esc_html_e( 'Aim for about 60 characters. Blank = automatic template.', 'greenworld' ); ?></p>
		</div>
		<div class="gw-seo-field">
			<label for="gw_seo_desc"><?php esc_html_e( 'Meta description', 'greenworld' ); ?></label>
			<textarea id="gw_seo_desc" name="_gw_seo_desc" rows="3" maxlength="200" placeholder="<?php esc_attr_e( 'Leave blank to auto-generate from the content', 'greenworld' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
			<p class="gw-seo-hint"><?php esc_html_e( 'Aim for about 155 characters. Describe the page honestly; no medical claims.', 'greenworld' ); ?></p>
		</div>
		<div class="gw-seo-field">
			<label for="gw_seo_focus"><?php esc_html_e( 'Focus topic / keyword', 'greenworld' ); ?></label>
			<input type="text" id="gw_seo_focus" name="_gw_seo_focus" value="<?php echo esc_attr( $focus ); ?>" />
			<p class="gw-seo-hint"><?php esc_html_e( 'For your own reference and future SEO reporting.', 'greenworld' ); ?></p>
		</div>
		<div class="gw-seo-field">
			<label for="gw_seo_canonical"><?php esc_html_e( 'Canonical URL override', 'greenworld' ); ?></label>
			<input type="url" id="gw_seo_canonical" name="_gw_seo_canonical" value="<?php echo esc_attr( $canon ); ?>" placeholder="<?php esc_attr_e( 'Leave blank for the self-referencing canonical', 'greenworld' ); ?>" />
		</div>
		<div class="gw-seo-field">
			<label><input type="checkbox" name="_gw_seo_noindex" value="1" <?php checked( $nodex ); ?> /> <?php esc_html_e( 'Hide this page from search engines (noindex)', 'greenworld' ); ?></label>
		</div>
		<?php
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save( $post_id, $post ): void {
		if ( ! isset( $_POST['gw_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['gw_seo_nonce'] ) ), 'gw_seo_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( self::FIELDS as $key => $type ) {
			if ( 'checkbox' === $type ) {
				if ( isset( $_POST[ $key ] ) ) {
					update_post_meta( $post_id, $key, '1' );
				} else {
					delete_post_meta( $post_id, $key );
				}
				continue;
			}
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			if ( 'url' === $type ) {
				$val = esc_url_raw( (string) $raw );
			} elseif ( 'textarea' === $type ) {
				$val = sanitize_textarea_field( (string) $raw );
			} else {
				$val = sanitize_text_field( (string) $raw );
			}
			if ( '' === $val ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $val );
			}
		}
	}
}
