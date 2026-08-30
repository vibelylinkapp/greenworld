<?php
/**
 * Page template for "About Us" (slug: about-us).
 *
 * Fills the page with the theme's single-sourced company / trust content
 * (About, Our Business, Sourcing, Authenticity, Regulatory, Customer
 * Protection, Team, Contact). Copy is Customizer-editable under GreenWorld
 * Theme > Trust Center.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::about_page();
}

get_footer();
