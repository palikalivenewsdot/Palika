<?php
/**
 * Plugin Name: Palika Live — स्क्यान र सुरक्षित मर्मत उपकरण
 * Description: अडिट (२९ सेप्टेम्बर २०२६) का बुँदा आफ्नै साइटमा जाँच्ने र केही सुरक्षित सुधार (ABSPATH guard, .DS_Store cleanup) गर्ने उपकरण।
 * Version:     1.0.0
 *
 * ------------------------------------------------------------------
 * प्रयोग कसरी गर्ने (cPanel बाट):
 *   १) File Manager → public_html/ भित्र यो फाइल Upload गर्नुहोस्
 *      (नाम: palika-toolkit.php)
 *   २) ब्राउजरमा खोल्नुहोस्:  https://palikalive.com/palika-toolkit.php
 *      → यो पेज हेर्न admin लगइन अनिवार्य छ (नत्र 403 आउँछ)।
 *   ३) रिपोर्ट पढ्नुहोस्। "सुरक्षित मर्मत" बटनहरू टेक्दा पहिले
 *      wp-content/uploads/palika-backup-.../ मा फाइलको प्रतिलिपि बन्छ।
 *   ४) काम सकिएपछि यो फाइल अनिवार्य Delete गर्नुहोस् (सुरक्षा)।
 *
 * केही पनि "write" नगर्ने स्क्यान मात्र हेर्न चाहनुहुन्छ भने केही टेक्नु पर्दैन।
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/wp-load.php';

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die(
		'यो उपकरण हेर्नको लागि wp-admin मा administrator भएर लगइन गर्नुहोस्।',
		'अनुमति छैन',
		array( 'response' => 403 )
	);
}

@set_time_limit( 180 );
@ini_set( 'memory_limit', '512M' );

// यो पेज कुनै क्यास/सर्च इन्जिनमा नजाओस् (रिपोर्ट साइटको जानकारी हो)।
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}
nocache_headers();
header( 'X-Robots-Tag: noindex, nofollow', true );

/* ==============================================================
 * सहयोगी फंक्सन
 * ============================================================== */

/**
 * थिम फोल्डर।
 *
 * @return string
 */
function pkt_theme_dir() {
	return trailingslashit( get_template_directory() );
}

/**
 * फोल्डर भित्रका फाइलहरूको सूची।
 *
 * @param string   $dir   फोल्डर।
 * @param string[] $exts  चाहिने extension (खाली भए सबै)।
 * @param int      $max   अधिकतम फाइल।
 * @return string[]
 */
function pkt_list_files( $dir, $exts = array(), $max = 20000 ) {

	$out = array();

	if ( ! is_dir( $dir ) ) {
		return $out;
	}

	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			if ( $exts ) {
				$ext = strtolower( pathinfo( $file->getPathname(), PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $exts, true ) ) {
					continue;
				}
			}
			$out[] = $file->getPathname();
			if ( count( $out ) >= $max ) {
				break;
			}
		}
	} catch ( Exception $e ) {
		return $out;
	}

	sort( $out );

	return $out;
}

/**
 * फाइलहरूमा नमुना खोज्ने (grep)।
 *
 * @param string[] $files   फाइल सूची।
 * @param string   $pattern Regex नमुना।
 * @param int      $limit   अधिकतम नतिजा।
 * @param bool     $negate  true भए नमुना *नभेटिएका* फाइल फर्काउने।
 * @return array
 */
