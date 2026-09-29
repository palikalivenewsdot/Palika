<?php
/**
 * Plugin Name:       Palika Live - Technical Fix Pack
 * Description:       Applies the fixes from the Palika Live technical audit (29 Sep 2026) without editing any theme file: empty section links, homepage H1, view-counter beacon, SEO duplication guard, menu URL cleanup, image/link accessibility, AJAX draft protection, login throttle.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Palika Live
 * License:           GPL-2.0-or-later
 *
 * ==========================================================================
 * INSTALL (cPanel)
 * ==========================================================================
 * 1. cPanel > File Manager > public_html/wp-content/
 * 2. If there is no "mu-plugins" folder, create it (lowercase, exact name).
 * 3. Upload this file inside it:  wp-content/mu-plugins/palikalive-fixes.php
 * 4. Reload the homepage and one article. That is all - no activation needed.
 *
 * ROLLBACK
 * ========
 * Rename or delete this file. Every change disappears immediately.
 *
 * TURN A SINGLE FIX OFF
 * =====================
 * Set its flag to false in PALIKA_FIX_CONFIG below.
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Block direct access.
}

/* ==========================================================================
 * 1. CONFIGURATION
 * ========================================================================== */
if ( ! defined( 'PALIKA_FIX_CONFIG' ) ) {
	define(
		'PALIKA_FIX_CONFIG',
		array(

			/* ---- Front-end HTML repairs ---- */

			// Repair href="" links. If the link sits under a section heading listed
			// in empty_link_map it points to that category, otherwise to the homepage.
			'repair_empty_links'     => true,

			// Add one H1 to the homepage (only when the page has none).
			'home_h1'                => true,
			'home_h1_text'           => '', // Empty = "Site name - Tagline".

			// Add loading/decoding attributes and fix lazy.png placeholders.
			// Enable AFTER LiteSpeed Cache > Lazy Load Images is ON.
			'native_lazy_images'     => false,

			// Accessibility: aria-label on image-only links, alt="" on images without alt.
			'image_accessibility'    => true,

			// Preload the featured image of an article (LCP) and add fetchpriority.
			'lcp_image_preload'      => true,

			// Warm up connections used on every page (image CDN + font host).
			'preconnect_hosts'       => array(
				'https://ec848qqjgie.exactdn.com',
				'https://fonts.gstatic.com',
			),

			/* ---- View counter (audit 1.4) ---- */

			// Remove the theme's per-view database write and count views with a
			// JavaScript beacon instead (bot filtered, one count per reader per 12h).
			'view_counter_beacon'    => true,
			'views_meta_key'         => 'auto', // 'auto' or the exact key your theme uses.
			'view_dedupe_hours'      => 12,
			'skip_admin_views'       => true,

			/* ---- SEO (audit 1.3) ---- */

			// When Rank Math (or Yoast) is active, stop the theme from printing a
			// second set of meta / canonical / Open Graph / schema tags.
			'seo_dedupe'             => true,
			'seo_head_callbacks'     => 'auto', // 'auto' or 'function_a, function_b'.

			// Add <meta name="robots" content="max-image-preview:large">
			// (Google Discover requirement) only when no SEO plugin is active.
			'robots_meta_fallback'   => true,

			/* ---- Site behaviour ---- */

			// Rewrite menu links that use http:// or www.palikalive.com (redirect hops).
			'fix_menu_urls'          => true,

			// 301 redirect /home/ to the front page - before and after page removal.
			'redirect_home_page'     => true,

			// Block AJAX requests that would return unpublished post content.
			'ajax_draft_guard'       => true,

			// Show the full-screen ad only once per browser session.
			// Fill interstitial_selectors with the real CSS selector(s) first.
			'interstitial_once'      => false,
			'interstitial_selectors' => '', // Example: '#skip, .skip-ad, .intro-ad'.

			// Block brute-force logins (10 failed attempts / 15 minutes per IP).
			'login_throttle'         => false,
			'login_max_attempts'     => 10,
			'login_window_minutes'   => 15,

			/* ---- Section heading -> category map (audit 1.1) ---- */

			// Keys are section headings (spaces and punctuation are ignored),
			// values are paths on this site. Both व/ब spellings are covered.
			'empty_link_map'         => array(
				'अर्थ पालिका'   => '/content/economy/',
				'विचार'         => '/content/opinion/',
				'पालिका वार्ता' => '/content/interview/',
				'पालिका बार्ता' => '/content/interview/',
				'५ प्रश्न'      => '/content/5-questions/',
				'हाम्रो प्रश्न'  => '/content/5-questions/',
			),
		)
	);
}

