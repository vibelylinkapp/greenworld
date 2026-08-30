<?php
/**
 * Template Name: Funnel - Wellness Supplements
 *
 * Thin funnel page: pulls products live from the health-wellness-supplements
 * category. Assign to any Page from Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

require_once get_parent_theme_file_path( 'inc/funnel/gw-funnel-render.php' );

gwf_render_funnel(
	array(
		'accent'          => '#1f6f43',
		'accent_dark'     => '#123726',
		'ink'             => '#173a2b',
		'hero_img'        => 'funnel-wellness-hero.jpg',
		'wa_text'         => 'Hello Green World, I would like a free consultation about health and wellness supplements.',
		'eyebrow'         => 'Everyday Health & Wellness',
		'hero_title'      => 'Everyday nutrition to help your whole family thrive',
		'hero_lead'       => 'Natural supplements for immunity, energy and daily wellbeing - quality botanicals the whole family can trust, delivered across Kenya and backed by 30+ years of nutritional science.',
		'pain_title'      => 'Small daily habits, a stronger you',
		'pain_sub'        => 'Good health starts with good nutrition. When your body has what it needs, everything feels easier.',
		'pains'           => array(
			'Low energy or feeling tired often',
			'Falling sick more than you would like',
			'Poor appetite or slow recovery',
			'Gaps in your daily nutrition',
			'Stress and poor sleep',
			'Wanting to stay well and prevent illness',
		),
		'pain_note'       => 'Whatever your goal, there is a natural way to support it.',
		'why_title'       => 'Quality nutrition your family can trust',
		'why_copy'        => 'Green World blends time-tested botanicals with modern nutritional science to support immunity, energy and everyday wellbeing - natural formulas used by families in over 40 countries.',
		'solutions_title' => 'Health & wellness supplements',
		'solutions_sub'   => "Browse our everyday wellness range below. Not sure which fits? Tap \"Talk privately on WhatsApp\" and our team will guide you, free and in confidence.",
		'groups'          => array(
			array(
				'title'    => '',
				'type'     => 'category',
				'category' => 'health-wellness-supplements',
				'limit'    => 12,
				'link'     => home_url( '/product-category/health-wellness-supplements/' ),
			),
		),
		'proof_title'     => 'Families across Kenya trust Green World',
		'faqs'            => array(
			array( 'Are these safe for daily use?', 'Green World products are natural, plant-based supplements used in over 40 countries and are generally well tolerated for daily use. If you have a medical condition or take medication, please check with your doctor first.' ),
			array( 'Can the whole family use them?', 'Many of our supplements suit adults and older children. Message us on WhatsApp and we will recommend what is right for each member of your family.' ),
			array( 'How soon will I feel a difference?', 'Many people notice more energy and better wellbeing within a few weeks of consistent use.' ),
			array( 'Is delivery discreet?', 'Yes. Everything arrives in plain, unbranded packaging, delivered countrywide.' ),
			array( 'How do I pay?', 'Pay on delivery or via M-Pesa, across Kenya.' ),
			array( 'I am not sure which one to choose.', 'Message us on WhatsApp for a free, private recommendation.' ),
		),
		'final_title'     => 'Invest in your health today',
		'final_copy'      => 'Talk to a specialist now for a free consultation, or choose your supplements and we will deliver them to your door.',
	)
);
