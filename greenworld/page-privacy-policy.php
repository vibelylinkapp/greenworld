<?php
/**
 * Page template for "Privacy Policy" (slug: privacy-policy).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::privacy_page();
}

get_footer();
