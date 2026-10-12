<?php
/**
 * [smartconsent]          the "Cookies" link for the footer; opens the preferences (label="…" for another text)
 * [smartconsent_cookies]  the cookie table of the cookie policy page, in the page's language
 */
defined( 'ABSPATH' ) || exit;

add_shortcode( 'smartconsent', function ( $atts ) {
	$atts  = shortcode_atts( array( 'label' => '' ), $atts, 'smartconsent' );
	$label = trim( smc_clean_text( smc_limit( $atts['label'], 80 ) ) );
	if ( '' === $label ) {
		$label = smc_tr( smc_get_settings()['texts']['link'], smc_current_lang() );
	}
	return '<a href="#smartconsent" class="smartconsent-open" role="button">' . esc_html( $label ) . '</a>';
} );

add_shortcode( 'smartconsent_cookies', function () {
	return smc_cookie_table_html( smc_current_lang() );
} );

function smc_cookie_table_html( $lang ) {
	$s    = smc_get_settings();
	$tx   = function ( $k ) use ( $s, $lang ) {
		return smc_tr( $s['texts'][ $k ], $lang );
	};
	$rows = smc_cookie_list( $s );
	$out  = '<div class="smartconsent-cookies">';
	foreach ( smc_used_cats( $s ) as $cat ) {
		$in = array_filter( $rows, function ( $r ) use ( $cat ) {
			return $r['cat'] === $cat;
		} );
		if ( ! $in ) {
			continue;
		}
		$out .= '<h3>' . esc_html( $tx( 'cat_' . $cat ) ) . '</h3><p>' . esc_html( $tx( 'cat_' . $cat . '_d' ) ) . '</p>';
		$out .= '<div style="overflow-x:auto"><table><thead><tr><th>' . esc_html( $tx( 'th_cookie' ) ) . '</th><th>' . esc_html( $tx( 'th_provider' ) ) . '</th><th>' . esc_html( $tx( 'th_purpose' ) ) . '</th><th>' . esc_html( $tx( 'th_duration' ) ) . '</th></tr></thead><tbody>';
		foreach ( $in as $r ) {
			$out .= '<tr><td><code>' . esc_html( $r['name'] ) . '</code></td><td>' . esc_html( '' !== $r['provider'] ? $r['provider'] : $tx( 'this_site' ) ) . '</td><td>' . esc_html( smc_tr( $r['purpose'], $lang ) ) . '</td><td>' . esc_html( smc_tr( $r['duration'], $lang ) ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
	}
	return $out . '</div>';
}
