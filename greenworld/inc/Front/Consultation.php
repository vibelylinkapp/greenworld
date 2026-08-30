<?php
declare( strict_types=1 );

namespace GreenWorld\Front;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Online health consultation intake.
 *
 * Lets a visitor describe a health concern so the Green World team can follow
 * up and recommend suitable products. Submissions are sensitive, so they are:
 *  - stored in a PRIVATE custom post type visible only to shop managers,
 *  - never shown on the public site,
 *  - gated behind an explicit consent checkbox, and
 *  - clearly framed as guidance, not a medical diagnosis or emergency service.
 */
final class Consultation implements Bootable {

	public function boot(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'wp_ajax_gw_consult', [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_gw_consult', [ $this, 'handle' ] );
		add_shortcode( 'gw_health_consultation', [ $this, 'form' ] );
		add_action( 'add_meta_boxes', [ $this, 'metabox' ] );
	}

	public function register_cpt(): void {
		register_post_type(
			'gw_consultation',
			array(
				'labels'          => array(
					'name'          => __( 'Consultations', 'greenworld' ),
					'singular_name' => __( 'Consultation', 'greenworld' ),
					'menu_name'     => __( 'Consultations', 'greenworld' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-heart',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}

	private function opt( string $key, string $default = '' ): string {
		$v = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
		return $v !== '' ? $v : $default;
	}

	public function handle(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'greenworld' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'greenworld' ) ), 400 );
		}
		// Honeypot: real users never fill this hidden field. Feign success so
		// bots do not learn they were caught, but store nothing.
		if ( ! empty( $_POST['gw_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Thank you. Your request has been received and our team will contact you shortly.', 'greenworld' ) ) );
		}
		// Time trap: genuine submissions take more than a few seconds to complete.
		$ts = isset( $_POST['gw_ts'] ) ? (int) $_POST['gw_ts'] : 0;
		if ( $ts > 0 && ( time() - $ts ) < 3 ) {
			wp_send_json_error( array( 'message' => __( 'Please take a moment to complete the form, then try again.', 'greenworld' ) ), 429 );
		}
		$hp = isset( $_POST['gw_hp'] ) ? trim( (string) wp_unslash( $_POST['gw_hp'] ) ) : '';
		if ( strlen( $hp ) > 0 ) {
			wp_send_json_error( array( 'message' => __( 'Your submission could not be processed. Please try again.', 'greenworld' ) ), 422 );
		}
		$rl_key = 'gw_consult_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0' );
		$rl     = (int) get_transient( $rl_key );
		if ( $rl >= 8 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests from your connection. Please wait a few minutes and try again.', 'greenworld' ) ), 429 );
		}
		set_transient( $rl_key, $rl + 1, 10 * MINUTE_IN_SECONDS );
		if ( empty( $_POST['consent'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please tick the consent box so we can respond to you.', 'greenworld' ) ), 422 );
		}
		$name    = $this->opt( 'name' );
		$phone   = $this->opt( 'phone' );
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$age     = $this->opt( 'age' );
		$gender  = $this->opt( 'gender' );
		$prefer  = $this->opt( 'prefer' );
		$using   = $this->opt( 'using' );
			$ctype   = $this->opt( 'ctype' );
			$focus   = $this->opt( 'focus' );
		$concern = isset( $_POST['concern'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['concern'] ) ) : '';

		if ( $name === '' || $phone === '' || $concern === '' ) {
			wp_send_json_error( array( 'message' => __( 'Please add your name, phone number and a short description of your concern.', 'greenworld' ) ), 422 );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'gw_consultation',
				'post_status' => 'private',
				'post_title'  => sprintf( '%s — %s', $name, current_time( 'Y-m-d H:i' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
			wp_send_json_error( array( 'message' => __( 'We could not save your request. Please call us instead.', 'greenworld' ) ), 500 );
		}
		$pid = (int) $post_id;
		update_post_meta( $pid, '_gw_c_name', $name );
		update_post_meta( $pid, '_gw_c_phone', $phone );
		update_post_meta( $pid, '_gw_c_email', $email );
		update_post_meta( $pid, '_gw_c_age', $age );
		update_post_meta( $pid, '_gw_c_gender', $gender );
		update_post_meta( $pid, '_gw_c_prefer', $prefer );
		update_post_meta( $pid, '_gw_c_using', $using );
			update_post_meta( $pid, '_gw_c_type', $ctype );
			update_post_meta( $pid, '_gw_c_focus', $focus );
		update_post_meta( $pid, '_gw_c_concern', $concern );
		update_post_meta( $pid, '_gw_c_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '' );

		$this->notify( $name, $phone, $email, $age, $gender, $prefer, $using, $concern );

		/**
		 * Let companion plugins (e.g. WhatsApp alerts) react to a new consultation.
		 */
		do_action(
			'greenworld/consultation_submitted',
			array(
				'name'    => $name,
				'phone'   => $phone,
				'email'   => $email,
				'age'     => $age,
				'gender'  => $gender,
				'prefer'  => $prefer,
				'using'   => $using,
				'concern' => $concern,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Thank you. Your request has been received and our team will contact you shortly.', 'greenworld' ) ) );
	}

	private function notify( string $name, string $phone, string $email, string $age, string $gender, string $prefer, string $using, string $concern ): void {
		$to   = get_option( 'admin_email' );
		$body = sprintf(
			"New health consultation request.\n\nName: %s\nPhone: %s\nEmail: %s\nAge: %s\nGender: %s\nPreferred contact: %s\nCurrently using: %s\n\nConcern:\n%s\n\nReview under Consultations in wp-admin.",
			$name, $phone, $email !== '' ? $email : '-', $age !== '' ? $age : '-', $gender !== '' ? $gender : '-', $prefer !== '' ? $prefer : '-', $using !== '' ? $using : '-', $concern
		);
		wp_mail( $to, __( 'New health consultation request', 'greenworld' ), $body );
	}

	/**
	 * @param array<string,mixed> $atts
	 */
	public function form( $atts = array() ): string {
		$genders = array( '' => __( 'Prefer not to say', 'greenworld' ), 'female' => __( 'Female', 'greenworld' ), 'male' => __( 'Male', 'greenworld' ) );
		$prefers = array( 'phone' => __( 'Phone call', 'greenworld' ), 'whatsapp' => __( 'WhatsApp', 'greenworld' ), 'email' => __( 'Email', 'greenworld' ) );
		$ctypes  = array( 'product' => __( 'Product consultation', 'greenworld' ), 'assessment' => __( 'Health assessment', 'greenworld' ), 'pickup' => __( 'Product pickup', 'greenworld' ), 'followup' => __( 'Follow-up visit', 'greenworld' ), 'other' => __( 'Other', 'greenworld' ) );
		$focuses = array( '' => __( 'Select a concern', 'greenworld' ), 'weight' => __( 'Weight management', 'greenworld' ), 'immunity' => __( 'Immunity boost', 'greenworld' ), 'diabetes' => __( 'Diabetes care', 'greenworld' ), 'heart' => __( 'Heart health', 'greenworld' ), 'digestive' => __( 'Digestive care', 'greenworld' ), 'mens' => __( "Men's wellness", 'greenworld' ), 'womens' => __( "Women's wellness", 'greenworld' ), 'general' => __( 'General wellness', 'greenworld' ), 'other' => __( 'Other', 'greenworld' ) );
		$wa      = preg_replace( '/[^0-9]/', '', (string) get_theme_mod( 'gw_whatsapp', '254723579873' ) );
		ob_start();
		?>
		<div class="gw-consult-wrap">
			<form class="gw-consult" data-gw-consult-form novalidate>
				<div class="gw-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
					<label>Leave this field empty <input type="text" name="gw_website" tabindex="-1" autocomplete="off" value="" /></label>
				</div>
				<input type="hidden" name="gw_ts" value="<?php echo esc_attr( (string) time() ); ?>" />
				<input type="text" name="gw_hp" tabindex="-1" autocomplete="off" value="" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" />

				<div class="gw-consult__group">
					<h3 class="gw-consult__legend"><span class="gw-consult__step">1</span><?php esc_html_e( 'Your details', 'greenworld' ); ?></h3>
					<div class="gw-consult__row">
						<p class="gw-field"><label for="gwc-name"><?php esc_html_e( 'Full name', 'greenworld' ); ?> <span class="required">*</span></label><input id="gwc-name" name="name" type="text" required autocomplete="name" placeholder="<?php esc_attr_e( 'e.g. Jane Wanjiru', 'greenworld' ); ?>" /></p>
						<p class="gw-field"><label for="gwc-phone"><?php esc_html_e( 'Phone number', 'greenworld' ); ?> <span class="required">*</span></label><input id="gwc-phone" name="phone" type="tel" required autocomplete="tel" placeholder="07XX XXX XXX" /></p>
					</div>
					<div class="gw-consult__row">
						<p class="gw-field"><label for="gwc-email"><?php esc_html_e( 'Email (optional)', 'greenworld' ); ?></label><input id="gwc-email" name="email" type="email" autocomplete="email" /></p>
						<p class="gw-field gw-field--sm"><label for="gwc-age"><?php esc_html_e( 'Age', 'greenworld' ); ?></label><input id="gwc-age" name="age" type="number" min="0" max="120" inputmode="numeric" /></p>
						<p class="gw-field gw-field--sm"><label for="gwc-gender"><?php esc_html_e( 'Gender', 'greenworld' ); ?></label><select id="gwc-gender" name="gender"><?php foreach ( $genders as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); } ?></select></p>
					</div>
				</div>

				<div class="gw-consult__group">
					<h3 class="gw-consult__legend"><span class="gw-consult__step">2</span><?php esc_html_e( 'What would you like help with', 'greenworld' ); ?></h3>
					<div class="gw-consult__row">
						<p class="gw-field"><label for="gwc-ctype"><?php esc_html_e( 'Consultation type', 'greenworld' ); ?></label><select id="gwc-ctype" name="ctype"><?php foreach ( $ctypes as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); } ?></select></p>
						<p class="gw-field"><label for="gwc-focus"><?php esc_html_e( 'Primary health concern', 'greenworld' ); ?></label><select id="gwc-focus" name="focus"><?php foreach ( $focuses as $k => $v ) { printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $v ) ); } ?></select></p>
					</div>
					<p class="gw-field"><label for="gwc-concern"><?php esc_html_e( 'Tell us a little more', 'greenworld' ); ?> <span class="required">*</span></label><textarea id="gwc-concern" name="concern" rows="4" required placeholder="<?php esc_attr_e( 'For example: I have low energy and trouble sleeping, and would like natural support.', 'greenworld' ); ?>"></textarea></p>
					<p class="gw-field"><label for="gwc-using"><?php esc_html_e( 'Products or medication you currently take (optional)', 'greenworld' ); ?></label><input id="gwc-using" name="using" type="text" /></p>
				</div>

				<div class="gw-consult__group">
					<h3 class="gw-consult__legend"><span class="gw-consult__step">3</span><?php esc_html_e( 'How we reach you', 'greenworld' ); ?></h3>
					<fieldset class="gw-consult__prefer"><legend class="screen-reader-text"><?php esc_html_e( 'Preferred contact method', 'greenworld' ); ?></legend>
						<?php $first = true; foreach ( $prefers as $k => $v ) : ?>
							<label class="gw-radio"><input type="radio" name="prefer" value="<?php echo esc_attr( $k ); ?>" <?php checked( $first, true ); ?> /> <span><?php echo esc_html( $v ); ?></span></label>
						<?php $first = false; endforeach; ?>
					</fieldset>
					<label class="gw-consult__consent"><input type="checkbox" name="consent" value="1" /> <span><?php esc_html_e( 'I consent to Green World Health Solutions contacting me about my request and storing these details to respond. This is general wellness guidance, not a medical diagnosis or emergency service.', 'greenworld' ); ?></span></label>
					<div class="gw-consult__actions">
						<button type="submit" class="button gw-consult__submit"><?php esc_html_e( 'Send my request', 'greenworld' ); ?></button>
						<span class="gw-consult__status" role="status" aria-live="polite"></span>
					</div>
					<p class="gw-consult__note"><?php esc_html_e( 'If this is a medical emergency, please contact your doctor or nearest hospital immediately.', 'greenworld' ); ?></p>
				</div>
			</form>

			<aside class="gw-consult-aside">
				<div class="gw-consult-aside__card">
					<span class="gw-consult-aside__badge"><?php esc_html_e( 'Free and confidential', 'greenworld' ); ?></span>
					<h3><?php esc_html_e( 'How it works', 'greenworld' ); ?></h3>
					<ol class="gw-consult-aside__steps">
						<li><strong><?php esc_html_e( 'Share your concern', 'greenworld' ); ?></strong><span><?php esc_html_e( 'Tell us what you would like support with.', 'greenworld' ); ?></span></li>
						<li><strong><?php esc_html_e( 'We match products', 'greenworld' ); ?></strong><span><?php esc_html_e( 'Our consultants suggest suitable options.', 'greenworld' ); ?></span></li>
						<li><strong><?php esc_html_e( 'Guidance your way', 'greenworld' ); ?></strong><span><?php esc_html_e( 'We reply on your preferred channel.', 'greenworld' ); ?></span></li>
					</ol>
				</div>
				<div class="gw-consult-aside__card gw-consult-aside__talk">
					<h3><?php esc_html_e( 'Prefer to talk now', 'greenworld' ); ?></h3>
					<a class="button gw-btn--gold gw-consult-aside__wa" href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'greenworld' ); ?></a>
					<p class="gw-consult-aside__line"><a href="tel:+254723579873">0723 579 873</a></p>
					<p class="gw-consult-aside__line"><a href="mailto:info@greenworldhealth.co.ke">info@greenworldhealth.co.ke</a></p>
					<p class="gw-consult-aside__hours"><?php esc_html_e( 'Mon to Sat, 8:30 AM to 6:00 PM', 'greenworld' ); ?></p>
				</div>
			</aside>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function metabox(): void {
		add_meta_box( 'gw_consult_details', __( 'Consultation details', 'greenworld' ), [ $this, 'render_metabox' ], 'gw_consultation', 'normal', 'high' );
	}

	public function render_metabox( \WP_Post $post ): void {
		$fields = array(
			'_gw_c_name'    => __( 'Name', 'greenworld' ),
			'_gw_c_phone'   => __( 'Phone', 'greenworld' ),
			'_gw_c_email'   => __( 'Email', 'greenworld' ),
			'_gw_c_age'     => __( 'Age', 'greenworld' ),
			'_gw_c_gender'  => __( 'Gender', 'greenworld' ),
			'_gw_c_prefer'  => __( 'Preferred contact', 'greenworld' ),
			'_gw_c_using'   => __( 'Currently using', 'greenworld' ),
			'_gw_c_type'    => __( 'Consultation type', 'greenworld' ),
			'_gw_c_focus'   => __( 'Primary concern', 'greenworld' ),
			'_gw_c_concern' => __( 'Concern', 'greenworld' ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$val = (string) get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:180px;text-align:left">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $val ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
