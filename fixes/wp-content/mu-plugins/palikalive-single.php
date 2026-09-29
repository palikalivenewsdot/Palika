<?php
/**
 * Plugin Name:  PalikaLive Single Post Fix
 * Description:  Single news page fixes without touching the theme: (1) the headline block is NEVER pinned — it leaves the screen the moment the reader scrolls down and a small bar comes back on scroll up, (2) the sub-headline red bar / grey box is removed, (3) the share bar shows a live share count + Facebook, X, Messenger, WhatsApp, Share. Also hides the injected homepage H1 visually (it stays there for SEO / screen readers).
 * Version:      3.1.0
 * Author:       Palika Live
 * File:         wp-content/mu-plugins/palikalive-single.php
 * Marker:       PalikaLive Single Post Fix v3.1.0
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS FILE DOES
 *   1) Title block (#pl-post-header)
 *      a. The block can never be pinned in its normal state: the rule
 *         "#pl-post-header { position: static !important }" beats any theme /
 *         optimizer CSS that tries to keep it on screen. This is what was
 *         still missing in v3.0.0 — the theme's CSS pinned `.post-header`,
 *         so the headline + sub-headline stayed visible while reading.
 *      b. On top of that, JS hides it the moment the reader scrolls down
 *         (past 80px) and brings a small sticky bar back on scroll up.
 *   2) Sub-headline: the red vertical bar and the grey box are removed.
 *   3) Share bar: Viber is removed, Messenger is added, a live share count
 *      chip is added and the last button becomes a real Share button.
 *   4) Homepage H1 (added by the fix pack) is hidden visually — it stays in
 *      the HTML for Google and screen readers, but visitors never see the
 *      grey strip. Prefer it fully gone? In palikalive-fixes.php set
 *      'home_h1' => false.
 *
 * WHY THIS IS SAFE
 *   - The theme's files are NOT edited. Delete (or rename) this one file and
 *     the site is exactly like before.
 *   - CSS/JS only + one small AJAX counter. No database change.
 *   - The counter lives in the post meta "_palika_share_count" and is served
 *     by admin-ajax.php, which is never page-cached, so the number is fresh
 *     even on cached pages.
 *
 * HOW TO INSTALL / UPDATE
 *   cPanel -> File Manager -> public_html/wp-content/mu-plugins/ ->
 *   open palikalive-single.php -> select all -> paste this whole file ->
 *   Save -> LiteSpeed Cache: Purge All -> Ctrl+Shift+R in the browser.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * 1. SHARE COUNTER (AJAX)
 *    POST admin-ajax.php  action=pklv_share&act=add&post=123&n=1
 *    GET  admin-ajax.php?action=pklv_share&act=get&post=123
 * =========================================================================== */
add_action( 'wp_ajax_nopriv_pklv_share', 'pklv_share_ajax' );
add_action( 'wp_ajax_pklv_share', 'pklv_share_ajax' );

function pklv_share_ajax() {
	/* Never cache this answer (the page cache must not freeze the number). */
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
	}

	$post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
	$act     = isset( $_REQUEST['act'] ) ? sanitize_key( wp_unslash( $_REQUEST['act'] ) ) : 'get';
	$count   = 0;
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( $post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
		$meta  = '_palika_share_count';
		$count = (int) get_post_meta( $post_id, $meta, true );

		if ( 'add' === $act ) {
			$number = isset( $_REQUEST['n'] ) ? absint( $_REQUEST['n'] ) : 1;
			if ( $number < 1 ) {
				$number = 1;
			}
			if ( $number > 5 ) {
				$number = 5;
			}

			/* Abuse guard: same IP + same post = one count per 45 seconds. */
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
			$key = 'pklv_sh_' . md5( $ip . '|' . $post_id );

			if ( ! get_transient( $key ) ) {
				set_transient( $key, 1, 45 );
				$count = $count + $number;
				update_post_meta( $post_id, $meta, $count );
			}
		}

		if ( $count < 0 ) {
			$count = 0;
		}
	}

	wp_send_json_success( array( 'count' => $count ) );
}

/* ===========================================================================
 * 2. FRONT END — CSS in <head>, JS in the footer
 * =========================================================================== */
add_action( 'wp_head', 'pklv_head_css', 99 );

