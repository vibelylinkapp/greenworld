<?php
declare( strict_types=1 );

namespace GreenWorld\Customizer;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Theme Customizer: brand contact, colours, hero and homepage settings, and the
 * health disclaimer. No store data is hardcoded in templates - it all flows
 * from here so the admin can change everything without a developer.
 */
final class Customizer implements Bootable {

	public function boot(): void {
		add_action( 'customize_register', [ $this, 'register' ] );
	}

	/** @return array<string,string> */
	public static function defaults(): array {
		return [
			'gw_phone'             => '0723 579 873',
			'gw_whatsapp'          => '254723579873',
			'gw_email'             => 'info@greenworldhealth.co.ke',
			'gw_hours'             => 'Mon - Sat, 8:30 AM - 6:00 PM',
			'gw_address'           => 'Development House, 11th Floor, Room 7, Nairobi',
			'gw_topbar_notice'     => 'Welcome to Green World Health Solutions — free health consultation available',
			'gw_gbp_url'           => '',
			'gw_wa_order_msg'      => 'Hi Green World Health Solutions, I would like to order: {product} ({url})',
			'gw_delivery_note'     => 'Reliable delivery across Kenya. Nairobi same/next day; countrywide in 1–4 business days.',
			'gw_default_disclaimer'=> 'Product information is provided for general informational purposes and is not a substitute for professional medical advice, diagnosis or treatment. Always consult a qualified healthcare professional before starting any product, especially if you are pregnant, nursing, taking medication or managing a medical condition.',
			'gw_hero_eyebrow'      => 'Trusted health & wellness in Kenya',
			'gw_hero_title'        => 'Your Health. Your Wellness. Your Better Tomorrow.',
			'gw_hero_sub'          => 'Discover carefully selected health and wellness products designed to support healthier everyday living.',
		];
	}

	public static function val( string $key ): string {
		$defaults = self::defaults();
		$fallback = $defaults[ $key ] ?? '';
		return (string) get_theme_mod( $key, $fallback );
	}

