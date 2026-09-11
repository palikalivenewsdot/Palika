<?php
/**
 * Plugin Name: Palika — Stop fake share counts
 * Description: Removes the leftover ShareThis block from article bodies and (optionally) freezes the theme's fake "Shares" counter.
 * Version:     1.0
 *
 * HOW TO INSTALL
 * --------------
 * 1. Create the folder  wp-content/mu-plugins/  if it does not exist
 *    (mu-plugins = "must-use" plugins; they run automatically and cannot
 *    be accidentally deactivated).
 * 2. Copy THIS FILE into  wp-content/mu-plugins/stop-fake-shares.php
 *    (via cPanel File Manager, FTP, or your host's file manager).
 * 3. Optional: after you find the exact meta key the theme uses for the
 *    fake counter (see STOP-FAKE-SHARES.md, Step 4), define it below in
 *    PALIKA_FAKE_SHARE_META_KEY. The counter will then be frozen at 0 and
 *    will stop climbing on every page load and bot visit.
 *
 * Safe to delete at any time; it changes no database structure.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

/* The post meta key that stores the inflated number. Leave as '' until
 * you have identified it (Step 4 in the guide), e.g. 'share_count'. */
if ( ! defined( 'PALIKA_FAKE_SHARE_META_KEY' ) ) {
    define( 'PALIKA_FAKE_SHARE_META_KEY', '' );
}

/**
 * 1) Remove the leftover ShareThis inline container from article content
 *    — but ONLY when the official ShareThis plugin is not active, so we
 *    never break a working ShareThis setup.
 */
add_filter( 'the_content', function ( $content ) {
    if ( class_exists( 'Sharethis_Plugin' ) || class_exists( 'ShareThis_Plugin' ) ) {
        return $content; // Official plugin is active; leave its block alone.
    }

    // Exact block currently stored at the top of each article:
    $content = preg_replace(
        '#<div[^>]*class=["\']sharethis-inline-share-buttons["\'][^>]*>\s*</div>#i',
        '',
        $content
    );

    return $content;
}, 20 );

/* ---------------------------------------------------------------------
 * 2) Freeze the fake counter once its meta key is known.
 *    - Every read returns 0 (so even old cached templates show 0).
 *    - Every write is ignored (the number can no longer climb).
 * ------------------------------------------------------------------- */
if ( PALIKA_FAKE_SHARE_META_KEY ) {

    add_filter( 'get_post_metadata', function ( $check, $object_id, $meta_key, $single ) {
        if ( PALIKA_FAKE_SHARE_META_KEY === $meta_key ) {
            return $single ? 0 : array( 0 );
        }
        return $check;
    }, 99, 4 );

    add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key, $meta_value ) {
        if ( PALIKA_FAKE_SHARE_META_KEY === $meta_key ) {
            return true; // Block the update.
        }
        return $check;
    }, 99, 4 );

    add_filter( 'add_post_metadata', function ( $check, $object_id, $meta_key, $meta_value ) {
        if ( PALIKA_FAKE_SHARE_META_KEY === $meta_key ) {
            return true; // Block the insert.
        }
        return $check;
    }, 99, 4 );
}
