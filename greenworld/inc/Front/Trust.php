<?php
declare( strict_types=1 );

namespace GreenWorld\Front;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Trust / service strip. Registers the [gw_trust_badges] shortcode used in the
 * footer and homepage. Copy is deliberately factual - no medical claims.
 */
final class Trust implements Bootable {

	public function boot(): void {
		add_shortcode( 'gw_trust_badges', [ $this, 'badges' ] );
	}

	/**
	 * @return array<int,array{title:string,body:string,icon:string}>
	 */
	public static function items(): array {
		return [
			[
				'title' => __( 'Quality Products', 'greenworld' ),
				'body'  => __( 'Carefully selected health and wellness products.', 'greenworld' ),
				'icon'  => 'M9 12l2 2 4-4M12 3l7 4v5c0 4.4-3 7.6-7 9-4-1.4-7-4.6-7-9V7l7-4z',
			],
			[
				'title' => __( 'Secure Shopping', 'greenworld' ),
				'body'  => __( 'Safe, private and secure online checkout.', 'greenworld' ),
				'icon'  => 'M6 10V8a6 6 0 1 1 12 0v2M5 10h14v10H5V10z',
			],
			[
				'title' => __( 'Convenient Delivery', 'greenworld' ),
				'body'  => __( 'Reliable delivery options across Kenya.', 'greenworld' ),
				'icon'  => 'M3 7h11v8H3zM14 10h4l3 3v2h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
			],
			[
				'title' => __( 'Customer Support', 'greenworld' ),
				'body'  => __( 'Friendly assistance whenever you need it.', 'greenworld' ),
				'icon'  => 'M4 12a8 8 0 0 1 16 0v5a2 2 0 0 1-2 2h-2v-6h3M7 19H6a2 2 0 0 1-2-2v-5h3v6z',
			],
		];
	}

	public function badges( $atts = [] ): string {
		$items = self::items();
		$html  = '<div class="gw-trust__grid">';
		foreach ( $items as $it ) {
			$html .= '<div class="gw-trust__item">';
			$html .= '<span class="gw-trust__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' . esc_attr( $it['icon'] ) . '"/></svg></span>';
			$html .= '<span class="gw-trust__text"><span class="gw-trust__title">' . esc_html( $it['title'] ) . '</span><span class="gw-trust__body">' . esc_html( $it['body'] ) . '</span></span>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}
}
