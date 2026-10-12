<?php
/**
 * Plugin Name:       SmartConsent
 * Description:       Cookie consent banner with Google Consent Mode v2. Third-party scripts wait for the visitor's consent, the cookie policy table fills itself, and every choice is logged anonymously. WPML ready.
 * Version:           1.0.2
 * Author:            GG
 * Author URI:        https://gglias.github.io
 * Plugin URI:        https://github.com/gglias/smartconsent
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartconsent
 * Domain Path:       /languages
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Update URI:        https://github.com/gglias/smartconsent
 */

defined( 'ABSPATH' ) || exit;

define( 'SMC_VERSION', '1.0.2' );
define( 'SMC_DB_VERSION', '1' );
define( 'SMC_FILE', __FILE__ );
define( 'SMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMC_URL', plugin_dir_url( __FILE__ ) );
define( 'SMC_OPTION', 'smartconsent_settings' );
define( 'SMC_COOKIE', 'smartconsent' );

require_once SMC_DIR . 'includes/languages.php';
require_once SMC_DIR . 'includes/library.php';
require_once SMC_DIR . 'includes/defaults.php';
require_once SMC_DIR . 'includes/settings.php';
require_once SMC_DIR . 'includes/store.php';
require_once SMC_DIR . 'includes/rest.php';
require_once SMC_DIR . 'includes/front.php';
require_once SMC_DIR . 'includes/blocker.php';
require_once SMC_DIR . 'includes/shortcodes.php';
if ( is_admin() ) {
	require_once SMC_DIR . 'includes/policy.php';
	require_once SMC_DIR . 'includes/scan.php';
	require_once SMC_DIR . 'includes/admin.php';
}

add_action( 'init', function () {
	load_plugin_textdomain( 'smartconsent', false, dirname( plugin_basename( SMC_FILE ) ) . '/languages' );
} );

register_activation_hook( __FILE__, function () {
	smc_install_table();
	if ( ! wp_next_scheduled( 'smc_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smc_daily' );
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'smc_daily' );
} );

// Table upgrades after a zip update (activation hooks don't run on updates).
add_action( 'plugins_loaded', function () {
	if ( get_option( 'smartconsent_db' ) !== SMC_DB_VERSION ) {
		smc_install_table();
	}
} );

// The daily clean-up is scheduled on activation; if it ever goes missing (e.g. network activation), it is put back.
add_action( 'admin_init', function () {
	if ( ! wp_next_scheduled( 'smc_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smc_daily' );
	}
} );

/*
 * SmartConsent is updated only by uploading a new zip. The "Update URI" header already tells wordpress.org not to
 * offer updates for it; this makes sure no update offered from anywhere else can replace this code either.
 */
add_filter( 'site_transient_update_plugins', function ( $updates ) {
	$file = plugin_basename( SMC_FILE );
	if ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) {
		unset( $updates->response[ $file ] );
	}
	return $updates;
} );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	return ( is_object( $item ) && isset( $item->plugin ) && plugin_basename( SMC_FILE ) === $item->plugin ) ? false : $update;
}, 10, 2 );
