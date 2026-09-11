<?php
/**
 * Code Snippet: Palika share-count fix + diagnostics (Phase 1)
 * Snippets -> Add New -> paste everything BELOW this comment
 * (do NOT paste the <?php line in Code Snippets). Run everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Health + debug endpoints (debug needs the key in the URL). */
add_action( 'rest_api_init', function () {
    register_rest_route( 'palika/v1', '/health', array(
        'methods' => 'GET',
        'callback' => function () { return array( 'ok' => 1 ); },
        'permission_callback' => '__return_true',
    ) );
    register_rest_route( 'palika/v1', '/debug', array(
        'methods' => 'GET',
        'callback' => function () {
            if ( ! isset( $_GET['key'] ) || 'palika2026' !== $_GET['key'] ) {
                return new WP_Error( 'forbidden', 'bad key', array( 'status' => 403 ) );
            }
            return array(
                'html' => get_option( 'palika_debug_share_html', array() ),
                'meta' => get_option( 'palika_debug_share_meta', array() ),
                'keys' => get_option( 'palika_share_meta_keys', array() ),
            );
        },
        'permission_callback' => '__return_true',
    ) );
} );

/* Stop fake increments only for keys explicitly identified later. */
add_filter( 'update_post_metadata', function ( $check, $oid, $key, $val ) {
    $keys = (array) get_option( 'palika_share_meta_keys', array() );
    return in_array( $key, $keys, true ) ? true : $check;
}, 99, 4 );
add_filter( 'add_post_metadata', function ( $check, $oid, $key, $val ) {
    $keys = (array) get_option( 'palika_share_meta_keys', array() );
    return in_array( $key, $keys, true ) ? true : $check;
}, 99, 4 );

add_action( 'template_redirect', function () {
    if ( ! is_singular( 'post' ) ) { return; }

    ob_start( function ( $html ) {
        $post = get_queried_object();
        if ( ! $post || empty( $post->ID ) ) { return $html; }
        $id = $post->ID;

        /* 1) Record the exact counter HTML for inspection. */
        if ( preg_match_all(
            '#<[a-z0-9]+[^>]*class=["\'][^"\']*share-total[^"\']*["\'][^>]*>#i',
            $html, $mm, PREG_OFFSET_CAPTURE ) ) {
            $ctx = array();
            foreach ( $mm[0] as $hit ) {
                $p     = $hit[1];
                $ctx[] = substr( $html, max( 0, $p - 100 ), 500 );
                if ( count( $ctx ) >= 3 ) { break; }
            }
            $all = (array) get_option( 'palika_debug_share_html', array() );
            $all[ $id ] = $ctx;
            $all = array_slice( $all, -6, null, true );
            update_option( 'palika_debug_share_html', $all, false );
        }

        /* 2) Record numeric post-meta (to find the stored counter). */
        $custom = get_post_custom( $id );
        $num    = array();
        if ( is_array( $custom ) ) {
            foreach ( $custom as $k => $vals ) {
                if ( strpos( $k, '_wp_' ) === 0 ) { continue; }
                $v = isset( $vals[0] ) ? $vals[0] : '';
                if ( is_numeric( $v ) ) { $num[ $k ] = $v; }
            }
        }
        $allm = (array) get_option( 'palika_debug_share_meta', array() );
        $allm[ $id ] = $num;
        $allm = array_slice( $allm, -6, null, true );
        update_option( 'palika_debug_share_meta', $allm, false );

        /* 3) Divide every displayed .share-total number by 5. */
        $html = preg_replace_callback(
            '#<([a-z0-9]+)([^>]*class=["\'][^"\']*share-total[^"\']*["\'][^>]*>)(.*?)</\1>#is',
            function ( $m ) {
                if ( ! preg_match( '#>\s*[0-9][0-9,\.]{0,12}\s*<#', $m[3], $nm, PREG_OFFSET_CAPTURE ) ) {
                    return $m[0];
                }
                $txt   = $nm[0][0];
                $fake  = (int) preg_replace( '/[^0-9]/', '', $txt );
                $real  = (int) round( $fake / 5 );
                $new   = '>' . number_format( $real ) . '<';
                $pos   = $nm[0][1];
                $inner = substr( $m[3], 0, $pos ) . $new . substr( $m[3], $pos + strlen( $txt ) );
                return '<' . $m[1] . $m[2] . $inner . '</' . $m[1] . '>';
            },
            $html
        );

        return $html;
    } );
} );
