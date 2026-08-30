<?php
/**
 * Default template for static Pages (About, policies, Contact, etc.).
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="gw-container gw-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'gw-page__article' ); ?>>
			<header class="gw-page__header">
				<h1 class="gw-page__title"><?php the_title(); ?></h1>
			</header>
			<div class="gw-page__content gw-richtext">
				<?php the_content(); ?>
				<?php wp_link_pages( array( 'before' => '<div class="gw-pagelinks">', 'after' => '</div>' ) ); ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
