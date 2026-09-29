<?php
/**
 * Plugin Name: Palika Live — सुधार प्याक (mu-plugin)
 * Description: २९ सेप्टेम्बर २०२६ को अडिटमा उल्लेखित समस्या थिम फाइल नछोई (धेरैजसो) समाधान गर्ने ब्लकहरू। Mu-plugin भएकोले स्वतः सक्रिय हुन्छ — Plugins सूचीमा देखिँदैन।
 * Version:     1.0.0
 * Author:      Palika Live
 *
 * ------------------------------------------------------------------
 * स्थापना (cPanel बाट):
 *   १) File Manager → public_html/wp-content/ खोल्नुहोस्
 *   २) यदि mu-plugins फोल्डर छैन भने +Folder ले "mu-plugins" बनाउनुहोस् (सानो अक्षर)
 *   ३) यो फाइल mu-plugins भित्र Upload गर्नुहोस्
 *   ४) होमपेज र एउटा लेख Refresh गरेर जाँच्नुहोस्
 *
 * कुनै ब्लक मन परेन भने तलको PALIKA_FIX_CONFIG एरेमा false गर्नुहोस्, वा
 * यो फाइल rename/delete गर्दा सबै परिवर्तन तुरुन्तै रद्द हुन्छ। (जोखिम कम)
 * ------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // सिधै यो फाइल खोल्ने प्रयास रोक्ने।
}

/* ==============================================================
 * १) सेटिङ — कुन सुधार चालु हुने
 * ============================================================== */
if ( ! defined( 'PALIKA_FIX_CONFIG' ) ) {
	define(
		'PALIKA_FIX_CONFIG',
		array(

			// खाली href="" लाई घरपृष्ठ लिङ्कमा बदल्ने (सुरक्षा जाल; मुख्य समाधान थिम फाइलमै गर्नुपर्छ)
			'empty_href'              => true,

			// होमपेजमा h1 नभएमा एउटा h1 थप्ने
			'h1_front_page'           => true,

			// लेखको मुख्य तस्बिर <head> मा preload गर्ने (LCP सुधार)
			'lcp_preload'             => true,

			// थिमको wp_head भ्यु-काउन्टर हटाएर REST beacon चलाउने (DB write कम गर्ने)
			'views_beacon'            => true,

			// AJAX बाट प्रकाशित नभएको पोस्टको सामग्री चुहिन रोक्ने
			'ajax_guard'              => true,

			// ==== तलका दुई वटा पहिले परीक्षण गरेर मात्र true गर्नुहोस् ====

			// जालसाजी (JS) नचल्ने अवस्थामा पनि तस्बिर देखिने गरी native lazy loading प्रयोग गर्ने
			'native_lazy'             => false,

			// इन्टरस्टिसियल विज्ञापन सेसनमा एकै पटक मात्र देखाउने (CSS selector तल भर्नुपर्छ)
			'interstitial_once'       => false,
			'interstitial_selectors'  => '', // उदाहरण: '#skip-overlay, .skip-ad, .intro-ad'

			// wp_head बाट हटाउनुपर्ने थिम फंक्सनका नाम (अडिट १.३) — टुलकिटले नाम दिन्छ
			// उदाहरण: 'palika_seo_meta, sandesh_og_tags'
			'head_callbacks_to_remove' => '',

			// भ्यु-काउन्टर कुन meta key मा लेख्ने। 'auto' = postmeta मा "view" भएको key स्वतः खोज्ने।
			// सुझाव: टुलकिटको रिपोर्टमा देखिएको key यहाँ ठ्याक्कै लेख्दा गन्ती पुरानै ठाउँमा जान्छ।
			'views_meta_key'          => 'auto',

			// व्यवस्थापक (admin) को भ्रमण गन्दै नगर्ने
			'skip_admin_views'        => true,

			// एउटै पाठकले एउटै लेख १२ घण्टामा एक पटक मात्र गनिने
			'view_dedupe_hours'       => 12,
		)
	);
}

