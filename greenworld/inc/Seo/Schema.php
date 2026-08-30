<?php
declare( strict_types=1 );

namespace GreenWorld\Seo;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * One authoritative JSON-LD knowledge graph for Green World Health Solutions.
 *
 * Emits a single connected @graph keyed by stable @id references:
 *   OnlineStore (#organization) + logo (#logo) + WebSite (#website) sitewide,
 *   plus a context-appropriate WebPage subtype (#webpage), BreadcrumbList
 *   (#breadcrumb), and Product / ItemList / Article / FAQPage entities that
 *   link back to the organization and website.
 *
 * Yields entirely to Yoast / Rank Math when either is active so the final HTML
 * carries exactly one Organization / WebSite / Product / Breadcrumb graph.
 * Override with the `greenworld_force_schema` filter.
 */
final class Schema implements Bootable {

	public function boot(): void {
		add_action( 'wp_head', [ $this, 'output' ], 5 );
	}

	private function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	public function output(): void {
		if ( true === (bool) apply_filters( 'greenworld_disable_schema', false ) ) {
			return;
		}
		if ( $this->seo_plugin_active() && false === (bool) apply_filters( 'greenworld_force_schema', false ) ) {
			return; // A dedicated SEO plugin owns the graph; do not duplicate entities.
		}

		$graph = [ $this->organization(), $this->store(), $this->place(), $this->logo_object(), $this->offer_catalog(), $this->website() ];

		$page = $this->webpage();
		if ( null !== $page ) {
			$graph[] = $page;
		}
		$crumb = $this->breadcrumbs();
		if ( null !== $crumb ) {
			$graph[] = $crumb;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = $this->product_schema();
			if ( null !== $product ) {
				$graph[] = $product;
			}
		} elseif ( ( function_exists( 'is_product_category' ) && is_product_category() ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			$list = $this->collection_list();
			if ( null !== $list ) {
				$graph[] = $list;
			}
		} elseif ( is_singular( \GreenWorld\Content\TopicMap::GUIDE_CPT ) ) {
			$graph[] = $this->guide_schema();
		} elseif ( is_page() && \GreenWorld\Content\TopicMap::LANDING_TPL === get_page_template_slug() ) {
			$col = $this->landing_collection();
			if ( null !== $col ) {
				$graph[] = $col;
			}
		} elseif ( is_singular( 'post' ) ) {
			$graph[] = $this->article();
		}

		$faq = $this->faq_entities();
		if ( count( $faq ) === 0 ) {
			$faq = $this->faq_from_map();
		}
		if ( count( $faq ) > 0 ) {
			// Attach visible FAQ Q&A to the current WebPage as mainEntity.
			foreach ( $graph as $i => $node ) {
				if ( isset( $node['@id'] ) && $node['@id'] === $this->id( '#webpage' ) ) {
					$graph[ $i ]['@type']      = $this->page_is( 'faq' ) ? 'FAQPage' : $node['@type'];
					$graph[ $i ]['mainEntity'] = $faq;
				}
			}
		}

		$data = [
			'@context' => 'https://schema.org',
			'@graph'   => array_values( array_filter( $graph ) ),
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/* --------------------------------------------------------------------- */
	/* Sitewide entities                                                     */
	/* --------------------------------------------------------------------- */

	private function organization(): array {
		$org = [
			'@type'              => 'Organization',
			'@id'                => $this->id( '#organization', true ),
			'name'               => get_bloginfo( 'name' ),
			'alternateName'      => apply_filters( 'greenworld_org_alternate_names', [ 'Green World Health', 'Green World Health Solutions Kenya' ] ),
			'url'                => home_url( '/' ),
			'logo'               => [ '@id' => $this->id( '#logo', true ) ],
			'image'              => [ '@id' => $this->id( '#logo', true ) ],
			'description'        => $this->org_description(),
			'telephone'          => get_option( 'greenworld_phone', '+254723579873' ),
			'email'              => get_option( 'greenworld_email', 'info@greenworldhealth.co.ke' ),
			'address'            => $this->postal_address(),
			'areaServed'         => [ '@type' => 'Country', 'name' => 'Kenya' ],
			'currenciesAccepted' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'KES',
			'paymentAccepted'    => 'M-Pesa, Cash on Delivery, Bank Transfer',
			'priceRange'         => apply_filters( 'greenworld_price_range', 'KES' ),
			'contactPoint'       => [
				'@type'             => 'ContactPoint',
				'telephone'         => get_option( 'greenworld_phone', '+254723579873' ),
				'email'             => get_option( 'greenworld_email', 'info@greenworldhealth.co.ke' ),
				'contactType'       => 'customer service',
				'areaServed'        => 'KE',
				'availableLanguage' => [ 'en', 'sw' ],
			],
			'knowsAbout'         => apply_filters( 'greenworld_knows_about', [
				'Health and wellness products',
				'Natural health products',
				'Nutritional supplements',
				'Herbal products',
				'Healthy living',
			] ),
		];
		$hours = $this->opening_hours();
		if ( count( $hours ) > 0 ) {
			$org['openingHoursSpecification'] = $hours;
		}
		$same = $this->social_links();
		if ( count( $same ) > 0 ) {
			$org['sameAs'] = $same;
		}
		return $org;
	}

	/** The commercial component: the online store, a sub-organisation of the brand. */
	private function store(): array {
		$store = [
			'@type'              => 'OnlineStore',
			'@id'                => $this->id( '#store', true ),
			'name'               => get_bloginfo( 'name' ),
			'url'                => home_url( '/' ),
			'image'              => [ '@id' => $this->id( '#logo', true ) ],
			'logo'               => [ '@id' => $this->id( '#logo', true ) ],
			'parentOrganization' => [ '@id' => $this->id( '#organization', true ) ],
			'address'            => $this->postal_address(),
			'areaServed'         => [ '@type' => 'Country', 'name' => 'Kenya' ],
			'currenciesAccepted' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'KES',
			'paymentAccepted'    => 'M-Pesa, Cash on Delivery, Bank Transfer',
			'priceRange'         => apply_filters( 'greenworld_price_range', 'KES' ),
			'hasOfferCatalog'    => [ '@id' => $this->id( '#offercatalog', true ) ],
		];
		$hours = $this->opening_hours();
		if ( count( $hours ) > 0 ) {
			$store['openingHoursSpecification'] = $hours;
		}
		return $store;
	}

	/** The physical location as a full LocalBusiness (Nairobi office; pickup available), linked to the brand. */
	private function place(): array {
		$place = [
			'@type'              => 'LocalBusiness',
			'@id'                => $this->id( '#place', true ),
			'name'               => get_bloginfo( 'name' ),
			'description'        => $this->org_description(),
			'url'                => home_url( '/' ),
			'image'              => [ '@id' => $this->id( '#logo', true ) ],
			'logo'               => [ '@id' => $this->id( '#logo', true ) ],
			'telephone'          => get_option( 'greenworld_phone', '+254723579873' ),
			'email'              => get_option( 'greenworld_email', 'info@greenworldhealth.co.ke' ),
			'address'            => $this->postal_address(),
			'areaServed'         => [ '@type' => 'Country', 'name' => 'Kenya' ],
			'currenciesAccepted' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'KES',
			'paymentAccepted'    => 'M-Pesa, Cash on Delivery, Bank Transfer',
			'priceRange'         => apply_filters( 'greenworld_price_range', 'KES' ),
			'parentOrganization' => [ '@id' => $this->id( '#organization', true ) ],
		];
		$hours = $this->opening_hours();
		if ( count( $hours ) > 0 ) {
			$place['openingHoursSpecification'] = $hours;
		}
		$same = $this->social_links();
		if ( count( $same ) > 0 ) {
			$place['sameAs'] = $same;
		}
		$geo = $this->geo();
		if ( count( $geo ) > 0 ) {
			$place['geo'] = $geo;
		}
		$map = trim( (string) get_theme_mod( 'gw_gbp_url', '' ) );
		if ( strlen( $map ) > 0 ) {
			$place['hasMap'] = esc_url_raw( $map );
		}
		return $place;
	}

	/**
	 * OfferCatalog of the eight pillars: a first-class topical-authority signal
	 * describing the full breadth of what the store offers. Each pillar links to
	 * its canonical landing page or category.
	 */
	private function offer_catalog(): array {
		$items = [];
		if ( class_exists( '\GreenWorld\Content\TopicMap' ) ) {
			foreach ( \GreenWorld\Content\TopicMap::pillars() as $key => $p ) {
				$entry = [ '@type' => 'OfferCatalog', 'name' => (string) $p['label'] ];
				if ( class_exists( '\GreenWorld\Content\Relations' ) ) {
					$url = \GreenWorld\Content\Relations::pillar_url( \GreenWorld\Content\TopicMap::pillar( (string) $key ) ?? $p );
					if ( '' !== $url ) {
						$entry['url'] = $url;
					}
				}
				$items[] = $entry;
			}
		}
		return [
			'@type'           => 'OfferCatalog',
			'@id'             => $this->id( '#offercatalog', true ),
			'name'            => 'Health & Wellness Product Categories',
			'itemListElement' => $items,
		];
	}

	private function logo_object(): array {
		$url = $this->logo_url();
		return array_filter( [
			'@type'      => 'ImageObject',
			'@id'        => $this->id( '#logo', true ),
			'url'        => $url,
			'contentUrl' => $url,
			'caption'    => get_bloginfo( 'name' ),
		] );
	}

	private function website(): array {
		return [
			'@type'           => 'WebSite',
			'@id'             => $this->id( '#website', true ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => (string) get_bloginfo( 'description' ),
			'publisher'       => [ '@id' => $this->id( '#organization', true ) ],
			'inLanguage'      => $this->lang(),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				],
				'query-input' => 'required name=search_term_string',
			],
		];
	}

	/* --------------------------------------------------------------------- */
	/* Per-page WebPage node                                                 */
	/* --------------------------------------------------------------------- */

	private function webpage(): ?array {
		$url  = $this->current_url();
		$type = $this->webpage_type();
		$node = [
			'@type'      => $type,
			'@id'        => $this->id( '#webpage' ),
			'url'        => $url,
			'name'       => $this->page_name(),
			'isPartOf'   => [ '@id' => $this->id( '#website', true ) ],
			'inLanguage' => $this->lang(),
		];
		if ( is_front_page() ) {
			$node['about'] = [ '@id' => $this->id( '#organization', true ) ];
		}
		$img = $this->page_image();
		if ( '' !== $img ) {
			$node['primaryImageOfPage'] = [ '@type' => 'ImageObject', 'url' => $img ];
		}
		if ( is_singular() ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Post ) {
				$node['datePublished'] = get_the_date( 'c', $obj );
				$node['dateModified']  = get_the_modified_date( 'c', $obj );
			}
		}
		return $node;
	}

	private function webpage_type(): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'ItemPage';
		}
		if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_category' ) && is_product_category() ) || is_post_type_archive() || is_archive() ) {
			return 'CollectionPage';
		}
		if ( $this->page_is( 'about-us' ) || $this->page_is( 'about' ) ) {
			return 'AboutPage';
		}
		if ( $this->page_is( 'contact-us' ) || $this->page_is( 'contact' ) ) {
			return 'ContactPage';
		}
		if ( $this->page_is( 'faq' ) ) {
			return 'FAQPage';
		}
		if ( is_search() ) {
			return 'SearchResultsPage';
		}
		return 'WebPage';
	}

	/* --------------------------------------------------------------------- */
	/* Product                                                               */
	/* --------------------------------------------------------------------- */

	private function product_schema(): ?array {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}
		$pid   = $product->get_id();
		$brand = $this->product_brand( $product );
		$gtin  = (string) get_post_meta( $pid, '_greenworld_gtin', true );
		$mpn   = (string) get_post_meta( $pid, '_greenworld_mpn', true );
		if ( strlen( $mpn ) === 0 ) {
			$mpn = (string) $product->get_sku();
		}

		$schema = [
			'@type'           => $product->is_type( 'variable' ) ? 'ProductGroup' : 'Product',
			'@id'             => get_permalink( $pid ) . '#product',
			'name'            => $product->get_name(),
			'description'     => $this->clean( $product->get_short_description() ?: $product->get_description() ),
			'sku'             => $product->get_sku(),
			'image'           => $this->product_images( $product ),
			'url'             => get_permalink( $pid ),
			'mainEntityOfPage'=> [ '@id' => $this->id( '#webpage' ) ],
		];
		if ( strlen( $brand ) > 0 ) {
			$schema['brand'] = [ '@type' => 'Brand', 'name' => $brand ];
		}
		$cat = $this->primary_category_name( $pid );
		if ( '' !== $cat ) {
			$schema['category'] = $cat;
		}
		if ( strlen( $gtin ) > 0 ) {
			$schema['gtin'] = $gtin;
		}
		if ( strlen( $mpn ) > 0 ) {
			$schema['mpn'] = $mpn;
		}
		$props = $this->additional_properties( $product );
		if ( count( $props ) > 0 ) {
			$schema['additionalProperty'] = $props;
		}
		$related = $this->related_refs( $product );
		if ( count( $related ) > 0 ) {
			$schema['isRelatedTo'] = $related;
		}
		$guide_ref = $this->product_guide_ref( $pid );
		if ( null !== $guide_ref ) {
			$schema['subjectOf'] = $guide_ref;
		}
		if ( $product->is_type( 'variable' ) ) {
			$schema['hasVariant'] = $this->variant_nodes( $product );
			$schema['offers']     = $this->build_offers( $product );
		} else {
			$schema['offers'] = $this->build_offers( $product );
		}
		if ( $product->get_review_count() > 0 && 'yes' === get_option( 'woocommerce_enable_review_rating' ) ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
				'bestRating'  => '5',
				'worstRating' => '1',
			];
			$reviews = $this->real_reviews( $pid );
			if ( count( $reviews ) > 0 ) {
				$schema['review'] = $reviews;
			}
		}
		return $schema;
	}

	private function variant_nodes( \WC_Product $product ): array {
		$out = [];
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v instanceof \WC_Product ) {
				continue;
			}
			$out[] = array_filter( [
				'@type'  => 'Product',
				'@id'    => get_permalink( $product->get_id() ) . '#variant-' . $vid,
				'name'   => $v->get_name(),
				'sku'    => $v->get_sku(),
				'image'  => wp_get_attachment_image_url( (int) $v->get_image_id(), 'full' ) ?: null,
				'offers' => [
					'@type'         => 'Offer',
					'priceCurrency' => get_woocommerce_currency(),
					'price'         => $v->get_price(),
					'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
					'availability'  => $v->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
					'url'           => get_permalink( $product->get_id() ),
					'itemCondition' => 'https://schema.org/NewCondition',
					'seller'        => [ '@id' => $this->id( '#organization', true ) ],
					'hasMerchantReturnPolicy' => $this->return_policy(),
				],
			] );
		}
		return $out;
	}

	private function build_offers( \WC_Product $product ): array {
		$currency = get_woocommerce_currency();
		$avail    = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
		$url      = get_permalink( $product->get_id() );
		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			$list   = ( isset( $prices['price'] ) && is_array( $prices['price'] ) ) ? array_values( $prices['price'] ) : [];
			if ( count( $list ) > 0 ) {
				return [
					'@type'         => 'AggregateOffer',
					'priceCurrency' => $currency,
					'lowPrice'      => (string) min( $list ),
					'highPrice'     => (string) max( $list ),
					'offerCount'    => count( $list ),
					'availability'  => $avail,
					'url'           => $url,
					'seller'        => [ '@id' => $this->id( '#organization', true ) ],
					'itemCondition' => 'https://schema.org/NewCondition',
					'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
					'hasMerchantReturnPolicy' => $this->return_policy(),
				];
			}
		}
		$offer = [
			'@type'                   => 'Offer',
			'priceCurrency'           => $currency,
			'price'                   => $product->get_price(),
			'priceValidUntil'         => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'itemCondition'           => 'https://schema.org/NewCondition',
			'availability'            => $avail,
			'url'                     => $url,
			'seller'                  => [ '@id' => $this->id( '#organization', true ) ],
			'hasMerchantReturnPolicy' => $this->return_policy(),
		];
		$sd = $this->shipping_details();
		if ( count( $sd ) > 0 ) {
			$offer['shippingDetails'] = $sd;
		}
		return $offer;
	}

	private function product_brand( \WC_Product $product ): string {
		$brand = (string) get_post_meta( $product->get_id(), '_greenworld_brand', true );
		if ( strlen( $brand ) === 0 ) {
			$attr  = (string) $product->get_attribute( 'brand' );
			$brand = strlen( $attr ) > 0 ? $attr : (string) $product->get_attribute( 'pa_brand' );
		}
		return $brand;
	}

	private function additional_properties( \WC_Product $product ): array {
		$out = [];
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! $attr instanceof \WC_Product_Attribute || ! $attr->get_visible() || $attr->get_variation() ) {
				continue;
			}
			$name   = wc_attribute_label( $attr->get_name() );
			$values = $product->get_attribute( $attr->get_name() );
			if ( '' === $name || '' === $values ) {
				continue;
			}
			$out[] = [ '@type' => 'PropertyValue', 'name' => $name, 'value' => $values ];
		}
		return $out;
	}

	private function related_refs( \WC_Product $product ): array {
		if ( ! function_exists( 'wc_get_related_products' ) ) {
			return [];
		}
		$ids = wc_get_related_products( $product->get_id(), 4 );
		$out = [];
		foreach ( $ids as $rid ) {
			$out[] = [ '@type' => 'Product', '@id' => get_permalink( (int) $rid ) . '#product', 'url' => get_permalink( (int) $rid ), 'name' => get_the_title( (int) $rid ) ];
		}
		return $out;
	}

	private function real_reviews( int $pid ): array {
		$comments = get_comments( [ 'post_id' => $pid, 'status' => 'approve', 'type' => 'review', 'number' => 3 ] );
		$out      = [];
		foreach ( (array) $comments as $c ) {
			$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			if ( $rating <= 0 ) {
				continue;
			}
			$out[] = [
				'@type'         => 'Review',
				'reviewRating'  => [ '@type' => 'Rating', 'ratingValue' => (string) $rating, 'bestRating' => '5' ],
				'author'        => [ '@type' => 'Person', 'name' => $c->comment_author ],
				'datePublished' => gmdate( 'Y-m-d', strtotime( $c->comment_date ) ),
				'reviewBody'    => $this->clean( $c->comment_content ),
			];
		}
		return $out;
	}

	/* --------------------------------------------------------------------- */
	/* Collection ItemList + Article + FAQ                                   */
	/* --------------------------------------------------------------------- */

	private function collection_list(): ?array {
		global $wp_query;
		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return null;
		}
		$items = [];
		$pos   = 1;
		foreach ( $wp_query->posts as $p ) {
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			$items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'url' => get_permalink( $p->ID ), 'name' => get_the_title( $p->ID ) ];
			if ( $pos > 30 ) {
				break;
			}
		}
		if ( count( $items ) === 0 ) {
			return null;
		}
		return [
			'@type'           => 'ItemList',
			'@id'             => $this->id( '#itemlist' ),
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		];
	}

	private function article(): array {
		$obj = get_queried_object();
		$img = $this->page_image();
		return array_filter( [
			'@type'            => 'Article',
			'@id'              => $this->id( '#article' ),
			'headline'         => get_the_title(),
			'description'      => $this->clean( get_the_excerpt() ),
			'image'            => '' !== $img ? $img : null,
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => [ '@type' => 'Organization', '@id' => $this->id( '#organization', true ) ],
			'publisher'        => [ '@id' => $this->id( '#organization', true ) ],
			'mainEntityOfPage' => [ '@id' => $this->id( '#webpage' ) ],
			'inLanguage'       => $this->lang(),
		] );
	}

	/**
	 * Schema for a Health & Wellness Guide (gw_guide): Article by default, or
	 * HowTo when the guide is flagged how-to and contains a numbered step list.
	 * Interlinks to its pillar category (about) and related products (mentions).
	 */
	private function guide_schema(): array {
		$gid  = (int) get_the_ID();
		$img  = $this->page_image();
		$type = (string) get_post_meta( $gid, '_gw_guide_type', true );
		$node = array_filter( [
			'@type'            => 'Article',
			'@id'              => $this->id( '#article' ),
			'headline'         => get_the_title(),
			'description'      => $this->clean( get_the_excerpt() ),
			'image'            => '' !== $img ? $img : null,
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => [ '@id' => $this->id( '#organization', true ) ],
			'publisher'        => [ '@id' => $this->id( '#organization', true ) ],
			'mainEntityOfPage' => [ '@id' => $this->id( '#webpage' ) ],
			'isPartOf'         => [ '@id' => $this->id( '#website', true ) ],
			'inLanguage'       => $this->lang(),
		] );

		if ( class_exists( '\GreenWorld\Content\TopicMap' ) && class_exists( '\GreenWorld\Content\Relations' ) ) {
			$pillar_key = (string) get_post_meta( $gid, '_gw_guide_pillar', true );
			$pillar     = '' !== $pillar_key ? \GreenWorld\Content\TopicMap::pillar( $pillar_key ) : null;
			if ( null !== $pillar ) {
				$curl = \GreenWorld\Content\Relations::category_url( (string) $pillar['cat'] );
				if ( '' !== $curl ) {
					$node['about'] = [ [ '@type' => 'Thing', 'name' => (string) $pillar['label'], 'url' => $curl ] ];
				}
				$mentions = [];
				foreach ( \GreenWorld\Content\Relations::products_in_cat( (string) $pillar['cat'], 4 ) as $post ) {
					$mentions[] = [ '@type' => 'Product', 'name' => get_the_title( $post->ID ), 'url' => (string) get_permalink( $post->ID ) ];
				}
				if ( count( $mentions ) > 0 ) {
					$node['mentions'] = $mentions;
				}
			}
		}

		if ( 'howto' === $type ) {
			$steps = $this->howto_steps();
			if ( count( $steps ) >= 2 ) {
				$node['@type'] = 'HowTo';
				$node['name']  = get_the_title();
				$node['step']  = $steps;
			}
		}
		return $node;
	}

	/**
	 * Parse <ol><li>..</li></ol> items from the current post into HowToStep nodes
	 * so HowTo markup always mirrors the visible numbered steps.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function howto_steps(): array {
		$obj = get_queried_object();
		if ( ! $obj instanceof \WP_Post ) {
			return [];
		}
		if ( ! preg_match( '/<ol[^>]*>(.*?)<\/ol>/is', (string) $obj->post_content, $mm ) ) {
			return [];
		}
		if ( ! preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $mm[1], $items, PREG_SET_ORDER ) ) {
			return [];
		}
		$steps = [];
		$pos   = 1;
		foreach ( $items as $it ) {
			$text = trim( wp_strip_all_tags( $it[1] ) );
			if ( strlen( $text ) < 3 ) {
				continue;
			}
			$steps[] = [ '@type' => 'HowToStep', 'position' => $pos++, 'name' => wp_trim_words( $text, 8 ), 'text' => $text ];
		}
		return $steps;
	}

	/**
	 * CollectionPage + ItemList for a commercial landing page, interlinked to its
	 * pillar's category (significantLink), related categories and guides.
	 */
	private function landing_collection(): ?array {
		if ( ! class_exists( '\GreenWorld\Content\TopicMap' ) || ! class_exists( '\GreenWorld\Content\Relations' ) ) {
			return null;
		}
		$obj = get_queried_object();
		if ( ! $obj instanceof \WP_Post ) {
			return null;
		}
		$node = [
			'@type'      => 'CollectionPage',
			'@id'        => $this->id( '#collection' ),
			'url'        => $this->current_url(),
			'name'       => get_the_title(),
			'isPartOf'   => [ '@id' => $this->id( '#website', true ) ],
			'about'      => [ '@id' => $this->id( '#organization', true ) ],
			'inLanguage' => $this->lang(),
		];
		$pillar = \GreenWorld\Content\TopicMap::pillar_by_landing( $obj->post_name );
		if ( null === $pillar ) {
			return $node;
		}
		$items = [];
		$pos   = 1;
		foreach ( \GreenWorld\Content\Relations::products_in_cat( (string) $pillar['cat'], 12 ) as $post ) {
			$items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'url' => (string) get_permalink( $post->ID ), 'name' => get_the_title( $post->ID ) ];
		}
		if ( count( $items ) > 0 ) {
			$node['mainEntity'] = [ '@type' => 'ItemList', 'numberOfItems' => count( $items ), 'itemListElement' => $items ];
		}
		$about_cat = \GreenWorld\Content\Relations::category_url( (string) $pillar['cat'] );
		if ( '' !== $about_cat ) {
			$node['significantLink'] = $about_cat;
		}
		$links = [];
		foreach ( \GreenWorld\Content\Relations::related_categories( (string) $pillar['key'], 3 ) as $r ) {
			if ( '' !== $r['url'] ) {
				$links[] = $r['url'];
			}
		}
		foreach ( \GreenWorld\Content\Relations::guides_for_pillar( (string) $pillar['key'], 3 ) as $g ) {
			$links[] = (string) get_permalink( $g );
		}
		if ( count( $links ) > 0 ) {
			$node['relatedLink'] = array_values( array_unique( $links ) );
		}
		return $node;
	}

	/**
	 * FAQ fallback: when a landing/guide page has no visible <h3> FAQ but is a
	 * known pillar landing or the commercial hub, emit the pillar/general FAQ bank
	 * so structured data and the on-page shortcode FAQ stay aligned.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function faq_from_map(): array {
		if ( ! is_page() || ! class_exists( '\GreenWorld\Content\TopicMap' ) ) {
			return [];
		}
		$obj = get_queried_object();
		if ( ! $obj instanceof \WP_Post ) {
			return [];
		}
		$bank   = [];
		$pillar = \GreenWorld\Content\TopicMap::pillar_by_landing( $obj->post_name );
		if ( null !== $pillar && ! empty( $pillar['faqs'] ) ) {
			$bank = (array) $pillar['faqs'];
		} else {
			$hub = \GreenWorld\Content\TopicMap::commercial_hub();
			if ( $obj->post_name === $hub['slug'] ) {
				$bank = \GreenWorld\Content\TopicMap::general_faqs();
			}
		}
		if ( count( $bank ) === 0 && in_array( $obj->post_name, array( 'faq', 'faqs', 'frequently-asked-questions' ), true ) ) {
			$bank = \GreenWorld\Content\TopicMap::general_faqs();
		}
		$out = [];
		foreach ( $bank as $qa ) {
			$out[] = [ '@type' => 'Question', 'name' => (string) $qa[0], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => (string) $qa[1] ] ];
		}
		return count( $out ) >= 2 ? $out : [];
	}

	/**
	 * subjectOf reference from a product to its primary educational guide — the
	 * product side of the product <-> content relationship.
	 */
	private function product_guide_ref( int $pid ): ?array {
		if ( ! class_exists( '\GreenWorld\Content\Relations' ) ) {
			return null;
		}
		$guide = \GreenWorld\Content\Relations::guide_for_product( $pid );
		if ( ! $guide instanceof \WP_Post ) {
			return null;
		}
		return [
			'@type' => 'Article',
			'@id'   => get_permalink( $guide ) . '#article',
			'name'  => get_the_title( $guide ),
			'url'   => (string) get_permalink( $guide ),
		];
	}

	/**
	 * Parse visible <h3>Question</h3><p>Answer</p> pairs from the current
	 * page content. Returns Question nodes only when at least two are found so
	 * FAQ markup never ships without matching visible content.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function faq_entities(): array {
		if ( ! is_singular() ) {
			return [];
		}
		$obj = get_queried_object();
		if ( ! $obj instanceof \WP_Post ) {
			return [];
		}
		$html = (string) $obj->post_content;
		if ( false === stripos( $html, '<h3' ) ) {
			return [];
		}
		if ( ! preg_match_all( '/<h3[^>]*>(.*?)<\/h3>\s*(.*?)(?=<h3|\z)/is', $html, $m, PREG_SET_ORDER ) ) {
			return [];
		}
		$out = [];
		foreach ( $m as $pair ) {
			$q = trim( wp_strip_all_tags( $pair[1] ) );
			$a = trim( wp_strip_all_tags( $pair[2] ) );
			if ( strlen( $q ) < 3 || strlen( $a ) < 3 ) {
				continue;
			}
			$out[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ],
			];
		}
		return count( $out ) >= 2 ? $out : [];
	}

	/* --------------------------------------------------------------------- */
	/* Breadcrumbs                                                           */
	/* --------------------------------------------------------------------- */

	private function crumb( int $pos, string $name, string $url ): array {
		return [ '@type' => 'ListItem', 'position' => $pos, 'name' => $name, 'item' => $url ];
	}

	private function term_chain( \WP_Term $term, int &$pos, array &$items ): void {
		foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat' ) ) as $aid ) {
			$anc = get_term( (int) $aid, 'product_cat' );
			if ( $anc instanceof \WP_Term ) {
				$link = get_term_link( $anc );
				if ( ! is_wp_error( $link ) ) {
					$items[] = $this->crumb( $pos++, $anc->name, (string) $link );
				}
			}
		}
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$items[] = $this->crumb( $pos++, $term->name, (string) $link );
		}
	}

	private function breadcrumbs(): ?array {
		if ( is_front_page() ) {
			return null;
		}
		$pos   = 1;
		$items = [ $this->crumb( $pos++, __( 'Home', 'greenworld' ), home_url( '/' ) ) ];

		if ( function_exists( 'is_product' ) && is_product() ) {
			$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			$items[] = $this->crumb( $pos++, __( 'Shop', 'greenworld' ), (string) $shop );
			$terms   = get_the_terms( get_the_ID(), 'product_cat' );
			if ( is_array( $terms ) && count( $terms ) > 0 ) {
				$this->term_chain( $terms[0], $pos, $items );
			}
			$items[] = $this->crumb( $pos++, get_the_title(), (string) get_permalink() );
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			$items[] = $this->crumb( $pos++, __( 'Shop', 'greenworld' ), (string) $shop );
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$this->term_chain( $term, $pos, $items );
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$items[] = $this->crumb( $pos++, __( 'Shop', 'greenworld' ), (string) wc_get_page_permalink( 'shop' ) );
		} elseif ( is_singular() ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Post && $obj->post_parent > 0 ) {
				foreach ( array_reverse( get_post_ancestors( $obj ) ) as $anc ) {
					$items[] = $this->crumb( $pos++, get_the_title( $anc ), (string) get_permalink( $anc ) );
				}
			}
			$items[] = $this->crumb( $pos++, get_the_title(), $this->current_url() );
		} else {
			return null;
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => $this->id( '#breadcrumb' ),
			'itemListElement' => $items,
		];
	}

	/* --------------------------------------------------------------------- */
	/* Shared helpers                                                        */
	/* --------------------------------------------------------------------- */

	private function id( string $fragment, bool $home = false ): string {
		$base = $home ? home_url( '/' ) : $this->current_url();
		return $base . ltrim( $fragment, '/' );
	}

	private function current_url(): string {
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$t = get_queried_object();
			if ( $t instanceof \WP_Term ) {
				$l = get_term_link( $t );
				if ( ! is_wp_error( $l ) ) {
					return (string) $l;
				}
			}
		}
		if ( is_front_page() || is_home() ) {
			return home_url( '/' );
		}
		return home_url( add_query_arg( [], $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	}

	private function page_name(): string {
		$t = wp_get_document_title();
		return is_string( $t ) ? $t : (string) get_bloginfo( 'name' );
	}

	private function page_image(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			return (string) get_the_post_thumbnail_url( null, 'large' );
		}
		return $this->logo_url();
	}

	private function page_is( string $slug ): bool {
		return is_page( $slug ) || ( is_singular() && get_post_field( 'post_name', get_queried_object_id() ) === $slug );
	}

	private function primary_category_name( int $pid ): string {
		$terms = get_the_terms( $pid, 'product_cat' );
		if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof \WP_Term ) {
			return $terms[0]->name;
		}
		return '';
	}

	private function lang(): string {
		$l = str_replace( '_', '-', (string) get_locale() );
		return '' !== $l ? $l : 'en-KE';
	}

	private function clean( string $s ): string {
		$s = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $s ) ) );
		return $s;
	}

	private function org_description(): string {
		$d = trim( (string) get_bloginfo( 'description' ) );
		if ( '' !== $d ) {
			return $d;
		}
		return 'Green World Health Solutions is a Kenyan health and wellness company offering carefully selected natural health and wellness products with delivery across Kenya.';
	}

	private function postal_address(): array {
		return [
			'@type'           => 'PostalAddress',
			'streetAddress'   => get_option( 'greenworld_street', 'Development House, 11th Floor, Room 7' ),
			'addressLocality' => get_option( 'greenworld_city', 'Nairobi' ),
			'addressCountry'  => 'KE',
		];
	}

	private function geo(): array {
		$lat = trim( (string) get_option( 'greenworld_geo_lat', (string) get_theme_mod( 'gw_geo_lat', '' ) ) );
		$lng = trim( (string) get_option( 'greenworld_geo_lng', (string) get_theme_mod( 'gw_geo_lng', '' ) ) );
		if ( strlen( $lat ) === 0 || strlen( $lng ) === 0 ) {
			return array();
		}
		return [
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		];
	}

	private function opening_hours(): array {
		$hours = apply_filters( 'greenworld_opening_hours', [
			[ 'days' => [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ], 'opens' => '08:30', 'closes' => '18:00' ],
		] );
		$spec = [];
		foreach ( (array) $hours as $h ) {
			if ( ! isset( $h['days'], $h['opens'], $h['closes'] ) ) {
				continue;
			}
			$spec[] = [
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $h['days'],
				'opens'     => $h['opens'],
				'closes'    => $h['closes'],
			];
		}
		return $spec;
	}

	private function logo_url(): string {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id > 0 ) {
			$u = wp_get_attachment_url( $id );
			if ( is_string( $u ) ) {
				return $u;
			}
		}
		return trailingslashit( get_template_directory_uri() ) . 'assets/img/logo-badge.png';
	}

	/** @return array<int,string> */
	private function social_links(): array {
		$links = apply_filters( 'greenworld_social_profiles', [] );
		return is_array( $links ) ? array_values( array_filter( array_map( 'esc_url_raw', $links ) ) ) : [];
	}

	/** @return array<int,string> */
	private function product_images( \WC_Product $product ): array {
		$ids  = array_merge( [ $product->get_image_id() ], $product->get_gallery_image_ids() );
		$urls = [];
		foreach ( $ids as $iid ) {
			$u = wp_get_attachment_image_url( (int) $iid, 'full' );
			if ( is_string( $u ) && strlen( $u ) > 0 ) {
				$urls[] = $u;
			}
		}
		if ( count( $urls ) === 0 && function_exists( 'wc_placeholder_img_src' ) ) {
			$ph = wc_placeholder_img_src( 'full' );
			if ( is_string( $ph ) ) {
				$urls[] = $ph;
			}
		}
		return $urls;
	}

	/** @return array<string,mixed> */
	private function return_policy(): array {
		return apply_filters( 'greenworld_return_policy', [
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => 'KE',
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => 7,
			'returnMethod'         => 'https://schema.org/ReturnByMail',
			'returnFees'           => 'https://schema.org/FreeReturn',
		] );
	}

	/** @return array<string,mixed> */
	private function shipping_details(): array {
		$rate = trim( (string) get_option( 'greenworld_flat_shipping', '' ) );
		if ( '' === $rate ) {
			return [];
		}
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'KES';
		return apply_filters( 'greenworld_shipping_details', [
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => [ '@type' => 'MonetaryAmount', 'value' => $rate, 'currency' => $currency ],
			'shippingDestination' => [ '@type' => 'DefinedRegion', 'addressCountry' => 'KE' ],
			'deliveryTime'        => [
				'@type'        => 'ShippingDeliveryTime',
				'handlingTime' => [ '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ],
				'transitTime'  => [ '@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 4, 'unitCode' => 'DAY' ],
			],
		] );
	}
}
