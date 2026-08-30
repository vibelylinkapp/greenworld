<?php
/**
 * Bridges the theme's consultation form to WhatsApp. The theme fires
 * greenworld/consultation_submitted after it saves a request; we forward a
 * summary to staff on WhatsApp. The theme's email + admin record are unchanged.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Consultation {

	private static $instance = null;

	public static function instance(): GWC_Consultation {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'greenworld/consultation_submitted', array( $this, 'on_submit' ), 10, 1 );
	}

	/**
	 * @param array<string,string> $data
	 */
	public function on_submit( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}
		$g = static function ( $k ) use ( $data ) {
			return ( isset( $data[ $k ] ) && '' !== $data[ $k ] ) ? (string) $data[ $k ] : '-';
		};
		$msg = sprintf(
			"New health consultation:\nName: %s\nPhone: %s\nEmail: %s\nAge: %s\nGender: %s\nPreferred contact: %s\nCurrently using: %s\nConcern: %s",
			$g( 'name' ),
			$g( 'phone' ),
			$g( 'email' ),
			$g( 'age' ),
			$g( 'gender' ),
			$g( 'prefer' ),
			$g( 'using' ),
			$g( 'concern' )
		);
		GWC_WhatsApp::notify_staff( $msg );
	}
}
