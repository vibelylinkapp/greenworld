<?php
/**
 * Page template for "Returns & Refunds" (slug: return-refund-policy).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::returns_page();
}

get_footer();
