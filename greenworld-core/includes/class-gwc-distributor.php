<?php
/**
 * Distributor onboarding, admin activation, and the "Distributor" dashboard.
 *
 * Registration itself (the Customer / Distributor toggle, applicant fields, the
 * gw_distributor role and the initial "pending" status) lives in the theme's
 * Registration module. This plugin module owns what must survive a theme
 * change:
 *   - an admin activation screen (Users -> Distributors) to review pending
 *     applications and activate / suspend them, notifying the distributor;
 *   - a referral code issued on activation;
 *   - a "Distributor" tab on My Account showing status, profile, referral code
 *     and a points placeholder (the points ledger arrives in a later phase).
 *
 * It reads the user meta the theme already writes:
 *   _gw_account_type, _gw_phone, _gw_county, _gw_sponsor, _gw_distributor_status
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Distributor {

	private static $instance = null;

	public const ENDPOINT = 'distributor';
	public const ROLE     = 'gw_distributor';

	/* User-meta keys (shared with the theme's Registration module). */
	private const M_TYPE      = '_gw_account_type';
	private const M_PHONE     = '_gw_phone';
	private const M_COUNTY    = '_gw_county';
	private const M_SPONSOR   = '_gw_sponsor';
	private const M_STATUS    = '_gw_distributor_status';
	private const M_REF       = '_gw_ref_code';
	private const M_ACTIVATED = '_gw_distributor_activated';
	private const M_POINTS    = '_gw_points_balance';

	public static function instance(): GWC_Distributor {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// My Account "Distributor" tab.
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );

		// Admin activation screen.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_gwc_dist_action', array( $this, 'handle_admin_action' ) );
	}

	/* ================================================================== *
	 * Status + referral helpers.
	 * ================================================================== */

	public static function is_distributor( int $uid ): bool {
		if ( $uid <= 0 ) {
			return false;
		}
		if ( 'distributor' === (string) get_user_meta( $uid, self::M_TYPE, true ) ) {
			return true;
		}
		$user = get_userdata( $uid );
		return ( $user instanceof \WP_User && in_array( self::ROLE, (array) $user->roles, true ) );
	}

	public static function status( int $uid ): string {
		$s = (string) get_user_meta( $uid, self::M_STATUS, true );
		return '' !== $s ? $s : 'pending';
	}

	/**
	 * The distributor's referral code. When $create is true and none exists yet,
	 * a stable code derived from the user ID is generated and stored.
	 */
	public static function ref_code( int $uid, bool $create = false ): string {
		$code = (string) get_user_meta( $uid, self::M_REF, true );
		if ( '' === $code && $create && $uid > 0 ) {
			$code = 'GW' . str_pad( (string) $uid, 5, '0', STR_PAD_LEFT );
			update_user_meta( $uid, self::M_REF, $code );
		}
		return $code;
	}

	public static function points( int $uid ): int {
		return (int) get_user_meta( $uid, self::M_POINTS, true );
	}

	/**
	 * How many distributors named this one as their sponsor at registration,
	 * matched on the sponsor field against their referral code, login or email.
	 */
	private function referral_count( int $uid ): int {
		$user = get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			return 0;
		}
		$needles = array_filter( array( self::ref_code( $uid ), $user->user_login, $user->user_email ) );
		if ( empty( $needles ) ) {
			return 0;
		}
		$meta = array( 'relation' => 'OR' );
		foreach ( $needles as $needle ) {
			$meta[] = array(
				'key'     => self::M_SPONSOR,
				'value'   => $needle,
				'compare' => '=',
			);
		}
		$query = new \WP_User_Query(
			array(
				'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'fields'     => 'ID',
				'number'     => 500,
			)
		);
		return (int) count( (array) $query->get_results() );
	}

	private function dashboard_url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( self::ENDPOINT );
		}
		return home_url( '/my-account/' . self::ENDPOINT . '/' );
	}

	/* ================================================================== *
	 * My Account "Distributor" endpoint.
	 * ================================================================== */

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
	 * Add the tab only for distributors, just before Log out.
	 *
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public function menu_item( $items ): array {
		if ( ! self::is_distributor( get_current_user_id() ) ) {
			return (array) $items;
		}
		$new = array();
		foreach ( (array) $items as $key => $label ) {
			if ( 'customer-logout' === $key && ! isset( $new[ self::ENDPOINT ] ) ) {
				$new[ self::ENDPOINT ] = __( 'Distributor', 'greenworld-core' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'Distributor', 'greenworld-core' );
		}
		return $new;
	}

	public function render(): void {
		$uid = (int) get_current_user_id();
		if ( $uid <= 0 ) {
			echo '<p>' . esc_html__( 'Please log in to view your distributor dashboard.', 'greenworld-core' ) . '</p>';
			return;
		}
		if ( ! self::is_distributor( $uid ) ) {
			echo '<p>' . esc_html__( 'This area is for Green World distributors.', 'greenworld-core' ) . ' <a href="' . esc_url( home_url( '/become-a-distributor/' ) ) . '">' . esc_html__( 'Become a distributor', 'greenworld-core' ) . '</a></p>';
			return;
		}
		$this->styles();
		$status = self::status( $uid );
		echo '<div class="gw-dist">';
		$this->section_status( $uid, $status );
		$this->section_profile( $uid );
		if ( 'active' === $status ) {
			do_action( 'gwc_distributor_dashboard_active', $uid );
			$this->section_referral( $uid );
			$this->section_points( $uid );
		}
		echo '</div>';
	}

	private function styles(): void {
		echo '<style>.gw-dist{--gw:#14421f}.gw-dist .gw-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:1.1rem 1.15rem;margin:0 0 1.1rem;box-shadow:0 6px 18px rgba(0,0,0,.06)}.gw-dist h3{margin:.1rem 0 .5rem;color:var(--gw);font-size:1.12rem}.gw-dist p.gw-sub{margin:0 0 .6rem;color:#4a5a4f;font-size:.92rem}.gw-dist .gw-badge{display:inline-block;font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:999px;text-transform:capitalize}.gw-dist .gw-badge--pending{background:#fdf3e0;color:#8a5a12}.gw-dist .gw-badge--active{background:#e7f6ec;color:#14612b}.gw-dist .gw-badge--suspended{background:#fdecec;color:#8a1c1c}.gw-dist dl.gw-facts{margin:.3rem 0 0;display:grid;grid-template-columns:auto 1fr;gap:.35rem .9rem}.gw-dist dl.gw-facts dt{color:#6a776e;font-size:.85rem}.gw-dist dl.gw-facts dd{margin:0;font-weight:600;color:#22322a;font-size:.92rem}.gw-dist .gw-code{font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700;letter-spacing:.06em;background:#f0f6f1;border:1px dashed #b9d3bf;color:#14612b;padding:.28rem .65rem;border-radius:8px;display:inline-block}.gw-dist .gw-points{font-size:2rem;font-weight:800;color:var(--gw);line-height:1;margin:.2rem 0}.gw-dist a.gw-share{word-break:break-all;font-size:.88rem}.gw-dist .button{margin-top:.5rem}</style>';
	}

	private function section_status( int $uid, string $status ): void {
		$map = array(
			'pending'   => array(
				__( 'Application received', 'greenworld-core' ),
				__( 'Your distributor application is being reviewed by our team. We will activate your account and be in touch shortly. You can still shop and track orders in the meantime.', 'greenworld-core' ),
			),
			'active'    => array(
				__( 'Active distributor', 'greenworld-core' ),
				__( 'Your distributor account is active. Your referral code and points are shown below.', 'greenworld-core' ),
			),
			'suspended' => array(
				__( 'Account on hold', 'greenworld-core' ),
				__( 'Your distributor account is currently on hold. Please contact our team so we can help you get back on track.', 'greenworld-core' ),
			),
		);
		$key   = isset( $map[ $status ] ) ? $status : 'pending';
		$title = $map[ $key ][0];
		$sub   = $map[ $key ][1];
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Distributor status', 'greenworld-core' ) . ' <span class="gw-badge gw-badge--' . esc_attr( $key ) . '">' . esc_html( ucfirst( $key ) ) . '</span></h3>';
		echo '<p style="font-weight:600;color:#22322a;margin:.2rem 0 .3rem;">' . esc_html( $title ) . '</p>';
		echo '<p class="gw-sub">' . esc_html( $sub ) . '</p>';
		echo '</div>';
	}

	private function section_profile( int $uid ): void {
		$user    = get_userdata( $uid );
		$name    = $user instanceof \WP_User ? $user->display_name : '';
		$email   = $user instanceof \WP_User ? $user->user_email : '';
		$phone   = (string) get_user_meta( $uid, self::M_PHONE, true );
		$county  = (string) get_user_meta( $uid, self::M_COUNTY, true );
		$sponsor = (string) get_user_meta( $uid, self::M_SPONSOR, true );
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Your details', 'greenworld-core' ) . '</h3>';
		echo '<dl class="gw-facts">';
		echo '<dt>' . esc_html__( 'Name', 'greenworld-core' ) . '</dt><dd>' . esc_html( '' !== $name ? $name : '-' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Email', 'greenworld-core' ) . '</dt><dd>' . esc_html( '' !== $email ? $email : '-' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Phone', 'greenworld-core' ) . '</dt><dd>' . esc_html( '' !== $phone ? $phone : '-' ) . '</dd>';
		echo '<dt>' . esc_html__( 'County / Town', 'greenworld-core' ) . '</dt><dd>' . esc_html( '' !== $county ? $county : '-' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Referred by', 'greenworld-core' ) . '</dt><dd>' . esc_html( '' !== $sponsor ? $sponsor : '-' ) . '</dd>';
		echo '</dl>';
		echo '<p class="gw-sub" style="margin-top:.7rem;">' . esc_html__( 'To update your details, edit your account information or contact our team.', 'greenworld-core' ) . '</p>';
		echo '</div>';
	}

	private function section_referral( int $uid ): void {
		$code  = self::ref_code( $uid, true );
		$count = $this->referral_count( $uid );
		$link  = esc_url( add_query_arg( array( 'gw_type' => 'distributor', 'ref' => $code ), home_url( '/become-a-distributor/' ) ) );
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Your referral code', 'greenworld-core' ) . '</h3>';
		echo '<p class="gw-sub">' . esc_html__( 'Share this code with people you introduce to Green World. When they register as a distributor, they enter it in the "Sponsor / Referral ID" field so you are credited.', 'greenworld-core' ) . '</p>';
		echo '<p><span class="gw-code">' . esc_html( '' !== $code ? $code : '-' ) . '</span></p>';
		echo '<p class="gw-sub">' . esc_html__( 'Or share this sign-up link:', 'greenworld-core' ) . '<br /><a class="gw-share" href="' . $link . '">' . esc_html( $link ) . '</a></p>';
		echo '<p class="gw-sub" style="margin-top:.6rem;">' . esc_html( sprintf( _n( '%d person has registered with your code so far.', '%d people have registered with your code so far.', $count, 'greenworld-core' ), $count ) ) . '</p>';
		echo '</div>';
	}

	private function section_points( int $uid ): void {
		$points = self::points( $uid );
		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Your points', 'greenworld-core' ) . '</h3>';
		echo '<p class="gw-points">' . esc_html( number_format_i18n( $points ) ) . '</p>';
		echo '<p class="gw-sub">' . esc_html__( 'You earn points from the product batches our team allocates to you, based on the point value of each product.', 'greenworld-core' ) . '</p>';
		if ( class_exists( 'GWC_Points' ) ) {
			$batches = GWC_Points::user_batches( $uid, 10 );
			if ( ! empty( $batches ) ) {
				echo '<p class="gw-sub" style="font-weight:600;color:#22322a;margin:.7rem 0 .3rem;">' . esc_html__( 'Recent points', 'greenworld-core' ) . '</p>';
				echo '<ul style="list-style:none;margin:0;padding:0;">';
				foreach ( $batches as $batch ) {
					$pts   = (int) get_post_meta( (int) $batch->ID, '_gw_b_points', true );
					$items = GWC_Points::items_summary( (int) $batch->ID );
					$sign  = $pts < 0 ? '' : '+';
					echo '<li style="padding:.55rem .65rem;border:1px solid rgba(0,0,0,.08);border-radius:8px;margin-bottom:.4rem;background:#fafbf9;">';
					echo '<strong style="color:#14612b;">' . esc_html( $sign . number_format_i18n( $pts ) ) . ' ' . esc_html__( 'pts', 'greenworld-core' ) . '</strong> ';
					echo '<span style="color:#6a776e;font-size:.8rem;">' . esc_html( get_the_date( '', $batch ) ) . '</span>';
					if ( '' !== $items ) {
						echo '<div class="gw-sub" style="margin:.2rem 0 0;">' . esc_html( $items ) . '</div>';
					}
					echo '</li>';
				}
				echo '</ul>';
			}
		}
		echo '</div>';
	}

	/* ================================================================== *
	 * Admin activation screen (Users -> Distributors).
	 * ================================================================== */

	public function admin_menu(): void {
		add_users_page(
			__( 'Distributors', 'greenworld-core' ),
			__( 'Distributors', 'greenworld-core' ),
			'edit_users',
			'gwc-distributors',
			array( $this, 'render_admin' )
		);
	}

	public function render_admin(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage distributors.', 'greenworld-core' ) );
		}

		$notice = isset( $_GET['gwd'] ) ? sanitize_key( (string) wp_unslash( $_GET['gwd'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dists  = get_users(
			array(
				'role'    => self::ROLE,
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 500,
			)
		);

		$counts = array( 'pending' => 0, 'active' => 0, 'suspended' => 0 );
		foreach ( $dists as $d ) {
			$st = self::status( (int) $d->ID );
			if ( isset( $counts[ $st ] ) ) {
				$counts[ $st ]++;
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Distributors', 'greenworld-core' ) . '</h1>';

		if ( '' !== $notice ) {
			$msgs = array(
				'activated' => array( 'success', __( 'Distributor activated. They have been notified by WhatsApp (if configured) and email.', 'greenworld-core' ) ),
				'suspended' => array( 'warning', __( 'Distributor put on hold.', 'greenworld-core' ) ),
				'error'     => array( 'error', __( 'That action could not be completed. Please try again.', 'greenworld-core' ) ),
			);
			if ( isset( $msgs[ $notice ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $msgs[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $notice ][1] ) . '</p></div>';
			}
		}

		echo '<p>' . esc_html( sprintf( __( '%1$d pending, %2$d active, %3$d on hold.', 'greenworld-core' ), $counts['pending'], $counts['active'], $counts['suspended'] ) ) . '</p>';

		if ( empty( $dists ) ) {
			echo '<p>' . esc_html__( 'No distributor applications yet.', 'greenworld-core' ) . '</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Applicant', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Phone', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'County / Town', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Referred by', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Code', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Registered', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'greenworld-core' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $dists as $d ) {
			$id      = (int) $d->ID;
			$status  = self::status( $id );
			$phone   = (string) get_user_meta( $id, self::M_PHONE, true );
			$county  = (string) get_user_meta( $id, self::M_COUNTY, true );
			$sponsor = (string) get_user_meta( $id, self::M_SPONSOR, true );
			$code    = self::ref_code( $id );
			$edit    = get_edit_user_link( $id );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $d->display_name ) . '</strong><br /><a href="' . esc_url( $edit ) . '">' . esc_html( $d->user_email ) . '</a></td>';
			echo '<td>' . esc_html( '' !== $phone ? $phone : '-' ) . '</td>';
			echo '<td>' . esc_html( '' !== $county ? $county : '-' ) . '</td>';
			echo '<td>' . esc_html( '' !== $sponsor ? $sponsor : '-' ) . '</td>';
			echo '<td>' . esc_html( '' !== $code ? $code : '-' ) . '</td>';
			echo '<td><span class="gwc-status" style="font-weight:600;">' . esc_html( ucfirst( $status ) ) . '</span></td>';
			echo '<td>' . esc_html( mysql2date( (string) get_option( 'date_format' ), $d->user_registered ) ) . '</td>';
			echo '<td>';
			if ( 'active' !== $status ) {
				$this->action_button( $id, 'activate', __( 'Activate', 'greenworld-core' ), 'button button-primary' );
			}
			if ( 'suspended' !== $status ) {
				$this->action_button( $id, 'suspend', __( 'Put on hold', 'greenworld-core' ), 'button' );
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	private function action_button( int $uid, string $do, string $label, string $class ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 .25rem .25rem 0;">';
		echo '<input type="hidden" name="action" value="gwc_dist_action" />';
		echo '<input type="hidden" name="uid" value="' . esc_attr( (string) $uid ) . '" />';
		echo '<input type="hidden" name="do" value="' . esc_attr( $do ) . '" />';
		wp_nonce_field( 'gwc_dist_action' );
		echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	public function handle_admin_action(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'greenworld-core' ) );
		}
		check_admin_referer( 'gwc_dist_action' );

		$uid  = isset( $_POST['uid'] ) ? (int) $_POST['uid'] : 0;
		$do   = isset( $_POST['do'] ) ? sanitize_key( (string) wp_unslash( $_POST['do'] ) ) : '';
		$back = admin_url( 'users.php?page=gwc-distributors' );

		if ( $uid <= 0 || ! self::is_distributor( $uid ) ) {
			wp_safe_redirect( add_query_arg( 'gwd', 'error', $back ) );
			exit;
		}

		if ( 'activate' === $do ) {
			$this->activate( $uid );
			wp_safe_redirect( add_query_arg( 'gwd', 'activated', $back ) );
			exit;
		}

		if ( 'suspend' === $do ) {
			update_user_meta( $uid, self::M_STATUS, 'suspended' );
			wp_safe_redirect( add_query_arg( 'gwd', 'suspended', $back ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'gwd', 'error', $back ) );
		exit;
	}

	private function activate( int $uid ): void {
		update_user_meta( $uid, self::M_STATUS, 'active' );
		update_user_meta( $uid, self::M_ACTIVATED, current_time( 'mysql' ) );
		$code = self::ref_code( $uid, true );
		$this->notify_distributor_active( $uid, $code );
	}

	private function notify_distributor_active( int $uid, string $code ): void {
		$user = get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$dash    = $this->dashboard_url();
		$message = sprintf(
			/* translators: 1: name, 2: referral code, 3: dashboard URL */
			__( 'Hello %1$s, your Green World distributor account is now active. Your referral code is %2$s. View your distributor dashboard here: %3$s', 'greenworld-core' ),
			$user->display_name,
			'' !== $code ? $code : '-',
			$dash
		);

		$phone = (string) get_user_meta( $uid, self::M_PHONE, true );
		if ( '' !== $phone && class_exists( 'GWC_WhatsApp' ) ) {
			GWC_WhatsApp::send_text( $phone, $message );
		}

		wp_mail(
			$user->user_email,
			__( 'Your Green World distributor account is active', 'greenworld-core' ),
			$message
		);
	}
}
