<?php
/**
 * Scan bookings: a front-end form that records each booking as a private
 * gw_scan post and (when configured) fires a WhatsApp alert to staff.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Scan {

	private static $instance = null;
	public const CPT         = 'gw_scan';

	public static function instance(): GWC_Scan {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( 'gw_scan_form', array( $this, 'form' ) );
		add_action( 'admin_post_nopriv_gwc_scan', array( $this, 'handle' ) );
		add_action( 'admin_post_gwc_scan', array( $this, 'handle' ) );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	public function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Scan Bookings', 'greenworld-core' ),
					'singular_name' => __( 'Scan Booking', 'greenworld-core' ),
					'menu_name'     => __( 'Scan Bookings', 'greenworld-core' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-calendar-alt',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
	}

	private function opt( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	}

	public function handle(): void {
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}
		if ( ! isset( $_POST['gwc_scan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gwc_scan_nonce'] ) ), 'gwc_scan' ) ) {
			wp_safe_redirect( add_query_arg( 'scan', 'error', $redirect ) );
			exit;
		}
		// Honeypot: feign success so bots do not learn they were caught.
		if ( ! empty( $_POST['gw_website'] ) ) {
			wp_safe_redirect( add_query_arg( 'scan', 'ok', $redirect ) );
			exit;
		}
		$name  = $this->opt( 'gw_name' );
		$phone = $this->opt( 'gw_phone' );
		$date  = $this->opt( 'gw_date' );
		$time  = $this->opt( 'gw_time' );
		$place = $this->opt( 'gw_location' );
		$note  = isset( $_POST['gw_note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['gw_note'] ) ) : '';

		if ( '' === $name || '' === $phone ) {
			wp_safe_redirect( add_query_arg( 'scan', 'invalid', $redirect ) );
			exit;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'private',
				'post_title'  => sprintf( '%s — %s', $name, current_time( 'Y-m-d H:i' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			wp_safe_redirect( add_query_arg( 'scan', 'error', $redirect ) );
			exit;
		}
		$pid = (int) $post_id;
		update_post_meta( $pid, '_gw_s_name', $name );
		update_post_meta( $pid, '_gw_s_phone', $phone );
		update_post_meta( $pid, '_gw_s_date', $date );
		update_post_meta( $pid, '_gw_s_time', $time );
		update_post_meta( $pid, '_gw_s_location', $place );
		update_post_meta( $pid, '_gw_s_note', $note );

		$msg = sprintf(
			"New scan booking:\nName: %s\nPhone: %s\nPreferred date: %s\nPreferred time: %s\nLocation: %s\nNote: %s",
			$name,
			$phone,
			'' !== $date ? $date : '-',
			'' !== $time ? $time : '-',
			'' !== $place ? $place : '-',
			'' !== $note ? $note : '-'
		);
		GWC_WhatsApp::notify_staff( $msg );

		/**
		 * Fires after a scan booking is saved.
		 *
		 * @param int $pid Booking post ID.
		 */
		do_action( 'greenworld/scan_booked', $pid );

		wp_safe_redirect( add_query_arg( 'scan', 'ok', $redirect ) );
		exit;
	}

	/**
	 * [gw_scan_form] — renders the booking form.
	 *
	 * @param array<string,mixed> $atts
	 */
	public function form( $atts = array() ): string {
		static $printed_css = false;
		$status = isset( $_GET['scan'] ) ? sanitize_key( (string) $_GET['scan'] ) : '';
		ob_start();
		if ( ! $printed_css ) {
			$printed_css = true;
			echo '<style>.gw-scan-form{display:grid;grid-template-columns:1fr 1fr;gap:.5rem .7rem;max-width:440px;background:#fff;padding:1.05rem 1.15rem;border-radius:14px;box-shadow:0 12px 30px rgba(0,0,0,.18);margin:.5rem 0 0}.gw-scan-form p{margin:0}.gw-scan-form .gw-f-full{grid-column:1 / -1}.gw-scan-form label{display:block;font-size:.78rem;font-weight:600;color:#14421f;letter-spacing:.01em}.gw-scan-form input,.gw-scan-form textarea{width:100%;padding:.48rem .6rem;border:1px solid rgba(0,0,0,.2);border-radius:8px;font:inherit;font-size:16px;line-height:1.3;margin-top:.22rem;box-sizing:border-box;background:#fff;color:#14311c}.gw-scan-form textarea{min-height:64px;resize:vertical}.gw-scan-form .gw-scan-btn{margin-top:.4rem}.gw-scan-form .gw-scan-btn button{width:100%}.gw-scan-hp{position:absolute;left:-9999px}.gw-scan-notice{max-width:440px;padding:.65rem .85rem;border-radius:10px;margin:0 0 .7rem;font-size:.9rem}.gw-scan-notice--ok{background:#e7f6ec;color:#14612b}.gw-scan-notice--err{background:#fdecec;color:#8a1c1c}@media(max-width:479px){.gw-scan-form{grid-template-columns:1fr}}</style>';
		}
		if ( 'ok' === $status ) {
			echo '<div class="gw-scan-notice gw-scan-notice--ok">' . esc_html__( 'Thank you. Your scan booking has been received — we will confirm shortly.', 'greenworld-core' ) . '</div>';
		} elseif ( 'invalid' === $status ) {
			echo '<div class="gw-scan-notice gw-scan-notice--err">' . esc_html__( 'Please add at least your name and phone number.', 'greenworld-core' ) . '</div>';
		} elseif ( 'error' === $status ) {
			echo '<div class="gw-scan-notice gw-scan-notice--err">' . esc_html__( 'Something went wrong. Please try again or WhatsApp us.', 'greenworld-core' ) . '</div>';
		}
		?>
		<form class="gw-scan-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gwc_scan" />
			<?php wp_nonce_field( 'gwc_scan', 'gwc_scan_nonce' ); ?>
			<p class="gw-scan-hp" aria-hidden="true"><label><?php esc_html_e( 'Leave this field empty', 'greenworld-core' ); ?><input type="text" name="gw_website" tabindex="-1" autocomplete="off" /></label></p>
			<p><label><?php esc_html_e( 'Full name', 'greenworld-core' ); ?><input type="text" name="gw_name" required /></label></p>
			<p><label><?php esc_html_e( 'Phone / WhatsApp', 'greenworld-core' ); ?><input type="tel" name="gw_phone" required /></label></p>
			<p><label><?php esc_html_e( 'Preferred date', 'greenworld-core' ); ?><input type="date" name="gw_date" /></label></p>
			<p><label><?php esc_html_e( 'Preferred time', 'greenworld-core' ); ?><input type="time" name="gw_time" /></label></p>
			<p><label><?php esc_html_e( 'Location / branch', 'greenworld-core' ); ?><input type="text" name="gw_location" /></label></p>
			<p><label><?php esc_html_e( 'Note (optional)', 'greenworld-core' ); ?><textarea name="gw_note" rows="3"></textarea></label></p>
			<p><button type="submit" class="button gw-btn--gold"><?php esc_html_e( 'Book your scan', 'greenworld-core' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public function metabox(): void {
		add_meta_box( 'gwc_scan_details', __( 'Booking details', 'greenworld-core' ), array( $this, 'render_metabox' ), self::CPT, 'normal', 'high' );
	}

	public function render_metabox( \WP_Post $post ): void {
		$rows = array(
			__( 'Name', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_s_name', true ),
			__( 'Phone', 'greenworld-core' )    => get_post_meta( $post->ID, '_gw_s_phone', true ),
			__( 'Date', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_s_date', true ),
			__( 'Time', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_s_time', true ),
			__( 'Location', 'greenworld-core' ) => get_post_meta( $post->ID, '_gw_s_location', true ),
			__( 'Note', 'greenworld-core' )     => get_post_meta( $post->ID, '_gw_s_note', true ),
		);
		echo '<table class="widefat striped">';
		foreach ( $rows as $label => $val ) {
			echo '<tr><th style="width:180px;">' . esc_html( (string) $label ) . '</th><td>' . esc_html( (string) $val ) . '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function columns( $cols ): array {
		$new = array();
		foreach ( (array) $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gw_phone'] = __( 'Phone', 'greenworld-core' );
				$new['gw_date']  = __( 'Preferred date', 'greenworld-core' );
			}
		}
		return $new;
	}

	public function column( string $col, int $post_id ): void {
		if ( 'gw_phone' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_s_phone', true ) );
		} elseif ( 'gw_date' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_gw_s_date', true ) );
		}
	}
}