function pkt_grep( $files, $pattern, $limit = 60, $negate = false ) {

	$hits = array();

	foreach ( $files as $file ) {

		$size = @filesize( $file );
		if ( false === $size || $size > 900000 ) {
			continue;
		}

		$lines = @file( $file );
		if ( ! is_array( $lines ) ) {
			continue;
		}

		if ( $negate ) {
			$found = false;
			foreach ( $lines as $line ) {
				if ( preg_match( $pattern, $line ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$hits[] = array(
					'file' => str_replace( pkt_theme_dir(), '', $file ),
					'line' => 0,
					'text' => '(नमुना भेटिएन)',
				);
				if ( count( $hits ) >= $limit ) {
					return $hits;
				}
			}
			continue;
		}

		foreach ( $lines as $i => $line ) {
			if ( preg_match( $pattern, $line ) ) {
				$hits[] = array(
					'file' => str_replace( pkt_theme_dir(), '', $file ),
					'line' => $i + 1,
					'text' => trim( substr( $line, 0, 220 ) ),
				);
				if ( count( $hits ) >= $limit ) {
					return $hits;
				}
			}
		}
	}

	return $hits;
}

/**
 * मानव-पढ्न सकिने फाइल आकार।
 *
 * @param int $bytes बाइट।
 * @return string
 */
function pkt_size( $bytes ) {
	$bytes = (float) $bytes;
	if ( $bytes >= 1048576 ) {
		return round( $bytes / 1048576, 2 ) . ' MB';
	}
	if ( $bytes >= 1024 ) {
		return round( $bytes / 1024, 1 ) . ' KB';
	}
	return (int) $bytes . ' B';
}

/**
 * ब्याकअप फोल्डर बनाउने।
 *
 * @return string|false
 */
function pkt_backup_dir() {

	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return false;
	}

	$dir = $uploads['basedir'] . '/palika-backup-' . gmdate( 'Ymd-His' );

	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	return $dir;
}

/**
 * फाइल ब्याकअप गर्ने (थिम फोल्डर सापेक्ष बाटो कायम राख्दै)।
 *
 * @param string $file   मूल फाइल।
 * @param string $backup ब्याकअप फोल्डर।
 * @return bool
 */
function pkt_backup_file( $file, $backup ) {

	$relative = ltrim( str_replace( pkt_theme_dir(), '', $file ), '/\\' );
	$target   = trailingslashit( $backup ) . $relative;

	wp_mkdir_p( dirname( $target ) );

	return copy( $file, $target );
}

/**
 * मर्मत कार्यहरू — बटन थिचिएमा मात्र चल्ने।
 *
 * @return array चलेका कार्यको नतिजा।
 */
function pkt_run_actions() {

	$result = array(
		'done'   => array(),
		'failed' => array(),
		'backup' => '',
	);

	if ( empty( $_POST['pkt_action'] ) ) {
		return $result;
	}

	check_admin_referer( 'palika_toolkit_action' );

	$action = sanitize_key( wp_unslash( $_POST['pkt_action'] ) );
	$backup = pkt_backup_dir();

	if ( ! $backup ) {
		$result['failed'][] = 'ब्याकअप फोल्डर बनाउन सकिएन (uploads फोल्डरको अनुमति जाँच्नुहोस्)।';
		return $result;
	}
	$result['backup'] = str_replace( ABSPATH, '', $backup );

	// --- (क) ABSPATH guard थप्ने ---
	if ( 'abspath' === $action ) {

		$php_files = pkt_list_files( pkt_theme_dir(), array( 'php' ) );
		$guard     = "if ( ! defined( 'ABSPATH' ) ) {\n\texit; // सिधै खोल्न रोक्ने।\n}\n";

		foreach ( $php_files as $file ) {

			$contents = @file_get_contents( $file );
			if ( false === $contents ) {
				continue;
			}

			if ( false !== strpos( $contents, 'ABSPATH' ) ) {
				continue; // पहिले नै छ।
			}
			if ( false !== strpos( $contents, 'namespace ' ) ) {
				continue; // namespace भएको फाइलमा राख्न मिल्दैन।
			}
			if ( 0 !== strpos( ltrim( $contents ), '<?php' ) ) {
				continue; // असामान्य सुरुवात।
			}
			if ( strlen( $contents ) < 60 ) {
				continue;
			}

			// <?php को ठीक पछि guard थप्ने।
			$new = preg_replace( '/^(\s*<\?php)/', "$1\n" . $guard, $contents, 1, $count );

			if ( $count < 1 || ! is_string( $new ) ) {
				continue;
			}

			if ( ! pkt_backup_file( $file, $backup ) ) {
				$result['failed'][] = 'ब्याकअप बनाउन सकिएन: ' . $file;
				continue;
			}

			if ( false === @file_put_contents( $file, $new ) ) {
				$result['failed'][] = 'लेख्न सकिएन: ' . $file;
				continue;
			}

			$result['done'][] = str_replace( pkt_theme_dir(), '', $file );
		}
	}

	// --- (ख) .DS_Store / dev फाइल हटाउने (ब्याकअप सहित) ---
	if ( 'quarantine' === $action ) {

		$junk = array();
		foreach ( pkt_list_files( pkt_theme_dir() ) as $file ) {
			$base = basename( $file );
			$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( '.DS_Store' === $base || 'ds_store' === strtolower( $base ) ) {
				$junk[] = $file;
			} elseif ( in_array( $ext, array( 'scss', 'map' ), true ) ) {
				$junk[] = $file;
			}
		}

		foreach ( $junk as $file ) {
			if ( ! pkt_backup_file( $file, $backup ) ) {
				$result['failed'][] = 'ब्याकअप बनाउन सकिएन: ' . $file;
				continue;
			}
			if ( ! @unlink( $file ) ) {
				$result['failed'][] = 'हटाउन सकिएन: ' . $file;
				continue;
			}
			$result['done'][] = str_replace( pkt_theme_dir(), '', $file );
		}
	}

	return $result;
}

$pkt_result = pkt_run_actions();

/* ==============================================================
 * स्क्यान
 * ============================================================== */
$theme_dir  = pkt_theme_dir();
$theme_name = wp_get_theme()->get( 'Name' );
$theme_ver  = wp_get_theme()->get( 'Version' );
$all_files  = pkt_list_files( $theme_dir );
$php_files  = pkt_list_files( $theme_dir, array( 'php' ) );

// फाइल आकार तथ्याङ्क।
$total_size = 0;
$by_ext     = array();
$biggest    = array();

foreach ( $all_files as $file ) {
	$size        = (int) @filesize( $file );
	$total_size += $size;
	$ext         = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( '' === $ext ) {
		$ext = '(none)';
	}
	if ( ! isset( $by_ext[ $ext ] ) ) {
		$by_ext[ $ext ] = array( 'count' => 0, 'size' => 0 );
	}
	$by_ext[ $ext ]['count']++;
	$by_ext[ $ext ]['size'] += $size;
	$biggest[ str_replace( $theme_dir, '', $file ) ] = $size;
}

arsort( $biggest );
$biggest = array_slice( $biggest, 0, 15, true );
arsort( $by_ext );

// जाँचहरू।
$checks = array();

$checks['cat_id'] = pkt_grep( $php_files, '/get_cat_ID\s*\(|get_category_link\s*\(/', 60 );
$checks['empty_href'] = pkt_grep( $php_files, '/href\s*=\s*(["\'])\s*\1/', 40 );
$checks['wp_head'] = pkt_grep( $php_files, '/add_action\s*\(\s*(["\'])wp_head\1/', 80 );
$checks['no_abspath'] = null;
$checks['getelementbyid'] = pkt_grep( $php_files, '/getElementById\s*=|AbortError/', 40 );
$checks['danger'] = pkt_grep( $php_files, '/\beval\s*\(|unserialize\s*\(|base64_decode\s*\(|create_function\s*\(/', 40 );
$checks['sql_raw'] = pkt_grep( $php_files, '/\$wpdb->(query|get_var|get_row|get_col|get_results)\s*\(\s*(["\']).*\$/', 40 );
$checks['assets'] = pkt_grep( $php_files, '/logo\.png|logo_\.png|lazy\.png|assets\/images/', 40 );
$checks['enqueue'] = pkt_grep( $php_files, '/wp_enqueue_(script|style)\s*\(/', 80 );
$checks['view_fn'] = pkt_grep( $php_files, '/sandesh_track_views|update_post_meta\s*\(\s*[^,]+,\s*(["\'])[^"\']*view/i', 40 );
$checks['skip_ad'] = pkt_grep( $php_files, '/aria-modal|role\s*=\s*(["\'])dialog/i', 20 );
$checks['pdf_upload'] = pkt_grep( $php_files, '/\$_FILES|move_uploaded_file|wp_check_filetype/', 40 );

$abspath_missing = array();
foreach ( $php_files as $file ) {
	$head = @file_get_contents( $file, false, null, 0, 3000 );
	if ( false === $head ) {
		continue;
	}
	if ( false === strpos( $head, 'ABSPATH' ) ) {
		$abspath_missing[] = str_replace( $theme_dir, '', $file );
	}
}
$checks['no_abspath'] = $abspath_missing;

// मुख्य फाइलहरू छन्/छैनन्।
$expect = array(
	'functions.php',
	'front-page.php',
	'header.php',
	'content.php',
	'headline.php',
	'video.php',
	'single-old.php',
	'single-writters.php',
	'skip.php',
	'assets/css/style.css',
	'assets/js/lazy.js',
	'assets/images/logo.png',
	'assets/images/lazy.png',
);
$exists = array();
foreach ( $expect as $rel ) {
	$exists[ $rel ] = file_exists( $theme_dir . $rel );
}

// base64 मा लुकेका ??? — थप जाँच आवश्यक छैन।

// डाटाबेस जाँच।
global $wpdb;

$views_rows = $wpdb->get_results(
	"SELECT meta_key, COUNT(*) AS total FROM {$wpdb->postmeta} WHERE meta_key LIKE '%view%' GROUP BY meta_key ORDER BY total DESC LIMIT 10"
);

$featured_rows = $wpdb->get_results(
	"SELECT pm.meta_value AS att_id, COUNT(*) AS total
	 FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = '_thumbnail_id' AND p.post_type = 'post' AND p.post_status = 'publish'
	 GROUP BY pm.meta_value ORDER BY total DESC LIMIT 10"
);

$menu_rows = $wpdb->get_results(
	"SELECT post_id, meta_value FROM {$wpdb->postmeta}
	 WHERE meta_key = '_menu_item_url'
	   AND (meta_value LIKE '%www.palikalive.com%' OR meta_value LIKE 'http://%')
	 LIMIT 40"
);

$hardcoded = $wpdb->get_results(
	"SELECT ID, post_title FROM {$wpdb->posts}
	 WHERE post_status = 'publish' AND post_type = 'post'
	   AND (post_content LIKE '%www.palikalive.com%' OR post_content LIKE '%http://palikalive%')
	 LIMIT 15"
);

$no_alt = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} p
	 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
	 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
	   AND (m.meta_value IS NULL OR m.meta_value = '')"
);

