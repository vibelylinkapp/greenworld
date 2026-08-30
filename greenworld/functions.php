<?php
/**
 * GreenWorld Wellness theme bootstrap.
 *
 * @package GreenWorld
 * @author  Green World Health Solutions
 * @license GPL-2.0-or-later
 *
 * Minimum: PHP 8.0, WordPress 6.4, WooCommerce 8.0.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' .
				esc_html( sprintf( __( 'GreenWorld requires PHP 8.0 or higher. This server runs PHP %s. In cPanel, open Select PHP Version (or MultiPHP Manager) and switch this site to PHP 8.1 or 8.2, then reload.', 'greenworld' ), PHP_VERSION ) ) .
				'</p></div>';
		}
	);
	return;
}

define( 'GREENWORLD_VERSION', '1.34.5' );

/**
 * Emit the theme version into the page head so the running build is verifiable
 * from View Source (helps confirm a deploy actually took effect).
 */
add_action(
	'wp_head',
	static function (): void {
		echo "\n<!-- GreenWorld theme v" . GREENWORLD_VERSION . " -->\n";
	},
	1
);
define( 'GREENWORLD_DIR', trailingslashit( get_template_directory() ) );
define( 'GREENWORLD_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Block the built-in theme/plugin file editors. Code ships from Git, and the
 * in-dashboard editor is a common post-compromise persistence vector.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Branded placeholder for products with no featured image, so category, shop
 * and search cards never render as an empty box (a common cause of grid gaps).
 */
add_filter(
	'woocommerce_placeholder_img_src',
	static function ( $src ) {
		unset( $src );
		return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2MDAgNjAwIiB3aWR0aD0iNjAwIiBoZWlnaHQ9IjYwMCI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJnIiB4MT0iMCIgeTE9IjAiIHgyPSIxIiB5Mj0iMSI+PHN0b3Agb2Zmc2V0PSIwIiBzdG9wLWNvbG9yPSIjZWVmNGVlIi8+PHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjZDllNmRjIi8+PC9saW5lYXJHcmFkaWVudD48L2RlZnM+PHJlY3Qgd2lkdGg9IjYwMCIgaGVpZ2h0PSI2MDAiIGZpbGw9InVybCgjZykiLz48ZyBmaWxsPSIjMUU1QjNFIiBvcGFjaXR5PSIwLjUiPjxwYXRoIGQ9Ik0zMDAgMTUwIEMyMTQgMTk2IDE5MCAzMDYgMzAwIDQ0MiBDNDEwIDMwNiAzODYgMTk2IDMwMCAxNTAgWiIvPjwvZz48cGF0aCBkPSJNMzAwIDIxMCBMMzAwIDQ0MiIgc3Ryb2tlPSIjZmZmZmZmIiBzdHJva2Utd2lkdGg9IjYiIGZpbGw9Im5vbmUiIG9wYWNpdHk9IjAuNjUiLz48dGV4dCB4PSIzMDAiIHk9IjUyNCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9Ikdlb3JnaWEsIHNlcmlmIiBmb250LXNpemU9IjQwIiBmaWxsPSIjMUU1QjNFIiBvcGFjaXR5PSIwLjcyIj5HcmVlbiBXb3JsZDwvdGV4dD48L3N2Zz4=';
	}
);

/**
 * PSR-4 style autoloader for the GreenWorld\ namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'GreenWorld\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = GREENWORLD_DIR . 'inc/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

// Boot the theme container.
add_action(
	'after_setup_theme',
	static function (): void {
		( new \GreenWorld\Core\Theme() )->boot();
	},
	5
);

/**
 * Premium editorial fonts: Fraunces (display serif) + Inter (body sans).
 * Preconnect + display=swap keep this off the critical path for a fast LCP.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'greenworld-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap',
			[],
			null
		);
	},
	5
);
add_action(
	'wp_head',
	static function (): void {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com" />' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
	},
	0
);

/**
 * Load the Google Fonts stylesheet without blocking first paint: swap it to a
 * non-render-blocking load (media="print" flipped to "all" on load) with a
 * <noscript> fallback. With display=swap this keeps the fonts CSS and its font
 * files off the critical render path (PSI: render-blocking requests).
 */
add_filter(
	'style_loader_tag',
	static function ( string $tag, string $handle ): string {
		if ( 'greenworld-fonts' !== $handle ) {
			return $tag;
		}
		$async = (string) preg_replace( '/ media=(\'|")[^\'"]*(\'|")/', '', $tag );
		$async = str_replace(
			array( " rel='stylesheet'", ' rel="stylesheet"' ),
			" rel='stylesheet' media='print' onload=\"this.media='all';this.onload=null;\"",
			$async
		);
		return $async . '<noscript>' . $tag . '</noscript>';
	},
	10,
	2
);

/**
 * Flush GreenWorld catalogue caches when products, categories or reviews change.
 */
foreach ( [ 'save_post_product', 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'created_product_cat', 'edited_product_cat', 'delete_product_cat', 'comment_post', 'edit_comment', 'wp_set_comment_status' ] as $gw_hook ) {
	add_action( $gw_hook, [ '\\GreenWorld\\Support\\Cache', 'flush' ] );
}

/**
 * Live header cart count via WooCommerce fragments.
 */
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			ob_start();
			echo '<span class="gw-cart__count">' . esc_html( (string) WC()->cart->get_cart_contents_count() ) . '</span>';
			$fragments['span.gw-cart__count'] = ob_get_clean();
		}
		return $fragments;
	}
);

