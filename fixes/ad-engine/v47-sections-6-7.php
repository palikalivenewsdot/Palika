<?php
/**
 * PalikaLive Ad Engine v47 - SECTIONS 6 AND 7
 * ==========================================================================
 * Paste these two blocks into v47-sections-1-5.php, replacing the old
 * sections 6 and 7. Nothing else changes.
 * ==========================================================================
 *
 * SECTION 6 - in-article ad: fixes a real HTML bug in v46.
 *   v46 used explode('</p>') + implode('') which DELETES every closing </p>
 *   tag it splits on, so the article HTML came out malformed
 *   ("<p>A<p>B</p>AD<p>C" - browsers guess the rest). Google reads that
 *   markup, and so does every social/AMP/reader parser.
 *   This version inserts the ad after the second </p> without losing a tag.
 *
 * SECTION 7 - header slot: same behaviour as v46, hardened.
 *   - wp_json_encode( ..., JSON_HEX_TAG | JSON_HEX_AMP ) so a stray </script>
 *     or & can never break the page.
 *   - pklvRotateAds() is called after injection (rotation works immediately).
 *   - Server-side helper pklv_header_ad_html() for header.php, plus a switch
 *     (PKLV_ADS_HEADER_JS) to turn the JS fallback off once the theme prints
 *     the slot itself. Printing it server-side removes the layout shift.
 * ==========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * 6. IN-ARTICLE AD ("in-between")
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
	// returned untouched, so the markup stays valid.
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
 * 7. HEADER SLOT (logo-right)
 * ========================================================================== */

// Set false in wp-config.php after header.php prints the slot itself:
//   define( 'PKLV_ADS_HEADER_JS', false );
// Server-side printing removes the layout shift and works without JavaScript.
if ( ! defined( 'PKLV_ADS_HEADER_JS' ) ) {
	define( 'PKLV_ADS_HEADER_JS', true );
}

/**
 * Header slot HTML. Use it directly in header.php:
 *
 *   <div class="pl-header-ad-flex">
 *       <?php echo pklv_header_ad_html(); ?>
 *   </div>
 *
 * @return string
 */
function pklv_header_ad_html() {
	return pklv_master_get_ad( 'header-banner', 'right', 2, true );
}

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
	// Block comments below keep the closing PHP tag intact on the same line.
	
	?>
<script id="pklv-header-js">
document.addEventListener('DOMContentLoaded', function () {
	var adHtml = <?php echo $payload; /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>;
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
add_action( 'wp_footer', 'pklv_ads_header_script', 100 );

/* ==========================================================================
 * STILL MISSING (send these to finish v47)
 *   - Skip / interstitial ad
 *   - Category page slots
 *   - Admin settings screen (if any)
 * ========================================================================== */
