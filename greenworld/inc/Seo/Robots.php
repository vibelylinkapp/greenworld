<?php
declare( strict_types=1 );

namespace GreenWorld\Seo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Crawl + indexing infrastructure: robots.txt rules, XML sitemap hygiene, and
 * image SEO (alt-text fallbacks, lazy loading, async decoding).
 *
 * Complements WordPress core sitemaps and the Schema/Meta modules; it defers to
 * a dedicated SEO plugin's robots.txt handling when one is active.
 */
final class Robots implements Bootable {

	public function boot(): void {
		add_filter( 'robots_txt', [ $this, 'robots_txt' ], 20, 2 );

		// Keep utility pages out of the XML sitemap.
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'sitemap_exclude' ], 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', [ $this, 'drop_user_sitemap' ], 10, 2 );

		// Image SEO.
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'image_attrs' ], 20, 3 );
	}

	private function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * @param string $output
	 * @param bool   $public
	 */
	public function robots_txt( $output, $public ): string {
		if ( ! $public || $this->seo_plugin_active() ) {
			return $output; // Blog is private, or a plugin owns robots.txt.
		}
		$lines   = [];
		$lines[] = 'User-agent: *';
		$lines[] = 'Disallow: /wp-admin/';
		$lines[] = 'Allow: /wp-admin/admin-ajax.php';
		$lines[] = 'Disallow: /cart/';
		$lines[] = 'Disallow: /checkout/';
		$lines[] = 'Disallow: /my-account/';
		$lines[] = 'Disallow: /*add-to-cart=';
		$lines[] = 'Disallow: /*?orderby=';
		$lines[] = 'Disallow: /*?filter_';
		$lines[] = 'Disallow: /*?s=';
		$lines[] = 'Disallow: /?s=';
		$lines[] = 'Disallow: /search/';
		$lines[] = '';
		$sitemap = function_exists( 'get_sitemap_url' ) ? get_sitemap_url( 'index' ) : home_url( '/wp-sitemap.xml' );
		if ( is_string( $sitemap ) && '' !== $sitemap ) {
			$lines[] = 'Sitemap: ' . $sitemap;
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Exclude cart, checkout and my-account from the posts sitemap.
	 *
	 * @param array<string,mixed> $args
	 * @param string              $post_type
	 * @return array<string,mixed>
	 */
	public function sitemap_exclude( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$exclude = [];
		foreach ( [ 'cart', 'checkout', 'myaccount' ] as $key ) {
			if ( function_exists( 'wc_get_page_id' ) ) {
				$id = wc_get_page_id( $key );
				if ( $id > 0 ) {
					$exclude[] = $id;
				}
			}
		}
		if ( count( $exclude ) > 0 ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : [], $exclude );
		}
		return $args;
	}

	/**
	 * Drop the users sitemap — an ecommerce store has no author archives worth indexing.
	 *
	 * @param mixed  $provider
	 * @param string $name
	 * @return mixed
	 */
	public function drop_user_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Ensure every image carries descriptive alt text, lazy loading and async decoding.
	 *
	 * @param array<string,string> $attr
	 * @param \WP_Post              $attachment
	 * @param string|array         $size
	 * @return array<string,string>
	 */
	public function image_attrs( $attr, $attachment, $size ) {
		if ( ! isset( $attr['alt'] ) || '' === trim( (string) $attr['alt'] ) ) {
			$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
			if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
				$alt = get_the_title( $attachment->ID );
				$parent = wp_get_post_parent_id( $attachment->ID );
				if ( $parent && 'product' === get_post_type( $parent ) ) {
					$alt = get_the_title( $parent );
				}
			}
			$attr['alt'] = trim( (string) $alt );
		}
		if ( ! isset( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( ! isset( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}
}