/**
 * सेटिङ पढ्ने सहयोगी।
 *
 * @param string $key     सेटिङको नाम।
 * @param mixed  $default नभेटिए फर्काउने मान।
 * @return mixed
 */
function palika_fix_cfg( $key, $default = null ) {
	$cfg = PALIKA_FIX_CONFIG;
	if ( array_key_exists( $key, $cfg ) ) {
		return $cfg[ $key ];
	}
	return $default;
}

/* ==============================================================
 * २) HTML बफर — खाली href + होमपेज h1 (+ वैकल्पिक lazy)
 * ============================================================== */
/**
 * सामुख्य HTML पोस्ट-प्रोसेस गर्ने बफर सुरु गर्ने।
 */
add_action( 'template_redirect', 'palika_fix_maybe_start_buffer', 1 );

function palika_fix_maybe_start_buffer() {

	// प्रशासन, AJAX, cron, REST, फिड, robots, preview → कुनै छेडछाड नगर्ने।
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( is_feed() || is_embed() || is_preview() || is_404() || is_search() ) {
		return;
	}
	if ( function_exists( 'is_robots' ) && is_robots() ) {
		return;
	}
	if ( function_exists( 'is_sitemap' ) && is_sitemap() ) {
		return;
	}
	if ( function_exists( 'is_trackback' ) && is_trackback() ) {
		return;
	}

	$need = palika_fix_cfg( 'empty_href' ) || palika_fix_cfg( 'h1_front_page' ) || palika_fix_cfg( 'native_lazy' );
	if ( ! $need ) {
		return;
	}

	ob_start( 'palika_fix_process_html' );
}

/**
 * बफर कलब्याक — पूरा HTML पाएर आवश्यक सुधार गर्छ।
 *
 * @param string $html पूरा पेजको HTML।
 * @return string
 */
function palika_fix_process_html( $html ) {

	// छोटो वा HTML नभएको जवाफ (JSON, XML, redirect) छोड्ने।
	if ( ! is_string( $html ) || strlen( $html ) < 500 ) {
		return $html;
	}
	if ( false === stripos( $html, '<html' ) ) {
		return $html;
	}

	// ---- २.१ खाली href="" (अडिट १.१) ----
	if ( palika_fix_cfg( 'empty_href' ) ) {
		if ( false !== strpos( $html, 'href=""' ) || false !== strpos( $html, "href=''" ) ) {
			// खाली मान भएका <a ... href="" ...> लाई घरपृष्ठमा लैजाने।
			$html = preg_replace(
				'/<a\b([^>]*?)\shref=(["\'])\s*\2/i',
				'<a$1 href="/"',
				$html
			);
		}
	}

	// ---- २.२ होमपेजको h1 (अडिट १.२) ----
	if ( palika_fix_cfg( 'h1_front_page' ) && ( is_home() || is_front_page() ) ) {
		if ( false === stripos( $html, '<h1' ) ) {
			$html = palika_fix_insert_h1( $html );
		}
	}

	// ---- २.३ native lazy loading (वैकल्पिक) ----
	if ( palika_fix_cfg( 'native_lazy' ) ) {
		$html = palika_fix_images( $html );
	}

	return $html;
}

/**
 * पेजमा h1 थप्ने (पहिलो <main>, नभए #main-content, नभए <body> पछि)।
 *
 * @param string $html पेजको HTML।
 * @return string
 */
