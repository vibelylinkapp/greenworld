<?php
/**
 * GreenWorld homepage. Renders premium, restrained sections from live
 * WooCommerce data. If a static front page with content is set, that content
 * prints between the hero and the category sections.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

use GreenWorld\Front\Home;

get_header();

Home::hero();
Home::trust_strip();
Home::shop_by_category();

if ( class_exists( '\GreenWorld\Content\Relations' ) ) {
	echo '<section class="gw-container gw-section gw-home-authority">';
	echo '<div class="gw-sec__head"><div class="gw-sec__heads"><span class="gw-eyebrow">Explore</span><h2 class="gw-sec__title">Explore our world of wellness</h2></div>';
	echo '<a class="gw-sec__more" href="' . esc_url( \GreenWorld\Content\Relations::hub_url() ) . '">Health &amp; Wellness Guide &rarr;</a></div>';
	echo '<div class="gw-marquee gw-marquee--pillars" data-gw-marquee>';
	echo do_shortcode( '[gw_pillars]' );
	echo '</div>';
	echo '</section>';
}

if ( is_page() && have_posts() ) {
	while ( have_posts() ) {
		the_post();
		if ( trim( (string) get_the_content() ) !== '' ) {
			echo '<div class="gw-container gw-section gw-userblock">';
			the_content();
			echo '</div>';
		}
	}
}

Home::featured_products();
Home::health_focus();
Home::full_body_scan();
Home::best_sellers();
Home::join_band();
Home::consultation_band();
Home::why_choose();
\GreenWorld\Front\TrustCenter::why_trust();

get_footer();
