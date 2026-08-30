<?php
/**
 * Template Name: Track Your Order
 *
 * Elegant order-tracking page. Uses WooCommerce's native order tracking form.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="gw-container gw-section gw-track">
	<div class="gw-track__inner">
		<span class="gw-eyebrow"><?php esc_html_e( 'Order status', 'greenworld' ); ?></span>
		<h1 class="gw-track__title"><?php esc_html_e( 'Track Your Order', 'greenworld' ); ?></h1>
		<p class="gw-track__lead"><?php esc_html_e( 'Enter your order number and the email or phone used at checkout to see the latest status.', 'greenworld' ); ?></p>
		<?php
		if ( shortcode_exists( 'woocommerce_order_tracking' ) ) {
			echo do_shortcode( '[woocommerce_order_tracking]' );
		} else {
			echo '<p>' . esc_html__( 'Order tracking requires WooCommerce to be active.', 'greenworld' ) . '</p>';
		}
		?>
		<p class="gw-track__help"><?php esc_html_e( 'Need help? Contact our customer service team and we will assist you.', 'greenworld' ); ?> <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact us', 'greenworld' ); ?></a></p>
	</div>
</div>
<?php
get_footer();
