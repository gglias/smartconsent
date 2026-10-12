<?php
/**
 * Settings: one option. Everything is forced into its expected shape both when saved and when read, so values that
 * reached the database some other way (WP-CLI, an import, another plugin) can't end up in a page as-is.
 */
defined( 'ABSPATH' ) || exit;

const SMC_REV_OPTION = 'smartconsent_rev'; // raised by "Ask everyone again": older choices no longer count

/** Read once per request; the copy is dropped whenever the option changes (a save, WP-CLI, another plugin). */
function smc_get_settings() {
	global $smc_settings_cache;
	if ( null === $smc_settings_cache ) {
		$saved              = get_option( SMC_OPTION, null );
		$smc_settings_cache = is_array( $saved ) ? smc_sanitize_settings( $saved ) : smc_defaults();
	}
	return $smc_settings_cache;
}

function smc_forget_settings() {
	$GLOBALS['smc_settings_cache'] = null;
}
add_action( 'update_option_' . SMC_OPTION, 'smc_forget_settings' );
add_action( 'add_option_' . SMC_OPTION, 'smc_forget_settings' );
add_action( 'delete_option_' . SMC_OPTION, 'smc_forget_settings' );

function smc_save_settings( $raw ) {
	$clean = smc_sanitize_settings( is_array( $raw ) ? $raw : array() );
	update_option( SMC_OPTION, $clean, true ); // autoloaded: every front-end page reads it
	smc_forget_settings();
	return $clean;
}

/** Consent revision: a choice saved under an older one is asked again. */
function smc_rev() {
	return max( 1, (int) get_option( SMC_REV_OPTION, 1 ) );
}

function smc_limit( $v, $max ) {
	$v = is_scalar( $v ) ? (string) $v : '';
	return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
}

/** Plain text: tags stripped, but a lone "<" (as in "< 1 year") survives. */
function smc_clean_text( $v, $multiline = false ) {
	$v = $multiline ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v );
	return str_replace( '&lt;', '<', $v );
}

/** A per-language text: only known language codes, strings only, trimmed and length-limited. */
function smc_clean_map( $map, $multiline = false, $max = 400 ) {
	$out = array();
	if ( ! is_array( $map ) ) {
		return $out;
	}
	foreach ( smc_text_langs() as $lang ) {
		$out[ $lang ] = smc_clean_text( smc_limit( smc_str( $map, $lang ), $max ), $multiline );
	}
	return $out;
}

/** Cookie names: letters, digits and _ - . with * as a wildcard ("_ga_*"). */
function smc_clean_cookie_name( $v ) {
	$v = trim( is_scalar( $v ) ? (string) $v : '' );
	// Names become keys of plain objects in the admin screen, so never __proto__, constructor and the like.
	if ( in_array( strtolower( $v ), array( '__proto__', 'constructor', 'prototype', 'hasownproperty', 'tostring', 'valueof' ), true ) ) {
		return '';
	}
	return preg_match( '/^[A-Za-z0-9_\-.*]{1,80}\z/', $v ) ? $v : '';
}

