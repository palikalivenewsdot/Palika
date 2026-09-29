<?php
/**
 * Plugin Name: PalikaLive Ad Engine
 * Description: Single-file ad engine (v47). Cache-friendly header, in-article and shortcode ad slots with per-page-load rotation, sponsored links and a kill switch.
 * Version:     47.0.0
 * Author:      PalikaLive
 * License:     GPL-2.0-or-later
 *
 * ==========================================================================
 * FILE          wp-content/mu-plugins/palikalive-ads.php   (same name as now)
 * REPLACES      PalikaLive Master Ad Engine v46
 * REQUIRES      WordPress 5.8+ / PHP 7.4+
 * ==========================================================================
 *
 * INSTALL (cPanel)
 * ----------------
 * 1. File Manager > public_html/wp-content/mu-plugins/
 * 2. Rename the old file first:  palikalive-ads.php  ->  palikalive-ads.php.bak-2026-09-29
 * 3. Upload this file with the same name:  palikalive-ads.php
 * 4. Load the homepage and one article. Done - mu-plugins need no activation.
 *
 * ROLLBACK
 * --------
 * Delete this file and rename the .bak file back. Nothing else to undo.
 *
 * IMPORTANT
 * ---------
 * If another file (a second copy in wp-content/plugins/ or a theme include)
 * also defines pklv_master_get_ad(), remove it. The function_exists guards
 * below stop a fatal error, but ads would come from the older copy.
 *
 * ==========================================================================
 * WHAT CHANGED VS v46
 * ==========================================================================
 * 1. Page cache is no longer disabled for readers. It is bypassed only for an
 *    administrator using ?pklv_ads_debug=1, or when PKLV_ADS_NOCACHE is true.
 *    (v46 called litespeed_control_set_nocache + nocache_headers for every
 *    logged-out visitor, which made the whole site slow.)
 * 2. Ad rotation: give a slot 2-5 creatives and one is chosen per page load in
 *    the browser (count parameter / shortcode count="3"). This keeps the page
 *    cacheable AND the ad fresh - the problem v46 tried to solve by killing
 *    the cache.
 * 3. Paid ad links now carry rel="sponsored noopener noreferrer".
 * 4. The in-article ad no longer breaks the article HTML. v46 used
 *    explode('</p>') + implode(''), which deleted every </p> it split on:
 *    <p>A<p>B</p>AD<p>C instead of valid markup. Now only the second </p> is
 *    used as the insertion point.
 * 5. Header slot: wp_json_encode( JSON_HEX_TAG | JSON_HEX_AMP ) so an ad image
 *    URL containing & or a stray </script> can never break the page; the
 *    rotation script is called after injection; pklv_header_ad_html() prints
 *    the slot server-side (no layout shift, works without JavaScript).
 * 6. Every callback has a real name (pklv_ads_*), so it can be removed or
 *    reordered by other code. No anonymous closures any more.
 * 7. New switches: PKLV_ADS_ENABLED (kill switch), PKLV_ADS_NOCACHE,
 *    PKLV_ADS_HEADER_JS (turn the header JavaScript off after theme edits).
 * 8. Whitelisted align value (left|center|right) instead of a raw style value.
 * 9. no_found_rows on the ad query (one less COUNT(*) query per slot).
 *
 * ==========================================================================
 * QUICK CONTROLS (wp-config.php)
 * ==========================================================================
 * define( 'PKLV_ADS_ENABLED', false );   // all ad output off instantly
 * define( 'PKLV_ADS_NOCACHE', true );    // disable page cache temporarily
 * define( 'PKLV_ADS_HEADER_JS', false ); // header printed by the theme itself
 *
 * ==========================================================================
 * HOW TO TEST
 * ==========================================================================
 * - Fresh ad while debugging:  https://palikalive.com/?pklv_ads_debug=1  (admin)
 * - Cache working again:       browser DevTools > Network > document request >
 *                              x-litespeed-cache: hit
 * - Header slow? print it server-side in header.php:
 *      <div class="pl-header-ad-flex"><?php echo pklv_header_ad_html(); ?></div>
 *   and add define( 'PKLV_ADS_HEADER_JS', false ); to wp-config.php
 * - In-article slot name: ad_position taxonomy term "in-between"
 * - Header slot name:     ad_position taxonomy term "header-banner"
 * - Which file is live:   View Source and search for "PalikaLive Ad Engine v"
 *                         (a one-line HTML comment is printed in <head>)
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Block direct access.
}

/* ==========================================================================
 * 0. ZERO-COLLISION GUARD
 *    If another copy of the engine is already loaded (the old v46 file was not
 *    renamed), this file steps aside instead of triggering a "Cannot
 *    redeclare" fatal error or printing every ad twice.
 * ========================================================================== */

