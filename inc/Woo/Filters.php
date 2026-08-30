<?php
declare( strict_types=1 );

namespace GreenWorld\Woo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 4 layered filters. Renders a filter sidebar (categories, price, rating,
 * product attributes) that drives WooCommerce's native URL-based filtering, plus
 * an AJAX-free "Load more" button after the loop.
 */
final class Filters implements Bootable {

	public function boot(): void {
		add_action( 'woocommerce_after_shop_loop', array( $this, 'load_more' ), 20 );
	}

	public function load_more(): void {
		global $wp_query;
		$max = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 0;
		$cur = max( 1, (int) get_query_var( 'paged' ) );
		if ( $max > 1 && $cur < $max ) {
			$next = esc_url( get_pagenum_link( $cur + 1 ) );
			echo '<div class="gw-loadmore"><button type="button" class="button gw-loadmore__btn" data-gw-loadmore data-next="' . $next . '">' . esc_html__( 'Load more products', 'greenworld' ) . '</button></div>';
		}
	}

	private static function base_url(): string {
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) === false ) {
					return (string) $link;
				}
			}
		}
		$shop = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		return $shop > 0 ? (string) get_permalink( $shop ) : home_url( '/' );
	}

	private static function current_list( string $param ): array {
		if ( isset( $_GET[ $param ] ) === false ) {
			return array();
		}
		$raw = explode( ',', (string) wp_unslash( $_GET[ $param ] ) );
		return array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) );
	}

	private static function is_active( string $param, string $value ): bool {
		return in_array( $value, self::current_list( $param ), true );
	}

	private static function toggle_url( string $param, string $value ): string {
		$list = self::current_list( $param );
		if ( in_array( $value, $list, true ) ) {
			$list = array_values( array_diff( $list, array( $value ) ) );
		} else {
			$list[] = $value;
		}
		$args = array();
		foreach ( $_GET as $k => $v ) {
			if ( $k === 'paged' || $k === $param ) {
				continue;
			}
			$args[ sanitize_key( (string) $k ) ] = sanitize_text_field( (string) wp_unslash( $v ) );
		}
		if ( count( $list ) > 0 ) {
			$args[ $param ] = implode( ',', $list );
		}
		return esc_url( add_query_arg( $args, self::base_url() ) );
	}

	public static function panel(): void {
		if ( function_exists( 'is_shop' ) === false ) {
			return;
		}
		echo '<div class="gw-filters__head"><span>' . esc_html__( 'Filter products', 'greenworld' ) . '</span><button type="button" class="gw-filters__close" data-gw-filters-toggle aria-label="' . esc_attr__( 'Close filters', 'greenworld' ) . '">&times;</button></div>';

		$has = false;
		foreach ( array_keys( $_GET ) as $gk ) {
			if ( in_array( (string) $gk, array( 'min_price', 'max_price', 'rating_filter' ), true ) || strpos( (string) $gk, 'filter_' ) === 0 ) {
				$has = true;
				break;
			}
		}
		if ( $has ) {
			echo '<a class="gw-filters__clear" href="' . esc_url( self::base_url() ) . '">' . esc_html__( 'Clear all filters', 'greenworld' ) . '</a>';
		}

		$cats = \GreenWorld\Support\Cache::remember( 'gw_filter_cats_20', 6 * HOUR_IN_SECONDS, static function () { $r = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 20 ) ); return is_array( $r ) ? $r : array(); } );
		if ( is_array( $cats ) && count( $cats ) > 0 ) {
			$cur_cat = ( function_exists( 'is_product_category' ) && is_product_category() ) ? (int) get_queried_object_id() : 0;
			echo '<div class="gw-filters__group"><h3>' . esc_html__( 'Categories', 'greenworld' ) . '</h3><ul class="gw-filters__cats">';
			foreach ( $cats as $c ) {
				$link = get_term_link( $c );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$cls = ( (int) $c->term_id === $cur_cat ) ? ' class="is-active"' : '';
				printf( '<li%s><a href="%s">%s<span>%d</span></a></li>', $cls, esc_url( (string) $link ), esc_html( $c->name ), (int) $c->count );
			}
			echo '</ul></div>';
		}

		$min = isset( $_GET['min_price'] ) ? esc_attr( sanitize_text_field( (string) wp_unslash( $_GET['min_price'] ) ) ) : '';
		$max = isset( $_GET['max_price'] ) ? esc_attr( sanitize_text_field( (string) wp_unslash( $_GET['max_price'] ) ) ) : '';
		echo '<div class="gw-filters__group"><h3>' . esc_html__( 'Price (KSh)', 'greenworld' ) . '</h3>';
		echo '<form method="get" class="gw-filters__price" action="' . esc_url( self::base_url() ) . '">';
		foreach ( $_GET as $k => $v ) {
			if ( in_array( (string) $k, array( 'min_price', 'max_price', 'paged' ), true ) ) {
				continue;
			}
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( sanitize_key( (string) $k ) ), esc_attr( sanitize_text_field( (string) wp_unslash( $v ) ) ) );
		}
		printf( '<input type="number" name="min_price" value="%s" placeholder="%s" min="0"><input type="number" name="max_price" value="%s" placeholder="%s" min="0"><button type="submit" class="button">%s</button>', $min, esc_attr__( 'Min', 'greenworld' ), $max, esc_attr__( 'Max', 'greenworld' ), esc_html__( 'Go', 'greenworld' ) );
		echo '</form></div>';

		echo '<div class="gw-filters__group"><h3>' . esc_html__( 'Rating', 'greenworld' ) . '</h3><ul class="gw-filters__rating">';
		for ( $r = 5; $r >= 1; $r-- ) {
			$active = self::is_active( 'rating_filter', (string) $r );
			$label  = esc_html( sprintf( _n( '%d star & up', '%d stars & up', $r, 'greenworld' ), $r ) );
			printf( '<li class="%s"><a href="%s"><span class="gw-check"></span>%s</a></li>', $active ? 'is-active' : '', self::toggle_url( 'rating_filter', (string) $r ), $label );
		}
		echo '</ul></div>';

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $attr ) {
				$tax   = wc_attribute_taxonomy_name( $attr->attribute_name );
				$terms = \GreenWorld\Support\Cache::remember( 'gw_filter_attr_' . $tax, 6 * HOUR_IN_SECONDS, static function () use ( $tax ) { $r = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true, 'number' => 30 ) ); return is_array( $r ) ? $r : array(); } );
				if ( is_array( $terms ) === false || count( $terms ) === 0 ) {
					continue;
				}
				$param = 'filter_' . $attr->attribute_name;
				echo '<div class="gw-filters__group"><h3>' . esc_html( wc_attribute_label( $tax ) ) . '</h3><ul class="gw-filters__attr">';
				foreach ( $terms as $t ) {
					$active = self::is_active( $param, $t->slug );
					printf( '<li class="%s"><a href="%s"><span class="gw-check"></span>%s<span>%d</span></a></li>', $active ? 'is-active' : '', self::toggle_url( $param, $t->slug ), esc_html( $t->name ), (int) $t->count );
				}
				echo '</ul></div>';
			}
		}
	}
}