/**
 * Read one setting.
 *
 * @param string $key     Setting name.
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function palika_cfg( $key, $default = null ) {
	$config = PALIKA_FIX_CONFIG;
	return array_key_exists( $key, $config ) ? $config[ $key ] : $default;
}

/**
 * Is an SEO plugin (Rank Math or Yoast) handling the meta tags?
 *
 * @return bool
 */
function palika_seo_plugin_active() {
	return (
		defined( 'RANK_MATH_VERSION' )
		|| defined( 'RANK_MATH_FILE' )
		|| class_exists( 'RankMath' )
		|| defined( 'WPSEO_VERSION' )
		|| class_exists( 'WPSEO_Frontend' )
	);
}

/**
 * Normalise a heading label: strip tags, zero-width characters, spaces, punctuation.
 *
 * @param string $text Raw text.
 * @return string
 */
function palika_normalize_label( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // &nbsp; &#2407; etc.
	$text = str_replace( array( "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xC2\xA0", "\xEF\xBB\xBF" ), '', $text );
	$text = preg_replace( '/[\s]+/u', '', $text );
	$text = preg_replace( '/[।,\.:;!\?\|\(\)\[\]"\'’“”«»\+\-–—]+/u', '', $text );
	return trim( (string) $text );
}

/**
 * Turn a path from empty_link_map into a full URL.
 *
 * @param string $target Path or absolute URL.
 * @return string
 */
function palika_map_target_to_url( $target ) {
	$target = trim( (string) $target );
	if ( '' === $target ) {
		return home_url( '/' );
	}
	if ( 0 === strpos( $target, 'http://' ) || 0 === strpos( $target, 'https://' ) ) {
		return $target;
	}
	return home_url( user_trailingslashit( $target, 'category' ) );
}

/* ==========================================================================
 * 2. OUTPUT BUFFER - HTML repairs
 * ========================================================================== */
add_action( 'template_redirect', 'palika_start_buffer', 1 );

/**
 * Start the output buffer for front-end HTML repairs.
 *
 * @return void
 */
function palika_start_buffer() {

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

	$needed = palika_cfg( 'repair_empty_links' )
		|| palika_cfg( 'home_h1' )
		|| palika_cfg( 'image_accessibility' )
		|| palika_cfg( 'native_lazy_images' );

	if ( ! $needed ) {
		return;
	}

	ob_start( 'palika_process_html' );
}

/**
 * Process the finished HTML page.
 *
 * @param string $html Full page HTML.
 * @return string
 */
function palika_process_html( $html ) {

	if ( ! is_string( $html ) || strlen( $html ) < 500 || false === stripos( $html, '<html' ) ) {
		return $html;
	}

	if ( palika_cfg( 'repair_empty_links' ) ) {
		$html = palika_repair_empty_links( $html );
	}

	if ( palika_cfg( 'home_h1' ) && ( is_home() || is_front_page() ) && false === stripos( $html, '<h1' ) ) {
		$html = palika_insert_h1( $html );
	}

	if ( palika_cfg( 'image_accessibility' ) ) {
		$html = palika_fix_image_accessibility( $html );
	}

	if ( palika_cfg( 'native_lazy_images' ) ) {
		$html = palika_fix_images( $html );
	}

	return $html;
}

/**
 * Point every href="" to the right category page (or the homepage).
 *
 * How it works: the heading that appears before the empty link decides the target,
 * using the empty_link_map setting. Example: <h3><a href="">पालिका वार्ता</a></h3>
 * followed by <a href="">सबै</a> both become /content/interview/.
 *
 * @param string $html Page HTML.
 * @return string
 */
