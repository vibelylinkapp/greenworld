<?php
declare( strict_types=1 );

namespace GreenWorld\Front;

defined( 'ABSPATH' ) || exit;

use GreenWorld\Customizer\Customizer;
use GreenWorld\Support\Cache;

/**
 * Homepage section renderer. Pulls live WooCommerce data with graceful,
 * premium fallbacks so the page never looks empty before products exist.
 * Fewer, better sections - generous whitespace, editorial hierarchy.
 */
final class Home {

	private static function shop(): string {
		return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	}

	/** @return array<int,int> */
	private static function product_ids( array $args ): array {

		$defaults = array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 8, 'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true );
		$q = new \WP_Query( array_merge( $defaults, $args ) );
		return array_map( 'intval', (array) $q->posts );
	}

	private static function render_products( array $ids, int $cols = 4 ): void {
		if ( count( $ids ) === 0 ) {
			return;
		}
		echo do_shortcode( sprintf( '[products ids="%s" columns="%d" limit="%d"]', esc_attr( implode( ',', array_map( 'strval', $ids ) ) ), $cols, count( $ids ) ) );
	}

	private static function section_head( string $eyebrow, string $title, string $link = '', string $link_text = '' ): void {
		echo '<div class="gw-sec__head">';
		echo '<div class="gw-sec__heads">';
		if ( $eyebrow !== '' ) { echo '<span class="gw-eyebrow">' . esc_html( $eyebrow ) . '</span>'; }
		echo '<h2 class="gw-sec__title">' . esc_html( $title ) . '</h2>';
		echo '</div>';
		if ( $link !== '' && $link_text !== '' ) {
			echo '<a class="gw-sec__more" href="' . esc_url( $link ) . '">' . esc_html( $link_text ) . '</a>';
		}
		echo '</div>';
	}

	/* --------------------------------------------------------------------- */

	/**
	 * Built-in + Customizer-driven hero slides (4 banner slides).
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function hero_slides(): array {
		$defaults = array(
			array(
				'img'   => 'assets/img/slides/hero-weight-loss.jpg',
				'eye'   => __( 'Weight Management', 'greenworld' ),
				'title' => __( 'Weight Loss Without Exercise', 'greenworld' ),
				'sub'   => __( 'Natural slimming teas and capsules to support your weight goals.', 'greenworld' ),
				'cta'   => __( 'Shop Weight Management', 'greenworld' ),
				'url'   => add_query_arg( array( 's' => rawurlencode( 'Weight Management' ), 'post_type' => 'product' ), home_url( '/' ) ),
			),
			array(
				'img'   => 'assets/img/slides/hero-men.jpg',
				'eye'   => __( "Men's Wellness", 'greenworld' ),
				'title' => __( 'Confidence Starts With You', 'greenworld' ),
				'sub'   => __( 'Support performance, stamina and everyday vitality with our range for men.', 'greenworld' ),
				'cta'   => __( "Shop Men's Wellness", 'greenworld' ),
				'url'   => add_query_arg( array( 's' => rawurlencode( "Men's Wellness" ), 'post_type' => 'product' ), home_url( '/' ) ),
			),
			array(
				'img'   => 'assets/img/slides/hero-women.jpg',
				'eye'   => __( "Women's Wellness", 'greenworld' ),
				'title' => __( 'Natural Care Made for Women', 'greenworld' ),
				'sub'   => __( 'From hormonal balance to gentle intimate care, chosen with women in mind.', 'greenworld' ),
				'cta'   => __( "Shop Women's Wellness", 'greenworld' ),
				'url'   => add_query_arg( array( 's' => rawurlencode( "Women's Wellness" ), 'post_type' => 'product' ), home_url( '/' ) ),
			),
			array(
				'img'   => 'assets/img/slides/hero-general.jpg',
				'eye'   => __( 'Health & Wellness', 'greenworld' ),
				'title' => __( 'Premium Health Supplements', 'greenworld' ),
				'sub'   => __( 'Quality-selected natural products for healthier everyday living.', 'greenworld' ),
				'cta'   => __( 'Shop All Products', 'greenworld' ),
				'url'   => self::shop(),
			),
			array(
				'img'   => 'assets/img/slides/delivery.webp',
				'eye'   => __( 'We deliver', 'greenworld' ),
				'title' => __( 'Nationwide & Worldwide Delivery', 'greenworld' ),
				'sub'   => __( 'Same-day in Nairobi, countrywide courier and DHL worldwide. Pay on delivery in Kenya.', 'greenworld' ),
				'cta'   => __( 'See delivery options', 'greenworld' ),
				'url'   => self::shop(),
			),
		);

		$slides = array();
		$total = count( $defaults );
		for ( $i = 1; $i <= $total; $i++ ) {
			$d        = $defaults[ $i - 1 ];
			$url_def  = isset( $d['url'] ) ? (string) $d['url'] : self::shop();
			$url_mod  = (string) get_theme_mod( "gw_hero{$i}_url", '' );
			$url      = ( '' === $url_mod ) ? $url_def : $url_mod;
			$slides[] = array(
				'image'   => (string) get_theme_mod( "gw_hero{$i}_image", GREENWORLD_URI . $d['img'] ),
				'eyebrow' => (string) get_theme_mod( "gw_hero{$i}_eyebrow", $d['eye'] ),
				'title'   => (string) get_theme_mod( "gw_hero{$i}_title", $d['title'] ),
				'sub'     => (string) get_theme_mod( "gw_hero{$i}_sub", $d['sub'] ),
				'cta'     => (string) get_theme_mod( "gw_hero{$i}_cta", $d['cta'] ),
				'url'     => $url,
			);
		}

		return array_values( array_filter( $slides, static function ( $s ) {
			return $s['title'] !== '' || $s['image'] !== '';
		} ) );
	}

	/**
	 * Resolved background image of the first hero slide — the homepage LCP
	 * element. Used by the Performance Optimizer to emit an accurate
	 * high-priority preload so the largest paint is discovered immediately.
	 */
	public static function first_hero_image(): string {
		$slides = self::hero_slides();
		if ( empty( $slides ) || empty( $slides[0]['image'] ) ) {
			return '';
		}
		return (string) $slides[0]['image'];
	}

