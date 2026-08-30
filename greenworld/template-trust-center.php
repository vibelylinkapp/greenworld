<?php
/**
 * Template Name: Trust Center
 *
 * Renders the GreenWorld Trust Center. Create a Page (e.g. "Trust Center",
 * slug trust-center) and select this template under Page Attributes.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
	\GreenWorld\Front\TrustCenter::render();
}

if ( is_page() && have_posts() ) {
	while ( have_posts() ) {
		the_post();
		if ( trim( (string) get_the_content() ) !== '' ) {
			echo '<div class="gw-container gw-section gw-richtext">';
			the_content();
			echo '</div>';
		}
	}
}

get_footer();
