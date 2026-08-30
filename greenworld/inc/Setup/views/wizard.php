<?php
/**
 * Setup Wizard shell. Steps are progressively enhanced by assets/js/wizard.js.
 *
 * @package GreenWorld
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="gw-wizard" id="gw-wizard">
	<header class="gw-wizard__head">
		<h1>GreenWorld Setup</h1>
		<ol class="gw-wizard__steps" aria-label="<?php esc_attr_e( 'Setup steps', 'greenworld' ); ?>">
			<li data-step="1" class="is-active"><?php esc_html_e( 'Welcome', 'greenworld' ); ?></li>
			<li data-step="2"><?php esc_html_e( 'Plugins', 'greenworld' ); ?></li>
			<li data-step="3"><?php esc_html_e( 'Activate', 'greenworld' ); ?></li>
			<li data-step="4"><?php esc_html_e( 'Demo Content', 'greenworld' ); ?></li>
			<li data-step="5"><?php esc_html_e( 'Finish', 'greenworld' ); ?></li>
		</ol>
	</header>

	<section class="gw-wizard__panel" data-panel="1" aria-hidden="false">
		<h2><?php esc_html_e( 'Welcome to GreenWorld', 'greenworld' ); ?></h2>
		<p><?php esc_html_e( 'This wizard installs the plugins your store needs, activates them, and imports professional demo content tailored for a health and wellness store. You can re-run it any time from Appearance → GreenWorld Setup.', 'greenworld' ); ?></p>
		<button class="button button-primary gw-next"><?php esc_html_e( 'Let’s go', 'greenworld' ); ?></button>
	</section>

	<section class="gw-wizard__panel" data-panel="2" aria-hidden="true" hidden>
		<h2><?php esc_html_e( 'Install Required Plugins', 'greenworld' ); ?></h2>
		<ul class="gw-plugin-list" id="gw-plugin-list"></ul>
		<button class="button button-primary gw-install-all"><?php esc_html_e( 'Install &amp; Activate All', 'greenworld' ); ?></button>
		<button class="button gw-next"><?php esc_html_e( 'Skip', 'greenworld' ); ?></button>
	</section>

	<section class="gw-wizard__panel" data-panel="3" aria-hidden="true" hidden>
		<h2><?php esc_html_e( 'Activating', 'greenworld' ); ?></h2>
		<p id="gw-activate-log" role="status" aria-live="polite"></p>
		<button class="button button-primary gw-next"><?php esc_html_e( 'Continue', 'greenworld' ); ?></button>
	</section>

	<section class="gw-wizard__panel" data-panel="4" aria-hidden="true" hidden>
		<h2><?php esc_html_e( 'Import Demo Content', 'greenworld' ); ?></h2>
		<p><?php esc_html_e( 'Imports categories, sample products, pages (About, Contact, Policies, FAQs) and the primary menu. Safe to re-run.', 'greenworld' ); ?></p>
		<button class="button button-primary gw-import"><?php esc_html_e( 'Import Demo', 'greenworld' ); ?></button>
		<p id="gw-import-log" role="status" aria-live="polite"></p>
		<button class="button gw-next"><?php esc_html_e( 'Continue', 'greenworld' ); ?></button>
	</section>

	<section class="gw-wizard__panel" data-panel="5" aria-hidden="true" hidden>
		<h2><?php esc_html_e( 'Your store is ready', 'greenworld' ); ?></h2>
		<p><?php esc_html_e( 'Next: verify your business details, review Merchant Compliance, and connect payments (M-Pesa, Cash on Delivery, Bank Transfer).', 'greenworld' ); ?></p>
		<a class="button button-primary" id="gw-finish" href="#"><?php esc_html_e( 'Go to Dashboard', 'greenworld' ); ?></a>
	</section>
</div>