function palika_repair_empty_links( $html ) {

	if ( false === strpos( $html, 'href=""' ) && false === strpos( $html, "href=''" ) ) {
		return $html;
	}

	// Normalised heading map.
	$map = array();
	foreach ( (array) palika_cfg( 'empty_link_map', array() ) as $label => $target ) {
		$map[ palika_normalize_label( $label ) ] = palika_map_target_to_url( $target );
	}

	// All headings with their position in the document.
	$headings = array();
	if ( preg_match_all( '#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#is', $html, $found, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $found[1] as $index => $match ) {
			$headings[] = array(
				'label'  => palika_normalize_label( $match[0] ),
				'offset' => $found[0][ $index ][1],
			);
		}
	}

	// All anchors that have an empty href.
	if ( ! preg_match_all( '#<a\b[^>]*?href=(["\'])\s*\1[^>]*>#i', $html, $anchors, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$fallback = palika_map_target_to_url( '' );
	$matches  = $anchors[0];

	// Replace from the end so earlier offsets stay valid.
	for ( $i = count( $matches ) - 1; $i >= 0; $i-- ) {

		$tag    = $matches[ $i ][0];
		$offset = $matches[ $i ][1];
		$url    = '';

		// Nearest heading above this link (max 8000 characters up).
		for ( $h = count( $headings ) - 1; $h >= 0; $h-- ) {
			if ( $headings[ $h ]['offset'] < $offset && ( $offset - $headings[ $h ]['offset'] ) < 8000 ) {
				if ( isset( $map[ $headings[ $h ]['label'] ] ) ) {
					$url = $map[ $headings[ $h ]['label'] ];
				}
				break;
			}
		}

		if ( '' === $url ) {
			$url = $fallback; // Homepage: never leave an empty href behind.
		}

		$url   = str_replace( '"', '%22', $url );
		$fixed = preg_replace( '#href=(["\'])\s*\1#i', 'href="' . $url . '"', $tag, 1 );

		if ( is_string( $fixed ) && $fixed !== $tag ) {
			$html = substr_replace( $html, $fixed, $offset, strlen( $tag ) );
		}
	}

	return $html;
}

/**
 * Insert the homepage H1 after <main>, #main-content or <body>.
 *
 * @param string $html Page HTML.
 * @return string
 */
function palika_insert_h1( $html ) {

	$text = trim( (string) palika_cfg( 'home_h1_text', '' ) );

	if ( '' === $text ) {
		$text = get_bloginfo( 'name' );
		$tag  = get_bloginfo( 'description' );
		if ( $tag ) {
			$text .= ' — ' . $tag;
		}
	}

	$h1   = '<h1 class="palika-home-h1">' . esc_html( $text ) . '</h1>';
	$safe = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $h1 );
	$count = 0;

	$out = preg_replace( '/(<main\b[^>]*>)/i', '$1' . $safe, $html, 1, $count );

	if ( $count < 1 ) {
		$out = preg_replace( '/(<div\b[^>]*id=["\']main-content["\'][^>]*>)/i', '$1' . $safe, $html, 1, $count );
	}
	if ( $count < 1 ) {
		$out = preg_replace( '/(<body\b[^>]*>)/i', '$1' . $safe, $html, 1, $count );
	}

	return ( $count > 0 && is_string( $out ) ) ? $out : $html;
}

/**
 * Accessibility: name image-only links and give every image an alt attribute.
 *
 * @param string $html Page HTML.
 * @return string
 */
function palika_fix_image_accessibility( $html ) {

	// 1) <a><img alt="Text"></a>  ->  <a aria-label="Text"><img ...></a>
	$result = preg_replace_callback(
		'#<a\b([^>]*)>\s*(<img\b[^>]*>)\s*</a>#i',
		function ( $matches ) {

			$attributes = $matches[1];
			$image      = $matches[2];

			if ( false !== stripos( $attributes, 'aria-label' ) || false !== stripos( $attributes, 'aria-labelledby' ) ) {
				return $matches[0];
			}

			$label = '';

			if ( preg_match( '#\salt=(["\'])(.+?)\1#i', $image, $alt ) ) {
				$label = trim( wp_strip_all_tags( $alt[2] ) );
			}
			if ( '' === $label && preg_match( '#\stitle=(["\'])(.+?)\1#i', $image, $title ) ) {
				$label = trim( wp_strip_all_tags( $title[2] ) );
			}
			if ( '' === $label ) {
				return $matches[0]; // Nothing usable to name the link with.
			}

			return '<a' . $attributes . ' aria-label="' . esc_attr( $label ) . '">' . $image . '</a>';
		},
		$html
	);

	if ( is_string( $result ) ) {
		$html = $result;
	}

	// 2) <img> without alt  ->  add alt="" (valid HTML, decorative by default).
	$result = preg_replace_callback(
		'#<img\b[^>]*>#i',
		function ( $matches ) {
			if ( preg_match( '#\salt\s*=#i', $matches[0] ) ) {
				return $matches[0];
			}
			return preg_replace( '/^<img/i', '<img alt=""', $matches[0], 1 );
		},
		$html
	);

	if ( is_string( $result ) ) {
		$html = $result;
	}

	return $html;
}

/**
 * Native lazy loading + placeholder repairs.
 *
 * @param string $html Page HTML.
 * @return string
 */
function palika_fix_images( $html ) {

	$first = true;

	$out = preg_replace_callback(
		'#<img\b[^>]*>#i',
		function ( $matches ) use ( &$first ) {

			$tag = $matches[0];

			if ( $first ) {
				$first = false;
				if ( false === stripos( $tag, 'fetchpriority' ) ) {
					$tag = preg_replace( '/^<img/i', '<img fetchpriority="high"', $tag, 1 );
				}
				return $tag;
			}

			// Replace lazy.png / lazyload placeholders with the real source.
			if ( false !== stripos( $tag, 'lazy.png' ) || false !== stripos( $tag, 'lazyload' ) ) {
				if ( preg_match( '#\sdata-(?:lazy-)?src\s*=\s*(["\'])(.+?)\1#i', $tag, $data ) && '' !== $data[2] ) {
					$real = $data[2];
					$tag  = preg_replace_callback(
						'#(\ssrc\s*=\s*)(["\'])(.*?)\2#i',
						function ( $parts ) use ( $real ) {
							return $parts[1] . $parts[2] . $real . $parts[2];
						},
						$tag,
						1
					);
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

/**
 * Style for the injected homepage H1.
 *
 * @return void
 */
function palika_h1_css() {
	if ( ! palika_cfg( 'home_h1' ) || ! ( is_home() || is_front_page() ) ) {
		return;
	}
	echo '<style id="palika-home-h1-css">'
		. '.palika-home-h1{margin:12px 0 16px;padding:9px 14px;font-size:17px;line-height:1.5;font-weight:700;'
		. 'color:#16181d;background:#f5f5f5;border-left:4px solid #c8102e}</style>' . "\n";
}
add_action( 'wp_head', 'palika_h1_css', 99 );

/* ==========================================================================
 * 3. LCP PRELOAD + PRE-CONNECT
 * ========================================================================== */
add_action( 'wp_head', 'palika_lcp_preload', 1 );

/**
 * Preload the featured image of the article being viewed.
 *
 * @return void
 */
function palika_lcp_preload() {

	if ( palika_cfg( 'lcp_image_preload' ) && is_singular( 'post' ) ) {
		$thumbnail_id = get_post_thumbnail_id();
		if ( $thumbnail_id ) {
			$image = wp_get_attachment_image_src( $thumbnail_id, 'large' );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				echo '<link rel="preload" as="image" href="' . esc_url( $image[0] ) . '" fetchpriority="high" />' . "\n";
			}
		}
	}

	foreach ( (array) palika_cfg( 'preconnect_hosts', array() ) as $host ) {
		$host = esc_url( $host );
		if ( ! $host ) {
			continue;
		}
		// crossorigin is needed for font servers; plain preconnect is correct for CDNs.
		$crossorigin = ( false !== strpos( $host, 'fonts.g' ) ) ? ' crossorigin' : '';
		echo '<link rel="preconnect" href="' . $host . '"' . $crossorigin . ' />' . "\n";
	}
}

/* ==========================================================================
 * 4. VIEW COUNTER BEACON (audit 1.4)
 * ========================================================================== */

/**
 * Remove the theme's server-side counter from wp_head.
 *
 * @return void
 */
function palika_remove_theme_view_counter() {

	if ( ! palika_cfg( 'view_counter_beacon' ) ) {
		return;
	}

	global $wp_filter;

	$known = array( 'sandesh_track_views', 'palika_track_views', 'track_post_views', 'set_post_views', 'count_post_views' );

	foreach ( array( 'wp_head', 'wp' ) as $hook ) {

		if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) {
			continue;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = isset( $callback['function'] ) ? $callback['function'] : null;
				if ( is_string( $function ) && in_array( $function, $known, true ) ) {
					remove_action( $hook, $function, $priority );
				}
			}
		}
	}
}
add_action( 'init', 'palika_remove_theme_view_counter', 99 );

/**
 * Which post meta key stores the view count?
 *
 * @return string
 */
function palika_views_meta_key() {

	$configured = palika_cfg( 'views_meta_key', 'auto' );
	if ( $configured && 'auto' !== $configured ) {
		return (string) $configured;
	}

	$detected = get_option( 'palika_detected_views_meta_key' );
	if ( $detected ) {
		return (string) $detected;
	}

	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT meta_key, COUNT(*) AS total FROM {$wpdb->postmeta} WHERE meta_key LIKE '%view%' GROUP BY meta_key ORDER BY total DESC LIMIT 5"
	);

	$key = '';
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( ! empty( $row->meta_key ) && false !== stripos( $row->meta_key, 'view' ) ) {
				$key = (string) $row->meta_key;
				break;
			}
		}
	}
	if ( '' === $key ) {
		$key = '_palika_views';
	}

	update_option( 'palika_detected_views_meta_key', $key, false );

	return $key;
}

