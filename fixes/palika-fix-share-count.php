<?php
/**
 * Plugin Name: Palika — Fix inflated (x5) Share count
 * Description: The theme multiplies the real share number by 5 on screen and inflates it on every visit. This plugin shows the real number and blocks the inflation. No JS, no settings.
 * Version:     2.0
 *
 * HOW TO INSTALL (pick ANY one — all run the same code):
 *
 *  A) cPanel / FTP (recommended):
 *     Create file  wp-content/mu-plugins/palika-fix-share-count.php
 *     (create the mu-plugins folder if missing) and paste everything
 *     below. It runs automatically — nothing to activate.
 *
 *  B) wp-admin only:
 *     Plugins → Add New → install & activate "Code Snippets",
 *     then Snippets → Add New → paste the code BELOW the "<?php" line
 *     (do not include the <?php tag), choose "Run everywhere",
 *     Save & Activate.
 *
 *  C) Or: Appearance → Theme File Editor → Theme Functions (functions.php),
 *     paste the code BELOW the "<?php" line at the very end → Update File.
 *
 *  After installing: purge Cloudflare cache AND your cache plugin,
 *  then open a news article with ?test=1 on the URL and press Ctrl+U
 *  (view source) and search for "palika share key".
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* The theme displays  real x 5  (120 real = 600 on screen). */
if ( ! defined( 'PALIKA_SHARE_DIVISOR' ) ) {
    define( 'PALIKA_SHARE_DIVISOR', 5 );
}
/* Optional: hard-code the counter's meta key if auto-detection fails. */
if ( ! defined( 'PALIKA_SHARE_META_KEY' ) ) {
    define( 'PALIKA_SHARE_META_KEY', '' );
}

function palika_share_keys() {
    $keys = (array) get_option( 'palika_share_meta_keys', array() );
    if ( PALIKA_SHARE_META_KEY ) {
        $keys[] = PALIKA_SHARE_META_KEY;
    }
    return array_values( array_unique( $keys ) );
}

/* Stop the fake "inflate on every page view" writes. */
add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key, $meta_value ) {
    return in_array( $meta_key, palika_share_keys(), true ) ? true : $check;
}, 99, 4 );

add_filter( 'add_post_metadata', function ( $check, $object_id, $meta_key, $meta_value ) {
    return in_array( $meta_key, palika_share_keys(), true ) ? true : $check;
}, 99, 4 );

add_action( 'template_redirect', function () {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    ob_start( function ( $html ) {
        $post = get_queried_object();
        if ( ! $post || empty( $post->ID ) ) {
            return $html;
        }

        $divisor  = max( 1, (int) PALIKA_SHARE_DIVISOR );
        $detected = '';

        /* Match each <tag class="...share-total..."> ... <tag>NUMBER</tag>
           (any number of empty icon tags are allowed before the number). */
        $pattern =
            '#<([a-z0-9]+)\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bshare-total\b)[^>]*>'
            . '(?:\s*(?:<[a-z0-9]+\b[^>]*>\s*</[a-z0-9]+>|<[a-z0-9]+\b[^>]*/>))*'
            . '\s*<[a-z0-9]+\b[^>]*>\s*([0-9][0-9,\.]{0,12})\s*</[a-z0-9]+>#i';

        $html = preg_replace_callback( $pattern, function ( $m ) use ( &$detected, $divisor, $post ) {
            $fake = (int) str_replace( array( ',', '.' ), '', $m[1] );
            $real = (int) round( $fake / $divisor );

            if ( ! $detected ) {
                $all = get_post_meta( $post->ID );
                $best_score = -1;
                if ( is_array( $all ) ) {
                    foreach ( $all as $key => $vals ) {
                        if ( strpos( $key, '_wp_' ) === 0 || in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
                            continue;
                        }
                        $v = isset( $vals[0] ) ? $vals[0] : '';
                        if ( ! is_numeric( $v ) ) {
                            continue;
                        }
                        // Stored value is either the real value (x5 applied on screen)
                        // or the already-inflated value — both identify the same key.
                        if ( abs( (int) $v - $real ) > 3 && abs( (int) $v - $fake ) > 3 ) {
                            continue;
                        }
                        $score = 1;
                        if ( preg_match( '/share|social/i', $key ) ) { $score += 10; }
                        if ( preg_match( '/count|total/i', $key ) ) { $score += 6; }
                        if ( preg_match( '/view|hit/i', $key ) )     { $score += 3; }
                        if ( $score > $best_score ) {
                            $best_score = $score;
                            $detected   = $key;
                        }
                    }
                }
            }

            // Put the real number into the number element.
            return preg_replace(
                '#(<[a-z0-9]+\b[^>]*>)\s*[0-9][0-9,\.]{0,12}\s*(</[a-z0-9]+>)#i',
                '${1}' . number_format( $real ) . '${2}',
                $m[0],
                1
            );
        }, $html );

        if ( $detected ) {
            $keys = palika_share_keys();
            if ( ! in_array( $detected, $keys, true ) ) {
                $keys[] = $detected;
                update_option( 'palika_share_meta_keys', array_values( array_unique( $keys ) ), false );
            }
            $note = '<!-- palika share key detected: ' . esc_html( $detected ) . ' -->';
            $html = str_replace( '</head>', $note . '</head>', $html );
        }

        return $html;
    } );
} );
