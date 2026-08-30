<?php
declare( strict_types=1 );

namespace GreenWorld\Seo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Head meta layer: dynamic SEO titles, meta descriptions, canonical URLs,
 * Open Graph + Twitter cards, and robots directives.
 *
 * Reads per-post overrides written by the SEO Control Center meta box
 * (_gw_seo_title, _gw_seo_desc, _gw_seo_canonical, _gw_seo_noindex).
 * Yields to Yoast / Rank Math when active (override: greenworld_force_meta).
 */
final class Meta implements Bootable {

	public function boot(): void {
		if ( $this->seo_plugin_active() && false === (bool) apply_filters( 'greenworld_force_meta', false ) ) {
			return; // Let the dedicated SEO plugin own titles, canonical and social meta.
		}
		add_filter( 'pre_get_document_title', [ $this, 'manual_title' ], 20 );
		add_filter( 'document_title_parts', [ $this, 'title_parts' ], 20 );
		add_filter( 'wp_robots', [ $this, 'robots' ], 20 );
		add_action( 'wp_head', [ $this, 'head' ], 6 );
	}

	private function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/* ------------------------------------------------------------------ */
	/* Title                                                              */
	/* ------------------------------------------------------------------ */

	public function manual_title( $title ) {
		$override = $this->post_meta( '_gw_seo_title' );
		return '' !== $override ? $override : $title;
	}

	/**
	 * @param array<string,string> $parts
	 * @return array<string,string>
	 */
	public function title_parts( $parts ) {
		$site = get_bloginfo( 'name' );

		if ( is_front_page() ) {
			$parts['title']   = $site;
			$parts['tagline'] = apply_filters( 'greenworld_home_title_suffix', 'Health & Natural Wellness Products Kenya' );
			return $parts;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			$brand = ( $product instanceof \WC_Product ) ? (string) $product->get_attribute( 'brand' ) : '';
			$parts['title'] = trim( (string) $parts['title'] );
			$parts['site']  = '' !== $brand ? $brand . ' | ' . $site : $site;
			return $parts;
		}
		if ( ( function_exists( 'is_product_category' ) && is_product_category() ) || is_category() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$parts['title'] = $term->name . ' ' . __( 'in Kenya', 'greenworld' );
			}
			$parts['site'] = $site;
			return $parts;
		}
		return $parts;
	}

	/* ------------------------------------------------------------------ */
	/* Robots                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @param array<string,bool> $robots
	 * @return array<string,bool>
	 */
	public function robots( $robots ) {
		if ( is_search() || is_404() ) {
			$robots['noindex']  = true;
			$robots['follow']   = true;
			unset( $robots['index'] );
		}
		// Thin utility endpoints should never be indexed.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		if ( '1' === $this->post_meta( '_gw_seo_noindex' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		$robots['max-image-preview'] = 'large';
		return $robots;
	}

	/* ------------------------------------------------------------------ */
	/* Head output                                                        */
	/* ------------------------------------------------------------------ */

	public function head(): void {
		$desc = $this->description();
		if ( '' !== $desc ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
		}

		$canonical = $this->canonical();
		if ( '' !== $canonical && ! is_singular() ) {
			// Core rel_canonical already covers singular; emit for archives/shop/home.
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}
		$manual_canon = $this->post_meta( '_gw_seo_canonical' );
		if ( '' !== $manual_canon ) {
			remove_action( 'wp_head', 'rel_canonical' );
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $manual_canon ) );
		}

		$title = wp_get_document_title();
		$url   = '' !== $canonical ? $canonical : home_url( add_query_arg( [], $GLOBALS['wp']->request ?? '' ) );
		$img   = $this->social_image();
		$is_product = function_exists( 'is_product' ) && is_product();

		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( str_replace( '-', '_', str_replace( '_', '-', (string) get_locale() ) ) ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $desc ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		}
		printf( '<meta property="og:type" content="%s" />' . "\n", $is_product ? 'product' : ( is_singular( 'post' ) ? 'article' : 'website' ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		if ( '' !== $img ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) );
		}

		if ( $is_product ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				printf( '<meta property="product:price:amount" content="%s" />' . "\n", esc_attr( (string) $product->get_price() ) );
				printf( '<meta property="product:price:currency" content="%s" />' . "\n", esc_attr( get_woocommerce_currency() ) );
				printf( '<meta property="product:availability" content="%s" />' . "\n", $product->is_in_stock() ? 'in stock' : 'out of stock' );
			}
		}

		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $desc ) {
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
		}
		if ( '' !== $img ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $img ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Value helpers                                                      */
	/* ------------------------------------------------------------------ */

	private function description(): string {
		$override = $this->post_meta( '_gw_seo_desc' );
		if ( '' !== $override ) {
			return $this->trim_desc( $override );
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$d = $product->get_short_description() ?: $product->get_description();
				$d = $this->trim_desc( wp_strip_all_tags( $d ) );
				if ( '' !== $d ) {
					return $d;
				}
			}
		}
		if ( ( function_exists( 'is_product_category' ) && is_product_category() ) || is_category() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term && '' !== trim( (string) $term->description ) ) {
				return $this->trim_desc( wp_strip_all_tags( $term->description ) );
			}
			if ( $term instanceof \WP_Term ) {
				return $this->trim_desc( sprintf( 'Shop %s in Kenya at Green World Health Solutions. Quality health and wellness products with delivery across Kenya and payment by M-Pesa, bank transfer or cash on delivery.', $term->name ) );
			}
		}
		if ( is_singular() ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Post ) {
				$d = has_excerpt( $obj ) ? get_the_excerpt( $obj ) : $obj->post_content;
				$d = $this->trim_desc( wp_strip_all_tags( $d ) );
				if ( '' !== $d ) {
					return $d;
				}
			}
		}
		return $this->trim_desc( (string) get_bloginfo( 'description' ) );
	}

	private function trim_desc( string $d ): string {
		$d = trim( (string) preg_replace( '/\s+/', ' ', $d ) );
		if ( strlen( $d ) > 160 ) {
			$d = substr( $d, 0, 157 ) . '...';
		}
		return $d;
	}

	private function canonical(): string {
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$l = get_term_link( $term );
				return is_wp_error( $l ) ? '' : (string) $l;
			}
		}
		return '';
	}

	private function social_image(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			return (string) get_the_post_thumbnail_url( null, 'large' );
		}
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id > 0 ) {
			$u = wp_get_attachment_image_url( $id, 'full' );
			if ( is_string( $u ) ) {
				return $u;
			}
		}
		return trailingslashit( get_template_directory_uri() ) . 'assets/img/logo-badge.png';
	}

	private function post_meta( string $key ): string {
		if ( ! is_singular() ) {
			return '';
		}
		$v = get_post_meta( get_queried_object_id(), $key, true );
		return is_string( $v ) ? trim( $v ) : '';
	}
}
