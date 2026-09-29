<?php
/**
 * Palika Live — Quick Scan (READ-ONLY report)
 *
 * File:  public_html/palika-scan.php
 * Use:   Log in to wp-admin, then open  https://palikalive.com/palika-scan.php
 *        Press "Download report (.txt)" and keep that file.
 *
 * This file only READS data (settings, database, one HTML fetch of the site).
 * It changes nothing. Delete it when you are done.
 *
 * Version: 1.0.0
 */

require_once __DIR__ . '/wp-load.php';

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( "Access denied. Log in to wp-admin first, then open this page again.\n" );
}

nocache_headers();
header( 'X-Robots-Tag: noindex, nofollow', true );

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

global $wpdb;

$R = array();
$T = function ( $title ) use ( &$R ) {
	$R[] = '';
	$R[] = '====== ' . $title . ' ======';
};
$L = function ( $line ) use ( &$R ) {
	$R[] = $line;
};

/* ---------------------------------------------------------------- basics */
$T( 'BASICS' );
$L( 'Generated      : ' . date( 'Y-m-d H:i:s' ) . ' (server time)' );
$L( 'Site URL       : ' . home_url( '/' ) );
$L( 'WP version     : ' . get_bloginfo( 'version' ) );
$L( 'PHP version    : ' . PHP_VERSION );
$L( 'Language       : ' . get_locale() );
$L( 'Blog name      : ' . get_bloginfo( 'name' ) );
$L( 'Tagline        : ' . get_bloginfo( 'description' ) );
$theme = wp_get_theme();
$L( 'Theme          : ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . '  [' . get_template_directory() . ']' );
$L( 'Permalink      : ' . get_option( 'permalink_structure' ) );
$L( 'Posts (publish): ' . (int) wp_count_posts( 'post' )->publish );
$L( 'Rank Math      : ' . ( defined( 'RANK_MATH_VERSION' ) ? 'active' : 'NOT detected' ) );
$L( 'Yoast          : ' . ( defined( 'WPSEO_VERSION' ) ? 'active' : 'not detected' ) );
$L( 'LiteSpeed Cache: ' . ( defined( 'LSCWP_V' ) ? 'active' : 'NOT detected' ) );

/* --------------------------------------------------------------- plugins */
$T( 'ACTIVE PLUGINS' );
$active = (array) get_option( 'active_plugins' );
if ( $active ) {
	foreach ( $active as $plugin_file ) {
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
		if ( ! empty( $data['Name'] ) ) {
			$L( '- ' . $data['Name'] . ' ' . $data['Version'] );
		} else {
			$L( '- ' . $plugin_file );
		}
	}
} else {
	$L( '(none)' );
}

/* ------------------------------------------------------------- mu-plugins */
$T( 'MU-PLUGINS (wp-content/mu-plugins)' );
$mu_files = glob( WPMU_PLUGIN_DIR . '/*.php' );
if ( $mu_files ) {
	foreach ( $mu_files as $mu ) {
		$L( '- ' . basename( $mu ) . '  (' . size_format( @filesize( $mu ) ) . ')' );
	}
} else {
	$L( '(none)' );
}

/* ---------------------------------------------------------- view meta keys */
$T( 'VIEW META KEYS (top 10 by usage)' );
$view_rows = $wpdb->get_results(
	"SELECT meta_key, COUNT(*) AS used FROM {$wpdb->postmeta} WHERE meta_key LIKE '%view%' GROUP BY meta_key ORDER BY used DESC LIMIT 10"
);
if ( $view_rows ) {
	foreach ( $view_rows as $row ) {
		$L( str_pad( (string) $row->used, 8, ' ', STR_PAD_LEFT ) . ' x  ' . $row->meta_key );
	}
	$L( '' );
	$L( '=> Use in palikalive-fixes.php  ->  views_meta_key: ' . $view_rows[0]->meta_key );
} else {
	$L( '(no meta key contains "view")' );
}

/* ------------------------------------------------------------ theme files */
$T( 'THEME FILE LIST (php files, root + inc)' );
$theme_dir = get_template_directory();
foreach ( array( '', '/inc' ) as $sub ) {
	$found = glob( $theme_dir . $sub . '/*.php' );
	if ( $found ) {
		foreach ( $found as $file ) {
			$L( '- ' . ( $sub ? ltrim( $sub, '/' ) . '/' : '' ) . basename( $file ) . '  (' . size_format( @filesize( $file ) ) . ')' );
		}
	}
}

/* ------------------------------------------- theme wp_head / seo callbacks */
$T( 'THEME wp_head LINES (static scan — for seo_head_callbacks)' );
$scan_files = array();
foreach ( array( '', '/inc', '/template-parts' ) as $sub ) {
	$found = glob( $theme_dir . $sub . '/*.php' );
	if ( $found ) {
		$scan_files = array_merge( $scan_files, $found );
	}
}
$hits = 0;
foreach ( $scan_files as $file ) {
	$src = @file_get_contents( $file );
	if ( ! $src ) {
		continue;
	}
	$lines = preg_split( '/\r\n|\n/', $src );
	foreach ( $lines as $no => $text ) {
		if ( false !== stripos( $text, 'wp_head' ) ) {
			$L( '- ' . str_replace( $theme_dir . '/', '', $file ) . ':' . ( $no + 1 ) . '  ' . trim( $text ) );
			$hits++;
			if ( $hits >= 60 ) {
				break 2;
			}
		}
	}
}
if ( 0 === $hits ) {
	$L( '(no wp_head lines found)' );
}

/* ------------------------------------------------------------ categories */
$T( 'CATEGORIES (posts | name | slug)' );
$cats = $wpdb->get_results(
	"SELECT t.name, tt.slug, tt.count
	 FROM {$wpdb->terms} t
	 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
	 WHERE tt.taxonomy = 'category'
	 ORDER BY tt.count DESC, t.name ASC"
);
if ( $cats ) {
	foreach ( $cats as $cat ) {
		$L( str_pad( (string) $cat->count, 5, ' ', STR_PAD_LEFT ) . '  ' . $cat->name . '  [' . $cat->slug . ']' );
	}
}

/* --------------------------------------------------------- ad positions */
$T( 'AD POSITION TERMS (ads | term | slug)' );
$ad_terms = $wpdb->get_results(
	"SELECT t.name, tt.slug, tt.count
	 FROM {$wpdb->terms} t
	 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
	 WHERE tt.taxonomy = 'ad_position'
	 ORDER BY tt.count DESC, t.name ASC"
);
if ( $ad_terms ) {
	foreach ( $ad_terms as $term ) {
		$L( str_pad( (string) $term->count, 5, ' ', STR_PAD_LEFT ) . '  ' . $term->name . '  [' . $term->slug . ']' );
	}
} else {
	$L( '(taxonomy ad_position not found)' );
}

/* ------------------------------------------------------------------ pages */
$T( 'PAGES (id | status | slug | title)' );
$pages = $wpdb->get_results(
	"SELECT ID, post_status, post_name, post_title FROM {$wpdb->posts} WHERE post_type = 'page' ORDER BY ID ASC"
);
if ( $pages ) {
	foreach ( $pages as $page ) {
		$L( $page->ID . ' | ' . $page->post_status . ' | /' . $page->post_name . '/ | ' . $page->post_title );
	}
} else {
	$L( '(no pages)' );
}

/* ------------------------------------------------------------------ menus */
$T( 'MENUS' );
$menus = wp_get_nav_menus();
if ( $menus ) {
	foreach ( $menus as $menu ) {
		$items = wp_get_nav_menu_items( $menu->term_id );
		$L( 'Menu: ' . $menu->name . ' (' . ( $items ? count( $items ) : 0 ) . ' items)' );
		if ( $items ) {
			foreach ( $items as $item ) {
				$L( '   - ' . $item->title . '   ->   ' . $item->url );
			}
		}
	}
} else {
	$L( '(no menus)' );
}

/* ------------------------------------------------------- live html checks */
$T( 'LIVE HTML CHECKS (server-side fetch of your own site)' );
$latest_posts = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
$targets = array(
	'Homepage'    => home_url( '/' ),
	'Latest post' => $latest_posts ? get_permalink( $latest_posts[0]->ID ) : '',
);
foreach ( $targets as $label => $url ) {
	if ( ! $url ) {
		continue;
	}
	$L( '-- ' . $label . ': ' . $url );
	$fetch_url = $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'pkscan=' . time();
	$response  = wp_remote_get(
		$fetch_url,
		array(
			'timeout'     => 15,
			'redirection' => 3,
			'sslverify'   => false,
			'user-agent'  => 'PalikaScan/1.0',
		)
	);
	if ( is_wp_error( $response ) ) {
		$L( '   FETCH FAILED: ' . $response->get_error_message() );
		continue;
	}
	$html = wp_remote_retrieve_body( $response );
	if ( '' === $html ) {
		$L( '   EMPTY RESPONSE' );
		continue;
	}
	$lower    = strtolower( $html );
	$canon    = substr_count( $html, 'rel="canonical"' ) + substr_count( $html, "rel='canonical'" );
	$desc     = substr_count( $html, 'name="description"' ) + substr_count( $html, "name='description'" );
	$empty    = substr_count( $html, 'href=""' ) + substr_count( $html, "href=''" );
	$L( '   bytes               : ' . number_format( strlen( $html ) ) );
	$L( '   HTTP code           : ' . wp_remote_retrieve_response_code( $response ) );
	$L( '   canonical tags      : ' . $canon . ( $canon > 1 ? '  <-- DUPLICATE (SEO)' : '' ) );
	$L( '   description tags    : ' . $desc . ( $desc > 1 ? '  <-- DUPLICATE (SEO)' : '' ) );
	$L( '   og:title tags       : ' . substr_count( $html, 'og:title' ) );
	$L( '   NewsArticle schema  : ' . substr_count( $html, 'NewsArticle' ) );
	$L( '   <h1> tags           : ' . substr_count( $lower, '<h1' ) );
	$L( '   empty href="" links : ' . $empty );
	$L( '   single-fix (pklv-js): ' . ( false !== strpos( $html, 'pklv-js' ) ? 'yes' : 'no' ) );
	$L( '   share count chip    : ' . ( false !== strpos( $html, 'pl-share-count' ) ? 'yes' : 'no' ) );
	$L( '   ad engine marker    : ' . ( false !== strpos( $html, 'PalikaLive Ad Engine' ) ? 'yes' : 'no' ) );
}

/* ---------------------------------------------------------- latest posts */
$T( 'LATEST 5 POSTS (id | date | url)' );
$recent = get_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
if ( $recent ) {
	foreach ( $recent as $post_item ) {
		$L( $post_item->ID . ' | ' . $post_item->post_date . ' | ' . get_permalink( $post_item->ID ) );
	}
}

$L( '' );
$L( 'NOTE: delete public_html/palika-scan.php when you are done.' );

$report = implode( PHP_EOL, $R );

/* --------------------------------------------------------------- output */
if ( isset( $_GET['download'] ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="palika-scan-' . date( 'Y-m-d-Hi' ) . '.txt"' );
	echo $report;
	exit;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title>Palika Live — Quick Scan</title>
<style>
body{font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:24px;color:#16181d;background:#fff}
pre{background:#f6f7f9;border:1px solid #dde1e6;border-radius:8px;padding:16px;overflow:auto;font:12.5px/1.55 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap}
a.btn{display:inline-block;background:#0b2545;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:700;margin:0 0 14px}
.warn{background:#fff7e6;border-left:4px solid #e8a33d;padding:10px 14px;border-radius:4px;margin-bottom:14px}
h2{margin:0 0 10px}
</style>
</head>
<body>
<h2>Palika Live — Quick Scan (read-only)</h2>
<p><a class="btn" href="?download=1">Download report (.txt)</a></p>
<div class="warn">This file only reads data. Delete <code>public_html/palika-scan.php</code> from the server when you are done.</div>
<pre><?php echo esc_html( $report ); ?></pre>
</body>
</html>
