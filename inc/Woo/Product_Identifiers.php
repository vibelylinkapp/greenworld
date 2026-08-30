<?php
declare( strict_types=1 );

namespace GreenWorld\Woo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Brand / GTIN / MPN fields to the product Inventory panel so merchants can
 * supply the identifiers Google Merchant Center requires. Values feed the JSON-LD
 * Product schema emitted by GreenWorld\Seo\Schema.
 */
final class Product_Identifiers implements Bootable {

	public function boot(): void {
		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'fields' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save' ] );
	}

	public function fields(): void {
		echo '<div class="options_group">';
		woocommerce_wp_text_input( [
			'id'          => '_greenworld_brand',
			'label'       => __( 'Brand (Google)', 'greenworld' ),
			'desc_tip'    => true,
			'description' => __( 'Manufacturer brand, e.g. Makita. Required by Google Merchant Center unless a GTIN is supplied.', 'greenworld' ),
		] );
		woocommerce_wp_text_input( [
			'id'          => '_greenworld_gtin',
			'label'       => __( 'GTIN / UPC / EAN', 'greenworld' ),
			'desc_tip'    => true,
			'description' => __( 'Global Trade Item Number (barcode). Strongly recommended for Google Shopping.', 'greenworld' ),
		] );
		woocommerce_wp_text_input( [
			'id'          => '_greenworld_mpn',
			'label'       => __( 'MPN', 'greenworld' ),
			'desc_tip'    => true,
			'description' => __( 'Manufacturer Part Number. Defaults to the SKU when left blank.', 'greenworld' ),
		] );
		echo '</div>';
	}

	public function save( \WC_Product $product ): void {
		$keys = [ '_greenworld_brand', '_greenworld_gtin', '_greenworld_mpn' ];
		foreach ( $keys as $key ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( (string) $_POST[ $key ] ) : '';
			$product->update_meta_data( $key, sanitize_text_field( $raw ) );
		}
	}
}