function palika_fix_insert_h1( $html ) {

	$site_name = get_bloginfo( 'name' );
	$tagline   = get_bloginfo( 'description' );

	$text = $site_name;
	if ( $tagline ) {
		$text .= ' — ' . $tagline;
	}

	$h1 = '<h1 class="palika-home-h1">' . esc_html( $text ) . '</h1>';

	// preg_replace को replacement भित्र $ र \ विशेष अक्षर हुन् — सुरक्षित बनाउने।
	$safe = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $h1 );

	$count = 0;
	$out   = preg_replace( '/(<main\b[^>]*>)/i', '$1' . $safe, $html, 1, $count );

	if ( $count < 1 ) {
		$out   = preg_replace( '/(<div\b[^>]*id=["\']main-content["\'][^>]*>)/i', '$1' . $safe, $html, 1, $count );
	}
	if ( $count < 1 ) {
		$out = preg_replace( '/(<body\b[^>]*>)/i', '$1' . $safe, $html, 1, $count );
	}

	if ( $count > 0 && is_string( $out ) ) {
		return $out;
	}
	return $html;
}

/**
 * तस्बिरहरूमा loading/decoding थप्ने र lazy placeholder लाई सक्कली URL मा बदल्ने।
 *
 * @param string $html पेजको HTML।
 * @return string
 */
function palika_fix_images( $html ) {

	$first = true;

	$out = preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) use ( &$first ) {

			$tag = $m[0];

			// पहिलो तस्बिर (प्रायः LCP) — lazy नगर्ने, बरु प्राथमिकता दिने।
			if ( $first ) {
				$first = false;
				if ( false === stripos( $tag, 'fetchpriority' ) ) {
					$tag = preg_replace( '/^<img/i', '<img fetchpriority="high"', $tag, 1 );
				}
				return $tag;
			}

			// lazy.png जस्ता placeholder भए data-src को सक्कली URL राख्ने।
			if ( false !== stripos( $tag, 'lazy.png' ) || false !== stripos( $tag, 'lazyload' ) ) {
				if ( preg_match( '/\sdata-(?:lazy-)?src\s*=\s*(["\'])(.+?)\1/i', $tag, $mm ) ) {
					$real = $mm[2];
					if ( '' !== $real ) {
						$tag = preg_replace_callback(
							'/(\ssrc\s*=\s*)(["\'])(.*?)\2/i',
							function ( $x ) use ( $real ) {
								return $x[1] . $x[2] . $real . $x[2];
							},
							$tag,
							1
						);
					}
				}
			}

			if ( false === stripos( $tag, 'loading=' ) ) {
				$tag = preg_replace( '/^<img/i', '<img loading="lazy"', $tag, 1 );
			}
			if ( false === stripos( $tag, 'decoding=' ) ) {
				$tag = preg_replace( '/^<img/i', '<img decoding="async"', $tag, 1 );
			}

			return $tag;
		},
		$html
	);

	return is_string( $out ) ? $out : $html;
}

/* ==============================================================
 * ३) होमपेज h1 को शैली (CSS) — थिमको Additional CSS नछोई राम्रो देखिने
 * ============================================================== */
add_action( 'wp_head', 'palika_fix_h1_css', 99 );

function palika_fix_h1_css() {

	if ( ! palika_fix_cfg( 'h1_front_page' ) || ! ( is_home() || is_front_page() ) ) {
		return;
	}

	echo '<style id="palika-home-h1-css">'
		. '.palika-home-h1{margin:10px 0 14px;padding:8px 12px;font-size:16px;line-height:1.5;font-weight:700;'
		. 'color:#1a1a1a;background:#f5f5f5;border-left:4px solid #c8102e;}'
		. '</style>' . "\n";
}

/* ==============================================================
 * ४) लेखको मुख्य तस्बिर preload (LCP)
 * ============================================================== */
add_action( 'wp_head', 'palika_fix_lcp_preload', 2 );

function palika_fix_lcp_preload() {

	if ( ! palika_fix_cfg( 'lcp_preload' ) || ! is_singular( 'post' ) ) {
		return;
	}

	$thumb_id = get_post_thumbnail_id();
	if ( ! $thumb_id ) {
		return;
	}

	$src = wp_get_attachment_image_src( $thumb_id, 'large' );
	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return;
	}

	echo '<link rel="preload" as="image" href="' . esc_url( $src[0] ) . '" fetchpriority="high" />' . "\n";
}

