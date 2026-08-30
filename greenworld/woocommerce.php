<?php
/**
 * WooCommerce wrapper. Archives (shop + product taxonomy) get a filter sidebar;
 * single products and other endpoints render full width.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

get_header();

$gw_is_archive = ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) );
?>
<div class="gw-container gw-wc<?php echo $gw_is_archive ? ' gw-wc--shop' : ''; ?>">
	<?php if ( $gw_is_archive && class_exists( '\GreenWorld\Woo\Filters' ) ) : ?>
		<button class="gw-filters-toggle" type="button" data-gw-filters-toggle aria-expanded="false">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
			<?php esc_html_e( 'Filters', 'greenworld' ); ?>
		</button>
		<aside class="gw-filters" data-gw-filters aria-label="<?php esc_attr_e( 'Product filters', 'greenworld' ); ?>">
			<?php \GreenWorld\Woo\Filters::panel(); ?>
		</aside>
	<?php endif; ?>
	<div class="gw-wc__main">
		<?php woocommerce_content(); ?>
	</div>
</div>
<?php
get_footer();
