<?php
declare( strict_types=1 );

namespace GreenWorld\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Central service container / bootstrapper.
 *
 * Follows a lightweight dependency-injection pattern: every feature module is a
 * class implementing Bootable::boot(). Adding a feature = registering it here.
 */
final class Theme {

	/**
	 * Feature modules, resolved lazily.
	 *
	 * @return array<int, class-string>
	 */
	private function modules(): array {
		return [
			\GreenWorld\Core\Assets::class,
			\GreenWorld\Setup\Supports::class,
			\GreenWorld\Setup\Menus::class,
			\GreenWorld\Setup\Setup_Wizard::class,
			\GreenWorld\Setup\Plugin_Installer::class,
			\GreenWorld\Woo\WooCommerce::class,
			\GreenWorld\Woo\QuickView::class,
			\GreenWorld\Woo\Filters::class,
			\GreenWorld\Woo\Product_Identifiers::class,
			\GreenWorld\Seo\Schema::class,
			\GreenWorld\Seo\Meta::class,
			\GreenWorld\Seo\Robots::class,
			\GreenWorld\Seo\MetaBox::class,
			\GreenWorld\Seo\Breadcrumbs::class,
			\GreenWorld\Content\ContentTypes::class,
			\GreenWorld\Content\Seeder::class,
			\GreenWorld\Content\InternalLinks::class,
			\GreenWorld\Admin\IntentMap::class,
			\GreenWorld\Performance\Optimizer::class,
			\GreenWorld\Security\Headers::class,
			\GreenWorld\Admin\Dashboard::class,
			\GreenWorld\Admin\ProductList::class,
			\GreenWorld\Admin\HomeCategories::class,
			\GreenWorld\Compat\Elementor::class,
			\GreenWorld\Customizer\Customizer::class,
			\GreenWorld\Customizer\HomepagePanel::class,
			\GreenWorld\Search\AjaxSearch::class,
			\GreenWorld\Account\Registration::class,
			\GreenWorld\Front\Trust::class,
			\GreenWorld\Front\Consultation::class,
			\GreenWorld\Front\HomeAssets::class,
			\GreenWorld\Front\TrustCenter::class,
		];
	}

	public function boot(): void {
		load_theme_textdomain( 'greenworld', GREENWORLD_DIR . 'languages' );

		foreach ( $this->modules() as $module ) {
			if ( class_exists( $module ) ) {
				$instance = new $module();
				if ( $instance instanceof Bootable ) {
					$instance->boot();
				}
			}
		}
	}
}