	public function register( \WP_Customize_Manager $wp ): void {
		$wp->add_panel( 'greenworld', [ 'title' => __( 'GreenWorld Wellness', 'greenworld' ), 'priority' => 20 ] );

		// --- Header & contact ---
		$wp->add_section( 'gw_contact', [ 'title' => __( 'Header & Contact', 'greenworld' ), 'panel' => 'greenworld' ] );
		$fields = [
			'gw_phone'         => [ 'label' => __( 'Phone number', 'greenworld' ), 'type' => 'text' ],
			'gw_whatsapp'      => [ 'label' => __( 'WhatsApp number (digits only)', 'greenworld' ), 'type' => 'text' ],
			'gw_email'         => [ 'label' => __( 'Support email', 'greenworld' ), 'type' => 'email' ],
			'gw_hours'         => [ 'label' => __( 'Opening hours', 'greenworld' ), 'type' => 'text' ],
			'gw_address'       => [ 'label' => __( 'Address', 'greenworld' ), 'type' => 'text' ],
			'gw_topbar_notice' => [ 'label' => __( 'Top utility bar message', 'greenworld' ), 'type' => 'text' ],
		];
		$d = self::defaults();
		foreach ( $fields as $key => $meta ) {
			$is_email  = ( 'email' === $meta['type'] );
			$wp->add_setting( $key, [ 'default' => $d[ $key ], 'sanitize_callback' => $is_email ? 'sanitize_email' : 'sanitize_text_field', 'transport' => 'refresh' ] );
			$wp->add_control( $key, [ 'label' => $meta['label'], 'section' => 'gw_contact', 'type' => $is_email ? 'email' : 'text' ] );
		}
		$wp->add_setting( 'gw_wa_order_msg', [ 'default' => $d['gw_wa_order_msg'], 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_wa_order_msg', [ 'label' => __( 'WhatsApp order message', 'greenworld' ), 'description' => __( 'Use {product} and {url}.', 'greenworld' ), 'section' => 'gw_contact', 'type' => 'textarea' ] );
		$wp->add_setting( 'gw_delivery_note', [ 'default' => $d['gw_delivery_note'], 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_delivery_note', [ 'label' => __( 'Delivery note (product pages)', 'greenworld' ), 'section' => 'gw_contact', 'type' => 'textarea' ] );

		// --- Branding & colours ---
		$wp->add_section( 'gw_branding', [ 'title' => __( 'Branding & Colours', 'greenworld' ), 'panel' => 'greenworld' ] );
		$colors = [
			'gw_brand_color'  => [ 'label' => __( 'Botanical green (primary)', 'greenworld' ), 'default' => '#1E5B3E' ],
			'gw_brand_deep'   => [ 'label' => __( 'Deep green (headings)', 'greenworld' ), 'default' => '#123726' ],
			'gw_accent_color' => [ 'label' => __( 'Brass accent', 'greenworld' ), 'default' => '#A98545' ],
		];
		foreach ( $colors as $key => $meta ) {
			$wp->add_setting( $key, [ 'default' => $meta['default'], 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'refresh' ] );
			$wp->add_control( new \WP_Customize_Color_Control( $wp, $key, [ 'label' => $meta['label'], 'section' => 'gw_branding' ] ) );
		}

		// --- Hero ---
		$wp->add_section( 'gw_hero', [ 'title' => __( 'Homepage Hero', 'greenworld' ), 'panel' => 'greenworld' ] );
		$wp->add_setting( 'gw_hero_image', [ 'default' => get_template_directory_uri() . '/assets/img/hero.jpg', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp->add_control( new \WP_Customize_Image_Control( $wp, 'gw_hero_image', [ 'label' => __( 'Hero background image', 'greenworld' ), 'section' => 'gw_hero' ] ) );
		foreach ( [ 'gw_hero_eyebrow' => __( 'Eyebrow', 'greenworld' ), 'gw_hero_title' => __( 'Heading', 'greenworld' ), 'gw_hero_sub' => __( 'Supporting text', 'greenworld' ) ] as $key => $label ) {
			$wp->add_setting( $key, [ 'default' => $d[ $key ], 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
			$wp->add_control( $key, [ 'label' => $label, 'section' => 'gw_hero', 'type' => ( 'gw_hero_title' === $key || 'gw_hero_sub' === $key ) ? 'textarea' : 'text' ] );
		}

		// --- Health & compliance ---
		$wp->add_section( 'gw_health', [ 'title' => __( 'Health Disclaimer', 'greenworld' ), 'panel' => 'greenworld', 'description' => __( 'Shown site-wide and on product pages. Keep responsible; do not make medical claims.', 'greenworld' ) ] );
		$wp->add_setting( 'gw_default_disclaimer', [ 'default' => $d['gw_default_disclaimer'], 'sanitize_callback' => 'sanitize_textarea_field', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_default_disclaimer', [ 'label' => __( 'Disclaimer text', 'greenworld' ), 'section' => 'gw_health', 'type' => 'textarea' ] );

		$wp->add_setting( 'gw_gbp_url', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_gbp_url', [ 'label' => __( 'Google Business Profile URL (reviews)', 'greenworld' ), 'section' => 'gw_contact', 'type' => 'url' ] );
		$wp->add_setting( 'gw_geo_lat', [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_geo_lat', [ 'label' => __( 'Map latitude (optional, e.g. -1.2864)', 'greenworld' ), 'section' => 'gw_contact', 'type' => 'text' ] );
		$wp->add_setting( 'gw_geo_lng', [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh' ] );
		$wp->add_control( 'gw_geo_lng', [ 'label' => __( 'Map longitude (optional, e.g. 36.8172)', 'greenworld' ), 'section' => 'gw_contact', 'type' => 'text' ] );

		// --- Social profiles (entity SEO sameAs) ---
		$wp->add_section( 'gw_social', array( 'title' => __( 'Social Profiles', 'greenworld' ), 'panel' => 'greenworld', 'description' => __( 'Official profile URLs. Used for schema sameAs and footer links.', 'greenworld' ) ) );
		foreach ( array( 'gw_facebook' => 'Facebook', 'gw_instagram' => 'Instagram', 'gw_tiktok' => 'TikTok', 'gw_youtube' => 'YouTube' ) as $gw_key => $gw_label ) {
			$wp->add_setting( $gw_key, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw', 'transport' => 'refresh' ) );
			$wp->add_control( $gw_key, array( 'label' => $gw_label, 'section' => 'gw_social', 'type' => 'url' ) );
		}
	}
}
