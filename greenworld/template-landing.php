<?php
/**
 * Template Name: GreenWorld Landing Page
 *
 * Commercial pillar landing pages (the topical authority layer). The body is
 * composed from TopicMap-driven shortcodes and FAQ blocks by the Seeder, and can
 * be edited freely in the block editor.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="gw-container gw-landing">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'gw-landing__article' ); ?>>
			<header class="gw-landing__header">
				<h1 class="gw-landing__title"><?php the_title(); ?></h1>
			</header>
			<div class="gw-landing__content gw-richtext">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
