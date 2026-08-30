<?php
declare( strict_types=1 );

namespace GreenWorld\Setup;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Declares theme feature support (media, editor, WooCommerce) and nav menus.
 */
final class Supports implements Bootable {

	public function boot(): void {
		add_action( 'after_setup_theme', [ $this, 'register' ] );
	}

	public function register(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ] );
		add_theme_support( 'custom-logo', [ 'height' => 64, 'width' => 240, 'flex-height' => true, 'flex-width' => true ] );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'custom-line-height' );
		add_theme_support( 'custom-spacing' );

		// WooCommerce.
		add_theme_support( 'woocommerce', [
			'thumbnail_image_width' => 600,
			'single_image_width'    => 1200,
			'product_grid'          => [ 'default_columns' => 4, 'min_columns' => 2, 'max_columns' => 4 ],
		] );
		add_theme_support( 'wc-product-gallery-lightbox' );

		register_nav_menus( [
			'primary'         => __( 'Primary Menu', 'greenworld' ),
			'mega'            => __( 'Health Categories (Mega Menu)', 'greenworld' ),
			'utility'         => __( 'Top Utility Bar', 'greenworld' ),
			'mobile-bottom'   => __( 'Mobile Bottom Navigation', 'greenworld' ),
			'footer-info'     => __( 'Footer: Information', 'greenworld' ),
			'footer-service'  => __( 'Footer: Customer Service', 'greenworld' ),
			'footer-health'   => __( 'Footer: Health Categories', 'greenworld' ),
		] );
	}
}
