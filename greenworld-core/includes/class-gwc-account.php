<?php
/**
 * Customer dashboard on the WooCommerce "My Account" page.
 *
 * Adds a "My Health" tab that shows the customer's purchased products and lets
 * them: request a refill or a change of product, log a progress check-in, and
 * message the Green World team. All submissions are saved via GWC_Records and
 * (when configured) alert staff on WhatsApp.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Account {

	private static $instance = null;
	public const ENDPOINT    = 'health';

	public static function instance(): GWC_Account {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_action( 'admin_post_gwc_request', array( $this, 'handle_request' ) );
		add_action( 'admin_post_gwc_checkin', array( $this, 'handle_checkin' ) );
		add_action( 'admin_post_gwc_message', array( $this, 'handle_message' ) );
	}

	public function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function query_vars( $vars ): array {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public function menu_item( $items ): array {
		$new = array();
		foreach ( (array) $items as $key => $label ) {
			if ( 'customer-logout' === $key && ! isset( $new[ self::ENDPOINT ] ) ) {
				$new[ self::ENDPOINT ] = __( 'My Health', 'greenworld-core' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'My Health', 'greenworld-core' );
		}
		return $new;
	}

	private function endpoint_url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( self::ENDPOINT );
		}
		return home_url( '/my-account/' . self::ENDPOINT . '/' );
	}

	private function account_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return home_url( '/my-account/' );
	}

	private function opt( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	}

	private function textarea( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	}

	private function verify(): bool {
		return isset( $_POST['gwc_health_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gwc_health_nonce'] ) ), 'gwc_health' );
	}

	/**
	 * Unique product names the customer has purchased.
	 *
	 * @return array<int,string>
	 */
	private function purchased_products( int $user_id ): array {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
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

	/* ------------------------------------------------------------------ *
	 * Form handlers (admin-post).
	 * ------------------------------------------------------------------ */

	public function handle_request(): void {
		$url = $this->endpoint_url();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->account_url() );
			exit;
		}
		if ( ! $this->verify() ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		$uid     = get_current_user_id();
		$product = $this->opt( 'gw_product' );
		if ( '__other' === $product || '' === $product ) {
			$product = $this->opt( 'gw_product_other' );
		}
		$type = $this->opt( 'gw_type' );
		$note = $this->textarea( 'gw_note' );
		if ( '' === $type && '' === $product && '' === $note ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		GWC_Records::add_request( (int) $uid, $product, $type, $note );
		wp_safe_redirect( add_query_arg( 'gwh', 'req_ok', $url ) );
		exit;
	}

	public function handle_checkin(): void {
		$url = $this->endpoint_url();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->account_url() );
			exit;
		}
		if ( ! $this->verify() ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		$uid     = get_current_user_id();
		$status  = $this->opt( 'gw_status' );
		$product = $this->opt( 'gw_ck_product' );
		$note    = $this->textarea( 'gw_note' );
		if ( '' === $status ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		GWC_Records::add_checkin( (int) $uid, $status, $product, $note );
		wp_safe_redirect( add_query_arg( 'gwh', 'checkin_ok', $url ) );
		exit;
	}

	public function handle_message(): void {
		$url = $this->endpoint_url();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->account_url() );
			exit;
		}
		if ( ! $this->verify() ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		$uid  = get_current_user_id();
		$body = $this->textarea( 'gw_message' );
		if ( '' === trim( $body ) ) {
			wp_safe_redirect( add_query_arg( 'gwh', 'error', $url ) );
			exit;
		}
		GWC_Records::add_message( (int) $uid, $body );
		wp_safe_redirect( add_query_arg( 'gwh', 'msg_ok', $url ) . '#gw-thread' );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Rendering.
	 * ------------------------------------------------------------------ */

	public function render(): void {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your health dashboard.', 'greenworld-core' ) . '</p>';
			return;
		}
		$uid = get_current_user_id();
		$this->styles();
		$this->notice();
		echo '<div class="gw-health">';
		$this->section_products( (int) $uid );
		$this->section_request( (int) $uid );
		$this->section_checkin( (int) $uid );
		$this->section_thread( (int) $uid );
		echo '</div>';
	}

	private function styles(): void {
		echo '<style>.gw-health{--gw:#14421f}.gw-health .gw-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:1.1rem 1.15rem;margin:0 0 1.1rem;box-shadow:0 6px 18px rgba(0,0,0,.06)}.gw-health h3{margin:.1rem 0 .5rem;color:var(--gw);font-size:1.12rem}.gw-health p.gw-sub{margin:0 0 .8rem;color:#4a5a4f;font-size:.92rem}.gw-health label{display:block;font-size:.8rem;font-weight:600;color:var(--gw);margin-top:.55rem}.gw-health input,.gw-health select,.gw-health textarea{width:100%;padding:.5rem .65rem;border:1px solid rgba(0,0,0,.2);border-radius:8px;font:inherit;font-size:16px;margin-top:.2rem;box-sizing:border-box;background:#fff;color:#14311c}.gw-health textarea{min-height:70px;resize:vertical}.gw-health .gw-actions{margin-top:.8rem}.gw-health .gw-grid2{display:grid;grid-template-columns:1fr 1fr;gap:.4rem .7rem}@media(max-width:520px){.gw-health .gw-grid2{grid-template-columns:1fr}}.gw-health ul.gw-list{list-style:none;margin:.4rem 0 0;padding:0}.gw-health ul.gw-list li{padding:.55rem .65rem;border:1px solid rgba(0,0,0,.08);border-radius:8px;margin-bottom:.4rem;background:#fafbf9}.gw-health .gw-pill{display:inline-block;font-size:.72rem;font-weight:700;padding:.12rem .5rem;border-radius:999px;background:#e7f6ec;color:#14612b;text-transform:capitalize}.gw-health .gw-pill--handled{background:#e8eef7;color:#284a7a}.gw-health .gw-meta{color:#6a776e;font-size:.8rem}.gw-health .gw-thread{max-height:360px;overflow:auto;padding:.2rem}.gw-health .gw-msg{max-width:82%;padding:.5rem .7rem;border-radius:12px;margin:.35rem 0;font-size:.92rem;line-height:1.35}.gw-health .gw-msg--you{margin-left:auto;background:#e7f6ec;color:#12401f;border-bottom-right-radius:4px}.gw-health .gw-msg--team{margin-right:auto;background:#f0f1ee;color:#26302a;border-bottom-left-radius:4px}.gw-health .gw-msg time{display:block;font-size:.7rem;color:#7a857d;margin-top:.2rem}.gw-health .gw-notice{padding:.7rem .9rem;border-radius:10px;margin:0 0 1rem;font-size:.92rem}.gw-health .gw-notice--ok{background:#e7f6ec;color:#14612b}.gw-health .gw-notice--err{background:#fdecec;color:#8a1c1c}.gw-health .button{margin-top:.2rem}</style>';
	}

	private function notice(): void {
		$s = isset( $_GET['gwh'] ) ? sanitize_key( (string) $_GET['gwh'] ) : '';
		if ( '' === $s ) {
			return;
		}
		$map = array(
			'req_ok'     => array( 'ok', __( 'Your request has been sent to our team. We will be in touch.', 'greenworld-core' ) ),
			'checkin_ok' => array( 'ok', __( 'Thank you for the update. Your check-in has been saved.', 'greenworld-core' ) ),
			'msg_ok'     => array( 'ok', __( 'Message sent. Our team will reply here and by email.', 'greenworld-core' ) ),
			'error'      => array( 'err', __( 'Sorry, that could not be submitted. Please try again.', 'greenworld-core' ) ),
		);
		if ( ! isset( $map[ $s ] ) ) {
			return;
		}
		echo '<div class="gw-notice gw-notice--' . esc_attr( $map[ $s ][0] ) . '">' . esc_html( $map[ $s ][1] ) . '</div>';
	}

	private function section_products( int $uid ): void {
		$products = $this->purchased_products( $uid );
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Your products', 'greenworld-core' ) . '</h3>';
		if ( empty( $products ) ) {
			echo '<p class="gw-sub">' . esc_html__( 'Your purchased products will appear here. When you place an order, you can request refills or changes from this page.', 'greenworld-core' ) . '</p>';
		} else {
			echo '<p class="gw-sub">' . esc_html__( 'Products from your recent orders. Use the form below to request more or a change.', 'greenworld-core' ) . '</p>';
			echo '<ul class="gw-list">';
			foreach ( $products as $name ) {
				echo '<li>' . esc_html( $name ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private function product_select( int $uid ): void {
		$products = $this->purchased_products( $uid );
		if ( empty( $products ) ) {
			echo '<label for="gw_product_other">' . esc_html__( 'Product', 'greenworld-core' ) . '</label>';
			echo '<input type="text" id="gw_product_other" name="gw_product_other" placeholder="' . esc_attr__( 'Which product?', 'greenworld-core' ) . '" />';
			return;
		}
		echo '<label for="gw_product">' . esc_html__( 'Product', 'greenworld-core' ) . '</label>';
		echo '<select id="gw_product" name="gw_product">';
		foreach ( $products as $name ) {
			echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '<option value="__other">' . esc_html__( 'Other (type below)', 'greenworld-core' ) . '</option>';
		echo '</select>';
		echo '<input type="text" name="gw_product_other" placeholder="' . esc_attr__( 'If "Other", type the product here', 'greenworld-core' ) . '" />';
	}

	private function section_request( int $uid ): void {
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Request a refill or a change', 'greenworld-core' ) . '</h3>';
		echo '<p class="gw-sub">' . esc_html__( 'Need more of something, or want to change your product? Send a request and our team will help.', 'greenworld-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="gwc_request" />';
		wp_nonce_field( 'gwc_health', 'gwc_health_nonce' );
		echo '<div class="gw-grid2">';
		echo '<div>';
		$this->product_select( $uid );
		echo '</div>';
		echo '<div>';
		echo '<label for="gw_type">' . esc_html__( 'What do you need?', 'greenworld-core' ) . '</label>';
		echo '<select id="gw_type" name="gw_type">';
		$types = array(
			'Refill / more'      => __( 'Refill / more of the same', 'greenworld-core' ),
			'Change of product'  => __( 'Change to a different product', 'greenworld-core' ),
			'Advice'             => __( 'Advice on what to take', 'greenworld-core' ),
		);
		foreach ( $types as $val => $lab ) {
			echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '<label for="gw_note_req">' . esc_html__( 'Note (optional)', 'greenworld-core' ) . '</label>';
		echo '<textarea id="gw_note_req" name="gw_note" placeholder="' . esc_attr__( 'Anything we should know?', 'greenworld-core' ) . '"></textarea>';
		echo '<div class="gw-actions"><button type="submit" class="button">' . esc_html__( 'Send request', 'greenworld-core' ) . '</button></div>';
		echo '</form>';

		$requests = GWC_Records::user_requests( $uid, 8 );
		if ( ! empty( $requests ) ) {
			echo '<ul class="gw-list" style="margin-top:1rem;">';
			foreach ( $requests as $r ) {
				$type    = (string) get_post_meta( $r->ID, '_gw_r_type', true );
				$product = (string) get_post_meta( $r->ID, '_gw_r_product', true );
				$status  = (string) get_post_meta( $r->ID, '_gw_r_status', true );
				$status  = '' !== $status ? $status : 'open';
				$pill    = ( 'handled' === $status ) ? ' gw-pill--handled' : '';
				echo '<li>';
				echo '<span class="gw-pill' . esc_attr( $pill ) . '">' . esc_html( $status ) . '</span> ';
				echo '<strong>' . esc_html( $type ) . '</strong>';
				if ( '' !== $product ) {
					echo ' — ' . esc_html( $product );
				}
				echo '<div class="gw-meta">' . esc_html( get_the_date( '', $r ) . ' ' . get_the_time( '', $r ) ) . '</div>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private function section_checkin( int $uid ): void {
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'How are you doing?', 'greenworld-core' ) . '</h3>';
		echo '<p class="gw-sub">' . esc_html__( 'Let us know how you are getting on with your products. This is not a medical diagnosis — it just helps our team support you.', 'greenworld-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="gwc_checkin" />';
		wp_nonce_field( 'gwc_health', 'gwc_health_nonce' );
		echo '<div class="gw-grid2">';
		echo '<div>';
		echo '<label for="gw_status">' . esc_html__( 'Overall', 'greenworld-core' ) . '</label>';
		echo '<select id="gw_status" name="gw_status">';
		$statuses = array(
			'Doing well'    => __( 'Doing well', 'greenworld-core' ),
			'Managing okay' => __( 'Managing okay', 'greenworld-core' ),
			'Need help'     => __( 'Need help', 'greenworld-core' ),
		);
		foreach ( $statuses as $val => $lab ) {
			echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '<div>';
		$products = $this->purchased_products( $uid );
		echo '<label for="gw_ck_product">' . esc_html__( 'Related product (optional)', 'greenworld-core' ) . '</label>';
		if ( empty( $products ) ) {
			echo '<input type="text" id="gw_ck_product" name="gw_ck_product" />';
		} else {
			echo '<select id="gw_ck_product" name="gw_ck_product">';
			echo '<option value="">' . esc_html__( '— none —', 'greenworld-core' ) . '</option>';
			foreach ( $products as $name ) {
				echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</option>';
			}
			echo '</select>';
		}
		echo '</div>';
		echo '</div>';
		echo '<label for="gw_note_ck">' . esc_html__( 'Note (optional)', 'greenworld-core' ) . '</label>';
		echo '<textarea id="gw_note_ck" name="gw_note" placeholder="' . esc_attr__( 'How have things been?', 'greenworld-core' ) . '"></textarea>';
		echo '<div class="gw-actions"><button type="submit" class="button">' . esc_html__( 'Save check-in', 'greenworld-core' ) . '</button></div>';
		echo '</form>';

		$checkins = GWC_Records::user_checkins( $uid, 6 );
		if ( ! empty( $checkins ) ) {
			echo '<ul class="gw-list" style="margin-top:1rem;">';
			foreach ( $checkins as $c ) {
				$status  = (string) get_post_meta( $c->ID, '_gw_ck_status', true );
				$product = (string) get_post_meta( $c->ID, '_gw_ck_product', true );
				$note    = (string) get_post_meta( $c->ID, '_gw_ck_note', true );
				echo '<li>';
				echo '<span class="gw-pill">' . esc_html( $status ) . '</span> ';
				if ( '' !== $product ) {
					echo '<strong>' . esc_html( $product ) . '</strong> ';
				}
				if ( '' !== $note ) {
					echo esc_html( $note );
				}
				echo '<div class="gw-meta">' . esc_html( get_the_date( '', $c ) . ' ' . get_the_time( '', $c ) ) . '</div>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private function section_thread( int $uid ): void {
		echo '<div class="gw-card" id="gw-thread">';
		echo '<h3>' . esc_html__( 'Message our team', 'greenworld-core' ) . '</h3>';
		echo '<p class="gw-sub">' . esc_html__( 'Ask a question or share an update. Replies appear here and are emailed to you.', 'greenworld-core' ) . '</p>';

		$comments = GWC_Records::thread_comments( $uid );
		if ( ! empty( $comments ) ) {
			echo '<div class="gw-thread">';
			foreach ( $comments as $cm ) {
				$is_you = ( (int) $cm->user_id === $uid && $uid > 0 );
				$who    = $is_you ? __( 'You', 'greenworld-core' ) : __( 'Green World team', 'greenworld-core' );
				$cls    = $is_you ? 'gw-msg--you' : 'gw-msg--team';
				echo '<div class="gw-msg ' . esc_attr( $cls ) . '">';
				echo esc_html( (string) $cm->comment_content );
				echo '<time>' . esc_html( $who . ' · ' . mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cm->comment_date ) ) . '</time>';
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<p class="gw-sub">' . esc_html__( 'No messages yet. Start the conversation below.', 'greenworld-core' ) . '</p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="gwc_message" />';
		wp_nonce_field( 'gwc_health', 'gwc_health_nonce' );
		echo '<label for="gw_message">' . esc_html__( 'Your message', 'greenworld-core' ) . '</label>';
		echo '<textarea id="gw_message" name="gw_message" required></textarea>';
		echo '<div class="gw-actions"><button type="submit" class="button">' . esc_html__( 'Send message', 'greenworld-core' ) . '</button></div>';
		echo '</form>';
		echo '</div>';
	}
}
