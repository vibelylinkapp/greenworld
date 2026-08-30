<?php
declare( strict_types=1 );

namespace GreenWorld\Content;

defined( 'ABSPATH' ) || exit;

/**
 * TopicMap — the single source of truth for GreenWorld's topical authority layer.
 *
 * Defines the eight commercial pillars, their product-category slugs, the
 * matching commercial landing pages, related-pillar relationships, the content
 * hub topic taxonomy, the search-intent map (anti-cannibalisation) and per-pillar
 * FAQ banks. Every other content/entity module (Relations, InternalLinks, Seeder,
 * Schema, IntentMap admin) reads from here, so the taxonomy, the internal linking
 * and the structured data can never drift apart.
 *
 * Copy is deliberately factual and YMYL-safe: no diagnosis, cure or treatment
 * claims anywhere in this file.
 */
final class TopicMap {

	/** Content-build version. Bump to force the Seeder to re-run. */
	public const BUILD_VERSION = '1';

	public const GUIDE_CPT   = 'gw_guide';
	public const GUIDE_TAX   = 'gw_guide_topic';
	public const HUB_SLUG    = 'health-wellness-guide';
	public const LANDING_TPL = 'template-landing.php';

	/**
	 * The eight pillars that define what Green World Health Solutions sells and
	 * knows about.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function pillars(): array {
		return [
			'health-products' => [
				'label'   => 'Health Products',
				'cat'     => 'health-products',
				'landing' => 'health-products-kenya',
				'title'   => 'Health Products in Kenya',
				'intent'  => 'health products Kenya',
				'topic'   => 'natural-health',
				'blurb'   => 'Everyday health and wellbeing products sourced for Kenyan households.',
				'intro'   => 'Green World Health Solutions offers a broad range of health products for everyday wellbeing in Kenya, from daily-use essentials to targeted wellness support. Browse the categories below, read our guides to choose well, and order online for delivery across the country.',
				'related' => [ 'natural-products', 'supplements', 'wellness-products' ],
				'faqs'    => [
					[ 'What health products does Green World Health Solutions sell?', 'We carry health and wellbeing products across natural products, supplements, wellness, nutrition, herbal and personal-care categories. Browse the category pages to see the current range.' ],
					[ 'Do you deliver health products across Kenya?', 'Yes. We deliver nationwide, with pay-on-delivery available in many areas. Delivery timing depends on your location; see our Shipping and Delivery page for details.' ],
					[ 'How do I choose the right health product?', 'Start with our Health and Wellness Guide, read the product information on each page, and contact us for guidance. Consult a qualified health professional before starting any new product.' ],
				],
			],
			'natural-products' => [
				'label'   => 'Natural Products',
				'cat'     => 'natural-products',
				'landing' => 'natural-products-kenya',
				'title'   => 'Natural Products in Kenya',
				'intent'  => 'natural products Kenya',
				'topic'   => 'natural-health',
				'blurb'   => 'Nature-derived products for people who prefer a natural approach to wellbeing.',
				'intro'   => 'Our natural products range brings together nature-derived options for Kenyans who prefer a natural approach to daily wellbeing. Explore the sub-categories, learn what natural means for each product in our guides, and buy online with delivery across Kenya.',
				'related' => [ 'herbal-products', 'wellness-products', 'health-products' ],
				'faqs'    => [
					[ 'What are natural products?', 'Natural products are derived from plant, mineral or other naturally occurring sources rather than being wholly synthetic. Always read the ingredient and usage information on each product page.' ],
					[ 'Are natural products safe to use?', 'Follow the usage guidance on each product and consult a qualified health professional before use, especially if you are pregnant, nursing, managing a health condition or taking medication.' ],
					[ 'How are natural products different from herbal products?', 'Herbal products are a subset of natural products made specifically from herbs and botanicals. See our Herbal Products category for that range.' ],
				],
			],
			'wellness-products' => [
				'label'   => 'Wellness Products',
				'cat'     => 'wellness-products',
				'landing' => 'wellness-products-kenya',
				'title'   => 'Wellness Products in Kenya',
				'intent'  => 'wellness products Kenya',
				'topic'   => 'wellness',
				'blurb'   => 'Products that support a balanced, active and healthy lifestyle.',
				'intro'   => 'Wellness products support a balanced, active lifestyle, from daily-routine support to products that help you feel your best. Discover the range, read our wellness guides, and order online for nationwide delivery.',
				'related' => [ 'health-products', 'nutrition', 'personal-care' ],
				'faqs'    => [
					[ 'What counts as a wellness product?', 'Wellness products support general wellbeing and healthy routines. The exact benefits vary by product, so read each product page for its specific information.' ],
					[ 'Which wellness product should I start with?', 'It depends on your goals. Read our Wellness guides in the Health and Wellness Guide, then contact us if you would like help choosing.' ],
				],
			],
			'nutrition' => [
				'label'   => 'Nutrition',
				'cat'     => 'nutrition',
				'landing' => 'nutrition-products-kenya',
				'title'   => 'Nutrition Products in Kenya',
				'intent'  => 'nutrition products Kenya',
				'topic'   => 'nutrition',
				'blurb'   => 'Nutrition-focused products to support a balanced diet.',
				'intro'   => 'Our nutrition range supports a balanced diet with nutrition-focused products for everyday life. Explore the options, learn the basics in our nutrition guides, and buy online with delivery across Kenya.',
				'related' => [ 'supplements', 'wellness-products', 'healthy-living' ],
				'faqs'    => [
					[ 'Do nutrition products replace a balanced diet?', 'No. Nutrition products are intended to complement, not replace, a varied and balanced diet. Read each product page and consult a professional for dietary advice.' ],
					[ 'How do I use nutrition products?', 'Follow the directions on each product. Our nutrition guides explain general principles; individual needs vary.' ],
				],
			],
			'supplements' => [
				'label'   => 'Supplements',
				'cat'     => 'supplements',
				'landing' => 'health-supplements-kenya',
				'title'   => 'Health Supplements in Kenya',
				'intent'  => 'health supplements Kenya',
				'topic'   => 'nutrition',
				'blurb'   => 'Dietary supplements to complement your daily routine.',
				'intro'   => 'Browse dietary supplements chosen to complement a healthy daily routine. Read our supplement guides to understand what each type is for, then order online for delivery across Kenya.',
				'related' => [ 'nutrition', 'natural-products', 'health-products' ],
				'faqs'    => [
					[ 'What are dietary supplements?', 'Dietary supplements are products taken to add nutrients or other substances to the diet. They are not a substitute for a balanced diet or medical care.' ],
					[ 'Should I consult a professional before taking supplements?', 'Yes. Consult a qualified health professional before starting any supplement, especially if you are pregnant, nursing, managing a health condition or taking medication.' ],
				],
			],
			'personal-care' => [
				'label'   => 'Personal Care',
				'cat'     => 'personal-care',
				'landing' => 'personal-care-products-kenya',
				'title'   => 'Personal Care Products in Kenya',
				'intent'  => 'personal care products Kenya',
				'topic'   => 'wellness',
				'blurb'   => 'Everyday personal-care and hygiene products.',
				'intro'   => 'Our personal-care range covers everyday hygiene and self-care essentials. Explore the products, read our care guides, and order online for delivery across Kenya.',
				'related' => [ 'wellness-products', 'natural-products', 'health-products' ],
				'faqs'    => [
					[ 'What personal-care products do you stock?', 'Our personal-care category covers everyday hygiene and self-care items. Browse the category page for the current range.' ],
					[ 'How should I store personal-care products?', 'Follow the storage guidance on each product label. Our product-care guide covers general best practice.' ],
				],
			],
			'herbal-products' => [
				'label'   => 'Herbal Products',
				'cat'     => 'herbal-products',
				'landing' => 'herbal-products-kenya',
				'title'   => 'Herbal Products in Kenya',
				'intent'  => 'herbal products Kenya',
				'topic'   => 'natural-health',
				'blurb'   => 'Herbal and botanical products from trusted sources.',
				'intro'   => 'Our herbal range brings together herbal and botanical products for a traditional, plant-based approach to wellbeing. Learn how to use them safely in our guides, then buy online with delivery across Kenya.',
				'related' => [ 'natural-products', 'supplements', 'wellness-products' ],
				'faqs'    => [
					[ 'What are herbal products?', 'Herbal products are made from herbs and botanicals. Read the ingredient and usage information on each product page before use.' ],
					[ 'Are herbal products suitable for everyone?', 'Not always. Consult a qualified health professional before use, especially if you are pregnant, nursing, managing a health condition or taking medication.' ],
				],
			],
			'healthy-living' => [
				'label'   => 'Healthy Living',
				'cat'     => 'healthy-living',
				'landing' => null, // Content pillar: routed to the Health and Wellness Guide, not a doorway page.
				'title'   => 'Healthy Living',
				'intent'  => 'healthy living Kenya',
				'topic'   => 'healthy-living',
				'blurb'   => 'Guides and products for everyday healthy living.',
				'intro'   => 'Healthy living is about everyday habits. Our Healthy Living guides share practical, non-medical tips and link to the products that support them.',
				'related' => [ 'wellness-products', 'nutrition', 'natural-products' ],
				'faqs'    => [],
			],
		];
	}

	/** The master transactional landing that ties every pillar together. */
	public static function commercial_hub(): array {
		return [
			'slug'   => 'buy-health-products-online-kenya',
			'title'  => 'Buy Health Products Online in Kenya',
			'intent' => 'buy health products online Kenya',
			'intro'  => 'Buy health, natural, wellness, nutrition, supplement, herbal and personal-care products online from Green World Health Solutions, with delivery across Kenya and pay-on-delivery available in many areas. Choose a category below to start shopping, or read our Health and Wellness Guide for help choosing.',
		];
	}

