<?php
/**
 * Blocker: tags that reach the page without going through SmartConsent (Site Kit, a theme's "header code" box,
 * another plugin) are held back until consent. Each <script> that loads or starts a known tag gets
 * type="text/plain", which browsers neither download nor run; core.js turns it back into a real script after
 * consent for its category. <noscript> fallbacks of those tags are removed: without JavaScript nobody can consent.
 */
defined( 'ABSPATH' ) || exit;

/** Known tags: [ service id, category, regex ] for script src/content, from the library (and the category set here). */
function smc_block_rules() {
	$active = smc_active_services();
	$rules  = array();
	foreach ( smc_library() as $id => $svc ) {
		if ( empty( $svc['block'] ) ) {
			continue;
		}
		$cat     = isset( $active[ $id ] ) ? $active[ $id ]['cat'] : $svc['cat'];
		$rules[] = array( $id, $cat, '~' . implode( '|', $svc['block'] ) . '~i' );
	}
	return $rules;
}

/** Embeds: [ service id, category, regex ] for <iframe src>, unless the service was switched off in the admin. */
function smc_embed_rules() {
	$s     = smc_get_settings();
	$rules = array();
	foreach ( smc_library() as $id => $svc ) {
		if ( empty( $svc['embed'] ) || ( isset( $s['services'][ $id ] ) && empty( $s['services'][ $id ]['on'] ) ) ) {
			continue;
		}
		$rules[] = array( $id, $svc['cat'], '~^(?:https?:)?//(?:' . implode( '|', $svc['embed'] ) . ')~i' );
	}
	return $rules;
}

/** The rule a script matches, or null. $attrs is the raw attribute string, $code the inline content. */
function smc_block_match( $attrs, $code, array $rules ) {
	$src = preg_match( '~\ssrc\s*=\s*["\']?([^"\'\s>]+)~i', ' ' . $attrs, $m ) ? $m[1] : '';
	foreach ( $rules as $r ) {
		if ( ( '' !== $src && preg_match( $r[2], $src ) ) || ( '' === $src && '' !== trim( $code ) && preg_match( $r[2], $code ) ) ) {
			return $r;
		}
	}
	return null;
}

/** Is this tag one of SmartConsent's own (it carries this page's mark)? */
function smc_is_ours( $attrs ) {
	return (bool) preg_match( '~\sdata-smc-(?:skip|n)\s*=\s*["\']?' . preg_quote( smc_marker(), '~' ) . '\b~i', ' ' . $attrs );
}

/** A regex that gave up (PCRE limits) is remembered, so the admin overview can say the page wasn't checked. */
function smc_block_failed( $where ) {
	set_transient( 'smc_block_error', array( 'time' => time(), 'where' => $where, 'error' => function_exists( 'preg_last_error_msg' ) ? preg_last_error_msg() : (string) preg_last_error() ), DAY_IN_SECONDS );
}

