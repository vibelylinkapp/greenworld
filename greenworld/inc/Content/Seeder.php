<?php
declare( strict_types=1 );

namespace GreenWorld\Content;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Seeder — idempotently builds the topical authority layer:
 *
 * - Eight pillar product categories (product_cat) with descriptions.
 * - Six content-hub topics (gw_guide_topic).
 * - Starter educational guides (gw_guide), including two How-To guides with real
 *   numbered steps, each cross-linked to its categories and products.
 * - Commercial landing pages (template-landing.php), one per pillar plus the
 *   master "buy online" hub, each with unique content and FAQ blocks.
 *
 * Everything is guarded by slug existence and a build-version option, so running
 * it repeatedly never duplicates content. Bump TopicMap::BUILD_VERSION to reseed.
 */
final class Seeder implements Bootable {

	public function boot(): void {
		add_action( 'admin_init', [ $this, 'maybe_build' ] );
		add_action( 'after_switch_theme', [ $this, 'on_activate' ] );
	}

	public function on_activate(): void {
		delete_option( 'gw_perma_flushed' );
	}

	public function maybe_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( 'gw_perma_flushed' ) !== TopicMap::BUILD_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'gw_perma_flushed', TopicMap::BUILD_VERSION );
		}

		if ( get_option( 'gw_content_build' ) === TopicMap::BUILD_VERSION ) {
			return;
		}

		$this->seed_categories();
		$this->seed_topics();
		$this->seed_guides();
		$this->seed_landings();

		update_option( 'gw_content_build', TopicMap::BUILD_VERSION );
	}

	/* ------------------------------------------------------------------ */

	private function seed_categories(): void {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}
		foreach ( TopicMap::pillars() as $p ) {
			$slug = (string) $p['cat'];
			if ( term_exists( $slug, 'product_cat' ) || get_term_by( 'slug', $slug, 'product_cat' ) ) {
				continue;
			}
			wp_insert_term(
				(string) $p['label'],
				'product_cat',
				[ 'slug' => $slug, 'description' => (string) $p['blurb'] ]
			);
		}
	}

	private function seed_topics(): void {
		if ( ! taxonomy_exists( TopicMap::GUIDE_TAX ) ) {
			return;
		}
		foreach ( TopicMap::hub_topics() as $slug => $label ) {
			if ( get_term_by( 'slug', $slug, TopicMap::GUIDE_TAX ) ) {
				continue;
			}
			wp_insert_term( (string) $label, TopicMap::GUIDE_TAX, [ 'slug' => $slug ] );
		}
	}

	private function seed_guides(): void {
		if ( ! post_type_exists( TopicMap::GUIDE_CPT ) ) {
			return;
		}
		foreach ( $this->guides() as $g ) {
			if ( $this->guide_exists( $g['slug'] ) ) {
				continue;
			}
			$id = wp_insert_post(
				[
					'post_type'    => TopicMap::GUIDE_CPT,
					'post_status'  => 'publish',
					'post_name'    => $g['slug'],
					'post_title'   => $g['title'],
					'post_excerpt' => $g['excerpt'],
					'post_content' => $g['content'],
				],
				true
			);
			if ( is_wp_error( $id ) || 0 === (int) $id ) {
				continue;
			}
			if ( taxonomy_exists( TopicMap::GUIDE_TAX ) && '' !== $g['topic'] ) {
				wp_set_object_terms( (int) $id, [ $g['topic'] ], TopicMap::GUIDE_TAX );
			}
			update_post_meta( (int) $id, '_gw_guide_type', $g['type'] );
			update_post_meta( (int) $id, '_gw_guide_pillar', $g['pillar'] );
		}
	}

	private function seed_landings(): void {
		// One landing page per commercial pillar.
		foreach ( TopicMap::landing_pillars() as $key => $p ) {
			$slug = (string) $p['landing'];
			if ( $this->page_exists( $slug ) ) {
				continue;
			}
			$this->make_landing( $slug, (string) $p['title'], $this->landing_content( $key, $p ) );
		}

		// Master commercial hub.
		$hub = TopicMap::commercial_hub();
		if ( ! $this->page_exists( (string) $hub['slug'] ) ) {
			$this->make_landing( (string) $hub['slug'], (string) $hub['title'], $this->hub_landing_content( $hub ) );
		}
	}

	private function make_landing( string $slug, string $title, string $content ): void {
		$id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
			],
			true
		);
		if ( ! is_wp_error( $id ) && (int) $id > 0 ) {
			update_post_meta( (int) $id, '_wp_page_template', TopicMap::LANDING_TPL );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Content builders                                                    */
	/* ------------------------------------------------------------------ */

	/** @param array<string,mixed> $p */
	private function landing_content( string $key, array $p ): string {
		$parts   = [];
		$parts[] = '<p>' . esc_html( (string) $p['intro'] ) . '</p>';
		$parts[] = '<h2>' . esc_html( 'Shop ' . (string) $p['label'] ) . '</h2>';
		$parts[] = '[gw_featured_products cat="' . esc_attr( (string) $p['cat'] ) . '" n="8"]';
		$parts[] = '[gw_guides pillar="' . esc_attr( $key ) . '" n="3"]';
		$parts[] = '[gw_related_categories pillar="' . esc_attr( $key ) . '"]';
		$parts[] = $this->faq_html( (array) $p['faqs'] );
		return implode( "\n\n", array_filter( $parts ) );
	}

	/** @param array<string,mixed> $hub */
	private function hub_landing_content( array $hub ): string {
		$parts   = [];
		$parts[] = '<p>' . esc_html( (string) $hub['intro'] ) . '</p>';
		$parts[] = '<h2>Shop by category</h2>';
		$parts[] = '[gw_pillars]';
		$parts[] = '<h2>Guides to help you choose</h2>';
		$parts[] = '[gw_guides n="6"]';
		$parts[] = $this->faq_html( TopicMap::general_faqs() );
		return implode( "\n\n", $parts );
	}

	/**
	 * Renders literal <h3>/<p> FAQ markup. Stored in post_content so it is both
	 * visible and picked up by the Schema FAQ parser (visible content = schema).
	 *
	 * @param array<int,array{0:string,1:string}> $faqs
	 */
	private function faq_html( array $faqs ): string {
		if ( count( $faqs ) === 0 ) {
			return '';
		}
		$out = '<h2>Frequently asked questions</h2>';
		foreach ( $faqs as $qa ) {
			$out .= "\n<h3>" . esc_html( (string) $qa[0] ) . '</h3>';
			$out .= "\n<p>" . esc_html( (string) $qa[1] ) . '</p>';
		}
		return $out;
	}

	/**
	 * Starter guides. Two are How-To with real <ol><li> steps so the HowTo schema
	 * always matches visible content.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function guides(): array {
		return [
			[
				'slug'    => 'what-are-natural-wellness-products',
				'title'   => 'What Are Natural Wellness Products?',
				'topic'   => 'natural-health',
				'pillar'  => 'natural-products',
				'type'    => 'article',
				'excerpt' => 'A plain-language explanation of natural wellness products and how to choose them in Kenya.',
				'content' => '<p>Natural wellness products are everyday products derived from plant, mineral or other naturally occurring sources, chosen by people who prefer a natural approach to daily wellbeing. This guide explains what the term means, how natural products relate to herbal products and supplements, and how to shop for them sensibly.</p>'
					. '<h2>What counts as "natural"?</h2><p>There is no single legal definition, so always read the ingredient list and usage information on each product page. At Green World Health Solutions we group these under our Natural Products and Herbal Products categories.</p>'
					. '<h2>Explore the range</h2>[gw_featured_products cat="natural-products" n="4"]'
					. '[gw_related_categories pillar="natural-products"]'
					. '<h2>Frequently asked questions</h2>'
					. '<h3>Are natural products safe?</h3><p>Follow the usage guidance on each product and consult a qualified health professional before use, especially if you are pregnant, nursing, managing a health condition or taking medication.</p>'
					. '<h3>Do natural products replace medical care?</h3><p>No. They are intended to support general wellbeing and are not a substitute for professional medical advice, diagnosis or treatment.</p>',
			],
			[
				'slug'    => 'beginners-guide-to-health-supplements-kenya',
				'title'   => "A Beginner's Guide to Health Supplements in Kenya",
				'topic'   => 'nutrition',
				'pillar'  => 'supplements',
				'type'    => 'article',
				'excerpt' => 'What dietary supplements are, how to read a label, and how to choose supplements responsibly.',
				'content' => '<p>Dietary supplements are products taken to add nutrients or other substances to the diet. They are popular in Kenya as part of a healthy routine, but they work best alongside a balanced diet, not in place of one.</p>'
					. '<h2>How to read a supplement</h2><p>Check the ingredients, the serving size and the directions for use on each product page. If anything is unclear, contact us before ordering.</p>'
					. '<h2>Popular supplements</h2>[gw_featured_products cat="supplements" n="4"]'
					. '[gw_related_categories pillar="supplements"]'
					. '<h2>Frequently asked questions</h2>'
					. '<h3>Do I need supplements?</h3><p>Not necessarily. A varied, balanced diet meets most needs. Consult a qualified health professional about your individual situation.</p>'
					. '<h3>When should I avoid supplements?</h3><p>Speak to a professional first if you are pregnant, nursing, on medication or managing a health condition.</p>',
			],
			[
				'slug'    => 'understanding-nutrition-and-a-balanced-diet',
				'title'   => 'Understanding Nutrition and a Balanced Diet',
				'topic'   => 'nutrition',
				'pillar'  => 'nutrition',
				'type'    => 'article',
				'excerpt' => 'The basics of balanced nutrition and how nutrition products fit into everyday meals.',
				'content' => '<p>Good nutrition comes first from balanced, everyday meals. Nutrition products are there to complement that foundation, never to replace it. This guide covers the basics and points you to products that can support a balanced routine.</p>'
					. '<h2>Building a balanced plate</h2><p>Aim for variety across the food groups, stay hydrated and keep portions sensible. For personalised dietary advice, consult a qualified professional.</p>'
					. '<h2>Nutrition products</h2>[gw_featured_products cat="nutrition" n="4"]'
					. '[gw_related_categories pillar="nutrition"]',
			],
			[
				'slug'    => 'how-to-place-an-order',
				'title'   => 'How to Place an Order on Green World Health Solutions',
				'topic'   => 'product-guides',
				'pillar'  => '',
				'type'    => 'howto',
				'excerpt' => 'A step-by-step guide to ordering online and paying by M-Pesa, Cash on Delivery or Bank Transfer.',
				'content' => '<p>Ordering from Green World Health Solutions takes just a few minutes. Follow these steps.</p>'
					. '<ol>'
					. '<li>Browse to the product you want and choose any options such as size or quantity.</li>'
					. '<li>Click Add to Cart, then open your cart to review the items.</li>'
					. '<li>Click Checkout and enter your delivery details.</li>'
					. '<li>Choose your payment method: M-Pesa, Cash on Delivery or Bank Transfer.</li>'
					. '<li>Confirm your order. We will contact you to arrange delivery across Kenya.</li>'
					. '</ol>'
					. '<p>Prefer to order by phone? Call or WhatsApp us on 0723 579 873.</p>'
					. '<h2>Frequently asked questions</h2>'
					. '<h3>What payment methods can I use?</h3><p>M-Pesa, Cash on Delivery and Bank Transfer.</p>'
					. '<h3>Do you deliver nationwide?</h3><p>Yes, we deliver across Kenya. Delivery timing depends on your location.</p>',
			],
			[
				'slug'    => 'how-to-use-herbal-products-safely',
				'title'   => 'How to Use Herbal Products Safely',
				'topic'   => 'natural-health',
				'pillar'  => 'herbal-products',
				'type'    => 'howto',
				'excerpt' => 'A practical, non-medical checklist for using herbal products responsibly.',
				'content' => '<p>Herbal products are made from herbs and botanicals. Using them responsibly starts with reading the product information and following the directions. This is general guidance, not medical advice.</p>'
					. '<ol>'
					. '<li>Read the label and ingredient list on the product.</li>'
					. '<li>Check the recommended usage and dosage instructions.</li>'
					. '<li>Confirm suitability: consult a qualified health professional if you are pregnant, nursing, on medication or managing a condition.</li>'
					. '<li>Start with the recommended amount and follow the directions exactly.</li>'
					. '<li>Store the product as directed and keep it out of reach of children.</li>'
					. '</ol>'
					. '<h2>Browse herbal products</h2>[gw_featured_products cat="herbal-products" n="4"]'
					. '[gw_related_categories pillar="herbal-products"]',
			],
			[
				'slug'    => 'healthy-living-everyday-wellness-habits',
				'title'   => 'Healthy Living: Everyday Wellness Habits',
				'topic'   => 'healthy-living',
				'pillar'  => 'healthy-living',
				'type'    => 'article',
				'excerpt' => 'Simple, practical habits for everyday healthy living, and the products that support them.',
				'content' => '<p>Healthy living is built from small, consistent habits: movement, rest, hydration, balanced meals and a routine that works for you. This guide shares practical, non-medical ideas and links to products that can support them.</p>'
					. '<h2>Everyday habits</h2><p>Keep active, drink enough water, prioritise sleep and eat a varied diet. Small changes, kept up over time, add up.</p>'
					. '<h2>Supportive products</h2>[gw_featured_products cat="wellness-products" n="4"]'
					. '[gw_related_categories pillar="healthy-living"]',
			],
		];
	}

	/* ------------------------------------------------------------------ */

	private function page_exists( string $slug ): bool {
		$p = get_page_by_path( $slug );
		return $p instanceof \WP_Post;
	}

	private function guide_exists( string $slug ): bool {
		$p = get_page_by_path( $slug, OBJECT, TopicMap::GUIDE_CPT );
		return $p instanceof \WP_Post;
	}
}