function pklv_head_css() {

	/* (a) Homepage H1 from the fix pack: keep it, hide it visually. */
	if ( is_home() || is_front_page() ) {
		echo '<style id="pklv-h1-hide">'
			. '.palika-home-h1{position:absolute!important;width:1px!important;height:1px!important;'
			. 'margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;'
			. 'clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important;background:none!important;'
			. 'color:transparent!important;font-size:1px!important;box-shadow:none!important}'
			. '</style>' . "\n";
	}

	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$css = '
/* ===== PalikaLive Single Post Fix v3.1.0 ===== */

/* (1) Sub-headline: red bar + grey box removed, clean lead paragraph. */
.single-sub-heading {
	border: 0 !important;
	border-left: 0 !important;
	background: transparent !important;
	padding: 0 !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	color: #46536b !important;
	font-size: 17px !important;
	line-height: 1.75 !important;
	font-weight: 500 !important;
	max-width: 820px;
	margin: 2px 0 16px !important;
}

/* (2) Title block.
   Base state: the block can NEVER be pinned — whatever the theme or an
   optimizer CSS tries, it scrolls away with the page. */
#pl-post-header {
	position: static !important;
	top: auto !important;
}

/* Small sticky bar while reading (added by the script on scroll up). */
#pl-post-header.pl-head-compact {
	position: -webkit-sticky !important;
	position: sticky !important;
	top: 0 !important;
	z-index: 999 !important;
	background: #ffffff !important;
	padding: 9px 0 8px !important;
	margin-bottom: 12px !important;
	box-shadow: 0 4px 14px rgba(11, 37, 69, 0.10) !important;
	transition: transform 0.28s ease, opacity 0.2s ease !important;
}
#pl-post-header.pl-head-compact .single-title {
	font-size: 18px !important;
	line-height: 1.35 !important;
	margin: 0 !important;
	max-height: 3em;
	overflow: hidden;
}
#pl-post-header.pl-head-compact .single-sub-heading {
	display: none !important;
}
#pl-post-header.pl-head-compact .heading {
	font-size: 11.5px !important;
	padding: 3px 8px !important;
	margin-bottom: 6px !important;
}
body.admin-bar #pl-post-header.pl-head-compact {
	top: 32px !important;
}
@media (max-width: 782px) {
	body.admin-bar #pl-post-header.pl-head-compact {
		top: 46px !important;
	}
}

/* Hidden state — printed last, so it always wins. */
#pl-post-header.plk-hidden,
#pl-post-header.pl-head-hidden {
	transform: translateY(-130%) !important;
	opacity: 0 !important;
	visibility: hidden !important;
	pointer-events: none !important;
}
@media (prefers-reduced-motion: reduce) {
	#pl-post-header {
		transition: none !important;
	}
}

/* (3) Share bar. */
.pl-share-btn.pl-viber {
	display: none !important;
}
.pl-share-btn.pl-messenger {
	background: #0084ff !important;
	color: #ffffff !important;
}
.pl-share-count {
	display: inline-flex !important;
	align-items: center !important;
	gap: 6px !important;
	padding: 6px 12px !important;
	margin: 0 4px 0 0 !important;
	background: #eef2f7 !important;
	border: 1px solid #e2e8f0 !important;
	border-radius: 999px !important;
	color: #0f172a !important;
	font-size: 13.5px !important;
	font-weight: 700 !important;
	line-height: 1.25 !important;
	white-space: nowrap !important;
}
.pl-share-count .pl-share-count-n {
	font-weight: 800 !important;
	color: #0f172a !important;
}
.pl-share-count .pl-share-count-l {
	font-weight: 600 !important;
	color: #64748b !important;
}
';

	echo "\n<!-- PalikaLive Single Post Fix v3.1.0 -->\n";
	echo '<style id="pklv-css">' . $css . "</style>\n";
}

add_action( 'wp_footer', 'pklv_footer_js', 99 );

function pklv_footer_js() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$count = (int) get_post_meta( $post_id, '_palika_share_count', true );
	if ( $count < 0 ) {
		$count = 0;
	}

	$data = array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'post'  => (int) $post_id,
		'count' => $count,
		'url'   => get_permalink( $post_id ),
		'title' => wp_strip_all_tags( get_the_title( $post_id ) ),
	);

	$js = <<<'JS'
