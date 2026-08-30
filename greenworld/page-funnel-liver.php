<?php
/**
 * Template Name: Funnel - Liver & Detox
 *
 * Thin funnel page: pulls products live from the liver-detox-wellness
 * category. Assign to any Page from Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

require_once get_parent_theme_file_path( 'inc/funnel/gw-funnel-render.php' );

gwf_render_funnel(
	array(
		'accent'          => '#2f8f4e',
		'accent_dark'     => '#1b5730',
		'ink'             => '#173a2b',
		'hero_img'        => 'funnel-liver-hero.jpg',
		'wa_text'         => 'Hello Green World, I would like a free consultation about liver and detox wellness.',
		'eyebrow'         => 'Liver & Detox Wellness',
		'hero_title'      => 'Cleanse, renew and feel lighter - naturally',
		'hero_lead'       => 'Natural support for your liver, digestion and whole-body detox - helping you feel cleaner, lighter and more energised. Delivered across Kenya, backed by 30+ years of botanical science.',
		'pain_title'      => 'If your body feels heavy and sluggish',
		'pain_sub'        => 'When your system is overloaded, everything feels harder. A gentle, natural detox can help you feel like yourself again.',
		'pains'           => array(
			'Low energy, sluggishness or brain fog',
			'Bloating or poor digestion',
			'Dull skin or frequent breakouts',
			'Feeling heavy after rich food or alcohol',
			'Struggling to lose weight',
			'Wanting a fresh, natural reset',
		),
		'pain_note'       => 'Whatever you are facing, there is a natural path forward.',
		'why_title'       => 'A gentle reset for your liver and gut',
		'why_copy'        => 'Green World blends time-tested botanicals with modern nutritional science to support your liver, digestion and natural detox pathways - helping your body cleanse and renew from the inside.',
		'solutions_title' => 'Liver & detox solutions',
		'solutions_sub'   => "Browse our natural liver and detox range below. Not sure which fits? Tap \"Talk privately on WhatsApp\" and our team will guide you, free and in confidence.",
		'groups'          => array(
			array(
				'title'    => '',
				'type'     => 'category',
				'category' => 'liver-detox-wellness',
				'limit'    => 12,
				'link'     => home_url( '/product-category/liver-detox-wellness/' ),
			),
		),
		'proof_title'     => 'People across Kenya are feeling refreshed',
		'faqs'            => array(
			array( 'Is it safe to use?', 'Green World products are natural, plant-based supplements used in over 40 countries and are generally well tolerated. If you have a medical condition or take medication, please check with your doctor first.' ),
			array( 'Are there side effects?', 'Because the formulas are natural, most people tolerate them well when used as directed. Follow the recommended usage and stop if you notice any reaction.' ),
			array( 'How soon will I feel a difference?', 'Many people feel lighter and more energised within a couple of weeks. Natural support works best used consistently.' ),
			array( 'Is delivery discreet?', 'Yes. Everything arrives in plain, unbranded packaging, delivered countrywide.' ),
			array( 'How do I pay?', 'Pay on delivery or via M-Pesa, across Kenya.' ),
			array( 'I am not sure which one to choose.', 'Message us on WhatsApp for a free, private recommendation.' ),
		),
		'final_title'     => 'Start your natural reset today',
		'final_copy'      => 'Talk to a specialist now for a free consultation, or choose your product and we will deliver it to your door.',
	)
);
