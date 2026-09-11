<?php
/**
 * Plugin Name: Palika — Real Share Counts
 * Description: Replaces the theme's fake (x10) "Shares" number with the true count of real share-button clicks. One click = one count. Bots and page refreshes are not counted.
 * Version:     1.1
 *
 * INSTALL
 * -------
 * 1. Create folder  wp-content/mu-plugins/  if it does not exist.
 * 2. Copy this file to  wp-content/mu-plugins/palika-real-shares.php
 *    (cPanel File Manager / FTP). Must-use plugins run automatically.
 * 3. Purge Cloudflare cache + any WordPress cache plugin.
 *
 * Counts begin at 0 on install. Old numbers were fabricated and cannot be
 * converted. Same visitor + network + article is counted once per 6 hours.
 *
 * OPTIONAL official Facebook numbers: create a free Facebook App and add
 * to wp-config.php:
 *   define('PALIKA_FB_GRAPH_TOKEN', 'YOUR_APP_ID|YOUR_APP_SECRET');
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PALIKA_SHARE_META', '_palika_real_shares' );

function palika_share_networks() {
    return array( 'facebook', 'messenger', 'whatsapp', 'twitter' );
}

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

/* Optional official Facebook engagement.share_count (cached 2h). */
function palika_facebook_count( $post_id, $url ) {
    if ( ! defined( 'PALIKA_FB_GRAPH_TOKEN' ) || ! PALIKA_FB_GRAPH_TOKEN ) {
        return 0;
    }
    $cached = get_transient( 'pkfb_' . $post_id );
    if ( false !== $cached ) {
        return max( 0, (int) $cached );
    }
    $count    = -1;
    $endpoint = add_query_arg(
        array(
            'id'           => $url,
            'fields'       => 'engagement',
            'access_token' => PALIKA_FB_GRAPH_TOKEN,
        ),
        'https://graph.facebook.com/v19.0/'
    );
    $response = wp_remote_get( $endpoint, array( 'timeout' => 5 ) );
    if ( ! is_wp_error( $response ) ) {
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['engagement']['share_count'] ) ) {
            $count = (int) $data['engagement']['share_count'];
        }
    }
    set_transient( 'pkfb_' . $post_id, $count, 2 * HOUR_IN_SECONDS );
    return max( 0, $count );
}

function palika_real_share_total( $post_id ) {
    $counts = get_post_meta( $post_id, PALIKA_SHARE_META, true );
    $clicks = is_array( $counts ) ? array_sum( array_map( 'intval', $counts ) ) : 0;
    return $clicks + palika_facebook_count( $post_id, get_permalink( $post_id ) );
}

/* REST endpoint: GET read, POST record a genuine click. */
add_action( 'rest_api_init', function () {
    register_rest_route( 'palika/v1', '/shares', array(
        'methods'             => array( 'GET', 'POST' ),
        'callback'            => 'palika_shares_api',
        'permission_callback' => '__return_true',
    ) );
} );

function palika_shares_api( WP_REST_Request $request ) {
    $id   = (int) $request->get_param( 'id' );
    $post = get_post( $id );

    if ( ! $post || 'post' !== $post->post_type ) {
        return new WP_Error( 'palika_bad_post', 'Invalid post', array( 'status' => 404 ) );
    }

    $network = strtolower( (string) $request->get_param( 'network' ) );

    if ( in_array( $network, palika_share_networks(), true ) ) {
        $ip  = palika_share_client_ip();
        $key = 'pks_' . md5( $id . '|' . $network . '|' . $ip );

        if ( $ip && false === get_transient( $key ) ) {
            $counts = get_post_meta( $id, PALIKA_SHARE_META, true );
            if ( ! is_array( $counts ) ) {
                $counts = array();
            }
            $counts[ $network ] = ( isset( $counts[ $network ] ) ? (int) $counts[ $network ] : 0 ) + 1;
            update_post_meta( $id, PALIKA_SHARE_META, $counts );
            set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
        }
    }

    $total = palika_real_share_total( $id );

    return new WP_REST_Response(
        array(
            'count'     => $total,
            'formatted' => number_format_i18n( $total ),
        ),
        200,
        array( 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0' )
    );
}

/* Server-side: overwrite the fake theme number with the real total inside
   every <div class="share-total"><span>NUMBER</span>... on article pages.
   This removes the x10/rounded number even before JavaScript runs. */
add_action( 'template_redirect', function () {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    ob_start( function ( $html ) {
        $post = get_queried_object();
        if ( ! $post || empty( $post->ID ) ) {
            return $html;
        }

        $real = number_format_i18n( palika_real_share_total( $post->ID ) );

        // First <span> inside .share-total is the number; .shares is the label.
        return preg_replace_callback(
            '#(class=["\'][^"\']*\bshare-total\b[^"\']*["\'][^>]*>\s*<span[^>]*>)(.*?)(</span>)#is',
            function ( $m ) use ( $real ) {
                return $m[1] . $real . $m[3];
            },
            $html
        );
    } );
} );

/* Client-side: record real clicks and keep the displayed number current
   even on cached (Cloudflare / cache plugin) pages. */
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
        return Array.prototype.slice.call(
            document.querySelectorAll('.share-total span:not(.shares)')
        );
    }
    function render(count) {
        numberSpans().forEach(function (span) { span.textContent = fmt(count); });
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
