<?php
/**
 * Consultation follow-up automation for Green World Health.
 *
 * Turns the consultation case pipeline into a gentle, compliant follow-up
 * cadence, built entirely on top of the existing gw_consultation records and
 * the case layer (GWC_Cases). No new data store, no duplication:
 *
 *   1. Acknowledgement - shortly after a request is submitted, the customer
 *      receives "we have received your request" with their case number.
 *   2. First follow-up - N days (default 2) after an advisor first engages
 *      the case (status Contacted / Waiting / Advice / Follow-up):
 *      "how are you getting on?".
 *   3. Second follow-up - M days (default 7) after first engagement:
 *      "would you like help with anything else?".
 *
 * Closing a case stops all further follow-ups automatically. A one-time
 * "since" stamp means enabling the feature never messages the historical
 * back-catalogue. Delivery reuses the plugin's WhatsApp (Meta Cloud API) and
 * email channels. Messages are wellness-only and carry no medical advice.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Followup {

	private static $instance = null;

	const CRON_HOOK = 'gwc_followup_scan';
	const OPTION    = 'gwc_followup';
	const SINCE     = 'gwc_fu_since';
	const LAST_RUN  = 'gwc_fu_last_run';
	const COUNTS    = 'gwc_fu_counts';

	/* Per-post meta this module owns. */
	const M_ACK       = '_gw_fu_ack_sent';
	const M_CONTACTED = '_gw_fu_contacted_at';
	const M_FU1       = '_gw_fu_1_sent';
	const M_FU2       = '_gw_fu_2_sent';

	/* Keys owned by the theme / case layer - read only, never written here. */
	const CPT       = 'gw_consultation';
	const M_STATUS  = '_gw_case_status';
	const M_HISTORY = '_gw_case_history';
	const M_NAME    = '_gw_c_name';
	const M_PHONE   = '_gw_c_phone';
	const M_EMAIL   = '_gw_c_email';

	/** Statuses that mean an advisor has actively engaged the case. */
	private static function engaged_statuses(): array {
		return array( 'contacted', 'waiting', 'advice', 'followup' );
	}

	public static function instance(): GWC_Followup {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_filter( 'cron_schedules', array( $this, 'cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'ensure_scheduled' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* ------------------------------------------------------------- scheduling */

	public function cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules['gwc_15min'] ) ) {
			$schedules['gwc_15min'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes (Green World)', 'greenworld-core' ),
			);
		}
		return $schedules;
	}

	private function stamp_since(): void {
		if ( false === get_option( self::SINCE, false ) ) {
			update_option( self::SINCE, time(), false );
		}
	}

	/** Self-healing: keep the scan scheduled to match the enabled setting. */
	public function ensure_scheduled(): void {
		$this->stamp_since();
		$enabled   = ! empty( $this->settings()['enabled'] );
		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + 300, 'gwc_15min', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/** Called from GWC_Plugin::activate(). */
	public function schedule(): void {
		$this->stamp_since();
		if ( ! empty( $this->settings()['enabled'] ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'gwc_15min', self::CRON_HOOK );
		}
	}

	/* --------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'     => 1,
			'ack_enabled' => 1,
			'channel'     => 'both',
			'fu1_days'    => 2,
			'fu2_days'    => 7,
			'ack_msg'     => __( 'Hello %1$s, thank you for contacting Green World Health. We have received your consultation request (%2$s) and one of our wellness advisors will be in touch with you soon. This is general wellness support, not a medical diagnosis.', 'greenworld-core' ),
			'fu1_msg'     => __( 'Hello %1$s, this is Green World Health following up on your consultation (%2$s). How are you getting on? Reply any time if you would like further help.', 'greenworld-core' ),
			'fu2_msg'     => __( 'Hello %1$s, this is Green World Health. Would you like us to help you with anything else regarding your consultation (%2$s)? We are happy to assist whenever you need us.', 'greenworld-core' ),
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
		register_setting( 'gwc_followup_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();

		$out['enabled']     = empty( $input['enabled'] ) ? 0 : 1;
		$out['ack_enabled'] = empty( $input['ack_enabled'] ) ? 0 : 1;

		$channel        = isset( $input['channel'] ) ? sanitize_key( (string) $input['channel'] ) : 'both';
		$out['channel'] = in_array( $channel, array( 'both', 'whatsapp', 'email' ), true ) ? $channel : 'both';

		$fu1 = isset( $input['fu1_days'] ) ? absint( $input['fu1_days'] ) : $d['fu1_days'];
		$fu2 = isset( $input['fu2_days'] ) ? absint( $input['fu2_days'] ) : $d['fu2_days'];
		$out['fu1_days'] = max( 1, min( 60, $fu1 ) );
		$out['fu2_days'] = max( $out['fu1_days'] + 1, min( 120, $fu2 ) );

		foreach ( array( 'ack_msg', 'fu1_msg', 'fu2_msg' ) as $k ) {
			$val        = isset( $input[ $k ] ) ? sanitize_textarea_field( (string) $input[ $k ] ) : '';
			$out[ $k ]  = '' !== $val ? $val : $d[ $k ];
		}

		return $out;
	}

	/* -------------------------------------------------------------- cron work */

	public function run(): void {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}

		$since  = (int) get_option( self::SINCE, 0 );
		$counts = get_option( self::COUNTS, array() );
		$counts = is_array( $counts ) ? $counts : array();
		$ack    = isset( $counts['ack'] ) ? (int) $counts['ack'] : 0;
		$fu1    = isset( $counts['fu1'] ) ? (int) $counts['fu1'] : 0;
		$fu2    = isset( $counts['fu2'] ) ? (int) $counts['fu2'] : 0;

		if ( ! empty( $s['ack_enabled'] ) ) {
			$ack += $this->run_acks( $s, $since );
		}

		$res  = $this->run_followups( $s, $since );
		$fu1 += $res[0];
		$fu2 += $res[1];

		update_option( self::COUNTS, array( 'ack' => $ack, 'fu1' => $fu1, 'fu2' => $fu2 ), false );
		update_option( self::LAST_RUN, time(), false );
	}

	private function run_acks( array $s, int $since ): int {
		$sent = 0;
		$ids  = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => array( 'private', 'publish' ),
				'posts_per_page'   => 25,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'fields'           => 'ids',
				'meta_query'       => array(
					array(
						'key'     => self::M_ACK,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$created = (int) get_post_time( 'U', true, $id );
			if ( $created > 0 && $created < $since ) {
				// Predates the feature: close out so it is never scanned again.
				update_post_meta( $id, self::M_ACK, 'skipped' );
				continue;
			}
			$msg = $this->message( $s['ack_msg'], $id );
			if ( '' !== $msg && $this->deliver( $id, $msg, $s['channel'], __( 'We have received your Green World consultation', 'greenworld-core' ) ) ) {
				update_post_meta( $id, self::M_ACK, (string) time() );
				$sent++;
			} else {
				update_post_meta( $id, self::M_ACK, 'skipped' );
			}
		}

		return $sent;
	}

	/**
	 * @return array{0:int,1:int} [ first-follow-ups sent, second-follow-ups sent ]
	 */
	private function run_followups( array $s, int $since ): array {
		$fu1_sent = 0;
		$fu2_sent = 0;

		$ids = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => array( 'private', 'publish' ),
				'posts_per_page'   => 25,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'fields'           => 'ids',
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => self::M_STATUS,
						'value'   => self::engaged_statuses(),
						'compare' => 'IN',
					),
					array(
						'key'     => self::M_FU2,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$now  = time();
		$fu1d = (int) $s['fu1_days'] * DAY_IN_SECONDS;
		$fu2d = (int) $s['fu2_days'] * DAY_IN_SECONDS;

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$anchor = $this->anchor( $id );
			if ( $anchor <= 0 ) {
				continue;
			}
			if ( $anchor < $since ) {
				// Engaged before the feature existed: never message; close out.
				update_post_meta( $id, self::M_FU1, 'skipped' );
				update_post_meta( $id, self::M_FU2, 'skipped' );
				continue;
			}

			$fu1_done = (string) get_post_meta( $id, self::M_FU1, true );
			$fu2_done = (string) get_post_meta( $id, self::M_FU2, true );

			if ( '' === $fu1_done && $now >= $anchor + $fu1d ) {
				$msg = $this->message( $s['fu1_msg'], $id );
				if ( '' !== $msg && $this->deliver( $id, $msg, $s['channel'], __( 'Following up on your Green World consultation', 'greenworld-core' ) ) ) {
					update_post_meta( $id, self::M_FU1, (string) $now );
					$fu1_sent++;
				} else {
					update_post_meta( $id, self::M_FU1, 'skipped' );
				}
			}

			if ( '' === $fu2_done && $now >= $anchor + $fu2d ) {
				$msg = $this->message( $s['fu2_msg'], $id );
				if ( '' !== $msg && $this->deliver( $id, $msg, $s['channel'], __( 'Green World consultation - anything else we can help with?', 'greenworld-core' ) ) ) {
					update_post_meta( $id, self::M_FU2, (string) $now );
					$fu2_sent++;
				} else {
					update_post_meta( $id, self::M_FU2, 'skipped' );
				}
			}
		}

		return array( $fu1_sent, $fu2_sent );
	}

	/**
	 * Timestamp the case was first engaged. Stamped once, then cached in meta.
	 * Derived from the case history's first move into an engaged status.
	 */
	private function anchor( int $id ): int {
		$stamp = (int) get_post_meta( $id, self::M_CONTACTED, true );
		if ( $stamp > 0 ) {
			return $stamp;
		}

		$engaged = self::engaged_statuses();
		$history = get_post_meta( $id, self::M_HISTORY, true );
		$found   = 0;
		if ( is_array( $history ) ) {
			foreach ( $history as $h ) {
				if ( is_array( $h ) && isset( $h['to'], $h['t'] ) && in_array( (string) $h['to'], $engaged, true ) ) {
					$found = (int) $h['t'];
					break; // History is chronological; first engagement wins.
				}
			}
		}
		if ( $found <= 0 ) {
			// Status is engaged but no timestamped history (e.g. set in code).
			$found = time();
		}

		update_post_meta( $id, self::M_CONTACTED, $found );
		return $found;
	}

	/* ---------------------------------------------------------- messaging out */

	private function message( string $template, int $id ): string {
		$name = (string) get_post_meta( $id, self::M_NAME, true );
		$name = '' !== $name ? $name : __( 'there', 'greenworld-core' );
		if ( class_exists( 'GWC_Cases' ) && is_callable( array( 'GWC_Cases', 'number' ) ) ) {
			$number = GWC_Cases::number( $id );
		} else {
			$number = 'GW-' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT );
		}
		return trim( sprintf( $template, $name, $number ) );
	}

	/**
	 * Send on the configured channel(s). Returns true when there was at least
	 * one valid recipient to deliver to (so a target-less case is closed out
	 * rather than retried forever).
	 */
	private function deliver( int $id, string $message, string $channel, string $email_subject ): bool {
		$phone = (string) get_post_meta( $id, self::M_PHONE, true );
		$email = (string) get_post_meta( $id, self::M_EMAIL, true );

		$use_wa = ( 'both' === $channel || 'whatsapp' === $channel );
		$use_em = ( 'both' === $channel || 'email' === $channel );

		$attempted = false;

		if ( $use_wa && '' !== $phone && class_exists( 'GWC_WhatsApp' ) && is_callable( array( 'GWC_WhatsApp', 'send_text' ) ) ) {
			GWC_WhatsApp::send_text( $phone, $message );
			$attempted = true;
		}
		if ( $use_em && '' !== $email && is_email( $email ) ) {
			wp_mail( $email, $email_subject, $message );
			$attempted = true;
		}

		return $attempted;
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . self::CPT,
			__( 'Consultation follow-ups', 'greenworld-core' ),
			__( 'Follow-ups', 'greenworld-core' ),
			'manage_options',
			'gwc-followups',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s        = $this->settings();
		$last_run = (int) get_option( self::LAST_RUN, 0 );
		$next     = wp_next_scheduled( self::CRON_HOOK );
		$counts   = get_option( self::COUNTS, array() );
		$counts   = is_array( $counts ) ? $counts : array();
		$c_ack    = isset( $counts['ack'] ) ? (int) $counts['ack'] : 0;
		$c_fu1    = isset( $counts['fu1'] ) ? (int) $counts['fu1'] : 0;
		$c_fu2    = isset( $counts['fu2'] ) ? (int) $counts['fu2'] : 0;
		$fmt      = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Consultation follow-up automation', 'greenworld-core' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Automatic, wellness-only messages built on your existing consultation cases. An acknowledgement goes out shortly after a request arrives; the two follow-ups are timed from when an advisor first actions the case (Contacted / Waiting / Advice / Follow-up). Closing a case stops its follow-ups.', 'greenworld-core' ); ?>
			</p>

			<div class="notice notice-info inline" style="padding:10px 12px;">
				<strong><?php esc_html_e( 'Status', 'greenworld-core' ); ?>:</strong>
				<?php
				printf(
					/* translators: 1: last run, 2: next run */
					esc_html__( 'Last scan: %1$s. Next scan: %2$s.', 'greenworld-core' ),
					$last_run ? esc_html( wp_date( $fmt, $last_run ) ) : esc_html__( 'not yet run', 'greenworld-core' ),
					$next ? esc_html( wp_date( $fmt, (int) $next ) ) : esc_html__( 'not scheduled', 'greenworld-core' )
				);
				?>
				&nbsp;|&nbsp;
				<?php
				printf(
					/* translators: 1: ack count, 2: first follow-up count, 3: second follow-up count */
					esc_html__( 'Sent so far - acknowledgements: %1$d, first follow-ups: %2$d, second follow-ups: %3$d.', 'greenworld-core' ),
					$c_ack,
					$c_fu1,
					$c_fu2
				);
				?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'gwc_followup_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Follow-up automation', 'greenworld-core' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable the scheduled scan (acknowledgement + follow-ups)', 'greenworld-core' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Acknowledgement', 'greenworld-core' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[ack_enabled]" value="1" <?php checked( ! empty( $s['ack_enabled'] ) ); ?> />
								<?php esc_html_e( 'Send an acknowledgement shortly after a consultation is submitted', 'greenworld-core' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Channel', 'greenworld-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[channel]">
								<option value="both" <?php selected( $s['channel'], 'both' ); ?>><?php esc_html_e( 'WhatsApp and email', 'greenworld-core' ); ?></option>
								<option value="whatsapp" <?php selected( $s['channel'], 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp only', 'greenworld-core' ); ?></option>
								<option value="email" <?php selected( $s['channel'], 'email' ); ?>><?php esc_html_e( 'Email only', 'greenworld-core' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'WhatsApp uses the number captured on the consultation and your Meta Cloud API settings. Email uses the address on the consultation.', 'greenworld-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'First follow-up delay', 'greenworld-core' ); ?></th>
						<td>
							<input type="number" min="1" max="60" name="<?php echo esc_attr( self::OPTION ); ?>[fu1_days]" value="<?php echo esc_attr( (string) $s['fu1_days'] ); ?>" class="small-text" />
							<?php esc_html_e( 'days after the advisor first actions the case', 'greenworld-core' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Second follow-up delay', 'greenworld-core' ); ?></th>
						<td>
							<input type="number" min="2" max="120" name="<?php echo esc_attr( self::OPTION ); ?>[fu2_days]" value="<?php echo esc_attr( (string) $s['fu2_days'] ); ?>" class="small-text" />
							<?php esc_html_e( 'days after the advisor first actions the case (must be later than the first)', 'greenworld-core' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Acknowledgement message', 'greenworld-core' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[ack_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['ack_msg'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'First follow-up message', 'greenworld-core' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[fu1_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['fu1_msg'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Second follow-up message', 'greenworld-core' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[fu2_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['fu2_msg'] ); ?></textarea></td>
					</tr>
				</table>
				<p class="description"><?php esc_html_e( 'Placeholders: %1$s = customer name, %2$s = case number. Keep messages general and supportive - never diagnostic.', 'greenworld-core' ); ?></p>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Upcoming follow-ups', 'greenworld-core' ); ?></h2>
			<?php $this->render_preview( $s, $fmt ); ?>
		</div>
		<?php
	}

	private function render_preview( array $s, string $fmt ): void {
		$ids = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => array( 'private', 'publish' ),
				'posts_per_page'   => 20,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'fields'           => 'ids',
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => self::M_STATUS,
						'value'   => self::engaged_statuses(),
						'compare' => 'IN',
					),
					array(
						'key'     => self::M_FU2,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			echo '<p>' . esc_html__( 'No cases are currently waiting on a follow-up.', 'greenworld-core' ) . '</p>';
			return;
		}

		$statuses = class_exists( 'GWC_Cases' ) ? GWC_Cases::statuses() : array();
		$fu1d     = (int) $s['fu1_days'] * DAY_IN_SECONDS;
		$fu2d     = (int) $s['fu2_days'] * DAY_IN_SECONDS;

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Case', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'First engaged', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'First follow-up', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Second follow-up', 'greenworld-core' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$anchor = (int) get_post_meta( $id, self::M_CONTACTED, true );
			if ( $anchor <= 0 ) {
				$anchor = $this->anchor_readonly( $id );
			}
			$status = (string) get_post_meta( $id, self::M_STATUS, true );
			$label  = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
			$name   = (string) get_post_meta( $id, self::M_NAME, true );
			$number = ( class_exists( 'GWC_Cases' ) && is_callable( array( 'GWC_Cases', 'number' ) ) ) ? GWC_Cases::number( $id ) : (string) $id;

			$fu1_done = (string) get_post_meta( $id, self::M_FU1, true );
			$fu2_done = (string) get_post_meta( $id, self::M_FU2, true );

			echo '<tr>';
			echo '<td>' . esc_html( $number ) . '</td>';
			echo '<td>' . esc_html( '' !== $name ? $name : '-' ) . '</td>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . ( $anchor > 0 ? esc_html( wp_date( $fmt, $anchor ) ) : '-' ) . '</td>';
			echo '<td>' . esc_html( $this->cell( $fu1_done, $anchor, $fu1d, $fmt ) ) . '</td>';
			echo '<td>' . esc_html( $this->cell( $fu2_done, $anchor, $fu2d, $fmt ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function cell( string $done, int $anchor, int $offset, string $fmt ): string {
		if ( 'skipped' === $done ) {
			return __( 'skipped', 'greenworld-core' );
		}
		if ( '' !== $done && ctype_digit( $done ) ) {
			/* translators: %s: date sent */
			return sprintf( __( 'sent %s', 'greenworld-core' ), wp_date( $fmt, (int) $done ) );
		}
		if ( $anchor <= 0 ) {
			return '-';
		}
		$due = $anchor + $offset;
		/* translators: %s: due date */
		return sprintf( __( 'due %s', 'greenworld-core' ), wp_date( $fmt, $due ) );
	}

	/** Read-only anchor derivation for the preview (does not write meta). */
	private function anchor_readonly( int $id ): int {
		$engaged = self::engaged_statuses();
		$history = get_post_meta( $id, self::M_HISTORY, true );
		if ( is_array( $history ) ) {
			foreach ( $history as $h ) {
				if ( is_array( $h ) && isset( $h['to'], $h['t'] ) && in_array( (string) $h['to'], $engaged, true ) ) {
					return (int) $h['t'];
				}
			}
		}
		return 0;
	}
}
