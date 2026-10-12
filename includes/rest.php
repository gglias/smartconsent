<?php
/**
 * REST: POST smartconsent/v1/log, one call per choice from the banner. Public (pages are cached, so there is no
 * nonce to check), so every field is checked against a strict format and each address has an hourly limit.
 * The address is used only for that limit, as a keyed hash in a transient that expires, and is never stored.
 */
defined( 'ABSPATH' ) || exit;

const SMC_CLOUDFLARE = array(
	'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
	'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
	'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
	'2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
);
const SMC_LOG_LIMIT = 60; // choices per address per hour

function smc_ip_in( $ip, $cidr ) {
	list( $net, $bits ) = explode( '/', $cidr );
	$a = @inet_pton( $ip );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$b = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
		return false;
	}
	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	return 0 === $rest || ( ord( $a[ $bytes ] ) >> ( 8 - $rest ) ) === ( ord( $b[ $bytes ] ) >> ( 8 - $rest ) );
}

function smc_remote_addr() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/** Did this request really come through Cloudflare? (Its headers alone prove nothing: anyone can send them.) */
function smc_from_cloudflare() {
	$ip = smc_remote_addr();
	foreach ( SMC_CLOUDFLARE as $range ) {
		if ( smc_ip_in( $ip, $range ) ) {
			return true;
		}
	}
	return false;
}

/** The visitor's address: behind Cloudflare, its CF-Connecting-IP. Other proxies: the smartconsent_client_ip filter. */
function smc_client_ip() {
	$ip = smc_remote_addr();
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && smc_from_cloudflare() ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
	}
	$ip = (string) apply_filters( 'smartconsent_client_ip', $ip );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}
	// "::ffff:1.2.3.4" (dual-stack servers) is an IPv4 visitor: as IPv6 every one of them would share one /64.
	$bin = inet_pton( $ip );
	if ( 16 === strlen( $bin ) && str_repeat( "\0", 10 ) . "\xff\xff" === substr( $bin, 0, 12 ) ) {
		$ip = inet_ntop( substr( $bin, 12 ) );
	}
	return $ip;
}

/**
 * Hourly limits that apply to this request: [ transient key, limit ].
 * - the address: an IPv4 one, or a whole IPv6 /64 and its /48 (a free tunnel hands out a /48 = 65,536 /64s);
 * - an address that can't be read shares one small bucket instead of having none;
 * - the whole site, so no number of addresses can fill the database.
 */
function smc_rate_keys() {
	$ip   = smc_client_ip();
	$keys = array();
	$hash = function ( $v ) {
		return 'smc_rl_' . substr( hash_hmac( 'sha256', $v, wp_salt( 'nonce' ) ), 0, 32 );
	};
	if ( '' === $ip ) {
		$keys[] = array( $hash( 'unknown' ), 120 );
	} elseif ( false !== strpos( $ip, ':' ) ) {
		$bin    = inet_pton( $ip );
		$keys[] = array( $hash( bin2hex( substr( $bin, 0, 8 ) ) . '/64' ), SMC_LOG_LIMIT );
		$keys[] = array( $hash( bin2hex( substr( $bin, 0, 6 ) ) . '/48' ), SMC_LOG_LIMIT * 5 );
	} else {
		$keys[] = array( $hash( $ip ), SMC_LOG_LIMIT );
	}
	$keys[] = array( 'smc_rl_site', max( 100, (int) apply_filters( 'smartconsent_log_site_limit', 3000 ) ) );
	return $keys;
}

/** Hits of a key in its current one-hour window: [ count, window start ]. */
function smc_rate_hits( $key ) {
	$v = get_transient( $key );
	return ( is_array( $v ) && isset( $v[0], $v[1] ) && time() - (int) $v[1] < HOUR_IN_SECONDS ) ? array( (int) $v[0], (int) $v[1] ) : array( 0, time() );
}

function smc_nocache( WP_REST_Response $r ) {
	foreach ( wp_get_nocache_headers() as $k => $v ) {
		$r->header( $k, $v );
	}
	return $r;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'smartconsent/v1', '/log', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'smc_rest_log',
	) );
} );

function smc_rest_log( WP_REST_Request $req ) {
	$s   = smc_get_settings();
	$bad = smc_nocache( new WP_REST_Response( array( 'ok' => false ), 400 ) );
	if ( ! $s['enabled'] || ! $s['consent']['log'] ) {
		return smc_nocache( new WP_REST_Response( array( 'ok' => true, 'logged' => false ), 200 ) );
	}
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_body_params();
	}
	$id     = smc_str( $p, 'id' );
	$action = smc_str( $p, 'action' );
	$lang   = sanitize_key( smc_str( $p, 'lang' ) );
	$rev    = (int) smc_str( $p, 'rev' );
	$cats   = isset( $p['cats'] ) && is_array( $p['cats'] ) ? $p['cats'] : array();
	if ( ! preg_match( '/^[a-f0-9]{16}\z/', $id ) || ! in_array( $action, SMC_ACTIONS, true ) || ! in_array( $lang, smc_text_langs(), true ) || $rev < 1 || $rev > smc_rev() || count( $cats ) > count( SMC_CATS ) ) {
		return $bad;
	}
	$clean = array( 'necessary' );
	foreach ( $cats as $c ) {
		if ( ! is_string( $c ) || ! in_array( $c, SMC_CATS, true ) ) {
			return $bad;
		}
		if ( ! in_array( $c, $clean, true ) ) {
			$clean[] = $c;
		}
	}
	$limits = array();
	foreach ( smc_rate_keys() as $k ) {
		$hits = smc_rate_hits( $k[0] );
		if ( $hits[0] >= $k[1] ) {
			return smc_nocache( new WP_REST_Response( array( 'ok' => false, 'code' => 'limit' ), 429 ) );
		}
		$limits[] = array( $k[0], $hits );
	}
	// The same choice saved again within a minute (double click, two tabs) is one choice. Looked up in the log
	// itself (indexed by consent ID), so no extra rows are written for it.
	if ( smc_log_recent( $id, $action, $clean, MINUTE_IN_SECONDS ) ) {
		return smc_nocache( new WP_REST_Response( array( 'ok' => true, 'logged' => false ), 200 ) );
	}
	if ( ! smc_log_insert( $id, $lang, $action, $clean, $rev ) ) {
		return smc_nocache( new WP_REST_Response( array( 'ok' => false ), 500 ) );
	}
	foreach ( $limits as $l ) {
		set_transient( $l[0], array( $l[1][0] + 1, $l[1][1] ), HOUR_IN_SECONDS );
	}
	return smc_nocache( new WP_REST_Response( array( 'ok' => true, 'logged' => true ), 201 ) );
}
