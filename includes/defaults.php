<?php
/**
 * Defaults: every text the visitor sees (Greek and English), the look, the services and the consent rules.
 * A fresh install starts switched off, so nothing changes on the site until the banner is set up.
 */
defined( 'ABSPATH' ) || exit;

function smc_default_texts() {
	return array(
		'title'        => smc_t( 'Διαχείριση cookies', 'Manage cookie consent' ),
		// {purposes} becomes the purposes actually in use on the site ("for traffic statistics", …), so the text is
		// always true and "Reject" plainly means the optional cookies.
		'text'         => smc_t(
			'Χρησιμοποιούμε απαραίτητα cookies για να λειτουργεί το site. Με τη συγκατάθεσή σας χρησιμοποιούμε και προαιρετικά cookies {purposes}. Μπορείτε να τα αποδεχτείτε, να τα απορρίψετε ή να διαλέξετε στις Προτιμήσεις, και να αλλάξετε την επιλογή σας όποτε θέλετε από το link «Cookies» στο κάτω μέρος της σελίδας.',
			'We use necessary cookies to make this site work. With your consent, we also use optional cookies {purposes}. You can accept them, reject them or choose in Preferences, and change your choice at any time from the “Cookies” link at the bottom of the page.'
		),
		'purpose_preferences' => smc_t( 'για να θυμόμαστε τις προτιμήσεις σας', 'to remember your preferences' ),
		'purpose_statistics'  => smc_t( 'για στατιστικά επισκεψιμότητας', 'for traffic statistics' ),
		'purpose_marketing'   => smc_t( 'για marketing και για περιεχόμενο τρίτων, όπως video και χάρτες', 'for marketing and for third-party content such as videos and maps' ),
		'and'                 => smc_t( 'και', 'and' ),
		'policy'       => smc_t( 'Πολιτική cookies', 'Cookie policy' ),
		'prefs'        => smc_t( 'Προτιμήσεις', 'Preferences' ),
		'save'         => smc_t( 'Αποθήκευση επιλογών', 'Save choices' ),
		'reject'       => smc_t( 'Απόρριψη', 'Reject' ),
		'accept'       => smc_t( 'Αποδοχή όλων', 'Accept all' ),
		'always'       => smc_t( 'Πάντα ενεργά', 'Always active' ),
		'link'         => smc_t( 'Cookies', 'Cookies' ),
		'consent_id'   => smc_t( 'Κωδικός συγκατάθεσης', 'Consent ID' ),
		'cat_necessary'      => smc_t( 'Απαραίτητα', 'Necessary' ),
		'cat_necessary_d'    => smc_t( 'Χρειάζονται για να λειτουργεί το site: ασφάλεια, γλώσσα και η επιλογή σας για τα cookies.', 'Required for the site to work: security, language and your cookie choice.' ),
		'cat_preferences'    => smc_t( 'Προτιμήσεις', 'Preferences' ),
		'cat_preferences_d'  => smc_t( 'Θυμούνται επιλογές σας, για παράδειγμα τη γλώσσα ή την περιοχή.', 'Remember your choices, such as language or region.' ),
		'cat_statistics'     => smc_t( 'Στατιστικά', 'Statistics' ),
		'cat_statistics_d'   => smc_t( 'Μας δείχνουν πώς χρησιμοποιείται το site, ώστε να το βελτιώνουμε.', 'Show us how the site is used, so we can improve it.' ),
		'cat_marketing'      => smc_t( 'Marketing', 'Marketing' ),
		'cat_marketing_d'    => smc_t( 'Μετρούν τις διαφημίσεις μας και μπορεί να σας αναγνωρίζουν και σε άλλα sites.', 'Measure our ads and may recognise you on other sites.' ),
		'ph_title'     => smc_t( 'Περιεχόμενο από %s', 'Content from %s' ),
		'ph_text'      => smc_t( 'Φορτώνει από άλλη εταιρεία, που μπορεί να χρησιμοποιεί cookies Marketing. Για να το δείτε, χρειάζεται η συγκατάθεσή σας.', 'It loads from another company, which may use marketing cookies. To see it, we need your consent.' ),
		'ph_btn'       => smc_t( 'Αποδοχή και προβολή', 'Accept and show' ),
		'th_cookie'    => smc_t( 'Cookie', 'Cookie' ),
		'th_provider'  => smc_t( 'Πάροχος', 'Provider' ),
		'th_purpose'   => smc_t( 'Σκοπός', 'Purpose' ),
		'th_duration'  => smc_t( 'Διάρκεια', 'Duration' ),
		'this_site'    => smc_t( 'Αυτό το site', 'This site' ),
	);
}

