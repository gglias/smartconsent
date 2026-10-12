<?php
/**
 * Admin: one page, built by assets/admin.js from the data printed below.
 * Every AJAX action checks the nonce and manage_options.
 */
defined( 'ABSPATH' ) || exit;

require_once SMC_DIR . 'includes/admin-i18n.php';

const SMC_PAGE_HOOK = 'toplevel_page_smartconsent';
const SMC_PER_PAGE  = 20;

/* ---------- Screen language (per user, independent of the WordPress language) ---------- */

function smc_ui_lang_pref() {
	$p = get_user_meta( get_current_user_id(), 'smc_ui_lang', true );
	return in_array( $p, array( 'en', 'el' ), true ) ? $p : '';
}

function smc_ui_lang() {
	$p = smc_ui_lang_pref();
	return $p ? $p : ( 0 === strpos( get_user_locale(), 'el' ) ? 'el' : 'en' );
}

add_action( 'init', function () {
	$pref = smc_ui_lang_pref();
	if ( ! $pref ) {
		return;
	}
	unload_textdomain( 'smartconsent' ); // not reloadable, so English really stays English
	if ( 'el' === $pref ) {
		load_textdomain( 'smartconsent', SMC_DIR . 'languages/smartconsent-el.mo' );
	}
}, 20 );

/* ---------- Menu ---------- */

/** The Smartup "S" emblem, filled black so WordPress can recolour it like the other menu icons. */
function smc_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-3 -1 31 33"><g fill="black"><rect x="6.26" width="18.74" height="6.21"/><rect y="6.21" width="6.26" height="6.21"/><rect x="6.26" y="12.37" width="12.48" height="6.21"/><rect x="18.74" y="18.58" width="6.26" height="6.21"/><rect y="24.79" width="18.74" height="6.21"/></g></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

add_action( 'admin_menu', function () {
	add_menu_page( 'SmartConsent', 'SmartConsent', 'manage_options', 'smartconsent', 'smc_render_admin_page', smc_menu_icon(), 59 );
} );

add_filter( 'plugin_action_links_' . plugin_basename( SMC_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smartconsent' ) ) . '">' . esc_html__( 'Settings', 'smartconsent' ) . '</a>' );
	return $links;
} );

add_filter( 'admin_body_class', function ( $classes ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return ( $screen && SMC_PAGE_HOOK === $screen->id ) ? $classes . ' smc-page' : $classes;
} );

function smc_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap smc-wrap"><h1 class="screen-reader-text">SmartConsent</h1><div id="smc-admin" class="smc-admin"><noscript>' . esc_html__( 'SmartConsent needs JavaScript.', 'smartconsent' ) . '</noscript></div></div>';
}

/* ---------- Data for the screen ---------- */

/** The library, as the screen shows it (texts in the screen language). */
function smc_library_for_ui() {
	$ui  = smc_ui_lang();
	$out = array();
	foreach ( smc_library() as $id => $svc ) {
		$out[] = array(
			'id'          => $id,
			'name'        => $svc['name'],
			'provider'    => $svc['provider'],
			'kind'        => $svc['kind'],
			'cat'         => $svc['cat'],
			'desc'        => smc_tr( $svc['desc'], $ui ),
			'detected'    => 'auto' === $svc['kind'] ? smc_detected( $id ) : false,
			'placeholder' => isset( $svc['field'] ) ? $svc['field']['placeholder'] : '',
			'pattern'     => isset( $svc['field'] ) ? trim( $svc['field']['pattern'], '/' ) : '',
			'cookies'     => wp_list_pluck( $svc['cookies'], 'name' ),
		);
	}
	return $out;
}

function smc_pages_list() {
	$out   = array();
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
	foreach ( $pages as $p ) {
		$out[] = array( 'id' => $p->ID, 'title' => ( '' !== $p->post_title ? $p->post_title : '#' . $p->ID ) . ( 'draft' === $p->post_status ? ' (' . __( 'draft', 'smartconsent' ) . ')' : '' ) );
	}
	return $out;
}