/**
 * Tell the administrator that an older copy is still active.
 *
 * @return void
 */
function pklv_ads_duplicate_notice() {
	echo '<div class="notice notice-error"><p><strong>PalikaLive Ad Engine:</strong> '
		. 'an older copy of the engine is still active, so v47 is inactive. '
		. 'Rename the old file inside wp-content/mu-plugins/ (for example add .bak to the file name) and reload this page.</p></div>';
}

if ( function_exists( 'pklv_master_get_ad' ) ) {
	add_action( 'admin_notices', 'pklv_ads_duplicate_notice' );
	return; // The older engine keeps working; v47 waits.
}

/* ==========================================================================
 * 1. SWITCHES, CONSTANTS, VERSION
 * ========================================================================== */

if ( ! defined( 'PKLV_ADS_VERSION' ) ) {
	define( 'PKLV_ADS_VERSION', '47.0.0' );
}

// Master switch. define( 'PKLV_ADS_ENABLED', false ); stops every ad instantly.
if ( ! defined( 'PKLV_ADS_ENABLED' ) ) {
	define( 'PKLV_ADS_ENABLED', true );
}

// Force no-cache for every visitor. Keep false - the cache is what keeps the
// site fast. Only true while chasing an ad-delivery bug.
if ( ! defined( 'PKLV_ADS_NOCACHE' ) ) {
	define( 'PKLV_ADS_NOCACHE', false );
}

// Header JavaScript fallback. Turn off once header.php prints the slot.
if ( ! defined( 'PKLV_ADS_HEADER_JS' ) ) {
	define( 'PKLV_ADS_HEADER_JS', true );
}

// v46 markers kept so any other code checking them keeps working.
if ( ! defined( 'PALIKA_AD_ENGINE_ACTIVE' ) ) {
	define( 'PALIKA_AD_ENGINE_ACTIVE', true );
}
if ( ! defined( 'PALIKA_AD_ACTIVE' ) ) {
	define( 'PALIKA_AD_ACTIVE', true );
}

/* ==========================================================================
 * 2. CACHE POLICY
 *    v46 disabled LiteSpeed cache for every logged-out visitor. That is what
 *    made the site slow. Here the bypass is reserved for a logged-in
 *    administrator who explicitly asks for fresh ads.
 * ========================================================================== */

/**
 * Is this request allowed to skip the page cache?
 *
 * @return bool
 */
function pklv_ads_is_uncached_request() {

	if ( ! empty( $_GET['pklv_ads_debug'] ) && current_user_can( 'manage_options' ) ) {
		return true;
	}

	return (bool) PKLV_ADS_NOCACHE;
}

/**
 * Tell LiteSpeed (and other caches) to skip this page - only when asked.
 *
 * @return void
 */
