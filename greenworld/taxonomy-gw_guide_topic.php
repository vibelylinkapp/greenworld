<?php
/**
 * Guide topic archive (Natural Health, Nutrition, Wellness, ...). Lists the
 * guides in the topic and links out to the shop-by-category pillars.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

use GreenWorld\Content\InternalLinks;

get_header();
InternalLinks::styles();
$gw_term = get_queried_object();
?>
<div class="gw-container gw-hub-archive">
	<header class="gw-page__header">
		<h1 class="gw-page__title"><?php echo esc_html( single_term_title( '', false ) ); ?></h1>
		<?php if ( $gw_term instanceof WP_Term && '' !== $gw_term->description ) : ?>
			<p class="gw-hub__intro"><?php echo esc_html( $gw_term->description ); ?></p>
		<?php endif; ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<ul class="gw-guides__grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<li><a href="<?php the_permalink(); ?>"><strong><?php the_title(); ?></strong><span><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></span></a></li>
			<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No guides in this topic yet.', 'greenworld' ); ?></p>
	<?php endif; ?>
	<div class="gw-richtext"><?php echo do_shortcode( '[gw_pillars]' ); ?></div>
</div>
<?php
get_footer();
