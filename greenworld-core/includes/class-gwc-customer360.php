<?php
/**
 * Customer 360 (Phase 4).
 *
 * A single admin screen (Users -> Customer 360) that brings together everything
 * Green World already knows about a customer, so the team recognises them
 * instantly: profile + contact, orders and spend, products bought, reward
 * points and batches, distributor status, open "My Health" support items, and
 * their consultation cases (with case number + status). It reads only from
 * existing systems - WooCommerce, the GWC points/distributor/records modules,
 * GWC_Cases, and (if present) the YITH wishlist - and invents nothing.
 *
 * It also adds a "360 view" row action on the Users list, and can be opened for
 * a lead by email straight from a consultation case.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Customer_360 {

	private static $instance = null;
	private const SLUG = 'gwc-customer-360';

	public static function instance(): GWC_Customer_360 {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'user_row_actions', array( $this, 'row_action' ), 10, 2 );
	}

	public function menu(): void {
		add_submenu_page(
			'users.php',
			__( 'Customer 360', 'greenworld-core' ),
			__( 'Customer 360', 'greenworld-core' ),
			'edit_users',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array<string,string> $actions
	 * @param WP_User               $user
	 * @return array<string,string>
	 */
	public function row_action( $actions, $user ): array {
		if ( $user instanceof WP_User && current_user_can( 'edit_users' ) ) {
			$url = admin_url( 'users.php?page=' . self::SLUG . '&user=' . (int) $user->ID );
			$actions['gwc_360'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Customer 360', 'greenworld-core' ) . '</a>';
		}
		return (array) $actions;
	}

	private function url_for( int $uid ): string {
		return admin_url( 'users.php?page=' . self::SLUG . '&user=' . $uid );
	}

	/* ================================================================== *
	 * Router.
	 * ================================================================== */

	public function render(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to view customers.', 'greenworld-core' ) );
		}
		echo '<div class="wrap gw-360">';
		echo '<h1>' . esc_html__( 'Customer 360', 'greenworld-core' ) . '</h1>';
		$this->styles();

		$uid   = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email = isset( $_GET['search_email'] ) ? sanitize_email( (string) wp_unslash( $_GET['search_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 0 === $uid && '' !== $email ) {
			$found = get_user_by( 'email', $email );
			if ( $found instanceof WP_User ) {
				$uid = (int) $found->ID;
			}
		}

		if ( $uid > 0 ) {
			$this->render_profile( $uid );
		} elseif ( '' !== $email ) {
			$this->render_lead( $email );
		} else {
			$this->render_search();
		}
		echo '</div>';
	}

	/* ================================================================== *
	 * Search / directory.
	 * ================================================================== */

	private function render_search(): void {
		$term = isset( $_GET['cs'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['cs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<form method="get" style="margin:1rem 0">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="search" name="cs" value="' . esc_attr( $term ) . '" class="regular-text" placeholder="' . esc_attr__( 'Search by name, email or phone', 'greenworld-core' ) . '" /> ';
		submit_button( __( 'Search customers', 'greenworld-core' ), 'primary', '', false );
		echo '</form>';

		if ( '' !== $term ) {
			$users = get_users(
				array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
					'number'         => 40,
					'orderby'        => 'display_name',
				)
			);
			// Also match by billing/gw phone.
			if ( empty( $users ) ) {
				$users = get_users(
					array(
						'number'     => 40,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query' => array(
							'relation' => 'OR',
							array(
								'key'     => 'billing_phone',
								'value'   => $term,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_gw_phone',
								'value'   => $term,
								'compare' => 'LIKE',
							),
						),
					)
				);
			}
		} else {
			$users = get_users(
				array(
					'number'  => 25,
					'orderby' => 'registered',
					'order'   => 'DESC',
				)
			);
			echo '<h2>' . esc_html__( 'Recent customers', 'greenworld-core' ) . '</h2>';
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'greenworld-core' ) . '</th><th>' . esc_html__( 'Email', 'greenworld-core' ) . '</th><th>' . esc_html__( 'Type', 'greenworld-core' ) . '</th><th></th></tr></thead><tbody>';
		if ( empty( $users ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No matching customers.', 'greenworld-core' ) . '</td></tr>';
		}
		foreach ( (array) $users as $u ) {
			if ( ! $u instanceof WP_User ) {
				continue;
			}
			$type = (string) get_user_meta( (int) $u->ID, '_gw_account_type', true );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $u->display_name ) . '</strong></td>';
			echo '<td>' . esc_html( $u->user_email ) . '</td>';
			echo '<td>' . esc_html( '' !== $type ? ucfirst( $type ) : '—' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $this->url_for( (int) $u->ID ) ) . '">' . esc_html__( 'Open 360', 'greenworld-core' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/* ================================================================== *
	 * Lead (email with no user account).
	 * ================================================================== */

	private function render_lead( string $email ): void {
		echo '<p><a href="' . esc_url( admin_url( 'users.php?page=' . self::SLUG ) ) . '">&larr; ' . esc_html__( 'All customers', 'greenworld-core' ) . '</a></p>';
		echo '<h2>' . esc_html( $email ) . ' <span class="gw-360__tag">' . esc_html__( 'Lead (no account)', 'greenworld-core' ) . '</span></h2>';
		echo '<div class="gw-360__grid">';
		$this->card_consultations_by_email( $email );
		echo '</div>';
	}

	/* ================================================================== *
	 * Full customer profile.
	 * ================================================================== */

	private function render_profile( int $uid ): void {
		$user = get_userdata( $uid );
		if ( ! $user instanceof WP_User ) {
			echo '<p>' . esc_html__( 'Customer not found.', 'greenworld-core' ) . '</p>';
			return;
		}

		echo '<p><a href="' . esc_url( admin_url( 'users.php?page=' . self::SLUG ) ) . '">&larr; ' . esc_html__( 'All customers', 'greenworld-core' ) . '</a> &nbsp; ';
		echo '<a class="button button-small" href="' . esc_url( (string) get_edit_user_link( $uid ) ) . '">' . esc_html__( 'Edit user', 'greenworld-core' ) . '</a></p>';

		$is_dist = class_exists( 'GWC_Distributor' ) && GWC_Distributor::is_distributor( $uid );
		echo '<h2>' . esc_html( $user->display_name );
		if ( $is_dist ) {
			echo ' <span class="gw-360__tag gw-360__tag--dist">' . esc_html__( 'Distributor', 'greenworld-core' ) . '</span>';
		}
		echo '</h2>';

		echo '<div class="gw-360__grid">';
		$this->card_profile( $user );
		$this->card_orders( $uid );
		$this->card_products( $uid );
		$this->card_points( $uid, $is_dist );
		$this->card_support( $uid );
		$this->card_consultations_by_email( (string) $user->user_email );
		$this->card_wishlist( $uid );
		echo '</div>';
	}

	private function open_card( string $title ): void {
		echo '<div class="gw-360__card"><h3>' . esc_html( $title ) . '</h3>';
	}

	private function row( string $label, string $value ): void {
		if ( '' === $value ) {
			return;
		}
		echo '<p class="gw-360__row"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></p>';
	}

	private function card_profile( WP_User $user ): void {
		$uid   = (int) $user->ID;
		$phone = (string) get_user_meta( $uid, '_gw_phone', true );
		if ( '' === $phone ) {
			$phone = (string) get_user_meta( $uid, 'billing_phone', true );
		}
		$this->open_card( __( 'Profile', 'greenworld-core' ) );
		$this->row( __( 'Email', 'greenworld-core' ), (string) $user->user_email );
		$this->row( __( 'Phone', 'greenworld-core' ), $phone );
		$this->row( __( 'County / Town', 'greenworld-core' ), (string) get_user_meta( $uid, '_gw_county', true ) );
		$type = (string) get_user_meta( $uid, '_gw_account_type', true );
		$this->row( __( 'Account type', 'greenworld-core' ), '' !== $type ? ucfirst( $type ) : __( 'Customer', 'greenworld-core' ) );
		if ( class_exists( 'GWC_Distributor' ) && GWC_Distributor::is_distributor( $uid ) ) {
			$this->row( __( 'Distributor status', 'greenworld-core' ), (string) GWC_Distributor::status( $uid ) );
		}
		$sponsor = (string) get_user_meta( $uid, '_gw_sponsor', true );
		$this->row( __( 'Sponsor / referral', 'greenworld-core' ), $sponsor );
		$this->row( __( 'Registered', 'greenworld-core' ), mysql2date( (string) get_option( 'date_format' ), (string) $user->user_registered ) );
		echo '</div>';
	}

	private function card_orders( int $uid ): void {
		$this->open_card( __( 'Orders', 'greenworld-core' ) );
		if ( ! function_exists( 'wc_get_orders' ) ) {
			echo '<p class="gw-360__muted">' . esc_html__( 'WooCommerce not active.', 'greenworld-core' ) . '</p></div>';
			return;
		}
		$count = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $uid ) : 0;
		$spent = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $uid ) : 0.0;
		$this->row( __( 'Total orders', 'greenworld-core' ), (string) $count );
		$this->row( __( 'Lifetime spend', 'greenworld-core' ), function_exists( 'wc_price' ) ? wp_strip_all_tags( (string) wc_price( $spent ) ) : (string) $spent );

		$orders = wc_get_orders(
			array(
				'customer_id' => $uid,
				'limit'       => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		if ( ! empty( $orders ) ) {
			echo '<ul class="gw-360__list">';
			foreach ( (array) $orders as $o ) {
				if ( ! is_object( $o ) || ! method_exists( $o, 'get_id' ) ) {
					continue;
				}
				$date   = $o->get_date_created() ? $o->get_date_created()->date_i18n( (string) get_option( 'date_format' ) ) : '';
				$status = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $o->get_status() ) : $o->get_status();
				$total  = wp_strip_all_tags( (string) $o->get_formatted_order_total() );
				echo '<li>#' . esc_html( (string) $o->get_order_number() ) . ' · ' . esc_html( $status ) . ' · ' . esc_html( $total ) . ' <span class="gw-360__muted">' . esc_html( $date ) . '</span></li>';
			}
			echo '</ul>';
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $uid ) ) . '">' . esc_html__( 'All orders', 'greenworld-core' ) . ' &rarr;</a></p>';
		} else {
			echo '<p class="gw-360__muted">' . esc_html__( 'No orders yet.', 'greenworld-core' ) . '</p>';
		}
		echo '</div>';
	}

	private function card_products( int $uid ): void {
		$this->open_card( __( 'Products bought', 'greenworld-core' ) );
		$names = $this->purchased_products( $uid );
		if ( empty( $names ) ) {
			echo '<p class="gw-360__muted">' . esc_html__( 'No purchased products yet.', 'greenworld-core' ) . '</p></div>';
			return;
		}
		echo '<ul class="gw-360__list">';
		foreach ( $names as $n ) {
			echo '<li>' . esc_html( $n ) . '</li>';
		}
		echo '</ul></div>';
	}

	private function card_points( int $uid, bool $is_dist ): void {
		if ( ! class_exists( 'GWC_Points' ) ) {
			return;
		}
		$balance = (int) get_user_meta( $uid, GWC_Points::BAL_META, true );
		if ( 0 === $balance && ! $is_dist ) {
			return;
		}
		$this->open_card( __( 'Rewards & points', 'greenworld-core' ) );
		$this->row( __( 'Balance', 'greenworld-core' ), number_format_i18n( $balance ) . ' ' . __( 'pts', 'greenworld-core' ) );
		if ( method_exists( 'GWC_Points', 'user_batches' ) ) {
			$batches = GWC_Points::user_batches( $uid, 5 );
			if ( ! empty( $batches ) ) {
				echo '<ul class="gw-360__list">';
				foreach ( (array) $batches as $b ) {
					if ( ! is_object( $b ) || ! isset( $b->ID ) ) {
						continue;
					}
					$pts   = (int) get_post_meta( (int) $b->ID, '_gw_b_points', true );
					$items = method_exists( 'GWC_Points', 'items_summary' ) ? GWC_Points::items_summary( (int) $b->ID ) : '';
					echo '<li><strong>' . esc_html( '+' . number_format_i18n( $pts ) ) . '</strong> ' . esc_html( $items ) . ' <span class="gw-360__muted">' . esc_html( get_the_date( '', $b ) ) . '</span></li>';
				}
				echo '</ul>';
			}
		}
		echo '</div>';
	}

	private function card_support( int $uid ): void {
		if ( ! class_exists( 'GWC_Records' ) ) {
			return;
		}
		$this->open_card( __( 'Health support', 'greenworld-core' ) );
		$requests = method_exists( 'GWC_Records', 'user_requests' ) ? GWC_Records::user_requests( $uid, 5 ) : array();
		if ( ! empty( $requests ) ) {
			echo '<ul class="gw-360__list">';
			foreach ( (array) $requests as $r ) {
				if ( ! is_object( $r ) || ! isset( $r->ID ) ) {
					continue;
				}
				$type    = (string) get_post_meta( (int) $r->ID, '_gw_r_type', true );
				$product = (string) get_post_meta( (int) $r->ID, '_gw_r_product', true );
				$status  = (string) get_post_meta( (int) $r->ID, '_gw_r_status', true );
				$status  = '' !== $status ? $status : 'open';
				echo '<li><span class="gw-360__pill">' . esc_html( $status ) . '</span> ' . esc_html( $type );
				if ( '' !== $product ) {
					echo ' — ' . esc_html( $product );
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="gw-360__muted">' . esc_html__( 'No support requests.', 'greenworld-core' ) . '</p>';
		}
		echo '</div>';
	}

	private function card_consultations_by_email( string $email ): void {
		$this->open_card( __( 'Consultations', 'greenworld-core' ) );
		if ( ! class_exists( 'GWC_Cases' ) ) {
			echo '<p class="gw-360__muted">' . esc_html__( 'Consultation module unavailable.', 'greenworld-core' ) . '</p></div>';
			return;
		}
		$cases = GWC_Cases::for_email( $email, 15 );
		if ( empty( $cases ) ) {
			echo '<p class="gw-360__muted">' . esc_html__( 'No consultations on record.', 'greenworld-core' ) . '</p></div>';
			return;
		}
		echo '<ul class="gw-360__list">';
		foreach ( (array) $cases as $c ) {
			if ( ! is_object( $c ) || ! isset( $c->ID ) ) {
				continue;
			}
			$id    = (int) $c->ID;
			$focus = (string) get_post_meta( $id, '_gw_c_focus', true );
			if ( '' === $focus ) {
				$focus = (string) get_post_meta( $id, '_gw_c_concern', true );
			}
			$edit = (string) get_edit_post_link( $id );
			echo '<li>';
			echo '<code>' . esc_html( GWC_Cases::number( $id ) ) . '</code> ' . wp_kses_post( GWC_Cases::badge( $id ) );
			if ( '' !== $focus ) {
				echo '<br /><span class="gw-360__muted">' . esc_html( wp_trim_words( $focus, 14 ) ) . '</span>';
			}
			echo ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'open', 'greenworld-core' ) . '</a>';
			echo '</li>';
		}
		echo '</ul></div>';
	}

	private function card_wishlist( int $uid ): void {
		$count = null;
		if ( function_exists( 'yith_wcwl_get_products' ) ) {
			$items = yith_wcwl_get_products( array( 'user_id' => $uid ) );
			$count = is_array( $items ) ? count( $items ) : 0;
		}
		if ( null === $count ) {
			return;
		}
		$this->open_card( __( 'Wishlist', 'greenworld-core' ) );
		$this->row( __( 'Saved products', 'greenworld-core' ), (string) $count );
		echo '</div>';
	}

	/* ================================================================== *
	 * Helpers.
	 * ================================================================== */

	/**
	 * @return array<int,string>
	 */
	private function purchased_products( int $uid ): array {
		if ( $uid <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $uid,
				'status'      => array( 'completed', 'processing', 'on-hold' ),
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$names = array();
		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$n = trim( (string) $item->get_name() );
				if ( '' !== $n ) {
					$names[ $n ] = $n;
				}
			}
		}
		return array_values( $names );
	}

	private function styles(): void {
		echo '<style>'
			. '.gw-360__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;margin-top:1rem}'
			. '.gw-360__card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:1rem 1.1rem}'
			. '.gw-360__card h3{margin:0 0 .6rem;color:#14421f;font-size:1.02rem;border-bottom:1px solid #eef1ee;padding-bottom:.4rem}'
			. '.gw-360__row{display:flex;justify-content:space-between;gap:1rem;margin:.25rem 0;font-size:.9rem}'
			. '.gw-360__row span{color:#50575e}'
			. '.gw-360__list{list-style:none;margin:.2rem 0 0;padding:0}'
			. '.gw-360__list li{padding:.4rem .5rem;border:1px solid #eef1ee;border-radius:7px;margin-bottom:.35rem;background:#fafbf9;font-size:.88rem}'
			. '.gw-360__muted{color:#6a776e;font-size:.85rem}'
			. '.gw-360__pill{display:inline-block;font-size:.7rem;font-weight:700;padding:.1rem .5rem;border-radius:999px;background:#e7f6ec;color:#14612b;text-transform:capitalize}'
			. '.gw-360__tag{display:inline-block;font-size:.72rem;font-weight:700;padding:.15rem .6rem;border-radius:999px;background:#eef1ee;color:#50575e;vertical-align:middle}'
			. '.gw-360__tag--dist{background:#e7f6ec;color:#14612b}'
			. '</style>';
	}
}
