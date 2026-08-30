<?php
declare( strict_types=1 );

namespace GreenWorld\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Asset pipeline: versioned, deferred, critical-CSS aware.
 */
final class Assets implements Bootable {

	public function boot(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'front' ], 20 );
		add_filter( 'script_loader_tag', [ $this, 'defer' ], 10, 3 );
	}

	public function front(): void {
		$ver = GREENWORLD_VERSION;
		// Auto cache-bust: append each asset's file modification time so any
		// edit to the CSS/JS changes the version query string automatically.
		$css_file = get_parent_theme_file_path( 'assets/css/main.css' );
		$js_file  = get_parent_theme_file_path( 'assets/js/app.js' );
		$css_ver  = file_exists( $css_file ) ? $ver . '.' . (string) filemtime( $css_file ) : $ver;
		$js_ver   = file_exists( $js_file ) ? $ver . '.' . (string) filemtime( $js_file ) : $ver;

		wp_enqueue_style( 'greenworld-main', GREENWORLD_URI . 'assets/css/main.css', [], $css_ver );
		wp_style_add_data( 'greenworld-main', 'rtl', 'replace' );

		wp_enqueue_script( 'greenworld-app', GREENWORLD_URI . 'assets/js/app.js', [], $js_ver, true );
		wp_localize_script(
			'greenworld-app',
			'GreenWorld',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'greenworld' ),
				'restUrl' => esc_url_raw( rest_url() ),
				'wcAjax'  => class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
			]
		);
	}

	/**
	 * Defer all theme JavaScript to keep the main thread free (INP/TBT).
	 */
	public function defer( string $tag, string $handle, string $src ): string {
		if ( in_array( $handle, [ 'greenworld-app', 'gw-home', 'greenworld-wizard' ], true ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		return $tag;
	}
}
