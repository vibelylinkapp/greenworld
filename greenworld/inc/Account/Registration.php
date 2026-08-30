<?php
declare( strict_types=1 );

namespace GreenWorld\Account;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Dual registration: shoppers can register as a Customer or apply as a
 * Distributor (the Green World direct-selling model). Adds a distributor role,
 * extends the WooCommerce registration form, stores applicant details, and
 * exposes a [gw_register] shortcode for the Become a Distributor page.
 *
 * No pricing/commission logic lives here - it records intent and applicant
 * details, assigns a role, and notifies the store owner to follow up.
 */
final class Registration implements Bootable {

	public function boot(): void {
		add_action( 'init', [ $this, 'register_role' ] );
		add_action( 'woocommerce_register_form_start', [ $this, 'type_toggle' ] );
		add_action( 'woocommerce_register_form', [ $this, 'fields' ] );
		add_action( 'woocommerce_register_post', [ $this, 'validate' ], 10, 3 );
		add_action( 'woocommerce_created_customer', [ $this, 'save' ], 10, 3 );
		add_shortcode( 'gw_register', [ $this, 'shortcode' ] );
		add_filter( 'manage_users_columns', [ $this, 'user_column' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'user_column_value' ], 10, 3 );
	}

	/**
	 * Register a Distributor role that mirrors the customer capabilities.
	 */
	public function register_role(): void {
		if ( get_role( 'gw_distributor' ) !== null ) {
			return;
		}
		$caps = array( 'read' => true );
		$customer = get_role( 'customer' );
		if ( $customer instanceof \WP_Role && is_array( $customer->capabilities ) ) {
			$caps = $customer->capabilities;
		}
		add_role( 'gw_distributor', __( 'Distributor', 'greenworld' ), $caps );
	}

	private function requested_type(): string {
		$type = isset( $_GET['gw_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['gw_type'] ) ) : '';
		return ( $type === 'distributor' ) ? 'distributor' : 'customer';
	}

	/**
	 * Account-type chooser at the top of the registration form.
	 */
	public function type_toggle(): void {
		$type = $this->requested_type();
		wp_nonce_field( 'gw_register', 'gw_register_nonce' );
		// Honeypot: hidden from real users; bots that fill it are rejected in validate().
		echo '<div class="gw-reg-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Leave this field empty <input type="text" name="gw_hp" tabindex="-1" autocomplete="off" value="" /></label></div>';
		echo '<input type="hidden" name="gw_rt" value="' . esc_attr( (string) time() ) . '" />';
		echo '<div class="gw-reg-type" role="radiogroup" aria-label="' . esc_attr__( 'Register as', 'greenworld' ) . '">';
		echo '<span class="gw-reg-type__label">' . esc_html__( 'I want to register as', 'greenworld' ) . '</span>';
		echo '<label class="gw-reg-type__opt"><input type="radio" name="gw_account_type" value="customer" ' . checked( $type, 'customer', false ) . ' /><span><strong>' . esc_html__( 'Customer', 'greenworld' ) . '</strong><small>' . esc_html__( 'Shop products for yourself and your family.', 'greenworld' ) . '</small></span></label>';
		echo '<label class="gw-reg-type__opt"><input type="radio" name="gw_account_type" value="distributor" ' . checked( $type, 'distributor', false ) . ' /><span><strong>' . esc_html__( 'Distributor', 'greenworld' ) . '</strong><small>' . esc_html__( 'Join the Green World business and build your own network.', 'greenworld' ) . '</small></span></label>';
		echo '</div>';
	}

	/**
	 * Extra applicant fields (phone required, county + sponsor optional).
	 */
	public function fields(): void {
		$phone   = isset( $_POST['gw_phone'] ) ? esc_attr( wp_unslash( (string) $_POST['gw_phone'] ) ) : '';
		$county  = isset( $_POST['gw_county'] ) ? esc_attr( wp_unslash( (string) $_POST['gw_county'] ) ) : '';
		$sponsor = isset( $_POST['gw_sponsor'] ) ? esc_attr( wp_unslash( (string) $_POST['gw_sponsor'] ) ) : '';
		echo '<p class="form-row form-row-wide"><label for="gw_phone">' . esc_html__( 'Phone number', 'greenworld' ) . '&nbsp;<span class="required">*</span></label><input type="tel" class="input-text" name="gw_phone" id="gw_phone" autocomplete="tel" value="' . $phone . '" /></p>';
		echo '<p class="form-row form-row-wide"><label for="gw_county">' . esc_html__( 'County / Town', 'greenworld' ) . '</label><input type="text" class="input-text" name="gw_county" id="gw_county" value="' . $county . '" /></p>';
		echo '<p class="form-row form-row-wide gw-reg-sponsor"><label for="gw_sponsor">' . esc_html__( 'Sponsor / Referral ID (distributors only, if referred)', 'greenworld' ) . '</label><input type="text" class="input-text" name="gw_sponsor" id="gw_sponsor" value="' . $sponsor . '" /><span class="gw-reg-hint">' . esc_html__( 'Leave blank if you were not referred by an existing distributor.', 'greenworld' ) . '</span></p>';
	}

	/**
	 * @param string    $username
	 * @param string    $email
	 * @param \WP_Error $errors
	 */
	public function validate( $username, $email, $errors ): void {
		if ( ! isset( $_POST['gw_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gw_register_nonce'] ) ), 'gw_register' ) ) {
			return;
		}
		if ( ! empty( $_POST['gw_hp'] ) ) {
			$errors->add( 'gw_hp_error', __( 'Your submission could not be processed. Please try again.', 'greenworld' ) );
			return;
		}
		$rt = isset( $_POST['gw_rt'] ) ? (int) $_POST['gw_rt'] : 0;
		if ( $rt > 0 && ( time() - $rt ) < 4 ) {
			$errors->add( 'gw_rt_error', __( 'Your submission came through too fast. Please try again.', 'greenworld' ) );
			return;
		}
		$rl_key = 'gw_reg_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '0' );
		$rl     = (int) get_transient( $rl_key );
		if ( $rl >= 10 ) {
			$errors->add( 'gw_rl_error', __( 'Too many sign-up attempts from your connection. Please wait a while and try again.', 'greenworld' ) );
			return;
		}
		set_transient( $rl_key, $rl + 1, HOUR_IN_SECONDS );
		if ( empty( $_POST['gw_phone'] ) ) {
			$errors->add( 'gw_phone_error', __( 'Please enter a phone number so we can reach you about your order or application.', 'greenworld' ) );
		}
	}

