<?php
/**
 * The services SmartConsent knows: what each one stores in the visitor's browser (for the cookie table), how it is
 * recognised on the site, and, for the tags the plugin loads itself, the ID it needs and the scripts to hold back.
 *
 * kind: core  = always listed (WordPress, SmartConsent itself)
 *       auto  = listed when found on the site (WPML, Cloudflare, our other plugins); can be switched off
 *       tag   = loaded by SmartConsent after consent, from an ID entered in the admin
 */
defined( 'ABSPATH' ) || exit;

const SMC_CATS = array( 'necessary', 'preferences', 'statistics', 'marketing' );

function smc_cookie( $name, $purpose, $duration, $provider = '' ) {
	return array( 'name' => $name, 'purpose' => $purpose, 'duration' => $duration, 'provider' => $provider );
}

/** Duration texts used by several services. */
function smc_dur( $key ) {
	$d = array(
		'session' => smc_t( 'Όσο είναι ανοιχτός ο browser', 'Until the browser is closed' ),
		'30min'   => smc_t( '30 λεπτά', '30 minutes' ),
		'1day'    => smc_t( '1 ημέρα', '1 day' ),
		'3months' => smc_t( '3 μήνες', '3 months' ),
		'1year'   => smc_t( '1 χρόνος', '1 year' ),
		'2years'  => smc_t( '2 χρόνια', '2 years' ),
	);
	return $d[ $key ];
}

