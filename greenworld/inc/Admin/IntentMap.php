<?php
declare( strict_types=1 );

namespace GreenWorld\Admin;

use GreenWorld\Core\Bootable;
use GreenWorld\Content\TopicMap;
use GreenWorld\Content\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * IntentMap — a read-only admin reference page ("Topical Authority") that shows
 * the canonical destination for each search intent plus the live build status of
 * every pillar, landing page and guide topic. It is the anti-cannibalisation
 * reference: one intent, one page.
 */
final class IntentMap implements Bootable {

	public function boot(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Topical Authority', 'greenworld' ),
			__( 'Topical Authority', 'greenworld' ),
			'manage_options',
			'gw-topical-authority',
			[ $this, 'render' ],
			'dashicons-networking',
			58
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Topical Authority & Search Intent Map', 'greenworld' ) . '</h1>';
		echo '<p>' . esc_html__( 'One canonical page per search intent. Use this map to avoid two pages competing for the same query.', 'greenworld' ) . '</p>';

		echo '<h2>' . esc_html__( 'Search intent map', 'greenworld' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Search intent', 'greenworld' ) . '</th><th>' . esc_html__( 'Canonical page', 'greenworld' ) . '</th><th>' . esc_html__( 'Type', 'greenworld' ) . '</th><th>' . esc_html__( 'Note', 'greenworld' ) . '</th></tr></thead><tbody>';
		foreach ( TopicMap::intent_map() as $row ) {
			echo '<tr><td><code>' . esc_html( (string) $row['query'] ) . '</code></td><td>' . esc_html( (string) $row['target'] ) . '</td><td>' . esc_html( (string) $row['type'] ) . '</td><td>' . esc_html( (string) $row['note'] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Pillar status', 'greenworld' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Pillar', 'greenworld' ) . '</th><th>' . esc_html__( 'Category', 'greenworld' ) . '</th><th>' . esc_html__( 'Landing page', 'greenworld' ) . '</th><th>' . esc_html__( 'Guides', 'greenworld' ) . '</th></tr></thead><tbody>';
		foreach ( TopicMap::pillars() as $key => $p ) {
			$cat_ok = ( Relations::category( (string) $p['cat'] ) instanceof \WP_Term ) ? '&#10003;' : '&mdash;';
			if ( ! empty( $p['landing'] ) ) {
				$land    = Relations::landing_url( (string) $p['landing'] );
				$land_td = '' !== $land ? '<a href="' . esc_url( $land ) . '">' . esc_html( (string) $p['landing'] ) . '</a>' : esc_html__( 'not built', 'greenworld' );
			} else {
				$land_td = esc_html__( 'content hub', 'greenworld' );
			}
			$guides = count( Relations::guides_for_pillar( (string) $key, 20 ) );
			echo '<tr><td><strong>' . esc_html( (string) $p['label'] ) . '</strong></td><td>' . wp_kses_post( $cat_ok ) . ' <code>' . esc_html( (string) $p['cat'] ) . '</code></td><td>' . wp_kses_post( $land_td ) . '</td><td>' . esc_html( (string) $guides ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Keep one landing page per commercial intent. Route informational "what is" queries to guides, and product-name / price queries to product pages.', 'greenworld' ) . '</p>';
		echo '</div>';
	}
}
