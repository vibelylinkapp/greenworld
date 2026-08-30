<?php
/**
 * Customer dashboard data: refill/change requests, progress check-ins, and the
 * customer<->staff message thread. Each type is a non-public post type so the
 * records never appear on the front end, but staff manage them in wp-admin and
 * the customer sees their own via the My Account "My Health" tab.
 *
 * All records are stored with post_status "publish" (queryable by our own
 * meta-filtered lookups) on a non-public post type (no front-end URL), which
 * avoids the read_private_posts capability trap for logged-in customers.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Records {

	private static $instance = null;

	public const REQUEST = 'gw_request';
	public const CHECKIN = 'gw_checkin';
	public const THREAD  = 'gw_thread';

	public static function instance(): GWC_Records {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'register_cpts' ) );

		add_filter( 'manage_' . self::REQUEST . '_posts_columns', array( $this, 'request_columns' ) );
		add_action( 'manage_' . self::REQUEST . '_posts_custom_column', array( $this, 'request_column' ), 10, 2 );
		add_filter( 'manage_' . self::CHECKIN . '_posts_columns', array( $this, 'checkin_columns' ) );
		add_action( 'manage_' . self::CHECKIN . '_posts_custom_column', array( $this, 'checkin_column' ), 10, 2 );

		add_action( 'add_meta_boxes', array( $this, 'metaboxes' ) );
		add_action( 'save_post_' . self::REQUEST, array( $this, 'save_request_status' ) );

		add_action( 'wp_insert_comment', array( $this, 'on_comment' ), 10, 2 );
	}

	public function register_cpts(): void {
		$common = array(
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
		);

		register_post_type(
			self::REQUEST,
			array_merge(
				$common,
				array(
					'labels'    => array(
						'name'          => __( 'Refill Requests', 'greenworld-core' ),
						'singular_name' => __( 'Refill Request', 'greenworld-core' ),
						'menu_name'     => __( 'Refill Requests', 'greenworld-core' ),
					),
					'menu_icon' => 'dashicons-update',
					'supports'  => array( 'title' ),
				)
			)
		);

		register_post_type(
			self::CHECKIN,
			array_merge(
				$common,
				array(
					'labels'    => array(
						'name'          => __( 'Check-ins', 'greenworld-core' ),
						'singular_name' => __( 'Check-in', 'greenworld-core' ),
						'menu_name'     => __( 'Check-ins', 'greenworld-core' ),
					),
					'menu_icon' => 'dashicons-heart',
					'supports'  => array( 'title' ),
				)
			)
		);

		register_post_type(
			self::THREAD,
			array_merge(
				$common,
				array(
					'labels'    => array(
						'name'          => __( 'Customer Messages', 'greenworld-core' ),
						'singular_name' => __( 'Customer Thread', 'greenworld-core' ),
						'menu_name'     => __( 'Customer Messages', 'greenworld-core' ),
					),
					'menu_icon' => 'dashicons-email',
					'supports'  => array( 'title', 'comments' ),
				)
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Create helpers (called from the front-end handlers).
	 * ------------------------------------------------------------------ */

	private static function display_name( int $user_id ): string {
		$u = get_userdata( $user_id );
		return $u ? $u->display_name : ( '#' . $user_id );
	}

	private static function notify( string $subject, string $message ): void {
		GWC_WhatsApp::notify_staff( $message );
		$to = get_option( 'admin_email' );
		if ( is_email( (string) $to ) ) {
			wp_mail( $to, $subject, $message );
		}
	}

	public static function add_request( int $user_id, string $product, string $type, string $note ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::REQUEST,
				'post_status' => 'publish',
				'post_author' => $user_id,
				'post_title'  => sprintf( '%s — %s', self::display_name( $user_id ), current_time( 'Y-m-d H:i' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}
		$id = (int) $post_id;
		update_post_meta( $id, '_gw_r_user', (string) $user_id );
		update_post_meta( $id, '_gw_r_product', $product );
		update_post_meta( $id, '_gw_r_type', $type );
		update_post_meta( $id, '_gw_r_note', $note );
		update_post_meta( $id, '_gw_r_status', 'open' );

		$msg = sprintf(
			"New refill/change request from %s:\nType: %s\nProduct: %s\nNote: %s",
			self::display_name( $user_id ),
			'' !== $type ? $type : '-',
			'' !== $product ? $product : '-',
			'' !== $note ? $note : '-'
		);
		self::notify( __( 'New refill/change request', 'greenworld-core' ), $msg );
		return $id;
	}

	public static function add_checkin( int $user_id, string $status, string $product, string $note ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::CHECKIN,
				'post_status' => 'publish',
				'post_author' => $user_id,
				'post_title'  => sprintf( '%s — %s', self::display_name( $user_id ), current_time( 'Y-m-d H:i' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}
		$id = (int) $post_id;
		update_post_meta( $id, '_gw_ck_user', (string) $user_id );
		update_post_meta( $id, '_gw_ck_status', $status );
		update_post_meta( $id, '_gw_ck_product', $product );
		update_post_meta( $id, '_gw_ck_note', $note );

		$msg = sprintf(
			"New progress check-in from %s:\nHow they are doing: %s\nProduct: %s\nNote: %s",
			self::display_name( $user_id ),
			'' !== $status ? $status : '-',
			'' !== $product ? $product : '-',
			'' !== $note ? $note : '-'
		);
		self::notify( __( 'New progress check-in', 'greenworld-core' ), $msg );
		return $id;
	}

	public static function thread_id_for( int $user_id, bool $create = true ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'   => self::THREAD,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_gw_t_user',
				'meta_value'  => (string) $user_id,
			)
		);
		if ( ! empty( $found ) ) {
			return (int) $found[0];
		}
		if ( ! $create ) {
			return 0;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'      => self::THREAD,
				'post_status'    => 'publish',
				'post_author'    => $user_id,
				'post_title'     => sprintf( '%s (#%d)', self::display_name( $user_id ), $user_id ),
				'comment_status' => 'open',
			),
			true
		);
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return 0;
		}
		update_post_meta( (int) $post_id, '_gw_t_user', (string) $user_id );
		return (int) $post_id;
	}

	public static function add_message( int $user_id, string $body ): int {
		$body = trim( $body );
		if ( $user_id <= 0 || '' === $body ) {
			return 0;
		}
		$thread = self::thread_id_for( $user_id, true );
		if ( $thread <= 0 ) {
			return 0;
		}
		$u   = get_userdata( $user_id );
		$cid = wp_insert_comment(
			array(
				'comment_post_ID'      => $thread,
				'comment_content'      => $body,
				'user_id'              => $user_id,
				'comment_author'       => $u ? $u->display_name : __( 'Customer', 'greenworld-core' ),
				'comment_author_email' => $u ? $u->user_email : '',
				'comment_approved'     => 1,
			)
		);
		return (int) $cid;
	}

	/* ------------------------------------------------------------------ *
	 * Read helpers (front-end rendering).
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<int,\WP_Post>
	 */
	public static function user_requests( int $user_id, int $limit = 10 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'   => self::REQUEST,
				'post_status' => 'publish',
				'numberposts' => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_key'    => '_gw_r_user',
				'meta_value'  => (string) $user_id,
			)
		);
	}

	/**
	 * @return array<int,\WP_Post>
	 */
	public static function user_checkins( int $user_id, int $limit = 10 ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'   => self::CHECKIN,
				'post_status' => 'publish',
				'numberposts' => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_key'    => '_gw_ck_user',
				'meta_value'  => (string) $user_id,
			)
		);
	}

	/**
	 * @return array<int,\WP_Comment>
	 */
	public static function thread_comments( int $user_id ): array {
		$tid = self::thread_id_for( $user_id, false );
		if ( $tid <= 0 ) {
			return array();
		}
		return get_comments(
			array(
				'post_id' => $tid,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Notifications on new thread comments (both directions).
	 * ------------------------------------------------------------------ */

	/**
	 * @param int         $id      Comment ID.
	 * @param \WP_Comment $comment Comment object.
	 */
	public function on_comment( $id, $comment ): void {
		if ( ! is_object( $comment ) ) {
			return;
		}
		$post_id = (int) $comment->comment_post_ID;
		if ( get_post_type( $post_id ) !== self::THREAD ) {
			return;
		}
		$thread_user = (int) get_post_meta( $post_id, '_gw_t_user', true );
		$author_id   = (int) $comment->user_id;
		$body        = (string) $comment->comment_content;

		if ( $thread_user > 0 && $author_id === $thread_user ) {
			// Customer wrote -> alert staff.
			$msg = sprintf(
				"New customer message from %s:\n%s",
				self::display_name( $thread_user ),
				$body
			);
			self::notify( __( 'New customer message', 'greenworld-core' ), $msg );
		} else {
			// Staff replied -> email the customer.
			$u = get_userdata( $thread_user );
			if ( $u && is_email( (string) $u->user_email ) ) {
				wp_mail(
					$u->user_email,
					__( 'Reply from Green World Health Solutions', 'greenworld-core' ),
					sprintf( "You have a new reply from our team:\n\n%s\n\nView the conversation in your account under \"My Health\".", $body )
				);
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Admin: columns + detail meta boxes.
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function request_columns( $cols ): array {
		$new = array();
		foreach ( (array) $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['gw_type']    = __( 'Type', 'greenworld-core' );
				$new['gw_product'] = __( 'Product', 'greenworld-core' );
				$new['gw_status']  = __( 'Status', 'greenworld-core' );
			}
		}
		return $new;
	}

	public function request_column( string $col, int $post_id ): void {
		if ( 'gw_type' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_r_type', true ) );
		} elseif ( 'gw_product' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_r_product', true ) );
		} elseif ( 'gw_status' === $col ) {
			$s = (string) get_post_meta( $post_id, '_gw_r_status', true );
			echo esc_html( '' !== $s ? $s : 'open' );
		}
	}

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function checkin_columns( $cols ): array {
		$new = array();
		foreach ( (array) $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['gw_ck_status']  = __( 'How they are doing', 'greenworld-core' );
				$new['gw_ck_product'] = __( 'Product', 'greenworld-core' );
			}
		}
		return $new;
	}

	public function checkin_column( string $col, int $post_id ): void {
		if ( 'gw_ck_status' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_ck_status', true ) );
		} elseif ( 'gw_ck_product' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_ck_product', true ) );
		}
	}

	public function metaboxes(): void {
		add_meta_box( 'gwc_request_details', __( 'Request details', 'greenworld-core' ), array( $this, 'render_request_box' ), self::REQUEST, 'normal', 'high' );
		add_meta_box( 'gwc_checkin_details', __( 'Check-in details', 'greenworld-core' ), array( $this, 'render_checkin_box' ), self::CHECKIN, 'normal', 'high' );
	}

	private function customer_link( int $user_id ): string {
		$name = self::display_name( $user_id );
		if ( $user_id > 0 ) {
			$u = get_userdata( $user_id );
			if ( $u ) {
				return $name . ' (' . $u->user_email . ')';
			}
		}
		return $name;
	}

	public function render_request_box( \WP_Post $post ): void {
		$uid    = (int) get_post_meta( $post->ID, '_gw_r_user', true );
		$status = (string) get_post_meta( $post->ID, '_gw_r_status', true );
		$status = '' !== $status ? $status : 'open';
		$rows   = array(
			__( 'Customer', 'greenworld-core' ) => $this->customer_link( $uid ),
			__( 'Type', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_r_type', true ),
			__( 'Product', 'greenworld-core' )  => get_post_meta( $post->ID, '_gw_r_product', true ),
			__( 'Note', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_r_note', true ),
		);
		echo '<table class="widefat striped">';
		foreach ( $rows as $label => $val ) {
			echo '<tr><th style="width:180px;">' . esc_html( (string) $label ) . '</th><td>' . esc_html( (string) $val ) . '</td></tr>';
		}
		echo '</table>';
		wp_nonce_field( 'gwc_request_status', 'gwc_request_status_nonce' );
		echo '<p style="margin-top:12px;"><label><strong>' . esc_html__( 'Status', 'greenworld-core' ) . '</strong> ';
		echo '<select name="gwc_request_status">';
		foreach ( array( 'open' => __( 'Open', 'greenworld-core' ), 'handled' => __( 'Handled', 'greenworld-core' ) ) as $val => $lab ) {
			echo '<option value="' . esc_attr( $val ) . '" ' . selected( $status, $val, false ) . '>' . esc_html( $lab ) . '</option>';
		}
		echo '</select></label></p>';
	}

	public function save_request_status( int $post_id ): void {
		if ( ! isset( $_POST['gwc_request_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gwc_request_status_nonce'] ) ), 'gwc_request_status' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$val = isset( $_POST['gwc_request_status'] ) ? sanitize_key( (string) wp_unslash( $_POST['gwc_request_status'] ) ) : 'open';
		update_post_meta( $post_id, '_gw_r_status', 'handled' === $val ? 'handled' : 'open' );
	}

	public function render_checkin_box( \WP_Post $post ): void {
		$uid  = (int) get_post_meta( $post->ID, '_gw_ck_user', true );
		$rows = array(
			__( 'Customer', 'greenworld-core' )           => $this->customer_link( $uid ),
			__( 'How they are doing', 'greenworld-core' ) => get_post_meta( $post->ID, '_gw_ck_status', true ),
			__( 'Product', 'greenworld-core' )            => get_post_meta( $post->ID, '_gw_ck_product', true ),
			__( 'Note', 'greenworld-core' )               => get_post_meta( $post->ID, '_gw_ck_note', true ),
		);
		echo '<table class="widefat striped">';
		foreach ( $rows as $label => $val ) {
			echo '<tr><th style="width:180px;">' . esc_html( (string) $label ) . '</th><td>' . esc_html( (string) $val ) . '</td></tr>';
		}
		echo '</table>';
	}
}
