<?php
/**
 * Plugin Name: PalikaLive Ad Engine (v47)
 * Description: Cache-friendly ad engine. Fixes the v46 cache bypass, adds a kill switch, sponsored links, ad rotation per page load and a whitelisted align parameter. Drop-in replacement for v46 sections 1-5.
 * Version:     47.0.0
 * Author:      PalikaLive
 * License:     GPL-2.0-or-later
 *
 * ==========================================================================
 * WHAT CHANGED VS v46 (Nepali explanation is in fixes/ad-engine/README.md)
 * ==========================================================================
 * 1. Page cache is no longer disabled for readers. The bypass now applies only
 *    to an admin using ?pklv_ads_debug=1, or when PKLV_ADS_NOCACHE is true.
 * 2. All callbacks have real names (pklv_ads_*), so any of them can be removed
 *    by other code and nothing depends on anonymous closures.
 * 3. PKLV_ADS_ENABLED kill switch: define it false in wp-config.php and every
 *    ad slot returns an empty string instantly.
 * 4. Ad links now carry rel="sponsored noopener noreferrer" (Google requires
 *    sponsored/nofollow on paid placements - audit item 3).
 * 5. New $count parameter: put 3 creatives in a slot and one is chosen per page
 *    load in the browser. This keeps page caching on AND serves a fresh ad.
 * 6. The header slot can be printed eagerly (no lazy load) so it does not hurt
 *    LCP; other slots stay lazy.
 * 7. $align is whitelisted (left|center|right) instead of passed through.
 * 8. Query runs with no_found_rows to skip the extra COUNT(*) query.
 *
 * ==========================================================================
 * HOW TO KEEP IT WORKING WITH THE CACHE
 * ==========================================================================
 * - Normal page: fresh ad HTML is cached; the ad itself is rotated in the
 *   browser when a slot has more than one creative.
 * - Testing ads:   https://palikalive.com/?pklv_ads_debug=1   (admin only)
 * - Emergency off: define( 'PKLV_ADS_ENABLED', false );  in wp-config.php
 * - Emergency no-cache for everyone: define( 'PKLV_ADS_NOCACHE', true );
 * ==========================================================================
 *
 * ==========================================================================
 * SECTIONS STILL MISSING FROM THIS FILE (send them to be integrated):
 *   - Skip / interstitial ad
 *   - Article in-content ads
 *   - Category page slots
 *   - Admin settings screen (if any)
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * 0. SWITCHES AND CONSTANTS
 * ========================================================================== */

// Master switch. Handy in an emergency: define( 'PKLV_ADS_ENABLED', false );
if ( ! defined( 'PKLV_ADS_ENABLED' ) ) {
	define( 'PKLV_ADS_ENABLED', true );
}

// Force "no cache" for every visitor. Keep false: the cache is what makes the
// site fast. Only true while you are chasing an ad-delivery bug.
if ( ! defined( 'PKLV_ADS_NOCACHE' ) ) {
	define( 'PKLV_ADS_NOCACHE', false );
}

// v46 markers kept so any other code that checks them does not break.
if ( ! defined( 'PALIKA_AD_ENGINE_ACTIVE' ) ) {
	define( 'PALIKA_AD_ENGINE_ACTIVE', true );
}
if ( ! defined( 'PALIKA_AD_ACTIVE' ) ) {
	define( 'PALIKA_AD_ACTIVE', true );
}

/* ==========================================================================
 * 1. CACHE POLICY
 *    v46 disabled LiteSpeed cache for every logged-out visitor. That is what
 *    made the whole site slow. This version keeps the cache on and reserves
 *    the bypass for an admin who explicitly asks for fresh ads.
 * ========================================================================== */

/**
 * Allow the theme/plugins to query an uncached page for ad testing.
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
 * Tell LiteSpeed (and other caches) to skip the page - only when asked.
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
 * 2. FRONTEND CSS
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
 * 3. AD OUTPUT ENGINE
 * ========================================================================== */

/**
 * Build the HTML of one ad slot.
 *
 * @param string $slug  Ad position slug (taxonomy term).
 * @param string $align Text alignment: left|center|right.
 * @param int    $count How many creatives to print (1-5). More than one turns
 *                      the slot into a rotating slot handled in the browser.
 * @param bool   $eager Print the images eagerly (use for header slots).
 * @return string
 */