/* ==============================================================
 * ५) भ्यु-काउन्टर (अडिट १.४): थिमको प्रत्येक-भ्यु DB write हटाउने + REST beacon
 * ============================================================== */
/**
 * थिमले wp_head मा जोडेको भ्यु-काउन्टर हटाउने।
 */
add_action( 'init', 'palika_fix_remove_theme_view_counter', 99 );

function palika_fix_remove_theme_view_counter() {

	if ( ! palika_fix_cfg( 'views_beacon' ) ) {
		return;
	}

	global $wp_filter;

	$names = array( 'sandesh_track_views', 'palika_track_views', 'track_post_views', 'set_post_views' );

	foreach ( array( 'wp_head', 'wp' ) as $hook ) {

		if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) {
			continue;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = isset( $cb['function'] ) ? $cb['function'] : null;
				if ( is_string( $fn ) && in_array( $fn, $names, true ) ) {
					remove_action( $hook, $fn, $priority );
					// साइड इफेक्ट: थिम पुनः जोड्न सक्छ, त्यसैले फेरि हटाउने।
					remove_action( $hook, $fn, $priority );
				}
			}
		}
	}
}

/**
 * भ्यु गन्न प्रयोग हुने meta key पत्ता लगाउने (एकै पटक, नतिजा option मा राखिन्छ)।
 *
 * @return string
 */
function palika_fix_views_meta_key() {

	$configured = palika_fix_cfg( 'views_meta_key', 'auto' );
	if ( $configured && 'auto' !== $configured ) {
		return (string) $configured;
	}

	$detected = get_option( 'palika_detected_views_meta_key' );
	if ( $detected ) {
		return (string) $detected;
	}

	global $wpdb;

	// धेरै पोस्टमा प्रयोग भएको, नाममा "view" भएको meta key खोज्ने (read-only)।
	$rows = $wpdb->get_results(
		"SELECT meta_key, COUNT(*) AS total FROM {$wpdb->postmeta} WHERE meta_key LIKE '%view%' GROUP BY meta_key ORDER BY total DESC LIMIT 5"
	);

	$key = '';
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( ! empty( $row->meta_key ) && false !== stripos( $row->meta_key, 'view' ) ) {
				$key = $row->meta_key;
				break;
			}
		}
	}

	if ( ! $key ) {
		$key = '_palika_views';
	}

	update_option( 'palika_detected_views_meta_key', $key, false );

	return $key;
}

/**
 * REST मार्ग दर्ता — POST /wp-json/palika/v1/view
 */
add_action( 'rest_api_init', 'palika_fix_register_view_route' );

function palika_fix_register_view_route() {

	if ( ! palika_fix_cfg( 'views_beacon' ) ) {
		return;
	}

	register_rest_route(
		'palika/v1',
		'/view',
		array(
			'methods'             => 'POST',
			'callback'            => 'palika_fix_record_view',
			'permission_callback' => '__return_true', // सार्वजनिक लेख गन्ने — नन्स चाहिएन (dedupe ले जोगाउँछ)।
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $value ) {
						return is_numeric( $value );
					},
				),
			),
		)
	);
}

/**
 * भ्यु रेकर्ड गर्ने — bot फिल्टर + १२ घण्टे dedupe सहित।
 *
 * @param WP_REST_Request $request अनुरोध।
 * @return WP_REST_Response
 */
