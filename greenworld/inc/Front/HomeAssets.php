<?php
declare( strict_types=1 );

namespace GreenWorld\Front;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the homepage / front-end enhancement CSS + JS
 * (hero carousel, featured scroller, product-grid alignment fix).
 * Scoped selectors make it safe to load site-wide.
 */
final class HomeAssets implements Bootable {

	public function boot(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	public function assets(): void {
		wp_enqueue_style( 'gw-home', GREENWORLD_URI . 'assets/css/gw-home.css', array(), GREENWORLD_VERSION );
		wp_enqueue_style( 'gw-trust', GREENWORLD_URI . 'assets/css/gw-trust.css', array( 'gw-home' ), GREENWORLD_VERSION );
		wp_enqueue_script( 'gw-home', GREENWORLD_URI . 'assets/js/gw-home.js', array(), GREENWORLD_VERSION, true );
	}
}