/**
 * Ensure WooCommerce ajax add-to-cart is available on the homepage loops.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( is_front_page() && function_exists( 'is_shop' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}
	}
);

/**
 * Customizer brand colours as CSS variables so the whole theme retints.
 */
add_action(
	'wp_head',
	static function (): void {
		$green = sanitize_hex_color( (string) get_theme_mod( 'gw_brand_color', '#1E5B3E' ) );
		$deep  = sanitize_hex_color( (string) get_theme_mod( 'gw_brand_deep', '#123726' ) );
		$gold  = sanitize_hex_color( (string) get_theme_mod( 'gw_accent_color', '#A98545' ) );
		$css   = '';
		if ( $green ) { $css .= '--gw-green:' . $green . ';'; }
		if ( $deep ) { $css .= '--gw-green-deep:' . $deep . ';'; }
		if ( $gold ) { $css .= '--gw-gold:' . $gold . ';'; }
		if ( $css !== '' ) {
			echo '<style id="greenworld-brand-vars">:root{' . esc_html( $css ) . '}</style>' . "\n";
		}
	},
	20
);

/**
 * Preload the hero image for a faster LCP on the homepage.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( is_front_page() === false ) {
			return;
		}
		$hero = get_theme_mod( 'gw_hero_image', GREENWORLD_URI . 'assets/img/hero.jpg' );
		if ( is_string( $hero ) && $hero !== '' ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $hero ) . '" fetchpriority="high" />' . "\n";
		}
	},
	1
);

/**
 * Fallback primary navigation for a balanced header before menus are assigned.
 *
 * @param array<string,mixed> $args wp_nav_menu args.
 */