function palika_fix_record_view( $request ) {

	$post_id = absint( $request->get_param( 'id' ) );
	$post    = $post_id ? get_post( $post_id ) : null;

	// प्रकाशित लेख नभए बेवास्ता (draft/preview/bot)।
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( '' === $ua || palika_fix_is_bot( $ua ) ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	if ( palika_fix_cfg( 'skip_admin_views' ) && current_user_can( 'manage_options' ) ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) $_SERVER['HTTP_CF_CONNECTING_IP'] : '';
	if ( '' === $ip ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}

	$dedupe_hours = (int) palika_fix_cfg( 'view_dedupe_hours', 12 );
	if ( $dedupe_hours < 1 ) {
		$dedupe_hours = 12;
	}

	$key = 'pkv_' . md5( $post_id . '|' . $ip . '|' . $ua );
	if ( get_transient( $key ) ) {
		return new WP_REST_Response( array( 'recorded' => false, 'duplicate' => true ), 200 );
	}
	set_transient( $key, 1, $dedupe_hours * HOUR_IN_SECONDS );

	$meta_key = palika_fix_views_meta_key();
	$current  = (int) get_post_meta( $post_id, $meta_key, true );

	update_post_meta( $post_id, $meta_key, $current + 1 );

	return new WP_REST_Response( array( 'recorded' => true, 'key' => $meta_key ), 200 );
}

/**
 * साधारण bot/क्राउलर पहिचान।
 *
 * @param string $ua User agent।
 * @return bool
 */
function palika_fix_is_bot( $ua ) {

	$ua = strtolower( $ua );

	$needles = array(
		'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python', 'java/',
		'facebookexternalhit', 'preview', 'monitor', 'pingdom', 'uptime',
		'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'headless',
		'lighthouse', 'gtmetrix', 'pagespeed', 'whatsapp', 'telegram',
		'viber', 'line/', 'skype', 'yahoo', 'baidu', 'yandex', 'sogou',
	);

	foreach ( $needles as $needle ) {
		if ( false !== strpos( $ua, $needle ) ) {
			return true;
		}
	}
	return false;
}

/**
 * लेख पढ्दा चल्ने सानो JS — ५ सेकेन्ड पछि REST मा भ्यु पठाउने।
 */
add_action( 'wp_footer', 'palika_fix_view_beacon', 99 );

function palika_fix_view_beacon() {

	if ( ! palika_fix_cfg( 'views_beacon' ) || ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$endpoint = esc_url_raw( rest_url( 'palika/v1/view' ) );
	?>
<script id="palika-view-beacon">
(function () {
	var url = <?php echo wp_json_encode( $endpoint ); ?>;
	var id  = <?php echo (int) $post_id; ?>;
	var done = false;
	function send() {
		if (done || !url) { return; }
		done = true;
		var data = new FormData();
		data.append('id', id);
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(url, data)) { return; }
		} catch (e) {}
		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url, true);
			xhr.send(data);
		} catch (e) {}
	}
	function later() {
		if (document.visibilityState && 'visible' !== document.visibilityState) { return; }
		window.setTimeout(send, 5000); // ५ सेकेन्ड नहेरी बाहिरिएको भ्रमण नगन्ने
	}
	window.addEventListener('load', later, false);
	document.addEventListener('visibilitychange', later, false);
}());
</script>
	<?php
}

/* ==============================================================
 * ६) AJAX सुरक्षा (अडिट ५): गैर-प्रकाशित पोस्टको सामग्री चुहिन रोक्ने
 * ============================================================== */
add_action( 'wp_ajax_nepalitheme_oldpost_html', 'palika_fix_guard_oldpost_ajax', 0 );

function palika_fix_guard_oldpost_ajax() {

	if ( ! palika_fix_cfg( 'ajax_guard' ) ) {
		return;
	}

	$post_id = 0;
	foreach ( array( 'post_id', 'postid', 'post', 'id', 'pid' ) as $field ) {
		if ( ! empty( $_REQUEST[ $field ] ) && is_numeric( $_REQUEST[ $field ] ) ) {
			$post_id = absint( $_REQUEST[ $field ] );
			break;
		}
	}

	if ( ! $post_id ) {
		return; // थिमले आफ्नै तरिकाले ह्यान्डल गरोस्।
	}

	$status = get_post_status( $post_id );
	if ( false === $status ) {
		return; // पोस्ट भेटिएन — थिमको कोड चलोस्।
	}

	if ( 'publish' === $status ) {
		return; // प्रकाशित लेख सबैले हेर्न पाउने — रोक्दैनौँ।
	}

	if ( current_user_can( 'edit_post', $post_id ) ) {
		return; // सम्पादकले आफ्नो ड्राफ्ट हेर्न पाउने।
	}

	wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
}