function smc_defaults() {
	return array(
		'enabled'     => 0,
		'texts'       => smc_default_texts(),
		'look'        => array(
			'position' => 'left',
			'fill'     => '#46e9d4',
			'fill_text' => '#000000',
			'line'     => '#042727',
			'bg'       => '#ffffff',
			'text'     => '#0b1414',
			'equal'    => 1, // no design that favours "Accept" (Greek DPA); can be switched off in Appearance
			'hide_others' => 1,
		),
		'services'    => array(
			'google'  => array( 'on' => 0, 'id' => '', 'mode' => 'basic', 'cat' => 'statistics' ),
			'meta'    => array( 'on' => 0, 'id' => '' ),
			'clarity' => array( 'on' => 0, 'id' => '' ),
			'hotjar'  => array( 'on' => 0, 'id' => '' ),
		),
		'scripts'     => array(),
		'cookies'     => array(),
		'cookie_text' => array(),
		'policy'      => array( 'pages' => array() ),
		'consent'     => array(
			'months'    => 12,
			'retention' => 12,
			'gpc'       => 1,
			'purge'     => 1,
			'log'       => 1,
			'autoblock' => 1,
		),
	);
}

/** Texts of the cookie policy page the plugin creates. {table} is where the cookie table goes. */
function smc_policy_page_texts() {
	return array(
		'el' => array(
			'title' => 'Πολιτική cookies',
			'slug'  => 'politiki-cookies',
			'body'  => "<p>Τα cookies είναι μικρά αρχεία που αποθηκεύονται στον browser σας όταν επισκέπτεστε ένα site. Σε αυτή τη σελίδα θα βρείτε ποια cookies χρησιμοποιεί το site μας, γιατί, για πόσο καιρό και ποιος τα βάζει.</p>\n\n<h2>Πώς επιλέγετε</h2>\n<p>Την πρώτη φορά που μπαίνετε στο site σας ρωτάμε αν δέχεστε τα cookies που δεν είναι απαραίτητα. Μέχρι να απαντήσετε, και αν τα απορρίψετε, φορτώνουν μόνο τα απαραίτητα. Μπορείτε να αλλάξετε την επιλογή σας όποτε θέλετε από το link «Cookies» στο κάτω μέρος κάθε σελίδας ή εδώ: [smartconsent label=\"Αλλαγή των ρυθμίσεων cookies\"].</p>\n<p>Η επιλογή σας ισχύει για {months} μήνες. Μετά σας ρωτάμε ξανά. Αν ο browser σας στέλνει το σήμα Global Privacy Control, το λαμβάνουμε ως απόρριψη.</p>\n\n<h2>Τα cookies που χρησιμοποιούμε</h2>\n{table}\n\n<h2>Περισσότερα</h2>\n<p>Για το πώς επεξεργαζόμαστε τα προσωπικά σας δεδομένα, και για τα δικαιώματά σας, διαβάστε την Πολιτική απορρήτου. Μπορείτε επίσης να σβήσετε τα cookies από τις ρυθμίσεις του browser σας.</p>",
		),
		'en' => array(
			'title' => 'Cookie policy',
			'slug'  => 'cookie-policy',
			'body'  => "<p>Cookies are small files stored in your browser when you visit a website. This page explains which cookies our site uses, why, for how long and who sets them.</p>\n\n<h2>How you choose</h2>\n<p>The first time you visit, we ask whether you accept the cookies that aren't necessary. Until you answer, and if you reject them, only the necessary ones load. You can change your choice at any time from the “Cookies” link at the bottom of every page, or here: [smartconsent label=\"Change your cookie settings\"].</p>\n<p>Your choice is kept for {months} months, after which we ask again. If your browser sends the Global Privacy Control signal, we treat it as a rejection.</p>\n\n<h2>The cookies we use</h2>\n{table}\n\n<h2>More information</h2>\n<p>For how we process your personal data, and your rights, see our privacy policy. You can also delete cookies from your browser's settings.</p>",
		),
	);
}
