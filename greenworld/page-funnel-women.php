<?php
/**
 * Template Name: Funnel - Women's Wellness
 *
 * Thin funnel page: defines the config and delegates to the shared engine
 * (inc/funnel/gw-funnel-render.php). Assign to any Page from
 * Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

require_once get_parent_theme_file_path( 'inc/funnel/gw-funnel-render.php' );

gwf_render_funnel(
	array(
		'accent'          => '#9d3c6c',
		'accent_dark'     => '#5f2444',
		'ink'             => '#3a2a34',
		'hero_img'        => 'funnel-women-hero.jpg',
		'wa_text'         => "Hello Green World, I would like a free private consultation about women's health.",
		'eyebrow'         => "Women's Wellness & Fertility",
		'hero_title'      => "Natural care for every season of a woman's health",
		'hero_lead'       => "Gentle, natural support for fertility, fibroids, hormonal balance, menopause and monthly comfort - delivered discreetly across Kenya and backed by Green World's 30+ years of botanical science.",
		'trust'           => array( '100% natural', 'Discreet delivery', 'Trusted across Kenya', 'Free consultation' ),
		'pain_title'      => 'If this sounds familiar, you are not alone',
		'pain_sub'        => 'Thousands of Kenyan women quietly carry these worries. It is common, it is nothing to be ashamed of, and it can be supported naturally.',
		'pains'           => array(
			'Trouble conceiving or carrying to term',
			'Painful, heavy or irregular periods',
			'Fibroids, cysts or blocked fallopian tubes',
			'Hot flushes, mood swings or menopause discomfort',
			'Breast tenderness or lumps that worry you',
			'Feeling out of balance and unheard',
		),
		'pain_note'       => 'Whatever you are facing, there is a gentle, natural path forward.',
		'why_eyebrow'     => 'The gentle, natural way',
		'why_title'       => "Support that works with a woman's body",
		'why_copy'        => 'Green World blends time-tested botanicals with modern nutritional science to support hormonal balance, reproductive health and monthly comfort at the root - rather than only masking the symptoms.',
		'solutions_title' => 'Targeted natural packages for every concern',
		'solutions_sub'   => "Pick the package that matches what you are experiencing. Not sure which one fits? Tap \"Talk privately on WhatsApp\" and our team will guide you, free and in confidence.",
		'groups'          => array(
			array(
				'title' => 'Fertility &amp; Conception',
				'type'  => 'slugs',
				'items' => array(
					array( 'female-infertility-treatment-packageuterus-pillsoy-powerzinckidney-tonnifying-womenroyal-jellypine-pollengarlic', 'Female Infertility Treatment Package', 'A complete natural package to support conception, hormonal balance and a healthy reproductive system.', true ),
					array( 'blocked-fallopian-tubes-treatment-soy-powervit-elecithinkuddingkidney-tonifying-womenuterus-pill', 'Blocked Fallopian Tubes Treatment', 'Natural support to help clear and soothe the fallopian tubes and support fertility.', false ),
				),
			),
			array(
				'title' => 'Reproductive Health',
				'type'  => 'slugs',
				'items' => array(
					array( 'fibroids-treatment-packagepine-pollenroyal-jellyginsengchitosana-powerucp', 'Fibroids Treatment Package', 'Targeted botanical support to help shrink fibroids and ease the symptoms naturally.', false ),
					array( 'breast-disorders-treatment-package', 'Breast Disorders Treatment Package', 'Gentle, natural support for breast health, lumps and tenderness.', false ),
				),
			),
			array(
				'title' => 'Cycle &amp; Menopause',
				'type'  => 'slugs',
				'items' => array(
					array( 'period-pain-treatment-soy-powercalciumaloe-vera', 'Period Pain Treatment', 'Ease painful, heavy periods and cramps with soothing, natural support.', false ),
					array( 'menopause-management-pack', 'Menopause Management Pack', 'Natural relief for hot flushes, mood swings and the changes of menopause.', false ),
				),
			),
		),
		'proof_title'     => 'Women across Kenya are feeling well again',
		'faqs'            => array(
			array( 'Is it safe to use?', 'Green World products are natural, plant-based supplements used in over 40 countries and are generally well tolerated. If you are pregnant, breastfeeding, have a medical condition or take medication, please check with your doctor first.' ),
			array( 'Can I use these while trying to conceive?', 'Many of our fertility packages are designed to support conception. For personal guidance, message us on WhatsApp for a free, confidential recommendation.' ),
			array( 'How soon will I see results?', 'Many women notice changes within a few weeks. Natural support works best used consistently, and fertility and fibroid packages are usually taken over a full cycle or more.' ),
			array( 'Is delivery really discreet?', 'Yes. Everything arrives in plain, unbranded packaging. Only you know what is inside.' ),
			array( 'How do I pay?', 'Pay on delivery or via M-Pesa, with delivery countrywide across Kenya.' ),
			array( 'I am not sure which one to choose.', 'Message us on WhatsApp for a free, private recommendation based on what you are experiencing.' ),
		),
		'final_title'     => 'Take the first step today - privately and with care',
		'final_copy'      => 'Talk to a specialist now for a free, discreet consultation, or choose your package and we will deliver it to your door.',
	)
);
