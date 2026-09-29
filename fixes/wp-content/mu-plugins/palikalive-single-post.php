<?php
/**
 * Plugin Name:  PalikaLive Single Post Fixes
 * Description:  Single news page UX fixes — (1) headline + sub-headline auto-hide on scroll, (2) clean sub-headline (red bar removed), (3) share bar shows a live share count with exactly 5 buttons (Facebook, X, Messenger, WhatsApp, Share).
 * Version:      1.0.0
 * Author:       Palika Live
 * File:         wp-content/mu-plugins/palikalive-single-post.php
 * Marker:       PalikaLive Single Post Fixes v1.0.0
 *
 * ───────────────────────────────────────────────────────────────────────────
 *  यो फाइल के गर्छ (छोटो):
 *   1) लेख पढ्दै तल स्क्रोल गर्नासाथ टाइटल + सब-हेडलाइन (sticky) स्वतः लुक्छ;
 *      माथि स्क्रोल गर्दा फेरि देखिन्छ।  (hide_after = 90px)
 *   2) सब-हेडलाइनको रातो ठाडो लाइन हट्छ र सफा "lead" शैलीमा सुन्दर देखिन्छ।
 *   3) शेयर बारमा जीवित शेयर संख्या देखिन्छ + ठ्याक्कै ५ बटन:
 *      Facebook, X, Messenger, WhatsApp, Share  (Viber हट्छ)।
 *
 *  टेक्निकल नोट:
 *   - साइटमा पहिले नै #pl-post-header लाई hide गर्ने एउटा <head> स्क्रिप्ट छ,
 *     तर त्यो DOM बन्नुअघि चल्ने हुँदा काम गर्दैन (त्यसैले हेडलाइन टाँसिरहन्छ)।
 *     यो फाइलले त्यही काम footer + DOMContentLoaded बाट सही तरिकाले गर्छ,
 *     नयाँ क्लास "plx-title-hidden" प्रयोग गरेर (पुरानोसँग झगडा हुँदैन)।
 *   - शेयर संख्या यही साइटभित्र गनिन्छ (post meta _palika_share_count) र
 *     REST बाट पढिन्छ — कुनै तेस्रो पक्ष API चाहिँदैन (Facebook को पुरानो
 *     share-count API सन् 2019 मै बन्द भइसकेको छ)।
 *   - LiteSpeed cache सुरक्षित: पेज क्यास भए पनि संख्या REST बाट ताजै आउँछ।
 *   - केही मेटाइँदैन; सबै परिवर्तन CSS/JS हुन्। फाइल डिलिट गरे सबै रद्द हुन्छ।
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * 0. CONFIG  (चाहिएमा यहाँका मान फेर्नुहोस्)
 * =========================================================================== */
if ( ! defined( 'PALIKA_SINGLE_CONFIG' ) ) {
	define(
		'PALIKA_SINGLE_CONFIG',
		array(
			'version'           => '1.0.0',

			/* 1) टाइटल auto-hide */
			'autohide'          => true,
			'hide_after'        => 90,      // कति px तल स्क्रोल गरेपछि लुक्ने
			'show_on_scroll_up' => true,    // माथि स्क्रोल गर्दा फेरि देखाउने

			/* 2) सब-हेडलाइन */
			'subhead_accent'    => false,   // true गरे पातलो गोलो रातो accent लाइन आउँछ

			/* 3) शेयर बार */
			'share_count'       => true,
			'count_base'        => 0,       // संख्या यहाँबाट सुरु हुन्छ
			'count_label'       => 'Shares',
			'count_label_one'   => 'Share',
			'nepali_digits'     => false,   // true गरे "१०५ Shares" देखिन्छ
			'kill_viber'        => true,    // Viber बटन हटाउने
			'add_messenger'     => true,    // Messenger बटन थप्ने
			'count_copy'        => true,    // Share/Copy बटनको क्लिक पनि गन्ने
			'meta_key'          => '_palika_share_count',
			'rate_seconds'      => 45,      // एउटै पाठक + एउटै लेख = यति सेकेन्डमा १ गणना
		)
	);
}

/**
 * Config helper.
 *
 * @param string $key Config key.
 * @return mixed
 */
