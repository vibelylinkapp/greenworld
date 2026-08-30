<?php
declare( strict_types=1 );

namespace GreenWorld\Content;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * InternalLinks — renders the deliberate internal-link "power structure" that
 * connects Home -> pillars -> categories -> products -> guides -> FAQs, plus the
 * reverse links (guide -> categories/products, product -> guide/FAQs).
 *
 * Every relationship comes from Relations/TopicMap, so the on-page links mirror
 * the Schema graph exactly. All markup is rendered from shortcodes (usable in
 * seeded pages, the block editor or Elementor) and from a small set of hooks on
 * the WooCommerce product and category templates.
 */
final class InternalLinks implements Bootable {

	private static bool $css_done = false;

	public function boot(): void {
		add_shortcode( 'gw_pillars', [ $this, 'sc_pillars' ] );
		add_shortcode( 'gw_featured_products', [ $this, 'sc_products' ] );
		add_shortcode( 'gw_related_categories', [ $this, 'sc_related_cats' ] );
		add_shortcode( 'gw_guides', [ $this, 'sc_guides' ] );
		add_shortcode( 'gw_faq', [ $this, 'sc_faq' ] );
		add_shortcode( 'gw_guide_hub', [ $this, 'sc_hub' ] );

		// Power structure on WooCommerce templates.
		add_action( 'woocommerce_before_shop_loop', [ $this, 'category_shop_by_need' ], 5 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'product_context' ], 25 );
		add_action( 'woocommerce_after_shop_loop', [ $this, 'category_context' ], 20 );
	}

	/**
	 * "Shop by need" chips on a product-category archive: the child categories
	 * of the current category, so a broad landing page (e.g. Men's Health) links
	 * down into its sub-needs (Men's Vitality, Prostate Wellness, ...). Turns a
	 * bare product grid into a stronger topical/entity landing page for SEO.
	 */
	public function category_shop_by_need(): void {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$children = get_terms( [
			'taxonomy'   => 'product_cat',
			'parent'     => $term->term_id,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 12,
		] );
		if ( ! is_array( $children ) || count( $children ) === 0 ) {
			return;
		}
		$this->styles();
		echo '<nav class="gw-shopneed" aria-label="' . esc_attr__( 'Shop by need', 'greenworld' ) . '">';
		echo '<span class="gw-shopneed__label">' . esc_html__( 'Shop by need', 'greenworld' ) . '</span>';
		echo '<ul class="gw-shopneed__list">';
		foreach ( $children as $c ) {
			$link = get_term_link( $c );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			printf( '<li><a href="%s">%s<span>%d</span></a></li>', esc_url( (string) $link ), esc_html( $c->name ), (int) $c->count );
		}
		echo '</ul></nav>';
	}

	/* ------------------------------------------------------------------ */
	/* Shortcodes                                                          */
	/* ------------------------------------------------------------------ */

	public function sc_pillars( $atts ): string {
		$this->styles();
		$out = '<nav class="gw-pillars" aria-label="' . esc_attr__( 'Product categories', 'greenworld' ) . '"><ul class="gw-pillars__grid">';
		foreach ( TopicMap::pillars() as $key => $p ) {
			$url = Relations::pillar_url( TopicMap::pillar( $key ) ?? $p );
			if ( '' === $url ) {
				continue;
			}
			$out .= '<li class="gw-pillars__item"><a href="' . esc_url( $url ) . '"><span class="gw-pillars__label">' . esc_html( (string) $p['label'] ) . '</span><span class="gw-pillars__blurb">' . esc_html( (string) $p['blurb'] ) . '</span></a></li>';
		}
		$out .= '</ul></nav>';
		return $out;
	}

	public function sc_products( $atts ): string {
		$a   = shortcode_atts( [ 'cat' => '', 'n' => 8 ], $atts, 'gw_featured_products' );
		$cat = sanitize_title( (string) $a['cat'] );
		if ( '' === $cat ) {
			return '';
		}
		$products = Relations::products_in_cat( $cat, (int) $a['n'] );
		if ( count( $products ) === 0 ) {
			return '';
		}
		$this->styles();
		$cat_url = Relations::category_url( $cat );
		$out     = '<div class="gw-prodgrid">';
		foreach ( $products as $post ) {
			$out .= $this->product_card( (int) $post->ID );
		}
		$out .= '</div>';
		if ( '' !== $cat_url ) {
			$out .= '<p class="gw-more"><a href="' . esc_url( $cat_url ) . '">' . esc_html__( 'View all', 'greenworld' ) . ' ' . esc_html( $this->cat_label( $cat ) ) . ' &rarr;</a></p>';
		}
		return $out;
	}

	public function sc_related_cats( $atts ): string {
		$a   = shortcode_atts( [ 'pillar' => '', 'n' => 3 ], $atts, 'gw_related_categories' );
		$key = sanitize_key( (string) $a['pillar'] );
		$rel = Relations::related_categories( $key, (int) $a['n'] );
		if ( count( $rel ) === 0 ) {
			return '';
		}
		$this->styles();
		$out = '<section class="gw-related"><h2 class="gw-related__title">' . esc_html__( 'Related categories', 'greenworld' ) . '</h2><ul class="gw-related__grid">';
		foreach ( $rel as $r ) {
			$out .= '<li><a href="' . esc_url( $r['url'] ) . '"><strong>' . esc_html( $r['label'] ) . '</strong><span>' . esc_html( $r['blurb'] ) . '</span></a></li>';
		}
		$out .= '</ul></section>';
		return $out;
	}

	public function sc_guides( $atts ): string {
		$a = shortcode_atts( [ 'pillar' => '', 'topic' => '', 'n' => 4 ], $atts, 'gw_guides' );
		if ( '' !== $a['pillar'] ) {
			$guides = Relations::guides_for_pillar( sanitize_key( (string) $a['pillar'] ), (int) $a['n'] );
		} else {
			$guides = Relations::guides_for_topic( sanitize_title( (string) $a['topic'] ), (int) $a['n'] );
		}
		if ( count( $guides ) === 0 ) {
			return '';
		}
		$this->styles();
		$out = '<section class="gw-guides"><h2 class="gw-guides__title">' . esc_html__( 'Guides & resources', 'greenworld' ) . '</h2><ul class="gw-guides__grid">';
		foreach ( $guides as $g ) {
			$excerpt = has_excerpt( $g ) ? get_the_excerpt( $g ) : wp_trim_words( wp_strip_all_tags( (string) $g->post_content ), 22 );
			$out    .= '<li><a href="' . esc_url( (string) get_permalink( $g ) ) . '"><strong>' . esc_html( get_the_title( $g ) ) . '</strong><span>' . esc_html( $excerpt ) . '</span></a></li>';
		}
		$out .= '</ul></section>';
		return $out;
	}

	/**
	 * Renders visible FAQ markup as <h3>/<p> pairs. Note: for FAQPage schema the
	 * same Q&A must exist in the page's stored content — the Seeder inlines it,
	 * and Schema also reads the pillar FAQ bank directly, so both stay in sync.
	 */
	public function sc_faq( $atts ): string {
		$a    = shortcode_atts( [ 'pillar' => '', 'set' => '' ], $atts, 'gw_faq' );
		$faqs = self::faq_bank( sanitize_key( (string) $a['pillar'] ), sanitize_key( (string) $a['set'] ) );
		if ( count( $faqs ) === 0 ) {
			return '';
		}
		$this->styles();
		$out = '<section class="gw-faq" aria-label="' . esc_attr__( 'Frequently asked questions', 'greenworld' ) . '"><h2 class="gw-faq__title">' . esc_html__( 'Frequently asked questions', 'greenworld' ) . '</h2>';
		foreach ( $faqs as $qa ) {
			$out .= '<div class="gw-faq__item"><h3>' . esc_html( (string) $qa[0] ) . '</h3><p>' . esc_html( (string) $qa[1] ) . '</p></div>';
		}
		$out .= '</section>';
		return $out;
	}

	public function sc_hub( $atts ): string {
		$this->styles();
		$out = '<div class="gw-hub">';

		// Topics.
		$out .= '<section class="gw-hub__topics"><h2>' . esc_html__( 'Browse by topic', 'greenworld' ) . '</h2><ul class="gw-related__grid">';
		foreach ( TopicMap::hub_topics() as $slug => $label ) {
			$term = get_term_by( 'slug', $slug, TopicMap::GUIDE_TAX );
			$url  = ( $term instanceof \WP_Term && ! is_wp_error( get_term_link( $term ) ) ) ? (string) get_term_link( $term ) : Relations::hub_url();
			$out .= '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( (string) $label ) . '</strong></a></li>';
		}
		$out .= '</ul></section>';

		// Featured guides.
		$guides = Relations::guides_for_topic( '', 8 );
		if ( count( $guides ) > 0 ) {
			$out .= '<section class="gw-guides"><h2>' . esc_html__( 'Latest guides', 'greenworld' ) . '</h2><ul class="gw-guides__grid">';
			foreach ( $guides as $g ) {
				$excerpt = has_excerpt( $g ) ? get_the_excerpt( $g ) : wp_trim_words( wp_strip_all_tags( (string) $g->post_content ), 22 );
				$out    .= '<li><a href="' . esc_url( (string) get_permalink( $g ) ) . '"><strong>' . esc_html( get_the_title( $g ) ) . '</strong><span>' . esc_html( $excerpt ) . '</span></a></li>';
			}
			$out .= '</ul></section>';
		}

		// Shop the pillars.
		$out .= '<section class="gw-hub__shop"><h2>' . esc_html__( 'Shop by category', 'greenworld' ) . '</h2>' . $this->sc_pillars( [] ) . '</section>';
		$out .= '</div>';
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Power-structure hooks                                               */
	/* ------------------------------------------------------------------ */

	public function product_context(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$pid    = (int) get_the_ID();
		$pillar = Relations::pillar_for_product( $pid );
		if ( null === $pillar ) {
			return;
		}
		$this->styles();
		echo '<section class="gw-product-context">';

		// Parent category link + related categories.
		$cat_url = Relations::category_url( (string) $pillar['cat'] );
		if ( '' !== $cat_url ) {
			echo '<p class="gw-product-context__parent">' . esc_html__( 'Part of', 'greenworld' ) . ' <a href="' . esc_url( $cat_url ) . '">' . esc_html( (string) $pillar['label'] ) . '</a></p>';
		}

		// Relevant educational guide.
		$guide = Relations::guide_for_product( $pid );
		if ( $guide instanceof \WP_Post ) {
			echo '<p class="gw-product-context__guide">' . esc_html__( 'Learn more:', 'greenworld' ) . ' <a href="' . esc_url( (string) get_permalink( $guide ) ) . '">' . esc_html( get_the_title( $guide ) ) . '</a></p>';
		}

		// Pillar FAQ (visible + schema-aligned via the pillar bank).
		echo do_shortcode( '[gw_faq pillar="' . esc_attr( (string) ( $pillar['key'] ?? '' ) ) . '"]' );

		// Related categories.
		echo do_shortcode( '[gw_related_categories pillar="' . esc_attr( (string) ( $pillar['key'] ?? '' ) ) . '"]' );

		echo '</section>';
	}

	public function category_context(): void {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$pillar = TopicMap::pillar_by_cat( $term->slug );
		if ( null === $pillar ) {
			return;
		}
		$this->styles();
		echo '<section class="gw-category-context">';
		echo do_shortcode( '[gw_guides pillar="' . esc_attr( (string) $pillar['key'] ) . '" n="3"]' );
		echo do_shortcode( '[gw_related_categories pillar="' . esc_attr( (string) $pillar['key'] ) . '"]' );
		echo do_shortcode( '[gw_faq pillar="' . esc_attr( (string) $pillar['key'] ) . '"]' );
		echo '</section>';
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faq_bank( string $pillarKey, string $set ): array {
		if ( 'general' === $set ) {
			return TopicMap::general_faqs();
		}
		$p = TopicMap::pillar( $pillarKey );
		if ( null !== $p && ! empty( $p['faqs'] ) ) {
			return (array) $p['faqs'];
		}
		return [];
	}

	private function product_card( int $pid ): string {
		$title = get_the_title( $pid );
		$url   = (string) get_permalink( $pid );
		$img   = has_post_thumbnail( $pid ) ? get_the_post_thumbnail( $pid, 'woocommerce_thumbnail', [ 'loading' => 'lazy' ] ) : '';
		$price = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $pid );
			if ( $product instanceof \WC_Product ) {
				$price = $product->get_price_html();
			}
		}
		$out  = '<a class="gw-prodcard" href="' . esc_url( $url ) . '">';
		$out .= '<span class="gw-prodcard__img">' . $img . '</span>';
		$out .= '<span class="gw-prodcard__title">' . esc_html( $title ) . '</span>';
		if ( '' !== $price ) {
			$out .= '<span class="gw-prodcard__price">' . wp_kses_post( $price ) . '</span>';
		}
		$out .= '</a>';
		return $out;
	}

	private function cat_label( string $slug ): string {
		$p = TopicMap::pillar_by_cat( $slug );
		if ( null !== $p ) {
			return (string) $p['label'];
		}
		$t = Relations::category( $slug );
		return $t instanceof \WP_Term ? $t->name : $slug;
	}

	public static function styles(): void {
		if ( self::$css_done ) {
			return;
		}
		self::$css_done = true;
		$css = '.gw-pillars__grid,.gw-related__grid,.gw-guides__grid{list-style:none;margin:1rem 0;padding:0;display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}'
			. '.gw-pillars__item a,.gw-related__grid a,.gw-guides__grid a{display:flex;flex-direction:column;gap:.25rem;padding:1rem 1.1rem;border:1px solid #e2e8e2;border-radius:14px;background:#fff;color:inherit;text-decoration:none;transition:box-shadow .15s,transform .15s}'
			. '.gw-pillars__item a:hover,.gw-related__grid a:hover,.gw-guides__grid a:hover{box-shadow:0 8px 24px rgba(11,79,46,.10);transform:translateY(-2px)}'
			. '.gw-pillars__label,.gw-related__grid strong,.gw-guides__grid strong{font-weight:700;color:#0b4f2e}'
			. '.gw-pillars__blurb,.gw-related__grid span,.gw-guides__grid span{font-size:.9rem;color:#4b5563}'
			. '.gw-prodgrid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin:1rem 0}'
			. '.gw-prodcard{display:flex;flex-direction:column;gap:.4rem;text-decoration:none;color:inherit;border:1px solid #eef2ee;border-radius:12px;padding:.6rem;background:#fff}'
			. '.gw-prodcard img{width:100%;height:auto;border-radius:8px}.gw-prodcard__title{font-size:.9rem;font-weight:600}.gw-prodcard__price{color:#0b4f2e;font-weight:700}'
			. '.gw-faq__item{border-bottom:1px solid #eef2ee;padding:.6rem 0}.gw-faq__item h3{margin:0 0 .25rem;font-size:1.02rem;color:#0b4f2e}'
			. '.gw-product-context,.gw-category-context{margin-top:2rem;padding-top:1rem;border-top:2px solid #eef2ee}'
			. 'section.gw-related,section.gw-guides,section.gw-faq{margin:1.5rem 0}';
		wp_register_style( 'gw-topic-authority', false, [], GREENWORLD_VERSION );
		wp_enqueue_style( 'gw-topic-authority' );
		wp_add_inline_style( 'gw-topic-authority', $css );
	}
}