function smc_library() {
	static $lib = null;
	if ( null !== $lib ) {
		return $lib;
	}
	$lib = array(
		'smartconsent' => array(
			'name'     => 'SmartConsent',
			'provider' => '',
			'kind'     => 'core',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Αποθηκεύει την επιλογή του επισκέπτη για τα cookies.', 'Stores the visitor\'s cookie choice.' ),
			'cookies'  => array(
				smc_cookie( SMC_COOKIE, smc_t( 'Αποθηκεύει την επιλογή σας για τα cookies.', 'Stores your cookie choice.' ), null ),
			),
		),
		'wordpress'    => array(
			'name'     => 'WordPress',
			'provider' => '',
			'kind'     => 'core',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Cookies σύνδεσης, μόνο για όσους μπαίνουν στη διαχείριση του site.', 'Login cookies, only for people who sign in to manage the site.' ),
			'cookies'  => array(
				smc_cookie( 'wordpress_logged_in_*', smc_t( 'Κρατά συνδεδεμένους τους διαχειριστές του site.', 'Keeps the site\'s administrators signed in.' ), smc_t( 'Όσο είναι ανοιχτός ο browser ή 14 ημέρες', 'Until the browser is closed, or 14 days' ) ),
				smc_cookie( 'wordpress_sec_*', smc_t( 'Ασφαλής σύνδεση στη διαχείριση του site.', 'Secure sign-in to the site\'s admin area.' ), smc_t( 'Όσο είναι ανοιχτός ο browser ή 14 ημέρες', 'Until the browser is closed, or 14 days' ) ),
				smc_cookie( 'wordpress_test_cookie', smc_t( 'Ελέγχει αν ο browser δέχεται cookies, στη σελίδα σύνδεσης.', 'Checks that the browser accepts cookies, on the login page.' ), smc_dur( 'session' ) ),
				smc_cookie( 'wp-settings-*', smc_t( 'Θυμάται τις ρυθμίσεις οθόνης των διαχειριστών.', 'Remembers the administrators\' screen settings.' ), smc_dur( '1year' ) ),
			),
		),
		'wpml'         => array(
			'name'     => 'WPML',
			'provider' => '',
			'kind'     => 'auto',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Θυμάται τη γλώσσα του επισκέπτη.', 'Remembers the visitor\'s language.' ),
			'detect'   => function () {
				return defined( 'ICL_SITEPRESS_VERSION' );
			},
			'cookies'  => array(
				smc_cookie( 'wp-wpml_current_language', smc_t( 'Θυμάται τη γλώσσα του site που επιλέξατε.', 'Remembers the site language you chose.' ), smc_dur( '1day' ) ),
			),
		),
		'smartbanner'  => array(
			'name'     => 'SmartBanner',
			'provider' => '',
			'kind'     => 'auto',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Θυμάται ότι ο επισκέπτης έκλεισε την ανακοίνωση.', 'Remembers that the visitor closed the announcement.' ),
			'detect'   => function () {
				return defined( 'SMB_VERSION' );
			},
			'cookies'  => array(
				smc_cookie( 'smartbanner_closed_*', smc_t( 'Θυμάται ότι κλείσατε την ανακοίνωση, ώστε να μην ξαναεμφανιστεί.', 'Remembers that you closed the announcement, so it doesn\'t come back.' ), null ),
			),
		),
		'smartinquiry' => array(
			'name'     => 'SmartInquiry',
			'provider' => '',
			'kind'     => 'auto',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Δεν γράφει cookies. Τα στοιχεία της φόρμας καλύπτονται από την Πολιτική απορρήτου.', 'Stores no cookies. What people enter in the form is covered by the privacy policy.' ),
			'detect'   => function () {
				return defined( 'SMI_VERSION' );
			},
			'cookies'  => array(),
		),
		'cloudflare'   => array(
			'name'     => 'Cloudflare',
			'provider' => 'Cloudflare',
			'kind'     => 'auto',
			'cat'      => 'necessary',
			'desc'     => smc_t( 'Προστασία του site από bots και έλεγχος ασφαλείας (Turnstile).', 'Protects the site from bots and runs the security check (Turnstile).' ),
			'detect'   => function () {
				return (bool) get_option( 'smartconsent_cf_seen' );
			},
			'cookies'  => array(
				smc_cookie( '__cf_bm', smc_t( 'Ξεχωρίζει τους ανθρώπους από τα bots, για την προστασία του site.', 'Tells people and bots apart, to protect the site.' ), smc_dur( '30min' ), 'Cloudflare' ),
				smc_cookie( 'cf_clearance', smc_t( 'Επιβεβαιώνει ότι περάσατε τον έλεγχο ασφαλείας.', 'Confirms that you passed the security check.' ), smc_t( '30 λεπτά (προεπιλογή της Cloudflare)', '30 minutes (Cloudflare\'s default)' ), 'Cloudflare' ),
			),
		),
		/*
		 * Embeds: the blocker takes the src off their <iframe>s and front.js puts a placeholder in their place. They
		 * are listed (and Marketing appears in the banner) once one has been seen on a page of the site.
		 */
		'youtube'      => array(
			'name'     => 'YouTube',
			'provider' => 'Google',
			'kind'     => 'auto',
			'cat'      => 'marketing',
			'desc'     => smc_t( 'Ενσωματωμένα video. Φορτώνουν μόνο μετά από συγκατάθεση, και μέχρι τότε εμφανίζεται placeholder.', 'Embedded videos. They load only after consent, with a placeholder until then.' ),
			'detect'   => function () {
				return smc_embed_seen( 'youtube' );
			},
			'embed'    => array( '(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|v)/', 'youtu\.be/' ),
			'block'    => array( 'youtube\.com/(?:iframe_api|player_api)' ),
			'cookies'  => array(
				smc_cookie( 'YSC', smc_t( 'Μετρά τις προβολές των video του YouTube (γράφεται στο youtube.com).', 'Counts YouTube video views (stored on youtube.com).' ), smc_dur( 'session' ), 'Google (YouTube)' ),
				smc_cookie( 'VISITOR_INFO1_LIVE', smc_t( 'Εκτιμά την ταχύτητα σύνδεσης και θυμάται ρυθμίσεις του player (youtube.com).', 'Estimates bandwidth and remembers player settings (youtube.com).' ), smc_t( '6 μήνες', '6 months' ), 'Google (YouTube)' ),
				smc_cookie( 'VISITOR_PRIVACY_METADATA', smc_t( 'Κρατά τις επιλογές απορρήτου του επισκέπτη στο YouTube (youtube.com).', 'Keeps the visitor\'s privacy choices for YouTube (youtube.com).' ), smc_t( '6 μήνες', '6 months' ), 'Google (YouTube)' ),
			),
		),
		'vimeo'        => array(
			'name'     => 'Vimeo',
			'provider' => 'Vimeo',
			'kind'     => 'auto',
			'cat'      => 'marketing',
			'desc'     => smc_t( 'Ενσωματωμένα video. Φορτώνουν μόνο μετά από συγκατάθεση, και μέχρι τότε εμφανίζεται placeholder.', 'Embedded videos. They load only after consent, with a placeholder until then.' ),
			'detect'   => function () {
				return smc_embed_seen( 'vimeo' );
			},
			'embed'    => array( 'player\.vimeo\.com/video/' ),
			'cookies'  => array(
				smc_cookie( 'vuid', smc_t( 'Μετρά τις προβολές των video του Vimeo (γράφεται στο vimeo.com).', 'Counts Vimeo video views (stored on vimeo.com).' ), smc_dur( '2years' ), 'Vimeo' ),
			),
		),
		'gmaps'        => array(
			'name'     => 'Google Maps',
			'provider' => 'Google',
			'kind'     => 'auto',
			'cat'      => 'marketing',
			'desc'     => smc_t( 'Ενσωματωμένοι χάρτες. Φορτώνουν μόνο μετά από συγκατάθεση, και μέχρι τότε εμφανίζεται placeholder.', 'Embedded maps. They load only after consent, with a placeholder until then.' ),
			'detect'   => function () {
				return smc_embed_seen( 'gmaps' );
			},
			'embed'    => array( '(?:www\.)?google\.[a-z.]{2,6}/maps(?:/embed|\?|/)', 'maps\.google\.[a-z.]{2,6}/' ),
			'cookies'  => array(
				smc_cookie( 'NID', smc_t( 'Θυμάται προτιμήσεις σας στις υπηρεσίες της Google (γράφεται στο google.com).', 'Remembers your preferences for Google services (stored on google.com).' ), smc_t( '6 μήνες', '6 months' ), 'Google' ),
			),
		),
		'google'       => array(
			'name'     => 'Google Tag Manager / Google Analytics 4',
			'provider' => 'Google',
			'kind'     => 'tag',
			'cat'      => 'statistics',
			'desc'     => smc_t( 'Μέτρηση επισκεψιμότητας. Φορτώνει μόνο μετά από συγκατάθεση, με Google Consent Mode v2.', 'Traffic measurement. Loads only after consent, with Google Consent Mode v2.' ),
			'field'    => array( 'pattern' => '/^(GTM-[A-Z0-9]{4,12}|G-[A-Z0-9]{4,16})$/', 'placeholder' => 'GTM-XXXXXXX / G-XXXXXXXXXX' ),
			'cookies'  => array(
				smc_cookie( '_ga', smc_t( 'Ξεχωρίζει τους επισκέπτες, για να μετράμε ανώνυμα την επισκεψιμότητα.', 'Tells visitors apart, so we can measure traffic anonymously.' ), smc_dur( '2years' ), 'Google' ),
				smc_cookie( '_ga_*', smc_t( 'Κρατά την κατάσταση της επίσκεψης (σελίδες, διάρκεια).', 'Keeps the state of the visit (pages, duration).' ), smc_dur( '2years' ), 'Google' ),
			),
			'block'    => array( 'googletagmanager\.com/(gtag/js|gtm\.js)', 'google-analytics\.com/(analytics|ga)\.js', 'gtm\.start', "gtag\\(\\s*['\"]config['\"]", 'GoogleAnalyticsObject' ),
			'purge'    => array( '_ga', '_ga_', '_gid', '_gat' ),
		),
		'meta'         => array(
			'name'     => 'Meta Pixel',
			'provider' => 'Meta',
			'kind'     => 'tag',
			'cat'      => 'marketing',
			'desc'     => smc_t( 'Μέτρηση διαφημίσεων στο Facebook και στο Instagram.', 'Ad measurement on Facebook and Instagram.' ),
			'field'    => array( 'pattern' => '/^[0-9]{10,20}$/', 'placeholder' => '123456789012345' ),
			'cookies'  => array(
				smc_cookie( '_fbp', smc_t( 'Αναγνωρίζει τον browser σας για τις διαφημίσεις της Meta.', 'Recognises your browser for Meta\'s ads.' ), smc_dur( '3months' ), 'Meta' ),
				smc_cookie( 'fr', smc_t( 'Διαφημίσεις της Meta (γράφεται στο facebook.com).', 'Meta\'s ads (stored on facebook.com).' ), smc_dur( '3months' ), 'Meta' ),
			),
			'block'    => array( 'connect\.facebook\.net', 'fbq\\(\\s*[\'"]init' ),
			'purge'    => array( '_fbp', '_fbc' ),
		),
		'clarity'      => array(
			'name'     => 'Microsoft Clarity',
			'provider' => 'Microsoft',
			'kind'     => 'tag',
			'cat'      => 'statistics',
			'desc'     => smc_t( 'Heatmaps και καταγραφές επισκέψεων.', 'Heatmaps and session recordings.' ),
			'field'    => array( 'pattern' => '/^[a-z0-9]{6,20}$/', 'placeholder' => 'abcd1234ef' ),
			'cookies'  => array(
				smc_cookie( '_clck', smc_t( 'Ξεχωρίζει τους επισκέπτες στο Clarity.', 'Tells visitors apart in Clarity.' ), smc_dur( '1year' ), 'Microsoft' ),
				smc_cookie( '_clsk', smc_t( 'Ενώνει τις σελίδες μιας επίσκεψης σε μία καταγραφή.', 'Joins the pages of a visit into one recording.' ), smc_dur( '1day' ), 'Microsoft' ),
				smc_cookie( 'MUID', smc_t( 'Αναγνωριστικό των υπηρεσιών της Microsoft (γράφεται στο bing.com / clarity.ms).', 'Microsoft services identifier (stored on bing.com / clarity.ms).' ), smc_dur( '1year' ), 'Microsoft' ),
			),
			'block'    => array( 'clarity\.ms' ),
			'purge'    => array( '_clck', '_clsk' ),
		),
		'hotjar'       => array(
			'name'     => 'Hotjar',
			'provider' => 'Hotjar',
			'kind'     => 'tag',
			'cat'      => 'statistics',
			'desc'     => smc_t( 'Heatmaps και καταγραφές επισκέψεων.', 'Heatmaps and session recordings.' ),
			'field'    => array( 'pattern' => '/^[0-9]{5,12}$/', 'placeholder' => '1234567' ),
			'cookies'  => array(
				smc_cookie( '_hjSessionUser_*', smc_t( 'Ξεχωρίζει τους επισκέπτες στο Hotjar.', 'Tells visitors apart in Hotjar.' ), smc_dur( '1year' ), 'Hotjar' ),
				smc_cookie( '_hjSession_*', smc_t( 'Κρατά τα δεδομένα της τρέχουσας επίσκεψης.', 'Holds the data of the current visit.' ), smc_dur( '30min' ), 'Hotjar' ),
			),
			'block'    => array( 'static\.hotjar\.com', 'hotjar\.com/c/' ),
			'purge'    => array( '_hjSessionUser_', '_hjSession_', '_hjAbsoluteSessionInProgress' ),
		),
	);
	return $lib;
}