(function () {
	"use strict";

	var D = document, W = window;

	function ready(fn) {
		if (D.readyState !== "loading") { fn(); }
		else { D.addEventListener("DOMContentLoaded", fn); }
	}

	/* -----------------------------------------------------------------
	 * 1. Title block: leaves the screen as soon as the reader scrolls
	 *    down; a small sticky bar comes back on scroll up.
	 * ----------------------------------------------------------------- */
	function titleBar() {
		var head = D.getElementById("pl-post-header");
		if (!head) { return; }

		var HIDE_AT = 80;   // px scrolled before the block may stick/hide
		var SHOW_UP = 8;    // px of upward movement needed to bring it back
		var lastY   = W.pageYOffset || 0;
		var tick    = false;

		function hide() {
			head.classList.add("pl-head-compact");
			head.classList.add("pl-head-hidden");
			head.classList.add("plk-hidden");
			/* Inline copy: wins over any theme rule without !important. */
			head.style.setProperty("transform", "translateY(-130%)", "important");
			head.style.setProperty("opacity", "0", "important");
		}
		function show() {
			head.classList.add("pl-head-compact");
			head.classList.remove("pl-head-hidden");
			head.classList.remove("plk-hidden");
			head.style.removeProperty("transform");
			head.style.removeProperty("opacity");
		}
		function top() {
			head.classList.remove("pl-head-compact");
			head.classList.remove("pl-head-hidden");
			head.classList.remove("plk-hidden");
			head.style.removeProperty("transform");
			head.style.removeProperty("opacity");
		}

		function step() {
			tick = false;
			var y  = W.pageYOffset || D.documentElement.scrollTop || 0;
			var dy = y - lastY;

			if (y <= HIDE_AT) {
				top();                          /* back at the headline */
			} else if (dy > 1) {
				hide();                         /* scrolling down -> gone */
			} else if (dy < -SHOW_UP) {
				show();                         /* scrolling up -> small bar */
			}

			lastY = y;
		}

		function onScroll() {
			if (tick) { return; }
			tick = true;
			if (W.requestAnimationFrame) { W.requestAnimationFrame(step); }
			else { setTimeout(step, 16); }
		}

		W.addEventListener("scroll", onScroll, { passive: true });
		W.addEventListener("load", function () { lastY = W.pageYOffset || 0; step(); });
		step();
	}

	/* -----------------------------------------------------------------
	 * 2. Share bar: Viber out, Messenger in, count chip in, Share button.
	 * ----------------------------------------------------------------- */
	function shareBar() {
		var cfg  = W.PKLVS || {};
		var bar  = D.querySelector(".pl-share-bar");
		if (!bar) { return; }

		var wrap = bar.querySelector(".pl-share-buttons-inner") || bar.querySelector(".pl-share-buttons");
		if (!wrap) { return; }

		/* 2a. Viber out. */
		var viber = wrap.querySelector(".pl-share-btn.pl-viber");
		if (viber && viber.parentNode) { viber.parentNode.removeChild(viber); }

		/* 2b. Messenger in (right after X / Twitter). */
		if (!wrap.querySelector(".pl-share-btn.pl-messenger")) {
			var m = D.createElement("a");
			m.className = "pl-share-btn pl-messenger";
			m.href = "https://www.messenger.com/";
			m.target = "_blank";
			m.rel = "noopener nofollow";
			m.title = "Messenger";
			m.setAttribute("aria-label", "Messenger");
			m.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.477 2 2 6.145 2 11.259c0 2.913 1.38 5.507 3.542 7.24.185.15.295.375.295.615l.007 1.646c0 .53.557.877 1.03.645l1.851-.825a.866.866 0 0 1 .605-.05 11.13 11.13 0 0 0 2.67.325c5.523 0 10-4.145 10-9.259S17.523 2 12 2zm6.02 7.14l-2.9 4.6c-.42.66-1.34.82-1.96.34l-2.36-1.78a.6.6 0 0 0-.72.01l-2.86 2.16c-.5.38-1.14-.22-.79-.75l2.9-4.6c.42-.66 1.34-.82 1.96-.34l2.36 1.78a.6.6 0 0 0 .72-.01l2.86-2.16c.5-.38 1.14.22.79.75z"/></svg>';

			var tw = wrap.querySelector(".pl-share-btn.pl-twitter");
			if (tw && tw.nextSibling) { wrap.insertBefore(m, tw.nextSibling); }
			else { wrap.appendChild(m); }
		}

		/* 2c. Last button: a real Share button (icon + label). */
		var copyBtn = wrap.querySelector(".pl-share-btn.pl-copy");
		if (copyBtn) {
			copyBtn.title = "Share";
			copyBtn.setAttribute("aria-label", "Share");
			copyBtn.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>';
		}

		/* 2d. Count chip (first item in the row). */
		var chip = wrap.querySelector(".pl-share-count");
		if (!chip) {
			chip = D.createElement("span");
			chip.className = "pl-share-count";
			chip.id = "pl-share-count";
			chip.title = "Share count";
			chip.innerHTML = '<b class="pl-share-count-n">0</b><span class="pl-share-count-l">Shares</span>';
			wrap.insertBefore(chip, wrap.firstChild);
		}

		var shown = parseInt(cfg.count, 10);
		if (isNaN(shown) || shown < 0) { shown = 0; }

		function paint(n) {
			n = parseInt(n, 10);
			if (isNaN(n) || n < 0) { n = 0; }
			shown = n;
			var num = chip.querySelector(".pl-share-count-n");
			var lab = chip.querySelector(".pl-share-count-l");
			if (num) { num.textContent = String(n); }
			if (lab) { lab.textContent = (n === 1) ? "Share" : "Shares"; }
		}

		paint(shown);

		/* 2e. Counter: read once (cached pages stay fresh), add on every tap. */
		var pending = 0, timer = null;

		function read() {
			if (!cfg.ajax || !cfg.post) { return; }
			var url = cfg.ajax + (cfg.ajax.indexOf("?") > -1 ? "&" : "?") +
				"action=pklv_share&act=get&post=" + encodeURIComponent(cfg.post) + "&t=" + (new Date()).getTime();
			try {
				fetch(url, { credentials: "same-origin", cache: "no-store" })
					.then(function (r) { return r.json(); })
					.then(function (j) {
						if (j && j.success && j.data && typeof j.data.count !== "undefined") { paint(j.data.count); }
					})
					.catch(function () {});
			} catch (e) {}
		}

		function push() {
			var n = pending;
			pending = 0;
			timer = null;
			if (!n || !cfg.ajax || !cfg.post) { return; }
			try {
				fetch(cfg.ajax, {
					method: "POST",
					credentials: "same-origin",
					cache: "no-store",
					keepalive: true,
					headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
					body: "action=pklv_share&act=add&post=" + encodeURIComponent(cfg.post) + "&n=" + n
				})
					.then(function (r) { return r.json(); })
					.then(function (j) {
						if (j && j.success && j.data && typeof j.data.count !== "undefined") { paint(j.data.count); }
					})
					.catch(function () {});
			} catch (e) {}
		}

		function bump() {
			pending = pending + 1;
			paint(shown + 1);          /* instant feedback */
			if (timer) { clearTimeout(timer); }
			timer = setTimeout(push, 800);
		}

		/* Every share tap counts once. Capture phase: runs before the theme's
		   own copy handler, and the Share button can stop it cleanly. */
		wrap.addEventListener("click", function (event) {
			var node = event.target;
			while (node && node !== wrap) {
				if (node.className && String(node.className).indexOf("pl-share-btn") > -1) { break; }
				node = node.parentNode;
			}
			if (!node || node === wrap) { return; }

			var cls = String(node.className);

			if (cls.indexOf("pl-copy") > -1 && navigator.share && cfg.url) {
				/* Phones / supporting browsers: native share sheet. */
				event.preventDefault();
				event.stopPropagation();
				try {
					navigator.share({ title: cfg.title || D.title, url: cfg.url });
				} catch (e) {}
			}

			bump();
		}, true);

		/* Read the fresh number shortly after load. */
		setTimeout(read, 700);
		W.addEventListener("load", read);
	}

	ready(function () {
		titleBar();
		shareBar();
	});
})();
JS;

	echo "\n<!-- PalikaLive Single Post Fix v3.1.0 -->\n";
	echo '<script id="pklv-js">window.PKLVS = ' . wp_json_encode( $data ) . ";\n" . $js . "</script>\n";
}
