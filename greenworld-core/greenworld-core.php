<?php
/**
 * Plugin Name:       Green World Core
 * Plugin URI:        https://greenworldhealth.co.ke/
 * Description:       Business logic for Green World Health Solutions: automatic WhatsApp notifications (Meta Cloud API), scan bookings, the customer health dashboard, and the full distributor programme (dashboard, admin activation, product point values, batch allocation and a points ledger). Keeps this data independent of the active theme.
 * Version:           0.16.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Green World Health Solutions
 * Text Domain:       greenworld-core
 */

defined( 'ABSPATH' ) || exit;

define( 'GWC_VERSION', '0.16.1' );
define( 'GWC_FILE', __FILE__ );
define( 'GWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'GWC_URL', plugin_dir_url( __FILE__ ) );

require_once GWC_DIR . 'includes/class-gwc-settings.php';
require_once GWC_DIR . 'includes/class-gwc-whatsapp.php';
require_once GWC_DIR . 'includes/class-gwc-scan.php';
require_once GWC_DIR . 'includes/class-gwc-consultation.php';
require_once GWC_DIR . 'includes/class-gwc-records.php';
require_once GWC_DIR . 'includes/class-gwc-account.php';
require_once GWC_DIR . 'includes/class-gwc-distributor.php';
require_once GWC_DIR . 'includes/class-gwc-points.php';
require_once GWC_DIR . 'includes/class-gwc-ranks.php';
require_once GWC_DIR . 'includes/class-gwc-cart-recovery.php';
require_once GWC_DIR . 'includes/class-gwc-compliance.php';
require_once GWC_DIR . 'includes/class-gwc-dashboard.php';
require_once GWC_DIR . 'includes/class-gwc-cases.php';
require_once GWC_DIR . 'includes/class-gwc-customer360.php';
require_once GWC_DIR . 'includes/class-gwc-followup.php';
require_once GWC_DIR . 'includes/class-gwc-ai-providers.php';
require_once GWC_DIR . 'includes/class-gwc-ai-safety.php';
require_once GWC_DIR . 'includes/class-gwc-ai-log.php';
require_once GWC_DIR . 'includes/class-gwc-ai.php';
require_once GWC_DIR . 'includes/class-gwc-whatsapp-bot.php';
require_once GWC_DIR . 'includes/class-gwc-plugin.php';

register_activation_hook( __FILE__, array( 'GWC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GWC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GWC_Plugin', 'boot' ) );
