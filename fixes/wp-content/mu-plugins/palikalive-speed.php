<?php
/**
 * Plugin Name: Palika Live — Speed Pack
 * Description: Trims the Google Fonts download to the families the page really uses, adds preconnect hints for the font host and the image CDN, and reports exactly what it changed. Front end only; delete the file to revert.
 * Version: 1.0.0
 * Author: Palika Live
 *
 * File:  wp-content/mu-plugins/palikalive-speed.php
 *
 * ==========================================================================
 * WHAT IT DOES
 * ==========================================================================
 * 1. Google Fonts.  The theme asks Google for several font families. This file
 *    keeps only the families the page's own CSS actually mentions, in one
 *    request, with display=swap (text is readable while fonts download).
 *    Works on both:
 *      <link href="https://fonts.googleapis.com/css?family=A|B|C">
 *      <style>@import url(https://fonts.googleapis.com/css?family=A|B|C);</style>
 *    If it cannot recognise anything, it changes nothing.
 * 2. Preconnect.  Adds <link rel="preconnect"> for fonts.gstatic.com and for the
 *    image CDN host the page already uses (…exactdn.com), so DNS + TLS start
 *    earlier and the first image/font arrives sooner.
 * 3. Report.  While logged in as an administrator, add ?pklv_speed_report=1 to
 *    any page URL to see exactly what this file did on that page, plus every
 *    enqueued stylesheet and script (handle + URL).
 *
 * ==========================================================================
 * WHAT IT NEVER DOES
 * ==========================================================================
 * - Never edits a theme file. Nothing is written anywhere.
 * - Never adds a font family the page's CSS does not mention.
 * - Never touches wp-admin, AJAX, REST, feeds, robots.txt, sitemaps or XML.
 *
 * ==========================================================================
 * ROLLBACK
 * ==========================================================================
 * Delete the file (or rename it to palikalive-speed.php.bak). Nothing else
 * to undo. Then LiteSpeed Cache -> Purge All.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access blocked.
}

if ( ! defined( 'PKLV_SPEED_VERSION' ) ) {
	define( 'PKLV_SPEED_VERSION', '1.0.0' );
}

/* ==========================================================================
 * 0. CONFIGURATION  (edit these lines only)
 * ========================================================================== */

/**
 * Settings for this file.
 *
 * @return array
 */
function pklv_speed_config() {
	return array(
		'enabled'          => true,  // Master switch: false = the whole file does nothing.
		'trim_fonts'       => true,  // Rewrite the Google Fonts request.
		'preconnect'       => true,  // Add preconnect hints.

		// Families that must stay even when no CSS rule mentions them.
		// Example: array( 'Khand' )
		'always_keep'      => array(),

		// Extra hosts to preconnect to. Example: array( 'https://static.example.com' )
		'extra_preconnect' => array(),
	);
}

/**
 * Google families we can rebuild with exact weights (css2 API).
 * Only families listed here are ever requested. Unknown families are left alone.
 *
 * @return array
 */
function pklv_speed_font_map() {
	return array(
		'Mukta'                => 'Mukta:wght@400;500;600;700',
		'Ek Mukta'             => 'Ek+Mukta:wght@400;600;700',
		'Khand'                => 'Khand:wght@400;500;600;700',
		'Arya'                 => 'Arya:wght@400;700',
		'Kalam'                => 'Kalam:wght@400;700',
		'Niramit'              => 'Niramit:wght@400;600;700',
		'Roboto'               => 'Roboto:wght@400;500;700',
		'Noto Sans Devanagari' => 'Noto+Sans+Devanagari:wght@400;600;700',
	);
}

/* ==========================================================================
 * 1. OUTPUT BUFFER  (front end only)
 * ========================================================================== */

add_action( 'template_redirect', 'pklv_speed_start_buffer', 0 );

/**
 * Start buffering only for normal front-end HTML pages.
 *
 * @return void
 */
function pklv_speed_start_buffer() {

	$cfg = pklv_speed_config();
	if ( empty( $cfg['enabled'] ) ) {
		return;
	}

	if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_embed() || is_404() ) {
		return;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ), true ) ) {
		return;
	}

	ob_start( 'pklv_speed_filter_html' );
}

/**
 * Rewrite the finished HTML just before it is sent (and before LiteSpeed caches it).
 *
 * @param string $html Page HTML.
 * @return string
 */
