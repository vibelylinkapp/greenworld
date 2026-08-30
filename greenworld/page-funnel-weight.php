<?php
/**
 * Template Name: Funnel - Weight Management
 *
 * Thin funnel page: pulls products live from the weight-management category.
 * Assign to any Page from Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

require_once get_parent_theme_file_path( 'inc/funnel/gw-funnel-render.php' );

gwf_render_funnel(
	array(
		'accent'          => '#d1782f',
		'accent_dark'     => '#8a4a17',
		'ink'             => '#3a2a1e',
		'hero_img'        => 'funnel-weight-hero.jpg',
		'wa_text'         => 'Hello Green World, I would like a free consultation about weight management.',
		'eyebrow'         => 'Weight Management',
		'hero_title'      => 'Reach a healthy weight - and keep it - naturally',
		'hero_lead'       => 'Natural support for a healthy metabolism, balanced appetite and lasting weight management - no crash diets. Delivered across Kenya and backed by 30+ years of botanical science.',
		'pain_title'      => 'If this sounds familiar, you are not alone',
		'pain_sub'        => 'Lasting weight change is not about starving yourself - it is about supporting your body to work with you.',
		'pains'           => array(
			'Stubborn weight that will not shift',
			'Constant cravings or a big appetite',
			'Low energy and a slow metabolism',
			'Bloating and sluggish digestion',
			'Tried many diets with no lasting result',
			'Wanting to feel confident in your body again',
		),
		'pain_note'       => 'Whatever you are facing, there is a natural path forward.',
		'why_title'       => 'Support your metabolism, the natural way',
		'why_copy'        => 'Green World blends time-tested botanicals with modern nutritional science to support a healthy metabolism, balanced appetite and better digestion - so you can reach a healthy weight and keep it.',
		'solutions_title' => 'Weight management solutions',
		'solutions_sub'   => "Browse our natural weight range below. Not sure which fits? Tap \"Talk privately on WhatsApp\" and our team will guide you, free and in confidence.",
		'groups'          => array(
			array(
				'title'    => '',
				'type'     => 'category',
				'category' => 'weight-management',
				'limit'    => 12,
				'link'     => home_url( '/product-category/weight-management/' ),
			),
		),
		'proof_title'     => 'Real, lasting results across Kenya',
		'faqs'            => array(
			array( 'Is it safe?', 'Green World products are natural, plant-based supplements used in over 40 countries and are generally well tolerated. If you have a medical condition or take medication, please check with your doctor first.' ),
			array( 'Will I gain the weight back?', 'Our approach supports your metabolism and appetite so results can last. We also guide you on simple habits to maintain your progress.' ),
			array( 'How soon will I see results?', 'Many people see steady changes within a few weeks. Natural support works best combined with consistent healthy habits.' ),
			array( 'Is delivery discreet?', 'Yes. Everything arrives in plain, unbranded packaging, delivered countrywide.' ),
			array( 'How do I pay?', 'Pay on delivery or via M-Pesa, across Kenya.' ),
			array( 'I am not sure which one to choose.', 'Message us on WhatsApp for a free, private recommendation.' ),
		),
		'final_title'     => 'Start your journey today',
		'final_copy'      => 'Talk to a specialist now for a free consultation, or choose your package and we will deliver it to your door.',
	)
);
