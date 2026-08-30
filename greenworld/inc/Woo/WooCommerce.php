<?php
declare( strict_types=1 );

namespace GreenWorld\Woo;

use GreenWorld\Core\Bootable;
use GreenWorld\Customizer\Customizer;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce presentation layer for a premium health store: badges, wishlist,
 * trust signals, delivery estimate, sticky add-to-cart, ingredients / how-to-use
 * tabs and a responsible health disclaimer. No medical claims are invented.
 */
final class WooCommerce implements Bootable {

	public function boot(): void {
		add_action( 'after_setup_theme', [ $this, 'columns' ] );

		// Loop.
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'badges' ], 9 );
		add_action( 'woocommerce_before_shop_loop_item', [ $this, 'wishlist_button' ], 8 );

		// Single product.
		add_action( 'woocommerce_single_product_summary', [ $this, 'brand_eyebrow' ], 4 );
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'whatsapp_button' ], 20 );
		add_action( 'woocommerce_before_single_product', [ $this, 'breadcrumb' ], 5 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'trust_badges' ], 35 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'delivery_estimate' ], 36 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'product_disclaimer' ], 6 );
		add_action( 'wp_footer', [ $this, 'sticky_atc' ] );
		add_action( 'wp_head', [ $this, 'critical_product_css' ], 9999 );

		// Editable product info + tabs.
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'info_fields' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_info' ] );
		add_filter( 'woocommerce_product_tabs', [ $this, 'tabs' ] );

		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragments' ] );

		// Single-product layout rebuild (v1.10.0).
		add_filter( 'body_class', [ $this, 'product_body_class' ] );
		add_action( 'woocommerce_single_product_summary', [ $this, 'availability' ], 15 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'sale_savings' ], 11 );
		// v1.14.0: restore horizontal tabs (default), related products four-up, and a Recently viewed carousel.
		add_filter( 'woocommerce_output_related_products_args', [ $this, 'related_args' ] );
		add_action( 'template_redirect', [ $this, 'track_view' ], 20 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'recently_viewed' ], 25 );

		// Clean shop chrome; the theme provides its own wrappers.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

		// One sale badge only: drop WooCommerce's default flash; badges() is the single indicator.
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	}

	public function columns(): void {
		add_filter( 'loop_shop_columns', static fn() => 4 );
		add_filter( 'loop_shop_per_page', static fn() => 24 );
	}

	/**
	 * @param array<string,string> $fragments
	 * @return array<string,string>
	 */
	public function cart_fragments( array $fragments ): array {
		$count = ( WC()->cart instanceof \WC_Cart ) ? WC()->cart->get_cart_contents_count() : 0;
		$fragments['span.gw-cart__count'] = '<span class="gw-cart__count">' . esc_html( (string) $count ) . '</span>';
		ob_start();
		woocommerce_mini_cart();
		$fragments['div.gw-minicart__body'] = '<div class="gw-minicart__body">' . (string) ob_get_clean() . '</div>';
		return $fragments;
	}

	public function badges(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		echo '<div class="gw-badges">';
		if ( $product->is_on_sale() ) {
			echo '<span class="gw-badge gw-badge--sale">' . esc_html__( 'Sale', 'greenworld' ) . '</span>';
		}
		$date = $product->get_date_created();
		if ( $date && ( time() - $date->getTimestamp() ) < 30 * DAY_IN_SECONDS ) {
			echo '<span class="gw-badge gw-badge--new">' . esc_html__( 'New', 'greenworld' ) . '</span>';
		}
		if ( ! $product->is_in_stock() ) {
			echo '<span class="gw-badge gw-badge--oos">' . esc_html__( 'Out of stock', 'greenworld' ) . '</span>';
		}
		echo '</div>';
	}

	public function wishlist_button(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		printf(
			'<button class="gw-wish" type="button" data-gw-wishlist="%d" aria-label="%s" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21S4 14.5 4 8.8A4.2 4.2 0 0 1 12 6a4.2 4.2 0 0 1 8 2.8C20 14.5 12 21 12 21Z"/></svg></button>',
			(int) $product->get_id(),
			esc_attr__( 'Add to wishlist', 'greenworld' )
		);
	}

	/**
	 * Small brand kicker printed above the product title on the single-product
	 * page, mirroring the "Brand" line in the redesigned layout.
	 */
	public function brand_eyebrow(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		echo '<p class="gw-product-brand">' . esc_html__( 'Green World', 'greenworld' ) . '</p>';
	}

	/**
	 * "Order on WhatsApp" call-to-action inside the single-product summary,
	 * matching the loop button. Number + message template come from the
	 * Customizer, so nothing is hardcoded.
	 */
	public function whatsapp_button(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$wa = (string) preg_replace( '/[^0-9]/', '', Customizer::val( 'gw_whatsapp' ) );
		if ( '' === $wa ) {
			return;
		}
		$tmpl = (string) Customizer::val( 'gw_wa_order_msg' );
		if ( '' === trim( $tmpl ) ) {
			$tmpl = 'Hi Green World Health Solutions, I would like to order: {product} ({url})';
		}
		$msg = str_replace(
			[ '{product}', '{url}' ],
			[ $product->get_name(), (string) get_permalink( $product->get_id() ) ],
			$tmpl
		);
		printf(
			'<a class="gw-wa-order gw-wa-order--single" href="%s" target="_blank" rel="nofollow noopener"><svg class="gw-wa-order__icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.82c2.16 0 4.19.84 5.72 2.37a8.06 8.06 0 0 1 2.37 5.72c0 4.46-3.63 8.09-8.1 8.09a8.1 8.1 0 0 1-4.12-1.13l-.3-.18-3.12.82.83-3.04-.19-.31a8.03 8.03 0 0 1-1.25-4.25c0-4.46 3.63-8.09 8.1-8.09Zm4.68 10.29c-.26-.13-1.51-.75-1.74-.83-.23-.09-.4-.13-.57.13-.17.26-.65.83-.8 1-.15.17-.29.19-.55.06-.26-.13-1.08-.4-2.06-1.27-.76-.68-1.28-1.52-1.43-1.78-.15-.26-.02-.4.11-.53.12-.12.26-.31.39-.46.13-.15.17-.26.26-.44.09-.17.04-.33-.02-.46-.06-.13-.57-1.38-.78-1.89-.21-.5-.42-.43-.57-.44l-.49-.01c-.17 0-.44.06-.68.33-.23.26-.89.87-.89 2.12 0 1.25.91 2.46 1.04 2.63.13.17 1.79 2.74 4.34 3.84.61.26 1.08.42 1.45.54.61.19 1.16.16 1.6.1.49-.07 1.51-.62 1.72-1.21.21-.6.21-1.11.15-1.21-.06-.11-.23-.17-.49-.3Z"/></svg><span>%s</span></a>',
			esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $msg ) ),
			esc_html__( 'Order on WhatsApp', 'greenworld' )
		);
	}

	/**
	 * WooCommerce breadcrumb above the single product (Home / Category / Product),
	 * rendered inside the theme container so the layout matches the benchmark.
	 */
	public function breadcrumb(): void {
		if ( function_exists( 'woocommerce_breadcrumb' ) ) {
			woocommerce_breadcrumb();
		}
	}

	public function trust_badges(): void {
		$feats = array(
			array( 'truck', __( 'Fast delivery across Kenya', 'greenworld' ) ),
			array( 'shield', __( 'Genuine Green World product', 'greenworld' ) ),
			array( 'lock', __( 'Secure checkout: M-Pesa, bank or cash on delivery', 'greenworld' ) ),
			array( 'return', __( 'Easy returns within 7 days', 'greenworld' ) ),
		);
		$icons = array(
			'truck'  => '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="18" cy="17" r="2"/>',
			'shield' => '<path d="M12 3l7 3v5c0 4.4-3 7.8-7 9-4-1.2-7-4.6-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
			'lock'   => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'return' => '<path d="M3 7v5h5"/><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>',
		);
		echo '<ul class="gw-features" aria-label="' . esc_attr__( 'Store guarantees', 'greenworld' ) . '">';
		foreach ( $feats as $f ) {
			$key  = (string) $f[0];
			$path = isset( $icons[ $key ] ) ? $icons[ $key ] : '';
			echo '<li><span class="gw-features__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span><span class="gw-features__t">' . esc_html( (string) $f[1] ) . '</span></li>';
		}
		echo '</ul>';
	}

	public function delivery_estimate(): void {
		$text = (string) get_theme_mod( 'gw_delivery_note', __( 'Reliable delivery across Kenya. Nairobi same/next day; countrywide in 1–4 business days.', 'greenworld' ) );
		if ( '' === trim( $text ) ) {
			return;
		}
		echo '<p class="gw-delivery"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="18" cy="17" r="2"/></svg>' . esc_html( $text ) . '</p>';
	}

	public function product_disclaimer(): void {
		$text = Customizer::val( 'gw_default_disclaimer' );
		if ( '' === $text ) {
			return;
		}
		echo '<div class="gw-product-disclaimer"><p>' . esc_html( $text ) . '</p></div>';
	}

	public function info_fields(): void {
		echo '<div class="options_group">';
		woocommerce_wp_textarea_input( array(
			'id'          => '_gw_ingredients',
			'label'       => __( 'Ingredients / Composition', 'greenworld' ),
			'description' => __( 'Shown as a product tab. Enter only accurate, supplied information.', 'greenworld' ),
			'desc_tip'    => true,
		) );
		woocommerce_wp_textarea_input( array(
			'id'          => '_gw_howtouse',
			'label'       => __( 'How to Use', 'greenworld' ),
			'description' => __( 'Directions for use as provided on the product/label. Shown as a product tab.', 'greenworld' ),
			'desc_tip'    => true,
		) );
		echo '</div>';
	}

	public function save_info( $post_id ): void {
		$pid = (int) $post_id;
		if ( 0 === $pid ) {
			return;
		}
		foreach ( [ '_gw_ingredients', '_gw_howtouse' ] as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $pid, $key, sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) );
			} else {
				delete_post_meta( $pid, $key );
			}
		}
	}

	/**
	 * @param array<string,array<string,mixed>> $tabs
	 * @return array<string,array<string,mixed>>
	 */
	public function tabs( array $tabs ): array {
		global $product;
		if ( $product instanceof \WC_Product ) {
			$ingredients = trim( (string) get_post_meta( $product->get_id(), '_gw_ingredients', true ) );
			$howto       = trim( (string) get_post_meta( $product->get_id(), '_gw_howtouse', true ) );
			if ( $ingredients !== '' ) {
				$tabs['gw_ingredients'] = [
					'title'    => __( 'Ingredients', 'greenworld' ),
					'priority' => 22,
					'callback' => static function () use ( $ingredients ): void {
						echo '<h2>' . esc_html__( 'Ingredients / Composition', 'greenworld' ) . '</h2>';
						echo wp_kses_post( wpautop( $ingredients ) );
					},
				];
			}
			if ( $howto !== '' ) {
				$tabs['gw_howtouse'] = [
					'title'    => __( 'How to Use', 'greenworld' ),
					'priority' => 24,
					'callback' => static function () use ( $howto ): void {
						echo '<h2>' . esc_html__( 'How to Use', 'greenworld' ) . '</h2>';
						echo wp_kses_post( wpautop( $howto ) );
					},
				];
			}
		}
		$tabs['gw_delivery'] = [
			'title'    => __( 'Delivery', 'greenworld' ),
			'priority' => 30,
			'callback' => static function (): void {
				echo '<h2>' . esc_html__( 'Delivery Information', 'greenworld' ) . '</h2>';
				echo '<p>' . esc_html( (string) get_theme_mod( 'gw_delivery_note', __( 'Reliable delivery across Kenya. Nairobi same/next day; countrywide in 1–4 business days. Pay by M-Pesa, bank transfer or cash on delivery.', 'greenworld' ) ) ) . '</p>';
			},
		];
		return $tabs;
	}

	public function sticky_atc(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		printf(
			'<div class="gw-sticky-atc" role="region" aria-label="%1$s"><span class="gw-sticky-atc__name">%2$s</span><span class="gw-sticky-atc__price">%3$s</span><a class="gw-sticky-atc__btn button" href="#" data-add-to-cart="%4$d">%5$s</a></div>',
			esc_attr__( 'Add to cart', 'greenworld' ),
			esc_html( $product->get_name() ),
			wp_kses_post( $product->get_price_html() ),
			(int) $product->get_id(),
			esc_html__( 'Add to Cart', 'greenworld' )
		);
	}

	/* ------------------------------------------------------------------ */
	/*  Single-product layout rebuild (v1.10.0)                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Adds gw-has-productimg / gw-no-productimg to <body> on single-product
	 * pages so the layout can shrink the gallery column when there is no image.
	 *
	 * @param array<int,string> $classes
	 * @return array<int,string>
	 */
	public function product_body_class( array $classes ): array {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
			if ( $product instanceof \WC_Product ) {
				$has = ( $product->get_image_id() || count( (array) $product->get_gallery_image_ids() ) > 0 );
				$classes[] = $has ? 'gw-has-productimg' : 'gw-no-productimg';
			} else {
				$classes[] = 'gw-no-productimg';
			}
		}
		return $classes;
	}

	/**
	 * Clear In stock / Out of stock pill shown in the product summary.
	 */
	public function availability(): void {
		$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		if ( $product instanceof \WC_Product ) {
			$in    = $product->is_in_stock();
			$label = $in ? __( 'In stock', 'greenworld' ) : __( 'Out of stock', 'greenworld' );
			$cls   = $in ? 'is-in' : 'is-out';
			echo '<p class="gw-avail ' . esc_attr( $cls ) . '"><span class="gw-avail__dot" aria-hidden="true"></span>' . esc_html( $label ) . '</p>';
		}
	}

	/**
	 * Renders product information as stacked, collapsible sections instead of
	 * horizontal tabs. Reuses the woocommerce_product_tabs data, so Description,
	 * Ingredients, How to Use, Delivery and Reviews all appear as open-able
	 * panels (native details/summary, no JavaScript required). First panel open.
	 */
	public function stacked_sections(): void {
		$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		if ( $product instanceof \WC_Product ) {
			$tabs = apply_filters( 'woocommerce_product_tabs', array() );
			if ( is_array( $tabs ) && count( $tabs ) > 0 ) {
				uasort(
					$tabs,
					static function ( $a, $b ): int {
						$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
						$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
						return $pa <=> $pb;
					}
				);
				echo '<div class="gw-psections" id="gw-product-sections">';
				$first = true;
				foreach ( $tabs as $key => $tab ) {
					$cb = isset( $tab['callback'] ) ? $tab['callback'] : null;
					if ( is_callable( $cb ) ) {
						ob_start();
						call_user_func( $cb, (string) $key, $tab );
						$body = trim( (string) ob_get_clean() );
						if ( $body === '' ) {
							continue;
						}
						$title = isset( $tab['title'] ) ? (string) $tab['title'] : '';
						echo '<details class="gw-psection"' . ( $first ? ' open' : '' ) . '>';
						echo '<summary class="gw-psection__head"><span class="gw-psection__title">' . esc_html( $title ) . '</span><svg class="gw-psection__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>';
						echo '<div class="gw-psection__body gw-richtext">' . $body . '</div>';
						echo '</details>';
						$first = false;
					}
				}
				echo '</div>';
			}
		}
	}


	/**
	 * "You save X%" pill shown under the price when a product is on sale.
	 */
	public function sale_savings(): void {
		$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		if ( $product instanceof \WC_Product && $product->is_on_sale() ) {
			$reg  = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
			$sale = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_price() ) );
			if ( $reg > 0 && $sale > 0 && $sale < $reg ) {
				$pct  = (int) round( ( ( $reg - $sale ) / $reg ) * 100 );
				$save = wc_price( $reg - $sale );
				echo '<p class="gw-save"><span class="gw-save__pct">-' . esc_html( (string) $pct ) . '%</span><span class="gw-save__amt">' . wp_kses_post( sprintf( __( 'You save %s', 'greenworld' ), $save ) ) . '</span></p>';
			}
		}
	}

	/**
	 * Related products: show four across in one row, matching the benchmark.
	 *
	 * @param array<string,mixed> $args Related product args.
	 * @return array<string,mixed>
	 */
	public function related_args( $args ) {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}

	/**
	 * Track the current product in the woocommerce_recently_viewed cookie so the
	 * Recently viewed carousel has data. Independent of the WooCommerce widget.
	 */
	public function track_view(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$viewed = array();
		if ( isset( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			$viewed = explode( '|', (string) wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) );
		}
		$viewed   = array_filter( array_map( 'absint', (array) $viewed ) );
		$viewed[] = $post->ID;
		$viewed   = array_reverse( array_unique( array_reverse( $viewed ) ) );
		$viewed   = array_slice( $viewed, -15 );
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed ) );
		}
	}

	/**
	 * Recently viewed products rendered as a horizontal carousel with arrows,
	 * matching the benchmark. Reads the woocommerce_recently_viewed cookie.
	 */
	public function recently_viewed(): void {
		if ( ! is_singular( 'product' ) || empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			return;
		}
		$ids     = array_filter( array_map( 'absint', explode( '|', (string) wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) );
		$current = get_queried_object_id();
		$ids     = array_values( array_diff( array_unique( array_reverse( $ids ) ), array( $current ) ) );
		if ( count( $ids ) < 1 ) {
			return;
		}
		$ids = array_slice( $ids, 0, 12 );
		$q   = new \WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		if ( ! $q->have_posts() ) {
			wp_reset_postdata();
			return;
		}
		echo '<section class="gw-recent products" aria-label="' . esc_attr__( 'Recently viewed', 'greenworld' ) . '">';
		echo '<div class="gw-recent__head"><h2>' . esc_html__( 'Recently viewed', 'greenworld' ) . '</h2>';
		echo '<div class="gw-recent__nav"><button type="button" class="gw-recent__arrow" data-gw-recent-prev aria-label="' . esc_attr__( 'Scroll left', 'greenworld' ) . '">&#8249;</button><button type="button" class="gw-recent__arrow" data-gw-recent-next aria-label="' . esc_attr__( 'Scroll right', 'greenworld' ) . '">&#8250;</button></div></div>';
		echo '<ul class="products gw-recent__track" data-gw-recent-track>';
		while ( $q->have_posts() ) {
			$q->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		echo '</ul></section>';
		wp_reset_postdata();
		?>
<script>
(function(){
	var track=document.querySelector('[data-gw-recent-track]');
	if(!track){return;}
	function step(dir){
		var card=track.querySelector('li.product');
		var w=card?card.getBoundingClientRect().width+16:220;
		track.scrollBy({left:dir*w*2,behavior:'smooth'});
	}
	var prev=document.querySelector('[data-gw-recent-prev]');
	var next=document.querySelector('[data-gw-recent-next]');
	if(prev){prev.addEventListener('click',function(){step(-1);});}
	if(next){next.addEventListener('click',function(){step(1);});}
})();
</script>
		<?php
	}




	/**
	 * Print critical single-product layout CSS inline in <head>, after the
	 * enqueued stylesheets, on product pages only. The site serves a cached,
	 * combined CSS bundle (wpo-minify-*.css) that can lag behind theme updates,
	 * so committed main.css fixes may not reach the page. Emitting the critical
	 * layout rules inline guarantees the correct product layout is always served
	 * and cannot be defeated by a stale minified bundle (last in <head> + !important).
	 */
	public function critical_product_css(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		echo '<style id="gw-critical-product">'
			. '.single-product div.product{display:grid !important;grid-template-columns:minmax(0,1fr) minmax(0,1fr) !important;column-gap:2.5rem !important;row-gap:1.75rem !important;align-items:start !important;float:none !important}'
			. '.single-product div.product::before,.single-product div.product::after{content:none !important;display:none !important}'
			. '.single-product div.product>*{grid-column:1 / -1 !important;float:none !important;width:auto !important;max-width:100% !important;min-width:0 !important;clear:none !important}'
			. '.single-product div.product>.woocommerce-product-gallery,.single-product div.product>div.images{grid-column:1 / 2 !important;grid-row:1 !important;margin:0 !important;position:static !important}'
			. '.single-product div.product>.summary,.single-product div.product>.entry-summary{grid-column:2 / 3 !important;grid-row:1 !important;margin:0 !important;min-width:0 !important;overflow-wrap:break-word !important}'
			. '.single-product div.product .woocommerce-product-gallery{position:relative !important;width:100% !important;max-width:100% !important;min-width:0 !important;float:none !important;margin:0 !important;opacity:1 !important;overflow:hidden !important}'
			. '.single-product div.product .woocommerce-product-gallery__wrapper{display:flex !important;flex-wrap:wrap !important;gap:.55rem !important;align-items:flex-start !important;width:100% !important;max-width:100% !important;min-width:0 !important;margin:0 !important;padding:0 !important;transform:none !important;box-sizing:border-box !important}'
			. '.single-product div.product .woocommerce-product-gallery__image{flex:0 0 66px !important;width:66px !important;min-width:0 !important;margin:0 !important;border:1px solid rgba(0,0,0,.1) !important;border-radius:8px !important;overflow:hidden !important;background:#fff !important;list-style:none !important;box-sizing:border-box !important}'
			. '.single-product div.product .woocommerce-product-gallery__image:first-child{flex:0 0 100% !important;width:100% !important;max-width:100% !important;min-width:0 !important;border:0 !important;border-radius:12px !important;background:#f7f7f5 !important;display:block !important;text-align:center !important;padding:.4rem !important;box-sizing:border-box !important}'
			. '.single-product div.product .woocommerce-product-gallery__image>a{display:block !important;width:100% !important;max-width:100% !important;min-width:0 !important}'
			. '.single-product div.product .woocommerce-product-gallery img{max-width:100% !important;height:auto !important;aspect-ratio:auto !important;display:block !important}'
			. '.single-product div.product .woocommerce-product-gallery__image:first-child img{display:inline-block !important;width:auto !important;max-width:100% !important;height:auto !important;max-height:66vh !important;object-fit:contain !important;margin:0 auto !important}'
			. '.single-product div.product .woocommerce-product-gallery__image:not(:first-child) img{width:100% !important;height:66px !important;object-fit:cover !important;cursor:pointer !important}'
			. '.single-product div.product .woocommerce-product-gallery__trigger{position:absolute !important;top:.6rem !important;right:.6rem !important;z-index:5 !important}'
			. '.single-product div.product form.variations_form,.single-product div.product form.variations_form .variations,.single-product div.product .woocommerce-variation-add-to-cart{width:100% !important}'
			. '@media(max-width:900px){.single-product div.product{grid-template-columns:1fr !important;column-gap:0 !important}.single-product div.product>.woocommerce-product-gallery,.single-product div.product>div.images,.single-product div.product>.summary,.single-product div.product>.entry-summary{grid-column:1 / -1 !important;grid-row:auto !important}}'
			. '</style>' . "\n";
	}

}
