<?php
/**
 * Consent log: one anonymous row per choice. No IP, no user agent, nothing that points to a person; only the random
 * consent ID that also sits in the visitor's own cookie, so a choice can be found again from it. Rows arrive through
 * a public endpoint (pages are cached, so nothing can be signed), so this is a record of what the banner sent, not
 * tamper-proof evidence: anyone who knows an ID could add a row for it.
 */
defined( 'ABSPATH' ) || exit;

const SMC_ACTIONS = array( 'accept', 'reject', 'custom', 'gpc' );

function smc_table() {
	global $wpdb;
	return $wpdb->prefix . 'smartconsent_log';
}

function smc_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = smc_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		consent_id char(16) NOT NULL DEFAULT '',
		lang varchar(10) NOT NULL DEFAULT '',
		action varchar(10) NOT NULL DEFAULT '',
		cats varchar(60) NOT NULL DEFAULT '',
		rev int(10) unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY consent_id (consent_id)
	) {$charset};" );
	update_option( 'smartconsent_db', SMC_DB_VERSION, true );
}

function smc_log_insert( $consent_id, $lang, $action, array $cats, $rev ) {
	global $wpdb;
	return (bool) $wpdb->insert( smc_table(), array(
		'created_at' => current_time( 'mysql', true ),
		'consent_id' => $consent_id,
		'lang'       => $lang,
		'action'     => $action,
		'cats'       => implode( ',', $cats ),
		'rev'        => (int) $rev,
	), array( '%s', '%s', '%s', '%s', '%s', '%d' ) );
}

/** Was the same choice (ID, action, categories) logged in the last $seconds? */
function smc_log_recent( $consent_id, $action, array $cats, $seconds ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . smc_table() . ' WHERE consent_id = %s AND action = %s AND cats = %s AND created_at >= %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL
		$consent_id,
		$action,
		implode( ',', $cats ),
		gmdate( 'Y-m-d H:i:s', time() - $seconds )
	) );
}

/** WHERE clause of the log list: action tab and consent ID search. */
function smc_log_where( array $args ) {
	global $wpdb;
	$w = array( '1=1' );
	if ( in_array( $args['action'], SMC_ACTIONS, true ) ) {
		$w[] = $wpdb->prepare( 'action = %s', $args['action'] );
	}
	if ( '' !== $args['q'] ) {
		$w[] = $wpdb->prepare( 'consent_id LIKE %s', $wpdb->esc_like( $args['q'] ) . '%' );
	}
	return implode( ' AND ', $w );
}

function smc_log_list( array $args, $page, $per ) {
	global $wpdb;
	$where = smc_log_where( $args );
	$table = smc_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	return array( 'total' => $total, 'rows' => (array) $rows );
}

/** Rows per action (for the tabs), with the ID search applied. */
function smc_log_counts( $q = '' ) {
	global $wpdb;
	$where = smc_log_where( array( 'action' => '', 'q' => $q ) );
	$out   = array( 'all' => 0 );
	foreach ( SMC_ACTIONS as $a ) {
		$out[ $a ] = 0;
	}
	foreach ( (array) $wpdb->get_results( 'SELECT action, COUNT(*) n FROM ' . smc_table() . " WHERE {$where} GROUP BY action", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
		if ( isset( $out[ $r['action'] ] ) ) {
			$out[ $r['action'] ] = (int) $r['n'];
			$out['all']        += (int) $r['n'];
		}
	}
	return $out;
}

/** Choices of the last $days days: per action, and per language and action. */
function smc_log_stats( $days = 30 ) {
	global $wpdb;
	$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT lang, action, COUNT(*) n FROM ' . smc_table() . ' WHERE created_at >= %s GROUP BY lang, action', $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$total = array( 'all' => 0 );
	$langs = array();
	foreach ( SMC_ACTIONS as $a ) {
		$total[ $a ] = 0;
	}
	foreach ( (array) $rows as $r ) {
		$a = $r['action'];
		$n = (int) $r['n'];
		if ( ! isset( $total[ $a ] ) ) {
			continue;
		}
		$total[ $a ]  += $n;
		$total['all'] += $n;
		if ( ! isset( $langs[ $r['lang'] ] ) ) {
			$langs[ $r['lang'] ] = array_fill_keys( array_merge( array( 'all' ), SMC_ACTIONS ), 0 );
		}
		$langs[ $r['lang'] ][ $a ]    += $n;
		$langs[ $r['lang'] ]['all']   += $n;
	}
	return array( 'total' => $total, 'langs' => $langs );
}

/* Daily: rows older than the retention period go. */
add_action( 'smc_daily', 'smc_log_cleanup' );

function smc_log_cleanup() {
	global $wpdb;
	$months = (int) smc_get_settings()['consent']['retention'];
	$before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $months ) . ' months' ) );
	return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . smc_table() . ' WHERE created_at < %s', $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}
