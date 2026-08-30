<?php
/**
 * Related-content block for single guides. Complements (does not duplicate) the
 * in-body shortcodes: a shop CTA back to the guide's pillar plus sibling guides.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;

use GreenWorld\Content\Relations;
use GreenWorld\Content\TopicMap;
use GreenWorld\Content\InternalLinks;

InternalLinks::styles();

$gw_gid       = (int) get_the_ID();
$gw_pillarKey = (string) get_query_var( 'gw_pillar_key' );
$gw_pillar    = '' !== $gw_pillarKey ? TopicMap::pillar( $gw_pillarKey ) : null;

$gw_topics = get_the_terms( $gw_gid, TopicMap::GUIDE_TAX );
$gw_topic  = ( is_array( $gw_topics ) && isset( $gw_topics[0] ) && $gw_topics[0] instanceof WP_Term ) ? $gw_topics[0]->slug : '';
$gw_more   = Relations::guides_for_topic( $gw_topic, 5 );
$gw_others = array_values( array_filter( $gw_more, static function ( $g ) use ( $gw_gid ) {
	return (int) $g->ID !== $gw_gid;
} ) );
?>
<aside class="gw-container gw-guide-related">
	<?php
	if ( null !== $gw_pillar ) :
		$gw_purl = Relations::pillar_url( $gw_pillar );
		?>
		<div class="gw-guide-related__cta">
			<h2><?php echo esc_html( 'Shop ' . (string) $gw_pillar['label'] ); ?></h2>
			<p><?php echo esc_html( (string) $gw_pillar['blurb'] ); ?></p>
			<?php if ( '' !== $gw_purl ) : ?>
				<a class="gw-btn" href="<?php echo esc_url( $gw_purl ); ?>"><?php esc_html_e( 'Browse products', 'greenworld' ); ?> &rarr;</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $gw_others ) > 0 ) : ?>
		<div class="gw-guides">
			<h2><?php esc_html_e( 'More guides', 'greenworld' ); ?></h2>
			<ul class="gw-guides__grid">
				<?php foreach ( array_slice( $gw_others, 0, 4 ) as $gw_g ) : ?>
					<li><a href="<?php echo esc_url( (string) get_permalink( $gw_g ) ); ?>"><strong><?php echo esc_html( get_the_title( $gw_g ) ); ?></strong></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</aside>