function greenworld_primary_menu_fallback( $args = array() ): string {
	$args     = is_array( $args ) ? $args : (array) $args;
	$menu_id  = isset( $args['menu_id'] ) ? (string) $args['menu_id'] : 'gw-primary-menu';
	$menu_cls = isset( $args['menu_class'] ) ? (string) $args['menu_class'] : 'gw-primary__menu';
	$shop     = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

	$items   = array();
	$items[] = array( home_url( '/' ), __( 'Home', 'greenworld' ) );
	$items[] = array( $shop, __( 'Shop', 'greenworld' ) );
	$items[] = array( $shop, __( 'Health Categories', 'greenworld' ) );
	$items[] = array( add_query_arg( 'orderby', 'popularity', $shop ), __( 'Best Sellers', 'greenworld' ) );
	$items[] = array( add_query_arg( 'orderby', 'date', $shop ), __( 'New Arrivals', 'greenworld' ) );
	$items[] = array( home_url( '/become-a-distributor/' ), __( 'Become a Distributor', 'greenworld' ) );
	$items[] = array( home_url( '/about-us/' ), __( 'About Us', 'greenworld' ) );
	$items[] = array( home_url( '/contact-us/' ), __( 'Contact', 'greenworld' ) );

	$html = '<ul id="' . esc_attr( $menu_id ) . '" class="' . esc_attr( $menu_cls ) . '">';
	foreach ( $items as $it ) {
		$html .= '<li class="menu-item"><a href="' . esc_url( $it[0] ) . '">' . esc_html( (string) $it[1] ) . '</a></li>';
	}
	$html .= '</ul>';

	$echo = ( ! array_key_exists( 'echo', $args ) ) || ! empty( $args['echo'] );
	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
		return '';
	}
	return $html;
}

/**
 * One-time starter setup: health pages, static front page and WooCommerce basics.
 */