/** What changes with the saved settings: returned by every save, so the screen stays in step. */
function smc_derived( $s ) {
	$privacy = array();
	$policy  = array();
	foreach ( smc_text_langs() as $lang ) {
		$privacy[ $lang ] = smc_privacy_url( $lang );
		$policy[ $lang ]  = smc_policy_url( $lang );
	}
	return array(
		'cookieList' => smc_cookie_list( $s ),
		'usedCats'   => smc_used_cats( $s ),
		'policy'     => smc_policy_state(),
		'policyOk'   => smc_policy_wpml_ok(),
		'policyUrls' => $policy,
		'privacy'    => $privacy,
	);
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( SMC_PAGE_HOOK !== $hook ) {
		return;
	}
	$s     = smc_get_settings();
	$langs = array();
	$names = array( 'el' => 'Ελληνικά', 'en' => 'English' );
	$all   = smc_languages();
	foreach ( smc_text_langs() as $code ) {
		$langs[] = array( 'code' => $code, 'name' => isset( $all[ $code ] ) ? $all[ $code ]['name'] : ( isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ) ) );
	}

	wp_enqueue_style( 'smartconsent-admin', SMC_URL . 'assets/admin.css', array(), SMC_VERSION );
	wp_enqueue_script( 'smartconsent-admin', SMC_URL . 'assets/admin.js', array(), SMC_VERSION, true );

	$boot = array_merge( array(
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		'postUrl'     => admin_url( 'admin-post.php' ),
		'nonce'       => wp_create_nonce( 'smc_admin' ),
		'uiLang'      => smc_ui_lang(),
		'langs'       => $langs,
		'siteLang'    => smc_default_lang(),
		'wpml'        => defined( 'ICL_SITEPRESS_VERSION' ),
		'settings'    => $s,
		'defaults'    => array( 'texts' => smc_default_texts(), 'look' => smc_defaults()['look'] ),
		'library'     => smc_library_for_ui(),
		'cats'        => SMC_CATS,
		'pages'       => smc_pages_list(),
		'privacyEdit' => admin_url( 'options-privacy.php' ),
		'canScripts'  => current_user_can( 'unfiltered_html' ),
		'rev'         => smc_rev(),
		'policyLangs' => smc_policy_langs(),
		'blockError'  => get_transient( 'smc_block_error' ),
		'scan'        => get_option( 'smartconsent_scan', null ),
		'homeUrl'     => home_url( '/' ),
		'previewUrl'  => add_query_arg( 'smartconsent_preview', '1', home_url( '/' ) ),
		'perPage'     => SMC_PER_PAGE,
		'user'        => wp_get_current_user()->display_name,
		'version'     => SMC_VERSION,
		'i18n'        => smc_admin_i18n(),
	), smc_derived( $s ) );
	wp_add_inline_script( 'smartconsent-admin', 'var smcAdmin = ' . smc_json( $boot ) . ';', 'before' );
} );

/* ---------- AJAX ---------- */

function smc_ajax_guard() {
	check_ajax_referer( 'smc_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'smartconsent' ) ), 403 );
	}
}

function smc_post( $key ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
}

add_action( 'wp_ajax_smc_save', function () {
	smc_ajax_guard();
	$raw = json_decode( (string) smc_post( 'settings' ), true );
	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not save. Please try again.', 'smartconsent' ) ), 400 );
	}
	$clean  = smc_save_settings( $raw );
	$caches = smc_purge_page_caches(); // cached pages carry the banner's settings
	wp_send_json_success( array_merge( array( 'settings' => $clean, 'caches' => $caches ), smc_derived( $clean ) ) );
} );

/** The main switch: saved at once, on its own. */
add_action( 'wp_ajax_smc_enable', function () {
	smc_ajax_guard();
	$s            = smc_get_settings();
	$s['enabled'] = '1' === smc_post( 'on' ) ? 1 : 0;
	$clean        = smc_save_settings( $s );
	wp_send_json_success( array( 'enabled' => $clean['enabled'], 'caches' => smc_purge_page_caches() ) );
} );

add_action( 'wp_ajax_smc_stats', function () {
	smc_ajax_guard();
	wp_send_json_success( smc_log_stats( 30 ) );
} );

