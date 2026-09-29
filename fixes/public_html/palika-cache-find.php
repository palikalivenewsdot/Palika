<?php
/**
 * Palika Live — Cache Finder (READ-ONLY)
 *
 * File:  public_html/palika-cache-find.php
 * Use:   Log in to wp-admin, then open  https://palikalive.com/palika-cache-find.php
 *
 * Answers one question:  "Which code is telling LiteSpeed / the browser NOT to
 * cache the article pages?"  It only READS: LiteSpeed settings, runtime flags,
 * and a text search inside the theme + plugin files. It changes nothing.
 * Delete it when you are done.
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

global $wpdb;

$R = array();
$T = function ( $title ) use ( &$R ) {
	$R[] = '';
	$R[] = '====== ' . $title . ' ======';
};
$L = function ( $line ) use ( &$R ) {
	$R[] = $line;
};

/* ------------------------------------------------- 1. runtime flags */
$T( 'RUNTIME FLAGS (this request)' );
$L( 'DONOTCACHEPAGE constant : ' . ( defined( 'DONOTCACHEPAGE' ) ? 'DEFINED = ' . var_export( constant( 'DONOTCACHEPAGE' ), true ) : 'not defined' ) );
$L( 'DONOTCACHEOBJECT       : ' . ( defined( 'DONOTCACHEOBJECT' ) ? 'DEFINED' : 'not defined' ) );
$L( 'DONOTMINIFY            : ' . ( defined( 'DONOTMINIFY' ) ? 'DEFINED' : 'not defined' ) );
$L( 'WP_CACHE constant      : ' . ( defined( 'WP_CACHE' ) ? var_export( constant( 'WP_CACHE' ), true ) : 'not defined' ) );
$L( 'LiteSpeed plugin       : ' . ( defined( 'LSCWP_V' ) ? 'active ' . constant( 'LSCWP_V' ) : 'not detected' ) );
$L( 'Logged in user         : ' . ( is_user_logged_in() ? 'YES (admin sees uncached pages by design)' : 'no' ) );

/* --------------------------------------------- 2. LiteSpeed settings */
$T( 'LITESPEED SETTINGS THAT AFFECT CACHING' );
$rows = $wpdb->get_results(
	"SELECT option_name, option_value
	 FROM {$wpdb->options}
	 WHERE option_name LIKE 'litespeed.conf.cache%'
	    OR option_name LIKE 'litespeed.conf.purge%'
	    OR option_name LIKE 'litespeed.conf.esi%'
	 ORDER BY option_name ASC"
);
if ( $rows ) {
	foreach ( $rows as $row ) {
		$value = (string) $row->option_value;
		if ( strlen( $value ) > 300 ) {
			$value = substr( $value, 0, 300 ) . ' …';
		}
		$L( $row->option_name . ' = ' . $value );
	}
} else {
	$L( '(no litespeed.conf.* rows found — is LiteSpeed Cache configured?)' );
}

/* -------------------------------------- 3. text search in theme/plugins */
$T( 'CODE THAT CAN BLOCK CACHING (theme + plugins)' );

$needles = array(
	'nocache_headers',
	'DONOTCACHEPAGE',
	'no-store',
	'Cache-Control',
	'Pragma',
);

$dirs = array(
	'THEME'      => get_template_directory(),
	'PLUGINS'    => WP_PLUGIN_DIR,
	'MU-PLUGINS' => WPMU_PLUGIN_DIR,
);

$skip_dirs = array( 'node_modules', 'vendor', '.git', 'languages', 'assets', 'images', 'css', 'js' );

$total = 0;
foreach ( $dirs as $label => $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	$L( '' );
	$L( '-- ' . $label . ': ' . $dir );
	$hits_here = 0;

	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				function ( $file ) use ( $skip_dirs ) {
					if ( $file->isDir() ) {
						return ! in_array( strtolower( $file->getFilename() ), $skip_dirs, true );
					}
					return true;
				}
			),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
	} catch ( Exception $e ) {
		$L( '   (cannot read folder)' );
		continue;
	}

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$src = @file_get_contents( $file->getPathname() );
		if ( ! $src ) {
			continue;
		}
		$lines = preg_split( '/\r\n|\n/', $src );
		foreach ( $lines as $no => $text ) {
			foreach ( $needles as $needle ) {
				if ( false !== stripos( $text, $needle ) ) {
					$rel = str_replace( array( $dir . '/', '\\' ), array( '', '/' ), $file->getPathname() );
					$L( '   ' . $rel . ':' . ( $no + 1 ) . '  ' . trim( substr( $text, 0, 170 ) ) );
					$hits_here++;
					$total++;
					break;
				}
			}
			if ( $total >= 200 ) {
				$L( '   … (stopped after 200 matches)' );
				break 3;
			}
		}
	}
	if ( 0 === $hits_here ) {
		$L( '   (nothing found)' );
	}
}

/* ------------------------------------------------ 4. live self-fetch */
$T( 'LIVE CHECK — what the site answers to an anonymous visitor' );
$latest = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
$test_url = $latest ? get_permalink( $latest[0]->ID ) : home_url( '/' );
$L( 'URL: ' . $test_url );

$log = array();
$first = wp_remote_get( $test_url . '?cflive=1', array( 'timeout' => 20, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (CacheFinder)' ) );
if ( ! is_wp_error( $first ) ) {
	$log = (array) wp_remote_retrieve_headers( $first );
	$L( '-- 1st anonymous request:' );
	foreach ( $log as $key => $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		if ( preg_match( '/cache|litespeed|x-litespeed/i', $key ) ) {
			$L( '   ' . $key . ': ' . $value );
		}
	}
	$second = wp_remote_get( $test_url . '?cflive=1', array( 'timeout' => 20, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (CacheFinder)' ) );
	if ( ! is_wp_error( $second ) ) {
		$headers2 = (array) wp_remote_retrieve_headers( $second );
		$L( '-- 2nd identical request (a working cache shows "hit" here):' );
		foreach ( $headers2 as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( preg_match( '/cache|litespeed|x-litespeed/i', $key ) ) {
				$L( '   ' . $key . ': ' . $value );
			}
		}
	}
} else {
	$L( '   FETCH FAILED: ' . $first->get_error_message() );
}

$L( '' );
$L( 'NOTE: delete public_html/palika-cache-find.php when you are done.' );

$report = implode( PHP_EOL, $R );

if ( isset( $_GET['download'] ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="palika-cache-find-' . date( 'Y-m-d-Hi' ) . '.txt"' );
	echo $report;
	exit;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title>Palika Live — Cache Finder</title>
<style>
body{font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:24px;color:#16181d;background:#fff}
pre{background:#f6f7f9;border:1px solid #dde1e6;border-radius:8px;padding:16px;overflow:auto;font:12.5px/1.55 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap}
a.btn{display:inline-block;background:#0b2545;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:700;margin:0 0 14px}
.warn{background:#fff7e6;border-left:4px solid #e8a33d;padding:10px 14px;border-radius:4px;margin-bottom:14px}
h2{margin:0 0 10px}
</style>
</head>
<body>
<h2>Palika Live — Cache Finder (read-only)</h2>
<p><a class="btn" href="?download=1">Download report (.txt)</a></p>
<div class="warn">Read-only. Delete <code>public_html/palika-cache-find.php</code> when you are done.</div>
<pre><?php echo esc_html( $report ); ?></pre>
</body>
</html>
