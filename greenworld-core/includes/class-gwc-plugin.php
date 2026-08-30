<?php
/**
 * Bootstrap: wires the modules together and handles (de)activation.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Plugin {

	public static function boot(): void {
		GWC_Settings::instance()->boot();
		GWC_Scan::instance()->boot();
		GWC_Consultation::instance()->boot();
		GWC_Records::instance()->boot();
		GWC_Account::instance()->boot();
		GWC_Distributor::instance()->boot();
		GWC_Points::instance()->boot();
		GWC_Ranks::instance()->boot();
		GWC_Compliance::instance()->boot();
		GWC_Dashboard::instance()->boot();
		GWC_Cases::instance()->boot();
		GWC_Customer_360::instance()->boot();
		GWC_Followup::instance()->boot();
		GWC_AI::instance()->boot();
		GWC_WhatsApp_Bot::instance()->boot();
		GWC_Cart_Recovery::instance()->boot();

		// Flush rewrite rules once per version so new endpoints (e.g. the
		// "My Health" account tab) work after a plugin update without the
		// admin having to re-save permalinks.
		add_action( 'init', array( 'GWC_Plugin', 'maybe_flush' ), 99 );
	}

	public static function maybe_flush(): void {
		if ( get_option( 'gwc_rewrite_version' ) !== GWC_VERSION ) {
			flush_rewrite_rules();
			update_option( 'gwc_rewrite_version', GWC_VERSION );
		}
	}

	public static function activate(): void {
		GWC_Scan::instance()->register_cpt();
		GWC_Records::instance()->register_cpts();
		GWC_Account::instance()->add_endpoint();
		GWC_Dashboard::instance()->add_endpoints();
		GWC_Distributor::instance()->add_endpoint();
		GWC_Points::instance()->register_cpt();
		GWC_Cart_Recovery::instance()->install();
		GWC_Followup::instance()->schedule();
		if ( class_exists( 'GWC_Ranks' ) ) {
			GWC_Ranks::instance()->seed_baselines();
		}
		flush_rewrite_rules();
		update_option( 'gwc_rewrite_version', GWC_VERSION );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( GWC_Cart_Recovery::CRON_HOOK );
		wp_clear_scheduled_hook( GWC_Followup::CRON_HOOK );
		flush_rewrite_rules();
	}
}
