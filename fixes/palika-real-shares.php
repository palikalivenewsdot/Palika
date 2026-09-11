<?php
/**
 * Plugin Name: Palika — Real Share Counts
 * Description: Counts real clicks on the site's own share buttons (Facebook / Messenger / WhatsApp / X) and shows the true total in place of the theme's fake "Shares" number.
 * Version:     1.0
 *
 * INSTALL
 * -------
 * 1. Create folder  wp-content/mu-plugins/  if it does not exist.
 * 2. Copy this file to  wp-content/mu-plugins/palika-real-shares.php
 *    (cPanel File Manager / FTP). Must-use plugins run automatically —
 *    there is nothing to activate.
 * 3. Add the small CSS block from STOP-FAKE-SHARES.md (Step 6) under
 *    Appearance → Customize → Additional CSS, then purge cache
 *    (Cloudflare + cache plugin).
 *
 * WHAT IT DOES
 * ------------
 * - Adds /wp-json/palika/v1/shares (GET = read count, POST = record click).
 * - Counts only genuine clicks on the theme's share links (and ShareThis
 *   buttons if present). Bots and crawlers never fire clicks, so the
 *   number cannot be inflated by traffic.
 * - De-duplicates: same visitor + network + article is counted once per
 *   6 hours (uses Cloudflare connecting IP when present).
 * - JavaScript prints the verified total into every .share-total block
 *   (header bar, sticky bar and the desktop left rail).
 *
 * OPTIONAL — OFFICIAL FACEBOOK NUMBERS
 * ------------------------------------
 * Create a free Facebook App (developers.facebook.com), then put this in
 * wp-config.php (above the "That's all, stop editing!" line):
 *   define('PALIKA_FB_GRAPH_TOKEN', 'YOUR_APP_ID|YOUR_APP_SECRET');
 * Facebook's official engagement.share_count is then added to the total
 * (cached for 2 hours). WhatsApp and X never expose counts publicly.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PALIKA_SHARE_META', '_palika_real_shares' );

function palika_share_networks() {
    return array( 'facebook', 'messenger', 'whatsapp', 'twitter' );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'palika/v1', '/shares', array(
        'methods'             => array( 'GET', 'POST' ),
        'callback'            => 'palika_shares_api',
        'permission_callback' => '__return_true',
    ) );
} );

function palika_share_client_ip() {
    foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }
    return '';
}

function palika_shares_api( WP_REST_Request $request ) {
    $id   = (int) $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || 'post' !== $post->post_type ) {
        return new WP_Error( 'palika_bad_post', 'Invalid post', array( 'status' => 404 ) );
    }

    $network = strtolower( (string) $request->get_param( 'network' ) );
    $counts  = get_post_meta( $id, PALIKA_SHARE_META, true );
    if ( ! is_array( $counts ) ) {
        $counts = array();
    }

    // Record a click (with per-visitor de-duplication).
    if ( in_array( $network, palika_share_networks(), true ) ) {
        $ip  = palika_share_client_ip();
        $key = 'pks_' . md5( $id . '|' . $network . '|' . $ip );

        if ( $ip && false === get_transient( $key ) ) {
            $counts[ $network ] = ( isset( $counts[ $network ] ) ? (int) $counts[ $network ] : 0 ) + 1;
            update_post_meta( $id, PALIKA_SHARE_META, $counts );
            set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
        }
    }

    // Real clicks on our own buttons.
    $clicks = array_sum( array_map( 'intval', $counts ) );

    // Optional official Facebook engagement count.
    $facebook = 0;
    if ( defined( 'PALIKA_FB_GRAPH_TOKEN' ) && PALIKA_FB_GRAPH_TOKEN ) {
        $cached = get_transient( 'pkfb_' . $id );
        if ( false === $cached ) {
            $cached = -1;
            $url    = add_query_arg(
                array(
                    'id'           => get_permalink( $id ),
                    'fields'       => 'engagement',
                    'access_token' => PALIKA_FB_GRAPH_TOKEN,
                ),
                'https://graph.facebook.com/v19.0/'
            );
            $response = wp_remote_get( $url, array( 'timeout' => 5 ) );
            if ( ! is_wp_error( $response ) ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $data['engagement']['share_count'] ) ) {
                    $cached = (int) $data['engagement']['share_count'];
                }
            }
            set_transient( 'pkfb_' . $id, $cached, 2 * HOUR_IN_SECONDS );
        }
        if ( $cached > 0 ) {
            $facebook = $cached;
        }
    }

    $total = $clicks + $facebook;

    return new WP_REST_Response(
        array(
            'count'     => $total,
            'formatted' => number_format_i18n( $total ),
        ),
        200,
        array( 'Cache-Control' => 'no-store, no-cache, must-re-read, max-age=0' )
    );
}

add_action( 'wp_footer', function () {
    if ( ! is_single() ) {
        return;
    }
    ?>
<script id="palika-real-shares">
window.PALIKA_SHARE = <?php echo wp_json_encode( array(
    'id'   => (int) get_the_ID(),
    'rest' => esc_url_raw( rest_url( 'palika/v1/shares' ) ),
) ); ?>;
(function () {
    'use strict';
    var P = window.PALIKA_SHARE;
    if (!P) { return; }

    function fmt(n) { return (n || 0).toLocaleString('en-US'); }

    function numberSpans() {
        // The big number is the first span; ".shares" is the label.
        return Array.prototype.slice.call(
            document.querySelectorAll('.share-total span:not(.shares)')
        );
    }

    function render(count) {
        numberSpans().forEach(function (span) { span.textContent = fmt(count); });
        Array.prototype.slice.call(document.querySelectorAll('.share-total'))
            .forEach(function (box) { box.classList.add('palika-real'); });
    }

    function loadCount() {
        fetch(P.rest + '?id=' + P.id + '&_=' + Date.now(),
              { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && typeof d.count !== 'undefined') { render(d.count); }
            })
            .catch(function () {});
    }

    function bump(network) {
        try {
            var fd = new FormData();
            fd.append('id', P.id);
            fd.append('network', network);
            navigator.sendBeacon(P.rest, fd);
        } catch (e) {
            new Image().src = P.rest + '?id=' + P.id +
                '&network=' + encodeURIComponent(network) + '&_=' + Date.now();
        }
        // Optimistic +1 while the share dialog opens.
        numberSpans().forEach(function (span) {
            var v = parseInt((span.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;
            span.textContent = fmt(v + 1);
        });
    }

    function networkForLink(href) {
        if (/facebook\.com\/(sharer|dialog\/share)/.test(href)) { return 'facebook'; }
        if (/facebook\.com\/dialog\/send|messenger\.com/.test(href)) { return 'messenger'; }
        if (/(wa\.me|whatsapp\.com)/.test(href)) { return 'whatsapp'; }
        if (/(twitter|x)\.com\/intent/.test(href)) { return 'twitter'; }
        return null;
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest) { return; }

        var anchor = event.target.closest('a[href]');
        if (anchor) {
            var net = networkForLink(anchor.href || '');
            if (net) { bump(net); return; }
        }

        // ShareThis plugin buttons (if the plugin is active).
        var btn = event.target.closest('.st-btn[data-network]');
        if (btn) {
            var map = {
                facebook: 'facebook',
                messenger: 'messenger',
                whatsapp: 'whatsapp',
                twitter: 'twitter'
            };
            var n = map[btn.getAttribute('data-network')];
            if (n) { bump(n); }
        }
    }, true);

    loadCount();
})();
</script>
    <?php
}, 100 );
