<?php
/**
 * Content hub — the /health-wellness-guide/ archive. Presents the topic map,
 * the latest guides and the shop-by-category pillars in one authoritative place.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="gw-container gw-hub-archive">
	<header class="gw-page__header">
		<h1 class="gw-page__title"><?php esc_html_e( 'Health & Wellness Guide', 'greenworld' ); ?></h1>
		<p class="gw-hub__intro"><?php esc_html_e( 'Practical, non-medical guides to natural health, nutrition, wellness and healthy living — and the products that support them.', 'greenworld' ); ?></p>
	</header>
	<div class="gw-richtext">
		<?php echo do_shortcode( '[gw_guide_hub]' ); ?>
	</div>
</div>
<?php
get_footer();
