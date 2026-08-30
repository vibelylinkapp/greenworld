<?php
/**
 * Page template for "Terms & Conditions" (slug: terms-conditions).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::terms_page();
}

get_footer();