function greenworld_run_starter_setup(): void {
	if ( get_option( 'greenworld_starter_v1' ) === 'done' ) {
		return;
	}
	$dir = get_template_directory();

	// Seed brand contact options used by schema + templates (admin can override).
	add_option( 'greenworld_email', 'info@greenworldhealth.co.ke' );
	add_option( 'greenworld_phone', '+254723579873' );
	add_option( 'greenworld_street', 'Development House, 11th Floor, Room 7' );
	add_option( 'greenworld_city', 'Nairobi' );

	// Pages from bundled starter HTML.
	$page_ids      = array();
	$manifest_path = $dir . '/starter/pages/manifest.json';
	if ( file_exists( $manifest_path ) ) {
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( is_array( $manifest ) ) {
			foreach ( $manifest as $slug => $title ) {
				$html      = '';
				$html_path = $dir . '/starter/pages/' . $slug . '.html';
				if ( file_exists( $html_path ) ) {
					$html = (string) file_get_contents( $html_path );
				}
				$existing = get_page_by_path( (string) $slug );
				if ( $existing === null ) {
					$new_id = wp_insert_post( array( 'post_title' => wp_strip_all_tags( (string) $title ), 'post_name' => (string) $slug, 'post_content' => $html, 'post_status' => 'publish', 'post_type' => 'page' ) );
					if ( is_int( $new_id ) && $new_id > 0 ) {
						$page_ids[ $slug ] = $new_id;
					}
				} else {
					$page_ids[ $slug ] = (int) $existing->ID;
				}
			}
		}
	}
	if ( isset( $page_ids['privacy-policy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', $page_ids['privacy-policy'] );
	}

	// Footer menus.
	greenworld_seed_footer_menus( $page_ids );

	// WooCommerce store basics (Kenya).
	if ( class_exists( 'WooCommerce' ) ) {
		update_option( 'woocommerce_currency', 'KES' );
		update_option( 'woocommerce_default_country', 'KE' );
		update_option( 'woocommerce_store_city', 'Nairobi' );
		update_option( 'woocommerce_email_from_address', 'info@greenworldhealth.co.ke' );
		update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
		update_option( 'woocommerce_cart_redirect_after_add', 'no' );
		update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		if ( function_exists( 'wc_create_pages' ) ) {
			wc_create_pages();
		}
		$cod = get_option( 'woocommerce_cod_settings', array() );
		if ( is_array( $cod ) === false ) { $cod = array(); }
		$cod['enabled']     = 'yes';
		$cod['title']       = 'Cash on Delivery';
		$cod['description'] = 'Pay with cash or M-Pesa when your order arrives.';
		update_option( 'woocommerce_cod_settings', $cod );
		$bacs = get_option( 'woocommerce_bacs_settings', array() );
		if ( is_array( $bacs ) === false ) { $bacs = array(); }
		$bacs['enabled']     = 'yes';
		$bacs['title']       = 'Bank Transfer';
		$bacs['description'] = 'Pay by direct bank transfer. Use your order number as the payment reference; we ship once payment clears.';
		update_option( 'woocommerce_bacs_settings', $bacs );
	}

	// Static front page.
	$home_page = get_page_by_path( 'home' );
	if ( $home_page === null ) {
		$home_id = wp_insert_post( array( 'post_title' => 'Home', 'post_name' => 'home', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page' ) );
	} else {
		$home_id = (int) $home_page->ID;
	}
	if ( is_int( $home_id ) && $home_id > 0 ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
		$journal = get_page_by_path( 'journal' );
		if ( $journal === null ) {
			$journal_id = wp_insert_post( array( 'post_title' => 'Health & Wellness Journal', 'post_name' => 'journal', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page' ) );
			if ( is_int( $journal_id ) && $journal_id > 0 ) {
				update_option( 'page_for_posts', $journal_id );
			}
		}
	}

	update_option( 'greenworld_starter_v1', 'done' );
}
add_action( 'after_switch_theme', 'greenworld_run_starter_setup' );
add_action( 'admin_init', 'greenworld_run_starter_setup' );

/**
 * One-time: seed the Green World product-category hierarchy.
 *
 * Additive and idempotent. It only creates categories that do not already
 * exist under the same parent; it never renames, deletes or reassigns
 * anything. New categories are created empty, and the mega menu and filter
 * sidebar both use hide_empty=>true, so empty categories stay hidden from
 * shoppers until you assign products to them. Safe to run on a live store.
 * Guarded by an option flag so the pass runs exactly once.
 */
function greenworld_seed_category_hierarchy(): void {
	if ( is_admin() === false || current_user_can( 'manage_woocommerce' ) === false ) {
		return;
	}
	if ( get_option( 'greenworld_cat_hierarchy_v1' ) === 'done' ) {
		return;
	}
	if ( taxonomy_exists( 'product_cat' ) === false ) {
		return; // WooCommerce not ready yet; retried on the next admin load.
	}

	$tree = array(
		'Vitamins & Minerals'          => array(),
		"Women's Wellness"             => array( 'Menopause Wellness', 'Menstrual Wellness', 'Reproductive Wellness' ),
		"Men's Wellness"               => array( "Men's Vitality", 'Prostate Wellness', 'Reproductive Wellness' ),
		'Digestive Wellness'           => array(),
		'Immune Support'               => array(),
		'Bone & Joint Wellness'        => array(),
		'Heart & Circulatory Wellness' => array(),
		'Weight Management'            => array(),
		'Herbal & Natural Products'    => array(),
		'General Wellness'             => array(),
	);

	foreach ( $tree as $parent_name => $children ) {
		$parent_id = greenworld_ensure_product_cat( (string) $parent_name, 0 );
		if ( $parent_id <= 0 ) {
			continue;
		}
		foreach ( $children as $child_name ) {
			greenworld_ensure_product_cat( (string) $child_name, $parent_id );
		}
	}

	update_option( 'greenworld_cat_hierarchy_v1', 'done' );
}
add_action( 'admin_init', 'greenworld_seed_category_hierarchy', 20 );

/**
 * Create a product_cat term under $parent if one with that exact name does
 * not already exist there. Returns the term id (existing or new), or 0.
 */
function greenworld_ensure_product_cat( string $name, int $parent ): int {
	$existing = term_exists( $name, 'product_cat', $parent );
	if ( is_array( $existing ) && isset( $existing['term_id'] ) ) {
		return (int) $existing['term_id'];
	}
	$res = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
	if ( is_wp_error( $res ) ) {
		$maybe = get_term_by( 'name', $name, 'product_cat' );
		return ( $maybe instanceof \WP_Term ) ? (int) $maybe->term_id : 0;
	}
	return ( is_array( $res ) && isset( $res['term_id'] ) ) ? (int) $res['term_id'] : 0;
}

/**
 * Build the footer menus from created pages.
 *
 * @param array<string,int> $page_ids
 */
function greenworld_seed_footer_menus( array $page_ids ): void {
	$menus = array(
		'footer-info'    => array( 'label' => 'Footer Information', 'items' => array( 'about-us', 'contact-us', 'privacy-policy', 'terms-conditions', 'shipping-delivery', 'return-refund-policy' ) ),
		'footer-service' => array( 'label' => 'Footer Customer Service', 'items' => array( 'track-order', 'contact-us', 'faq', 'become-a-distributor', 'health-disclaimer' ) ),
	);
	$locations = get_theme_mod( 'nav_menu_locations' );
	if ( is_array( $locations ) === false ) { $locations = array(); }
	foreach ( $menus as $location => $conf ) {
		$menu = wp_get_nav_menu_object( $conf['label'] );
		$menu_id = ( $menu === false ) ? wp_create_nav_menu( $conf['label'] ) : (int) $menu->term_id;
		if ( is_int( $menu_id ) === false || $menu_id <= 0 ) { continue; }
		$existing = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $existing ) && count( $existing ) > 0 ) { $locations[ $location ] = $menu_id; continue; }
		foreach ( $conf['items'] as $slug ) {
			$pid = $page_ids[ $slug ] ?? 0;
			if ( $pid === 0 ) {
				$page = get_page_by_path( $slug );
				$pid  = $page ? (int) $page->ID : 0;
			}
			if ( $pid > 0 ) {
				wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-title'     => get_the_title( $pid ),
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $pid,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				) );
			}
		}
		$locations[ $location ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}


/**
 * Curated health category list used as a graceful fallback for the mega menu
 * and mobile drawer when no WooCommerce product categories exist yet.
 *
 * @return array<int,string>
 */
function greenworld_health_categories(): array {
	return array(
		"Men's Wellness", "Women's Wellness", 'Vitamins & Minerals', 'Digestive Wellness', 'Immune Support',
		'Bone & Joint Wellness', 'Heart & Circulatory Wellness', 'Weight Management', 'Herbal & Natural Products', 'General Wellness',
	);
}

/**
 * Fetch top-level product categories (cached) for the mega menu.
 *
 * @return array<int,\WP_Term>
 */
function greenworld_top_categories( int $limit = 10 ): array {
	if ( function_exists( 'get_terms' ) === false || taxonomy_exists( 'product_cat' ) === false ) {
		return array();
	}
	$terms = \GreenWorld\Support\Cache::remember(
		'gw_mega_cats_' . $limit,
		6 * HOUR_IN_SECONDS,
		static function () use ( $limit ) {
			$r = get_terms( array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			) );
			return is_array( $r ) ? $r : array();
		}
	);
	return is_array( $terms ) ? $terms : array();
}

/**
 * Render the primary navigation with an integrated health mega menu.
 */
function gw_render_primary_nav( string $shop ): void {
	$consult = home_url( '/health-consultation/' );
	$distrib = home_url( '/become-a-distributor/' );
	echo '<ul id="gw-primary-menu" class="gw-primary__menu">';
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'greenworld' ) );
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $shop ), esc_html__( 'Shop', 'greenworld' ) );

	// Health Categories -> mega.
	echo '<li class="menu-item gw-has-mega" data-gw-mega-item>';
	printf(
		'<a href="%s" class="gw-mega-trigger" aria-haspopup="true" aria-expanded="false">%s <svg class="gw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></a>',
		esc_url( $shop ),
		esc_html__( 'Health Categories', 'greenworld' )
	);
	gw_render_mega( $shop );
	echo '</li>';

	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( add_query_arg( 'orderby', 'popularity', $shop ) ), esc_html__( 'Best Sellers', 'greenworld' ) );
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( add_query_arg( 'orderby', 'date', $shop ) ), esc_html__( 'New Arrivals', 'greenworld' ) );
	printf( '<li class="menu-item gw-menu-accent"><a href="%s">%s</a></li>', esc_url( $consult ), esc_html__( 'Free Consultation', 'greenworld' ) );
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $distrib ), esc_html__( 'Become a Distributor', 'greenworld' ) );
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( home_url( '/about-us/' ) ), esc_html__( 'About', 'greenworld' ) );
	printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( home_url( '/contact-us/' ) ), esc_html__( 'Contact', 'greenworld' ) );
	echo '</ul>';
}

