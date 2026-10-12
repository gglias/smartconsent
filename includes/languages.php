<?php
/**
 * Languages. With WPML: its active languages, read only through its public hooks. Without it: the site language.
 * Every text the visitor sees is stored per language code ( [ 'el' => '…', 'en' => '…' ] ).
 */
defined( 'ABSPATH' ) || exit;

/** String value of a key, or '' when missing or not a scalar (arrays must never reach string functions). */
function smc_str( $arr, $key ) {
	return ( is_array( $arr ) && isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ) ? (string) $arr[ $key ] : '';
}

/** Two-letter code of a WordPress locale: el_GR / el -> el, en_US -> en. */
function smc_locale_code( $locale ) {
	$code = strtolower( substr( (string) $locale, 0, 2 ) );
	return preg_match( '/^[a-z]{2}$/', $code ) ? $code : 'en';
}

/**
 * Active languages, default first: code => [ code, name, default ].
 * Always at least one entry (the site language when WPML isn't there).
 */
function smc_languages() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$langs = array();
	$wpml  = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
	if ( is_array( $wpml ) && $wpml ) {
		$default = (string) apply_filters( 'wpml_default_language', null );
		foreach ( $wpml as $code => $l ) {
			$code = sanitize_key( (string) $code );
			if ( '' === $code || 'all' === $code || strlen( $code ) > 10 ) {
				continue;
			}
			$name           = smc_str( $l, 'native_name' );
			$langs[ $code ] = array(
				'code'    => $code,
				'name'    => '' !== $name ? $name : strtoupper( $code ),
				'default' => $code === $default,
			);
		}
		uasort( $langs, function ( $a, $b ) {
			return (int) $b['default'] - (int) $a['default'];
		} );
		if ( $langs && ! array_filter( wp_list_pluck( $langs, 'default' ) ) ) {
			$first                     = key( $langs );
			$langs[ $first ]['default'] = true;
		}
	}
	if ( ! $langs ) {
		$code  = smc_locale_code( get_locale() );
		$names = array( 'el' => 'Ελληνικά', 'en' => 'English' );
		$langs = array( $code => array( 'code' => $code, 'name' => isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ), 'default' => true ) );
	}
	$cache = $langs;
	return $cache;
}

function smc_lang_codes() {
	return array_keys( smc_languages() );
}

function smc_default_lang() {
	return key( smc_languages() );
}

/** Language of the current page. */
function smc_current_lang() {
	$langs = smc_languages();
	$cur   = sanitize_key( (string) apply_filters( 'wpml_current_language', null ) );
	return isset( $langs[ $cur ] ) ? $cur : smc_default_lang();
}

/** Language codes a per-language text may hold: the active ones plus Greek and English. */
function smc_text_langs() {
	return array_values( array_unique( array_merge( smc_lang_codes(), array( 'el', 'en' ) ) ) );
}

/** One language of a per-language text, falling back to the default language, then English, then anything. */
function smc_tr( $map, $lang ) {
	if ( ! is_array( $map ) ) {
		return is_scalar( $map ) ? (string) $map : '';
	}
	foreach ( array( $lang, smc_default_lang(), 'en', 'el' ) as $l ) {
		$v = smc_str( $map, $l );
		if ( '' !== $v ) {
			return $v;
		}
	}
	foreach ( $map as $v ) {
		if ( is_scalar( $v ) && '' !== (string) $v ) {
			return (string) $v;
		}
	}
	return '';
}

/** A text in both languages; the English defaults to the Greek. */
function smc_t( $el, $en = null ) {
	return array( 'el' => $el, 'en' => null === $en ? $el : $en );
}