function pklv_speed_filter_html( $html ) {

	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<head' ) ) {
		return $html; // JSON, XML, redirects, empty buffers.
	}

	$cfg   = pklv_speed_config();
	$note  = array();
	$bytes = strlen( $html );

	/* ---- 1a. Google Fonts ------------------------------------------------ */
	if ( ! empty( $cfg['trim_fonts'] ) ) {
		$html = pklv_speed_rewrite_font_links( $html, $cfg, $note );
		$html = pklv_speed_rewrite_font_imports( $html, $cfg, $note );
	}

	/* ---- 1b. Preconnect hints ------------------------------------------- */
	if ( ! empty( $cfg['preconnect'] ) ) {
		$html = pklv_speed_add_preconnect( $html, $cfg, $note );
	}

	/* ---- 1c. Optional report (administrators only) ---------------------- */
	if ( ! empty( $_GET['pklv_speed_report'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		$html = pklv_speed_append_report( $html, $note, $bytes );
	}

	return $html;
}

/* ==========================================================================
 * 2. FONT REWRITING
 * ========================================================================== */

/**
 * Read the family names out of a Google Fonts URL (v1 and css2 forms).
 *
 * @param string $url Google Fonts URL (HTML entities decoded before the call).
 * @return array Family names, e.g. array( 'Mukta', 'Khand' ).
 */
function pklv_speed_families_from_url( $url ) {

	$out = array();
	$pos = strpos( $url, '?' );
	$query = ( false === $pos ) ? '' : substr( $url, $pos + 1 );

	if ( '' === $query ) {
		return $out;
	}

	if ( preg_match_all( '/(?:^|&)family=([^&]+)/i', $query, $m ) ) {
		foreach ( $m[1] as $raw ) {
			$raw = urldecode( $raw );
			// v1: family=A|B|C   css2: family=A:wght@400;700 (one per family=)
			foreach ( explode( '|', $raw ) as $one ) {
				$one = preg_replace( '/:.*$/', '', $one );   // drop :wght@…
				$one = str_replace( '+', ' ', trim( $one ) ); // Ek+Mukta -> Ek Mukta
				if ( '' !== $one ) {
					$out[] = $one;
				}
			}
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Is a family mentioned in the page's own CSS?
 *
 * @param string $haystack All `font-family:` declarations found in the page.
 * @param string $family   Family name.
 * @return bool
 */
function pklv_speed_family_is_used( $haystack, $family ) {
	return (bool) preg_match( '/(?<![\w-])' . preg_quote( $family, '/' ) . '(?![\w-])/i', $haystack );
}

/**
 * The page text used to decide which families are really used.
 *
 * Google Fonts URLs are removed first, so a family counts as "used" only when
 * some other part of the page (CSS rule, CSS variable, inline style, script)
 * mentions it - never because the old font URL contained the name.
 *
 * @param string $html Page HTML.
 * @return string
 */
function pklv_speed_font_declarations( $html ) {
	$haystack = preg_replace( '#https?://[^"\']*fonts\.googleapis\.com[^"\']*#i', ' ', $html );
	if ( null === $haystack ) {
		$haystack = $html;
	}
	return $haystack;
}

/**
 * Build the trimmed css2 URL for the families that are really used.
 *
 * @param array  $orig   Families the theme asked for.
 * @param string $used   Font declarations of the page.
 * @param array  $cfg    Config.
 * @param array  $note   Report lines (by reference).
 * @param string $source 'link' or 'import' (for the report).
 * @return string New URL, or '' when nothing should change.
 */
function pklv_speed_build_font_url( $orig, $used, $cfg, &$note, $source ) {

	$map  = pklv_speed_font_map();
	$keep = array();

	foreach ( $orig as $family ) {
		if ( ! isset( $map[ $family ] ) ) {
			continue; // Unknown family: never invented, never requested here.
		}
		if ( pklv_speed_family_is_used( $used, $family ) || in_array( $family, (array) $cfg['always_keep'], true ) ) {
			$keep[] = $map[ $family ];
		}
	}

	$note[] = 'fonts (' . $source . '): asked for [' . implode( ', ', $orig ) . '] -> keeping [' . implode( ', ', $keep ) . ']';

	if ( empty( $keep ) ) {
		$note[] = 'fonts (' . $source . '): nothing recognised as used -> left unchanged';
		return '';
	}
	if ( count( $keep ) === count( $orig ) ) {
		$note[] = 'fonts (' . $source . '): all families are used -> only display=swap added';
	}

	$args = array();
	foreach ( $keep as $one ) {
		$args[] = 'family=' . $one;
	}
	$args[] = 'display=swap';

	return 'https://fonts.googleapis.com/css2?' . implode( '&', $args );
}

/**
 * Rewrite <link href="https://fonts.googleapis.com/..."> tags.
 *
 * @param string $html Page HTML.
 * @param array  $cfg  Config.
 * @param array  $note Report lines (by reference).
 * @return string
 */
function pklv_speed_rewrite_font_links( $html, $cfg, &$note ) {

	if ( false === stripos( $html, 'fonts.googleapis.com' ) ) {
		$note[] = 'fonts (link): no Google Fonts request found on this page';
		return $html;
	}

	$used = pklv_speed_font_declarations( $html );

	if ( ! preg_match_all( '#<link\b[^>]*>#i', $html, $links ) ) {
		return $html;
	}

	foreach ( $links[0] as $tag ) {
		if ( false === stripos( $tag, 'fonts.googleapis.com' ) || false === stripos( $tag, 'stylesheet' ) ) {
			continue;
		}
		if ( ! preg_match( '#href=(["\'])(.*?)\1#i', $tag, $hm ) ) {
			continue;
		}
		$old = html_entity_decode( $hm[2], ENT_QUOTES, 'UTF-8' );
		if ( false === strpos( $old, 'fonts.googleapis.com' ) ) {
			continue;
		}
		$new = pklv_speed_build_font_url( pklv_speed_families_from_url( $old ), $used, $cfg, $note, 'link' );
		if ( '' === $new ) {
			continue;
		}
		$replacement = '<link rel="stylesheet" id="pklv-fonts-css" href="' . esc_url( $new ) . '" media="all">';
		$html        = str_replace( $tag, $replacement, $html );
		$note[]      = 'fonts (link): ' . $old . '  =>  ' . $new;
	}

	return $html;
}

/**
 * Remove @import rules that pull Google Fonts from inside inline styles
 * (the trimmed <link> is added afterwards by pklv_speed_rewrite_font_links()).
 *
 * @param string $html Page HTML.
 * @param array  $cfg  Config.
 * @param array  $note Report lines (by reference).
 * @return string
 */
function pklv_speed_rewrite_font_imports( $html, $cfg, &$note ) {

	$pattern = '#@import\s+(?:url\(\s*)?(["\']?)https?://fonts\.googleapis\.com/css[^)"\';]*\1(?:\s*\))?\s*;#i';

	if ( ! preg_match( $pattern, $html ) ) {
		return $html;
	}

	$used = pklv_speed_font_declarations( $html );

	if ( preg_match_all( $pattern, $html, $hits ) ) {
		foreach ( $hits[0] as $rule ) {
			$url = '';
			if ( preg_match( '#https?://fonts\.googleapis\.com/css[^)"\';]*#i', $rule, $um ) ) {
				$url = html_entity_decode( $um[0], ENT_QUOTES, 'UTF-8' );
			}
			if ( '' === $url ) {
				continue;
			}
			$new = pklv_speed_build_font_url( pklv_speed_families_from_url( $url ), $used, $cfg, $note, 'import' );
			$html = str_replace( $rule, '', $html );
			$note[] = 'fonts (import): removed @import ' . $url;
			if ( '' !== $new && false === strpos( $html, $new ) ) {
				$added = preg_replace( '#<head(\s[^>]*)?>#i', '<head$1><link rel="stylesheet" id="pklv-fonts-css" href="' . esc_url( $new ) . '" media="all">', $html, 1 );
				if ( null !== $added ) {
					$html   = $added;
					$note[] = 'fonts (import): added ' . $new;
				}
			}
		}
	}

	return $html;
}

/* ==========================================================================
 * 3. PRECONNECT HINTS
 * ========================================================================== */

/**
 * Add preconnect hints for the font host and the image CDN the page uses.
 *
 * @param string $html Page HTML.
 * @param array  $cfg  Config.
 * @param array  $note Report lines (by reference).
 * @return string
 */
function pklv_speed_add_preconnect( $html, $cfg, &$note ) {

	$hosts = array();

	if ( false !== stripos( $html, 'fonts.googleapis.com' ) || false !== stripos( $html, 'fonts.gstatic.com' ) ) {
		$hosts['https://fonts.googleapis.com'] = false;
		$hosts['https://fonts.gstatic.com']    = true; // crossorigin required for font files.
	}

	if ( preg_match( '#https://([a-z0-9\-]+\.exactdn\.com)#i', $html, $cdn ) ) {
		$hosts[ 'https://' . $cdn[1] ] = false;
	}

	foreach ( (array) $cfg['extra_preconnect'] as $extra ) {
		$extra = trim( (string) $extra );
		if ( '' !== $extra ) {
			$hosts[ $extra ] = false;
		}
	}

	$inject = '';
	foreach ( $hosts as $host => $crossorigin ) {
		if ( false !== stripos( $html, 'rel="preconnect" href="' . $host . '"' ) ) {
			continue;
		}
		$inject .= '<link rel="preconnect" href="' . esc_url( $host ) . '"' . ( $crossorigin ? ' crossorigin' : '' ) . '>';
		$note[] = 'preconnect: ' . $host;
	}

	if ( '' === $inject ) {
		return $html;
	}

	$out = preg_replace( '#<head(\s[^>]*)?>#i', '<head$1>' . $inject, $html, 1 );

	return ( null === $out ) ? $html : $out;
}

/* ==========================================================================
 * 4. ADMIN REPORT  ( ?pklv_speed_report=1 )
 * ========================================================================== */

/**
 * Append a plain-text report inside the page for administrators.
 *
 * @param string $html  Page HTML.
 * @param array  $note  Report lines.
 * @param int    $bytes HTML size before the changes.
 * @return string
 */
function pklv_speed_append_report( $html, $note, $bytes ) {

	$lines   = array();
	$lines[] = 'Palika Live — Speed Pack report  (v' . PKLV_SPEED_VERSION . ')';
	$lines[] = 'URL: ' . home_url( add_query_arg( array() ) );
	$lines[] = 'HTML: ' . number_format( $bytes ) . ' bytes before, ' . number_format( strlen( $html ) ) . ' bytes after';
	$lines[] = '';
	$lines[] = '-- what this file changed on this page --';
	$lines   = array_merge( $lines, empty( $note ) ? array( '(nothing)' ) : $note );

	$lines[] = '';
	$lines[] = '-- stylesheets enqueued (handle => url) --';
	$lines   = array_merge( $lines, pklv_speed_asset_lines( wp_styles() ) );

	$lines[] = '';
	$lines[] = '-- scripts enqueued (handle => url) --';
	$lines   = array_merge( $lines, pklv_speed_asset_lines( wp_scripts() ) );

	$lines[] = '';
	$lines[] = 'Remove ?pklv_speed_report=1 from the URL to see the normal page.';

	$report = '<pre id="pklv-speed-report" style="margin:24px;padding:16px;background:#0b2545;color:#e8f0ff;'
		. 'font:12.5px/1.55 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap;border-radius:8px;'
		. 'direction:ltr;text-align:left;position:relative;z-index:99999">'
		. esc_html( implode( "\n", $lines ) ) . '</pre>';

	if ( false !== stripos( $html, '</body>' ) ) {
		$out = preg_replace( '#</body>#i', $report . '</body>', $html, 1 );
		return ( null === $out ) ? $html . $report : $out;
	}

	return $html . $report;
}

/**
 * Format a WP dependencies object as "handle => url" lines.
 *
 * @param WP_Dependencies $deps Styles or scripts object.
 * @return array
 */
function pklv_speed_asset_lines( $deps ) {

	$out = array();

	if ( ! is_object( $deps ) || empty( $deps->done ) ) {
		return array( '(none printed)' );
	}

	$done = $deps->done;
	sort( $done );

	foreach ( $done as $handle ) {
		$src = '';
		if ( isset( $deps->registered[ $handle ] ) && is_object( $deps->registered[ $handle ] ) ) {
			$src = (string) $deps->registered[ $handle ]->src;
		}
		if ( '' === $src ) {
			$src = '(inline only)';
		} elseif ( strlen( $src ) > 150 ) {
			$src = substr( $src, 0, 150 ) . ' …';
		}
		$out[] = $handle . ' => ' . $src;
	}

	return array_merge( array( 'total: ' . count( $done ) ), $out );
}
