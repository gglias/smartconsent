<?php
/**
 * Front end.
 *
 * In <head>, before anything else can run, a small inline script (assets/core.js with its settings) sets Google
 * Consent Mode to "denied", reads the visitor's saved choice and loads only the tags that choice allows.
 * assets/front.js (deferred) then draws the banner and saves new choices.
 */
defined( 'ABSPATH' ) || exit;

/** Inside a page builder's editing preview nothing runs: no banner over the editor, nothing held back. */
function smc_is_builder_preview() {
	if ( is_customize_preview() ) {
		return true; // WordPress itself only allows this to people who can customise
	}
	// Anyone can add ?elementor-preview to a link, so it counts only for someone who can edit, in Elementor's own preview.
	if ( ! isset( $_GET['elementor-preview'] ) || ! current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return false;
	}
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) ) {
		return (bool) \Elementor\Plugin::$instance->preview->is_preview_mode();
	}
	return true;
}

/**
 * Random mark of this page's own SmartConsent elements. The blocker leaves alone, and core.js brings to life,
 * only elements carrying it, so markup that other content puts on the page can neither opt out nor be run.
 */
function smc_marker() {
	static $m = null;
	if ( null === $m ) {
		$m = strtolower( wp_generate_password( 16, false, false ) );
	}
	return $m;
}

/** Is SmartConsent working on this request? */
function smc_front_active() {
	static $on = null;
	if ( null === $on ) {
		$s  = smc_get_settings();
		$on = ! is_admin() && ! wp_doing_ajax() && ! is_feed() && ! is_embed() && ! smc_is_builder_preview()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ( $s['enabled'] || smc_preview_mode() || smc_scan_mode() )
			&& apply_filters( 'smartconsent_load', true );
	}
	return $on;
}

