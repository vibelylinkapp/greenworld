<?php
declare( strict_types=1 );

namespace GreenWorld\Seo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Visible breadcrumb trail that mirrors the BreadcrumbList JSON-LD emitted by
 * Schema.php (Home -> Shop -> category ancestors -> current). Improves UX and
 * keeps the visible path consistent with the structured data.
 *
 * Auto-injected on WooCommerce pages; available anywhere via [gw_breadcrumbs].
 */
final class Breadcrumbs implements Bootable {

	public function boot(): void {
		add_shortcode( 'gw_breadcrumbs', array( __CLASS__, 'render_sc' ) );
		add_action( 'init', array( $this, 'swap_woo' ) );
	}

	public function swap_woo(): void {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		add_action( 'woocommerce_before_main_content', array( __CLASS__, 'render' ), 20 );
	}

	public static function render_sc(): string {
		ob_start();
		self::render();
		return (string) ob_get_clean();
	}

	/** @return array<int,array<string,string>> */
	private static function items(): array {
		$items = array( array( 'name' => __( 'Home', 'greenworld' ), 'url' => home_url( '/' ) ) );
		$shop  = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

		if ( function_exists( 'is_product' ) && is_product() ) {
			$items[] = array( 'name' => __( 'Shop', 'greenworld' ), 'url' => $shop );
			$terms   = get_the_terms( get_the_ID(), 'product_cat' );
			if ( is_array( $terms ) && count( $terms ) > 0 ) {
				self::term_chain( $terms[0], $items, false );
			}
			$items[] = array( 'name' => (string) get_the_title(), 'url' => '' );
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$items[] = array( 'name' => __( 'Shop', 'greenworld' ), 'url' => $shop );
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				self::term_chain( $term, $items, true );
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$items[] = array( 'name' => __( 'Shop', 'greenworld' ), 'url' => '' );
		} elseif ( is_singular() || is_page() ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Post && $obj->post_parent > 0 ) {
				foreach ( array_reverse( get_post_ancestors( $obj ) ) as $anc ) {
					$items[] = array( 'name' => (string) get_the_title( (int) $anc ), 'url' => (string) get_permalink( (int) $anc ) );
				}
			}
			$items[] = array( 'name' => (string) get_the_title(), 'url' => '' );
		} else {
			return array();
		}
		return $items;
	}

	/** @param array<int,array<string,string>> $items */
	private static function term_chain( \WP_Term $term, array &$items, bool $last_is_current ): void {
		foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat' ) ) as $aid ) {
			$anc = get_term( (int) $aid, 'product_cat' );
			if ( $anc instanceof \WP_Term ) {
				$link = get_term_link( $anc );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( 'name' => $anc->name, 'url' => (string) $link );
				}
			}
		}
		$link    = get_term_link( $term );
		$url     = ( $last_is_current || is_wp_error( $link ) ) ? '' : (string) $link;
		$items[] = array( 'name' => $term->name, 'url' => $url );
	}

	public static function render(): void {
		if ( is_front_page() ) {
			return;
		}
		$items = self::items();
		if ( count( $items ) < 2 ) {
			return;
		}
		$n = count( $items );
		echo '<nav class="gw-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'greenworld' ) . '"><div class="gw-container gw-breadcrumbs__wrap"><ol>';
		foreach ( $items as $i => $it ) {
			$is_last = ( $i === $n - 1 );
			if ( $is_last || '' === $it['url'] ) {
				echo '<li aria-current="page">' . esc_html( $it['name'] ) . '</li>';
			} else {
				echo '<li><a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['name'] ) . '</a></li>';
			}
		}
		echo '</ol></div></nav>';
	}
}
