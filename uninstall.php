<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Leave nothing behind (on every site of a multisite network): the consent log, the settings, the scheduled
// clean-up, the scan result, the rate-limit transients and every user's EN/ΕΛ choice.
// The cookie policy pages are content the site owner may have edited, so they stay.
function smc_uninstall_site() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'smartconsent_log' ); // phpcs:ignore WordPress.DB
	foreach ( array( 'smartconsent_settings', 'smartconsent_db', 'smartconsent_rev', 'smartconsent_scan', 'smartconsent_cf_seen' ) as $opt ) {
		delete_option( $opt );
	}
	wp_clear_scheduled_hook( 'smc_daily' );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_smc\_%' OR option_name LIKE '\_transient\_timeout\_smc\_%'" ); // phpcs:ignore WordPress.DB
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		smc_uninstall_site();
		restore_current_blog();
	}
} else {
	smc_uninstall_site();
}
delete_metadata( 'user', 0, 'smc_ui_lang', '', true );