/** Rewrites the page. Public for the tests. */
function smc_block_html( $html ) {
	if ( ! is_string( $html ) || ( false === stripos( $html, '<script' ) && false === stripos( $html, '<iframe' ) ) ) {
		return $html;
	}
	// Pages with very large inline scripts or JSON can exceed the default backtracking limit.
	$limit = ini_get( 'pcre.backtrack_limit' );
	if ( (int) $limit < 5000000 ) {
		ini_set( 'pcre.backtrack_limit', '5000000' ); // phpcs:ignore WordPress.PHP.IniSet
	}
	$html  = smc_block_iframes( $html );
	$rules = smc_block_rules();
	$mark  = smc_marker();
	$out   = ! $rules ? $html : preg_replace_callback( '~<script\b([^>]*)>(.*?)</script\s*>~is', function ( $m ) use ( $rules, $mark ) {
		$attrs = $m[1];
		// Only JavaScript: JSON-LD, templates, and anything already held back (text/plain) are left alone.
		$type = preg_match( '~\stype\s*=\s*["\']?([^"\'\s>]+)~i', ' ' . $attrs, $t ) ? strtolower( $t[1] ) : '';
		if ( smc_is_ours( $attrs ) || ( '' !== $type && ! in_array( $type, array( 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' ), true ) ) ) {
			return $m[0];
		}
		$rule = smc_block_match( $attrs, $m[2], $rules );
		if ( ! $rule ) {
			return $m[0];
		}
		$attrs = preg_replace( '~\stype\s*=\s*(["\'])[^"\']*\1|\stype\s*=\s*[^\s>]+~i', '', $attrs );
		$attrs = preg_replace( '~\sdata-smc-[a-z]+(?:\s*=\s*(["\'])[^"\']*\1|\s*=\s*[^\s>]+)?~i', '', $attrs ); // no borrowed marks
		$keep  = ( '' !== $type && 'text/javascript' !== $type ) ? ' data-smc-type="' . esc_attr( $type ) . '"' : '';
		return '<script type="text/plain" data-smc-cat="' . esc_attr( $rule[1] ) . '" data-smc-blocked="' . esc_attr( $rule[0] ) . '" data-smc-n="' . $mark . '"' . $keep . $attrs . '>' . $m[2] . '</script>';
	}, $html );
	if ( null === $out ) {
		smc_block_failed( 'script' );
		$out = $html; // unchanged rather than empty; the overview reports it
	}
	// <noscript> fallbacks of known tags (GTM's iframe, Meta's pixel image).
	$out2 = preg_replace( '~<noscript\b[^>]*>(?:(?!</noscript>).)*?(googletagmanager\.com/ns\.html|facebook\.com/tr\?)(?:(?!</noscript>).)*?</noscript\s*>~is', '', $out );
	if ( null === $out2 ) {
		smc_block_failed( 'noscript' );
	}
	if ( (int) $limit < 5000000 ) {
		ini_set( 'pcre.backtrack_limit', $limit ); // phpcs:ignore WordPress.PHP.IniSet
	}
	return null === $out2 ? $out : $out2;
}

/**
 * YouTube, Vimeo and Google Maps <iframe>s: the address moves from src to data-smc-src, so nothing loads.
 * front.js shows a placeholder in their place; core.js puts the address back after consent.
 */
function smc_block_iframes( $html ) {
	if ( false === stripos( $html, '<iframe' ) ) {
		return $html;
	}
	$rules = smc_embed_rules();
	if ( ! $rules ) {
		return $html;
	}
	$found = array();
	$mark  = smc_marker();
	$out   = preg_replace_callback( '~<iframe\b([^>]*)>~i', function ( $m ) use ( $rules, $mark, &$found ) {
		$attrs = $m[1];
		if ( smc_is_ours( $attrs ) || ! preg_match( '~\ssrc\s*=\s*(["\'])(.*?)\1~is', ' ' . $attrs, $src ) ) {
			return $m[0];
		}
		$url = html_entity_decode( trim( $src[2] ), ENT_QUOTES );
		foreach ( $rules as $r ) {
			if ( preg_match( $r[2], $url ) ) {
				$found[ $r[0] ] = true;
				$attrs          = str_replace( substr( $src[0], 1 ), '', $attrs ); // drop src="…"
				$attrs          = preg_replace( '~\sdata-smc-[a-z]+(?:\s*=\s*(["\'])[^"\']*\1|\s*=\s*[^\s>]+)?~i', '', $attrs );
				return '<iframe data-smc-src="' . esc_attr( $url ) . '" data-smc-cat="' . esc_attr( $r[1] ) . '" data-smc-embed="' . esc_attr( $r[0] ) . '" data-smc-n="' . $mark . '"' . $attrs . '>';
			}
		}
		return $m[0];
	}, $html );
	if ( null === $out ) {
		smc_block_failed( 'iframe' );
		return $html;
	}
	if ( $found ) {
		smc_embed_mark( array_keys( $found ) );
	}
	return $out;
}

/*
 * The page is rewritten as a whole: if another plugin flushes the output early, the pieces are kept and handled
 * together at the end, so a tag split between two pieces is still found.
 */
function smc_block_buffer( $chunk, $phase ) {
	static $held = '';
	if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {
		$held = ''; // ob_clean(): what was held is thrown away too
	}
	if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) && ! ( $phase & PHP_OUTPUT_HANDLER_END ) ) {
		$held .= $chunk;
		return '';
	}
	$page = $held . $chunk;
	$held = '';
	return smc_block_html( $page );
}

add_action( 'template_redirect', function () {
	if ( ! smc_front_active() || ! smc_get_settings()['consent']['autoblock'] ) {
		return;
	}
	ob_start( 'smc_block_buffer' );
}, 1 );