	/** @return array<string,string> topic slug => label */
	public static function hub_topics(): array {
		return [
			'natural-health' => 'Natural Health',
			'nutrition'      => 'Nutrition',
			'wellness'       => 'Wellness',
			'healthy-living' => 'Healthy Living',
			'product-guides' => 'Product Guides',
			'faqs'           => 'Frequently Asked Questions',
		];
	}

	/**
	 * Search-intent map. One canonical destination per intent prevents pages from
	 * competing against each other.
	 *
	 * @return array<int,array{query:string,target:string,type:string,note:string}>
	 */
	public static function intent_map(): array {
		$rows = [
			[ 'query' => 'Green World Health Solutions', 'target' => 'Homepage', 'type' => 'brand', 'note' => 'Primary brand entity.' ],
			[ 'query' => 'Green World Health Solutions Kenya', 'target' => 'Homepage', 'type' => 'brand', 'note' => 'Brand plus geo modifier.' ],
			[ 'query' => 'buy health products online Kenya', 'target' => 'Buy Health Products Online (commercial hub)', 'type' => 'transactional', 'note' => 'Master ecommerce landing.' ],
		];
		foreach ( self::pillars() as $p ) {
			$target = ! empty( $p['landing'] ) ? $p['title'] . ' (landing page)' : $p['label'] . ' (Health and Wellness Guide)';
			$rows[] = [ 'query' => (string) $p['intent'], 'target' => $target, 'type' => 'commercial', 'note' => 'One canonical page per pillar; no competing duplicates.' ];
		}
		$rows[] = [ 'query' => '[product] Kenya', 'target' => 'Matching product page', 'type' => 'transactional', 'note' => 'Individual product pages own product-name queries.' ];
		$rows[] = [ 'query' => '[product] price Kenya', 'target' => 'Matching product page', 'type' => 'transactional', 'note' => 'Price lives on the product page; do not build price-only pages.' ];
		$rows[] = [ 'query' => 'what is [topic]', 'target' => 'Educational guide (Health and Wellness Guide)', 'type' => 'informational', 'note' => 'Informational intent handled by guides, not category pages.' ];
		$rows[] = [ 'query' => '[product/topic] benefits', 'target' => 'Product page or matching guide', 'type' => 'informational', 'note' => 'Benefit content lives on the product or a guide, chosen to avoid overlap.' ];
		return $rows;
	}

