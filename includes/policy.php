<?php
/**
 * "Create the cookie policy page": one draft page per language (Greek and English, or only the site language
 * without WPML), linked to each other as WPML translations, with the cookie table inside. Drafts, so they are read
 * before they go live.
 */
defined( 'ABSPATH' ) || exit;

/** Languages that get a page: the active ones that have a page text. */
function smc_policy_langs() {
	$texts = smc_policy_page_texts();
	$langs = array_values( array_filter( smc_lang_codes(), function ( $l ) use ( $texts ) {
		return isset( $texts[ $l ] );
	} ) );
	return $langs ? $langs : array( isset( $texts[ smc_default_lang() ] ) ? smc_default_lang() : 'en' );
}

/** Creates the pages that are missing. Returns lang => page id, and saves them in the settings. */
function smc_create_policy_pages() {
	$s      = smc_get_settings();
	$texts  = smc_policy_page_texts();
	$months = (int) $s['consent']['months'];
	$pages  = $s['policy']['pages'];
	$trid   = null;
	$source = null;
	$wpml   = has_action( 'wpml_set_element_language_details' );
	foreach ( $pages as $lang => $pid ) {
		if ( 'page' !== get_post_type( $pid ) || 'trash' === get_post_status( $pid ) ) {
			unset( $pages[ $lang ] );
		}
	}
	/*
	 * Pages that already exist are checked in WPML first. One filed under another language, or with no language at
	 * all (1.0.0 could leave the English page like that: invisible in WPML's lists), gets its right language and is
	 * joined to the others. The group (trid) is taken from a page that is already right, if there is one.
	 */
	if ( $wpml && $pages ) {
		foreach ( $pages as $lang => $pid ) {
			if ( smc_wpml_lang( $pid ) === $lang ) {
				$trid   = apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
				$source = $lang;
				break;
			}
		}
		foreach ( $pages as $lang => $pid ) {
			$own = apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
			if ( smc_wpml_lang( $pid ) === $lang && ( ! $trid || (string) $own === (string) $trid ) ) {
				continue; // right language, already in the group
			}
			do_action( 'wpml_set_element_language_details', array(
				'element_id'           => $pid,
				'element_type'         => 'post_page',
				'trid'                 => $trid ? $trid : ( $own ? $own : false ),
				'language_code'        => $lang,
				'source_language_code' => $trid ? $source : null,
			) );
			if ( ! $trid ) {
				$trid   = apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
				$source = $lang;
			}
		}
	}
	/*
	 * WPML gives a new page the language the admin is currently in (e.g. Greek) as soon as it is saved. So each page
	 * is created with WPML switched to that page's language, then the language is set once more explicitly and the
	 * pages are joined as translations of one another (same trid).
	 */
	$current = $wpml ? apply_filters( 'wpml_current_language', null ) : null;
	foreach ( smc_policy_langs() as $lang ) {
		if ( isset( $pages[ $lang ] ) ) {
			continue;
		}
		if ( $wpml ) {
			do_action( 'wpml_switch_language', $lang );
		}
		$t    = $texts[ $lang ];
		$body = str_replace( array( '{table}', '{months}' ), array( '[smartconsent_cookies]', (string) $months ), $t['body'] );
		$pid  = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $t['title'],
			'post_name'    => $t['slug'],
			'post_content' => $body,
		), true );
		if ( is_wp_error( $pid ) || ! $pid ) {
			continue;
		}
		if ( $wpml ) {
			if ( ! $trid ) {
				// The first page: keep the trid WPML gave it on save, with the right language.
				$own = apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
				do_action( 'wpml_set_element_language_details', array(
					'element_id'           => $pid,
					'element_type'         => 'post_page',
					'trid'                 => $own ? $own : false,
					'language_code'        => $lang,
					'source_language_code' => null,
				) );
				$trid   = apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
				$source = $lang;
			} else {
				do_action( 'wpml_set_element_language_details', array(
					'element_id'           => $pid,
					'element_type'         => 'post_page',
					'trid'                 => $trid,
					'language_code'        => $lang,
					'source_language_code' => $source,
				) );
			}
		}
		$pages[ $lang ] = (int) $pid;
	}
	if ( $wpml && $current ) {
		do_action( 'wpml_switch_language', $current );
	}
	$s['policy']['pages'] = $pages;
	smc_save_settings( $s );
	return $pages;
}

/** The language WPML has for a page, or '' (no WPML, or the page has no language row). */
function smc_wpml_lang( $pid ) {
	$info = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => (int) $pid, 'element_type' => 'post_page' ) );
	return is_object( $info ) && isset( $info->language_code ) ? sanitize_key( (string) $info->language_code ) : '';
}

/** Are the policy pages right in WPML: each in its own language, all in one group of translations? */
function smc_policy_wpml_ok() {
	if ( ! has_action( 'wpml_set_element_language_details' ) ) {
		return true;
	}
	$trids = array();
	foreach ( smc_get_settings()['policy']['pages'] as $lang => $pid ) {
		if ( 'trash' === get_post_status( $pid ) || ! get_post_status( $pid ) ) {
			continue;
		}
		if ( smc_wpml_lang( $pid ) !== $lang ) {
			return false;
		}
		$trids[] = (string) apply_filters( 'wpml_element_trid', null, $pid, 'post_page' );
	}
	return count( array_unique( $trids ) ) <= 1;
}

/** State of the chosen pages, for the admin: lang => [ id, title, status, edit, view ]. */
function smc_policy_state() {
	$out = array();
	foreach ( smc_get_settings()['policy']['pages'] as $lang => $pid ) {
		$st = get_post_status( $pid );
		if ( ! $st || 'trash' === $st ) {
			continue;
		}
		// The language WPML actually has for the page: a page filed under the wrong one (or none) is shown as a warning.
		$real = smc_wpml_lang( $pid );
		$out[ $lang ] = array(
			'id'     => (int) $pid,
			'title'  => get_the_title( $pid ),
			'status' => $st,
			'edit'   => (string) get_edit_post_link( $pid, 'raw' ),
			'view'   => (string) get_permalink( $pid ),
			'wpml'   => $real, // '' without WPML
		);
	}
	return $out;
}
