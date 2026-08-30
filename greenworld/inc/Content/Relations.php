<?php
declare( strict_types=1 );

namespace GreenWorld\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Relations — resolves the semantic network between categories, products,
 * guides and FAQs. These are read-only query helpers shared by the templates,
 * the InternalLinks renderer and the Schema graph, so the on-page relationships
 * and the structured-data relationships are always identical.
 */
final class Relations {

	public static function category( string $slug ): ?\WP_Term {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return null;
		}
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		return $t instanceof \WP_Term ? $t : null;
	}

	public static function category_url( string $slug ): string {
		$t = self::category( $slug );
		if ( $t instanceof \WP_Term ) {
			$link = get_term_link( $t );
			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}
		return '';
	}

	public static function landing_url( string $slug ): string {
		$page = get_page_by_path( $slug );
		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : '';
	}

	public static function hub_url(): string {
		$link = get_post_type_archive_link( TopicMap::GUIDE_CPT );
		return false !== $link ? (string) $link : home_url( '/' . TopicMap::HUB_SLUG . '/' );
	}

	/**
	 * The best canonical URL for a pillar: its landing page if built, otherwise
	 * its product category, otherwise the content hub.
	 *
	 * @param array<string,mixed> $pillar
	 */
	public static function pillar_url( array $pillar ): string {
		if ( ! empty( $pillar['landing'] ) ) {
			$u = self::landing_url( (string) $pillar['landing'] );
			if ( '' !== $u ) {
				return $u;
			}
		}
		if ( ! empty( $pillar['cat'] ) ) {
			$u = self::category_url( (string) $pillar['cat'] );
			if ( '' !== $u ) {
				return $u;
			}
		}
		return self::hub_url();
	}

	/**
	 * Published products in a category, catalog order.
	 *
	 * @return array<int,\WP_Post>
	 */
	public static function products_in_cat( string $slug, int $n = 8 ): array {
		if ( ! post_type_exists( 'product' ) ) {
			return [];
		}
		$q = new \WP_Query( [
			'post_type'      => 'product',
			'posts_per_page' => max( 1, $n ),
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'ignore_sticky_posts' => true,
			'tax_query'      => [
				[ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ],
			],
		] );
		return $q->posts;
	}

	/**
	 * Related pillars for a given pillar key, resolved to on-site URLs.
	 *
	 * @return array<int,array{key:string,label:string,url:string,blurb:string}>
	 */
	public static function related_categories( string $key, int $n = 3 ): array {
		$p = TopicMap::pillar( $key );
		if ( null === $p ) {
			return [];
		}
		$out = [];
		foreach ( (array) $p['related'] as $rk ) {
			$rp = TopicMap::pillar( (string) $rk );
			if ( null === $rp ) {
				continue;
			}
			$out[] = [
				'key'   => (string) $rk,
				'label' => (string) $rp['label'],
				'url'   => self::pillar_url( $rp ),
				'blurb' => (string) $rp['blurb'],
			];
			if ( count( $out ) >= $n ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Guides mapped to a pillar (via its hub topic).
	 *
	 * @return array<int,\WP_Post>
	 */
	public static function guides_for_pillar( string $key, int $n = 4 ): array {
		$p = TopicMap::pillar( $key );
		if ( null === $p ) {
			return [];
		}
		// Prefer guides explicitly tied to this pillar; fall back to the topic.
		$byMeta = new \WP_Query( [
			'post_type'      => TopicMap::GUIDE_CPT,
			'posts_per_page' => max( 1, $n ),
			'post_status'    => 'publish',
			'no_found_rows'  => true,
			'meta_key'       => '_gw_guide_pillar',
			'meta_value'     => $key,
		] );
		if ( count( $byMeta->posts ) > 0 ) {
			return $byMeta->posts;
		}
		return self::guides_for_topic( (string) $p['topic'], $n );
	}

	/**
	 * @return array<int,\WP_Post>
	 */
	public static function guides_for_topic( string $topic, int $n = 6 ): array {
		if ( ! post_type_exists( TopicMap::GUIDE_CPT ) ) {
			return [];
		}
		$args = [
			'post_type'      => TopicMap::GUIDE_CPT,
			'posts_per_page' => max( 1, $n ),
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		];
		if ( '' !== $topic && taxonomy_exists( TopicMap::GUIDE_TAX ) ) {
			$args['tax_query'] = [
				[ 'taxonomy' => TopicMap::GUIDE_TAX, 'field' => 'slug', 'terms' => $topic ],
			];
		}
		$q = new \WP_Query( $args );
		return $q->posts;
	}

	/**
	 * The pillar a product belongs to (first matching product_cat, walking
	 * ancestors so sub-categories still resolve to their pillar).
	 */
	public static function pillar_for_product( int $product_id ): ?array {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! is_array( $terms ) ) {
			return null;
		}
		foreach ( $terms as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$p = TopicMap::pillar_by_cat( $t->slug );
			if ( null !== $p ) {
				return $p;
			}
			foreach ( get_ancestors( $t->term_id, 'product_cat' ) as $aid ) {
				$anc = get_term( (int) $aid, 'product_cat' );
				if ( $anc instanceof \WP_Term ) {
					$pa = TopicMap::pillar_by_cat( $anc->slug );
					if ( null !== $pa ) {
						return $pa;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Related product IDs: WooCommerce related where available, else same-pillar.
	 *
	 * @return array<int,int>
	 */
	public static function related_product_ids( int $product_id, int $n = 4 ): array {
		if ( function_exists( 'wc_get_related_products' ) ) {
			$ids = wc_get_related_products( $product_id, $n );
			if ( is_array( $ids ) && count( $ids ) > 0 ) {
				return array_values( array_map( 'intval', $ids ) );
			}
		}
		$p = self::pillar_for_product( $product_id );
		if ( null === $p ) {
			return [];
		}
		$out = [];
		foreach ( self::products_in_cat( (string) $p['cat'], $n + 1 ) as $post ) {
			if ( (int) $post->ID !== $product_id ) {
				$out[] = (int) $post->ID;
			}
			if ( count( $out ) >= $n ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * The primary educational guide for a product (its pillar's first guide).
	 */
	public static function guide_for_product( int $product_id ): ?\WP_Post {
		$p = self::pillar_for_product( $product_id );
		if ( null === $p || empty( $p['key'] ) ) {
			return null;
		}
		$guides = self::guides_for_pillar( (string) $p['key'], 1 );
		return $guides[0] ?? null;
	}
}