function plk_single_cfg( $key ) {
	$c = PALIKA_SINGLE_CONFIG;
	return isset( $c[ $key ] ) ? $c[ $key ] : null;
}

/* ===========================================================================
 * 1. REST API — शेयर गणना (GET = पढ्ने, POST = बढाउने)
 * =========================================================================== */
add_action( 'rest_api_init', 'plk_single_register_routes' );

function plk_single_register_routes() {
	register_rest_route(
		'palikalive/v1',
		'/shares',
		array(
			'methods'             => 'GET',
			'callback'            => 'plk_single_shares_get',
			'permission_callback' => '__return_true',
			'args'                => array(
				'post' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	register_rest_route(
		'palikalive/v1',
		'/shares',
		array(
			'methods'             => 'POST',
			'callback'            => 'plk_single_shares_post',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * सक्रिय लेख हो कि जाँच्ने (होइन भने 0)।
 */
function plk_single_valid_post( $id ) {
	$id = absint( $id );
	if ( ! $id ) {
		return 0;
	}
	$p = get_post( $id );
	if ( ! $p || 'post' !== $p->post_type || 'publish' !== $p->post_status ) {
		return 0;
	}
	return $id;
}

/**
 * हालको संख्या (base सहित)।
 */
function plk_single_count( $id ) {
	$stored = (int) get_post_meta( $id, plk_single_cfg( 'meta_key' ), true );
	$base   = (int) plk_single_cfg( 'count_base' );
	$total  = $stored + $base;
	return $total > 0 ? $total : 0;
}

function plk_single_shares_get( WP_REST_Request $request ) {
	nocache_headers();
	$id = plk_single_valid_post( $request->get_param( 'post' ) );
	if ( ! $id ) {
		return new WP_REST_Response( array( 'count' => 0 ), 200 );
	}
	return new WP_REST_Response( array( 'count' => plk_single_count( $id ) ), 200 );
}

function plk_single_shares_post( WP_REST_Request $request ) {
	nocache_headers();
	$body = $request->get_json_params();
	$id   = plk_single_valid_post( isset( $body['post'] ) ? $body['post'] : 0 );
	$inc  = isset( $body['n'] ) ? absint( $body['n'] ) : 1;

	if ( $inc < 1 ) {
		$inc = 1;
	}
	if ( $inc > 5 ) {
		$inc = 5;
	}
	if ( ! $id ) {
		return new WP_REST_Response( array( 'count' => 0 ), 200 );
	}

	/* दुरुपयोग रोक्न: एउटै IP + एउटै लेख = rate_seconds भित्र एकै पटक */
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	$key = 'plk_sh_' . md5( $ip . '|' . $id );

	if ( get_transient( $key ) ) {
		return new WP_REST_Response( array( 'count' => plk_single_count( $id ) ), 200 );
	}
	set_transient( $key, 1, max( 5, (int) plk_single_cfg( 'rate_seconds' ) ) );

	$meta = plk_single_cfg( 'meta_key' );
	$cur  = (int) get_post_meta( $id, $meta, true );
	update_post_meta( $id, $meta, $cur + $inc );

	return new WP_REST_Response( array( 'count' => plk_single_count( $id ) ), 200 );
}

/* ===========================================================================
 * 2. FRONT END — CSS (head) + JS (footer), लेखको पेजमा मात्र
 * =========================================================================== */
add_action( 'wp_head', 'plk_single_head', 99 );

function plk_single_head() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	echo "\n<!-- PalikaLive Single Post Fixes v" . esc_html( plk_single_cfg( 'version' ) ) . " -->\n";
	echo '<style id="plx-single-css">' . plk_single_css() . "</style>\n";
}

add_action( 'wp_footer', 'plk_single_footer', 99 );

function plk_single_footer() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$cfg = array(
		'version'           => plk_single_cfg( 'version' ),
		'rest'              => esc_url_raw( rest_url( 'palikalive/v1/shares' ) ),
		'post'              => get_the_ID(),
		'url'               => get_permalink(),
		'autohide'          => (bool) plk_single_cfg( 'autohide' ),
		'hide_after'        => (int) plk_single_cfg( 'hide_after' ),
		'show_on_scroll_up' => (bool) plk_single_cfg( 'show_on_scroll_up' ),
		'share_count'       => (bool) plk_single_cfg( 'share_count' ),
		'count_base'        => (int) plk_single_cfg( 'count_base' ),
		'label'             => (string) plk_single_cfg( 'count_label' ),
		'label_one'         => (string) plk_single_cfg( 'count_label_one' ),
		'nepali_digits'     => (bool) plk_single_cfg( 'nepali_digits' ),
		'kill_viber'        => (bool) plk_single_cfg( 'kill_viber' ),
		'add_messenger'     => (bool) plk_single_cfg( 'add_messenger' ),
		'count_copy'        => (bool) plk_single_cfg( 'count_copy' ),
	);

	$json = wp_json_encode( $cfg );
	$json = str_replace( '</', '<\/', $json );

	echo "\n<!-- PalikaLive Single Post Fixes v" . esc_html( plk_single_cfg( 'version' ) ) . " -->\n";
	echo '<script id="plx-single-js">' . "\n";
	echo '/* PalikaLive Single Post Fixes v' . esc_html( plk_single_cfg( 'version' ) ) . " */\n";
	echo 'window.PLX_SINGLE = ' . $json . ";\n";
	echo plk_single_js_body() . "\n";
	echo "</script>\n";
}

/* ===========================================================================
 * 3. CSS
 * =========================================================================== */
function plk_single_css() {
	$css = <<<'CSS'
/* ===== PalikaLive Single Post Fixes v1.0.0 ===== */

/* ---------- १) सब-हेडलाइन: रातो ठाडो लाइन हटाइयो + सफा lead शैली ---------- */
.single-sub-heading {
	border: 0 !important;
	border-left: 0 !important;
	border-image: none !important;
	box-shadow: none !important;
	background: transparent !important;
	padding: 0 !important;
	margin: 14px 0 6px !important;
	font-size: clamp(17px, 1.45vw, 20px) !important;
	line-height: 1.7 !important;
	font-weight: 600 !important;
	color: #41506b !important;
	max-width: 70ch;
	letter-spacing: 0 !important;
	text-wrap: pretty;
}
.single-sub-heading::before,
.single-sub-heading::after {
	content: none !important;
	display: none !important;
}

/* ---------- २) टाइटल ब्लक auto-hide ---------- */
#pl-post-header {
	transition: transform .28s ease, opacity .22s ease !important;
	will-change: transform;
}
#pl-post-header.plx-title-hidden {
	transform: translate3d(0, -120%, 0) !important;
	opacity: 0 !important;
	visibility: hidden !important;
	pointer-events: none !important;
}
@media (prefers-reduced-motion: reduce) {
	#pl-post-header { transition: none !important; }
}

/* ---------- ३) शेयर बार: संख्या + ५ बटन ---------- */
.pl-share-bar {
	display: flex !important;
	flex-wrap: wrap !important;
	align-items: center !important;
	justify-content: space-between !important;
	gap: 10px 16px !important;
	margin: 12px 0 20px !important;
}
.pl-share-bar .pl-share-meta {
	display: flex !important;
	flex-wrap: wrap !important;
	align-items: center !important;
	gap: 6px 12px !important;
	min-width: 0 !important;
}
.pl-share-bar .pl-share-buttons { margin-left: auto !important; }
.pl-share-buttons-inner {
	display: flex !important;
	flex-wrap: wrap !important;
	align-items: center !important;
	gap: 8px !important;
}

/* शेयर संख्या */
.pl-share-count {
	display: inline-flex !important;
	align-items: center !important;
	gap: 7px !important;
	margin: 0 4px 0 0 !important;
	padding: 6px 12px !important;
	background: #eef2f7 !important;
	border: 1px solid #e2e8f0 !important;
	border-radius: 999px !important;
	color: #0f172a !important;
	font-size: 14px !important;
	font-weight: 700 !important;
	line-height: 1.25 !important;
	white-space: nowrap !important;
	opacity: 0;
	transition: opacity .25s ease !important;
}
.pl-share-count.pl-share-count-on { opacity: 1 !important; }
.pl-share-count .pl-share-count-n { font-weight: 800 !important; color: #0f172a !important; }
.pl-share-count .pl-share-count-l { font-weight: 600 !important; color: #64748b !important; margin-left: 2px; }

/* बटन — समान गोल आइकन */
.pl-share-bar .pl-share-btn {
	position: relative !important;
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 38px !important;
	height: 38px !important;
	min-width: 38px !important;
	padding: 0 !important;
	margin: 0 !important;
	border: 0 !important;
	border-radius: 50% !important;
	overflow: hidden !important;
	text-indent: -9999px !important;
	font-size: 0 !important;
	line-height: 0 !important;
	color: #fff !important;
	cursor: pointer !important;
	background-color: #64748b !important;
	background-repeat: no-repeat !important;
	background-position: center center !important;
	background-size: 20px 20px !important;
	box-shadow: 0 1px 2px rgba(15, 23, 42, .18) !important;
	transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease !important;
}
.pl-share-bar .pl-share-btn i,
.pl-share-bar .pl-share-btn svg,
.pl-share-bar .pl-share-btn img,
.pl-share-bar .pl-share-btn span { display: none !important; }

.pl-share-bar .pl-share-btn:hover { transform: translateY(-1px) !important; box-shadow: 0 4px 10px rgba(15, 23, 42, .24) !important; }
.pl-share-bar .pl-share-btn:active { transform: scale(.94) !important; }
.pl-share-bar .pl-share-btn:focus-visible { outline: 2px solid #0f172a !important; outline-offset: 2px !important; }

.pl-share-btn.pl-facebook {
	background-color: #1877f2 !important;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.12-2.41-.12-2.38 0-4.01 1.45-4.01 4.12v2.3H7.6V13h2.68v8h3.22z'/%3E%3C/svg%3E") !important;
}
.pl-share-btn.pl-twitter {
	background-color: #0b0f14 !important;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M17.53 3h3.02l-6.6 7.54L21.75 21h-5.9l-4.62-6.03L5.94 21H2.91l7.06-8.07L2.25 3h6.05l4.18 5.52L17.53 3zm-1.06 16.2h1.67L7.6 4.71H5.8l10.67 14.49z'/%3E%3C/svg%3E") !important;
}
.pl-share-btn.pl-whatsapp {
	background-color: #25d366 !important;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M12.04 2A9.9 9.9 0 0 0 2.1 11.9c0 1.75.46 3.46 1.34 4.96L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01a9.9 9.9 0 0 0 9.93-9.9A9.9 9.9 0 0 0 12.04 2zm5.8 14.03c-.24.68-1.42 1.32-1.96 1.36-.5.05-1.13.07-1.83-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.8-4.17-4.94-4.36-.15-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.02-2.41.26-.29.57-.36.77-.36.19 0 .38 0 .55.01.18.01.41-.07.64.49.24.56.81 1.97.88 2.11.07.15.12.32.02.51-.1.19-.15.3-.29.47-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.75 1.24 1.61 2.01 1.11.99 2.04 1.29 2.33 1.44.29.15.46.12.63-.07.17-.19.73-.85.92-1.14.19-.29.39-.24.65-.15.26.1 1.66.78 1.95.93.29.15.48.22.55.34.07.13.07.75-.17 1.43z'/%3E%3C/svg%3E") !important;
}
.pl-share-btn.pl-messenger {
	background-color: #0084ff !important;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M12 2C6.3 2 2 6.2 2 11.6c0 2.9 1.2 5.4 3.2 7.1.2.15.3.36.3.6l.05 1.7c.02.54.58.9 1.08.68l1.9-.84c.17-.07.36-.08.54-.03 1.06.29 2.2.45 3.4.45 5.7 0 10-4.2 10-9.6S17.7 2 12 2zm-1.8 12.6l-2.9-3.1c-.28-.3.03-.77.42-.63l2.2.86c.19.07.4.06.57-.03l3.02-1.6c.4-.22.88.22.68.64l-2.9 3.1c-.28.3-.75.3-1.03 0z'/%3E%3C/svg%3E") !important;
}
.pl-share-btn.pl-copy {
	background-color: #475569 !important;
	background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23fff'%3E%3Cpath d='M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z'/%3E%3C/svg%3E") !important;
}
.pl-share-btn.pl-copy[title="Copied!"] { background-color: #16a34a !important; }

/* मोबाइल */
@media (max-width: 767px) {
	.pl-share-bar { justify-content: flex-start !important; gap: 8px 10px !important; }
	.pl-share-bar .pl-share-buttons { margin-left: 0 !important; }
	.pl-share-bar .pl-share-btn {
		width: 36px !important;
		height: 36px !important;
		min-width: 36px !important;
		background-size: 18px 18px !important;
	}
	.pl-share-count { padding: 5px 10px !important; font-size: 13px !important; }
}
CSS;

	/* सब-हेडलाइनमा वैकल्पिक सुन्दर accent (डिफल्ट बन्द) */
	if ( plk_single_cfg( 'subhead_accent' ) ) {
		$css .= "\n.single-sub-heading { border-left: 3px solid #d90429 !important; border-radius: 2px !important; padding-left: 12px !important; }\n";
	}

	/* Viber हटाउने (config बाट बन्द गर्न सकिन्छ) */
	if ( plk_single_cfg( 'kill_viber' ) ) {
		$css .= "\n.pl-share-btn.pl-viber { display: none !important; }\n";
	}

	return $css;
}

/* ===========================================================================
 * 4. JAVASCRIPT (vanilla, jQuery चाहिँदैन)
 * =========================================================================== */
function plk_single_js_body() {
	return <<<'JS'
(function () {
	"use strict";
	if (typeof window.PLX_SINGLE === "undefined") { return; }
	var C = window.PLX_SINGLE;
	var D = document, W = window;

	/* ---------- १) टाइटल ब्लक auto-hide ---------- */
	function plxAutoHide() {
		if (!C.autohide) { return; }
		var el = D.getElementById("pl-post-header") || D.querySelector(".single-post .post-header, .post-header");
		if (!el) { return; }
		var lastY = W.pageYOffset || 0;
		var tick = false;

		function hide() { el.classList.add("plx-title-hidden"); }
		function show() { el.classList.remove("plx-title-hidden"); }

		function step() {
			tick = false;
			var y = W.pageYOffset || D.documentElement.scrollTop || 0;
			var dy = y - lastY;
			if (y <= C.hide_after) {
				show();
			} else if (dy > 2) {
				hide();
			} else if (dy < -2) {
				if (C.show_on_scroll_up) { show(); } else { hide(); }
			}
			lastY = y;
		}
		function onScroll() {
			if (tick) { return; }
			tick = true;
			if (W.requestAnimationFrame) { W.requestAnimationFrame(step); } else { setTimeout(step, 16); }
		}
		W.addEventListener("scroll", onScroll, { passive: true });
		W.addEventListener("resize", onScroll, { passive: true });
		onScroll();
	}

	/* ---------- २) शेयर बार ---------- */
	var bumpTimer = null, bumpN = 0;

	function plxNum(n) {
		n = parseInt(n, 10);
		if (isNaN(n) || n < 0) { n = 0; }
		var s = String(n);
		if (C.nepali_digits) {
			s = s.replace(/[0-9]/g, function (d) { return "०१२३४५६७८९".charAt(parseInt(d, 10)); });
		}
		return s;
	}

	function plxRender(chip, n) {
		if (!chip) { return; }
		var num = chip.querySelector(".pl-share-count-n");
		var lab = chip.querySelector(".pl-share-count-l");
		if (num) { num.textContent = plxNum(n); }
		if (lab) { lab.textContent = (parseInt(n, 10) === 1) ? C.label_one : C.label; }
		chip.classList.add("pl-share-count-on");
	}

	function plxLoadCount(chip) {
		var url = C.rest + (C.rest.indexOf("?") > -1 ? "&" : "?") +
			"post=" + encodeURIComponent(C.post) + "&t=" + Date.now();
		try {
			W.fetch(url, { credentials: "same-origin", cache: "no-store" })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					plxRender(chip, (j && typeof j.count !== "undefined") ? j.count : C.count_base);
				})
				.catch(function () { plxRender(chip, C.count_base); });
		} catch (e) {
			plxRender(chip, C.count_base);
		}
	}

	function plxBump() {
		bumpN++;
		if (bumpTimer) { clearTimeout(bumpTimer); }
		bumpTimer = setTimeout(function () {
			var n = bumpN;
			bumpN = 0;
			bumpTimer = null;
			try {
				W.fetch(C.rest, {
					method: "POST",
					credentials: "same-origin",
					cache: "no-store",
					keepalive: true,
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ post: C.post, n: n })
				})
					.then(function (r) { return r.json(); })
					.then(function (j) {
						if (j && typeof j.count !== "undefined") {
							plxRender(D.querySelector(".pl-share-count"), j.count);
						}
					})
					.catch(function () {});
			} catch (e) {}
		}, 900);
	}

	function plxCopy(text) {
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text);
				return;
			}
			var ta = D.createElement("textarea");
			ta.value = text;
			ta.style.position = "fixed";
			ta.style.opacity = "0";
			D.body.appendChild(ta);
			ta.select();
			try { D.execCommand("copy"); } catch (e2) {}
			D.body.removeChild(ta);
		} catch (e3) {}
	}

	function plxShare() {
		var bar = D.querySelector(".pl-share-bar");
		if (!bar) { return; }
		var wrap = bar.querySelector(".pl-share-buttons-inner") || bar.querySelector(".pl-share-buttons");
		if (!wrap) { return; }

		/* २a) Viber हटाउने */
		if (C.kill_viber) {
			var viber = wrap.querySelector(".pl-share-btn.pl-viber");
			if (viber && viber.parentNode) { viber.parentNode.removeChild(viber); }
		}

		/* २b) Messenger बटन (Facebook र X को बीचमा) */
		if (C.add_messenger && !wrap.querySelector(".pl-share-btn.pl-messenger")) {
			var m = D.createElement("a");
			m.className = "pl-share-btn pl-messenger";
			m.href = "https://www.messenger.com/";
			m.target = "_blank";
			m.rel = "noopener nofollow";
			m.title = "Messenger मा सेयर";
			m.setAttribute("aria-label", "Messenger मा सेयर");
			m.addEventListener("click", function (e) {
				if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
					e.preventDefault();
					W.location.href = "fb-messenger://share/?link=" + encodeURIComponent(C.url);
				} else {
					plxCopy(C.url);
				}
			});
			var tw = wrap.querySelector(".pl-share-btn.pl-twitter");
			if (tw && tw.nextSibling) { wrap.insertBefore(m, tw.nextSibling); } else { wrap.appendChild(m); }
		}

		/* २c) बाँकी बटनका नाम */
		var labels = {
			facebook: "Facebook मा सेयर",
			twitter: "X (ट्विटर) मा सेयर",
			whatsapp: "WhatsApp मा सेयर",
			copy: "सेयर / लिङ्क कपी"
		};
		Object.keys(labels).forEach(function (k) {
			var b = wrap.querySelector(".pl-share-btn.pl-" + k);
			if (b) {
				b.title = labels[k];
				b.setAttribute("aria-label", labels[k]);
			}
		});

		/* २d) शेयर संख्या */
		if (C.share_count) {
			var chip = wrap.querySelector(".pl-share-count");
			if (!chip) {
				chip = D.createElement("span");
				chip.className = "pl-share-count";
				chip.setAttribute("data-plx", C.version);
				chip.setAttribute("title", "शेयर संख्या");
				chip.innerHTML = '<b class="pl-share-count-n">' + plxNum(C.count_base) +
					'</b><span class="pl-share-count-l">' + C.label + "</span>";
				wrap.insertBefore(chip, wrap.firstChild);
			}
			plxLoadCount(chip);
		}

		/* २e) क्लिक गणना (एकै पटक bind हुन्छ) */
		if (!wrap.getAttribute("data-plx-bound")) {
			wrap.setAttribute("data-plx-bound", "1");
			wrap.addEventListener("click", function (e) {
				var t = e.target;
				while (t && t !== wrap) {
					if (t.className && String(t.className).indexOf("pl-share-btn") > -1) { break; }
					t = t.parentNode;
				}
				if (!t || t === wrap) { return; }
				if (String(t.className).indexOf("pl-copy") > -1 && !C.count_copy) { return; }
				plxBump();
			}, true);
		}
	}

	function plxInit() {
		plxAutoHide();
		plxShare();
	}

	if (D.readyState === "loading") {
		D.addEventListener("DOMContentLoaded", plxInit);
	} else {
		plxInit();
	}
	W.addEventListener("load", plxInit);
	setTimeout(plxInit, 1200);
	setTimeout(plxInit, 3000);
})();
JS;
}
