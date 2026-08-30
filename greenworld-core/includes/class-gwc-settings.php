<?php
/**
 * Settings store + admin page (Settings -> Green World).
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Settings {

	private static $instance = null;
	private const OPTION     = 'gwc_settings';

	public static function instance(): GWC_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$defaults = array(
			'enabled'       => 0,
			'token'         => '',
			'phone_id'      => '',
			'api_version'   => 'v21.0',
			'recipients'    => '',
			'template'      => '',
			'template_lang' => 'en',
		);
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( string $key, $default = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Staff recipient numbers as a clean array of digits-only strings.
	 *
	 * @return array<int,string>
	 */
	public static function recipients(): array {
		$raw = (string) self::get( 'recipients', '' );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $num ) {
			$digits = preg_replace( '/[^0-9]/', '', (string) $num );
			if ( '' !== $digits ) {
				$out[] = $digits;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public function menu(): void {
		add_options_page(
			__( 'Green World', 'greenworld-core' ),
			__( 'Green World', 'greenworld-core' ),
			'manage_options',
			'greenworld-core',
			array( $this, 'render' )
		);
	}

	public function maybe_save(): void {
		if ( ! isset( $_POST['gwc_settings_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gwc_settings_nonce'] ) ), 'gwc_save_settings' ) ) {
			return;
		}
		$in    = ( isset( $_POST['gwc'] ) && is_array( $_POST['gwc'] ) ) ? wp_unslash( $_POST['gwc'] ) : array();
		$clean = array(
			'enabled'       => empty( $in['enabled'] ) ? 0 : 1,
			'token'         => isset( $in['token'] ) ? trim( sanitize_text_field( (string) $in['token'] ) ) : '',
			'phone_id'      => isset( $in['phone_id'] ) ? preg_replace( '/[^0-9]/', '', (string) $in['phone_id'] ) : '',
			'api_version'   => isset( $in['api_version'] ) ? sanitize_text_field( (string) $in['api_version'] ) : 'v21.0',
			'recipients'    => isset( $in['recipients'] ) ? sanitize_textarea_field( (string) $in['recipients'] ) : '',
			'template'      => isset( $in['template'] ) ? sanitize_text_field( (string) $in['template'] ) : '',
			'template_lang' => isset( $in['template_lang'] ) ? sanitize_text_field( (string) $in['template_lang'] ) : 'en',
		);
		update_option( self::OPTION, $clean );
		add_settings_error( 'gwc', 'saved', __( 'Settings saved.', 'greenworld-core' ), 'updated' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = self::all();
		$err = GWC_WhatsApp::last_error();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Green World Core', 'greenworld-core' ); ?></h1>
			<?php settings_errors( 'gwc' ); ?>
			<h2><?php esc_html_e( 'WhatsApp notifications (Meta Cloud API)', 'greenworld-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Sends scan bookings and consultation requests to your staff WhatsApp automatically. Copy these values from your Meta WhatsApp Cloud API app (see the plugin README).', 'greenworld-core' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'gwc_save_settings', 'gwc_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable WhatsApp alerts', 'greenworld-core' ); ?></th>
						<td><label><input type="checkbox" name="gwc[enabled]" value="1" <?php checked( (int) $s['enabled'], 1 ); ?> /> <?php esc_html_e( 'Send automatic WhatsApp messages', 'greenworld-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_token"><?php esc_html_e( 'Access token', 'greenworld-core' ); ?></label></th>
						<td><input type="password" id="gwc_token" name="gwc[token]" class="regular-text" value="<?php echo esc_attr( (string) $s['token'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_phone_id"><?php esc_html_e( 'Phone number ID', 'greenworld-core' ); ?></label></th>
						<td><input type="text" id="gwc_phone_id" name="gwc[phone_id]" class="regular-text" value="<?php echo esc_attr( (string) $s['phone_id'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_api_version"><?php esc_html_e( 'API version', 'greenworld-core' ); ?></label></th>
						<td><input type="text" id="gwc_api_version" name="gwc[api_version]" class="small-text" value="<?php echo esc_attr( (string) $s['api_version'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_recipients"><?php esc_html_e( 'Staff recipient numbers', 'greenworld-core' ); ?></label></th>
						<td><textarea id="gwc_recipients" name="gwc[recipients]" rows="2" class="large-text" placeholder="254700000000, 254711111111"><?php echo esc_textarea( (string) $s['recipients'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One or more numbers in full international format (digits only), separated by commas.', 'greenworld-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_template"><?php esc_html_e( 'Template name (optional)', 'greenworld-core' ); ?></label></th>
						<td><input type="text" id="gwc_template" name="gwc[template]" class="regular-text" value="<?php echo esc_attr( (string) $s['template'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Only needed to message staff outside the 24-hour window. Leave blank to send plain text (works when staff have messaged the business number within the last 24 hours).', 'greenworld-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="gwc_template_lang"><?php esc_html_e( 'Template language', 'greenworld-core' ); ?></label></th>
						<td><input type="text" id="gwc_template_lang" name="gwc[template_lang]" class="small-text" value="<?php echo esc_attr( (string) $s['template_lang'] ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( '' !== $err ) : ?>
				<h2><?php esc_html_e( 'Last WhatsApp error', 'greenworld-core' ); ?></h2>
				<p><code><?php echo esc_html( $err ); ?></code></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
