<?php
/**
 * Page template for "Shipping & Delivery" (slug: shipping-delivery).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::shipping_page();
}

get_footer();