$categories = get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => false,
	)
);

$mu_file  = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR . '/palikalive-fixes.php' : '';
$mu_ready = $mu_file && file_exists( $mu_file );

$active_plugins = (array) get_option( 'active_plugins', array() );

$htaccess = ABSPATH . '.htaccess';
$ht_rules = array();
$ht_size  = 0;
if ( file_exists( $htaccess ) ) {
	$ht_size  = (int) @filesize( $htaccess );
	$ht_lines = @file( $htaccess );
	if ( is_array( $ht_lines ) ) {
		foreach ( $ht_lines as $i => $line ) {
			if ( preg_match( '/RewriteRule|RewriteCond|Header\s|Options |redirect/i', $line ) ) {
				$ht_rules[] = ( $i + 1 ) . ': ' . trim( $line );
				if ( count( $ht_rules ) >= 25 ) {
					break;
				}
			}
		}
	}
}

$env = array(
	'WordPress'          => get_bloginfo( 'version' ),
	'PHP'                => PHP_VERSION,
	'Server'             => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'अज्ञात',
	'Memory limit'       => ini_get( 'memory_limit' ),
	'Max execution time' => ini_get( 'max_execution_time' ),
	'थिम'                => $theme_name . ' ' . $theme_ver . ' (' . str_replace( ABSPATH, '', $theme_dir ) . ')',
	'साइट भाषा'          => get_locale(),
	'स्थायी लिङ्क'        => get_option( 'permalink_structure' ),
	'Category base'      => get_option( 'category_base' ) ? get_option( 'category_base' ) : '(खाली)',
	'front page'         => get_option( 'show_on_front' ) . ' / page_on_front=' . (int) get_option( 'page_on_front' ),
	'साइट शीर्षक'        => get_option( 'blogname' ),
	'ट्यागलाइन'           => get_option( 'blogdescription' ),
	'Rank Math'          => ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) ? 'सक्रिय' : 'निष्क्रिय',
	'Yoast'              => defined( 'WPSEO_VERSION' ) ? 'सक्रिय' : 'निष्क्रिय',
	'mu-plugin'          => $mu_ready ? 'जडान भएको छ ✔' : 'जडान भएको छैन',
	'गनिएको views key'    => get_option( 'palika_detected_views_meta_key' ) ? get_option( 'palika_detected_views_meta_key' ) : '(सेट भएको छैन)',
	'सक्रिय प्लगइन'       => count( $active_plugins ) . ' वटा: ' . implode( ', ', array_map( 'basename', $active_plugins ) ),
);

