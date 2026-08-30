<?php
/**
 * Single guide (Health & Wellness Guide article / how-to). Renders the guide
 * body, then a related-content block linking back to its pillar, products and
 * sibling guides — the reverse side of the semantic network.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

use GreenWorld\Content\Relations;
use GreenWorld\Content\InternalLinks;

get_header();
InternalLinks::styles();
?>
<div class="gw-container gw-guide-single">
	<?php
	while ( have_posts() ) :
		the_post();
		$gw_gid    = (int) get_the_ID();
		$gw_pillar = (string) get_post_meta( $gw_gid, '_gw_guide_pillar', true );
		?>
		<article <?php post_class( 'gw-guide__article' ); ?>>
			<header class="gw-guide__header">
				<p class="gw-guide__eyebrow"><a href="<?php echo esc_url( Relations::hub_url() ); ?>"><?php esc_html_e( 'Health & Wellness Guide', 'greenworld' ); ?></a></p>
				<h1 class="gw-guide__title"><?php the_title(); ?></h1>
			</header>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="gw-guide__media"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<div class="gw-guide__content gw-richtext"><?php the_content(); ?></div>
		</article>
		<?php
		set_query_var( 'gw_pillar_key', $gw_pillar );
		get_template_part( 'template-parts/related-content' );
		?>
	<?php endwhile; ?>
</div>
<?php
get_footer();
