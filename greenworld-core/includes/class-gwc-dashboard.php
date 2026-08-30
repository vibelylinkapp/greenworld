<?php
/**
 * "My Green World" customer dashboard + one-click reorder.
 *
 * Phase 1 of the platform plan. Turns the WooCommerce My Account area into a
 * proper customer home:
 *
 *   - A "My Green World" overview tab (placed first) with a time-aware greeting
 *     and summary cards: My Orders, Wishlist, Rewards points, and Health
 *     support - each linking to the relevant screen. It also shows a short
 *     "reorder your usual products" preview.
 *   - A "Reorder" tab listing every product the customer has bought, with a
 *     quantity box and an add-to-cart button, so repeat buying is one click.
 *
 * Everything reads from systems that already exist (WooCommerce orders, the
 * GWC points ledger, distributor role, GWC_Records support items, and - if
 * present - the YITH wishlist). No customer data is invented.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Dashboard {

	private static $instance = null;

	public const EP_OVERVIEW = 'overview';
	public const EP_REORDER   = 'reorder';

	public static function instance(): GWC_Dashboard {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_action( 'woocommerce_account_' . self::EP_OVERVIEW . '_endpoint', array( $this, 'render_overview' ) );
		add_action( 'woocommerce_account_' . self::EP_REORDER . '_endpoint', array( $this, 'render_reorder' ) );
	}

	public function add_endpoints(): void {
		add_rewrite_endpoint( self::EP_OVERVIEW, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_REORDER, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function query_vars( $vars ): array {
		$vars[] = self::EP_OVERVIEW;
		$vars[] = self::EP_REORDER;
		return $vars;
	}

	/**
	 * Put "My Green World" first and "Reorder" straight after Orders.
	 *
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public function menu_items( $items ): array {
		$items = (array) $items;
		unset( $items[ self::EP_OVERVIEW ], $items[ self::EP_REORDER ] );

		$out = array();
		// Overview goes first, replacing the default "Dashboard" position.
		$out[ self::EP_OVERVIEW ] = __( 'My Green World', 'greenworld-core' );
		foreach ( $items as $key => $label ) {
			if ( 'dashboard' === $key ) {
				// Skip the stock dashboard label; our overview supersedes it.
				continue;
			}
			$out[ $key ] = $label;
			if ( 'orders' === $key ) {
				$out[ self::EP_REORDER ] = __( 'Reorder', 'greenworld-core' );
			}
		}
		if ( ! isset( $out[ self::EP_REORDER ] ) ) {
			$out[ self::EP_REORDER ] = __( 'Reorder', 'greenworld-core' );
		}
		return $out;
	}

	/* ================================================================== *
	 * Helpers.
	 * ================================================================== */

	private function endpoint_url( string $ep ): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( $ep );
		}
		return home_url( '/my-account/' . $ep . '/' );
	}

	private function greeting(): string {
		$hour = (int) current_time( 'G' );
		if ( $hour < 12 ) {
			$part = __( 'Good morning', 'greenworld-core' );
		} elseif ( $hour < 17 ) {
			$part = __( 'Good afternoon', 'greenworld-core' );
		} else {
			$part = __( 'Good evening', 'greenworld-core' );
		}
		$user  = wp_get_current_user();
		$first = ( $user instanceof WP_User && '' !== $user->first_name ) ? $user->first_name : ( $user instanceof WP_User ? $user->display_name : '' );
		return '' !== $first ? sprintf( '%s, %s', $part, $first ) : $part;
	}

	/**
	 * Products the customer has purchased, most-recent first, de-duplicated by
	 * product, with the last quantity ordered. Only currently purchasable
	 * products are returned so the reorder button always works.
	 *
	 * @return array<int,array{id:int,name:string,qty:int,product:object}>
	 */
	private function purchased_lines( int $user_id, int $limit = 50 ): array {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing', 'on-hold' ),
				'limit'       => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$lines = array();
		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
					continue;
				}
				$pid = (int) $item->get_product_id();
				if ( $pid <= 0 || isset( $lines[ $pid ] ) ) {
					continue;
				}
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
				if ( ! $product || ! method_exists( $product, 'is_purchasable' ) || ! $product->is_purchasable() ) {
					continue;
				}
				$qty = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1;
				$lines[ $pid ] = array(
					'id'      => $pid,
					'name'    => (string) $item->get_name(),
					'qty'     => max( 1, $qty ),
					'product' => $product,
				);
			}
		}
		return array_values( $lines );
	}

	private function orders_count( int $uid ): int {
		if ( function_exists( 'wc_get_customer_order_count' ) ) {
			return (int) wc_get_customer_order_count( $uid );
		}
		return 0;
	}

	/**
	 * Wishlist item count via YITH, if that plugin is active. Null means "no
	 * wishlist system" so the card is hidden.
	 */
	private function wishlist_count( int $uid ): ?int {
		if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
			return (int) yith_wcwl_count_all_products();
		}
		if ( function_exists( 'yith_wcwl_count_products' ) ) {
			return (int) yith_wcwl_count_products();
		}
		return null;
	}

	private function wishlist_url(): string {
		if ( function_exists( 'yith_wcwl_object' ) && is_callable( array( yith_wcwl_object(), 'get_wishlist_url' ) ) ) {
			return (string) yith_wcwl_object()->get_wishlist_url();
		}
		if ( class_exists( 'YITH_WCWL' ) && is_callable( array( 'YITH_WCWL', 'get_wishlist_url' ) ) ) {
			return (string) YITH_WCWL::get_wishlist_url();
		}
		return '';
	}

	private function points_balance( int $uid ): int {
		if ( class_exists( 'GWC_Points' ) ) {
			return (int) get_user_meta( $uid, GWC_Points::BAL_META, true );
		}
		return (int) get_user_meta( $uid, '_gw_points_balance', true );
	}

	/**
	 * Count of the customer's open "My Health" requests (plugin-owned data).
	 */
	private function open_support_count( int $uid ): int {
		if ( ! class_exists( 'GWC_Records' ) || ! method_exists( 'GWC_Records', 'user_requests' ) ) {
			return 0;
		}
		$open = 0;
		foreach ( (array) GWC_Records::user_requests( $uid, 20 ) as $r ) {
			if ( ! is_object( $r ) || ! isset( $r->ID ) ) {
				continue;
			}
			$status = (string) get_post_meta( (int) $r->ID, '_gw_r_status', true );
			if ( 'handled' !== $status ) {
				$open++;
			}
		}
		return $open;
	}

	/* ================================================================== *
	 * Overview.
	 * ================================================================== */

	public function render_overview(): void {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your dashboard.', 'greenworld-core' ) . '</p>';
			return;
		}
		$uid = (int) get_current_user_id();
		$this->styles();

		echo '<div class="gw-dash">';
		echo '<h2 class="gw-dash__hi">' . esc_html( $this->greeting() ) . '</h2>';
		echo '<p class="gw-dash__lead">' . esc_html__( 'Welcome to your Green World account. Here is everything in one place.', 'greenworld-core' ) . '</p>';

		echo '<div class="gw-dash__cards">';

		// Orders.
		$this->card(
			__( 'My Orders', 'greenworld-core' ),
			(string) $this->orders_count( $uid ),
			__( 'View orders & tracking', 'greenworld-core' ),
			$this->endpoint_url( 'orders' )
		);

		// Wishlist (only if a wishlist system is present).
		$wl = $this->wishlist_count( $uid );
		if ( null !== $wl ) {
			$this->card(
				__( 'Wishlist', 'greenworld-core' ),
				(string) $wl,
				__( 'View wishlist', 'greenworld-core' ),
				$this->wishlist_url()
			);
		}

		// Rewards points (only if the customer has any, or is a distributor).
		$pts        = $this->points_balance( $uid );
		$is_dist    = class_exists( 'GWC_Distributor' ) && GWC_Distributor::is_distributor( $uid );
		if ( $pts > 0 || $is_dist ) {
			$this->card(
				__( 'Rewards', 'greenworld-core' ),
				number_format_i18n( $pts ),
				$is_dist ? __( 'Open distributor dashboard', 'greenworld-core' ) : __( 'Points on your account', 'greenworld-core' ),
				$is_dist ? $this->endpoint_url( 'distributor' ) : ''
			);
		}

		// Health support.
		$this->card(
			__( 'Health support', 'greenworld-core' ),
			(string) $this->open_support_count( $uid ),
			__( 'Requests & messages', 'greenworld-core' ),
			$this->endpoint_url( 'health' ),
			__( 'open', 'greenworld-core' )
		);

		echo '</div>'; // cards

		// Reorder preview.
		$lines = $this->purchased_lines( $uid, 30 );
		if ( ! empty( $lines ) ) {
			echo '<div class="gw-dash__panel">';
			echo '<div class="gw-dash__panel-head"><h3>' . esc_html__( 'Reorder your usual products', 'greenworld-core' ) . '</h3>';
			echo '<a class="gw-dash__more" href="' . esc_url( $this->endpoint_url( self::EP_REORDER ) ) . '">' . esc_html__( 'See all', 'greenworld-core' ) . '</a></div>';
			echo '<div class="gw-reorder">';
			$preview = array_slice( $lines, 0, 3 );
			foreach ( $preview as $line ) {
				$this->reorder_row( $line );
			}
			echo '</div>';
			echo '</div>';
		}

		// Quick links.
		echo '<div class="gw-dash__links">';
		echo '<a class="gw-dash__link" href="' . esc_url( $this->endpoint_url( 'edit-address' ) ) . '">' . esc_html__( 'Delivery addresses', 'greenworld-core' ) . '</a>';
		echo '<a class="gw-dash__link" href="' . esc_url( $this->endpoint_url( 'edit-account' ) ) . '">' . esc_html__( 'Account details', 'greenworld-core' ) . '</a>';
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			echo '<a class="gw-dash__link" href="' . esc_url( (string) wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'greenworld-core' ) . '</a>';
		}
		echo '</div>';

		echo '</div>'; // gw-dash
	}

	/**
	 * @param string $title
	 * @param string $value
	 * @param string $link_label
	 * @param string $url
	 * @param string $suffix
	 */
	private function card( string $title, string $value, string $link_label, string $url, string $suffix = '' ): void {
		echo '<div class="gw-dcard">';
		echo '<span class="gw-dcard__title">' . esc_html( $title ) . '</span>';
		echo '<span class="gw-dcard__value">' . esc_html( $value );
		if ( '' !== $suffix ) {
			echo ' <small>' . esc_html( $suffix ) . '</small>';
		}
		echo '</span>';
		if ( '' !== $url ) {
			echo '<a class="gw-dcard__link" href="' . esc_url( $url ) . '">' . esc_html( $link_label ) . ' &rarr;</a>';
		} else {
			echo '<span class="gw-dcard__muted">' . esc_html( $link_label ) . '</span>';
		}
		echo '</div>';
	}

	/* ================================================================== *
	 * Reorder.
	 * ================================================================== */

	public function render_reorder(): void {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to reorder your products.', 'greenworld-core' ) . '</p>';
			return;
		}
		$uid = (int) get_current_user_id();
		$this->styles();
		$lines = $this->purchased_lines( $uid, 100 );

		echo '<div class="gw-dash">';
		echo '<h2 class="gw-dash__hi">' . esc_html__( 'Reorder', 'greenworld-core' ) . '</h2>';
		if ( empty( $lines ) ) {
			echo '<p class="gw-dash__lead">' . esc_html__( 'Once you have placed an order, your products will appear here so you can reorder them in one click.', 'greenworld-core' ) . '</p>';
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				echo '<p><a class="button" href="' . esc_url( (string) wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Browse products', 'greenworld-core' ) . '</a></p>';
			}
			echo '</div>';
			return;
		}
		echo '<p class="gw-dash__lead">' . esc_html__( 'Running low? Add your previous products straight back to the cart.', 'greenworld-core' ) . '</p>';
		echo '<div class="gw-reorder">';
		foreach ( $lines as $line ) {
			$this->reorder_row( $line );
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @param array{id:int,name:string,qty:int,product:object} $line
	 */
	private function reorder_row( array $line ): void {
		$product = $line['product'];
		$pid     = (int) $line['id'];
		$img     = is_callable( array( $product, 'get_image' ) ) ? $product->get_image( 'woocommerce_gallery_thumbnail' ) : '';
		$price   = is_callable( array( $product, 'get_price_html' ) ) ? (string) $product->get_price_html() : '';
		$link    = (string) get_permalink( $pid );
		$in_stock = is_callable( array( $product, 'is_in_stock' ) ) ? (bool) $product->is_in_stock() : true;

		echo '<div class="gw-reorder__row">';
		echo '<a class="gw-reorder__media" href="' . esc_url( $link ) . '">' . wp_kses_post( $img ) . '</a>';
		echo '<div class="gw-reorder__info">';
		echo '<a class="gw-reorder__name" href="' . esc_url( $link ) . '">' . esc_html( $line['name'] ) . '</a>';
		if ( '' !== $price ) {
			echo '<div class="gw-reorder__price">' . wp_kses_post( $price ) . '</div>';
		}
		echo '</div>';

		if ( $in_stock ) {
			echo '<form class="gw-reorder__form" method="post" action="' . esc_url( $this->endpoint_url( self::EP_REORDER ) ) . '">';
			echo '<input type="number" class="gw-reorder__qty" name="quantity" value="' . esc_attr( (string) $line['qty'] ) . '" min="1" step="1" aria-label="' . esc_attr__( 'Quantity', 'greenworld-core' ) . '" />';
			echo '<input type="hidden" name="add-to-cart" value="' . esc_attr( (string) $pid ) . '" />';
			echo '<button type="submit" class="button gw-reorder__btn">' . esc_html__( 'Reorder', 'greenworld-core' ) . '</button>';
			echo '</form>';
		} else {
			echo '<span class="gw-reorder__oos">' . esc_html__( 'Out of stock', 'greenworld-core' ) . '</span>';
		}
		echo '</div>';
	}

	/* ================================================================== *
	 * Styles.
	 * ================================================================== */

	private function styles(): void {
		echo '<style>'
			. '.gw-dash{--gw:#14421f;--gold:#b8892b}'
			. '.gw-dash__hi{color:var(--gw);margin:.1rem 0 .2rem;font-size:1.5rem}'
			. '.gw-dash__lead{color:#4a5a4f;margin:0 0 1.1rem}'
			. '.gw-dash__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.9rem;margin-bottom:1.4rem}'
			. '.gw-dcard{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:1rem 1.1rem;box-shadow:0 6px 18px rgba(0,0,0,.06);display:flex;flex-direction:column;gap:.15rem}'
			. '.gw-dcard__title{font-size:.82rem;font-weight:700;color:#4a5a4f;text-transform:uppercase;letter-spacing:.03em}'
			. '.gw-dcard__value{font-size:2rem;font-weight:800;color:var(--gw);line-height:1.1}'
			. '.gw-dcard__value small{font-size:.8rem;font-weight:600;color:#6a776e}'
			. '.gw-dcard__link{margin-top:.35rem;font-size:.85rem;font-weight:600;color:var(--gold);text-decoration:none}'
			. '.gw-dcard__link:hover{text-decoration:underline}'
			. '.gw-dcard__muted{margin-top:.35rem;font-size:.85rem;color:#8a938c}'
			. '.gw-dash__panel{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:1rem 1.1rem;margin-bottom:1.4rem;box-shadow:0 6px 18px rgba(0,0,0,.06)}'
			. '.gw-dash__panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem}'
			. '.gw-dash__panel-head h3{margin:0;color:var(--gw);font-size:1.12rem}'
			. '.gw-dash__more{font-size:.85rem;font-weight:600;color:var(--gold);text-decoration:none}'
			. '.gw-reorder{display:flex;flex-direction:column;gap:.6rem}'
			. '.gw-reorder__row{display:flex;align-items:center;gap:.8rem;padding:.55rem;border:1px solid rgba(0,0,0,.08);border-radius:10px;background:#fafbf9}'
			. '.gw-reorder__media{flex:0 0 auto;width:52px;height:52px;border-radius:8px;overflow:hidden;background:#fff;display:block}'
			. '.gw-reorder__media img{width:52px;height:52px;object-fit:cover;display:block}'
			. '.gw-reorder__info{flex:1 1 auto;min-width:0}'
			. '.gw-reorder__name{display:block;font-weight:700;color:#14311c;text-decoration:none;font-size:.95rem}'
			. '.gw-reorder__name:hover{text-decoration:underline}'
			. '.gw-reorder__price{color:#4a5a4f;font-size:.88rem;margin-top:.1rem}'
			. '.gw-reorder__form{display:flex;align-items:center;gap:.4rem;flex:0 0 auto}'
			. '.gw-reorder__qty{width:64px;padding:.4rem .5rem;border:1px solid rgba(0,0,0,.2);border-radius:8px;font:inherit;font-size:16px}'
			. '.gw-reorder__btn{white-space:nowrap}'
			. '.gw-reorder__oos{color:#8a1c1c;font-weight:600;font-size:.85rem}'
			. '.gw-dash__links{display:flex;flex-wrap:wrap;gap:.6rem}'
			. '.gw-dash__link{display:inline-block;padding:.5rem .85rem;border:1px solid rgba(0,0,0,.12);border-radius:999px;color:var(--gw);text-decoration:none;font-size:.88rem;font-weight:600;background:#fff}'
			. '.gw-dash__link:hover{background:#f1f6f2}'
			. '@media(max-width:520px){.gw-reorder__row{flex-wrap:wrap}.gw-reorder__form{width:100%;justify-content:flex-end}}'
			. '</style>';
	}
}