/**
 * Render the mega-menu panel: category columns + a featured promo rail.
 */
function gw_render_mega( string $shop ): void {
	$terms = greenworld_top_categories( 10 );
	echo '<div class="gw-mega" data-gw-mega>';
	echo '<div class="gw-container gw-mega__inner">';
	echo '<div class="gw-mega__cols">';

	if ( count( $terms ) > 0 ) {
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( is_wp_error( $link ) ) { continue; }
			echo '<div class="gw-mega__col">';
			printf( '<a class="gw-mega__cat" href="%s">%s</a>', esc_url( (string) $link ), esc_html( $t->name ) );
			$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $t->term_id, 'hide_empty' => true, 'number' => 5 ) );
			if ( is_array( $children ) && count( $children ) > 0 ) {
				echo '<ul class="gw-mega__sub">';
				foreach ( $children as $c ) {
					$cl = get_term_link( $c );
					if ( is_wp_error( $cl ) === false ) {
						printf( '<li><a href="%s">%s</a></li>', esc_url( (string) $cl ), esc_html( $c->name ) );
					}
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	} else {
		foreach ( greenworld_health_categories() as $name ) {
			$url = add_query_arg( array( 's' => rawurlencode( $name ), 'post_type' => 'product' ), home_url( '/' ) );
			echo '<div class="gw-mega__col">';
			printf( '<a class="gw-mega__cat" href="%s">%s</a>', esc_url( $url ), esc_html( $name ) );
			echo '</div>';
		}
	}

	echo '</div>';

	// Featured promo rail.
	echo '<aside class="gw-mega__feature">';
	echo '<span class="gw-mega__eyebrow">' . esc_html__( 'Not sure where to start?', 'greenworld' ) . '</span>';
	echo '<h3 class="gw-mega__ftitle">' . esc_html__( 'Get a free health consultation', 'greenworld' ) . '</h3>';
	echo '<p class="gw-mega__ftext">' . esc_html__( 'Tell us your health concern and our team will recommend suitable products.', 'greenworld' ) . '</p>';
	echo '<a class="button gw-mega__fbtn" href="' . esc_url( home_url( '/health-consultation/' ) ) . '">' . esc_html__( 'Talk to us', 'greenworld' ) . '</a>';
	echo '<ul class="gw-mega__quick">';
	printf( '<li><a href="%s">%s</a></li>', esc_url( $shop ), esc_html__( 'Shop all products', 'greenworld' ) );
	printf( '<li><a href="%s">%s</a></li>', esc_url( add_query_arg( 'orderby', 'popularity', $shop ) ), esc_html__( 'Best sellers', 'greenworld' ) );
	echo '</ul>';
	echo '</aside>';

	echo '</div></div>';
}