	/** General FAQs used on the commercial hub and homepage. */
	public static function general_faqs(): array {
		return [
			[ 'Where is Green World Health Solutions located?', 'We are at Development House, 11th Floor, Room 7, Nairobi. You are welcome to visit during business hours, Monday to Saturday, 8:30 AM to 6:00 PM. You can also reach us on 0723 579 873 or info@greenworldhealth.co.ke.' ],
			[ 'Are your products genuine Green World products?', 'Yes. Every item we sell is a genuine Green World brand product sourced through the group supply chain, never counterfeit or grey-market stock. If you would like to verify a product, contact us and we will help you check it.' ],
			[ 'How do I place an order?', 'Add the products you want to your cart and check out online, or message us on WhatsApp at 0723 579 873 and we will place the order for you. We then confirm the order and arrange delivery.' ],
			[ 'What payment methods do you accept?', 'We accept M-Pesa, bank transfer, and cash (on delivery within Nairobi or on pickup at our office). All online payments run over a secure, encrypted connection.' ],
			[ 'Do you deliver across Kenya?', 'Yes, we deliver nationwide. Nairobi orders placed before 5:00 PM can arrive the same day. Major towns are typically next day, and other areas take about 2 to 3 days via trusted bus, shuttle or courier services such as Wells Fargo and G4S.' ],
			[ 'How much does delivery cost?', 'Delivery within Nairobi can be free for same-day orders. Countrywide parcel delivery starts from around KSh 300 by bus or shuttle, and courier delivery from around KSh 550, depending on your location and parcel size. We confirm the exact cost when we confirm your order.' ],
			[ 'Do you ship internationally?', 'Yes. We ship worldwide via DHL for parcels of about 0.5 kg or less, typically arriving in 3 to 5 working days. We have delivered to customers across Africa, Europe, Asia and North America.' ],
			[ 'Can I track my order?', 'Yes. Once your order is dispatched we share the tracking or waybill details on WhatsApp so you can follow it to your door or nearest pickup branch.' ],
			[ 'What is your returns and refund policy?', 'Contact us within 7 days of delivery with your order number. Damaged, defective or incorrect items are replaced free or fully refunded. Sealed, unopened non-consumable items can be returned within 7 days. For safety and hygiene, opened health or supplement products cannot be returned unless they arrived damaged or incorrect. Approved refunds are sent to your M-Pesa or bank account within 3 to 5 business days.' ],
			[ 'Do I need an account to order?', 'No. You can check out as a guest. Creating an account is optional and simply makes it faster to reorder and to view your order history.' ],
			[ 'How do I choose the right product?', 'Start with the product information on each page, or use our free wellness consultation and a real advisor will suggest suitable options for your goals. Always consult a qualified health professional before starting a new product.' ],
			[ 'Is the product information medical advice?', 'No. Our product information is for general wellness purposes only and is not a substitute for professional medical advice, diagnosis or treatment. Please read the label and speak to a qualified healthcare professional before use, especially if you are pregnant, nursing, taking medication or managing a condition.' ],
			[ 'Is the free health consultation really free?', 'Yes. The consultation is free and carries no obligation to buy. Anything you share about your health is treated as private, used only to guide product suggestions, and is never sold or shared for marketing.' ],
			[ 'How do I contact customer care?', 'Message or call us on WhatsApp at 0723 579 873, email info@greenworldhealth.co.ke, or visit our Nairobi office during business hours, Monday to Saturday, 8:30 AM to 6:00 PM. We are glad to help.' ],
		];
	}

	public static function pillar( string $key ): ?array {
		$all = self::pillars();
		if ( ! isset( $all[ $key ] ) ) {
			return null;
		}
		$p        = $all[ $key ];
		$p['key'] = $key;
		return $p;
	}

	public static function pillar_by_cat( string $slug ): ?array {
		foreach ( self::pillars() as $key => $p ) {
			if ( $p['cat'] === $slug ) {
				$p['key'] = $key;
				return $p;
			}
		}
		return null;
	}

	public static function pillar_by_landing( string $slug ): ?array {
		foreach ( self::pillars() as $key => $p ) {
			if ( ! empty( $p['landing'] ) && $p['landing'] === $slug ) {
				$p['key'] = $key;
				return $p;
			}
		}
		return null;
	}

	/** @return array<string,array<string,mixed>> pillars that have a landing page */
	public static function landing_pillars(): array {
		$out = [];
		foreach ( self::pillars() as $key => $p ) {
			if ( ! empty( $p['landing'] ) ) {
				$p['key']    = $key;
				$out[ $key ] = $p;
			}
		}
		return $out;
	}
}