	public static function hero(): void {
		$slides = self::hero_slides();
		$count  = count( $slides );
		if ( $count === 0 ) { return; }
		$shop = self::shop();

		echo '<section class="gw-herocar" data-gw-hero aria-roledescription="carousel" aria-label="' . esc_attr__( 'Featured', 'greenworld' ) . '">';
		echo '<div class="gw-herocar__track" data-gw-hero-track>';
		foreach ( $slides as $i => $s ) {
			$href  = ( '' === $s['url'] ) ? $shop : $s['url'];
			$alt   = ( '' === $s['title'] ) ? $s['eyebrow'] : $s['title'];
			$style = ( '' === $s['image'] ) ? '' : ' style="background-image:url(' . esc_url( $s['image'] ) . ')"';
			echo '<article class="gw-herocar__slide' . ( 0 === $i ? ' is-active' : '' ) . '"' . $style . ' data-gw-hero-slide="' . (int) $i . '" aria-hidden="' . ( 0 === $i ? 'false' : 'true' ) . '">';
			echo '<span class="gw-herocar__scrim" aria-hidden="true"></span>';
			echo '<div class="gw-container gw-herocar__inner"><div class="gw-hero__copy">';
			if ( '' !== $s['eyebrow'] ) { echo '<span class="gw-hero__eyebrow">' . esc_html( $s['eyebrow'] ) . '</span>'; }
			if ( '' !== $s['title'] )   { echo '<h2 class="gw-hero__title">' . esc_html( $s['title'] ) . '</h2>'; }
			if ( '' !== $s['sub'] )     { echo '<p class="gw-hero__sub">' . esc_html( $s['sub'] ) . '</p>'; }
			$cta = ( '' === $s['cta'] ) ? __( 'Shop now', 'greenworld' ) : $s['cta'];
			echo '<div class="gw-hero__cta">';
			echo '<a class="gw-btn gw-btn--gold" href="' . esc_url( $href ) . '">' . esc_html( $cta ) . '</a>';
			echo '<a class="gw-btn gw-btn--onhero" href="' . esc_url( $shop ) . '">' . esc_html__( 'Shop all products', 'greenworld' ) . '</a>';
			echo '</div>';
			echo '</div></div>';
			echo '</article>';
		}
		echo '</div>';

		if ( $count > 1 ) {
			echo '<button type="button" class="gw-herocar__nav gw-herocar__nav--prev" data-gw-hero-prev aria-label="' . esc_attr__( 'Previous slide', 'greenworld' ) . '">&#8249;</button>';
			echo '<button type="button" class="gw-herocar__nav gw-herocar__nav--next" data-gw-hero-next aria-label="' . esc_attr__( 'Next slide', 'greenworld' ) . '">&#8250;</button>';
			echo '<div class="gw-herocar__dots" role="tablist" data-gw-hero-dots>';
			foreach ( $slides as $i => $s ) {
				echo '<button type="button" role="tab" class="gw-herocar__dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-gw-hero-dot="' . (int) $i . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'greenworld' ), $i + 1 ) ) . '"></button>';
			}
			echo '</div>';
		}
		echo '</section>';
	}

	public static function trust_strip(): void {
		echo '<section class="gw-trust gw-trust--hero" aria-label="' . esc_attr__( 'Our promise', 'greenworld' ) . '"><div class="gw-container">';
		echo do_shortcode( '[gw_trust_badges]' );
		echo '</div></section>';
	}

	/** @return array<int,string> */
	/**
	 * Category names for the homepage "Shop by Health Category" grid, pulled
	 * live from the real WooCommerce product categories so the section is
	 * managed entirely from Products -> Categories (add, rename, reorder, and
	 * set the tile image). Top-level categories only, in the category display
	 * order, with "Uncategorized" skipped. Falls back to the curated list until
	 * real categories exist. The number shown is filterable via
	 * gw_home_category_limit (default 10).
	 */
	private static function home_category_names(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return self::home_categories();
		}
		$limit   = (int) apply_filters( 'gw_home_category_limit', 10 );
		$exclude = array();
		$default = (int) get_option( 'default_product_cat' );
		if ( $default > 0 ) {
			$exclude[] = $default;
		}

		// Categories explicitly ticked "Show on homepage" (see
		// Admin\HomeCategories) take priority: when any exist, the grid shows
		// exactly those. We deliberately do NOT order this query by
		// menu_order: on product_cat WooCommerce implements menu_order via its
		// own term-meta JOIN, which collides with a meta_query filter and
		// returns zero rows. Instead we filter by meta only, then sort in PHP
		// by the WooCommerce category display order (term meta 'order').
		$featured = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'exclude'    => $exclude,
				'meta_query' => array(
					array(
						'key'     => '_gw_home_featured',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		if ( ! is_wp_error( $featured ) && ! empty( $featured ) ) {
			$featured = array_values( (array) $featured );
			usort(
				$featured,
				static function ( $a, $b ): int {
					$oa = (int) get_term_meta( (int) $a->term_id, 'order', true );
					$ob = (int) get_term_meta( (int) $b->term_id, 'order', true );
					if ( $oa === $ob ) {
						return strcasecmp( (string) $a->name, (string) $b->name );
					}
					return $oa <=> $ob;
				}
			);
			$picked = array();
			foreach ( $featured as $t ) {
				if ( ! isset( $t->slug ) || 'uncategorized' === $t->slug ) {
					continue;
				}
				$picked[] = (string) $t->name;
				if ( $limit > 0 && count( $picked ) >= $limit ) {
					break;
				}
			}
			if ( ! empty( $picked ) ) {
				return $picked;
			}
		}

		$hide_empty = (bool) apply_filters( 'gw_home_category_hide_empty', true );
		$args       = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => $hide_empty,
			'parent'     => 0,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
			'exclude'    => $exclude,
		);
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$args['orderby'] = 'name';
			$terms           = get_terms( $args );
		}
		// If nothing is populated yet, retry including empty categories so the
		// section still renders while the catalogue is being organised.
		if ( ( is_wp_error( $terms ) || empty( $terms ) ) && $hide_empty ) {
			$args['hide_empty'] = false;
			$args['orderby']    = 'menu_order';
			$terms              = get_terms( $args );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$args['orderby'] = 'name';
				$terms           = get_terms( $args );
			}
		}
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::home_categories();
		}
		$names = array();
		foreach ( (array) $terms as $t ) {
			if ( ! isset( $t->slug ) || 'uncategorized' === $t->slug ) {
				continue;
			}
			$names[] = (string) $t->name;
			if ( $limit > 0 && count( $names ) >= $limit ) {
				break;
			}
		}
		return ! empty( $names ) ? $names : self::home_categories();
	}

	private static function home_categories(): array {
		$default = array( 'General Health', "Men's Health", "Women's Health", 'Immunity & Energy', 'Wellness & Nutrition', 'Detox & Wellness', 'Heart & Circulation', 'Bone & Joint', 'Digestive Care', 'Weight Management' );
		$csv     = (string) get_theme_mod( 'gw_home_categories', '' );
		if ( trim( $csv ) !== '' ) {
			$list = array_values( array_filter( array_map( 'trim', explode( ',', $csv ) ) ) );
			if ( count( $list ) > 0 ) { return array_slice( $list, 0, 10 ); }
		}
		return $default;
	}

	/** Maps a category name to a bundled tile image assets/img/cat/{slug}.jpg when present. */
	private static function cat_image( string $name ): string {
		$rel = 'assets/img/cat/' . sanitize_title( $name ) . '.jpg';
		return is_readable( GREENWORLD_DIR . $rel ) ? GREENWORLD_URI . $rel : '';
	}

	/** WebP sibling of the bundled tile image assets/img/cat/{slug}.webp, when present. */
	private static function cat_webp( string $name ): string {
		$rel = 'assets/img/cat/' . sanitize_title( $name ) . '.webp';
		return is_readable( GREENWORLD_DIR . $rel ) ? GREENWORLD_URI . $rel : '';
	}

	public static function shop_by_category(): void {
		$shop = self::shop();
		$cats = self::home_category_names();
		echo '<section class="gw-section gw-cats"><div class="gw-container">';
		self::section_head( __( 'Shop by need', 'greenworld' ), __( 'Shop by Health Category', 'greenworld' ), $shop, __( 'View all', 'greenworld' ) );
		echo '<div class="gw-cats__grid gw-cats__grid--5">';
		foreach ( $cats as $name ) {
			$term  = taxonomy_exists( 'product_cat' ) ? get_term_by( 'name', $name, 'product_cat' ) : false;
			$img   = self::cat_image( $name );
			$webp  = self::cat_webp( $name );
			$count = 0;
			$tid   = 0;
			if ( $term && ! is_wp_error( $term ) ) {
				$link  = get_term_link( $term );
				$count = (int) $term->count;
				$tid   = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			} else {
				$link = add_query_arg( array( 's' => rawurlencode( $name ), 'post_type' => 'product' ), home_url( '/' ) );
			}
			if ( is_wp_error( $link ) ) { $link = $shop; }
			// Small tiles (~182px on phones): emit responsive srcset/sizes so
			// mobile fetches a small image instead of the full 400px file.
			$sizes = '(max-width: 640px) 46vw, 200px';
			$media = '';
			if ( $tid ) {
				$media = (string) wp_get_attachment_image(
					$tid,
					'medium',
					false,
					array(
						'class'    => 'gw-cat-card__img',
						'alt'      => $name,
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => $sizes,
					)
				);
			}
			if ( '' === $media ) {
				if ( $img !== '' ) {
					$imgtag = '<img class="gw-cat-card__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" width="400" height="400" loading="lazy" decoding="async" sizes="' . esc_attr( $sizes ) . '" />';
					$media  = ( $webp !== '' )
						? '<picture><source type="image/webp" srcset="' . esc_url( $webp ) . '" />' . $imgtag . '</picture>'
						: $imgtag;
				} else {
					$media = self::placeholder( $name );
				}
			}
			$meta = ( $count > 0 )
				? esc_html( sprintf( _n( '%d product', '%d products', $count, 'greenworld' ), $count ) )
				: esc_html__( 'Explore', 'greenworld' );
			printf(
				'<a class="gw-cat-card" href="%s"><span class="gw-cat-card__media">%s</span><span class="gw-cat-card__body"><span class="gw-cat-card__name">%s</span><span class="gw-cat-card__count">%s</span></span></a>',
				esc_url( (string) $link ), $media, esc_html( $name ), $meta
			);
		}
		echo '</div></div></section>';
	}

	private static function placeholder( string $name ): string {
		$initial = function_exists( 'mb_substr' ) ? mb_substr( trim( $name ), 0, 1 ) : substr( trim( $name ), 0, 1 );
		return '<span class="gw-ph" aria-hidden="true"><span>' . esc_html( strtoupper( $initial ) ) . '</span></span>';
	}

	public static function featured_products(): void {
		// Randomize on every load so the row feels fresh; prefer real "featured" products.
		$ids = self::product_ids( array( 'posts_per_page' => 12, 'orderby' => 'rand', 'tax_query' => array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured', 'operator' => 'IN' ) ) ) );
		if ( count( $ids ) < 4 ) {
			$ids = self::product_ids( array( 'posts_per_page' => 12, 'orderby' => 'rand' ) );
		}
		if ( count( $ids ) === 0 ) { return; }
		echo '<section class="gw-section gw-featured"><div class="gw-container">';
		self::section_head( __( 'Handpicked', 'greenworld' ), __( 'Featured Health & Wellness Products', 'greenworld' ), self::shop(), __( 'Shop all', 'greenworld' ) );
		echo '<div class="gw-scroller" data-gw-scroller>';
		echo '<button type="button" class="gw-scroller__nav gw-scroller__nav--prev" data-gw-scroll="prev" aria-label="' . esc_attr__( 'Scroll left', 'greenworld' ) . '">&#8249;</button>';
		self::render_products( $ids, 4 );
		echo '<button type="button" class="gw-scroller__nav gw-scroller__nav--next" data-gw-scroll="next" aria-label="' . esc_attr__( 'Scroll right', 'greenworld' ) . '">&#8250;</button>';
		echo '</div></div></section>';
	}

	/**
	 * Three category product shelves — Men's / Women's / General Health.
	 * Each row shows ~6 products on desktop with the rest in a horizontal
	 * scroller (2-up on mobile), and the selection is randomized on every
	 * load so the homepage always feels fresh. Replaces the old static
	 * "Shop by person / Health for Everyone" photo band.
	 */
	public static function health_focus(): void {
		$rows = array(
			array(
				'title'      => __( "Men's Wellness", 'greenworld' ),
				'q'          => "Men's Wellness",
				'search'     => "Men's Wellness",
				'candidates' => array( "Men's Wellness", "Men's Vitality", 'Prostate Wellness', "Men's Health", 'Male Fertility', 'Reproductive Wellness' ),
				'include'    => '/(\bmen\b|\bmens\b|\bmale\b|prostate|sperm|azoospermia|oligospermia|vigpower|a-?power|golden knight|testoster|androl)/i',
				'exclude'    => '/(\bwomen\b|\bfemale\b|menopaus|menstru|fibroid|ovar|uterus|uterine|breast|vaginal|silver eva|\bperiod\b)/i',
			),
			array(
				'title'      => __( "Women's Wellness", 'greenworld' ),
				'q'          => "Women's Wellness",
				'search'     => "Women's Wellness",
				'candidates' => array( "Women's Wellness", 'Menopause Wellness', 'Menstrual Wellness', 'Reproductive Wellness', "Women's Health", 'Female Fertility' ),
				'include'    => '/(\bwomen\b|\bwoman\b|\bwomens\b|\bfemale\b|menopaus|menstru|fibroid|\bovary\b|ovarian|uterus|uterine|breast|vaginal|\bperiod\b|silver eva)/i',
				'exclude'    => '/(\bmen\b|\bmale\b|prostate|sperm|azoospermia|oligospermia|vigpower|a-?power|golden knight)/i',
			),
			array(
				'title'      => __( 'Vitamins & Minerals', 'greenworld' ),
				'q'          => 'Vitamins & Minerals',
				'search'     => 'Vitamins & Minerals',
				'candidates' => array( 'Vitamins & Minerals', 'Vitamins and Minerals', 'Immune Support', 'General Wellness' ),
				'include'    => '/(vitamin|mineral|calcium|zinc|iron|selenium|magnesi|multivit|omega|fish oil|immun|antioxidant|spirulina|ginseng|nutrition)/i',
				'exclude'    => '/(\bmen\b|\bmens\b|\bmale\b|prostate|sperm|azoospermia|oligospermia|vigpower|a-?power|golden knight|\bwomen\b|\bwomens\b|\bfemale\b|menopaus|menstru|fibroid|ovar|uterus|breast|vaginal|silver eva|\bperiod\b|reproduct|fertilit)/i',
			),
		);

		$printed = false;
		foreach ( $rows as $row ) {
			$ids = self::shelf_product_ids( $row, 14 );
			if ( count( $ids ) < 2 ) {
				continue; // Skip a shelf with too few products rather than show a thin row.
			}
			if ( ! $printed ) {
				echo '<section class="gw-section gw-shelves"><div class="gw-container">';
				$printed = true;
			}
			echo '<div class="gw-shelf">';
			self::section_head( '', (string) $row['title'], self::category_link( (string) $row['q'] ), __( 'View all', 'greenworld' ) );
			echo '<div class="gw-scroller gw-scroller--shelf" data-gw-scroller>';
			echo '<button type="button" class="gw-scroller__nav gw-scroller__nav--prev" data-gw-scroll="prev" aria-label="' . esc_attr__( 'Scroll left', 'greenworld' ) . '">&#8249;</button>';
			self::render_products( $ids, 6 );
			echo '<button type="button" class="gw-scroller__nav gw-scroller__nav--next" data-gw-scroll="next" aria-label="' . esc_attr__( 'Scroll right', 'greenworld' ) . '">&#8250;</button>';
			echo '</div></div>';
		}
		if ( $printed ) {
			echo '</div></section>';
		}
	}

	/**
	 * Products for one homepage shelf, resilient to how the catalogue is
	 * organised. Order of preference (random/fresh on every load):
	 *   1. Membership of the shelf's product categories (incl. sub-categories).
	 *   2. If those categories are missing/empty, match by product-title
	 *      keywords with word boundaries, so "...for Men" lands under Men's
	 *      Health while "Menopause..."/"...for Women" land under Women's Health.
	 *
	 * This is why a row can look empty: if products are not assigned to a
	 * "Men's/Women's/General Health" category (the Shop-by-Category tiles then
	 * read "Explore" with no count), the category path yields nothing and we
	 * fall back to titles. Assigning products to those categories in
	 * WooCommerce makes the rows use exact category membership automatically.
	 *
	 * @param array<string,mixed> $row
	 * @return array<int,int>
	 */
	private static function shelf_product_ids( array $row, int $n ): array {
		// Authoritative: title-keyword classifier with word boundaries. A raw WP
		// search for "Men's ..." also matches "woMEN"/"MENopause"/"MENstrual" and
		// pollutes the shelf, so shelves never fall back to raw search.
		$title_ids = self::products_by_title( (string) ( $row['include'] ?? '' ), (string) ( $row['exclude'] ?? '' ), $n );
		if ( count( $title_ids ) >= 2 ) {
			return $title_ids;
		}

		// Secondary: real product-category membership, filtered through the same
		// exclude pattern so a mis-categorised product cannot leak into the shelf.
		if ( taxonomy_exists( 'product_cat' ) ) {
			$term_ids = array();
			$names    = isset( $row['candidates'] ) ? (array) $row['candidates'] : array( (string) ( $row['q'] ?? '' ) );
			foreach ( $names as $name ) {
				$t = get_term_by( 'name', (string) $name, 'product_cat' );
				if ( $t instanceof \WP_Term ) {
					$term_ids[] = (int) $t->term_id;
				}
			}
			$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
			if ( count( $term_ids ) > 0 ) {
				$cat_ids = self::product_ids( array(
					'posts_per_page' => (int) $n * 3,
					'orderby'        => 'rand',
					'tax_query'      => array(
						array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term_ids, 'operator' => 'IN', 'include_children' => true ),
					),
				) );
				$exclude = (string) ( $row['exclude'] ?? '' );
				$clean   = array();
				foreach ( $cat_ids as $id ) {
					$title = strtolower( (string) get_the_title( (int) $id ) );
					if ( '' !== $exclude && preg_match( $exclude, $title ) ) {
						continue;
					}
					$clean[] = (int) $id;
				}
				if ( count( $clean ) >= 2 ) {
					return array_slice( $clean, 0, $n );
				}
			}
		}

		return $title_ids;
	}
	private static function products_by_title( string $include, string $exclude, int $n ): array {
		$pool    = self::product_ids( array( 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC' ) );
		$matched = array();
		foreach ( $pool as $id ) {
			$title = strtolower( (string) get_the_title( (int) $id ) );
			if ( '' === $title ) {
				continue;
			}
			if ( '' !== $include && ! preg_match( $include, $title ) ) {
				continue;
			}
			if ( '' !== $exclude && preg_match( $exclude, $title ) ) {
				continue;
			}
			$matched[] = (int) $id;
		}
		if ( count( $matched ) > 1 ) {
			shuffle( $matched );
		}
		return array_slice( $matched, 0, max( 1, $n ) );
	}

	/**
	 * Shelf resolver that mirrors the on-site product search
	 * (/?s=<term>&post_type=product): published products matching the
	 * term, randomised so the row refreshes on every load.
	 *
	 * @return array<int,int>
	 */
	private static function products_by_search( string $term, int $n ): array {
		if ( '' === trim( $term ) ) {
			return array();
		}
		return self::product_ids( array(
			'posts_per_page' => max( 2, $n ),
			's'              => $term,
			'orderby'        => 'rand',
		) );
	}

	/** Best URL for a category name: its term archive, else a product search. */
	private static function category_link( string $name ): string {
		if ( taxonomy_exists( 'product_cat' ) ) {
			$term = get_term_by( 'name', $name, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					return (string) $link;
				}
			}
		}
		return (string) add_query_arg( array( 's' => rawurlencode( $name ), 'post_type' => 'product' ), home_url( '/' ) );
	}

	/** Bookable "Computerized Full Body Scan" promo band (Customizer-driven). */
	/** Full-width deliveries banner (image overridable in the Customizer). */
	public static function deliveries(): void {
		$img = (string) get_theme_mod( 'gw_delivery_image', GREENWORLD_URI . 'assets/img/slides/delivery.webp' );
		if ( '' === $img ) {
			return;
		}
		$shop = self::shop();
		echo '<section class="gw-section gw-deliveries"><div class="gw-container">';
		echo '<a class="gw-deliveries__banner" href="' . esc_url( $shop ) . '" aria-label="' . esc_attr__( 'We deliver across Kenya with pay on delivery, and internationally via DHL', 'greenworld' ) . '">';
		echo '<img class="gw-deliveries__img" src="' . esc_url( $img ) . '" alt="' . esc_attr__( 'Premium health supplements delivered across Kenya (pay on delivery) and internationally via DHL', 'greenworld' ) . '" loading="lazy">';
		echo '</a>';
		echo '</div></section>';
	}

	public static function full_body_scan(): void {
		if ( '1' !== (string) get_theme_mod( 'gw_scan_enable', '1' ) ) { return; }
		$title = (string) get_theme_mod( 'gw_scan_title', __( 'Computerized Full Body Health Scan', 'greenworld' ) );
		$price = (string) get_theme_mod( 'gw_scan_price', __( 'KSh 2,000', 'greenworld' ) );
		$desc  = (string) get_theme_mod( 'gw_scan_desc', __( 'Book a quick, non-invasive computerized full body scan and get a clear picture of your wellbeing. Walk in or reserve a time ahead.', 'greenworld' ) );
		$phone = preg_replace( '/[^0-9]/', '', (string) get_theme_mod( 'gw_whatsapp', '254723579873' ) );
		$wa    = 'https://wa.me/' . $phone . '?text=' . rawurlencode( sprintf( 'Hello Green World Health Solutions, I would like to book a Computerized Full Body Scan (%s).', $price ) );
		$url   = (string) get_theme_mod( 'gw_scan_url', '' );
		if ( $url === '' ) { $url = ( $phone !== '' ) ? $wa : home_url( '/health-consultation/' ); }
		?>
		<section class="gw-section gw-scanband">
			<div class="gw-container gw-scanband__inner">
				<div class="gw-scanband__copy">
					<span class="gw-eyebrow"><?php esc_html_e( 'Book an appointment', 'greenworld' ); ?></span>
					<h2><?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $desc ); ?></p>
					<?php if ( shortcode_exists( 'gw_scan_form' ) ) : ?>
						<?php echo do_shortcode( '[gw_scan_form]' ); ?>
					<?php else : ?>
						<a class="button gw-btn--gold" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Book your scan', 'greenworld' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="gw-scanband__price" aria-hidden="true">
					<span class="gw-scanband__from"><?php esc_html_e( 'Only', 'greenworld' ); ?></span>
					<span class="gw-scanband__amount"><?php echo esc_html( $price ); ?></span>
					<span class="gw-scanband__per"><?php esc_html_e( 'per scan', 'greenworld' ); ?></span>
				</div>
			</div>
		</section>
		<?php
	}

	public static function best_sellers(): void {
		$ids = self::product_ids( array( 'posts_per_page' => 8, 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );
		// Drop products with zero sales to avoid a meaningless "best sellers" row.
		$ids = array_values( array_filter( $ids, static function ( $id ) { return (int) get_post_meta( (int) $id, 'total_sales', true ) > 0; } ) );
		if ( count( $ids ) < 3 ) { return; }
		echo '<section class="gw-section gw-section--muted gw-bestsellers"><div class="gw-container">';
		self::section_head( __( 'Loved by customers', 'greenworld' ), __( 'Our Best Sellers', 'greenworld' ), add_query_arg( 'orderby', 'popularity', self::shop() ), __( 'See all', 'greenworld' ) );
		self::render_products( $ids, 4 );
		echo '</div></section>';
	}

	public static function join_band(): void {
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		?>
		<section class="gw-section gw-join">
			<div class="gw-container gw-join__grid">
				<article class="gw-join__card gw-join__card--customer">
					<span class="gw-eyebrow"><?php esc_html_e( 'For everyday wellness', 'greenworld' ); ?></span>
					<h2><?php esc_html_e( 'Register as a Customer', 'greenworld' ); ?></h2>
					<p><?php esc_html_e( 'Create a free account for faster checkout, saved delivery details, order tracking and a wishlist.', 'greenworld' ); ?></p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'gw_type', 'customer', $account ) ); ?>"><?php esc_html_e( 'Create free account', 'greenworld' ); ?></a>
				</article>
				<article class="gw-join__card gw-join__card--dist">
					<span class="gw-eyebrow"><?php esc_html_e( 'Build a business', 'greenworld' ); ?></span>
					<h2><?php esc_html_e( 'Become a Distributor', 'greenworld' ); ?></h2>
					<p><?php esc_html_e( 'Join the Green World direct-selling business, shop at distributor prices and grow your own network with our support.', 'greenworld' ); ?></p>
					<a class="button gw-btn--gold" href="<?php echo esc_url( home_url( '/become-a-distributor/' ) ); ?>"><?php esc_html_e( 'Start as distributor', 'greenworld' ); ?></a>
				</article>
			</div>
		</section>
		<?php
	}

	public static function collections(): void {
		$shop = self::shop();
		$defs = array(
			array( 'title' => __( "Men's Wellness", 'greenworld' ), 'text' => __( 'Products selected for men’s health and vitality.', 'greenworld' ), 'q' => "Men's Health" ),
			array( 'title' => __( "Women's Wellness", 'greenworld' ), 'text' => __( 'A carefully organized collection for women’s wellness.', 'greenworld' ), 'q' => "Women's Health" ),
			array( 'title' => __( 'Everyday Wellness', 'greenworld' ), 'text' => __( 'Popular products for general health and wellbeing.', 'greenworld' ), 'q' => 'General Health' ),
			array( 'title' => __( 'Nutrition & Supplements', 'greenworld' ), 'text' => __( 'Selected nutrition and wellness essentials.', 'greenworld' ), 'q' => 'Nutrition' ),
		);
		echo '<section class="gw-section gw-collections"><div class="gw-container">';
		self::section_head( __( 'Curated', 'greenworld' ), __( 'Wellness Collections', 'greenworld' ) );
		echo '<div class="gw-collections__grid">';
		foreach ( $defs as $d ) {
			$term = get_term_by( 'name', $d['q'], 'product_cat' );
			$url  = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : add_query_arg( array( 's' => rawurlencode( $d['q'] ), 'post_type' => 'product' ), home_url( '/' ) );
			printf(
				'<a class="gw-collection" href="%s"><span class="gw-collection__media">%s</span><span class="gw-collection__body"><span class="gw-collection__title">%s</span><span class="gw-collection__text">%s</span><span class="gw-collection__link">%s</span></span></a>',
				esc_url( is_wp_error( $url ) ? $shop : (string) $url ), self::placeholder( (string) $d['title'] ), esc_html( $d['title'] ), esc_html( $d['text'] ), esc_html__( 'Shop collection', 'greenworld' )
			);
		}
		echo '</div></div></section>';
	}

	public static function consultation_band(): void {
		?>
		<section class="gw-section gw-consultband">
			<div class="gw-container gw-consultband__inner">
				<div class="gw-consultband__copy">
					<span class="gw-eyebrow"><?php esc_html_e( 'We are here to help', 'greenworld' ); ?></span>
					<h2><?php esc_html_e( 'Not sure what you need? Get a free health consultation', 'greenworld' ); ?></h2>
					<p><?php esc_html_e( 'Tell us about your health concern and our team will recommend suitable products and guidance — online, at your convenience.', 'greenworld' ); ?></p>
					<a class="button gw-btn--gold" href="<?php echo esc_url( home_url( '/health-consultation/' ) ); ?>"><?php esc_html_e( 'Start free consultation', 'greenworld' ); ?></a>
					<p class="gw-consultband__note"><?php esc_html_e( 'General wellness guidance — not a medical diagnosis or emergency service.', 'greenworld' ); ?></p>
				</div>
			</div>
		</section>
		<?php
	}

	public static function why_choose(): void {
		$points = array(
			array( __( 'Carefully Selected Products', 'greenworld' ), __( 'A focused range of quality health and wellness products.', 'greenworld' ) ),
			array( __( 'Customer-Focused Service', 'greenworld' ), __( 'Friendly, knowledgeable support before and after you buy.', 'greenworld' ) ),
			array( __( 'Reliable Delivery', 'greenworld' ), __( 'Convenient delivery options across Kenya.', 'greenworld' ) ),
			array( __( 'Secure Payments', 'greenworld' ), __( 'Pay safely with M-Pesa, bank transfer or cash on delivery.', 'greenworld' ) ),
		);
		echo '<section class="gw-section gw-section--muted gw-why"><div class="gw-container">';
		self::section_head( __( 'The Green World difference', 'greenworld' ), __( 'Why Choose Green World Health Solutions', 'greenworld' ) );
		echo '<div class="gw-why__grid">';
		foreach ( $points as $p ) {
			printf( '<div class="gw-why__card"><h3>%s</h3><p>%s</p></div>', esc_html( $p[0] ), esc_html( $p[1] ) );
		}
		echo '</div></div></section>';
	}

	public static function journal(): void {
		$posts = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 3, 'post_status' => 'publish', 'ignore_sticky_posts' => true ) );
		if ( count( $posts ) === 0 ) { return; }
		echo '<section class="gw-section gw-journal"><div class="gw-container">';
		self::section_head( __( 'Learn', 'greenworld' ), __( 'Health & Wellness Journal', 'greenworld' ), get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/journal/' ), __( 'Read more', 'greenworld' ) );
		echo '<div class="gw-journal__grid">';
		foreach ( $posts as $post ) {
			$img = has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'medium', array( 'loading' => 'lazy', 'class' => 'gw-journal__img' ) ) : self::placeholder( (string) get_the_title( $post ) );
			printf(
				'<a class="gw-journal__card" href="%s"><span class="gw-journal__media">%s</span><span class="gw-journal__body"><span class="gw-journal__date">%s</span><span class="gw-journal__title">%s</span></span></a>',
				esc_url( (string) get_permalink( $post ) ), $img, esc_html( get_the_date( '', $post ) ), esc_html( get_the_title( $post ) )
			);
		}
		echo '</div></div></section>';
	}

	public static function disclaimer(): void {
		$text = Customizer::val( 'gw_default_disclaimer' );
		if ( $text === '' ) { return; }
		echo '<aside class="gw-section gw-disclaimerband"><div class="gw-container gw-disclaimerband__inner">';
		echo '<span class="gw-disclaimerband__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg></span>';
		echo '<p>' . esc_html( $text ) . '</p>';
		echo '</div></aside>';
	}

	public static function newsletter(): void {
		$email = Customizer::val( 'gw_email' );
		echo '<section class="gw-section gw-newsletter"><div class="gw-container gw-newsletter__inner">';
		echo '<h2 class="gw-newsletter__title">' . esc_html__( 'Join our wellness list', 'greenworld' ) . '</h2>';
		echo '<p>' . esc_html__( 'Health tips, new arrivals and offers — straight to your inbox.', 'greenworld' ) . '</p>';
		$html = do_shortcode( '[contact-form-7 id="newsletter" title="Newsletter"]' );
		if ( strpos( (string) $html, 'wpcf7' ) !== false && strpos( (string) $html, 'not found' ) === false ) {
			echo $html; // phpcs:ignore
		} else {
			echo '<form class="gw-news gw-news--lg" method="post" action="' . esc_url( $email !== '' ? 'mailto:' . $email : '#' ) . '">';
			echo '<label class="screen-reader-text" for="gw-news2">' . esc_html__( 'Email address', 'greenworld' ) . '</label>';
			echo '<input id="gw-news2" type="email" name="subject" placeholder="' . esc_attr__( 'Your email address', 'greenworld' ) . '" />';
			echo '<button class="button" type="submit">' . esc_html__( 'Subscribe', 'greenworld' ) . '</button>';
			echo '</form>';
		}
		echo '</div></section>';
	}
}
