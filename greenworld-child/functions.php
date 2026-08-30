<?php
/**
 * GreenWorld Wellness Child - functions.
 *
 * @package GreenWorldChild
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the child stylesheet after the parent design system (assets/css/main.css,
 * which the parent enqueues as the "greenworld-main" handle).
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'greenworld-child',
			get_stylesheet_directory_uri() . '/style.css',
			array( 'greenworld-main' ),
			( file_exists( get_stylesheet_directory() . '/style.css' ) ? (string) filemtime( get_stylesheet_directory() . '/style.css' ) : (string) wp_get_theme()->get( 'Version' ) )
		);
	},
	30
);

/* ============================================================
 * Green World Health Solutions — SEO schema activation (Option B)
 * Added via Town. Activates the theme's rich JSON-LD @graph and
 * feeds it verified business data through the theme's filter hooks.
 *
 * PREREQUISITE (do this in wp-admin, not code):
 *   Rank Math → Dashboard → turn OFF the "Schema (Structured Data)"
 *   module, so the two systems don't emit duplicate Organization /
 *   Product graphs. Keep Rank Math on for sitemaps, titles, redirects
 *   and Search Console.
 * ============================================================ */

// 1) Hand structured data to the theme's connected @graph.
add_filter( 'greenworld_force_schema', '__return_true' );

// 2) sameAs — link the site to verified profiles so Google merges
//    everything into ONE brand entity (knowledge panel + local pack).
add_filter(
	'greenworld_social_profiles',
	static function ( $links ) {
		return array_values(
			array_filter(
				array(
					// Verified from the Google Business Profile share link:
					'https://www.google.com/search?kgmid=/g/11y490xnvz',
					// TODO (optional): add real pages if they exist, else delete:
					// 'https://www.facebook.com/YOURPAGE',
					// 'https://www.instagram.com/YOURHANDLE',
					// 'https://www.youtube.com/@YOURCHANNEL',
					// 'https://www.tiktok.com/@YOURHANDLE',
				)
			)
		);
	}
);

// 3) Opening hours — confirmed from the Contact page (Mon–Sat, 8:30am–6:00pm).
//    NOTE: the Google Business Profile still says "closes 7pm" — update it there to match.
add_filter(
	'greenworld_opening_hours',
	static function () {
		return array(
			array(
				'days'   => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
				'opens'  => '08:30',
				'closes' => '18:00',
			),
		);
	}
);

// 4) A valid price range (the theme default "KES" is not a valid schema value).
add_filter( 'greenworld_price_range', static fn() => 'KSh 1,500 - KSh 26,500' );

// 5) Entity naming — establish ONE distinct entity and disambiguate from the
//    other "Green World" businesses in Kenya.
//    - Legal / organization name (the authoritative entity): Green World Health Solutions
//    - Short brand + preferred site name (WebSite.name):      Green World Health
//    We deliberately do NOT claim "Green World Kenya" as an alternate name — it
//    is a different, competing business, and claiming it would blur the very
//    entity we are trying to make distinct. Alternate names are limited to the
//    short brand and the domain, matching the target knowledge-panel identity.
add_filter( 'greenworld_legal_name', static fn() => 'Green World Health Solutions' );
add_filter( 'greenworld_brand_name', static fn() => 'Green World Health' );
add_filter(
	'greenworld_org_alternate_names',
	static fn() => array( 'Green World Health', 'greenworldhealth.co.ke' )
);

// 5a) More specific schema type for the Nairobi storefront (LocalBusiness node).
add_filter( 'greenworld_local_business_type', static fn() => 'HealthAndBeautyBusiness' );

// 5b) Homepage <title> suffix, aligned to the target search-result wording.
add_filter( 'greenworld_home_title_suffix', static fn() => 'Health & Wellness Products in Kenya' );

// 6) Nairobi office pin + Business Profile map link (strengthens the local pack).
//    TODO: replace the coordinates with the EXACT marker — open the pin in
//    Google Maps, right-click it, and copy the two numbers.
add_filter( 'option_greenworld_geo_lat', static fn() => '-1.2833' ); // TODO: exact latitude
add_filter( 'option_greenworld_geo_lng', static fn() => '36.8172' ); // TODO: exact longitude
add_filter( 'theme_mod_gw_gbp_url', static fn() => 'https://share.google/qY12jaWyfPujXM3Aw' );

// 7) OPTIONAL flat national shipping rate → adds OfferShippingDetails to Product
//    schema (helps Google free listings). Set the real rate, or delete this line.
add_filter( 'option_greenworld_flat_shipping', static fn() => '300' ); // KSh — set the real rate
