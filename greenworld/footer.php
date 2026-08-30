<?php
/**
 * GreenWorld footer: trust strip, footer columns, health disclaimer, mobile
 * bottom navigation and utility affordances.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

use GreenWorld\Customizer\Customizer;

$gw_year     = (string) gmdate( 'Y' );
$gw_phone    = Customizer::val( 'gw_phone' );
$gw_email    = Customizer::val( 'gw_email' );
$gw_hours    = Customizer::val( 'gw_hours' );
$gw_address  = Customizer::val( 'gw_address' );
$gw_whatsapp = (string) preg_replace( '/[^0-9]/', '', Customizer::val( 'gw_whatsapp' ) );
$gw_disc     = Customizer::val( 'gw_default_disclaimer' );
$gw_tel      = (string) preg_replace( '/[^0-9+]/', '', $gw_phone );
$gw_account  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$gw_shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
?>
</main>

<section class="gw-trust" aria-label="<?php esc_attr_e( 'Why shop with Green World Health Solutions', 'greenworld' ); ?>">
	<div class="gw-container"><?php echo do_shortcode( '[gw_trust_badges]' ); ?></div>
</section>

<footer class="gw-footer" role="contentinfo">
	<div class="gw-container gw-footer__cols">
		<div class="gw-footer__col gw-footer__about">
			<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
				<img class="gw-footer__logo" src="<?php echo esc_url( GREENWORLD_URI . 'assets/img/logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="230" />
			<?php endif; ?>
			<p><?php esc_html_e( 'Trusted health and wellness products for healthier everyday living, serving customers across Kenya. Shop online, register as a customer or distributor, and get friendly support when you need it.', 'greenworld' ); ?></p>
			<p class="gw-footer__social">
				<?php if ( strlen( $gw_whatsapp ) > 0 ) : ?><a href="https://wa.me/<?php echo esc_attr( $gw_whatsapp ); ?>" rel="noopener">WhatsApp</a><?php endif; ?>
				<?php foreach ( array( 'gw_facebook' => 'Facebook', 'gw_instagram' => 'Instagram', 'gw_tiktok' => 'TikTok', 'gw_youtube' => 'YouTube' ) as $gw_sk => $gw_sl ) { $gw_su = trim( (string) get_theme_mod( $gw_sk, '' ) ); if ( strlen( $gw_su ) > 0 ) { printf( '<a href="%s" rel="noopener me" target="_blank">%s</a>', esc_url( $gw_su ), esc_html( $gw_sl ) ); } } ?>
				
				
			</p>
		</div>

		<div class="gw-footer__col">
			<h3 class="gw-footer__title"><?php esc_html_e( 'Information', 'greenworld' ); ?></h3>
			<?php if ( has_nav_menu( 'footer-info' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'footer-info', 'container' => false, 'menu_class' => 'gw-footer__menu', 'fallback_cb' => false, 'depth' => 1 ) ); ?>
			<?php else : ?>
				<ul class="gw-footer__menu">
					<li><a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'About Us', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms-conditions/' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/shipping-delivery/' ) ); ?>"><?php esc_html_e( 'Shipping & Delivery', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/return-refund-policy/' ) ); ?>"><?php esc_html_e( 'Returns & Refunds', 'greenworld' ); ?></a></li>
				</ul>
			<?php endif; ?>
		</div>

		<div class="gw-footer__col">
			<h3 class="gw-footer__title"><?php esc_html_e( 'Customer Service', 'greenworld' ); ?></h3>
			<?php if ( has_nav_menu( 'footer-service' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'footer-service', 'container' => false, 'menu_class' => 'gw-footer__menu', 'fallback_cb' => false, 'depth' => 1 ) ); ?>
			<?php else : ?>
				<ul class="gw-footer__menu">
					<li><a href="<?php echo esc_url( $gw_account ); ?>"><?php esc_html_e( 'My Account', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track Order', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/health-consultation/' ) ); ?>"><?php esc_html_e( 'Free Health Consultation', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/become-a-distributor/' ) ); ?>"><?php esc_html_e( 'Become a Distributor', 'greenworld' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQs', 'greenworld' ); ?></a></li>
				</ul>
			<?php endif; ?>
		</div>

		<div class="gw-footer__col gw-footer__news">
			<h3 class="gw-footer__title"><?php esc_html_e( 'Stay Well, Stay Updated', 'greenworld' ); ?></h3>
			<p><?php esc_html_e( 'Subscribe for health, wellness and product updates. No spam, unsubscribe anytime.', 'greenworld' ); ?></p>
			<?php
			$gw_news = do_shortcode( '[contact-form-7 id="newsletter" title="Newsletter"]' );
			if ( strpos( (string) $gw_news, 'wpcf7' ) !== false && strpos( (string) $gw_news, 'not found' ) === false ) {
				echo $gw_news; // phpcs:ignore
			} else {
				?>
				<form class="gw-news" method="post" action="<?php echo esc_url( $gw_email !== '' ? 'mailto:' . $gw_email : '#' ); ?>">
					<label class="screen-reader-text" for="gw-news-email"><?php esc_html_e( 'Email address', 'greenworld' ); ?></label>
					<input id="gw-news-email" type="email" name="subject" placeholder="<?php esc_attr_e( 'Your email address', 'greenworld' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Subscribe', 'greenworld' ); ?></button>
				</form>
			<?php } ?>
			<ul class="gw-footer__contact">
				<?php if ( strlen( $gw_address ) > 0 ) : ?><li><?php echo esc_html( $gw_address ); ?></li><?php endif; ?>
				<?php if ( strlen( $gw_phone ) > 0 ) : ?><li><a href="tel:<?php echo esc_attr( $gw_tel ); ?>"><?php echo esc_html( $gw_phone ); ?></a></li><?php endif; ?>
				<?php if ( strlen( $gw_email ) > 0 ) : ?><li><a href="mailto:<?php echo esc_attr( $gw_email ); ?>"><?php echo esc_html( $gw_email ); ?></a></li><?php endif; ?>
				<?php if ( strlen( $gw_hours ) > 0 ) : ?><li><?php echo esc_html( $gw_hours ); ?></li><?php endif; ?>
			</ul>
		</div>
	</div>

	<div class="gw-footer__pay">
		<span class="gw-footer__pay-label"><?php esc_html_e( 'Payments', 'greenworld' ); ?></span>
		<span class="gw-pay">M-Pesa</span>
		<span class="gw-pay"><?php esc_html_e( 'Cash on Delivery', 'greenworld' ); ?></span>
		<span class="gw-pay"><?php esc_html_e( 'Bank Transfer', 'greenworld' ); ?></span>
		<span class="gw-footer__secure"><?php esc_html_e( 'SSL secured checkout', 'greenworld' ); ?></span>
	</div>

	<?php if ( strlen( $gw_disc ) > 0 ) : ?>
	<div class="gw-footer__disclaimer">
		<p><?php echo esc_html( $gw_disc ); ?></p>
	</div>
	<?php endif; ?>

	<div class="gw-footer__legal gw-container">
		<p>&copy; <?php echo esc_html( $gw_year ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved. All prices in Kenyan Shillings (KES).', 'greenworld' ); ?></p>
	</div>
</footer>

<?php if ( strlen( $gw_whatsapp ) > 0 ) : ?>
<a class="gw-whatsapp" href="https://wa.me/<?php echo esc_attr( $gw_whatsapp ); ?>" aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'greenworld' ); ?>" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.72 14.29c-.24.68-1.42 1.3-1.95 1.35-.5.05-1.13.27-3.7-.78-3.11-1.26-5.09-4.42-5.24-4.62-.15-.2-1.25-1.66-1.25-3.17 0-1.51.79-2.25 1.07-2.56.28-.31.61-.39.81-.39.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.59.83 2.04.9 2.19.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.18-.32.4-.45.53-.15.15-.31.31-.13.61.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.48 1.53.3.15.48.13.66-.08.18-.2.76-.89.96-1.19.2-.3.4-.25.68-.15.28.1 1.76.83 2.06.98.3.15.5.22.57.35.07.13.07.73-.17 1.41Z"/></svg></a>
<?php endif; ?>

<nav class="gw-bottomnav" aria-label="<?php esc_attr_e( 'Mobile', 'greenworld' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 11 12 4l8 7M6 10v9h12v-9"/></svg><span><?php esc_html_e( 'Home', 'greenworld' ); ?></span></a>
	<button type="button" data-gw-nav-toggle aria-controls="gw-primary-menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span><?php esc_html_e( 'Categories', 'greenworld' ); ?></span></button>
	<button type="button" data-gw-search-focus><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><span><?php esc_html_e( 'Search', 'greenworld' ); ?></span></button>
	<a href="<?php echo esc_url( home_url( '/health-consultation/' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12a8 8 0 0 1 16 0v5a2 2 0 0 1-2 2h-3l-3 2v-2H6a2 2 0 0 1-2-2Z"/></svg><span><?php esc_html_e( 'Consult', 'greenworld' ); ?></span></a>
	<a href="<?php echo esc_url( $gw_account ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span><?php esc_html_e( 'Account', 'greenworld' ); ?></span></a>
</nav>

<a class="gw-backtotop" href="#gw-main" data-gw-backtotop aria-label="<?php esc_attr_e( 'Back to top', 'greenworld' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></a>
<?php wp_footer(); ?>
</body>
</html>
