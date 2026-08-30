<?php
/**
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="gw-container">
	<?php if ( have_posts() ) : ?>
		<div class="gw-posts">
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'gw-post' ); ?>>
					<h2 class="gw-post__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="gw-post__excerpt"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'greenworld' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
