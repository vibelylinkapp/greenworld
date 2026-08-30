<?php
/**
 * Meta WhatsApp Cloud API sender.
 *
 * Sends plain-text messages when configured, and records the last error so it
 * can be shown on the settings screen. Methods no-op gracefully when the
 * integration is not configured, so callers never need to guard.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_WhatsApp {

	private const ERR_OPTION = 'gwc_wa_last_error';

	public static function is_configured(): bool {
		if ( 1 !== (int) GWC_Settings::get( 'enabled', 0 ) ) {
			return false;
		}
		$token = (string) GWC_Settings::get( 'token', '' );
		$phone = (string) GWC_Settings::get( 'phone_id', '' );
		return ( '' !== $token && '' !== $phone );
	}

	public static function last_error(): string {
		return (string) get_option( self::ERR_OPTION, '' );
	}

	/**
	 * Send a plain-text WhatsApp message to a single recipient.
	 */
	public static function send_text( string $to, string $message ): bool {
		$to = (string) preg_replace( '/[^0-9]/', '', $to );
		if ( '' === $to || '' === trim( $message ) ) {
			return false;
		}
		if ( ! self::is_configured() ) {
			return false;
		}
		$payload = array(
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $message,
			),
		);
		return self::request( $payload );
	}

	/**
	 * Fan out a message to every configured staff recipient.
	 */
	public static function notify_staff( string $message ): void {
		if ( ! self::is_configured() ) {
			return;
		}
		foreach ( GWC_Settings::recipients() as $to ) {
			self::send_text( $to, $message );
		}
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private static function request( array $payload ): bool {
		$version = (string) GWC_Settings::get( 'api_version', 'v21.0' );
		$phone   = (string) GWC_Settings::get( 'phone_id', '' );
		$token   = (string) GWC_Settings::get( 'token', '' );
		$url     = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $phone ) . '/messages';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( self::ERR_OPTION, $response->get_error_message() );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			update_option( self::ERR_OPTION, '' );
			return true;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		update_option( self::ERR_OPTION, 'HTTP ' . $code . ': ' . wp_strip_all_tags( $body ) );
		return false;
	}
}
