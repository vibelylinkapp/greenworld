<?php
/**
 * Consultation case-management (Phase 4).
 *
 * The theme registers the `gw_consultation` post type and stores each
 * customer's submitted details (_gw_c_name / _gw_c_email / _gw_c_phone /
 * _gw_c_concern / ...). This module turns every consultation into a workable
 * case without duplicating any data: a case number, a status pipeline
 * (New -> Assigned -> Contacted -> Waiting for customer -> Advice provided ->
 * Follow-up -> Closed), a priority, an assigned advisor, advisor notes, a
 * resolution, and an audit history. It can optionally send the customer a
 * short, compliant status update on WhatsApp and email when a case moves.
 *
 * It does NOT register the post type (the theme owns that) - it enhances it,
 * which is why every hook targets the `gw_consultation` type by name.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Cases {

	private static $instance = null;

	public const CPT = 'gw_consultation';

	private const M_NUMBER     = '_gw_case_number';
	private const M_STATUS     = '_gw_case_status';
	private const M_PRIORITY   = '_gw_case_priority';
	private const M_ASSIGNEE   = '_gw_case_assignee';
	private const M_NOTES      = '_gw_case_notes';
	private const M_RESOLUTION = '_gw_case_resolution';
	private const M_HISTORY    = '_gw_case_history';
	private const NONCE        = 'gwc_case_meta';

	public static function instance(): GWC_Cases {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'metabox' ), 20 );
		add_action( 'save_post_' . self::CPT, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'status_filter' ) );
		add_filter( 'parse_query', array( $this, 'apply_filter' ) );
	}

	/* ================================================================== *
	 * Static data + helpers (also used by Customer 360).
	 * ================================================================== */

	/**
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			'new'       => __( 'New', 'greenworld-core' ),
			'assigned'  => __( 'Assigned', 'greenworld-core' ),
			'contacted' => __( 'Contacted', 'greenworld-core' ),
			'waiting'   => __( 'Waiting for customer', 'greenworld-core' ),
			'advice'    => __( 'Advice provided', 'greenworld-core' ),
			'followup'  => __( 'Follow-up', 'greenworld-core' ),
			'closed'    => __( 'Closed', 'greenworld-core' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function priorities(): array {
		return array(
			'low'    => __( 'Low', 'greenworld-core' ),
			'normal' => __( 'Normal', 'greenworld-core' ),
			'high'   => __( 'High', 'greenworld-core' ),
			'urgent' => __( 'Urgent', 'greenworld-core' ),
		);
	}

	public static function status_label( string $key ): string {
		$m = self::statuses();
		return isset( $m[ $key ] ) ? $m[ $key ] : $m['new'];
	}

	public static function priority_label( string $key ): string {
		$m = self::priorities();
		return isset( $m[ $key ] ) ? $m[ $key ] : $m['normal'];
	}

	public static function get_status( int $id ): string {
		$s = (string) get_post_meta( $id, self::M_STATUS, true );
		return '' !== $s ? $s : 'new';
	}

	public static function get_priority( int $id ): string {
		$p = (string) get_post_meta( $id, self::M_PRIORITY, true );
		return '' !== $p ? $p : 'normal';
	}

	/**
	 * Return (creating if needed) the human case number, e.g. GW-00042.
	 */
	public static function number( int $id ): string {
		$n = (string) get_post_meta( $id, self::M_NUMBER, true );
		if ( '' === $n ) {
			$n = 'GW-' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT );
			update_post_meta( $id, self::M_NUMBER, $n );
		}
		return $n;
	}

	public static function status_color( string $key ): string {
		switch ( $key ) {
			case 'closed':
				return '#5a6b60';
			case 'advice':
			case 'followup':
				return '#1a7f37';
			case 'contacted':
			case 'assigned':
				return '#1f6f8b';
			case 'waiting':
				return '#996800';
			default:
				return '#b32d2e'; // new = needs attention
		}
	}

	public static function badge( int $id ): string {
		$st = self::get_status( $id );
		return '<span style="display:inline-block;padding:2px 9px;border-radius:10px;background:' . esc_attr( self::status_color( $st ) ) . ';color:#fff;font-size:11px;font-weight:600;line-height:1.6">' . esc_html( self::status_label( $st ) ) . '</span>';
	}

	/**
	 * Consultation posts for a given customer email (used by Customer 360).
	 *
	 * @return array<int,WP_Post>
	 */
	public static function for_email( string $email, int $limit = 20 ): array {
		$email = trim( $email );
		if ( '' === $email ) {
			return array();
		}
		return (array) get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => array( 'private', 'publish', 'pending', 'draft' ),
				'numberposts' => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'     => '_gw_c_email',
						'value'   => $email,
						'compare' => '=',
					),
				),
			)
		);
	}

	/**
	 * Count of open (not closed) consultation cases.
	 */
	public static function open_count(): int {
		$ids = (array) get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => array( 'private', 'publish', 'pending' ),
				'numberposts' => 200,
				'fields'      => 'ids',
			)
		);
		$open = 0;
		foreach ( $ids as $id ) {
			if ( 'closed' !== self::get_status( (int) $id ) ) {
				$open++;
			}
		}
		return $open;
	}

	private static function advisors(): array {
		return (array) get_users(
			array(
				'role__in' => array( 'administrator', 'shop_manager' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 100,
			)
		);
	}

	/* ================================================================== *
	 * Metaboxes.
	 * ================================================================== */

	public function metabox(): void {
		add_meta_box( 'gwc_case', __( 'Case management', 'greenworld-core' ), array( $this, 'render_side' ), self::CPT, 'side', 'high' );
		add_meta_box( 'gwc_case_notes', __( 'Advisor notes & resolution', 'greenworld-core' ), array( $this, 'render_notes' ), self::CPT, 'normal', 'default' );
	}

	public function render_side( WP_Post $post ): void {
		$id       = (int) $post->ID;
		$status   = self::get_status( $id );
		$priority = self::get_priority( $id );
		$assignee = (int) get_post_meta( $id, self::M_ASSIGNEE, true );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		echo '<p><strong>' . esc_html__( 'Case number', 'greenworld-core' ) . ':</strong> <code>' . esc_html( self::number( $id ) ) . '</code></p>';

		echo '<p><label for="gw_case_status"><strong>' . esc_html__( 'Status', 'greenworld-core' ) . '</strong></label><br />';
		echo '<select id="gw_case_status" name="gw_case_status" style="width:100%">';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';

		echo '<p><label for="gw_case_priority"><strong>' . esc_html__( 'Priority', 'greenworld-core' ) . '</strong></label><br />';
		echo '<select id="gw_case_priority" name="gw_case_priority" style="width:100%">';
		foreach ( self::priorities() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $priority, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';

		echo '<p><label for="gw_case_assignee"><strong>' . esc_html__( 'Assigned advisor', 'greenworld-core' ) . '</strong></label><br />';
		echo '<select id="gw_case_assignee" name="gw_case_assignee" style="width:100%">';
		echo '<option value="0">' . esc_html__( '- unassigned -', 'greenworld-core' ) . '</option>';
		foreach ( self::advisors() as $u ) {
			if ( ! $u instanceof WP_User ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $u->ID ) . '" ' . selected( $assignee, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select></p>';

		echo '<p style="margin-top:.6rem"><label><input type="checkbox" name="gw_case_notify" value="1" /> ' . esc_html__( 'Notify the customer of this update (WhatsApp + email)', 'greenworld-core' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Sends a short, general status message only - never advisor notes. WhatsApp requires the Meta Cloud API to be configured.', 'greenworld-core' ) . '</p>';

		// Quick customer context.
		$name  = (string) get_post_meta( $id, '_gw_c_name', true );
		$phone = (string) get_post_meta( $id, '_gw_c_phone', true );
		$email = (string) get_post_meta( $id, '_gw_c_email', true );
		echo '<hr /><p style="margin:.2rem 0"><strong>' . esc_html__( 'Customer', 'greenworld-core' ) . ':</strong> ' . esc_html( '' !== $name ? $name : '-' ) . '</p>';
		if ( '' !== $phone ) {
			echo '<p style="margin:.2rem 0">' . esc_html( $phone ) . '</p>';
		}
		if ( '' !== $email ) {
			$u360 = admin_url( 'users.php?page=gwc-customer-360&search_email=' . rawurlencode( $email ) );
			echo '<p style="margin:.2rem 0">' . esc_html( $email ) . '</p>';
			echo '<p style="margin:.4rem 0"><a class="button button-small" href="' . esc_url( $u360 ) . '">' . esc_html__( 'Open Customer 360', 'greenworld-core' ) . '</a></p>';
		}
	}

	public function render_notes( WP_Post $post ): void {
		$id         = (int) $post->ID;
		$notes      = (string) get_post_meta( $id, self::M_NOTES, true );
		$resolution = (string) get_post_meta( $id, self::M_RESOLUTION, true );

		echo '<p><label for="gw_case_notes"><strong>' . esc_html__( 'Advisor notes (internal)', 'greenworld-core' ) . '</strong></label>';
		echo '<textarea id="gw_case_notes" name="gw_case_notes" rows="4" class="large-text" placeholder="' . esc_attr__( 'Internal notes about this consultation. Not shown to the customer.', 'greenworld-core' ) . '">' . esc_textarea( $notes ) . '</textarea></p>';

		echo '<p><label for="gw_case_resolution"><strong>' . esc_html__( 'Resolution / outcome', 'greenworld-core' ) . '</strong></label>';
		echo '<textarea id="gw_case_resolution" name="gw_case_resolution" rows="3" class="large-text" placeholder="' . esc_attr__( 'How the case was resolved.', 'greenworld-core' ) . '">' . esc_textarea( $resolution ) . '</textarea></p>';

		$history = get_post_meta( $id, self::M_HISTORY, true );
		if ( is_array( $history ) && ! empty( $history ) ) {
			echo '<h4 style="margin:.8rem 0 .3rem">' . esc_html__( 'History', 'greenworld-core' ) . '</h4>';
			echo '<ul style="margin:0;padding:0;list-style:none">';
			foreach ( array_reverse( $history ) as $h ) {
				$when = isset( $h['t'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $h['t'] ) : '';
				$by   = isset( $h['by'] ) ? (int) $h['by'] : 0;
				$who  = $by > 0 ? get_the_author_meta( 'display_name', $by ) : __( 'system', 'greenworld-core' );
				$from = isset( $h['from'] ) ? self::status_label( (string) $h['from'] ) : '';
				$to   = isset( $h['to'] ) ? self::status_label( (string) $h['to'] ) : '';
				echo '<li style="padding:.35rem .5rem;border-left:3px solid #1f6f8b;background:#f6f8f7;margin-bottom:.3rem;font-size:.86rem">';
				echo esc_html( sprintf( '%1$s -> %2$s', $from, $to ) ) . ' <span style="color:#6a776e">' . esc_html( sprintf( '- %1$s, %2$s', $who, $when ) ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
	}

	/* ================================================================== *
	 * Save.
	 * ================================================================== */

	/**
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save( $post_id, $post ): void {
		$post_id = (int) $post_id;
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$old_status = self::get_status( $post_id );

		$statuses = self::statuses();
		$new_status = isset( $_POST['gw_case_status'] ) ? sanitize_key( (string) wp_unslash( $_POST['gw_case_status'] ) ) : $old_status;
		if ( ! isset( $statuses[ $new_status ] ) ) {
			$new_status = $old_status;
		}

		$priorities = self::priorities();
		$priority = isset( $_POST['gw_case_priority'] ) ? sanitize_key( (string) wp_unslash( $_POST['gw_case_priority'] ) ) : 'normal';
		if ( ! isset( $priorities[ $priority ] ) ) {
			$priority = 'normal';
		}

		$assignee   = isset( $_POST['gw_case_assignee'] ) ? (int) wp_unslash( $_POST['gw_case_assignee'] ) : 0;
		$notes      = isset( $_POST['gw_case_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['gw_case_notes'] ) ) : '';
		$resolution = isset( $_POST['gw_case_resolution'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['gw_case_resolution'] ) ) : '';

		self::number( $post_id );
		update_post_meta( $post_id, self::M_STATUS, $new_status );
		update_post_meta( $post_id, self::M_PRIORITY, $priority );
		update_post_meta( $post_id, self::M_ASSIGNEE, $assignee );
		update_post_meta( $post_id, self::M_NOTES, $notes );
		update_post_meta( $post_id, self::M_RESOLUTION, $resolution );

		if ( $new_status !== $old_status ) {
			$history   = get_post_meta( $post_id, self::M_HISTORY, true );
			$history   = is_array( $history ) ? $history : array();
			$history[] = array(
				't'    => time(),
				'by'   => (int) get_current_user_id(),
				'from' => $old_status,
				'to'   => $new_status,
			);
			if ( count( $history ) > 50 ) {
				$history = array_slice( $history, -50 );
			}
			update_post_meta( $post_id, self::M_HISTORY, $history );
		}

		if ( ! empty( $_POST['gw_case_notify'] ) ) {
			$this->notify_customer( $post_id, $new_status );
		}
	}

	private function notify_customer( int $id, string $status ): void {
		$templates = array(
			'contacted' => __( 'Hello %1$s, this is Green World Health regarding your consultation (%2$s). One of our wellness advisors will be in touch with you shortly.', 'greenworld-core' ),
			'waiting'   => __( 'Hello %1$s, regarding your Green World consultation (%2$s): we are waiting to hear back from you. Please reply when you have a moment.', 'greenworld-core' ),
			'advice'    => __( 'Hello %1$s, regarding your Green World consultation (%2$s): our advisor has prepared some general wellness guidance and will share it with you. This is general wellness information, not a medical diagnosis.', 'greenworld-core' ),
			'followup'  => __( 'Hello %1$s, this is Green World Health following up on your consultation (%2$s). How are you getting on? Reply to let us know if you need anything further.', 'greenworld-core' ),
			'closed'    => __( 'Hello %1$s, your Green World consultation (%2$s) has been completed. Thank you for reaching out - we are here whenever you need us.', 'greenworld-core' ),
		);
		if ( ! isset( $templates[ $status ] ) ) {
			return;
		}
		$name    = (string) get_post_meta( $id, '_gw_c_name', true );
		$name    = '' !== $name ? $name : __( 'there', 'greenworld-core' );
		$phone   = (string) get_post_meta( $id, '_gw_c_phone', true );
		$email   = (string) get_post_meta( $id, '_gw_c_email', true );
		$message = sprintf( $templates[ $status ], $name, self::number( $id ) );

		if ( '' !== $phone && class_exists( 'GWC_WhatsApp' ) && is_callable( array( 'GWC_WhatsApp', 'send_text' ) ) ) {
			GWC_WhatsApp::send_text( $phone, $message );
		}
		if ( '' !== $email && is_email( $email ) ) {
			wp_mail( $email, __( 'Update on your Green World consultation', 'greenworld-core' ), $message );
		}
	}

	/* ================================================================== *
	 * List columns + status filter.
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
				$new['gw_case_no']    = __( 'Case #', 'greenworld-core' );
				$new['gw_case_stat']  = __( 'Status', 'greenworld-core' );
				$new['gw_case_prio']  = __( 'Priority', 'greenworld-core' );
				$new['gw_case_asgn']  = __( 'Advisor', 'greenworld-core' );
			}
		}
		if ( ! isset( $new['gw_case_stat'] ) ) {
			$new['gw_case_no']   = __( 'Case #', 'greenworld-core' );
			$new['gw_case_stat'] = __( 'Status', 'greenworld-core' );
			$new['gw_case_prio'] = __( 'Priority', 'greenworld-core' );
			$new['gw_case_asgn'] = __( 'Advisor', 'greenworld-core' );
		}
		return $new;
	}

	/**
	 * @param string $col
	 * @param int    $post_id
	 */
	public function column( $col, $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'gw_case_no' === $col ) {
			echo '<code>' . esc_html( self::number( $post_id ) ) . '</code>';
		} elseif ( 'gw_case_stat' === $col ) {
			echo wp_kses_post( self::badge( $post_id ) );
		} elseif ( 'gw_case_prio' === $col ) {
			$p = self::get_priority( $post_id );
			$w = in_array( $p, array( 'high', 'urgent' ), true ) ? 'font-weight:700;color:#b32d2e' : '';
			echo '<span style="' . esc_attr( $w ) . '">' . esc_html( self::priority_label( $p ) ) . '</span>';
		} elseif ( 'gw_case_asgn' === $col ) {
			$uid = (int) get_post_meta( $post_id, self::M_ASSIGNEE, true );
			echo esc_html( $uid > 0 ? (string) get_the_author_meta( 'display_name', $uid ) : '—' );
		}
	}

	public function status_filter(): void {
		global $typenow;
		if ( self::CPT !== $typenow ) {
			return;
		}
		$current = isset( $_GET['gw_case_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['gw_case_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="gw_case_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'greenworld-core' ) . '</option>';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $current, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function apply_filter( $query ): void {
		global $pagenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow ) {
			return;
		}
		if ( ! isset( $query->query_vars['post_type'] ) || self::CPT !== $query->query_vars['post_type'] ) {
			return;
		}
		$status = isset( $_GET['gw_case_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['gw_case_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $status ) {
			return;
		}
		$mq = (array) $query->get( 'meta_query' );
		$mq[] = array(
			'key'     => self::M_STATUS,
			'value'   => $status,
			'compare' => '=',
		);
		$query->set( 'meta_query', $mq );
	}
}
