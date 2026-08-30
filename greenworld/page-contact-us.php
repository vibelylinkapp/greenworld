<?php
/**
 * Page template for "Contact Us" (slug: contact-us).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::contact_page();
}

get_footer();
