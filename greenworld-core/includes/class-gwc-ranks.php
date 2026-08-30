<?php
/**
 * Distributor rank engine for Green World Core.
 *
 * Builds a configurable rank ladder on top of the EXISTING points ledger
 * (GWC_Points / gw_batch) and referral structure (GWC_Distributor / _gw_sponsor).
 * No new ledger, no duplication:
 *
 *   - Lifetime points = the sum of a distributor's positive point batches (rank
 *     never drops when the current balance is spent or corrected down).
 *   - Direct referrals = how many distributors named this one as their sponsor.
 *   - Team volume     = the downline (level 2+ to a configurable depth): how many
 *     distributors sit below this one, and their combined lifetime points. Only
 *     computed when the ladder actually uses team thresholds (opt-in, for speed).
 *   - Rank = the highest tier whose points, referrals, team-points and team-size
 *     thresholds are ALL met.
 *
 * The ladder (names + thresholds) is fully admin-editable so it can be mapped
 * to Green World's real compensation plan. The engine surfaces a rank card on
 * the distributor dashboard (via the gwc_distributor_dashboard_active hook) and
 * a roster + ladder editor under Users -> Ranks. When a point batch is posted it
 * re-checks the recipient (and, when team volume is in use, their upline) and
 * sends a WhatsApp + email congratulation the moment a new tier is reached.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Ranks {

	private static $instance = null;

	const OPTION = 'gwc_ranks';

	/* Existing keys we read (owned by GWC_Points / GWC_Distributor). */
	const BATCH_CPT     = 'gw_batch';
	const M_BATCH_USER  = '_gw_b_user';
	const M_BATCH_PTS   = '_gw_b_points';
	const M_SPONSOR     = '_gw_sponsor';
	const M_REF_CODE    = '_gw_ref_code';
	const M_PHONE       = '_gw_phone';

	/* Keys this engine owns (rank baseline for change detection). */
	const M_LEVEL     = '_gw_rank_level';
	const M_RANK_NAME = '_gw_rank_name';

	const CACHE_TTL      = 600; // 10 minutes (per-user point/referral counts).
	const CACHE_TTL_TEAM = 900; // 15 minutes (downline tree + team volume).

	const NODE_CAP     = 2000; // Hard ceiling on downline nodes collected.
	const FRONTIER_CAP = 100;  // Max sponsors expanded per level (bounds query size).

	public static function instance(): GWC_Ranks {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'gwc_distributor_dashboard_active', array( $this, 'render_dashboard_card' ), 10, 1 );
		add_action( 'gwc_points_batch_added', array( $this, 'on_batch_added' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* --------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'        => 1,
			'ladder_text'    => "Distributor | 0 | 0 | 0 | 0\nBronze | 500 | 0 | 0 | 0\nSilver | 2000 | 0 | 0 | 0\nGold | 5000 | 3 | 0 | 0\nPlatinum | 15000 | 5 | 0 | 0\nDiamond | 40000 | 10 | 0 | 0",
			'notify_enabled' => 1,
			'notify_msg'     => 'Congratulations {name}, you have reached {rank} rank at Green World Health. Thank you for your dedication - keep building your team and aim for the next tier.',
			'team_depth'     => 3,
		);
	}

	public function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->defaults(), $saved );
	}

	public function register_settings(): void {
		register_setting( 'gwc_ranks_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();
		$out['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$out['notify_enabled'] = empty( $input['notify_enabled'] ) ? 0 : 1;
		$out['ladder_text']    = isset( $input['ladder_text'] ) ? sanitize_textarea_field( (string) $input['ladder_text'] ) : $d['ladder_text'];
		if ( '' === trim( $out['ladder_text'] ) ) {
			$out['ladder_text'] = $d['ladder_text'];
		}
		$out['notify_msg'] = isset( $input['notify_msg'] ) ? sanitize_textarea_field( (string) $input['notify_msg'] ) : $d['notify_msg'];
		if ( '' === trim( $out['notify_msg'] ) ) {
			$out['notify_msg'] = $d['notify_msg'];
		}
		$out['team_depth'] = isset( $input['team_depth'] ) ? (int) $input['team_depth'] : (int) $d['team_depth'];
		if ( $out['team_depth'] < 1 ) {
			$out['team_depth'] = 1;
		}
		if ( $out['team_depth'] > 6 ) {
			$out['team_depth'] = 6;
		}
		return $out;
	}

	public function is_enabled(): bool {
		$s = $this->settings();
		return ! empty( $s['enabled'] );
	}

	public function team_depth(): int {
		$s = $this->settings();
		$d = (int) $s['team_depth'];
		if ( $d < 1 ) {
			$d = 1;
		}
		if ( $d > 6 ) {
			$d = 6;
		}
		return $d;
	}

	/**
	 * Parsed rank ladder, ascending by points, always starting from a 0/0 base.
	 *
	 * @return array<int,array{name:string,points:int,referrals:int,team_points:int,team_size:int}>
	 */
	public function ladder(): array {
		$s     = $this->settings();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $s['ladder_text'] );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$name  = isset( $parts[0] ) ? $parts[0] : '';
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'name'        => $name,
				'points'      => isset( $parts[1] ) ? max( 0, (int) $parts[1] ) : 0,
				'referrals'   => isset( $parts[2] ) ? max( 0, (int) $parts[2] ) : 0,
				'team_points' => isset( $parts[3] ) ? max( 0, (int) $parts[3] ) : 0,
				'team_size'   => isset( $parts[4] ) ? max( 0, (int) $parts[4] ) : 0,
			);
		}

		if ( empty( $out ) ) {
			$out[] = array(
				'name'        => __( 'Distributor', 'greenworld-core' ),
				'points'      => 0,
				'referrals'   => 0,
				'team_points' => 0,
				'team_size'   => 0,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				if ( $a['points'] === $b['points'] ) {
					return $a['referrals'] - $b['referrals'];
				}
				return $a['points'] - $b['points'];
			}
		);

		// Guarantee a base tier everyone qualifies for.
		if ( $out[0]['points'] > 0 || $out[0]['referrals'] > 0 || $out[0]['team_points'] > 0 || $out[0]['team_size'] > 0 ) {
			array_unshift(
				$out,
				array(
					'name'        => __( 'Distributor', 'greenworld-core' ),
					'points'      => 0,
					'referrals'   => 0,
					'team_points' => 0,
					'team_size'   => 0,
				)
			);
		}

		return $out;
	}

	/** Whether any tier uses a team-volume threshold (gates all team computation). */
	public function ladder_uses_team(): bool {
		foreach ( $this->ladder() as $r ) {
			if ( (int) $r['team_points'] > 0 || (int) $r['team_size'] > 0 ) {
				return true;
			}
		}
		return false;
	}

	/* ----------------------------------------------------------------- metrics */

	/** Lifetime points earned = sum of positive point batches (cached). */
	public static function lifetime_points( int $uid ): int {
		if ( $uid <= 0 ) {
			return 0;
		}
		$key    = 'gwc_rank_lp_' . $uid;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$ids = (array) get_posts(
			array(
				'post_type'   => self::BATCH_CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::M_BATCH_USER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => (string) $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$sum = 0;
		foreach ( $ids as $bid ) {
			$p = (int) get_post_meta( (int) $bid, self::M_BATCH_PTS, true );
			if ( $p > 0 ) {
				$sum += $p;
			}
		}
		set_transient( $key, $sum, self::CACHE_TTL );
		return $sum;
	}

	/** Current spendable balance (from the existing cached user meta). */
	public static function balance( int $uid ): int {
		if ( class_exists( 'GWC_Distributor' ) && is_callable( array( 'GWC_Distributor', 'points' ) ) ) {
			return (int) GWC_Distributor::points( $uid );
		}
		return (int) get_user_meta( $uid, '_gw_points_balance', true );
	}

	/** Referral-matching needles for a distributor (ref code, login, email). */
	private static function needles_for( int $uid ): array {
		$user = get_userdata( $uid );
		if ( ! $user instanceof WP_User ) {
			return array();
		}
		$code = class_exists( 'GWC_Distributor' ) && is_callable( array( 'GWC_Distributor', 'ref_code' ) ) ? GWC_Distributor::ref_code( $uid ) : '';
		$out  = array();
		foreach ( array( $code, $user->user_login, $user->user_email ) as $n ) {
			$n = (string) $n;
			if ( '' !== $n ) {
				$out[ $n ] = true;
			}
		}
		return array_keys( $out );
	}

	/** Direct referrals: distributors who named this one as sponsor (cached). */
	public static function direct_referrals( int $uid ): int {
		if ( $uid <= 0 ) {
			return 0;
		}
		$key    = 'gwc_rank_dr_' . $uid;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$needles = self::needles_for( $uid );
		if ( empty( $needles ) ) {
			set_transient( $key, 0, self::CACHE_TTL );
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
		$query = new WP_User_Query(
			array(
				'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'fields'     => 'ID',
				'number'     => 500,
			)
		);
		$count = (int) count( (array) $query->get_results() );
		set_transient( $key, $count, self::CACHE_TTL );
		return $count;
	}

	/* ------------------------------------------------------------- team volume */

	/**
	 * The full downline below a distributor to the configured depth (cached).
	 * Breadth-first over the sponsor graph, cycle-guarded and bounded so a deep
	 * or wide tree can never run away. Returns an array of distinct user IDs.
	 *
	 * @return int[]
	 */
	public function downline( int $uid ): array {
		if ( $uid <= 0 ) {
			return array();
		}
		$key    = 'gwc_rank_dl_' . $uid;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$depth    = $this->team_depth();
		$visited  = array( $uid => true );
		$frontier = array( $uid );
		$all      = array();

		for ( $level = 0; $level < $depth; $level++ ) {
			if ( empty( $frontier ) ) {
				break;
			}
			if ( count( $frontier ) > self::FRONTIER_CAP ) {
				$frontier = array_slice( $frontier, 0, self::FRONTIER_CAP );
			}

			$needles = array();
			foreach ( $frontier as $fid ) {
				foreach ( self::needles_for( (int) $fid ) as $n ) {
					$needles[ $n ] = true;
				}
			}
			$needles = array_keys( $needles );
			if ( empty( $needles ) ) {
				break;
			}

			$meta = array( 'relation' => 'OR' );
			foreach ( $needles as $needle ) {
				$meta[] = array(
					'key'     => self::M_SPONSOR,
					'value'   => $needle,
					'compare' => '=',
				);
			}
			$q = new WP_User_Query(
				array(
					'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'fields'     => 'ID',
					'number'     => self::NODE_CAP,
				)
			);
			$ids = array_map( 'intval', (array) $q->get_results() );

			$next = array();
			foreach ( $ids as $cid ) {
				if ( $cid <= 0 || isset( $visited[ $cid ] ) ) {
					continue;
				}
				$visited[ $cid ] = true;
				$all[]           = $cid;
				$next[]          = $cid;
				if ( count( $all ) >= self::NODE_CAP ) {
					break 2;
				}
			}
			$frontier = $next;
		}

		set_transient( $key, $all, self::CACHE_TTL_TEAM );
		return $all;
	}

	/**
	 * Team size + combined lifetime points across the downline (cached).
	 * Returns zeros unless the ladder actually uses team thresholds, so sites
	 * that do not use team volume pay no computation cost.
	 *
	 * @return array{size:int,points:int}
	 */
	public function team_stats( int $uid ): array {
		if ( $uid <= 0 || ! $this->ladder_uses_team() ) {
			return array(
				'size'   => 0,
				'points' => 0,
			);
		}
		$key    = 'gwc_rank_team_' . $uid;
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['size'], $cached['points'] ) ) {
			return array(
				'size'   => (int) $cached['size'],
				'points' => (int) $cached['points'],
			);
		}
		$dl     = $this->downline( $uid );
		$points = 0;
		foreach ( $dl as $mid ) {
			$points += self::lifetime_points( (int) $mid );
		}
		$out = array(
			'size'   => count( $dl ),
			'points' => $points,
		);
		set_transient( $key, $out, self::CACHE_TTL_TEAM );
		return $out;
	}

	/**
	 * Walk UP the sponsor chain from a distributor, up to $depth ancestors.
	 * Used to re-check upline ranks when a downline batch changes team volume.
	 *
	 * @return int[]
	 */
	public function uplines( int $uid, int $depth ): array {
		$out  = array();
		$seen = array( $uid => true );
		$cur  = $uid;
		for ( $i = 0; $i < $depth; $i++ ) {
			$sp = (string) get_user_meta( $cur, self::M_SPONSOR, true );
			if ( '' === trim( $sp ) ) {
				break;
			}
			$pid = $this->resolve_sponsor( $sp );
			if ( $pid <= 0 || isset( $seen[ $pid ] ) ) {
				break;
			}
			$seen[ $pid ] = true;
			$out[]        = $pid;
			$cur          = $pid;
		}
		return $out;
	}

	/** Resolve a sponsor identifier (ref code, login, or email) to a user ID. */
	private function resolve_sponsor( string $value ): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}
		$q = new WP_User_Query(
			array(
				'meta_key'   => self::M_REF_CODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
				'number'     => 1,
			)
		);
		$r = array_map( 'intval', (array) $q->get_results() );
		if ( ! empty( $r ) ) {
			return (int) $r[0];
		}
		$u = get_user_by( 'login', $value );
		if ( $u instanceof WP_User ) {
			return (int) $u->ID;
		}
		$u = get_user_by( 'email', $value );
		if ( $u instanceof WP_User ) {
			return (int) $u->ID;
		}
		return 0;
	}

	/**
	 * Full rank picture for a distributor.
	 *
	 * @return array<string,mixed>
	 */
	public function rank_for( int $uid ): array {
		$ladder = $this->ladder();
		$lp     = self::lifetime_points( $uid );
		$refs   = self::direct_referrals( $uid );
		$team   = $this->team_stats( $uid );
		$tp     = (int) $team['points'];
		$tsz    = (int) $team['size'];

		$idx = 0;
		foreach ( $ladder as $i => $r ) {
			if ( $lp >= $r['points'] && $refs >= $r['referrals'] && $tp >= $r['team_points'] && $tsz >= $r['team_size'] ) {
				$idx = $i;
			}
		}
		$current = $ladder[ $idx ];
		$next    = isset( $ladder[ $idx + 1 ] ) ? $ladder[ $idx + 1 ] : null;

		$points_to_next = ( null !== $next ) ? max( 0, (int) $next['points'] - $lp ) : 0;
		$refs_to_next   = ( null !== $next ) ? max( 0, (int) $next['referrals'] - $refs ) : 0;
		$tp_to_next     = ( null !== $next ) ? max( 0, (int) $next['team_points'] - $tp ) : 0;
		$tsz_to_next    = ( null !== $next ) ? max( 0, (int) $next['team_size'] - $tsz ) : 0;

		$pct = 100;
		if ( null !== $next ) {
			$span = (int) $next['points'] - (int) $current['points'];
			$done = $lp - (int) $current['points'];
			$pct  = $span > 0 ? (int) round( max( 0, min( 100, ( $done / $span ) * 100 ) ) ) : ( $points_to_next <= 0 ? 100 : 0 );
		}

		return array(
			'index'               => $idx,
			'name'                => $current['name'],
			'current'             => $current,
			'next'                => $next,
			'lifetime'            => $lp,
			'balance'             => self::balance( $uid ),
			'referrals'           => $refs,
			'team_size'           => $tsz,
			'team_points'         => $tp,
			'points_to_next'      => $points_to_next,
			'refs_to_next'        => $refs_to_next,
			'team_points_to_next' => $tp_to_next,
			'team_size_to_next'   => $tsz_to_next,
			'progress'            => $pct,
			'uses_team'           => $this->ladder_uses_team(),
		);
	}

	/* --------------------------------------------------- rank-up notifications */

	/**
	 * Fired after a point batch is posted (gwc_points_batch_added).
	 * Re-checks the recipient and, when team volume is in use, their upline,
	 * congratulating anyone who has just crossed into a new tier.
	 *
	 * @param int $uid Distributor the batch was posted to.
	 */
	public function on_batch_added( $uid ): void {
		$uid = (int) $uid;
		if ( $uid <= 0 ) {
			return;
		}
		$targets = array( $uid );
		if ( $this->ladder_uses_team() ) {
			foreach ( $this->uplines( $uid, $this->team_depth() ) as $up ) {
				$targets[] = (int) $up;
			}
		}
		$targets = array_values( array_unique( $targets ) );
		foreach ( $targets as $t ) {
			delete_transient( 'gwc_rank_lp_' . $t );
			delete_transient( 'gwc_rank_team_' . $t );
			$this->check_rankup( $t );
		}
	}

	/** Compare a distributor's current rank to their stored baseline; notify on a rise. */
	private function check_rankup( int $uid ): void {
		if ( $uid <= 0 ) {
			return;
		}
		$info = $this->rank_for( $uid );
		$curr = (int) $info['index'];
		$prev = get_user_meta( $uid, self::M_LEVEL, true );
		$prev = ( '' === $prev ) ? 0 : (int) $prev;

		if ( $curr > $prev ) {
			$s = $this->settings();
			if ( ! empty( $s['notify_enabled'] ) ) {
				$this->notify_rank_up( $uid, (string) $info['name'] );
			}
		}
		if ( $curr !== $prev ) {
			update_user_meta( $uid, self::M_LEVEL, $curr );
			update_user_meta( $uid, self::M_RANK_NAME, (string) $info['name'] );
		}
	}

	/** Send the congratulation over WhatsApp (best effort) and email. */
	private function notify_rank_up( int $uid, string $rank_name ): void {
		$user = get_userdata( $uid );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$s   = $this->settings();
		$tpl = (string) $s['notify_msg'];
		if ( '' === trim( $tpl ) ) {
			$d   = $this->defaults();
			$tpl = (string) $d['notify_msg'];
		}
		$msg = strtr(
			$tpl,
			array(
				'{name}' => $user->display_name,
				'{rank}' => $rank_name,
			)
		);

		// WhatsApp: only when the integration is configured and a phone is on file.
		$phone = (string) get_user_meta( $uid, self::M_PHONE, true );
		if ( '' !== trim( $phone )
			&& class_exists( 'GWC_WhatsApp' )
			&& is_callable( array( 'GWC_WhatsApp', 'is_configured' ) )
			&& GWC_WhatsApp::is_configured()
			&& is_callable( array( 'GWC_WhatsApp', 'send_text' ) ) ) {
			GWC_WhatsApp::send_text( $phone, $msg );
		}

		// Email: whenever the distributor has an address on file.
		if ( '' !== trim( (string) $user->user_email ) ) {
			$subject = sprintf(
				/* translators: %s: rank name */
				__( 'Congratulations - you reached %s rank', 'greenworld-core' ),
				$rank_name
			);
			wp_mail( $user->user_email, $subject, $msg );
		}

		/**
		 * Fires after a distributor is congratulated on a new rank.
		 *
		 * @param int    $uid       Distributor user ID.
		 * @param string $rank_name The rank just reached.
		 */
		do_action( 'gwc_rank_promoted', $uid, $rank_name );
	}

	/**
	 * Record every existing distributor's current rank as their baseline WITHOUT
	 * notifying, so congratulations only fire for genuine FUTURE promotions.
	 * Runs once on activation; only seeds distributors that have no baseline yet,
	 * so re-activation never resets a legitimate baseline.
	 */
	public function seed_baselines(): void {
		if ( ! class_exists( 'GWC_Distributor' ) ) {
			return;
		}
		$ids = get_users(
			array(
				'role'   => GWC_Distributor::ROLE,
				'fields' => 'ID',
				'number' => self::NODE_CAP,
			)
		);
		foreach ( (array) $ids as $id ) {
			$id       = (int) $id;
			$existing = get_user_meta( $id, self::M_LEVEL, true );
			if ( '' !== $existing ) {
				continue;
			}
			$info = $this->rank_for( $id );
			update_user_meta( $id, self::M_LEVEL, (int) $info['index'] );
			update_user_meta( $id, self::M_RANK_NAME, (string) $info['name'] );
		}
	}

	/* ------------------------------------------------------- dashboard card */

	public function render_dashboard_card( $uid ): void {
		$uid = (int) $uid;
		if ( ! $this->is_enabled() || $uid <= 0 ) {
			return;
		}
		$info      = $this->rank_for( $uid );
		$uses_team = ! empty( $info['uses_team'] );

		echo '<div class="gw-card">';
		echo '<h3>' . esc_html__( 'Your rank', 'greenworld-core' ) . ' <span class="gw-badge gw-badge--active">' . esc_html( $info['name'] ) . '</span></h3>';

		if ( null !== $info['next'] ) {
			$pct = (int) $info['progress'];
			echo '<div style="background:#e7efe8;border-radius:999px;height:12px;overflow:hidden;margin:.5rem 0 .35rem;">';
			echo '<div style="width:' . esc_attr( (string) $pct ) . '%;height:100%;background:#1f7a3d;"></div>';
			echo '</div>';

			$bits = array();
			if ( (int) $info['points_to_next'] > 0 ) {
				$bits[] = sprintf(
					/* translators: 1: points, 2: next rank */
					__( '%1$s more points to reach %2$s', 'greenworld-core' ),
					number_format_i18n( (int) $info['points_to_next'] ),
					$info['next']['name']
				);
			}
			if ( (int) $info['refs_to_next'] > 0 ) {
				$bits[] = sprintf(
					/* translators: %d: number of referrals */
					_n( '%d more direct referral', '%d more direct referrals', (int) $info['refs_to_next'], 'greenworld-core' ),
					(int) $info['refs_to_next']
				);
			}
			if ( $uses_team && (int) $info['team_points_to_next'] > 0 ) {
				$bits[] = sprintf(
					/* translators: %s: team points */
					__( '%s more team points', 'greenworld-core' ),
					number_format_i18n( (int) $info['team_points_to_next'] )
				);
			}
			if ( $uses_team && (int) $info['team_size_to_next'] > 0 ) {
				$bits[] = sprintf(
					/* translators: %d: number of team members */
					_n( '%d more team member', '%d more team members', (int) $info['team_size_to_next'], 'greenworld-core' ),
					(int) $info['team_size_to_next']
				);
			}
			if ( empty( $bits ) ) {
				$bits[] = sprintf(
					/* translators: %s: next rank name */
					__( 'You have met the requirements for %s.', 'greenworld-core' ),
					$info['next']['name']
				);
			}
			echo '<p class="gw-sub">' . esc_html( implode( ' - ', $bits ) ) . '</p>';
		} else {
			echo '<p class="gw-sub">' . esc_html__( 'You have reached our top rank. Congratulations!', 'greenworld-core' ) . '</p>';
		}

		echo '<dl class="gw-facts" style="margin-top:.5rem;">';
		echo '<dt>' . esc_html__( 'Lifetime points', 'greenworld-core' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $info['lifetime'] ) ) . '</dd>';
		echo '<dt>' . esc_html__( 'Current balance', 'greenworld-core' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $info['balance'] ) ) . '</dd>';
		echo '<dt>' . esc_html__( 'Direct referrals', 'greenworld-core' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $info['referrals'] ) ) . '</dd>';
		if ( $uses_team ) {
			echo '<dt>' . esc_html__( 'Team size', 'greenworld-core' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $info['team_size'] ) ) . '</dd>';
			echo '<dt>' . esc_html__( 'Team points', 'greenworld-core' ) . '</dt><dd>' . esc_html( number_format_i18n( (int) $info['team_points'] ) ) . '</dd>';
		}
		echo '</dl>';

		$foot = $uses_team
			? __( 'Rank is based on your lifetime points, the distributors you have introduced, and your team volume. Keep building to reach the next tier.', 'greenworld-core' )
			: __( 'Rank is based on your lifetime points earned and the distributors you have introduced. Keep building to reach the next tier.', 'greenworld-core' );
		echo '<p class="gw-sub" style="margin-top:.6rem;">' . esc_html( $foot ) . '</p>';
		echo '</div>';
	}

	/* ---------------------------------------------------------------- admin */

	public function admin_menu(): void {
		add_users_page(
			__( 'Distributor Ranks', 'greenworld-core' ),
			__( 'Ranks', 'greenworld-core' ),
			'edit_users',
			'gwc-ranks',
			array( $this, 'render_admin' )
		);
	}

	public function render_admin(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to view distributor ranks.', 'greenworld-core' ) );
		}
		$s      = $this->settings();
		$ladder = $this->ladder();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Distributor Ranks', 'greenworld-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Ranks are calculated from your existing points ledger and referrals. Edit the ladder below to match your compensation plan; distributors see their rank and progress on their dashboard.', 'greenworld-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'gwc_ranks_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Rank engine', 'greenworld-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Show ranks on the distributor dashboard', 'greenworld-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rank-up alerts', 'greenworld-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[notify_enabled]" value="1" <?php checked( ! empty( $s['notify_enabled'] ) ); ?> /> <?php esc_html_e( 'Congratulate a distributor by WhatsApp and email the moment they reach a new rank', 'greenworld-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_notify_msg"><?php esc_html_e( 'Congratulation message', 'greenworld-core' ); ?></label></th>
						<td>
							<textarea id="gwc_notify_msg" name="<?php echo esc_attr( self::OPTION ); ?>[notify_msg]" rows="3" class="large-text"><?php echo esc_textarea( (string) $s['notify_msg'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Sent when a distributor is promoted. Use {name} for their name and {rank} for the rank they reached.', 'greenworld-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_ladder"><?php esc_html_e( 'Rank ladder', 'greenworld-core' ); ?></label></th>
						<td>
							<textarea id="gwc_ladder" name="<?php echo esc_attr( self::OPTION ); ?>[ladder_text]" rows="8" class="large-text code"><?php echo esc_textarea( (string) $s['ladder_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One rank per line, lowest first: Name | lifetime points | direct referrals | team points | team size. Every column after the name is optional (default 0). Leave team points and team size at 0 to ignore team volume. Example: Gold | 5000 | 3 | 0 | 0 or Platinum | 15000 | 5 | 20000 | 10', 'greenworld-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_team_depth"><?php esc_html_e( 'Team depth', 'greenworld-core' ); ?></label></th>
						<td>
							<input type="number" id="gwc_team_depth" name="<?php echo esc_attr( self::OPTION ); ?>[team_depth]" value="<?php echo esc_attr( (string) (int) $s['team_depth'] ); ?>" min="1" max="6" class="small-text" />
							<p class="description"><?php esc_html_e( 'How many levels below a distributor count towards team volume (1 = direct only, up to 6). Only used when a rank sets a team threshold.', 'greenworld-core' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Current ladder', 'greenworld-core' ); ?></h2>
			<?php $uses_team = $this->ladder_uses_team(); ?>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr>
					<th><?php esc_html_e( 'Rank', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Lifetime points', 'greenworld-core' ); ?></th>
					<th><?php esc_html_e( 'Direct referrals', 'greenworld-core' ); ?></th>
					<?php if ( $uses_team ) : ?>
						<th><?php esc_html_e( 'Team points', 'greenworld-core' ); ?></th>
						<th><?php esc_html_e( 'Team size', 'greenworld-core' ); ?></th>
					<?php endif; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $ladder as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['name'] ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( (int) $r['points'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $r['referrals'] ) ); ?></td>
						<?php if ( $uses_team ) : ?>
							<td><?php echo esc_html( number_format_i18n( (int) $r['team_points'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $r['team_size'] ) ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Distributor roster', 'greenworld-core' ); ?></h2>
			<?php $this->render_roster(); ?>
		</div>
		<?php
	}

	private function render_roster(): void {
		if ( ! class_exists( 'GWC_Distributor' ) ) {
			echo '<p>' . esc_html__( 'Distributor module unavailable.', 'greenworld-core' ) . '</p>';
			return;
		}
		$uses_team = $this->ladder_uses_team();
		$limit     = $uses_team ? 100 : 200;
		$dists     = get_users(
			array(
				'role'    => GWC_Distributor::ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => $limit,
			)
		);
		if ( empty( $dists ) ) {
			echo '<p>' . esc_html__( 'No distributors yet.', 'greenworld-core' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:1040px"><thead><tr>';
		echo '<th>' . esc_html__( 'Distributor', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Rank', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Lifetime points', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Balance', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Direct referrals', 'greenworld-core' ) . '</th>';
		if ( $uses_team ) {
			echo '<th>' . esc_html__( 'Team size', 'greenworld-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Team points', 'greenworld-core' ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $dists as $d ) {
			$id     = (int) $d->ID;
			$status = GWC_Distributor::status( $id );
			$info   = $this->rank_for( $id );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $d->display_name ) . '</strong><br /><span style="color:#6a776e;font-size:.85em">' . esc_html( $d->user_email ) . '</span></td>';
			echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
			echo '<td><strong>' . esc_html( $info['name'] ) . '</strong></td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $info['lifetime'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $info['balance'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (int) $info['referrals'] ) ) . '</td>';
			if ( $uses_team ) {
				echo '<td>' . esc_html( number_format_i18n( (int) $info['team_size'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (int) $info['team_points'] ) ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
		$note = $uses_team
			? sprintf(
				/* translators: %d: roster limit */
				esc_html__( 'Showing up to %d distributors. Team figures are cached and refresh within about 15 minutes of new batches or referrals.', 'greenworld-core' ),
				(int) $limit
			)
			: sprintf(
				/* translators: %d: roster limit */
				esc_html__( 'Showing up to %d distributors. Figures refresh a few minutes after new batches or referrals.', 'greenworld-core' ),
				(int) $limit
			);
		echo '<p class="description">' . $note . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
