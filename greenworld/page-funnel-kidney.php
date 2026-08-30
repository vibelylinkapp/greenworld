<?php
/**
 * Template Name: Funnel - Kidney & Urinary
 *
 * Thin funnel page: pulls products live from the kidney-urinary-wellness
 * category. Assign to any Page from Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

require_once get_parent_theme_file_path( 'inc/funnel/gw-funnel-render.php' );

gwf_render_funnel(
	array(
		'accent'          => '#1f7a8c',
		'accent_dark'     => '#124a5a',
		'ink'             => '#16323a',
		'hero_img'        => 'funnel-kidney-hero.jpg',
		'wa_text'         => 'Hello Green World, I would like a free consultation about kidney and urinary wellness.',
		'eyebrow'         => 'Kidney & Urinary Wellness',
		'hero_title'      => 'Support your kidneys and urinary health - naturally',
		'hero_lead'       => "Natural formulas to support kidney function, urinary comfort and healthy fluid balance - delivered across Kenya and backed by Green World's 30+ years of botanical science.",
		'pain_title'      => 'If this sounds familiar, you are not alone',
		'pain_sub'        => 'Kidney and urinary issues are more common than people admit - and there is gentle, natural support that can help.',
		'pains'           => array(
			'Frequent, urgent or uncomfortable urination',
			'Lower back or side discomfort',
			'Swelling in the feet, ankles or face',
			'Persistent tiredness and low energy',
			'Recurring urinary discomfort',
			'Worry about your kidney health',
		),
		'pain_note'       => 'Whatever you are facing, there is a natural path forward.',
		'why_title'       => "Gentle, natural support for your body's filter",
		'why_copy'        => 'Green World blends time-tested botanicals with modern nutritional science to support healthy kidney function, urinary comfort and fluid balance - working with your body at the root.',
		'solutions_title' => 'Kidney & urinary wellness solutions',
		'solutions_sub'   => "Browse our natural kidney and urinary range below. Not sure which fits? Tap \"Talk privately on WhatsApp\" and our team will guide you, free and in confidence.",
		'groups'          => array(
			array(
				'title'    => '',
				'type'     => 'category',
				'category' => 'kidney-urinary-wellness',
				'limit'    => 12,
				'link'     => home_url( '/product-category/kidney-urinary-wellness/' ),
			),
		),
		'proof_title'     => 'Trusted by families across Kenya',
		'faqs'            => array(
			array( 'Is it safe to use?', 'Green World products are natural, plant-based supplements used in over 40 countries and are generally well tolerated. If you have a kidney condition or take medication, please check with your doctor first.' ),
			array( 'Are there side effects?', 'Because the formulas are natural, most people tolerate them well when used as directed. Follow the recommended usage and stop if you notice any reaction.' ),
			array( 'How soon will I see results?', 'Many people notice changes within a few weeks. Natural support works best when used consistently.' ),
			array( 'Is delivery discreet?', 'Yes. Everything arrives in plain, unbranded packaging, delivered countrywide.' ),
			array( 'How do I pay?', 'Pay on delivery or via M-Pesa, across Kenya.' ),
			array( 'I am not sure which one to choose.', 'Message us on WhatsApp for a free, private recommendation.' ),
		),
		'final_title'     => 'Take the first step today',
		'final_copy'      => 'Talk to a specialist now for a free consultation, or choose your product and we will deliver it to your door.',
	)
);