function smc_clean_id( $v, $max = 30 ) {
	$id = substr( sanitize_key( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
	// Never __proto__, constructor and the like: ids become keys of plain objects in the browser.
	return ( 0 === strpos( $id, '__' ) || in_array( $id, array( 'constructor', 'prototype', 'hasownproperty', 'tostring', 'valueof' ), true ) ) ? '' : $id;
}

function smc_clean_cat( $v, $fallback = 'necessary' ) {
	$v = is_scalar( $v ) ? (string) $v : '';
	return in_array( $v, SMC_CATS, true ) ? $v : $fallback;
}

function smc_sanitize_settings( $in ) {
	$in = is_array( $in ) ? $in : array();
	$d  = smc_defaults();

	// Texts: only known keys; a text left empty falls back to the default of that language.
	$texts = array();
	$tin   = isset( $in['texts'] ) && is_array( $in['texts'] ) ? $in['texts'] : array();
	foreach ( $d['texts'] as $key => $def ) {
		$map = smc_clean_map( isset( $tin[ $key ] ) ? $tin[ $key ] : $def, 'text' === $key, 'text' === $key ? 1200 : 300 );
		foreach ( $map as $lang => $v ) {
			if ( '' === $v && '' !== smc_str( $def, $lang ) ) {
				$map[ $lang ] = $def[ $lang ];
			}
		}
		$texts[ $key ] = $map;
	}

	$look = isset( $in['look'] ) && is_array( $in['look'] ) ? $in['look'] : array();
	$hex  = function ( $key ) use ( $look, $d ) {
		$c = sanitize_hex_color( trim( smc_str( $look, $key ) ) );
		return $c ? strtolower( $c ) : $d['look'][ $key ];
	};
	$pos  = smc_str( $look, 'position' );

	// Services: the tags keep an ID that must match its format; the found services only an on/off.
	$services = array();
	$sin      = isset( $in['services'] ) && is_array( $in['services'] ) ? $in['services'] : array();
	foreach ( smc_library() as $id => $svc ) {
		$row = isset( $sin[ $id ] ) && is_array( $sin[ $id ] ) ? $sin[ $id ] : array();
		if ( 'tag' === $svc['kind'] ) {
			$tid = strtoupper( trim( smc_str( $row, 'id' ) ) );
			if ( 'clarity' === $id ) {
				$tid = strtolower( $tid );
			}
			$tid              = preg_match( $svc['field']['pattern'], $tid ) ? $tid : '';
			$services[ $id ]  = array( 'on' => ( '' !== $tid && ! empty( $row['on'] ) ) ? 1 : 0, 'id' => $tid );
			if ( 'google' === $id ) {
				$services[ $id ]['mode'] = 'advanced' === smc_str( $row, 'mode' ) ? 'advanced' : 'basic';
				$services[ $id ]['cat']  = 'marketing' === smc_str( $row, 'cat' ) ? 'marketing' : 'statistics';
			}
		} elseif ( 'auto' === $svc['kind'] && isset( $sin[ $id ] ) ) {
			$services[ $id ] = array( 'on' => empty( $row['on'] ) ? 0 : 1 ); // stored only once someone switched it
		}
	}

	// Custom scripts. Their code is stored as written, so only people allowed to add raw HTML may change them:
	// anyone else keeps what was already saved.
	$scripts = array();
	if ( current_user_can( 'unfiltered_html' ) || ! did_action( 'init' ) ) {
		$src = isset( $in['scripts'] ) && is_array( $in['scripts'] ) ? $in['scripts'] : array();
	} else {
		$prev = get_option( SMC_OPTION, array() );
		$src  = isset( $prev['scripts'] ) && is_array( $prev['scripts'] ) ? $prev['scripts'] : array();
	}
	$seen = array();
	foreach ( $src as $s ) {
		if ( count( $scripts ) >= 20 || ! is_array( $s ) ) {
			continue;
		}
		$sid  = smc_clean_id( smc_str( $s, 'id' ), 20 );
		$code = trim( smc_limit( smc_str( $s, 'code' ), 20000 ) );
		if ( '' === $sid || isset( $seen[ $sid ] ) || '' === $code ) {
			continue;
		}
		$seen[ $sid ] = true;
		$scripts[]    = array(
			'id'    => $sid,
			'on'    => empty( $s['on'] ) ? 0 : 1,
			'name'  => smc_clean_text( smc_limit( smc_str( $s, 'name' ), 80 ) ),
			'cat'   => 'necessary' === smc_clean_cat( smc_str( $s, 'cat' ), 'statistics' ) ? 'statistics' : smc_clean_cat( smc_str( $s, 'cat' ), 'statistics' ), // a script never runs without consent
			'where' => 'head' === smc_str( $s, 'where' ) ? 'head' : 'footer',
			'code'  => $code,
		);
	}

	// Cookies added by hand, for the cookie table.
	$cookies = array();
	$seen    = array();
	foreach ( isset( $in['cookies'] ) && is_array( $in['cookies'] ) ? $in['cookies'] : array() as $c ) {
		if ( count( $cookies ) >= 60 || ! is_array( $c ) ) {
			continue;
		}
		$name = smc_clean_cookie_name( smc_str( $c, 'name' ) );
		if ( '' === $name || isset( $seen[ $name ] ) ) {
			continue;
		}
		$seen[ $name ] = true;
		$cookies[]     = array(
			'name'     => $name,
			'provider' => smc_clean_text( smc_limit( smc_str( $c, 'provider' ), 80 ) ),
			'cat'      => smc_clean_cat( smc_str( $c, 'cat' ) ),
			'purpose'  => smc_clean_map( isset( $c['purpose'] ) ? $c['purpose'] : array(), false, 300 ),
			'duration' => smc_clean_map( isset( $c['duration'] ) ? $c['duration'] : array(), false, 80 ),
		);
	}

	// Changed texts of the library's cookies (only for names the library knows).
	$known = array();
	foreach ( smc_library() as $svc ) {
		foreach ( $svc['cookies'] as $c ) {
			$known[ $c['name'] ] = true;
		}
	}
	$cookie_text = array();
	foreach ( isset( $in['cookie_text'] ) && is_array( $in['cookie_text'] ) ? $in['cookie_text'] : array() as $name => $c ) {
		$name = smc_clean_cookie_name( $name );
		if ( '' === $name || ! isset( $known[ $name ] ) || ! is_array( $c ) ) {
			continue;
		}
		$row = array(
			'purpose'  => smc_clean_map( isset( $c['purpose'] ) ? $c['purpose'] : array(), false, 300 ),
			'duration' => smc_clean_map( isset( $c['duration'] ) ? $c['duration'] : array(), false, 80 ),
		);
		if ( array_filter( $row['purpose'], 'strlen' ) || array_filter( $row['duration'], 'strlen' ) ) {
			$cookie_text[ $name ] = $row;
		}
	}

	$pages = array();
	$pin   = isset( $in['policy']['pages'] ) && is_array( $in['policy']['pages'] ) ? $in['policy']['pages'] : array();
	foreach ( smc_text_langs() as $lang ) {
		$pid = absint( smc_str( $pin, $lang ) );
		if ( $pid && 'page' === get_post_type( $pid ) ) {
			$pages[ $lang ] = $pid;
		}
	}

	$c         = isset( $in['consent'] ) && is_array( $in['consent'] ) ? $in['consent'] : array();
	$flag      = function ( $key ) use ( $c, $d ) {
		return isset( $c[ $key ] ) ? ( empty( $c[ $key ] ) ? 0 : 1 ) : $d['consent'][ $key ];
	};
	$months    = (int) smc_str( $c, 'months' );
	$retention = (int) smc_str( $c, 'retention' );

	return array(
		'enabled'     => empty( $in['enabled'] ) ? 0 : 1,
		'texts'       => $texts,
		'look'        => array(
			'position'    => in_array( $pos, array( 'left', 'right', 'center' ), true ) ? $pos : 'left',
			'fill'        => $hex( 'fill' ),
			'fill_text'   => $hex( 'fill_text' ),
			'line'        => $hex( 'line' ),
			'bg'          => $hex( 'bg' ),
			'text'        => $hex( 'text' ),
			'equal'       => isset( $look['equal'] ) ? ( empty( $look['equal'] ) ? 0 : 1 ) : $d['look']['equal'],
			'hide_others' => isset( $look['hide_others'] ) ? ( empty( $look['hide_others'] ) ? 0 : 1 ) : 1,
		),
		'services'    => $services,
		'scripts'     => $scripts,
		'cookies'     => $cookies,
		'cookie_text' => $cookie_text,
		'policy'      => array( 'pages' => $pages ),
		'consent'     => array(
			'months'    => in_array( $months, array( 6, 12 ), true ) ? $months : 12,
			'retention' => in_array( $retention, array( 6, 12, 24 ), true ) ? $retention : 12,
			'gpc'       => $flag( 'gpc' ),
			'purge'     => $flag( 'purge' ),
			'log'       => $flag( 'log' ),
			'autoblock' => $flag( 'autoblock' ),
		),
	);
}

/* ---------- What is in use ---------- */

/**
 * Services in use, with their category: id => [ cat, id (tags), mode (Google) ].
 * Core ones always; found ones unless switched off; tags when switched on with an ID.
 */
function smc_active_services( $s = null ) {
	$s   = $s ? $s : smc_get_settings();
	$out = array();
	foreach ( smc_library() as $id => $svc ) {
		$row = isset( $s['services'][ $id ] ) ? $s['services'][ $id ] : null;
		if ( 'core' === $svc['kind'] ) {
			$out[ $id ] = array( 'cat' => $svc['cat'] );
		} elseif ( 'auto' === $svc['kind'] ) {
			// Found ones count until switched off; one that isn't found (e.g. Cloudflare not seen yet) once switched on.
			if ( $row ? ! empty( $row['on'] ) : smc_detected( $id ) ) {
				$out[ $id ] = array( 'cat' => $svc['cat'] );
			}
		} elseif ( $row && ! empty( $row['on'] ) && '' !== $row['id'] ) {
			$out[ $id ] = array( 'cat' => isset( $row['cat'] ) ? $row['cat'] : $svc['cat'], 'id' => $row['id'] );
			if ( isset( $row['mode'] ) ) {
				$out[ $id ]['mode'] = $row['mode'];
			}
		}
	}
	return $out;
}

/** How long SmartBanner keeps its "closed" cookie, as text, read from its own settings. */
function smc_smartbanner_duration() {
	$sb = get_option( 'smartbanner_settings', array() );
	$h  = is_array( $sb ) && isset( $sb['cookie_hours'] ) ? (int) $sb['cookie_hours'] : 720;
	if ( $h <= 0 ) {
		return smc_dur( 'session' );
	}
	if ( $h < 24 ) {
		return smc_t( $h . ( 1 === $h ? ' ώρα' : ' ώρες' ), $h . ( 1 === $h ? ' hour' : ' hours' ) );
	}
	$days = (int) round( $h / 24 );
	return smc_t( $days . ( 1 === $days ? ' ημέρα' : ' ημέρες' ), $days . ( 1 === $days ? ' day' : ' days' ) );
}

/**
 * Every cookie of the table, library and hand-added, with the texts as changed in the admin:
 * [ name, provider, cat, purpose{}, duration{}, source (service id or 'custom') ].
 */
function smc_cookie_list( $s = null ) {
	$s    = $s ? $s : smc_get_settings();
	$rows = array();
	$seen = array();
	foreach ( smc_active_services( $s ) as $id => $a ) {
		$svc = smc_lib( $id );
		foreach ( $svc['cookies'] as $c ) {
			$dur = $c['duration'];
			if ( SMC_COOKIE === $c['name'] ) {
				$m   = (int) $s['consent']['months'];
				$dur = smc_t( $m . ' μήνες', $m . ' months' );
			} elseif ( 'smartbanner_closed_*' === $c['name'] ) {
				$dur = smc_smartbanner_duration();
			}
			$over = isset( $s['cookie_text'][ $c['name'] ] ) ? $s['cookie_text'][ $c['name'] ] : null;
			$pur  = $c['purpose'];
			if ( $over ) {
				foreach ( $over['purpose'] as $l => $v ) {
					if ( '' !== $v ) {
						$pur[ $l ] = $v;
					}
				}
				foreach ( $over['duration'] as $l => $v ) {
					if ( '' !== $v ) {
						$dur[ $l ] = $v;
					}
				}
			}
			$seen[ $c['name'] ] = true;
			$rows[]             = array(
				'name'     => $c['name'],
				'provider' => $c['provider'],
				'cat'      => $a['cat'],
				'purpose'  => $pur,
				'duration' => $dur,
				'source'   => $id,
				'edited'   => $over ? 1 : 0,
			);
		}
	}
	foreach ( $s['cookies'] as $c ) {
		if ( isset( $seen[ $c['name'] ] ) ) {
			continue;
		}
		$rows[] = array_merge( $c, array( 'source' => 'custom', 'edited' => 0 ) );
	}
	usort( $rows, function ( $a, $b ) {
		$ca = array_search( $a['cat'], SMC_CATS, true );
		$cb = array_search( $b['cat'], SMC_CATS, true );
		return $ca === $cb ? 0 : ( $ca < $cb ? -1 : 1 );
	} );
	return $rows;
}

/** Categories the visitor gets to choose: those that something actually uses. Necessary is always first. */
function smc_used_cats( $s = null ) {
	$s    = $s ? $s : smc_get_settings();
	$used = array( 'necessary' => true );
	foreach ( smc_active_services( $s ) as $a ) {
		$used[ $a['cat'] ] = true;
	}
	foreach ( $s['scripts'] as $sc ) {
		if ( $sc['on'] ) {
			$used[ $sc['cat'] ] = true;
		}
	}
	foreach ( $s['cookies'] as $c ) {
		$used[ $c['cat'] ] = true;
	}
	return array_values( array_filter( SMC_CATS, function ( $c ) use ( $used ) {
		return isset( $used[ $c ] );
	} ) );
}

/** Cookie policy URL for a language: the page chosen or created in the settings (published only). */
function smc_policy_url( $lang ) {
	$s   = smc_get_settings();
	$pid = isset( $s['policy']['pages'][ $lang ] ) ? (int) $s['policy']['pages'][ $lang ] : 0;
	if ( ! $pid ) {
		$first = $s['policy']['pages'] ? reset( $s['policy']['pages'] ) : 0;
		$pid   = $first ? (int) apply_filters( 'wpml_object_id', (int) $first, 'page', true, $lang ) : 0;
	}
	return ( $pid && 'publish' === get_post_status( $pid ) ) ? (string) get_permalink( $pid ) : '';
}

/** Privacy policy URL of WordPress (Settings → Privacy), in a language through WPML. */
function smc_privacy_url( $lang ) {
	$pid = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $pid ) {
		$pid = (int) apply_filters( 'wpml_object_id', $pid, 'page', true, $lang );
	}
	return ( $pid && 'publish' === get_post_status( $pid ) ) ? (string) get_permalink( $pid ) : '';
}
