<?php
/**
 * Page template for "Trust Center" (slug: trust-center).
 *
 * Slug-based template so the Trust Center renders automatically at
 * /trust-center/ with no admin action. Previously the content lived only in the
 * assignable "Trust Center" page template (template-trust-center.php); if that
 * template was not selected on the page, the URL rendered blank. This routes the
 * same single-sourced, Customizer-editable content by slug.
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