function smc_log_args( $src ) {
	$action = smc_str( $src, 'action_f' );
	$q      = strtolower( preg_replace( '/[^a-fA-F0-9]/', '', smc_str( $src, 'q' ) ) );
	return array(
		'action' => in_array( $action, SMC_ACTIONS, true ) ? $action : '',
		'q'      => substr( $q, 0, 16 ),
	);
}

add_action( 'wp_ajax_smc_log', function () {
	smc_ajax_guard();
	$args = smc_log_args( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$page = max( 1, absint( smc_post( 'page' ) ) );
	$res  = smc_log_list( $args, $page, SMC_PER_PAGE );
	$rows = array();
	foreach ( $res['rows'] as $r ) {
		$rows[] = array(
			'date' => get_date_from_gmt( $r['created_at'], 'd/m/Y H:i' ),
			'id'   => $r['consent_id'],
			'lang' => $r['lang'],
			'act'  => $r['action'],
			'cats' => '' !== $r['cats'] ? explode( ',', $r['cats'] ) : array(),
			'rev'  => (int) $r['rev'],
		);
	}
	wp_send_json_success( array( 'total' => $res['total'], 'rows' => $rows, 'counts' => smc_log_counts( $args['q'] ) ) );
} );

/** "Ask everyone again": a new revision, so every saved choice counts as none. */
add_action( 'wp_ajax_smc_reask', function () {
	smc_ajax_guard();
	$rev = smc_rev() + 1;
	update_option( SMC_REV_OPTION, $rev, true );
	wp_send_json_success( array( 'rev' => $rev, 'caches' => smc_purge_page_caches() ) );
} );

add_action( 'wp_ajax_smc_policy_create', function () {
	smc_ajax_guard();
	smc_create_policy_pages();
	$s = get_option( SMC_OPTION, array() );
	wp_send_json_success( array_merge( array( 'settings' => smc_sanitize_settings( $s ) ), smc_derived( smc_sanitize_settings( $s ) ) ) );
} );

add_action( 'wp_ajax_smc_check', function () {
	smc_ajax_guard();
	wp_send_json_success( smc_check_home() );
} );

add_action( 'wp_ajax_smc_scan_start', function () {
	smc_ajax_guard();
	wp_send_json_success( smc_scan_start() );
} );

add_action( 'wp_ajax_smc_scan_save', function () {
	smc_ajax_guard();
	delete_transient( 'smc_scan_' . get_current_user_id() ); // the token is spent
	$raw = json_decode( (string) smc_post( 'result' ), true );
	wp_send_json_success( smc_scan_store( $raw ) );
} );

add_action( 'wp_ajax_smc_ui_lang', function () {
	smc_ajax_guard();
	$lang = sanitize_key( smc_post( 'lang' ) );
	if ( ! in_array( $lang, array( 'en', 'el' ), true ) ) {
		wp_send_json_error( null, 400 );
	}
	update_user_meta( get_current_user_id(), 'smc_ui_lang', $lang );
	wp_send_json_success();
} );

/* ---------- CSV export of the consent log (a form post, so the browser downloads the file) ---------- */

/** Cells that start with = + - @ would run as formulas in Excel; a leading apostrophe keeps them text. */
function smc_csv_cell( $v ) {
	$v = (string) $v;
	return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
}

add_action( 'admin_post_smc_export', function () {
	check_admin_referer( 'smc_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'smartconsent' ), 403 );
	}
	global $wpdb;
	$where = smc_log_where( smc_log_args( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . smc_table() . " WHERE {$where} ORDER BY id DESC LIMIT 50000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$acts  = array( 'accept' => __( 'Accept all', 'smartconsent' ), 'reject' => __( 'Reject', 'smartconsent' ), 'custom' => __( 'Custom', 'smartconsent' ), 'gpc' => __( 'Global Privacy Control', 'smartconsent' ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="smartconsent-' . wp_date( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM: Excel then reads Greek correctly
	fputcsv( $out, array( __( 'Date', 'smartconsent' ), __( 'Consent ID', 'smartconsent' ), __( 'Language', 'smartconsent' ), __( 'Choice', 'smartconsent' ), __( 'Categories', 'smartconsent' ), __( 'Banner revision', 'smartconsent' ) ), ',', '"', '' );
	foreach ( (array) $rows as $r ) {
		fputcsv( $out, array_map( 'smc_csv_cell', array(
			get_date_from_gmt( $r['created_at'], 'Y-m-d H:i:s' ),
			$r['consent_id'],
			strtoupper( $r['lang'] ),
			isset( $acts[ $r['action'] ] ) ? $acts[ $r['action'] ] : $r['action'],
			$r['cats'],
			$r['rev'],
		) ), ',', '"', '' );
	}
	fclose( $out );
	exit;
} );

/* ---------- Page caches: emptied after saving, so pages never keep an older copy of the banner ---------- */

/**
 * Empties the page cache of the common caching plugins and hosts. Returns the names of the caches that were cleared.
 * (Same list as SmartBanner's and SmartInquiry's.)
 */
function smc_purge_page_caches() {
	$purgers = array(
		'WP Rocket'            => function () {
			if ( ! function_exists( 'rocket_clean_domain' ) ) {
				return false;
			}
			rocket_clean_domain();
			return true;
		},
		'LiteSpeed Cache'      => function () {
			if ( ! defined( 'LSCWP_V' ) ) {
				return false;
			}
			do_action( 'litespeed_purge_all' );
			return true;
		},
		'W3 Total Cache'       => function () {
			if ( ! function_exists( 'w3tc_flush_all' ) ) {
				return false;
			}
			w3tc_flush_all();
			return true;
		},
		'WP Super Cache'       => function () {
			if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
				return false;
			}
			wp_cache_clear_cache();
			return true;
		},
		'WP Fastest Cache'     => function () {
			if ( ! isset( $GLOBALS['wp_fastest_cache'] ) || ! method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				return false;
			}
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
			return true;
		},
		'WP-Optimize'          => function () {
			if ( ! function_exists( 'wpo_cache_flush' ) ) {
				return false;
			}
			wpo_cache_flush();
			return true;
		},
		'SiteGround Optimizer' => function () {
			if ( ! function_exists( 'sg_cachepress_purge_cache' ) ) {
				return false;
			}
			sg_cachepress_purge_cache();
			return true;
		},
		'Breeze'               => function () {
			if ( ! class_exists( 'Breeze_PurgeCache' ) ) {
				return false;
			}
			do_action( 'breeze_clear_all_cache' );
			return true;
		},
		'Hummingbird'          => function () {
			if ( ! class_exists( 'Hummingbird\\WP_Hummingbird' ) && ! class_exists( 'WP_Hummingbird' ) ) {
				return false;
			}
			do_action( 'wphb_clear_page_cache' );
			return true;
		},
		'Cache Enabler'        => function () {
			if ( ! class_exists( 'Cache_Enabler' ) ) {
				return false;
			}
			do_action( 'cache_enabler_clear_complete_cache' );
			return true;
		},
		'Comet Cache'          => function () {
			if ( ! class_exists( 'comet_cache' ) || ! method_exists( 'comet_cache', 'clear' ) ) {
				return false;
			}
			comet_cache::clear();
			return true;
		},
		'Swift Performance'    => function () {
			if ( ! class_exists( 'Swift_Performance_Cache' ) || ! method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
				return false;
			}
			Swift_Performance_Cache::clear_all_cache();
			return true;
		},
		'Autoptimize'          => function () {
			if ( ! class_exists( 'autoptimizeCache' ) || ! method_exists( 'autoptimizeCache', 'clearall' ) ) {
				return false;
			}
			autoptimizeCache::clearall();
			return true;
		},
		'Nginx Helper'         => function () {
			if ( ! defined( 'NGINX_HELPER_BASEPATH' ) ) {
				return false;
			}
			do_action( 'rt_nginx_helper_purge_all' );
			return true;
		},
		'WP Engine'            => function () {
			if ( ! class_exists( 'WpeCommon' ) ) {
				return false;
			}
			if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
				WpeCommon::purge_memcached();
			}
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
			return true;
		},
	);

	// On a network these two empty the cache of every site; only a network admin may do that.
	if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
		unset( $purgers['W3 Total Cache'], $purgers['WP Super Cache'] );
	}
	$done = array();
	foreach ( $purgers as $name => $purge ) {
		try {
			if ( $purge() ) {
				$done[] = $name;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Another plugin failing must not stop the save; that cache is simply not listed.
		}
	}
	return $done;
}