/**
 * Output favicon and app-icon tags from bundled assets when the site owner
 * has not set a WordPress Site Icon. Respects an admin-set Site Icon.
 */
add_action( 'wp_head', 'greenworld_favicon_tags', 2 );
function greenworld_favicon_tags(): void {
	if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
		return;
	}
	$img = GREENWORLD_URI . 'assets/img/';
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon-32.png' ) . '" sizes="32x32" />' . "\n";
	echo '<link rel="icon" href="' . esc_url( $img . 'favicon-192.png' ) . '" sizes="192x192" />' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $img . 'apple-touch-icon.png' ) . '" />' . "\n";
	echo '<link rel="shortcut icon" href="' . esc_url( GREENWORLD_URI . 'favicon.ico' ) . '" />' . "\n";
}

/**
 * Feed official social-profile URLs (set in Customizer) into schema sameAs.
 */
add_filter( 'greenworld_social_profiles', 'greenworld_social_profiles_list' );
function greenworld_social_profiles_list( $links ) {
	foreach ( array( 'gw_facebook', 'gw_instagram', 'gw_tiktok', 'gw_youtube', 'gw_gbp_url' ) as $key ) {
		$u = trim( (string) get_theme_mod( $key, '' ) );
		if ( strlen( $u ) > 0 ) {
			$links[] = $u;
		}
	}
	return is_array( $links ) ? $links : array();
}