/** ?smartconsent_preview=1 shows the banner to an administrator while it is still switched off for everyone. */
function smc_preview_mode() {
	return isset( $_GET['smartconsent_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * The admin's site scan loads pages with ?smc_scan=<token>&smc_mode=deny|all. Only for an administrator with a
 * token the scan itself created, so no link can make a visitor's browser load tags without their consent.
 */
function smc_scan_mode() {
	static $mode = null;
	if ( null !== $mode ) {
		return $mode;
	}
	$mode  = '';
	$token = isset( $_GET['smc_scan'] ) ? sanitize_key( wp_unslash( $_GET['smc_scan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$m     = isset( $_GET['smc_mode'] ) ? sanitize_key( wp_unslash( $_GET['smc_mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $token && in_array( $m, array( 'deny', 'all' ), true ) && current_user_can( 'manage_options' ) && hash_equals( (string) get_transient( 'smc_scan_' . get_current_user_id() ), $token ) ) {
		$mode = $m;
	}
	return $mode;
}

/** What the tag loader needs. */
function smc_core_config() {
	$s      = smc_get_settings();
	$tags   = array();
	$purge  = array();
	$active = smc_active_services( $s );
	foreach ( $active as $id => $a ) {
		$svc = smc_lib( $id );
		if ( 'tag' === $svc['kind'] ) {
			$tag = array( 'svc' => $id, 'id' => $a['id'], 'cat' => $a['cat'] );
			if ( isset( $a['mode'] ) ) {
				$tag['mode'] = $a['mode'];
			}
			$tags[] = $tag;
		}
		if ( ! empty( $svc['purge'] ) ) {
			$purge[ $a['cat'] ] = array_merge( isset( $purge[ $a['cat'] ] ) ? $purge[ $a['cat'] ] : array(), $svc['purge'] );
		}
	}
	// Scripts held back on the page by the blocker belong to library services even when those aren't set up here.
	foreach ( smc_library() as $id => $svc ) {
		if ( ! empty( $svc['purge'] ) && ! isset( $active[ $id ] ) ) {
			$purge[ $svc['cat'] ] = array_merge( isset( $purge[ $svc['cat'] ] ) ? $purge[ $svc['cat'] ] : array(), $svc['purge'] );
		}
	}
	return array(
		'cookie' => SMC_COOKIE,
		'rev'    => smc_rev(),
		'months' => (int) $s['consent']['months'],
		'gpc'    => (int) $s['consent']['gpc'],
		'cats'   => smc_used_cats( $s ),
		'tags'   => $tags,
		'purge'  => $s['consent']['purge'] ? (object) $purge : null,
		'scan'   => smc_scan_mode(),
		// Nothing optional on the site: nothing to ask, so no banner (the "Cookies" link still opens it).
		'show'   => ( smc_scan_mode() || count( smc_used_cats( $s ) ) < 2 ) ? 0 : 1,
		'ho'     => (int) $s['look']['hide_others'],
		'n'      => smc_marker(),
	);
}

/** What the banner needs: texts of this page's language only. */
function smc_front_config() {
	$s     = smc_get_settings();
	$lang  = smc_current_lang();
	$texts = array();
	foreach ( $s['texts'] as $k => $map ) {
		$texts[ $k ] = smc_tr( $map, $lang );
	}
	$cats    = smc_used_cats( $s );
	$cookies = array();
	foreach ( smc_cookie_list( $s ) as $c ) {
		$cookies[ $c['cat'] ][] = $c['name'];
	}
	$embeds = array();
	foreach ( smc_library() as $id => $svc ) {
		if ( ! empty( $svc['embed'] ) ) {
			$embeds[ $id ] = $svc['name'];
		}
	}
	return array(
		'lang'    => $lang,
		'rest'    => $s['consent']['log'] ? esc_url_raw( rest_url( 'smartconsent/v1/log' ) ) : '',
		'policy'  => esc_url_raw( smc_policy_url( $lang ) ),
		'css'     => esc_url_raw( add_query_arg( 'ver', SMC_VERSION, SMC_URL . 'assets/front.css' ) ),
		'texts'   => $texts,
		'cats'    => $cats,
		'cookies' => $cookies,
		'embeds'  => $embeds,
		'look'    => $s['look'],
		'preview' => smc_preview_mode() ? 1 : 0,
	);
}

/**
 * Rules that must hold before front.css arrives (it is only loaded when the banner or a placeholder is shown):
 * on phones, SmartBanner and SmartInquiry's button wait while the banner is open.
 */
function smc_critical_css() {
	if ( ! smc_get_settings()['look']['hide_others'] ) {
		return '';
	}
	return '@media(max-width:767px){html.smc-ho.smc-pending #smartbanner,html.smc-ho.smc-pending #smartinquiry-float,html.smc-ho.smc-open #smartbanner,html.smc-ho.smc-open #smartinquiry-float{display:none!important}}';
}

/** The cookie policy page shows the table at once, so its styles come with the page there. */
function smc_page_has_table() {
	$post = is_singular() ? get_queried_object() : null;
	return $post instanceof WP_Post && has_shortcode( $post->post_content, 'smartconsent_cookies' );
}

/** JSON safe inside a <script>: < > & ' " are hex-encoded, so no text written in the admin can close the tag. */
function smc_json( $data ) {
	return wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/*
 * Printed as early in <head> as possible (priority 1). It has to run before any Google tag, so it is inline and not
 * a file; data-smc-skip tells the blocker to leave it alone although it mentions googletagmanager.com.
 */
add_action( 'wp_head', function () {
	if ( ! smc_front_active() ) {
		return;
	}
	$core = (string) file_get_contents( SMC_DIR . 'assets/core.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	echo "<script data-smc-skip=\"" . smc_marker() . "\" data-no-optimize=\"1\" data-no-defer=\"1\" data-no-minify=\"1\" data-cfasync=\"false\" nowprocket>window.smartConsentCore=" . smc_json( smc_core_config() ) . ";\n" . $core . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	$css = smc_critical_css();
	if ( '' !== $css ) {
		echo '<style id="smartconsent-critical">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed text
	}
}, 1 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! smc_front_active() ) {
		return;
	}
	// Most page views are from visitors who already answered: no stylesheet for them. front.js loads it on demand.
	if ( smc_page_has_table() ) {
		wp_enqueue_style( 'smartconsent', SMC_URL . 'assets/front.css', array(), SMC_VERSION );
	}
	wp_enqueue_script( 'smartconsent', SMC_URL . 'assets/front.js', array(), SMC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script( 'smartconsent', 'var smartConsent = ' . smc_json( smc_front_config() ) . ';', 'before' );
} );

// The deferred banner script must not be delayed or merged by optimisation plugins ("delay JS" would hide the banner).
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( 'smartconsent' === $handle ) {
		$tag = str_replace( '<script ', '<script data-smc-skip="' . smc_marker() . '" data-no-optimize="1" data-no-defer="1" data-cfasync="false" data-pagespeed-no-defer nowprocket ', $tag );
	}
	return $tag;
}, 10, 2 );

/*
 * Scripts added in "Services & scripts" are printed inside a <template>: nothing in it runs or loads until
 * core.js takes it out, after consent for its category.
 */
function smc_print_scripts( $where ) {
	if ( ! smc_front_active() ) {
		return;
	}
	foreach ( smc_get_settings()['scripts'] as $sc ) {
		if ( ! $sc['on'] || $sc['where'] !== $where ) {
			continue;
		}
		$code = $sc['code'];
		if ( false === strpos( $code, '<' ) ) {
			$code = '<script>' . $code . '</script>'; // plain JavaScript pasted without its tag
		}
		// A stray </template> in the pasted code would end the template early and let the rest run before consent.
		$code = preg_replace( '~</(template)~i', '&lt;/$1', $code );
		echo '<template data-smc-cat="' . esc_attr( 'necessary' === $sc['cat'] ? 'statistics' : $sc['cat'] ) . '" data-smc-n="' . smc_marker() . '">' . $code . "</template>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- stored by users with unfiltered_html only
	}
}
add_action( 'wp_head', function () {
	smc_print_scripts( 'head' );
}, 20 );
add_action( 'wp_footer', function () {
	smc_print_scripts( 'footer' );
}, 20 );

// A page served while the banner is being tested must not be cached for everyone.
add_action( 'template_redirect', function () {
	if ( smc_preview_mode() || smc_scan_mode() ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}
}, 0 );
