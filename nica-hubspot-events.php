<?php
/**
 * Plugin Name: NICA HubSpot Events
 * Description: Copies this league's events from the HubSpot Events object into WordPress, with a [nica_events] listing and an auto-generated page for each event.
 * Version:     0.2.1
 * Author:      NICA
 * Requires PHP: 7.4
 * Text Domain: nica-hubspot-events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NICA_HSE_VERSION', '0.2.1' );
define( 'NICA_HSE_FILE', __FILE__ );
define( 'NICA_HSE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NICA_HSE_URL', plugin_dir_url( __FILE__ ) );

require_once NICA_HSE_DIR . 'includes/class-nica-hse-util.php';
require_once NICA_HSE_DIR . 'includes/class-nica-hse-hubspot.php';
require_once NICA_HSE_DIR . 'includes/class-nica-hse-settings.php';
require_once NICA_HSE_DIR . 'includes/class-nica-hse-render.php';
require_once NICA_HSE_DIR . 'includes/class-nica-hse-sync.php';

/*
 * Updates from GitHub. Put your repo in wp-config.php (or edit the default here):
 *   define( 'NICA_HSE_GITHUB_REPO', 'https://github.com/YOUR-ORG/nica-hubspot-events/' );
 *   define( 'NICA_HSE_GITHUB_TOKEN', 'read-only token' ); // only for a private repo
 * Sites then show "Update available" whenever a new Release (tag) is published on GitHub.
 */
if ( ! defined( 'NICA_HSE_GITHUB_REPO' ) ) {
	define( 'NICA_HSE_GITHUB_REPO', '' );
}
if ( '' !== NICA_HSE_GITHUB_REPO && ( is_admin() || wp_doing_cron() || defined( 'WP_CLI' ) ) ) {
	require_once NICA_HSE_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
	$nica_hse_updater = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker( NICA_HSE_GITHUB_REPO, NICA_HSE_FILE, 'nica-hubspot-events' );
	if ( defined( 'NICA_HSE_GITHUB_TOKEN' ) && '' !== NICA_HSE_GITHUB_TOKEN ) {
		$nica_hse_updater->setAuthentication( NICA_HSE_GITHUB_TOKEN );
	}
}

NICA_HSE_Settings::init();
NICA_HSE_Render::init();
NICA_HSE_Sync::init();

register_activation_hook(
	__FILE__,
	function () {
		NICA_HSE_Render::register_post_type();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( NICA_HSE_Sync::CRON ) ) {
			wp_schedule_event( time() + 60, NICA_HSE_Sync::INTERVAL, NICA_HSE_Sync::CRON );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( NICA_HSE_Sync::CRON );
		flush_rewrite_rules();
	}
);