/**
 * Inline critical mobile-nav CSS, emitted late in <head> on every front-end
 * page. Injected inline (like the product critical CSS) so it regenerates per
 * request and cannot be defeated by the stale minified stylesheet bundle.
 *
 * Root cause of the earlier "drawer opens but is dimmed and untappable": the
 * OUTER .gw-header has position:relative;z-index:60, which caps its whole
 * subtree — including the fixed drawer — below the nav scrim (z 1150). No inner
 * z-index can escape that cap, so raising .gw-header__sticky did nothing. The
 * fix lifts the entire header above the scrim while the drawer is open, so the
 * drawer paints on top and stays tappable. Desktop (>900px) is untouched.
 */
function gw_critical_nav_css(): void {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="gw-critical-nav">'
		. '.gw-header .gw-drawer-extra{display:none}'
		. '@media(max-width:900px){'
		. '.gw-navbar{display:block !important;background:transparent !important;border:0 !important;box-shadow:none !important}'
		. '.gw-navbar__inner{display:block !important;padding:0 !important;min-height:0 !important}'
		. 'body.gw-nav-open .gw-header{z-index:1200 !important}'
		. '.gw-primary{position:fixed !important;top:0 !important;left:0 !important;bottom:0 !important;width:min(340px,86vw) !important;max-width:86vw !important;height:100% !important;background:#fff !important;z-index:1210 !important;transform:translateX(-100%) !important;transition:transform .28s ease !important;overflow-y:auto !important;-webkit-overflow-scrolling:touch;display:block !important;box-shadow:6px 0 30px rgba(0,0,0,.18) !important}'
		. 'body.gw-nav-open .gw-primary{transform:translateX(0) !important}'
		. '.gw-nav-scrim{position:fixed !important;inset:0 !important;z-index:1150 !important;background:rgba(18,38,32,.5) !important;border:0 !important}'
		. '.gw-nav-scrim[hidden]{display:none !important}'
		. 'body.gw-nav-open .gw-nav-scrim{display:block !important;opacity:1 !important;pointer-events:auto !important}'
		. 'body.gw-nav-open .gw-bottomnav,body.gw-nav-open .gw-whatsapp,body.gw-nav-open .gw-backtotop{z-index:1 !important}'
		. '.gw-primary__head{display:flex !important;align-items:center !important;justify-content:space-between !important;padding:1.1rem 1.2rem !important;background:#123726 !important;color:#fff !important}'
		. '.gw-primary__close{background:transparent !important;border:0 !important;color:#fff !important;font-size:1.8rem !important;line-height:1 !important;cursor:pointer !important}'
		. '.gw-primary__menu{display:flex !important;flex-direction:column !important;align-items:stretch !important;gap:0 !important;padding:.5rem 0 !important;margin:0 !important;list-style:none !important}'
		. '.gw-primary__menu>li{width:100% !important}'
		. '.gw-primary__menu>li>a{display:flex !important;align-items:center !important;justify-content:space-between !important;padding:.9rem 1.2rem !important;border-bottom:1px solid rgba(0,0,0,.06) !important;color:#1c2b22 !important}'
		. '.gw-mega{position:static !important;display:none !important;opacity:1 !important;visibility:visible !important;transform:none !important;box-shadow:none !important;border:0 !important;width:auto !important;background:#f7faf7 !important}'
		. '.gw-has-mega.is-open .gw-mega{display:block !important}'
		. '.gw-drawer-extra{display:block !important;padding:1rem 1.2rem !important;border-top:1px solid rgba(0,0,0,.08) !important}'
		. '}'
		. '</style>' . "\n";
}
add_action( 'wp_head', 'gw_critical_nav_css', 9998 );