function pklv_ads_cache_policy() {

	if ( is_admin() || ! pklv_ads_is_uncached_request() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	do_action( 'litespeed_control_set_nocache', 'PalikaLive ads debug request' );
	nocache_headers();
}
add_action( 'template_redirect', 'pklv_ads_cache_policy', 1 );

/* ==========================================================================
 * 3. FRONTEND CSS (banner slots, header slot, mobile)
 * ========================================================================== */
add_action( 'wp_head', 'pklv_ads_frontend_css', 99 );

/**
 * Banner + header slot styling.
 *
 * @return void
 */
function pklv_ads_frontend_css() {
	?>
<style id="pklv-v47-css">
	.pl-banner-box {
		display: block !important;
		visibility: visible !important;
		opacity: 1 !important;
		width: 100% !important;
		max-width: 100% !important;
		margin: 15px auto !important;
		text-align: center !important;
		clear: both !important;
		position: relative !important;
	}
	.pl-banner-box a {
		display: inline-block !important;
		max-width: 100% !important;
		line-height: 0 !important;
		text-decoration: none !important;
	}
	.pl-banner-box img {
		max-width: 100% !important;
		height: auto !important;
		display: inline-block !important;
		border-radius: 4px;
		vertical-align: middle;
	}
	.pl-header-ad-flex {
		display: flex !important;
		justify-content: flex-end !important;
		align-items: center !important;
		min-height: 80px;
		width: 100% !important;
	}
	.pl-header-ad-flex img {
		max-height: 90px !important;
		width: auto !important;
		max-width: 100% !important;
	}
	/* Rotating slots: reserve height so the ad swap does not push content. */
	.pl-banner-box[data-pklv-rotate] {
		min-height: 100px;
	}
	@media screen and (max-width: 768px) {
		.pl-banner-box img {
			width: 100% !important;
		}
		.pl-header-ad-flex {
			justify-content: center !important;
			margin-top: 10px !important;
		}
		.pl-header-ad-flex img {
			max-height: none !important;
			width: 100% !important;
		}
	}
</style>
	<?php
}

/* ==========================================================================
 * 4. AD OUTPUT ENGINE
 * ========================================================================== */

if ( ! function_exists( 'pklv_master_get_ad' ) ) {

	/**
	 * Build the HTML of one ad slot.
	 *
	 * @param string $slug  Ad position slug (ad_position taxonomy term).
	 * @param string $align Text alignment: left|center|right.
	 * @param int    $count How many creatives to print (1-5). More than one turns
	 *                      the slot into a rotating slot handled in the browser.
	 * @param bool   $eager Print images eagerly (use for the header slot).
	 * @return string
	 */
	function pklv_master_get_ad( $slug = '', $align = 'center', $count = 1, $eager = false ) {

		if ( ! PKLV_ADS_ENABLED || empty( $slug ) ) {
			return '';
		}

		$slug  = sanitize_text_field( trim( $slug ) );
		$count = max( 1, min( 5, (int) $count ) );

		// Never pass a raw value into a style attribute.
		$allowed_align = array( 'left', 'center', 'right' );
		$align         = in_array( $align, $allowed_align, true ) ? $align : 'center';

		// Accept both dash and underscore spellings of the term slug.
		$slug_variations = array_values(
			array_unique(
				array(
					$slug,
					str_replace( '-', '_', $slug ),
					str_replace( '_', '-', $slug ),
				)
			)
		);

		$query = new WP_Query(
			array(
				'post_type'              => 'ads',
				'posts_per_page'         => $count,
				'post_status'            => 'publish',
				'orderby'                => 'rand',
				'suppress_filters'       => true,  // Bypass WPML/Polylang filtering.
				'no_found_rows'          => true,  // Skip the extra COUNT(*) query.
				'update_post_term_cache' => false,
				'lang'                   => '',
				'tax_query'              => array(
					array(
						'taxonomy' => 'ad_position',
						'field'    => 'slug',
						'terms'    => $slug_variations,
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		$creatives = array();

		while ( $query->have_posts() ) {

			$query->the_post();

			$ad_id = get_the_ID();

			// Image: featured image first, then the custom fields v46 used.
			$image = get_the_post_thumbnail_url( $ad_id, 'full' );
			if ( ! $image ) {
				$image = get_post_meta( $ad_id, 'ad_image', true );
			}
			if ( ! $image ) {
				$image = get_post_meta( $ad_id, 'banner_image', true );
			}
			if ( ! $image ) {
				$thumb_id = get_post_meta( $ad_id, '_thumbnail_id', true );
				if ( $thumb_id ) {
					$image = wp_get_attachment_image_url( $thumb_id, 'full' );
				}
			}
			if ( ! $image ) {
				continue; // Ad without an image is skipped.
			}

			// Destination link.
			$link = get_post_meta( $ad_id, 'ad_link', true );
			if ( ! $link ) {
				$link = get_post_meta( $ad_id, 'link', true );
			}
			if ( ! $link ) {
				$link = get_post_meta( $ad_id, 'target_url', true );
			}

			$new_tab = get_post_meta( $ad_id, 'new_tab', true );
			$target  = ( 'yes' === $new_tab || '1' === $new_tab ) ? ' target="_blank"' : '';

			// Paid placements must be marked for search engines.
			$rel = ' rel="sponsored noopener noreferrer"';

			$creatives[] = array(
				'link'   => $link,
				'target' => $target,
				'rel'    => $rel,
				'img'    => '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( get_the_title() ) . '"' . pklv_ads_image_loading( $eager ) . '>',
			);
		}

		wp_reset_postdata();

		if ( ! $creatives ) {
			return '';
		}

		$rotate = ( count( $creatives ) > 1 );
		$output = '';

		if ( $rotate ) {

			/*
			 * ONE box holds every creative; the browser picks one per page load.
			 * This is what keeps the page cacheable AND the ad fresh.
			 */
			$output .= '<div class="pl-banner-box pl-slot-' . esc_attr( $slug ) . '" style="text-align:' . esc_attr( $align ) . ';" data-pklv-rotate>';

			foreach ( $creatives as $index => $creative ) {
				$output .= pklv_ads_render_creative( $creative, true, $index > 0 );
			}

			$output .= '</div>';

		} else {

			foreach ( $creatives as $creative ) {
				$output .= '<div class="pl-banner-box pl-slot-' . esc_attr( $slug ) . '" style="text-align:' . esc_attr( $align ) . ';">'
					. pklv_ads_render_creative( $creative, false, false ) . '</div>';
			}
		}

		return $output;
	}
}

/**
 * Loading attributes for one ad image.
 *
 * @param bool $eager Eager loading (header slot).
 * @return string
 */
function pklv_ads_image_loading( $eager ) {

	if ( $eager ) {
		return ' loading="eager" fetchpriority="high"';
	}

	// In a rotating slot every creative is loaded eagerly at low priority, so
	// the ad is ready the moment the browser swaps to it.
	return ' loading="eager" fetchpriority="low" decoding="async"';
}

/**
 * Render one creative inside a slot.
 *
 * @param array $creative  Creative parts (link, target, rel, img).
 * @param bool  $rotate    Slot rotates between creatives.
 * @param bool  $hide      Hide it until the rotation script runs.
 * @return string
 */
function pklv_ads_render_creative( $creative, $rotate, $hide ) {

	// !important because the slot CSS declares display on the anchor.
	$ad_attr = $hide ? ' data-pklv-ad style="display:none !important"' : ( $rotate ? ' data-pklv-ad' : '' );

	if ( $creative['link'] ) {
		return '<a href="' . esc_url( $creative['link'] ) . '"' . $creative['target'] . $creative['rel'] . $ad_attr . '>'
			. $creative['img'] . '</a>';
	}

	return $creative['img'];
}

/* ==========================================================================
 * 5. ROTATION SCRIPT
 *    Runs for server-rendered slots and again after DOM ready for slots the
 *    header script injects later.
 * ========================================================================== */
add_action( 'wp_footer', 'pklv_ads_rotation_script', 99 );

/**
 * Print the rotation script.
 *
 * @return void
 */
function pklv_ads_rotation_script() {
	?>
<script id="pklv-ad-rotate">
window.pklvRotateAds = function () {
	var boxes = document.querySelectorAll('[data-pklv-rotate]');
	Array.prototype.forEach.call(boxes, function (box) {
		var ads = box.querySelectorAll('[data-pklv-ad]');
		if (ads.length < 2) { return; }
		var pick = Math.floor(Math.random() * ads.length);
		Array.prototype.forEach.call(ads, function (ad, index) {
			if (index === pick) {
				ad.style.removeProperty('display');
			} else {
				ad.style.setProperty('display', 'none', 'important');
			}
		});
	});
};
if ('loading' === document.readyState) {
	document.addEventListener('DOMContentLoaded', window.pklvRotateAds);
} else {
	window.pklvRotateAds();
}
</script>
	<?php
}

/* ==========================================================================
 * 6. THEME HOOKS AND TEMPLATE ALIASES
 *    Same public names as v46 for drop-in compatibility.
 * ========================================================================== */

if ( ! function_exists( 'palika_display_ad' ) ) {

	/**
	 * Echo one ad slot.
	 *
	 * @param string $s     Position slug.
	 * @param string $a     Alignment.
	 * @param int    $count Creatives in the slot.
	 * @param bool   $eager Eager loading (header slot).
	 * @return void
	 */
	function palika_display_ad( $s = '', $a = 'center', $count = 1, $eager = false ) {
		echo pklv_master_get_ad( $s, $a, $count, $eager ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML is escaped inside the engine.
	}
}

if ( ! function_exists( 'sandesh_display_ad' ) ) {

	/**
	 * Echo one ad slot (legacy name).
	 *
	 * @param string $s     Position slug.
	 * @param string $a     Alignment.
	 * @param int    $count Creatives in the slot.
	 * @param bool   $eager Eager loading.
	 * @return void
	 */
	function sandesh_display_ad( $s = '', $a = 'center', $count = 1, $eager = false ) {
		echo pklv_master_get_ad( $s, $a, $count, $eager ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

if ( ! function_exists( 'pklv_ads_do_action' ) ) {

	/**
	 * Handler for do_action( 'palika_ad', 'header' ).
	 *
	 * @param string $slug  Position slug.
	 * @param string $align Alignment.
	 * @param int    $count Creatives.
	 * @param bool   $eager Eager loading.
	 * @return void
	 */
	function pklv_ads_do_action( $slug = '', $align = 'center', $count = 1, $eager = false ) {
		echo pklv_master_get_ad( $slug, $align, $count, $eager ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'palika_ad', 'pklv_ads_do_action', 10, 4 );
add_action( 'palika_display_ad', 'pklv_ads_do_action', 10, 4 );

/* ==========================================================================
 * 7. SHORTCODES
 *    [palika_ad position="header" align="center" count="3" eager="no"]
 * ========================================================================== */

if ( ! function_exists( 'pklv_ads_shortcode' ) ) {

	/**
	 * Shared shortcode renderer.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	function pklv_ads_shortcode( $atts ) {

		$atts = shortcode_atts(
			array(
				'position' => '',
				'slug'     => '',
				'align'    => 'center',
				'count'    => 1,
				'eager'    => 'no',
			),
			$atts,
			'palika_ad'
		);

		$slug  = $atts['position'] ? $atts['position'] : $atts['slug'];
		$eager = in_array( strtolower( (string) $atts['eager'] ), array( 'yes', '1', 'true' ), true );

		return pklv_master_get_ad( $slug, $atts['align'], (int) $atts['count'], $eager );
	}
}
add_shortcode( 'palika_ad', 'pklv_ads_shortcode' );
add_shortcode( 'sandesh_ad', 'pklv_ads_shortcode' );

add_filter( 'widget_text', 'do_shortcode' );
add_filter( 'widget_block_content', 'do_shortcode' );

/* ==========================================================================
 * 8. IN-ARTICLE AD
 *    Inserted after the second </p>. v46 lost every closing </p> because it
 *    used explode('</p>') + implode(''), which produced invalid article HTML.
 * ========================================================================== */
add_filter( 'the_content', 'pklv_ads_inject_in_article', 20 );

/**
 * Add one ad slot after the second paragraph of an article.
 *
 * @param string $content Post content.
 * @return string
 */
function pklv_ads_inject_in_article( $content ) {

	if ( is_admin() || is_feed() || is_embed() ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	// 2 creatives = the slot rotates per page load while the page stays cached.
	$ad = pklv_master_get_ad( 'in-between', 'center', 2 );
	if ( '' === $ad ) {
		return $content;
	}

	$inserted = 0;

	// Replace the second closing </p> with "</p>" + ad. Every other tag is
	// returned untouched, so the article markup stays valid.
	$content = preg_replace_callback(
		'#</p>#i',
		function ( $matches ) use ( $ad, &$inserted ) {
			$inserted++;
			return ( 2 === $inserted ) ? '</p>' . $ad : $matches[0];
		},
		$content
	);

	if ( 0 === $inserted ) {
		$content .= $ad; // Article shorter than two paragraphs.
	}

	return $content;
}

/* ==========================================================================
 * 9. HEADER SLOT (logo-right)
 * ========================================================================== */

if ( ! function_exists( 'pklv_header_ad_html' ) ) {

	/**
	 * Header slot HTML. Print it in header.php to avoid any layout shift:
	 *
	 *   <div class="pl-header-ad-flex"><?php echo pklv_header_ad_html(); ?></div>
	 *
	 * Then set PKLV_ADS_HEADER_JS to false in wp-config.php.
	 *
	 * @return string
	 */
	function pklv_header_ad_html() {
		return pklv_master_get_ad( 'header-banner', 'right', 2, true );
	}
}

add_action( 'wp_footer', 'pklv_ads_header_script', 100 );

/**
 * JavaScript fallback: inject the header slot next to the logo.
 *
 * @return void
 */
function pklv_ads_header_script() {

	if ( ! PKLV_ADS_HEADER_JS || is_admin() ) {
		return;
	}

	$header_ad = pklv_header_ad_html();
	if ( '' === $header_ad ) {
		return;
	}

	// JSON_HEX_TAG / JSON_HEX_AMP escape <, > and & inside the string.
	$payload = wp_json_encode( $header_ad, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	?>
<script id="pklv-header-js">
document.addEventListener('DOMContentLoaded', function () {
	var adHtml = <?php echo $payload; /* phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded with HEX flags. */ ?>;
	if (!adHtml || document.querySelector('.pl-slot-header-banner')) { return; }

	var target = document.querySelector('.header-ad, .header-right, .logo-right, .header-ad-area, .top-header-ad, .pl-header-ad-slot');

	if (target) {
		target.innerHTML = '<div class="pl-header-ad-flex">' + adHtml + '</div>';
		if (window.pklvRotateAds) { window.pklvRotateAds(); }
		return;
	}

	var logo = document.querySelector('.site-logo, .logo, .site-branding, a.custom-logo-link, img[alt*="Palika"], img[alt*="पालिका"]');
	if (!logo || !logo.parentElement) { return; }

	var parent = logo.parentElement;
	parent.style.display = 'flex';
	parent.style.justifyContent = 'space-between';
	parent.style.alignItems = 'center';
	parent.style.flexWrap = 'wrap';

	var wrapper = document.createElement('div');
	wrapper.className = 'pl-header-ad-flex';
	wrapper.style.flex = '1';
	wrapper.style.marginLeft = '15px';
	wrapper.innerHTML = adHtml;
	logo.after(wrapper);

	if (window.pklvRotateAds) { window.pklvRotateAds(); }
});
</script>
	<?php
}

/* ==========================================================================
 * 10. SELF-CHECK MARKER
 *     Hands off, one line. View Source of any page and search for
 *     "PalikaLive Ad Engine v" to see which copy is actually running.
 *     Useful after several uploads over time.
 * ========================================================================== */
add_action( 'wp_head', 'pklv_ads_version_marker', 0 );

/**
 * Print a one-line HTML comment with the engine version.
 *
 * @return void
 */
function pklv_ads_version_marker() {
	echo '<!-- PalikaLive Ad Engine v' . esc_html( PKLV_ADS_VERSION ) . ' (single-file build) -->' . "\n";
}

/* ==========================================================================
 * NOT IN THIS FILE
 * --------------------------------------------------------------------------
 * The v46 code you sent contains sections 1-7 only. If a skip/interstitial
 * ad, category-page slot or admin settings screen exists somewhere else,
 * send that file and it can be merged in the same style.
 *
 * For the interstitial policy (audit item 1.5) the fix pack already provides
 * an option in wp-content/mu-plugins/palikalive-fixes.php:
 *   'interstitial_once'      => true,
 *   'interstitial_selectors' => '#skip, .skip-ad, .intro-ad',
 * ========================================================================== */
