<?php
/**
 * Site scan and home page check (admin only).
 *
 * Scan: the admin screen loads pages of the site in hidden frames, twice (no consent, then "accept all"), and reads
 * which cookies appear and which other sites are contacted. This file only hands out the pages and a short-lived
 * token that lets those two modes run (see smc_scan_mode()); the reading is done by admin.js.
 *
 * Check: the home page is fetched the way a visitor gets it, to see whether a Google tag arrives from outside the
 * plugin and whether the "Cookies" link is on the page.
 */
defined( 'ABSPATH' ) || exit;

const SMC_SCAN_MAX = 14;

/** Pages to scan: the home page in each language, then recent pages and posts, and the cookie policy. */
function smc_scan_urls() {
	$urls = array();
	foreach ( smc_lang_codes() as $lang ) {
		$urls[] = (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang, true );
	}
	foreach ( smc_get_settings()['policy']['pages'] as $pid ) {
		if ( 'publish' === get_post_status( $pid ) ) {
			$urls[] = get_permalink( $pid );
		}
	}
	$posts = get_posts( array(
		'post_type'        => array( 'page', 'post' ),
		'post_status'      => 'publish',
		'numberposts'      => 30,
		'orderby'          => 'modified',
		'has_password'     => false,
		'suppress_filters' => true,
	) );
	foreach ( $posts as $p ) {
		$urls[] = get_permalink( $p );
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$out  = array();
	foreach ( $urls as $u ) {
		if ( $u && wp_parse_url( $u, PHP_URL_HOST ) === $host && ! in_array( $u, $out, true ) ) {
			$out[] = $u;
		}
		if ( count( $out ) >= SMC_SCAN_MAX ) {
			break;
		}
	}
	return $out;
}

function smc_scan_start() {
	$token = strtolower( wp_generate_password( 24, false, false ) );
	set_transient( 'smc_scan_' . get_current_user_id(), $token, 30 * MINUTE_IN_SECONDS );
	return array( 'token' => $token, 'urls' => smc_scan_urls() );
}

/** The last scan's findings, kept for the overview. Only the shape admin.js sends is kept. */
function smc_scan_store( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$names = function ( $list ) {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $n ) {
			$n = smc_clean_cookie_name( $n );
			if ( '' !== $n && count( $out ) < 100 ) {
				$out[] = $n;
			}
		}
		return array_values( array_unique( $out ) );
	};
	$hosts = array();
	foreach ( isset( $raw['hosts'] ) && is_array( $raw['hosts'] ) ? $raw['hosts'] : array() as $h ) {
		$h = strtolower( is_scalar( $h ) ? (string) $h : '' );
		if ( preg_match( '/^[a-z0-9.-]{3,253}$/', $h ) && count( $hosts ) < 60 ) {
			$hosts[] = $h;
		}
	}
	$clean = array(
		'time'      => time(),
		'pages'     => min( SMC_SCAN_MAX, absint( smc_str( $raw, 'pages' ) ) ),
		'failed'    => min( SMC_SCAN_MAX, absint( smc_str( $raw, 'failed' ) ) ),
		'before'    => $names( isset( $raw['before'] ) ? $raw['before'] : array() ),  // set without consent
		'after'     => $names( isset( $raw['after'] ) ? $raw['after'] : array() ),    // set after "accept all"
		'unknown'   => $names( isset( $raw['unknown'] ) ? $raw['unknown'] : array() ),// not in the cookie table
		'hosts'     => array_values( array_unique( $hosts ) ),                         // other sites before consent
		'foreign'   => array_values( array_intersect( array_keys( smc_library() ), isset( $raw['foreign'] ) && is_array( $raw['foreign'] ) ? array_map( 'strval', $raw['foreign'] ) : array() ) ),
	);
	update_option( 'smartconsent_scan', $clean, false );
	return $clean;
}

/**
 * Fetches a page of the site. $as_admin sends the current administrator's login cookies along (only to this same
 * site), so the page comes as they see it: past "Coming soon" / maintenance screens that visitors may get.
 * Returns the HTML, or null.
 */
function smc_fetch_own( $url, $as_admin ) {
	$args = array(
		'timeout'     => 15,
		'redirection' => 3,
		'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
		'headers'     => array( 'Cache-Control' => 'no-cache' ),
		'user-agent'  => 'SmartConsent check; ' . home_url( '/' ),
	);
	if ( $as_admin && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( admin_url(), PHP_URL_HOST ) ) {
		$args['cookies'] = array();
		$names           = array_filter( array(
			defined( 'LOGGED_IN_COOKIE' ) ? LOGGED_IN_COOKIE : '',
			defined( 'AUTH_COOKIE' ) ? AUTH_COOKIE : '',
			defined( 'SECURE_AUTH_COOKIE' ) ? SECURE_AUTH_COOKIE : '',
		) );
		foreach ( $names as $name ) {
			if ( isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$args['cookies'][] = new WP_Http_Cookie( array( 'name' => $name, 'value' => wp_unslash( $_COOKIE[ $name ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
	}
	$res = wp_remote_get( add_query_arg( 'smc_check', wp_rand( 1000, 999999 ), $url ), $args ); // a fresh copy, past page caches
	return ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) ? null : (string) wp_remote_retrieve_body( $res );
}

/** Is the "Cookies" link on this HTML? 'yes', 'text' (the shortcode printed as plain text: that field runs no shortcodes) or 'no'. */
function smc_find_link( $html ) {
	if ( preg_match( '~href=["\'][^"\']*#smartconsent["\']|class=["\'][^"\']*\b(?:smartconsent-open|js-smartconsent)\b~', $html ) ) {
		return 'yes';
	}
	return false !== strpos( $html, '[smartconsent' ) ? 'text' : 'no';
}

/**
 * Home page check.
 * - As a visitor: are tags arriving from outside the plugin, and do visitors get the real page at all?
 * - As the administrator, in every language: is the "Cookies" link there? (Footers are often one per language.)
 */
function smc_check_home() {
	$s     = smc_get_settings();
	$visit = smc_fetch_own( home_url( '/' ), false );
	$links = array();
	foreach ( smc_lang_codes() as $lang ) {
		$url  = (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang, true );
		$html = smc_fetch_own( add_query_arg( 'smartconsent_preview', '1', $url ), true );
		$links[ $lang ] = null === $html ? 'failed' : smc_find_link( $html );
	}
	if ( null === $visit && ! array_diff( $links, array( 'failed' ) ) ) {
		return array( 'ok' => false );
	}
	$foreign = array();
	if ( null !== $visit && preg_match_all( '~data-smc-blocked="([a-z0-9_-]+)"~', $visit, $m ) ) {
		$foreign = array_values( array_intersect( array_unique( $m[1] ), array_keys( smc_library() ) ) );
	}
	$core = null !== $visit && false !== strpos( $visit, 'window.smartConsentCore' );
	return array(
		'ok'      => true,
		'core'    => $core,
		// Switched on, yet what visitors get has no SmartConsent in it: a "Coming soon" or maintenance page, most likely.
		'gated'   => $s['enabled'] && null !== $visit && ! $core,
		'link'    => in_array( 'yes', $links, true ) && ! array_diff( $links, array( 'yes', 'failed' ) ),
		'links'   => $links,
		'foreign' => $foreign,
		'time'    => time(),
	);
}
