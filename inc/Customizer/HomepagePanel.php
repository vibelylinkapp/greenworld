<?php
declare( strict_types=1 );

namespace GreenWorld\Customizer;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Customizer controls for the homepage: hero slides, category list,
 * health-focus images, and the full body scan booking band. Keeps the
 * homepage fully editable (nothing hardcoded).
 */
final class HomepagePanel implements Bootable {

	public function boot(): void {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( \WP_Customize_Manager $wp ): void {
		if ( ! $wp->get_panel( 'greenworld' ) ) {
			$wp->add_panel( 'greenworld', array( 'title' => __( 'GreenWorld Theme', 'greenworld' ), 'priority' => 30 ) );
		}

		/* Hero slides ------------------------------------------------ */
		$wp->add_section( 'gw_home_hero', array( 'title' => __( 'Homepage: Hero slides', 'greenworld' ), 'panel' => 'greenworld', 'priority' => 10 ) );
		foreach ( array( 1, 2, 3, 4, 5 ) as $i ) {
			$wp->add_setting( "gw_hero{$i}_image", array( 'sanitize_callback' => 'esc_url_raw' ) );
			$wp->add_control( new \WP_Customize_Image_Control( $wp, "gw_hero{$i}_image", array( 'label' => sprintf( __( 'Slide %d image', 'greenworld' ), $i ), 'description' => __( 'Recommended size: 1600 x 900px (16:9). Use JPG or WebP, under 300KB for fast loading.', 'greenworld' ), 'section' => 'gw_home_hero' ) ) );
			$wp->add_setting( "gw_hero{$i}_eyebrow", array( 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp->add_control( "gw_hero{$i}_eyebrow", array( 'label' => sprintf( __( 'Slide %d eyebrow', 'greenworld' ), $i ), 'section' => 'gw_home_hero', 'type' => 'text' ) );
			$wp->add_setting( "gw_hero{$i}_title", array( 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp->add_control( "gw_hero{$i}_title", array( 'label' => sprintf( __( 'Slide %d title', 'greenworld' ), $i ), 'section' => 'gw_home_hero', 'type' => 'text' ) );
			$wp->add_setting( "gw_hero{$i}_sub", array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
			$wp->add_control( "gw_hero{$i}_sub", array( 'label' => sprintf( __( 'Slide %d subtitle', 'greenworld' ), $i ), 'section' => 'gw_home_hero', 'type' => 'textarea' ) );
			$wp->add_setting( "gw_hero{$i}_cta", array( 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp->add_control( "gw_hero{$i}_cta", array( 'label' => sprintf( __( 'Slide %d button text', 'greenworld' ), $i ), 'section' => 'gw_home_hero', 'type' => 'text' ) );
			$wp->add_setting( "gw_hero{$i}_url", array( 'sanitize_callback' => 'esc_url_raw' ) );
			$wp->add_control( "gw_hero{$i}_url", array( 'label' => sprintf( __( 'Slide %d button link', 'greenworld' ), $i ), 'section' => 'gw_home_hero', 'type' => 'url' ) );
		}

		/* Categories + focus images --------------------------------- */
		$wp->add_section( 'gw_home_cats', array( 'title' => __( 'Homepage: Categories', 'greenworld' ), 'panel' => 'greenworld', 'priority' => 20 ) );
		$wp->add_setting( 'gw_home_categories', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp->add_control( 'gw_home_categories', array( 'label' => __( 'Category names (comma separated, up to 10)', 'greenworld' ), 'description' => __( 'Leave blank to use the default 10 health categories.', 'greenworld' ), 'section' => 'gw_home_cats', 'type' => 'text' ) );
		foreach ( array( 'men' => __( "Men's", 'greenworld' ), 'women' => __( "Women's", 'greenworld' ), 'general' => __( 'General', 'greenworld' ) ) as $k => $lbl ) {
			$wp->add_setting( "gw_focus_{$k}_image", array( 'sanitize_callback' => 'esc_url_raw' ) );
			$wp->add_control( new \WP_Customize_Image_Control( $wp, "gw_focus_{$k}_image", array( 'label' => sprintf( __( '%s health image', 'greenworld' ), $lbl ), 'section' => 'gw_home_cats' ) ) );
		}

		/* Full body scan band --------------------------------------- */
		$wp->add_section( 'gw_scan', array( 'title' => __( 'Homepage: Full Body Scan', 'greenworld' ), 'panel' => 'greenworld', 'priority' => 30 ) );
		$wp->add_setting( 'gw_scan_enable', array( 'default' => '1', 'sanitize_callback' => array( $this, 'bool01' ) ) );
		$wp->add_control( 'gw_scan_enable', array( 'label' => __( 'Show scan booking band', 'greenworld' ), 'section' => 'gw_scan', 'type' => 'checkbox' ) );
		$wp->add_setting( 'gw_scan_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp->add_control( 'gw_scan_title', array( 'label' => __( 'Title', 'greenworld' ), 'section' => 'gw_scan', 'type' => 'text' ) );
		$wp->add_setting( 'gw_scan_price', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp->add_control( 'gw_scan_price', array( 'label' => __( 'Price label', 'greenworld' ), 'section' => 'gw_scan', 'type' => 'text' ) );
		$wp->add_setting( 'gw_scan_desc', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp->add_control( 'gw_scan_desc', array( 'label' => __( 'Description', 'greenworld' ), 'section' => 'gw_scan', 'type' => 'textarea' ) );
		$wp->add_setting( 'gw_scan_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		$wp->add_control( 'gw_scan_url', array( 'label' => __( 'Booking link (leave blank to use WhatsApp)', 'greenworld' ), 'section' => 'gw_scan', 'type' => 'url' ) );
	}

	public function bool01( $value ): string {
		return ( '1' === (string) $value || 1 === $value || true === $value ) ? '1' : '';
	}
}