/* ==============================================================
 * ७) थिमका wp_head फंक्सन हटाउने (अडिट १.३ — SEO दोहोरो हटाउन)
 * ---------------------------------------------------------------
 * टुलकिटको स्क्यानले देखाएको नाम यहाँ (PALIKA_FIX_CONFIG →
 * head_callbacks_to_remove) राख्नुहोस्। उदाहरण:
 *   'head_callbacks_to_remove' => 'palika_seo_meta, sandesh_og_tags',
 * ============================================================== */
add_action( 'wp_head', 'palika_fix_remove_head_callbacks', 0 );

function palika_fix_remove_head_callbacks() {

	$list = (string) palika_fix_cfg( 'head_callbacks_to_remove', '' );
	if ( '' === trim( $list ) ) {
		return;
	}

	$names = array_filter( array_map( 'trim', explode( ',', $list ) ) );
	if ( ! $names ) {
		return;
	}

	global $wp_filter;
	if ( empty( $wp_filter['wp_head'] ) || empty( $wp_filter['wp_head']->callbacks ) ) {
		return;
	}

	foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {

			$fn   = isset( $cb['function'] ) ? $cb['function'] : null;
			$name = '';

			if ( is_string( $fn ) ) {
				$name = $fn;
			} elseif ( is_array( $fn ) && isset( $fn[1] ) && is_string( $fn[1] ) ) {
				$name = $fn[1];
			}

			if ( '' !== $name && in_array( $name, $names, true ) ) {
				remove_action( 'wp_head', $fn, $priority );
			}
		}
	}
}

/**
 * SEO प्लगइन सक्रिय छ कि छैन जाँच्ने सहयोगी (थिमको कोडले पनि प्रयोग गर्न सकोस्)।
 *
 * @return bool
 */
if ( ! function_exists( 'palika_seo_plugin_active' ) ) {
	function palika_seo_plugin_active() {
		return (
			defined( 'RANK_MATH_VERSION' )        // Rank Math
			|| class_exists( 'RankMath' )
			|| defined( 'WPSEO_VERSION' )         // Yoast
			|| defined( 'RANK_MATH_FILE' )        // Rank Math (केही संस्करण)
			|| class_exists( 'RankMath\Helper' )
		);
	}
}

/* ==============================================================
 * ८) इन्टरस्टिसियल विज्ञापन — सेसनमा एकै पटक (वैकल्पिक)
 * ============================================================== */
add_action( 'wp_footer', 'palika_fix_interstitial_once', 100 );

function palika_fix_interstitial_once() {

	if ( ! palika_fix_cfg( 'interstitial_once' ) ) {
		return;
	}

	$selectors = trim( (string) palika_fix_cfg( 'interstitial_selectors', '' ) );
	if ( '' === $selectors ) {
		return;
	}
	?>
<script id="palika-interstitial-once">
(function () {
	var sel = <?php echo wp_json_encode( $selectors ); ?>;
	var key = 'palika_intro_seen';
	var seen = false;
	try { seen = '1' === window.sessionStorage.getItem(key); } catch (e) {}
	if (seen) {
		try {
			Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
				el.parentNode && el.parentNode.removeChild(el); // पूरै हटाउने (स्क्रोल लक पनि मुक्त)
			});
			document.documentElement.style.overflow = '';
			if (document.body) { document.body.style.overflow = ''; }
		} catch (e) {}
		return;
	}
	try { window.sessionStorage.setItem(key, '1'); } catch (e) {}
}());
</script>
	<?php
}
