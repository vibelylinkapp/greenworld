<?php
declare( strict_types=1 );

namespace GreenWorld\Performance;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Core Web Vitals optimizations: preload, resource hints, lazy media, emoji
 * removal, and self-hosted-font preconnect. Complements a caching plugin —
 * does not duplicate page caching.
 */
final class Optimizer implements Bootable {

	public function boot(): void {
		add_action( 'wp_head', [ $this, 'preload' ], 1 );
		add_filter( 'wp_resource_hints', [ $this, 'resource_hints' ], 10, 2 );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		add_filter( 'wp_lazy_loading_enabled', '__return_true' );
		add_action( 'init', [ $this, 'trim_head' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'trim_block_styles' ], 100 );
		add_action( 'wp_enqueue_scripts', [ $this, 'trim_wishlist_widget' ], 100 );
		add_filter( 'get_custom_logo', [ $this, 'lighten_logo' ], 20 );
	}

	/**
	 * Remove legacy head bloat (RSD, WLW manifest, shortlink, adjacent post
	 * links) that adds requests and leaks endpoints without SEO value.
	 */
	public function trim_head(): void {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
	}

	public function preload(): void {
		printf( '<link rel="preload" href="%s" as="style" />' . "\n", esc_url( GREENWORLD_URI . 'assets/css/main.css' ) );
		if ( is_front_page() ) {
			// Preload the actual first hero-carousel slide (the homepage LCP
			// element) so it is discoverable immediately and fetched at high
			// priority. Falls back to the legacy single-hero setting.
			$hero = class_exists( '\\GreenWorld\\Front\\Home' ) ? \GreenWorld\Front\Home::first_hero_image() : '';
			if ( '' === $hero ) {
				$hero = (string) get_theme_mod( 'gw_hero_image', GREENWORLD_URI . 'assets/img/hero.jpg' );
			}
			if ( '' !== $hero ) {
				printf( '<link rel="preload" href="%s" as="image" fetchpriority="high" />' . "\n", esc_url( $hero ) );
			}
		}
	}

	/**
	 * Preconnect to Google Maps only on the contact page, where the map iframe
	 * loads. The theme otherwise uses a system font stack, so there are no font
	 * origins worth hinting.
	 *
	 * @param array<int,string> $hints
	 * @return array<int,string>
	 */
	public function resource_hints( array $hints, string $relation ): array {
		if ( 'preconnect' === $relation && ( is_page( 'contact-us' ) || is_page( 'contact' ) ) ) {
			$hints[] = 'https://www.google.com';
			$hints[] = 'https://maps.gstatic.com';
		}
		return $hints;
	}

	public function trim_block_styles(): void {
		if ( is_admin() ) {
			return;
		}
		$post       = get_post();
		$has_blocks = ( is_object( $post ) && isset( $post->post_content ) ) ? has_blocks( (string) $post->post_content ) : false;
		if ( false === $has_blocks ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			// WooCommerce Blocks CSS is only needed by block-based Cart/Checkout
			// or product blocks (pages that contain blocks, exempted above). The
			// PHP-rendered homepage and archives do not use it.
			wp_dequeue_style( 'wc-blocks-style' );
		}
	}

	/**
	 * Keep the small header logo from being fetched at full size and from
	 * competing with the hero image for fetch priority.
	 */
	public function lighten_logo( string $html ): string {
		$html = str_replace( ' fetchpriority="high"', '', $html );
		$html = (string) preg_replace( '/ sizes="[^"]*"/', ' sizes="(max-width: 640px) 120px, 160px"', $html );
		return $html;
	}

	/**
	 * The YITH Wishlist React widget fires a guest REST call to wishlist/v1/lists
	 * that returns 401 and stalls (~1s) — the visible loading spinner. This theme
	 * ships its own localStorage wishlist, so YITH's frontend bundle is dead
	 * weight on browse pages. Drop it on the homepage and shop/category/tag
	 * archives; leave it intact everywhere else (e.g. the dedicated wishlist page).
	 */
	public function trim_wishlist_widget(): void {
		if ( is_admin() ) {
			return;
		}
		$browse = is_front_page() || ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) );
		if ( ! $browse ) {
			return;
		}
		$needles = [ 'wcwl', 'yith-woocommerce-wishlist', 'add-to-wishlist' ];
		$this->drop_by_src( wp_scripts(), $needles, 'script' );
		$this->drop_by_src( wp_styles(), $needles, 'style' );
	}

	/**
	 * Dequeue any registered script/style whose source URL contains one of the
	 * given needles. Matching by src (not handle) is robust to plugin handle
	 * changes. Dequeue only — never deregister — so dependents are unaffected.
	 *
	 * @param \WP_Dependencies|null $reg
	 * @param array<int,string>     $needles
	 */
	private function drop_by_src( $reg, array $needles, string $type ): void {
		if ( ! $reg || empty( $reg->registered ) ) {
			return;
		}
		foreach ( $reg->registered as $handle => $dep ) {
			$src = isset( $dep->src ) ? (string) $dep->src : '';
			if ( '' === $src ) {
				continue;
			}
			foreach ( $needles as $needle ) {
				if ( false !== stripos( $src, $needle ) ) {
					if ( 'script' === $type ) {
						wp_dequeue_script( $handle );
					} else {
						wp_dequeue_style( $handle );
					}
					break;
				}
			}
		}
	}
}