/** One service of the library, or null. */
function smc_lib( $id ) {
	$lib = smc_library();
	return isset( $lib[ $id ] ) ? $lib[ $id ] : null;
}

const SMC_EMBEDS_OPTION = 'smartconsent_embeds'; // embed services seen on the site: id => first time

function smc_embed_seen( $id ) {
	$seen = get_option( SMC_EMBEDS_OPTION, array() );
	return is_array( $seen ) && isset( $seen[ $id ] );
}

/** Remembers embeds the blocker found on a page (one write, the first time each is seen). */
function smc_embed_mark( array $ids ) {
	$seen = get_option( SMC_EMBEDS_OPTION, array() );
	$seen = is_array( $seen ) ? $seen : array();
	$new  = array_diff( $ids, array_keys( $seen ) );
	if ( ! $new ) {
		return;
	}
	foreach ( $new as $id ) {
		if ( smc_lib( $id ) ) {
			$seen[ $id ] = time();
		}
	}
	update_option( SMC_EMBEDS_OPTION, $seen, true );
	if ( function_exists( 'smc_forget_settings' ) ) {
		smc_forget_settings();
	}
}

/** Is an "auto" service present on this site? */
function smc_detected( $id ) {
	$svc = smc_lib( $id );
	return $svc && isset( $svc['detect'] ) && is_callable( $svc['detect'] ) && call_user_func( $svc['detect'] );
}

/*
 * Cloudflare can't be seen from PHP on every request, only when it happens to proxy one. The first time a request
 * arrives through it, that is remembered, so the cookie table lists its cookies from then on.
 */
add_action( 'init', function () {
	if ( isset( $_SERVER['HTTP_CF_RAY'] ) && ! get_option( 'smartconsent_cf_seen' ) && smc_from_cloudflare() ) {
		update_option( 'smartconsent_cf_seen', 1, true );
	}
} );
