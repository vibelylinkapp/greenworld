<?php
/**
 * GreenWorld header: utility bar, main bar (logo + AJAX search + account /
 * wishlist / cart), primary navigation with a health mega menu, and the
 * mini-cart drawer.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

use GreenWorld\Customizer\Customizer;

$gw_phone   = Customizer::val( 'gw_phone' );
$gw_wa      = (string) preg_replace( '/[^0-9]/', '', Customizer::val( 'gw_whatsapp' ) );
$gw_email   = Customizer::val( 'gw_email' );
$gw_hours   = Customizer::val( 'gw_hours' );
$gw_notice  = Customizer::val( 'gw_topbar_notice' );
$gw_tel     = (string) preg_replace( '/[^0-9+]/', '', $gw_phone );
$gw_account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$gw_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$gw_action  = home_url( '/' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text gw-skip" href="#gw-main"><?php esc_html_e( 'Skip to content', 'greenworld' ); ?></a>

<header class="gw-header" role="banner" data-gw-header>

	<div class="gw-utility">
		<div class="gw-utility__inner gw-container">
			<p class="gw-utility__welcome"><?php echo esc_html( $gw_notice ); ?></p>
			<ul class="gw-utility__links">
				<li><a href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track Order', 'greenworld' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Customer Service', 'greenworld' ); ?></a></li>
				<?php if ( strlen( $gw_hours ) > 0 ) : ?><li class="gw-utility__hours"><?php echo esc_html( $gw_hours ); ?></li><?php endif; ?>
				<?php if ( strlen( $gw_phone ) > 0 ) : ?><li class="gw-utility__call"><a href="tel:<?php echo esc_attr( $gw_tel ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4.5 5.5c0 7.7 6.3 14 14 14l0-3.2-4-1.1-2 2a13 13 0 0 1-5.7-5.7l2-2-1.1-4Z"/></svg><?php echo esc_html( $gw_phone ); ?></a></li><?php endif; ?>
				<li class="gw-utility__auth">
					<a href="<?php echo esc_url( $gw_account ); ?>"><?php esc_html_e( 'Sign in', 'greenworld' ); ?></a>
					<span aria-hidden="true">&middot;</span>
					<a class="gw-utility__join" href="<?php echo esc_url( home_url( '/become-a-distributor/' ) ); ?>"><?php esc_html_e( 'Join Green World', 'greenworld' ); ?></a>
				</li>
			</ul>
		</div>
	</div>

	<div class="gw-header__sticky" data-gw-sticky>
		<div class="gw-headbar">
			<div class="gw-headbar__inner gw-container">
				<button class="gw-burger" type="button" data-gw-nav-toggle aria-label="<?php esc_attr_e( 'Menu', 'greenworld' ); ?>" aria-controls="gw-primary-menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>

				<div class="gw-headbar__brand">
					<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
						<a class="gw-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<span class="gw-logo__mark" aria-hidden="true"><img src="<?php echo esc_url( GREENWORLD_URI . 'assets/img/logo-badge.png' ); ?>" alt="" width="44" height="44" /></span>
							<span class="gw-logo__text"><span class="gw-logo__name">Green World</span><span class="gw-logo__sub"><?php esc_html_e( 'Health Solutions', 'greenworld' ); ?></span></span>
						</a>
					<?php endif; ?>
				</div>

				<form class="gw-search" role="search" method="get" action="<?php echo esc_url( $gw_action ); ?>" data-gw-search>
					<label class="screen-reader-text" for="gw-search-input"><?php esc_html_e( 'Search products', 'greenworld' ); ?></label>
					<input id="gw-search-input" class="gw-search__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search health products, wellness solutions, categories...', 'greenworld' ); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gw-search-panel" data-gw-search-input />
					<input type="hidden" name="post_type" value="product" />
					<button type="submit" class="gw-search__btn" aria-label="<?php esc_attr_e( 'Search', 'greenworld' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></button>
					<div id="gw-search-panel" class="gw-search__panel" role="listbox" data-gw-search-panel hidden></div>
				</form>

				<div class="gw-headbar__actions">
					<a class="gw-action" href="<?php echo esc_url( $gw_account ); ?>" aria-label="<?php esc_attr_e( 'Account', 'greenworld' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span><?php esc_html_e( 'Account', 'greenworld' ); ?></span></a>
					<a class="gw-action gw-action--wish" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" aria-label="<?php esc_attr_e( 'Wishlist', 'greenworld' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21S4 14.5 4 8.8A4.2 4.2 0 0 1 12 6a4.2 4.2 0 0 1 8 2.8C20 14.5 12 21 12 21Z"/></svg><span><?php esc_html_e( 'Wishlist', 'greenworld' ); ?></span></a>
					<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
						<button class="gw-action gw-action--cart" type="button" data-gw-minicart-open aria-label="<?php esc_attr_e( 'Open cart', 'greenworld' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.5 12h11L21 7H6"/></svg><span class="gw-cart__count"><?php echo esc_html( (string) ( ( WC()->cart instanceof \WC_Cart ) ? WC()->cart->get_cart_contents_count() : 0 ) ); ?></span><span class="gw-action__label"><?php esc_html_e( 'Cart', 'greenworld' ); ?></span></button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="gw-navbar">
			<div class="gw-navbar__inner gw-container">
				<nav class="gw-primary" aria-label="<?php esc_attr_e( 'Primary', 'greenworld' ); ?>" data-gw-primary>
					<div class="gw-primary__head">
						<span class="gw-primary__headtitle"><?php esc_html_e( 'Menu', 'greenworld' ); ?></span>
						<button type="button" class="gw-primary__close" data-gw-nav-toggle aria-label="<?php esc_attr_e( 'Close menu', 'greenworld' ); ?>">&times;</button>
					</div>
					<?php gw_render_primary_nav( $gw_shop ); ?>

					<div class="gw-drawer-extra">
						<div class="gw-drawer-extra__links">
							<a href="<?php echo esc_url( $gw_account ); ?>"><?php esc_html_e( 'Sign in', 'greenworld' ); ?></a>
							<a href="<?php echo esc_url( add_query_arg( 'gw_type', 'customer', $gw_account ) ); ?>"><?php esc_html_e( 'Register as Customer', 'greenworld' ); ?></a>
							<a href="<?php echo esc_url( home_url( '/become-a-distributor/' ) ); ?>"><?php esc_html_e( 'Become a Distributor', 'greenworld' ); ?></a>
						</div>
					</div>
				</nav>
			</div>
		</div>
	</div>
</header>
<div class="gw-nav-scrim" data-gw-nav-scrim hidden></div>

<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
<div class="gw-minicart" data-gw-minicart hidden>
	<div class="gw-minicart__overlay" data-gw-minicart-close></div>
	<aside class="gw-minicart__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping cart', 'greenworld' ); ?>">
		<div class="gw-minicart__head">
			<h2 class="gw-minicart__title"><?php esc_html_e( 'Your Cart', 'greenworld' ); ?></h2>
			<button class="gw-minicart__close" type="button" data-gw-minicart-close aria-label="<?php esc_attr_e( 'Close cart', 'greenworld' ); ?>">&times;</button>
		</div>
		<div class="gw-minicart__body">
			<?php woocommerce_mini_cart(); ?>
		</div>
	</aside>
</div>
<?php endif; ?>

<main id="gw-main" class="gw-main">
