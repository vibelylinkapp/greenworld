<?php
/**
 * Distributor points: product point values, batch allocation, and the ledger.
 *
 * Phase 4 of the distributor programme. Each product carries a point value; an
 * admin allocates a "batch" of products (plus an optional manual adjustment) to
 * a distributor, and the points accrue to that distributor's balance. Every
 * allocation is stored as a private gw_batch record so the balance is fully
 * auditable, and the distributor sees the running history on their dashboard.
 *
 * Balance is cached in the user meta _gw_points_balance (shared with
 * GWC_Distributor) but always recomputed from the ledger on each change, so it
 * can never drift.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Points {

	private static $instance = null;

	public const CPT = 'gw_batch';

	private const M_USER   = '_gw_b_user';
	private const M_POINTS = '_gw_b_points';
	private const M_ITEMS  = '_gw_b_items';
	private const M_ADJUST = '_gw_b_adjust';
	private const M_NOTE   = '_gw_b_note';
	private const M_BY     = '_gw_b_by';

	public const PRODUCT_META = '_gw_points';
	public const BAL_META     = '_gw_points_balance';

	public static function instance(): GWC_Points {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Per-product point value on the WooCommerce product editor.
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );

		// Admin: allocation screen + batch list columns + detail box.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_gwc_allocate_batch', array( $this, 'handle_allocate' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
	}

	public function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Point Batches', 'greenworld-core' ),
					'singular_name' => __( 'Point Batch', 'greenworld-core' ),
					'menu_name'     => __( 'Point Batches', 'greenworld-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'users.php',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'hierarchical'    => false,
			)
		);
	}

	/* ================================================================== *
	 * Static data helpers (used by the dashboard + internally).
	 * ================================================================== */

	public static function product_points( int $product_id ): int {
		return (int) get_post_meta( $product_id, self::PRODUCT_META, true );
	}

	/**
	 * @return array<int,\WP_Post>
	 */
	public static function user_batches( int $uid, int $limit = 10 ): array {
		if ( $uid <= 0 ) {
			return array();
		}
		return (array) get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => $limit,
				'meta_key'    => self::M_USER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => (string) $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
	}

	public static function items_summary( int $post_id ): string {
		$items = get_post_meta( $post_id, self::M_ITEMS, true );
		if ( ! is_array( $items ) || empty( $items ) ) {
			$adj = (int) get_post_meta( $post_id, self::M_ADJUST, true );
			return 0 !== $adj ? __( 'Manual adjustment', 'greenworld-core' ) : '';
		}
		$parts = array();
		foreach ( $items as $it ) {
			$name = isset( $it['name'] ) ? (string) $it['name'] : '';
			$qty  = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
			if ( '' !== $name ) {
				$parts[] = $name . ' x' . $qty;
			}
		}
		return implode( ', ', $parts );
	}

	public static function recompute_balance( int $uid ): int {
		$ids = (array) get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::M_USER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => (string) $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$sum = 0;
		foreach ( $ids as $bid ) {
			$sum += (int) get_post_meta( (int) $bid, self::M_POINTS, true );
		}
		update_user_meta( $uid, self::BAL_META, $sum );
		return $sum;
	}

	/**
	 * Create a batch record and refresh the distributor's cached balance.
	 *
	 * @param array<int,array{id:int,qty:int}> $lines
	 * @return int The new batch post ID, or 0 on failure.
	 */
	public static function add_batch( int $uid, array $lines, int $adjust, string $note, int $by ): int {
		$total = $adjust;
		$items = array();
		foreach ( $lines as $ln ) {
			$pid = isset( $ln['id'] ) ? (int) $ln['id'] : 0;
			$qty = isset( $ln['qty'] ) ? (int) $ln['qty'] : 0;
			if ( $pid <= 0 || $qty <= 0 ) {
				continue;
			}
			$unit  = self::product_points( $pid );
			$line  = $unit * $qty;
			$total = $total + $line;
			$items[] = array(
				'id'   => $pid,
				'name' => (string) get_the_title( $pid ),
				'qty'  => $qty,
				'unit' => $unit,
				'line' => $line,
			);
		}

		$user  = get_userdata( $uid );
		$who   = $user instanceof \WP_User ? $user->display_name : ( '#' . $uid );
		$title = sprintf(
			/* translators: 1: distributor name, 2: date */
			__( 'Batch for %1$s - %2$s', 'greenworld-core' ),
			$who,
			date_i18n( (string) get_option( 'date_format' ) )
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}

		update_post_meta( (int) $post_id, self::M_USER, $uid );
		update_post_meta( (int) $post_id, self::M_POINTS, (int) $total );
		update_post_meta( (int) $post_id, self::M_ITEMS, $items );
		update_post_meta( (int) $post_id, self::M_ADJUST, (int) $adjust );
		if ( '' !== $note ) {
			update_post_meta( (int) $post_id, self::M_NOTE, $note );
		}
		if ( $by > 0 ) {
			update_post_meta( (int) $post_id, self::M_BY, $by );
		}

		self::recompute_balance( $uid );

		/**
		 * Fires after a point batch is posted for a distributor.
		 *
		 * @param int $uid     Distributor user ID.
		 * @param int $post_id Batch post ID.
		 * @param int $total   Points awarded in this batch.
		 */
		do_action( 'gwc_points_batch_added', $uid, (int) $post_id, (int) $total );

		return (int) $post_id;
	}

	/* ================================================================== *
	 * WooCommerce product point-value field.
	 * ================================================================== */

	public function product_field(): void {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			return;
		}
		woocommerce_wp_text_input(
			array(
				'id'                => self::PRODUCT_META,
				'label'             => __( 'Distributor points', 'greenworld-core' ),
				'description'       => __( 'Points a distributor earns per unit of this product when it is allocated in a batch.', 'greenworld-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0',
				),
			)
		);
	}

	public function save_product_field( int $post_id ): void {
		// WooCommerce verifies the product-save nonce before this fires.
		$val = isset( $_POST[ self::PRODUCT_META ] ) ? (int) wp_unslash( $_POST[ self::PRODUCT_META ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $post_id, self::PRODUCT_META, max( 0, $val ) );
	}

	/* ================================================================== *
	 * Admin: allocate a batch (Users -> Allocate Batch).
	 * ================================================================== */

	public function admin_menu(): void {
		add_users_page(
			__( 'Allocate Batch', 'greenworld-core' ),
			__( 'Allocate Batch', 'greenworld-core' ),
			'edit_users',
			'gwc-allocate-batch',
			array( $this, 'render_allocate' )
		);
	}

	public function render_allocate(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to allocate batches.', 'greenworld-core' ) );
		}

		$notice = isset( $_GET['gwb'] ) ? sanitize_key( (string) wp_unslash( $_GET['gwb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bal    = isset( $_GET['bal'] ) ? (int) $_GET['bal'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pts    = isset( $_GET['pts'] ) ? (int) $_GET['pts'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$dists = get_users(
			array(
				'role'    => GWC_Distributor::ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 500,
			)
		);

		$products = function_exists( 'wc_get_products' )
			? wc_get_products(
				array(
					'limit'   => 300,
					'status'  => 'publish',
					'orderby' => 'title',
					'order'   => 'ASC',
				)
			)
			: array();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Allocate a point batch', 'greenworld-core' ) . '</h1>';

		if ( 'ok' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Batch allocated: %1$s points added. New balance: %2$s points.', 'greenworld-core' ), number_format_i18n( $pts ), number_format_i18n( $bal ) ) ) . '</p></div>';
		} elseif ( 'error' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'That batch could not be allocated. Choose a distributor and at least one product (or a manual adjustment), then try again.', 'greenworld-core' ) . '</p></div>';
		}

		if ( empty( $dists ) ) {
			echo '<p>' . esc_html__( 'There are no distributors yet. Distributors appear here after they register and are activated under Users -> Distributors.', 'greenworld-core' ) . '</p></div>';
			return;
		}

		$opts = '<option value="">' . esc_html__( '- choose product -', 'greenworld-core' ) . '</option>';
		foreach ( $products as $p ) {
			if ( ! is_object( $p ) || ! method_exists( $p, 'get_id' ) ) {
				continue;
			}
			$pid   = (int) $p->get_id();
			$ppts  = self::product_points( $pid );
			$opts .= '<option value="' . esc_attr( (string) $pid ) . '">' . esc_html( $p->get_name() . ' (' . $ppts . ' pts)' ) . '</option>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="gwc_allocate_batch" />';
		wp_nonce_field( 'gwc_allocate_batch' );

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="gw_user">' . esc_html__( 'Distributor', 'greenworld-core' ) . '</label></th><td>';
		echo '<select id="gw_user" name="gw_user" required>';
		echo '<option value="">' . esc_html__( '- choose distributor -', 'greenworld-core' ) . '</option>';
		foreach ( $dists as $d ) {
			$status  = GWC_Distributor::status( (int) $d->ID );
			$balance = (int) get_user_meta( (int) $d->ID, self::BAL_META, true );
			$label   = $d->display_name . ' (' . $status . ', ' . $balance . ' pts)';
			echo '<option value="' . esc_attr( (string) $d->ID ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Products in this batch', 'greenworld-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Choose products and quantities. Points are the product point value times quantity. Leave rows blank if not needed.', 'greenworld-core' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>' . esc_html__( 'Product', 'greenworld-core' ) . '</th><th style="width:120px">' . esc_html__( 'Quantity', 'greenworld-core' ) . '</th></tr></thead><tbody>';
		for ( $i = 0; $i < 8; $i++ ) {
			echo '<tr>';
			echo '<td><select name="gw_product[]" style="width:100%">' . $opts . '</select></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $opts is built with esc_html/esc_attr.
			echo '<td><input type="number" name="gw_qty[]" min="0" step="1" style="width:100px" /></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="gw_adjust">' . esc_html__( 'Manual adjustment (+/-)', 'greenworld-core' ) . '</label></th><td>';
		echo '<input type="number" id="gw_adjust" name="gw_adjust" step="1" value="0" style="width:120px" /> ';
		echo '<span class="description">' . esc_html__( 'Optional. Add a bonus or correct the balance (may be negative).', 'greenworld-core' ) . '</span>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="gw_note">' . esc_html__( 'Note', 'greenworld-core' ) . '</label></th><td>';
		echo '<input type="text" id="gw_note" name="gw_note" class="regular-text" placeholder="' . esc_attr__( 'e.g. September stock allocation', 'greenworld-core' ) . '" />';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Notify distributor', 'greenworld-core' ) . '</th><td>';
		echo '<label><input type="checkbox" name="gw_notify" value="1" checked /> ' . esc_html__( 'Send a WhatsApp (if configured) and email letting them know they earned points.', 'greenworld-core' ) . '</label>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Allocate batch', 'greenworld-core' ) );
		echo '</form>';
		echo '</div>';
	}

	public function handle_allocate(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'greenworld-core' ) );
		}
		check_admin_referer( 'gwc_allocate_batch' );

		$back   = admin_url( 'users.php?page=gwc-allocate-batch' );
		$uid    = isset( $_POST['gw_user'] ) ? (int) $_POST['gw_user'] : 0;
		$prods  = ( isset( $_POST['gw_product'] ) && is_array( $_POST['gw_product'] ) ) ? array_map( 'intval', (array) wp_unslash( $_POST['gw_product'] ) ) : array();
		$qtys   = ( isset( $_POST['gw_qty'] ) && is_array( $_POST['gw_qty'] ) ) ? array_map( 'intval', (array) wp_unslash( $_POST['gw_qty'] ) ) : array();
		$adjust = isset( $_POST['gw_adjust'] ) ? (int) wp_unslash( $_POST['gw_adjust'] ) : 0;
		$note   = isset( $_POST['gw_note'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gw_note'] ) ) : '';
		$notify = isset( $_POST['gw_notify'] );

		$lines = array();
		$count = count( $prods );
		for ( $i = 0; $i < $count; $i++ ) {
			$pid = (int) $prods[ $i ];
			$qty = isset( $qtys[ $i ] ) ? (int) $qtys[ $i ] : 0;
			if ( $pid > 0 && $qty > 0 ) {
				$lines[] = array(
					'id'  => $pid,
					'qty' => $qty,
				);
			}
		}

		if ( $uid <= 0 || ! GWC_Distributor::is_distributor( $uid ) || ( empty( $lines ) && 0 === $adjust ) ) {
			wp_safe_redirect( add_query_arg( 'gwb', 'error', $back ) );
			exit;
		}

		$post_id = self::add_batch( $uid, $lines, $adjust, $note, (int) get_current_user_id() );
		if ( 0 === $post_id ) {
			wp_safe_redirect( add_query_arg( 'gwb', 'error', $back ) );
			exit;
		}

		$earned  = (int) get_post_meta( $post_id, self::M_POINTS, true );
		$balance = (int) get_user_meta( $uid, self::BAL_META, true );
		if ( $notify && 0 !== $earned ) {
			$this->notify_points( $uid, $earned, $balance );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'gwb' => 'ok',
					'pts' => $earned,
					'bal' => $balance,
				),
				$back
			)
		);
		exit;
	}

	private function notify_points( int $uid, int $earned, int $balance ): void {
		$user = get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$message = sprintf(
			/* translators: 1: name, 2: points earned, 3: new balance */
			__( 'Hello %1$s, your Green World distributor account earned %2$s points. Your new balance is %3$s points.', 'greenworld-core' ),
			$user->display_name,
			number_format_i18n( $earned ),
			number_format_i18n( $balance )
		);
		$phone = (string) get_user_meta( $uid, '_gw_phone', true );
		if ( '' !== $phone && class_exists( 'GWC_WhatsApp' ) ) {
			GWC_WhatsApp::send_text( $phone, $message );
		}
		wp_mail(
			$user->user_email,
			__( 'You earned Green World points', 'greenworld-core' ),
			$message
		);
	}

	/* ================================================================== *
	 * Admin: batch list columns + read-only detail box.
	 * ================================================================== */

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function columns( $cols ): array {
		$new = array();
		foreach ( (array) $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gw_b_dist']   = __( 'Distributor', 'greenworld-core' );
				$new['gw_b_points'] = __( 'Points', 'greenworld-core' );
				$new['gw_b_items']  = __( 'Items', 'greenworld-core' );
			}
		}
		return $new;
	}

	public function column( $col, $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'gw_b_dist' === $col ) {
			$uid  = (int) get_post_meta( $post_id, self::M_USER, true );
			$user = get_userdata( $uid );
			echo esc_html( $user instanceof \WP_User ? $user->display_name : ( '#' . $uid ) );
		} elseif ( 'gw_b_points' === $col ) {
			echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, self::M_POINTS, true ) ) );
		} elseif ( 'gw_b_items' === $col ) {
			echo esc_html( self::items_summary( $post_id ) );
		}
	}

	public function metabox(): void {
		add_meta_box( 'gwc_batch_detail', __( 'Batch breakdown', 'greenworld-core' ), array( $this, 'render_box' ), self::CPT, 'normal', 'high' );
	}

	public function render_box( \WP_Post $post ): void {
		$uid    = (int) get_post_meta( $post->ID, self::M_USER, true );
		$user   = get_userdata( $uid );
		$items  = get_post_meta( $post->ID, self::M_ITEMS, true );
		$adjust = (int) get_post_meta( $post->ID, self::M_ADJUST, true );
		$total  = (int) get_post_meta( $post->ID, self::M_POINTS, true );
		$note   = (string) get_post_meta( $post->ID, self::M_NOTE, true );

		echo '<p><strong>' . esc_html__( 'Distributor:', 'greenworld-core' ) . '</strong> ';
		if ( $user instanceof \WP_User ) {
			echo '<a href="' . esc_url( (string) get_edit_user_link( $uid ) ) . '">' . esc_html( $user->display_name ) . '</a>';
		} else {
			echo esc_html( '#' . $uid );
		}
		echo '</p>';

		echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th>' . esc_html__( 'Product', 'greenworld-core' ) . '</th><th>' . esc_html__( 'Qty', 'greenworld-core' ) . '</th><th>' . esc_html__( 'Unit pts', 'greenworld-core' ) . '</th><th>' . esc_html__( 'Line pts', 'greenworld-core' ) . '</th></tr></thead><tbody>';
		if ( is_array( $items ) && ! empty( $items ) ) {
			foreach ( $items as $it ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $it['name'] ) ? (string) $it['name'] : '' ) . '</td>';
				echo '<td>' . esc_html( (string) ( isset( $it['qty'] ) ? (int) $it['qty'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( isset( $it['unit'] ) ? (int) $it['unit'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( isset( $it['line'] ) ? (int) $it['line'] : 0 ) ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="4">' . esc_html__( 'No product lines.', 'greenworld-core' ) . '</td></tr>';
		}
		if ( 0 !== $adjust ) {
			echo '<tr><td colspan="3"><em>' . esc_html__( 'Manual adjustment', 'greenworld-core' ) . '</em></td><td>' . esc_html( (string) $adjust ) . '</td></tr>';
		}
		echo '<tr><td colspan="3"><strong>' . esc_html__( 'Total', 'greenworld-core' ) . '</strong></td><td><strong>' . esc_html( (string) $total ) . '</strong></td></tr>';
		echo '</tbody></table>';

		if ( '' !== $note ) {
			echo '<p><strong>' . esc_html__( 'Note:', 'greenworld-core' ) . '</strong> ' . esc_html( $note ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Batches are created from Users -> Allocate Batch. This view is read-only; the distributor balance is the sum of all their batch totals.', 'greenworld-core' ) . '</p>';
	}
}