// निर्यात (text) रिपोर्ट।
if ( isset( $_GET['pkt_export'] ) && check_admin_referer( 'palika_toolkit_export' ) ) {

	$lines = array();
	$lines[] = 'Palika Live — टुलकिट रिपोर्ट (' . gmdate( 'Y-m-d H:i' ) . ' UTC)';
	$lines[] = str_repeat( '=', 60 );

	$lines[] = '';
	$lines[] = '== वातावरण ==';
	foreach ( $env as $k => $v ) {
		$lines[] = $k . ': ' . $v;
	}

	$lines[] = '';
	$lines[] = '== थिम ==';
	$lines[] = 'फाइल संख्या: ' . count( $all_files ) . ' | कुल आकार: ' . pkt_size( $total_size );
	foreach ( $by_ext as $ext => $info ) {
		$lines[] = '.$ext : ' . $info['count'] . ' फाइल, ' . pkt_size( $info['size'] );
	}

	$lines[] = '';
	$lines[] = '== ठूला फाइल ==';
	foreach ( $biggest as $rel => $size ) {
		$lines[] = pkt_size( $size ) . ' — ' . $rel;
	}

	foreach ( array(
		'cat_id'     => 'get_cat_ID / get_category_link',
		'empty_href' => 'खाली href',
		'wp_head'    => 'wp_head hooks (थिम)',
		'no_abspath' => 'ABSPATH guard छैन — फाइल सूची',
		'danger'     => 'eval/unserialize/base64_decode',
		'sql_raw'    => 'prepare नभएको SQL (सम्भावित)',
		'view_fn'    => 'भ्यु काउन्टर कोड',
		'pdf_upload' => 'अपलोड/फाइल जाँच कोड',
	) as $key => $label ) {
		$lines[] = '';
		$lines[] = '== ' . $label . ' ==';
		if ( empty( $checks[ $key ] ) ) {
			$lines[] = '(केही भेटिएन)';
			continue;
		}
		foreach ( $checks[ $key ] as $hit ) {
			$lines[] = $hit['file'] . ':' . $hit['line'] . '  ' . $hit['text'];
		}
	}

	$lines[] = '';
	$lines[] = '== श्रेणीहरू (id | नाम | slug | लेख) ==';
	foreach ( $categories as $term ) {
		$lines[] = $term->term_id . ' | ' . $term->name . ' | ' . $term->slug . ' | ' . $term->count;
	}

	$lines[] = '';
	$lines[] = '== views meta key ==';
	foreach ( $views_rows as $row ) {
		$lines[] = $row->meta_key . ' — ' . $row->total . ' रेकर्ड';
	}

	$lines[] = '';
	$lines[] = '== धेरै प्रयोग भएका फिचर्ड इमेज ==';
	foreach ( $featured_rows as $row ) {
		$file = get_attached_file( (int) $row->att_id );
		$lines[] = 'attachment #' . $row->att_id . ' — ' . ( $file ? basename( $file ) : '?' ) . ' — ' . $row->total . ' लेख';
	}

	$lines[] = '';
	$lines[] = '== मेनुमा www / http लिङ्क ==';
	foreach ( $menu_rows as $row ) {
		$lines[] = 'menu item #' . $row->post_id . ' — ' . get_the_title( $row->post_id ) . ' — ' . $row->meta_value;
	}

	$lines[] = '';
	$lines[] = '== लेखभित्र पुरानो डोमेन लिङ्क ==';
	foreach ( $hardcoded as $row ) {
		$lines[] = 'post #' . $row->ID . ' — ' . $row->post_title;
	}

	$lines[] = '';
	$lines[] = 'alt टेक्स्ट नभएका image attachment: ' . $no_alt;

	$lines[] = '';
	$lines[] = '== .htaccess (छानिएका लाइन) ==';
	foreach ( $ht_rules as $rule ) {
		$lines[] = $rule;
	}

	$report = implode( "\n", $lines );

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=palika-report-' . gmdate( 'Ymd-Hi' ) . '.txt' );
	echo $report; // phpcs:ignore
	exit;
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Palika Live — टुलकिट रिपोर्ट</title>
<style>
	body { font-family: system-ui, "Segoe UI", Mukta, Arial, sans-serif; margin: 0; background: #f4f5f7; color: #16181d; }
	.wrap { max-width: 1180px; margin: 0 auto; padding: 20px 16px 60px; }
	h1 { font-size: 22px; }
	h2 { font-size: 17px; margin: 26px 0 8px; padding-bottom: 6px; border-bottom: 2px solid #d8dbe0; }
	table { width: 100%; border-collapse: collapse; background: #fff; font-size: 13px; }
	th, td { border: 1px solid #e2e5e9; padding: 6px 8px; text-align: left; vertical-align: top; }
	th { background: #f0f1f4; }
	code { background: #eef0f3; padding: 1px 4px; border-radius: 3px; }
	.ok { color: #157347; font-weight: 600; }
	.bad { color: #b02a37; font-weight: 600; }
	.warn { color: #a06a00; font-weight: 600; }
	.card { background: #fff; border: 1px solid #e2e5e9; border-radius: 8px; padding: 12px 14px; margin: 12px 0; }
	.btn { display: inline-block; background: #c8102e; color: #fff; padding: 9px 14px; border-radius: 6px; text-decoration: none; border: 0; font-size: 14px; cursor: pointer; }
	.btn.sec { background: #33415c; }
	.note { background: #fff8e5; border-left: 4px solid #f0b429; padding: 10px 12px; font-size: 13px; }
	small { color: #5b6270; }
	ul { margin: 6px 0 6px 20px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Palika Live — अडिट टुलकिट रिपोर्ट</h1>
<p><small>२९ सेप्टेम्बर २०२६ को अडिटलाई यही साइटमा मिलाएर जाँचिएको नतिजा। काम सकिएपछि यो फाइल Delete गर्नुहोस्।</small></p>

<?php if ( isset( $pkt_result['done'] ) && $pkt_result['done'] ) : ?>
	<div class="card">
		<strong class="ok">सम्पन्न भयो (<?php echo count( $pkt_result['done'] ); ?> फाइल)</strong><br />
		ब्याकअप: <code><?php echo esc_html( $pkt_result['backup'] ); ?></code>
		<ul><?php foreach ( array_slice( $pkt_result['done'], 0, 60 ) as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?></ul>
	</div>
<?php endif; ?>
<?php if ( isset( $pkt_result['failed'] ) && $pkt_result['failed'] ) : ?>
	<div class="card"><strong class="bad">समस्या</strong><ul><?php foreach ( $pkt_result['failed'] as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
	<a class="btn sec" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'pkt_export', '1' ), 'palika_toolkit_export' ) ); ?>">पूरा रिपोर्ट डाउनलोड (.txt)</a>
	&nbsp; <a class="btn sec" href="<?php echo esc_url( remove_query_arg( array( 'pkt_export', '_wpnonce' ) ) ); ?>">फेरि स्क्यान</a>
</div>

<h2>१. वातावरण</h2>
<table>
	<?php foreach ( $env as $k => $v ) : ?>
		<tr><th style="width:220px"><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( $v ); ?></td></tr>
	<?php endforeach; ?>
</table>

<h2>२. थिम फाइलहरू</h2>
<?php if ( ! $mu_ready ) : ?>
	<div class="note">
		<strong>mu-plugin जडान भएको छैन।</strong> सुधार प्याक चलाउन
		<code>fixes/mu-plugins/palikalive-fixes.php</code> लाई
		<code>wp-content/mu-plugins/</code> भित्र Upload गर्नुहोस् (फोल्डर छैन भने बनाउनुहोस्)।
	</div>
<?php else : ?>
	<div class="note"><strong class="ok">mu-plugin जडान भएको छ ✔</strong> — <code><?php echo esc_html( str_replace( ABSPATH, '', $mu_file ) ); ?></code>. केही परिवर्तन देखिएन भने फाइल Rename गरी रद्द गर्न सकिन्छ।</div>
<?php endif; ?>

<p>कुल <?php echo (int) count( $all_files ); ?> फाइल, <?php echo esc_html( pkt_size( $total_size ) ); ?></p>

<table>
	<tr><th>प्रकार</th><th>संख्या</th><th>आकार</th></tr>
	<?php foreach ( $by_ext as $ext => $info ) : ?>
		<tr><td>.<?php echo esc_html( $ext ); ?></td><td><?php echo (int) $info['count']; ?></td><td><?php echo esc_html( pkt_size( $info['size'] ) ); ?></td></tr>
	<?php endforeach; ?>
</table>

<h2>३. ठूला फाइल (टप १५)</h2>
<table>
	<?php foreach ( $biggest as $rel => $size ) : ?>
		<tr><td><?php echo esc_html( pkt_size( $size ) ); ?></td><td><code><?php echo esc_html( $rel ); ?></code></td></tr>
	<?php endforeach; ?>
</table>

<h2>४. अपेक्षित फाइलहरू</h2>
<table>
	<tr><th>फाइल</th><th>अवस्था</th></tr>
	<?php foreach ( $exists as $rel => $has ) : ?>
		<tr><td><code><?php echo esc_html( $rel ); ?></code></td>
			<td class="<?php echo $has ? 'ok' : 'bad'; ?>"><?php echo $has ? 'छ' : 'छैन'; ?></td></tr>
	<?php endforeach; ?>
</table>

<h2>५. श्रेणीहरू (अडिट १.१ का "सबै" लिङ्कको मुख्य आधार)</h2>
<p><small>"सबै" लिङ्क बनाउँदा नामको साटो <strong>slug</strong> प्रयोग गर्नुपर्छ — तलको slug स्तम्भ प्रयोग गर्नुहोस्।</small></p>
<table>
	<tr><th>ID</th><th>नाम</th><th>slug</th><th>लेख</th><th>लिङ्क</th></tr>
	<?php if ( ! is_wp_error( $categories ) ) : ?>
		<?php foreach ( $categories as $term ) : ?>
			<tr>
				<td><?php echo (int) $term->term_id; ?></td>
				<td><?php echo esc_html( $term->name ); ?></td>
				<td><code><?php echo esc_html( $term->slug ); ?></code></td>
				<td><?php echo (int) $term->count; ?></td>
				<td><a href="<?php echo esc_url( get_term_link( $term ) ); ?>" target="_blank" rel="noopener">खोल्ने</a></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
</table>

<h2>६. खाली <code>href=""</code> भएका ठाउँ (थिमको कोडमा)</h2>
<?php if ( empty( $checks['empty_href'] ) ) : ?>
	<p class="ok">थिमको PHP मा सिधै खाली href भेटिएन — समस्या get_cat_ID() बाट बनेको हो (तल हेर्नुहोस्)।</p>
<?php else : ?>
	<table><tr><th>फाइल</th><th>लाइन</th><th>कोड</th></tr>
	<?php foreach ( $checks['empty_href'] as $hit ) : ?>
		<tr><td><?php echo esc_html( $hit['file'] ); ?></td><td><?php echo (int) $hit['line']; ?></td><td><code><?php echo esc_html( $hit['text'] ); ?></code></td></tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

<h2>७. <code>get_cat_ID()</code> / <code>get_category_link()</code> प्रयोग</h2>
<p><small>यी लाइनहरू नै अडिट १.१ को जड हुन्। यिनलाई <code>get_term_link( 'slug', 'category' )</code> मा बदल्नुहोस्।</small></p>
<table><tr><th>फाइल</th><th>लाइन</th><th>कोड</th></tr>
	<?php foreach ( $checks['cat_id'] as $hit ) : ?>
		<tr><td><?php echo esc_html( $hit['file'] ); ?></td><td><?php echo (int) $hit['line']; ?></td><td><code><?php echo esc_html( $hit['text'] ); ?></code></td></tr>
	<?php endforeach; ?>
	<?php if ( empty( $checks['cat_id'] ) ) : ?>
		<tr><td colspan="3">केही भेटिएन।</td></tr>
	<?php endif; ?>
</table>

<h2>८. थिमका <code>wp_head</code> hooks (अडिट १.३ — SEO दोहोरो)</h2>
<table><tr><th>फाइल</th><th>लाइन</th><th>कोड</th></tr>
	<?php foreach ( $checks['wp_head'] as $hit ) : ?>
		<tr><td><?php echo esc_html( $hit['file'] ); ?></td><td><?php echo (int) $hit['line']; ?></td><td><code><?php echo esc_html( $hit['text'] ); ?></code></td></tr>
	<?php endforeach; ?>
	<?php if ( empty( $checks['wp_head'] ) ) : ?>
		<tr><td colspan="3">केही भेटिएन।</td></tr>
	<?php endif; ?>
</table>

<h2>९. भ्यु-काउन्टर कोड (अडिट १.४)</h2>
<table><tr><th>फाइल</th><th>लाइन</th><th>कोड</th></tr>
	<?php foreach ( $checks['view_fn'] as $hit ) : ?>
		<tr><td><?php echo esc_html( $hit['file'] ); ?></td><td><?php echo (int) $hit['line']; ?></td><td><code><?php echo esc_html( $hit['text'] ); ?></code></td></tr>
	<?php endforeach; ?>
	<?php if ( empty( $checks['view_fn'] ) ) : ?>
		<tr><td colspan="3">केही भेटिएन।</td></tr>
	<?php endif; ?>
</table>

<table style="margin-top:8px">
	<tr><th>postmeta key (नाममा "view")</th><th>कति रेकर्ड</th></tr>
	<?php foreach ( $views_rows as $row ) : ?>
		<tr><td><code><?php echo esc_html( $row->meta_key ); ?></code></td><td><?php echo (int) $row->total; ?></td></tr>
	<?php endforeach; ?>
	<?php if ( empty( $views_rows ) ) : ?>
		<tr><td colspan="2">कुनै भेटिएन।</td></tr>
	<?php endif; ?>
</table>
<p><small>यही key लाई mu-plugin को <code>views_meta_key</code> मा राखिदिनुहोस्, नत्र गन्ती नयाँ key मा जान्छ।</small></p>

<h2>१०. security / hardening संकेत</h2>
<table>
	<tr><th>जाँच</th><th>नतिजा</th></tr>
	<tr><td>eval / unserialize / base64_decode</td><td><?php echo $checks['danger'] ? esc_html( count( $checks['danger'] ) . ' ठाउँ — माथिको जस्तै सूची (निर्यात रिपोर्टमा)' ) : '<span class="ok">केही भेटिएन</span>'; ?></td></tr>
	<tr><td>prepare नभएको SQL (सम्भावित)</td><td><?php echo $checks['sql_raw'] ? esc_html( count( $checks['sql_raw'] ) . ' ठाउँ — म्यानुअल जाँच गर्नुहोस् (निर्यात रिपोर्टमा)' ) : '<span class="ok">केही भेटिएन</span>'; ?></td></tr>
	<tr><td>PDF/फाइल अपलोड कोड</td><td><?php echo esc_html( count( $checks['pdf_upload'] ) . ' ठाउँ भेटियो' ); ?></td></tr>
	<tr><td>ABSPATH guard नभएका PHP फाइल</td><td class="<?php echo $abspath_missing ? 'bad' : 'ok'; ?>"><?php echo (int) count( $abspath_missing ); ?> फाइल</td></tr>
	<tr><td>अपलोड गरिएका तस्बिरमध्ये alt नभएका</td><td><?php echo (int) $no_alt; ?> attachment</td></tr>
	<tr><td>.htaccess</td><td><?php echo $ht_size ? esc_html( 'छ, ' . pkt_size( $ht_size ) ) : 'छैन (Nginx हो भने सामान्य)'; ?></td></tr>
</table>

<?php if ( $abspath_missing ) : ?>
	<div class="card">
		<strong>सुरक्षित मर्मत: ABSPATH guard थप्ने</strong>
		<ul><?php foreach ( array_slice( $abspath_missing, 0, 40 ) as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?>
		<?php if ( count( $abspath_missing ) > 40 ) : ?><li>… र <?php echo (int) ( count( $abspath_missing ) - 40 ); ?> थप</li><?php endif; ?></ul>
		<form method="post" onsubmit="return confirm('ब्याकअप बनाएर लगभग <?php echo (int) count( $abspath_missing ); ?> फाइलमा guard थपिनेछ। अगाडि बढ्ने?');">
			<?php wp_nonce_field( 'palika_toolkit_action' ); ?>
			<input type="hidden" name="pkt_action" value="abspath" />
			<button class="btn" type="submit">ब्याकअप सहित guard थप्नुहोस्</button>
		</form>
		<p><small>ब्याकअप: <code>wp-content/uploads/palika-backup-…/</code> — फिर्ता ल्याउन त्यही फाइल कपी गर्नुहोस्।</small></p>
	</div>
<?php endif; ?>

<div class="card">
	<strong>सुरक्षित मर्मत: .DS_Store / SCSS / source map हटाउने (ब्याकअप सहित)</strong>
	<p><small>यी फाइल वेबसाइट चलाउन आवश्यक पर्दैनन्; थिम फोल्डर हल्का हुन्छ।</small></p>
	<form method="post" onsubmit="return confirm('यी dev फाइलहरू ब्याकअपमा सारिनेछन् (मेटिने छैन)। अगाडि बढ्ने?');">
		<?php wp_nonce_field( 'palika_toolkit_action' ); ?>
		<input type="hidden" name="pkt_action" value="quarantine" />
		<button class="btn" type="submit">ब्याकअपमा सार्नुहोस्</button>
	</form>
</div>

<h2>११. धेरै प्रयोग भएका फिचर्ड इमेज (अडिट ३ — लोगो मुद्दा)</h2>
<table><tr><th>attachment</th><th>फाइल</th><th>कति लेखमा</th><th></th></tr>
	<?php foreach ( $featured_rows as $row ) : ?>
		<?php $file = get_attached_file( (int) $row->att_id ); ?>
		<tr>
			<td>#<?php echo (int) $row->att_id; ?></td>
			<td><?php echo esc_html( $file ? basename( $file ) : '?' ); ?></td>
			<td><?php echo (int) $row->total; ?></td>
			<td><a target="_blank" rel="noopener" href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row->att_id . '&action=edit' ) ); ?>">सम्पादन</a></td>
		</tr>
	<?php endforeach; ?>
</table>
<p><small>यहाँ लोगो (logo_.png) माथि देखिए = अडिटको बुँदा सही हो; ती लेखहरूमा वास्तविक फोटो राख्नुपर्छ।</small></p>

<h2>१२. मेनुमा पुरानो डोमेन / http लिङ्क</h2>
<table><tr><th>मेनु आइटम</th><th>लेबल</th><th>URL</th></tr>
	<?php foreach ( $menu_rows as $row ) : ?>
		<tr>
			<td>#<?php echo (int) $row->post_id; ?></td>
			<td><?php echo esc_html( get_the_title( $row->post_id ) ); ?></td>
			<td><code><?php echo esc_html( $row->meta_value ); ?></code></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( empty( $menu_rows ) ) : ?>
		<tr><td colspan="3" class="ok">सफा छ ✔</td></tr>
	<?php endif; ?>
</table>
<p><small>सुधार: Appearance → Menus मा गएर यी आइटमको URL <code>https://palikalive.com/...</code> बनाउनुहोस्।</small></p>

<?php if ( $hardcoded ) : ?>
	<h2>१३. लेखभित्र पुरानो डोमेन लिङ्क भएका पोस्ट</h2>
	<ul><?php foreach ( $hardcoded as $row ) : ?>
		<li><a target="_blank" rel="noopener" href="<?php echo esc_url( get_edit_post_link( $row->ID ) ); ?>"><?php echo esc_html( wp_trim_words( $row->post_title, 12 ) ); ?></a></li>
	<?php endforeach; ?></ul>
<?php endif; ?>

<h2>१४. .htaccess (छानिएका लाइन)</h2>
<pre style="background:#fff;border:1px solid #e2e5e9;padding:10px;overflow:auto;font-size:12px"><?php echo esc_html( $ht_rules ? implode( "\n", $ht_rules ) : 'Rewrite/Header नियम भेटिएन।' ); ?></pre>

<h2>१५. dev / SEO जाँचका थप संकेत</h2>
<table>
	<tr><th>जाँच</th><th>नतिजा</th></tr>
	<tr><td>header.php मा getElementById override</td><td><?php echo $checks['getelementbyid'] ? esc_html( count( $checks['getelementbyid'] ) . ' ठाउँ भेटियो' ) : 'भेटिएन'; ?></td></tr>
	<tr><td>इन्क्युइ गरिएका script/style</td><td><?php echo (int) count( $checks['enqueue'] ); ?> लाइन (निर्यात रिपोर्टमा सूची)</td></tr>
	<tr><td>लोगो/placeholder asset सन्दर्भ</td><td><?php echo (int) count( $checks['assets'] ); ?> लाइन (निर्यात रिपोर्टमा सूची)</td></tr>
	<tr><td>aria-modal / dialog (interstitial)</td><td><?php echo $checks['skip_ad'] ? esc_html( count( $checks['skip_ad'] ) . ' ठाउँ' ) : 'भेटिएन'; ?></td></tr>
</table>

<p style="margin-top:30px"><small>काम सकिएपछि यो फाइल Delete गर्न नबिर्सनुहोस्। रिपोर्ट निर्यात गरेर राख्नुहोस्।</small></p>

</div>
</body>
</html>