/**
 * Register POST /wp-json/palika/v1/view
 *
 * @return void
 */
function palika_register_view_route() {

	if ( ! palika_cfg( 'view_counter_beacon' ) ) {
		return;
	}

	register_rest_route(
		'palika/v1',
		'/view',
		array(
			'methods'             => 'POST',
			'callback'            => 'palika_record_view',
			'permission_callback' => '__return_true', // Public endpoint; abuse is limited by the dedupe window.
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
add_action( 'rest_api_init', 'palika_register_view_route' );

/**
 * Record one view.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function palika_record_view( $request ) {

	$post_id = absint( $request->get_param( 'id' ) );
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( '' === $user_agent || palika_is_bot( $user_agent ) ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	if ( palika_cfg( 'skip_admin_views' ) && current_user_can( 'manage_options' ) ) {
		return new WP_REST_Response( array( 'recorded' => false ), 200 );
	}

	$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) $_SERVER['HTTP_CF_CONNECTING_IP'] : '';
	if ( '' === $ip ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}

	$hours = max( 1, (int) palika_cfg( 'view_dedupe_hours', 12 ) );
	$key   = 'pkv_' . md5( $post_id . '|' . $ip . '|' . $user_agent );

	if ( get_transient( $key ) ) {
		return new WP_REST_Response( array( 'recorded' => false, 'duplicate' => true ), 200 );
	}
	set_transient( $key, 1, $hours * HOUR_IN_SECONDS );

	$meta_key = palika_views_meta_key();
	$current  = (int) get_post_meta( $post_id, $meta_key, true );

	update_post_meta( $post_id, $meta_key, $current + 1 );

	return new WP_REST_Response( array( 'recorded' => true, 'meta_key' => $meta_key ), 200 );
}

/**
 * Simple bot / crawler detection.
 *
 * @param string $user_agent User agent string.
 * @return bool
 */
function palika_is_bot( $user_agent ) {

	$user_agent = strtolower( $user_agent );

	$needles = array(
		'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python', 'java/', 'node',
		'facebookexternalhit', 'preview', 'monitor', 'pingdom', 'uptime', 'gtmetrix',
		'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'headless', 'lighthouse',
		'pagespeed', 'whatsapp', 'telegram', 'viber', 'line/', 'skype', 'baidu',
		'yandex', 'sogou', 'gptbot', 'claudebot', 'perplexity', 'ccbot',
	);

	foreach ( $needles as $needle ) {
		if ( false !== strpos( $user_agent, $needle ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Print the counting script on article pages.
 *
 * @return void
 */
function palika_view_beacon_script() {

	if ( ! palika_cfg( 'view_counter_beacon' ) || ! is_singular( 'post' ) ) {
		return;
	}

	$post_id  = get_queried_object_id();
	$endpoint = $post_id ? esc_url_raw( rest_url( 'palika/v1/view' ) ) : '';

	if ( ! $endpoint ) {
		return;
	}
	?>
<script id="palika-view-beacon">
(function () {
	var url = <?php echo wp_json_encode( $endpoint ); ?>;
	var id  = <?php echo (int) $post_id; ?>;
	var sent = false;
	function send() {
		if (sent) { return; }
		sent = true;
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
	function maybeSend() {
		if (document.visibilityState && 'visible' !== document.visibilityState) { return; }
		window.setTimeout(send, 5000); // Ignore bounces shorter than 5 seconds.
	}
	window.addEventListener('load', maybeSend, false);
	document.addEventListener('visibilitychange', maybeSend, false);
}());
</script>
	<?php
}
add_action( 'wp_footer', 'palika_view_beacon_script', 99 );

/* ==========================================================================
 * 5. SEO DEDUPLICATION (audit 1.3)
 * ========================================================================== */

/**
 * Find and remove theme-owned SEO callbacks from wp_head.
 *
 * Only callbacks whose file lives inside the active theme are touched, and only
 * while Rank Math or Yoast is active. Plugins and other mu-plugins are never removed.
 *
 * @return void
 */
function palika_seo_dedupe() {

	if ( ! palika_cfg( 'seo_dedupe' ) || ! palika_seo_plugin_active() ) {
		return;
	}

	global $wp_filter;
	if ( empty( $wp_filter['wp_head'] ) || empty( $wp_filter['wp_head']->callbacks ) ) {
		return;
	}

	$configured = palika_cfg( 'seo_head_callbacks', 'auto' );
	$explicit   = array();

	if ( is_array( $configured ) ) {
		$explicit = $configured;
	} elseif ( is_string( $configured ) && '' !== trim( $configured ) && 'auto' !== trim( $configured ) ) {
		$explicit = array_filter( array_map( 'trim', explode( ',', $configured ) ) );
	}

	$auto_pattern = '/(^|_)(seo|og|opengraph|schema|schemadata|canonical|metatags|meta_tags|structured_data|twittercard|twitter_card)($|_)/i';
	$theme_dir    = trailingslashit( wp_normalize_path( get_template_directory() ) );

	foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {

		foreach ( $callbacks as $callback ) {

			$function = isset( $callback['function'] ) ? $callback['function'] : null;
			$name     = '';
			$file     = '';

			if ( is_string( $function ) && function_exists( $function ) ) {
				$name = $function;
				try {
					$reflection = new ReflectionFunction( $function );
					$file       = wp_normalize_path( (string) $reflection->getFileName() );
				} catch ( Exception $exception ) {
					$file = '';
				}
			} elseif ( is_array( $function ) && 2 === count( $function ) ) {
				$name = is_object( $function[0] ) ? get_class( $function[0] ) . '::' . $function[1] : (string) $function[0] . '::' . $function[1];
				try {
					$reflection = new ReflectionMethod( $function[0], $function[1] );
					$file       = wp_normalize_path( (string) $reflection->getFileName() );
				} catch ( Exception $exception ) {
					$file = '';
				}
			}

			// Never touch anything outside the theme folder.
			if ( '' === $file || 0 !== strpos( $file, $theme_dir ) ) {
				continue;
			}

			$remove = false;
			if ( $explicit && in_array( $name, $explicit, true ) ) {
				$remove = true;
			} elseif ( ! $explicit && '' !== $name && preg_match( $auto_pattern, $name ) ) {
				$remove = true;
			}

			if ( $remove ) {
				remove_action( 'wp_head', $function, $priority );
			}
		}
	}
}
add_action( 'wp_head', 'palika_seo_dedupe', 0 );

/**
 * Fallback robots meta tag: Google Discover needs max-image-preview:large.
 * Only printed when no SEO plugin is active.
 *
 * @return void
 */
function palika_robots_meta_fallback() {

	if ( ! palika_cfg( 'robots_meta_fallback' ) || palika_seo_plugin_active() || ! is_singular() ) {
		return;
	}
	echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />' . "\n";
}
add_action( 'wp_head', 'palika_robots_meta_fallback', 1 );

/* ==========================================================================
 * 6. MENU URLS + /home/ REDIRECT (audit 8)
 * ========================================================================== */

/**
 * Rewrite internal menu links to the canonical https://palikalive.com form.
 *
 * @param array $items Menu items.
 * @return array
 */
function palika_fix_menu_urls( $items ) {

	if ( ! palika_cfg( 'fix_menu_urls' ) || ! is_array( $items ) ) {
		return $items;
	}

	$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

	foreach ( $items as $item ) {

		if ( empty( $item->url ) ) {
			continue;
		}

		$parts = wp_parse_url( $item->url );
		if ( empty( $parts['host'] ) ) {
			// Relative link: normalise a bare /home/ to the front page.
			$item->url = preg_replace( '#/home/?$#i', '/', $item->url );
			continue;
		}

		$host = strtolower( $parts['host'] );
		if ( $host === $home_host || 'www.' . $home_host === $host ) {
			$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
			$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$path  = preg_replace( '#/home/?$#i', '/', $path );
			$item->url = home_url( $path ) . $query;
		}
	}

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'palika_fix_menu_urls', 20 );

/**
 * 301 the empty /home/ page to the front page - works while the page still
 * exists and after it has been deleted (404 branch).
 *
 * @return void
 */
function palika_redirect_home_page() {

	if ( ! palika_cfg( 'redirect_home_page' ) || is_admin() || wp_doing_ajax() ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path    = strtolower( (string) wp_parse_url( $request, PHP_URL_PATH ) );

	if ( '/home/' === trailingslashit( $path ) || '/home' === $path ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}

	if ( is_page( 'home' ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'palika_redirect_home_page', 0 );

/* ==========================================================================
 * 7. AJAX DRAFT GUARD (audit 9)
 * ========================================================================== */

/**
 * Stop the theme AJAX endpoint from returning unpublished content.
 *
 * @return void
 */
function palika_ajax_draft_guard() {

	if ( ! palika_cfg( 'ajax_draft_guard' ) ) {
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
		return; // Unknown parameter - let the theme handle it.
	}

	$status = get_post_status( $post_id );
	if ( false === $status || 'publish' === $status ) {
		return;
	}
	if ( current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
}
add_action( 'wp_ajax_nepalitheme_oldpost_html', 'palika_ajax_draft_guard', 0 );

/* ==========================================================================
 * 8. INTERSTITIAL ONCE PER SESSION (audit 1.5)
 * ========================================================================== */
add_action( 'wp_footer', 'palika_interstitial_once', 100 );

/**
 * Show the full-screen ad only once per browser session.
 *
 * @return void
 */
function palika_interstitial_once() {

	if ( ! palika_cfg( 'interstitial_once' ) ) {
		return;
	}

	$selectors = trim( (string) palika_cfg( 'interstitial_selectors', '' ) );
	if ( '' === $selectors ) {
		return;
	}
	?>
<script id="palika-interstitial-once">
(function () {
	var selector = <?php echo wp_json_encode( $selectors ); ?>;
	var key = 'palika_intro_seen';
	var seen = false;
	try { seen = '1' === window.sessionStorage.getItem(key); } catch (e) {}
	if (!seen) {
		try { window.sessionStorage.setItem(key, '1'); } catch (e) {}
		return;
	}
	try {
		Array.prototype.forEach.call(document.querySelectorAll(selector), function (element) {
			if (element.parentNode) { element.parentNode.removeChild(element); }
		});
		document.documentElement.style.overflow = '';
		if (document.body) { document.body.style.overflow = ''; }
	} catch (e) {}
}());
</script>
	<?php
}

/* ==========================================================================
 * 9. LOGIN THROTTLE (optional brute-force protection)
 * ========================================================================== */

/**
 * Transient key for the current IP address.
 *
 * @return string
 */
function palika_login_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	return 'palika_login_' . md5( $ip );
}

/**
 * Count failed logins.
 *
 * @return void
 */
function palika_login_failed() {
	if ( ! palika_cfg( 'login_throttle' ) ) {
		return;
	}
	$attempts = (int) get_transient( palika_login_key() );
	set_transient( palika_login_key(), $attempts + 1, max( 1, (int) palika_cfg( 'login_window_minutes', 15 ) ) * MINUTE_IN_SECONDS );
}
add_action( 'wp_login_failed', 'palika_login_failed' );

/**
 * Block logins after too many failures.
 *
 * @param null|WP_User|WP_Error $user     User or error.
 * @param string                $username Submitted username.
 * @param string                $password Submitted password.
 * @return null|WP_User|WP_Error
 */
function palika_login_throttle( $user, $username, $password ) {

	if ( ! palika_cfg( 'login_throttle' ) || empty( $username ) ) {
		return $user;
	}

	$limit = max( 3, (int) palika_cfg( 'login_max_attempts', 10 ) );

	if ( (int) get_transient( palika_login_key() ) >= $limit ) {
		return new WP_Error(
			'palika_too_many_attempts',
			sprintf( 'Too many failed login attempts. Please try again after %d minutes.', (int) palika_cfg( 'login_window_minutes', 15 ) )
		);
	}

	return $user;
}
add_filter( 'authenticate', 'palika_login_throttle', 30, 3 );

/**
 * Clear the counter after a successful login.
 *
 * @return void
 */
function palika_login_success() {
	delete_transient( palika_login_key() );
}
add_action( 'wp_login', 'palika_login_success' );