	/**
	 * Persist applicant details and assign the distributor role when requested.
	 *
	 * @param int $customer_id
	 */
	public function save( $customer_id, $new_customer_data = array(), $password_generated = false ): void {
		$cid = (int) $customer_id;
		if ( $cid === 0 ) {
			return;
		}
		if ( ! isset( $_POST['gw_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gw_register_nonce'] ) ), 'gw_register' ) ) {
			return;
		}
		$type    = ( isset( $_POST['gw_account_type'] ) && 'distributor' === $_POST['gw_account_type'] ) ? 'distributor' : 'customer';
		$phone   = isset( $_POST['gw_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gw_phone'] ) ) : '';
		$county  = isset( $_POST['gw_county'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gw_county'] ) ) : '';
		$sponsor = isset( $_POST['gw_sponsor'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gw_sponsor'] ) ) : '';

		update_user_meta( $cid, '_gw_account_type', $type );
		if ( $phone !== '' ) {
			update_user_meta( $cid, '_gw_phone', $phone );
			update_user_meta( $cid, 'billing_phone', $phone );
		}
		if ( $county !== '' ) {
			update_user_meta( $cid, '_gw_county', $county );
		}
		if ( $sponsor !== '' ) {
			update_user_meta( $cid, '_gw_sponsor', $sponsor );
		}

		if ( $type === 'distributor' ) {
			$user = new \WP_User( $cid );
			$user->add_role( 'gw_distributor' );
			update_user_meta( $cid, '_gw_distributor_status', 'pending' );
			$this->notify_admin( $cid, $phone, $county, $sponsor );
		}
	}

	private function notify_admin( int $cid, string $phone, string $county, string $sponsor ): void {
		$user = get_user_by( 'id', $cid );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$to      = get_option( 'admin_email' );
		$subject = __( 'New distributor application', 'greenworld' );
		$body    = sprintf(
			"A new distributor application was submitted.\n\nName: %s\nEmail: %s\nPhone: %s\nCounty/Town: %s\nSponsor/Referral ID: %s\n\nReview in Users > all users (filter by the Distributor role).",
			$user->display_name,
			$user->user_email,
			$phone,
			$county !== '' ? $county : '-',
			$sponsor !== '' ? $sponsor : '-'
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * [gw_register type="distributor"|"customer"] - benefits + CTA into My Account.
	 *
	 * @param array<string,mixed> $atts
	 */
	public function shortcode( $atts = array() ): string {
		$atts = shortcode_atts( array( 'type' => 'distributor' ), $atts, 'gw_register' );
		$type = ( 'customer' === $atts['type'] ) ? 'customer' : 'distributor';
		$my   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );

		if ( is_user_logged_in() ) {
			return '<div class="gw-reg-card"><p>' . esc_html__( 'You are signed in.', 'greenworld' ) . ' <a class="button" href="' . esc_url( $my ) . '">' . esc_html__( 'Go to My Account', 'greenworld' ) . '</a></p></div>';
		}

		$cta_url = esc_url( add_query_arg( 'gw_type', $type, $my ) . '#register' );

		if ( $type === 'distributor' ) {
			$benefits = array(
				__( 'Buy Green World products at distributor prices', 'greenworld' ),
				__( 'Build and grow your own customer network', 'greenworld' ),
				__( 'Access training, product guidance and support', 'greenworld' ),
				__( 'Track your orders and account online, anytime', 'greenworld' ),
			);
			$title = __( 'Become a Green World Distributor', 'greenworld' );
			$lead  = __( 'Start your own health and wellness business. Register below and our team will help you get started.', 'greenworld' );
			$btn   = __( 'Register as Distributor', 'greenworld' );
		} else {
			$benefits = array(
				__( 'Faster checkout and saved delivery details', 'greenworld' ),
				__( 'Track every order from your account', 'greenworld' ),
				__( 'Save products to your wishlist', 'greenworld' ),
				__( 'Health and wellness updates, only if you opt in', 'greenworld' ),
			);
			$title = __( 'Register as a Customer', 'greenworld' );
			$lead  = __( 'Create a free account for a faster, more personal shopping experience.', 'greenworld' );
			$btn   = __( 'Create Customer Account', 'greenworld' );
		}

		$html  = '<div class="gw-reg-card gw-reg-card--' . esc_attr( $type ) . '">';
		$html .= '<h2 class="gw-reg-card__title">' . esc_html( $title ) . '</h2>';
		$html .= '<p class="gw-reg-card__lead">' . esc_html( $lead ) . '</p>';
		$html .= '<ul class="gw-reg-card__list">';
		foreach ( $benefits as $b ) {
			$html .= '<li>' . esc_html( $b ) . '</li>';
		}
		$html .= '</ul>';
		$html .= '<a class="button gw-reg-card__cta" href="' . $cta_url . '">' . esc_html( $btn ) . '</a>';
		$html .= '<p class="gw-reg-card__alt"><a href="' . esc_url( $my ) . '">' . esc_html__( 'Already registered? Sign in', 'greenworld' ) . '</a></p>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function user_column( $cols ): array {
		$cols['gw_account_type'] = __( 'Account type', 'greenworld' );
		return $cols;
	}

	/**
	 * @param string $output
	 * @param string $column
	 * @param int    $user_id
	 */
	public function user_column_value( $output, $column, $user_id ): string {
		if ( 'gw_account_type' !== $column ) {
			return (string) $output;
		}
		$type = (string) get_user_meta( (int) $user_id, '_gw_account_type', true );
		if ( $type === 'distributor' ) {
			$status = (string) get_user_meta( (int) $user_id, '_gw_distributor_status', true );
			return '<span class="gw-tag gw-tag--dist">' . esc_html__( 'Distributor', 'greenworld' ) . '</span>' . ( $status ? ' <small>(' . esc_html( $status ) . ')</small>' : '' );
		}
		if ( $type === 'customer' ) {
			return esc_html__( 'Customer', 'greenworld' );
		}
		return '&mdash;';
	}
}