function pklv_master_get_ad( $slug = '', $align = 'center', $count = 1, $eager = false ) {

	if ( ! PKLV_ADS_ENABLED || empty( $slug ) ) {
		return '';
	}

	$slug  = sanitize_text_field( trim( $slug ) );
	$count = max( 1, min( 5, (int) $count ) );

	// Never trust a raw value into a style attribute.
	$allowed_align = array( 'left', 'center', 'right' );
	$align         = in_array( $align, $allowed_align, true ) ? $align : 'center';

	// Accept both dash and underscore spellings of the term slug.
	$slug_variations = array_unique(
		array(
			$slug,
			str_replace( '-', '_', $slug ),
			str_replace( '_', '-', $slug ),
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

	$rotate    = ( $query->post_count > 1 );
	$creatives = array();

	while ( $query->have_posts() ) {

		$query->the_post();

		$ad_id = get_the_ID();

		// Image: featured image first, then the custom fields used by v46.
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
			continue;
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

		$loading = $eager
			? ' loading="eager" fetchpriority="high"'
			: ' loading="lazy" decoding="async"';

		$image_html = '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( get_the_title() ) . '"' . $loading . '>';

		// Store the parts; the exact markup is assembled once the total count is known.
		$creatives[] = array(
			'link'   => $link,
			'target' => $target,
			'rel'    => $rel,
			'img'    => $image_html,
		);
	}

	wp_reset_postdata();

	if ( ! $creatives ) {
		return '';
	}

	/**
	 * Build one creative.
	 *
	 * @param array $creative Creative parts.
	 * @param bool  $hide     Hide it until the rotation script runs.
	 * @return string
	 */
	$render_creative = function ( $creative, $hide ) use ( $rotate ) {

		// !important because the slot CSS declares display on the anchor.
		$ad_attr = $hide ? ' data-pklv-ad style="display:none !important"' : ( $rotate ? ' data-pklv-ad' : '' );

		if ( $creative['link'] ) {
			return '<a href="' . esc_url( $creative['link'] ) . '"' . $creative['target'] . $creative['rel'] . $ad_attr . '>'
				. $creative['img'] . '</a>';
		}

		return $creative['img'];
	};

	$output = '';

	if ( $rotate ) {

		// ONE box holds every creative and the browser picks one per page load.
		// This is what keeps the page cacheable AND the ad fresh.
		$output .= '<div class="pl-banner-box pl-slot-' . esc_attr( $slug ) . '" style="text-align:' . esc_attr( $align ) . ';" data-pklv-rotate>';

		foreach ( $creatives as $index => $creative ) {
			$output .= $render_creative( $creative, $index > 0 );
		}

		$output .= '</div>';
	} else {

		foreach ( $creatives as $creative ) {
			$output .= '<div class="pl-banner-box pl-slot-' . esc_attr( $slug ) . '" style="text-align:' . esc_attr( $align ) . ';">'
				. $render_creative( $creative, false ) . '</div>';
		}
	}

	return $output;
}

/**
 * Rotation script. Print it once, near the end of the page.
 *
 * Uses setProperty('display', 'none', 'important') because the slot CSS above
 * declares display with !important.
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
// Run now for server-rendered slots, and again after DOM ready for slots that
// the header script injects later.
if ('loading' === document.readyState) {
	document.addEventListener('DOMContentLoaded', window.pklvRotateAds);
} else {
	window.pklvRotateAds();
}
</script>
	<?php
}
add_action( 'wp_footer', 'pklv_ads_rotation_script', 99 );

/* ==========================================================================
 * 4. THEME HOOKS AND ALIASES (same public names as v46, named callbacks now)
 * ========================================================================== */

if ( ! function_exists( 'palika_display_ad' ) ) {
	/**
	 * Echo one ad slot.
	 *
	 * @param string $s     Position slug.
	 * @param string $a     Alignment.
	 * @param int    $count Creatives in the slot.
	 * @param bool   $eager Eager loading (header slots).
	 * @return void
	 */
	function palika_display_ad( $s = '', $a = 'center', $count = 1, $eager = false ) {
		echo pklv_master_get_ad( $s, $a, $count, $eager ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML built and escaped inside.
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

/**
 * do_action( 'palika_ad', 'header' ) support.
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
add_action( 'palika_ad', 'pklv_ads_do_action', 10, 4 );
add_action( 'palika_display_ad', 'pklv_ads_do_action', 10, 4 );

/* ==========================================================================
 * 5. SHORTCODES AND WIDGET TEXT
 * ========================================================================== */

/**
 * Shared shortcode renderer.
 *
 * Usage: [palika_ad position="header" align="center" count="3" eager="yes"]
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
add_shortcode( 'palika_ad', 'pklv_ads_shortcode' );
add_shortcode( 'sandesh_ad', 'pklv_ads_shortcode' );

add_filter( 'widget_text', 'do_shortcode' );
add_filter( 'widget_block_content', 'do_shortcode' );

/* ==========================================================================
 * 6. SECTIONS STILL TO COME
 * --------------------------------------------------------------------------
 * Paste the remaining v46 sections here (Skip ad, article ads, category
 * slots, admin screen), or send them over so they can be rewritten the same
 * way: named functions, PKLV_ADS_ENABLED respected, escaped output, and no
 * sitewide cache bypass.
 * ========================================================================== */
