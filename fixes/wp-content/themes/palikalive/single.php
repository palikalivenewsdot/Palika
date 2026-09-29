<?php
/**
 * Single Post — Palika Live
 *
 * Safe drop-in single.php template.
 *
 * Changelog 5.0.0 (single-post fixes round):
 * - Sticky headline FIXED. The old script could be moved to <head> by
 *   LiteSpeed/JS optimizers (it runs there as a data: URI, before the DOM
 *   exists), so document.getElementById('pl-post-header') returned null and
 *   the whole hide logic silently did nothing — the title block kept showing.
 *   Everything now waits for DOMContentLoaded, so it works wherever the
 *   optimizer puts it. The block hides as soon as the reader scrolls down
 *   from the headline (threshold 120px, delta 2px) and shows again on scroll up.
 * - Sub-heading: the RED VERTICAL BAR (border-left: 3px #d90429) is removed
 *   and the sub-heading is restyled as a clean lead paragraph (no box, no bar).
 * - Share bar: shows the real share count + exactly 5 buttons —
 *   Facebook, X, Messenger, WhatsApp, Share. Viber is removed, Messenger added.
 * - Share counter lives in THIS file (a POST endpoint on the post URL itself,
 *   answered before any output). No extra plugin is needed. Counter meta key:
 *   _palika_share_count. Nothing is cached; the number is always fresh.
 * - The counter is abuse-guarded (same IP + same post = 1 count / 45 seconds)
 *   and the number is printed server-side, then refreshed after load.
 * - Copy/share button: uses the native share sheet when the device supports
 *   navigator.share, otherwise copies the link (green "Copied!" feedback).
 *
 * Changelog 4.9.3 (badge restore — no file hunting needed):
 * - The red kicker badge was being wiped by a hardcoded "रातो डब्बा हटाउने"
 *   block somewhere in the theme/plugin stack (style id="wp-custom-css")
 *   using !important. Since that file could not be located/edited, this
 *   version OVERRIDES it with a stronger, later, also-!important rule:
 *   .single-post .post-header .heading { ... } restores the #e11b22 pill.
 *   The old block can stay where it is; it now simply loses.
 *
 * Changelog 4.9.2 (headline-area fix round):
 * - Kicker: trailing ":" is stripped in PHP so the red badge + " :" suffix
 *   can never show as ": :".
 * - Headline: the old ".single-title" override could NEVER win because the
 *   theme's ".single-post .post-header .title" (63px) has higher specificity.
 *   Replaced with a winning selector that keeps the BIG reference look
 *   (63px / 600 weight / line-height 1.2 / black).
 * - IMPORTANT COMPANION STEP (one-time, in wp-admin): the badge was missing
 *   because a leftover snippet titled "रातो डब्बा हटाउने" lives in
 *   Appearance -> Customize -> Additional CSS (wp-custom-css) and forces
 *   .heading{ background:transparent !important; color:#d90429 !important }.
 *   Delete that snippet (block number 3) so the red badge returns.
 *
 * Carried from 4.9.1 (headline-look feedback):
 * - Main headline made bigger, bolder and tighter like the reference
 *   screenshots: 63px desktop (theme value), 24px mobile.
 * - Red kicker gets an ":" suffix (style kicker look).
 *
 * Carried from 4.9.0:
 * - Typography tuned closer to leading Nepali news portals: body 15px / 1.85.
 * - Body-area ads removed: the above-comment ad slot is OFF (article text is
 *   never interrupted by ads). Kill the legacy in-content ad filter too
 *   (one line in inc/theme-function.php — see site notes).
 * - Sidebar widget renamed: भर्खरै -> पालिका अपडेट.
 * - Dates removed from ट्रेन्डिङ items and from सम्बन्धित खबर cards.
 * - Below-title ad row CSS hardened: exactly 3 equal columns on desktop,
 *   stacked full-width on mobile.
 * - Facebook comments: SDK tag is excluded from LiteSpeed JS optimization
 *   (data-optimized="0") and a parse retry is added, fixing the blank box.
 *
 * Carried from 4.8.0:
 * - Smart sticky headline (hide on scroll down, show on scroll up).
 * - Facebook Comments between author bio and related posts.
 * - Author bio fallback chain.
 * - Ad slots use the site's REAL "Ad Positions" terms.
 *
 * Important: replace the old single.php entirely, then clear LiteSpeed cache.
 *
 * @package PalikaLive
 * @version 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ---------------------------------------------------------------------------
 * SHARE COUNTER ENDPOINT
 * ---------------------------------------------------------------------------
 * The share number is counted inside this site (no third-party API is used —
 * Facebook's old public share-count endpoint has been dead since 2019).
 * The value is stored in the post meta "_palika_share_count".
 *
 * This endpoint lives on the post URL itself and answers ONLY to POST
 * requests whose JSON body contains an "act" key, so the normal page flow is
 * never touched (POST requests are never page-cached):
 *   {"act":"get","id":123}        -> {"count":12}
 *   {"act":"add","id":123,"n":1}  -> {"count":13}
 */
if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
    $palikalive_raw = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
    $palikalive_in  = json_decode( (string) $palikalive_raw, true );

    if ( is_array( $palikalive_in ) && isset( $palikalive_in['act'] ) ) {
        $palika_share_meta = '_palika_share_count';
        $palika_share_post = (int) get_queried_object_id();
        $palika_share_act  = sanitize_key( $palikalive_in['act'] );
        $palika_share_out  = array( 'count' => 0 );

        if (
            $palika_share_post > 0
            && 'post' === get_post_type( $palika_share_post )
            && 'publish' === get_post_status( $palika_share_post )
        ) {
            $palika_share_count = (int) get_post_meta( $palika_share_post, $palika_share_meta, true );

            if ( 'add' === $palika_share_act ) {
                $palika_share_inc = isset( $palikalive_in['n'] ) ? absint( $palikalive_in['n'] ) : 1;
                $palika_share_inc = max( 1, min( 5, $palika_share_inc ) );

                /* Abuse guard: same IP + same post = one count per 45 seconds. */
                $palika_share_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
                $palika_share_key = 'plk_sh_' . md5( $palika_share_ip . '|' . $palika_share_post );

                if ( ! get_transient( $palika_share_key ) ) {
                    set_transient( $palika_share_key, 1, 45 );
                    $palika_share_count += $palika_share_inc;
                    update_post_meta( $palika_share_post, $palika_share_meta, $palika_share_count );
                }
            }

            $palika_share_out['count'] = $palika_share_count;
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        echo wp_json_encode( $palika_share_out );
        exit;
    }
}

/**
 * Facebook App ID (OPTIONAL).
 * Only needed to moderate Facebook comments in one place.
 * Create one free at https://developers.facebook.com/apps and paste the ID
 * between the quotes below. The comments plugin works without it too.
 */
$palikalive_fb_app_id = '';

/**
 * Return the CSS class for an ad type.
 *
 * @param string $type Ad type.
 * @return string CSS class list.
 */
if ( ! function_exists( 'palikalive_single_ad_class' ) ) {
    function palikalive_single_ad_class( $type ) {
        if ( 'sidebar' === $type ) {
            return 'sidebar-ads';
        }

        if ( 'trending' === $type ) {
            return 'trending-ads';
        }

        if ( 'in-between' === $type ) {
            return 'in-between col-sm-4 col-12';
        }

        return 'banner-ads';
    }
}

/**
 * Query ads by ad_position taxonomy with legacy postmeta fallback.
 *
 * @param string|array $position Ad position slug or slugs.
 * @param int          $limit    Number of ads to return.
 * @return WP_Query
 */
if ( ! function_exists( 'palikalive_single_get_ad_query' ) ) {
    function palikalive_single_get_ad_query( $position, $limit = 1 ) {
        $positions = array_filter( array_map( 'sanitize_title', (array) $position ) );
        $limit     = max( 1, absint( $limit ) );

        if ( empty( $positions ) ) {
            return new WP_Query(
                array(
                    'post_type'      => 'ads',
                    'post__in'       => array( 0 ),
                    'posts_per_page' => 1,
                    'no_found_rows'  => true,
                )
            );
        }

        $base_args = array(
            'post_type'           => 'ads',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        );

        if ( taxonomy_exists( 'ad_position' ) ) {
            $taxonomy_query = new WP_Query(
                array_merge(
                    $base_args,
                    array(
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'ad_position',
                                'field'    => 'slug',
                                'terms'    => $positions,
                            ),
                        ),
                    )
                )
            );

            if ( $taxonomy_query->have_posts() ) {
                return $taxonomy_query;
            }
        }

        return new WP_Query(
            array_merge(
                $base_args,
                array(
                    'meta_query' => array(
                        array(
                            'key'     => 'ad_position',
                            'value'   => $positions,
                            'compare' => 'IN',
                        ),
                    ),
                )
            )
        );
    }
}

/**
 * Render ad markup for a single ad slot and return HTML.
 *
 * @param string|array $position Ad position slug or slugs.
 * @param string       $type     Ad type.
 * @param int          $limit    Number of ads to render.
 * @return string Rendered ad HTML.
 */
if ( ! function_exists( 'palikalive_single_render_ads' ) ) {
    function palikalive_single_render_ads( $position, $type = 'banner', $limit = 1 ) {
        $query    = palikalive_single_get_ad_query( $position, $limit );
        $ad_class = palikalive_single_ad_class( $type );

        if ( ! $query->have_posts() ) {
            return '';
        }

        ob_start();

        while ( $query->have_posts() ) {
            $query->the_post();

            $ad_id      = get_the_ID();
            $ad_link    = get_post_meta( $ad_id, 'ad_link', true );
            $ad_new_tab = get_post_meta( $ad_id, 'new_tab', true );
            $target     = ( 'yes' === $ad_new_tab ) ? ' target="_blank" rel="noopener sponsored"' : '';

            if ( ! has_post_thumbnail( $ad_id ) ) {
                continue;
            }

            $image_html = wp_get_attachment_image(
                get_post_thumbnail_id( $ad_id ),
                'full',
                false,
                array(
                    'alt'      => get_the_title( $ad_id ) ? get_the_title( $ad_id ) : __( 'Advertisement', 'palikalive' ),
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                )
            );

            if ( empty( $image_html ) ) {
                continue;
            }
            ?>
            <div class="ads <?php echo esc_attr( $ad_class ); ?>">
                <?php if ( ! empty( $ad_link ) ) : ?>
                    <a href="<?php echo esc_url( $ad_link ); ?>"<?php echo $target; ?>>
                        <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                <?php else : ?>
                    <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
            <?php
        }

        wp_reset_postdata();

        return trim( ob_get_clean() );
    }
}

get_header();

while ( have_posts() ) :
    the_post();

    $post_id     = get_the_ID();
    $theme_uri   = get_template_directory_uri();
    $dwritter    = get_post_meta( $post_id, 'writter', true );
    $dbyline     = get_post_meta( $post_id, 'byline_writter', true );
    $heading     = get_post_meta( $post_id, 'heading', true );
    // Strip trailing colon(s)/spaces like "५५–३० प्रावधान :" — the CSS
    // :after already adds a clean ":" suffix, otherwise it shows ": :".
    $heading     = preg_replace( '/[\s:：]+$/u', '', trim( (string) $heading ) );
    $sub_heading = get_post_meta( $post_id, 'sub_heading', true );
    $img_caption = get_post_meta( $post_id, 'image_caption', true );
    $pdf         = get_post_meta( $post_id, 'wp_post_attachment', true );
    $video_url   = get_post_meta( $post_id, 'video_url', true );
    $permalink   = get_permalink();
    $share_url   = rawurlencode( $permalink );
    $share_title = rawurlencode( html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ) );

    /* Real share count for the share bar (0 when nothing was shared yet). */
    $share_count = (int) get_post_meta( $post_id, '_palika_share_count', true );

    /**
     * Ad controls (slots use the site's REAL "Ad Positions" terms).
     */
    $show_top_banner_ad                  = true;  // Position: Post Banner Above Title
    $show_below_title_grid               = true;  // Position: Post Banner Below Title (max 3 in one row)
    $show_above_thumbnail_ad             = true;  // Position: Post Banner Above Thumbnail
    $show_below_thumbnail_ad             = false; // Disabled: site has no such position
    $show_above_comment_ad               = false; // OFF: no ad at the bottom of the article text
    $show_above_author_ad                = false; // Disabled: site has no such position
    $show_sidebar_top_ad                 = true;  // Position: Home Sidebar (above पालिका अपडेट)
    $show_sidebar_middle_ad              = true;  // Position: Home Sidebar2 (between पालिका अपडेट and ट्रेन्डिङ)
    $show_facebook_comments              = true;  // Facebook comments below author bio
    $disable_auto_in_between_content_ads = true;  // No auto ads inside the article text

    if ( $disable_auto_in_between_content_ads && function_exists( 'prefix_insert_post_ads' ) ) {
        remove_filter( 'the_content', 'prefix_insert_post_ads' );
    }

    $schema_enabled      = ! defined( 'RANK_MATH_VERSION' );
    $section_schema_attr = $schema_enabled ? ' itemscope itemtype="https://schema.org/NewsArticle"' : '';
    $headline_attr       = $schema_enabled ? ' itemprop="headline"' : '';
    $alt_heading_attr    = $schema_enabled ? ' itemprop="alternativeHeadline"' : '';
    $description_attr    = $schema_enabled ? ' itemprop="description"' : '';
    $article_body_attr   = $schema_enabled ? ' itemprop="articleBody"' : '';
    $date_attr           = $schema_enabled ? ' itemprop="datePublished"' : '';

    $top_banner_ad      = $show_top_banner_ad      ? palikalive_single_render_ads( 'post-banner-above-title', 'banner', 1 ) : '';
    $below_title_grid   = $show_below_title_grid   ? palikalive_single_render_ads( 'post-banner-below-title', 'banner', 3 ) : '';
    $above_thumbnail_ad = $show_above_thumbnail_ad ? palikalive_single_render_ads( 'post-banner-above-thumbnail', 'banner', 1 ) : '';
    $below_thumbnail_ad = $show_below_thumbnail_ad ? palikalive_single_render_ads( 'post-banner-below-thumbnail', 'banner', 1 ) : '';
    $above_comment_ad   = $show_above_comment_ad   ? palikalive_single_render_ads( 'in-between', 'banner', 1 ) : '';
    $above_author_ad    = $show_above_author_ad    ? palikalive_single_render_ads( 'post-banner-above-author', 'banner', 1 ) : '';
    $sidebar_top_ad     = $show_sidebar_top_ad     ? palikalive_single_render_ads( 'home-sidebar', 'sidebar', 1 ) : '';
    $sidebar_middle_ad  = $show_sidebar_middle_ad  ? palikalive_single_render_ads( 'home-sidebar2', 'sidebar', 1 ) : '';
    ?>

<section class="single-post py-2"<?php echo $section_schema_attr; ?>>

    <header class="post-header mb-3 mt-2" id="pl-post-header">
        <div class="container">

            <?php if ( ! empty( $heading ) ) : ?>
                <h4 class="heading"<?php echo $alt_heading_attr; ?>>
                    <?php echo esc_html( $heading ); ?>
                </h4>
            <?php endif; ?>

            <h1 class="title clr single-title"<?php echo $headline_attr; ?>>
                <?php the_title(); ?>
            </h1>

            <?php if ( ! empty( $sub_heading ) ) : ?>
                <p class="single-sub-heading"<?php echo $description_attr; ?>>
                    <?php echo esc_html( $sub_heading ); ?>
                </p>
            <?php endif; ?>

        </div>
    </header>

    <div class="container">
        <div class="row">

            <article class="col-lg-9 col-12" id="main-post-content"<?php echo $article_body_attr; ?>>

                <?php if ( ! empty( $top_banner_ad ) ) : ?>
                    <div class="top-banner-ads my-3 text-center palikalive-ad-slot">
                        <div class="d-flex justify-content-center">
                            <?php echo $top_banner_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $below_title_grid ) ) : ?>
                    <div class="inner-3-ads-wrapper my-3 text-center palikalive-ad-slot">
                        <div class="palikalive-ad-label"><?php echo esc_html__( '-विज्ञापन-', 'palikalive' ); ?></div>
                        <div class="pl-adrow">
                            <?php echo $below_title_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pl-share-bar">
                    <div class="pl-share-meta">
                        <div class="pl-author-mini">
                            <?php if ( ! empty( $dbyline ) && 'none' !== $dbyline && function_exists( 'the_writter_thumb' ) ) : ?>
                                <img src="<?php the_writter_thumb( $dbyline ); ?>" alt="<?php echo esc_attr( get_the_title( $dbyline ) ); ?>" width="36" height="36" class="pl-author-mini-img" loading="eager">
                            <?php else : ?>
                                <img src="<?php echo esc_url( $theme_uri . '/assets/imgs/author.png' ); ?>" alt="Palika Live" width="36" height="36" class="pl-author-mini-img" loading="eager">
                            <?php endif; ?>

                            <span class="pl-author-mini-name">
                                <?php
                                if ( empty( $dwritter ) && ( empty( $dbyline ) || 'none' === $dbyline ) ) {
                                    echo esc_html__( 'पालिका लाइभ', 'palikalive' );
                                } elseif ( ! empty( $dwritter ) && ( empty( $dbyline ) || 'none' === $dbyline ) ) {
                                    echo esc_html( $dwritter );
                                } elseif ( ! empty( $dbyline ) && 'none' !== $dbyline ) {
                                    echo '<a href="' . esc_url( get_permalink( $dbyline ) ) . '">' . esc_html( get_the_title( $dbyline ) ) . '</a>';
                                }
                                ?>
                            </span>
                        </div>

                        <span class="pl-post-date">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"<?php echo $date_attr; ?>>
                                <?php echo esc_html( get_the_date( 'M d, Y' ) ); ?>
                            </time>
                        </span>
                    </div>

                    <div class="pl-share-buttons" aria-label="Share this post">
                        <div class="pl-share-buttons-inner">

                            <span class="pl-share-count" id="pl-share-count" data-post="<?php echo esc_attr( $post_id ); ?>" title="Share count">
                                <b class="pl-share-count-n"><?php echo esc_html( number_format_i18n( $share_count ) ); ?></b>
                                <span class="pl-share-count-l"><?php echo esc_html( 1 === $share_count ? 'Share' : 'Shares' ); ?></span>
                            </span>

                            <a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url ); ?>" target="_blank" rel="noopener noreferrer" title="Facebook" class="pl-share-btn pl-facebook" data-share="facebook">
                                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                                <span class="screen-reader-text">Facebook</span>
                            </a>

                            <a href="<?php echo esc_url( 'https://twitter.com/intent/tweet?url=' . $share_url . '&text=' . $share_title ); ?>" target="_blank" rel="noopener noreferrer" title="Twitter / X" class="pl-share-btn pl-twitter" data-share="twitter">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                <span class="screen-reader-text">Twitter / X</span>
                            </a>

                            <a href="https://www.messenger.com/" target="_blank" rel="noopener noreferrer nofollow" title="Messenger" class="pl-share-btn pl-messenger" data-share="messenger">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.477 2 2 6.145 2 11.259c0 2.913 1.38 5.507 3.542 7.24.185.15.295.375.295.615l.007 1.646c0 .53.557.877 1.03.645l1.851-.825a.866.866 0 0 1 .605-.05 11.13 11.13 0 0 0 2.67.325c5.523 0 10-4.145 10-9.259S17.523 2 12 2zm6.02 7.14l-2.9 4.6c-.42.66-1.34.82-1.96.34l-2.36-1.78a.6.6 0 0 0-.72.01l-2.86 2.16c-.5.38-1.14-.22-.79-.75l2.9-4.6c.42-.66 1.34-.82 1.96-.34l2.36 1.78a.6.6 0 0 0 .72-.01l2.86-2.16c.5-.38 1.14.22.79.75z"/></svg>
                                <span class="screen-reader-text">Messenger</span>
                            </a>

                            <a href="<?php echo esc_url( 'https://wa.me/?text=' . $share_title . '%20' . $share_url ); ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp" class="pl-share-btn pl-whatsapp" data-share="whatsapp">
                                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                <span class="screen-reader-text">WhatsApp</span>
                            </a>

                            <button id="pl-copy-btn" type="button" class="pl-share-btn pl-copy" data-share="share" data-copy-url="<?php echo esc_url( $permalink ); ?>" title="Share / Copy link">
                                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
                                <span class="screen-reader-text">Share</span>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ( ! empty( $above_thumbnail_ad ) ) : ?>
                    <div class="mb-3 text-center palikalive-ad-slot">
                        <?php echo $above_thumbnail_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <?php if ( has_post_thumbnail() ) : ?>
                    <figure class="post-thumbnail mb-3">
                        <?php
                        echo get_the_post_thumbnail(
                            $post_id,
                            'large',
                            array(
                                'class'         => 'img-fluid w-100 single-featured-image',
                                'alt'           => get_the_title( $post_id ),
                                'fetchpriority' => 'high',
                                'decoding'      => 'async',
                            )
                        );
                        ?>
                        <?php if ( ! empty( $img_caption ) ) : ?>
                            <figcaption class="single-image-caption">
                                <?php echo esc_html( $img_caption ); ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endif; ?>

                <?php if ( ! empty( $below_thumbnail_ad ) ) : ?>
                    <div class="mb-3 text-center palikalive-ad-slot">
                        <?php echo $below_thumbnail_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <div class="post-container my-3">
                    <div class="single-post-content">
                        <div class="post_content" id="article-main-text">
                            <?php the_content(); ?>
                        </div>

                        <?php if ( ! empty( $pdf ) && is_array( $pdf ) && ! empty( $pdf['url'] ) ) : ?>
                            <div class="text-center my-4">
                                <a href="<?php echo esc_url( $pdf['url'] ); ?>" download class="single-pdf-download">
                                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                    PDF डाउनलोड गर्नुहोस्
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $video_url ) && function_exists( 'replaceYouTube' ) ) : ?>
                            <div class="video-wrap my-4">
                                <?php echo replaceYouTube( $video_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $above_comment_ad ) ) : ?>
                            <div class="my-3 text-center palikalive-ad-slot">
                                <?php echo $above_comment_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>

                        <div class="notice-box">
                            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                            पालिका लाइभमा प्रकाशित सामग्रीबारे कुनै गुनासो, सूचना तथा सुझाव भए हामीलाई
                            <a href="mailto:palikalivenews@gmail.com">palikalivenews@gmail.com</a>
                            मा पठाउनु होला।
                        </div>

                        <?php if ( ! empty( $above_author_ad ) ) : ?>
                            <div class="my-3 text-center palikalive-ad-slot">
                                <?php echo $above_author_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $author_name       = __( 'पालिका लाइभ', 'palikalive' );
                        $author_url        = home_url( '/' );
                        $author_bio        = '';
                        $author_image      = $theme_uri . '/assets/imgs/author.png';
                        $has_byline_author = false;

                        if ( ! empty( $dbyline ) && 'none' !== $dbyline && get_post( $dbyline ) ) {
                            $has_byline_author = true;
                            $byline_id         = absint( $dbyline );
                            $author_name       = get_the_title( $byline_id );
                            $author_url        = get_permalink( $byline_id );
                            $bio               = get_post_field( 'post_excerpt', $byline_id );

                            if ( empty( trim( $bio ) ) ) {
                                $bio = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $byline_id ) ), 30, '...' );
                            }

                            if ( empty( trim( $bio ) ) ) {
                                $byline_post = get_post( $byline_id );

                                if ( $byline_post ) {
                                    $user = get_user_by( 'slug', $byline_post->post_name );

                                    if ( $user && ! empty( $user->description ) ) {
                                        $bio = $user->description;
                                    }
                                }
                            }

                            if ( ! empty( trim( $bio ) ) ) {
                                $author_bio = $bio;
                            }
                        } elseif ( ! empty( $dwritter ) ) {
                            $author_name = $dwritter;
                        }

                        // Fall back to the post author's WP profile bio, then to a default.
                        if ( empty( trim( $author_bio ) ) ) {
                            $wp_user_bio = get_the_author_meta( 'description' );
                            if ( ! empty( trim( $wp_user_bio ) ) ) {
                                $author_bio = $wp_user_bio;
                            }
                        }

                        if ( empty( trim( $author_bio ) ) ) {
                            $author_bio = __( 'स्थानीय तह, सुशासन, अर्थ र समाजका विषयमा तथ्यपूर्ण र सन्तुलित समाचार प्रकाशित गर्न प्रतिबद्ध पालिका लाइभ सञ्चार टोली।', 'palikalive' );
                        }
                        ?>

                        <div class="author-card-wrapper">
                            <div class="author-card-left">
                                <?php if ( $has_byline_author && function_exists( 'the_writter_thumb' ) ) : ?>
                                    <img src="<?php the_writter_thumb( $dbyline ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" width="75" height="75" class="author-card-img" loading="lazy">
                                <?php else : ?>
                                    <img src="<?php echo esc_url( $author_image ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" width="75" height="75" class="author-card-img" loading="lazy">
                                <?php endif; ?>

                                <div>
                                    <span class="author-badge">लेखक</span>
                                    <h3 class="author-card-title">
                                        <a href="<?php echo esc_url( $author_url ); ?>">
                                            <?php echo esc_html( $author_name ); ?>
                                        </a>
                                    </h3>
                                    <?php if ( ! empty( trim( $author_bio ) ) ) : ?>
                                        <p class="author-card-bio">
                                            <?php echo esc_html( $author_bio ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="author-card-action">
                                <a href="<?php echo esc_url( $author_url ); ?>" class="btn-author-more">
                                    लेखकबाट थप →
                                </a>
                            </div>
                        </div>

                        <?php if ( $show_facebook_comments ) : ?>
                            <!-- Facebook Comments: between author bio and related posts. -->
                            <div class="fb-comments-wrapper my-4">
                                <h2 class="fb-comments-heading">प्रतिक्रिया दिनुहोस्</h2>
                                <div class="fb-comments"
                                     data-href="<?php echo esc_url( $permalink ); ?>"
                                     data-width="100%"
                                     data-numposts="5"
                                     data-order-by="reverse_time"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <aside class="col-lg-3 col-12 mt-4 mt-lg-0" role="complementary">

                <?php if ( ! empty( $sidebar_top_ad ) ) : ?>
                    <div class="mb-4 text-center palikalive-ad-slot">
                        <?php echo $sidebar_top_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <div class="sidebar-posts mb-4">
                    <div class="sidebar-widget-block">
                        <div class="sidebar-title">
                            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> पालिका अपडेट
                        </div>

                        <?php
                        $recent_query = new WP_Query(
                            array(
                                'post_type'           => 'post',
                                'post_status'         => 'publish',
                                'posts_per_page'      => 8,
                                'post__not_in'        => array( $post_id ),
                                'no_found_rows'       => true,
                                'ignore_sticky_posts' => true,
                            )
                        );

                        while ( $recent_query->have_posts() ) :
                            $recent_query->the_post();
                            ?>
                            <div class="sidebar-post-item sidebar-post-flex">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <a href="<?php the_permalink(); ?>" class="sidebar-thumb-link">
                                        <?php
                                        echo get_the_post_thumbnail(
                                            get_the_ID(),
                                            'thumbnail',
                                            array(
                                                'class'    => 'sidebar-thumb-img',
                                                'alt'      => get_the_title(),
                                                'loading'  => 'lazy',
                                                'decoding' => 'async',
                                            )
                                        );
                                        ?>
                                    </a>
                                <?php endif; ?>

                                <div class="sidebar-post-info">
                                    <a href="<?php the_permalink(); ?>" class="sidebar-post-link sidebar-clamp-title">
                                        <?php the_title(); ?>
                                    </a>
                                    <span class="sidebar-post-date">
                                        <?php echo esc_html( get_the_date( 'M d' ) ); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                </div>

                <?php if ( ! empty( $sidebar_middle_ad ) ) : ?>
                    <div class="mb-4 text-center palikalive-ad-slot">
                        <?php echo $sidebar_middle_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <div class="sidebar-posts mb-4">
                    <div class="sidebar-widget-block">
                        <div class="sidebar-title sidebar-title-trending">
                            <i class="bi bi-fire" aria-hidden="true"></i> ट्रेन्डिङ
                        </div>

                        <?php
                        $trending_query = new WP_Query(
                            array(
                                'post_type'           => 'post',
                                'post_status'         => 'publish',
                                'posts_per_page'      => 6,
                                'meta_key'            => 'sandesh_post_views_count',
                                'orderby'             => 'meta_value_num',
                                'order'               => 'DESC',
                                'post__not_in'        => array( $post_id ),
                                'date_query'          => array(
                                    array(
                                        'after' => '6 months ago',
                                    ),
                                ),
                                'no_found_rows'       => true,
                                'ignore_sticky_posts' => true,
                            )
                        );

                        if ( $trending_query->have_posts() ) :
                            while ( $trending_query->have_posts() ) :
                                $trending_query->the_post();
                                ?>
                                <div class="sidebar-post-item sidebar-post-flex">
                                    <?php if ( has_post_thumbnail() ) : ?>
                                        <a href="<?php the_permalink(); ?>" class="sidebar-thumb-link">
                                            <?php
                                            echo get_the_post_thumbnail(
                                                get_the_ID(),
                                                'thumbnail',
                                                array(
                                                    'class'    => 'sidebar-thumb-img',
                                                    'alt'      => get_the_title(),
                                                    'loading'  => 'lazy',
                                                    'decoding' => 'async',
                                                )
                                            );
                                            ?>
                                        </a>
                                    <?php endif; ?>

                                    <div class="sidebar-post-info">
                                        <a href="<?php the_permalink(); ?>" class="sidebar-post-link sidebar-clamp-title">
                                            <?php the_title(); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; wp_reset_postdata(); endif; ?>
                    </div>
                </div>
            </aside>
        </div>

        <div class="row my-4">
            <div class="col-12">
                <div class="related-posts-box">
                    <div class="related-heading-wrap">
                        <h2 class="related-heading">सम्बन्धित खबर</h2>
                    </div>

                    <div class="row g-3">
                        <?php
                        $post_categories = wp_get_post_categories( $post_id );

                        if ( ! empty( $post_categories ) ) :
                            $related_query = new WP_Query(
                                array(
                                    'post_type'           => 'post',
                                    'post_status'         => 'publish',
                                    'category__in'        => $post_categories,
                                    'posts_per_page'      => 4,
                                    'post__not_in'        => array( $post_id ),
                                    'no_found_rows'       => true,
                                    'ignore_sticky_posts' => true,
                                    'orderby'             => 'date',
                                    'order'               => 'DESC',
                                )
                            );

                            while ( $related_query->have_posts() ) :
                                $related_query->the_post();
                                ?>
                                <div class="col-lg-3 col-md-6 col-12">
                                    <div class="related-card">
                                        <?php if ( has_post_thumbnail() ) : ?>
                                            <a href="<?php the_permalink(); ?>" class="related-card-image-link">
                                                <?php
                                                echo get_the_post_thumbnail(
                                                    get_the_ID(),
                                                    'medium_large',
                                                    array(
                                                        'class'    => 'related-card-image',
                                                        'alt'      => get_the_title(),
                                                        'loading'  => 'lazy',
                                                        'decoding' => 'async',
                                                    )
                                                );
                                                ?>
                                            </a>
                                        <?php endif; ?>

                                        <div class="related-card-body">
                                            <h3 class="related-card-title">
                                                <a href="<?php the_permalink(); ?>">
                                                    <?php the_title(); ?>
                                                </a>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; wp_reset_postdata(); endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php if ( $show_facebook_comments ) : ?>
<div id="fb-root"></div>
<script>
// Re-parse the comments plugin once the Facebook SDK is ready
// (fixes a blank box when the page was cached or combined by optimizers).
window.fbAsyncInit = function () {
    if ( window.FB && window.FB.XFBML ) {
        window.FB.XFBML.parse();
    }
};
</script>
<script async defer crossorigin="anonymous" data-optimized="0" data-no-defer="1"
    src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v21.0<?php echo $palikalive_fb_app_id ? '&appId=' . esc_attr( $palikalive_fb_app_id ) : ''; ?>"></script>
<?php endif; ?>

<style>
.post-header .heading {
    display: inline-block;
    background: #d90429;
    color: #ffffff;
    font-size: 13px;
    line-height: 1.45;
    font-weight: 700;
    letter-spacing: 0.01em;
    padding: 4px 10px;
    border-radius: 4px;
    margin: 0 0 8px;
}
/*style kicker: red badge text followed by " :" */
.post-header .heading:after {
    content: " :";
}

/* Restore the RED BADGE on the kicker.
   A leftover hardcoded block ("रातो डब्बा हटाउने", style id="wp-custom-css"
   printed by the theme/plugins in <head>) forces:
   .heading { background:transparent !important; color:#d90429 !important }
   It cannot be removed from here, so BEAT it instead: this selector is more
   specific (three classes), is itself !important, and prints LATER in the
   page (this <style> sits in the body, after <head>) — a guaranteed win. */
.single-post .post-header .heading {
    background: #e11b22 !important;
    background-color: #e11b22 !important;
    color: #ffffff !important;
    display: inline-block !important;
    padding: 2px 14px 0 !important;
    margin: 10px 0 20px !important;
    border: 0 !important;
    border-radius: 5px !important;
    box-shadow: none !important;
    font-size: 16px !important;
    font-weight: 500 !important;
    line-height: 1.7 !important;
}
@media (max-width: 991px) {
    .single-post .post-header .heading {
        font-size: 14px !important;
        padding: 3px 10px 0 !important;
    }
}
/* Headline — this selector must OUTRANK the theme's own rule
   (.single-post .post-header .title = 63px/600/#000), otherwise theme CSS
   silently wins and nothing here applies. Keep values = theme look
   (the reference screenshots). Change font-size here only if the
   headline ever needs to be smaller/bigger — it will actually work now. */
.single-post .post-header .single-title {
    font-size: 63px;
    line-height: 1.2;
    font-weight: 600;
    color: #000000;
    margin: 10px 0 12px;
}

/* Sub-heading (v5.0.0): the red vertical bar is GONE.
   Clean lead paragraph: no bar, no box, bigger text, muted ink.
   Want a soft accent line instead? Uncomment the rule at the bottom of this
   block (it is kept there for you). */
.single-sub-heading {
    display: block;
    max-width: 820px;
    background: transparent;
    border: 0;
    border-left: 0;
    border-radius: 0;
    color: #46536b;
    font-size: 17px;
    line-height: 1.75;
    font-weight: 500;
    padding: 0;
    margin: 2px 0 16px;
}
/* OPTIONAL soft accent (uncomment the three lines below if you want it):
.single-sub-heading {
    border-left: 3px solid #cfd8e3;
    padding-left: 12px;
}
*/

/* Smart sticky headline (v5.0.0).
   .pl-head-compact = the small sticky bar that appears while reading.
   .pl-head-hidden  = the bar slides away as soon as the reader scrolls down. */
#pl-post-header.pl-head-compact {
    position: sticky;
    top: 0;
    z-index: 999;
    background: #ffffff;
    padding: 9px 0 8px;
    margin-bottom: 12px;
    box-shadow: 0 4px 14px rgba(11, 37, 69, 0.10);
    transition: transform 0.35s ease, opacity 0.25s ease;
    will-change: transform;
}
#pl-post-header.pl-head-compact .single-title {
    font-size: 18px;
    line-height: 1.35;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
#pl-post-header.pl-head-compact .single-sub-heading {
    display: none;
}
#pl-post-header.pl-head-compact .heading {
    font-size: 11.5px;
    padding: 3px 8px;
    margin-bottom: 6px;
}
#pl-post-header.pl-head-compact.pl-head-hidden {
    transform: translateY(-120%) !important;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}
body.admin-bar #pl-post-header.pl-head-compact {
    top: 32px;
}
@media (max-width: 782px) {
    body.admin-bar #pl-post-header.pl-head-compact {
        top: 46px;
    }
    #pl-post-header.pl-head-compact .single-title {
        font-size: 17px;
    }
}

.palikalive-ad-slot {
    margin: 24px 0;
}
.palikalive-ad-label {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.4;
    text-align: center;
    margin: 0 0 14px;
}
/* Below-title ad row: exactly 3 equal columns on desktop, stacked on mobile. */
.pl-adrow {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: stretch;
    gap: 8px;
}
.pl-adrow .ads {
    flex: 1 1 0;
    max-width: 32%;
}
.pl-adrow .ads a {
    display: block;
    width: 100%;
}
.pl-adrow .ads img {
    width: 100%;
    height: auto;
    display: block;
}
@media (max-width: 767px) {
    .pl-adrow .ads {
        flex-basis: 100%;
        max-width: 100%;
    }
}
.inner-3-ads-wrapper .ads img,
.top-banner-ads .ads img,
.palikalive-ad-slot .ads img {
    max-width: 100%;
    height: auto;
    display: block;
}
.inner-3-ads-wrapper .ads a,
.top-banner-ads .ads a,
.palikalive-ad-slot .ads a {
    display: inline-block;
    max-width: 100%;
}
.pl-adrow .ads a {
    display: block;
    max-width: 100%;
    width: 100%;
}
.pl-share-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 10px 0;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 18px;
}
.pl-share-meta,
.pl-author-mini,
.pl-share-buttons-inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}
.pl-share-meta {
    gap: 12px;
}
.pl-author-mini {
    gap: 8px;
}
.pl-author-mini-img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}
.pl-author-mini-name,
.pl-author-mini-name a {
    font-weight: 700;
    color: #0b2545;
    font-size: 14px;
    text-decoration: none;
}
.pl-post-date {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}
.pl-share-buttons-inner {
    gap: 6px;
}

/* Share count chip (v5.0.0) — "105 Shares" style, sits left of the icons. */
.pl-share-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    margin: 0 4px 0 0;
    background: #eef2f7;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    color: #0f172a;
    font-size: 13.5px;
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
}
.pl-share-count .pl-share-count-n {
    font-weight: 800;
    color: #0f172a;
}
.pl-share-count .pl-share-count-l {
    font-weight: 600;
    color: #64748b;
}

.pl-share-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: 700;
}
.pl-facebook { background: #1877f2; }
.pl-twitter { background: #000000; }
.pl-messenger { background: #0084ff; }
.pl-whatsapp { background: #25d366; }
.pl-copy { background: #64748b; }
.single-featured-image {
    border-radius: 8px;
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    display: block;
}
.single-image-caption {
    background: transparent;
    color: #64748b;
    font-size: 12.8px;
    line-height: 1.55;
    text-align: center;
    padding: 7px 0 0;
    border-radius: 0;
}
.post_content {
    font-family: "Mukta", "Noto Sans Devanagari", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 15px;
    line-height: 1.85;
    color: #1f2937;
    font-weight: 400;
    letter-spacing: 0;
}
.post_content p {
    margin-bottom: 1.1em;
}
.single-pdf-download {
    background: #0b2545;
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.video-wrap {
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 8px;
    overflow: hidden;
}
.video-wrap iframe {
    width: 100%;
    height: 100%;
}
.notice-box i {
    color: #0284c7;
    margin-right: 6px;
}
.notice-box a {
    color: #0b2545;
    font-weight: 700;
    text-decoration: none;
}
.author-card-wrapper .author-card-bio {
    font-size: 13px !important;
    line-height: 1.55 !important;
    color: #64748b !important;
}
.author-card-action {
    flex-shrink: 0;
}

/* Facebook comments box */
.fb-comments-wrapper {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px;
}
.fb-comments-heading {
    margin: 0 0 14px;
    padding-bottom: 8px;
    font-size: 20px;
    font-weight: 800;
    color: #0b2545;
    border-bottom: 3px solid #1877f2;
    display: inline-block;
}
.fb-comments,
.fb-comments span,
.fb-comments iframe {
    width: 100% !important;
}

.sidebar-title i {
    color: #0284c7;
}
.sidebar-title-trending i {
    color: #ea580c;
}
.sidebar-post-flex {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.sidebar-thumb-link {
    flex-shrink: 0;
}
/* Enlarged sidebar items */
.sidebar-thumb-img {
    width: 95px;
    height: 68px;
    object-fit: cover;
    border-radius: 6px;
    display: block;
}
.sidebar-post-info {
    flex: 1;
    min-width: 0;
}
.sidebar-clamp-title {
    line-height: 1.5;
    font-size: 15px;
    font-weight: 600;
    color: #0b2545;
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.sidebar-post-item {
    padding: 8px 0;
}
.sidebar-post-date {
    font-size: 12px;
    color: #94a3b8;
    display: block;
    margin-top: 4px;
}
.sidebar-posts > div > div:last-child {
    border-bottom: none !important;
}
.col-lg-3 > div:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}
.related-posts-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px;
}
.related-heading-wrap {
    border-bottom: 3px solid #0b2545;
    padding-bottom: 8px;
    margin-bottom: 16px;
}
.related-heading {
    margin: 0;
}
.related-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    height: 100%;
}
.related-card-image-link {
    display: block;
    overflow: hidden;
}
.related-card-image {
    width: 100%;
    height: 155px;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}
.related-card-image:hover {
    transform: scale(1.03);
}
.related-card-body {
    padding: 10px;
}
.related-card-title {
    line-height: 1.5;
    margin: 0 0 0;
}
.related-card-title a {
    color: #0b2545;
    text-decoration: none;
}
.related-card-date {
    font-size: 11px;
    color: #94a3b8;
}
@media (max-width: 767px) {
    .post-header .heading {
        font-size: 12.5px;
        padding: 4px 9px;
    }
    /* Mobile headline size comes from the theme itself:
       .single-post .post-header .title { font-size:30px !important } at
       <=991px, matching the reference sites. No override needed here. */
    .single-sub-heading {
        font-size: 15.5px;
        line-height: 1.7;
        margin-bottom: 14px;
    }
    .post_content {
        font-size: 15px;
        line-height: 1.8;
    }
    .pl-share-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .pl-share-buttons {
        width: 100%;
    }
}
</style>

<script>
// Share / copy-link button (v5.0.0).
// Uses the native share sheet when the device supports it; otherwise copies
// the link and flashes the button green with a "Copied!" tooltip.
document.addEventListener('click', function (event) {
    var button = event.target.closest('#pl-copy-btn');

    if (!button) {
        return;
    }

    var copyUrl = button.getAttribute('data-copy-url');

    if (!copyUrl) {
        return;
    }

    function showCopied() {
        button.style.background = '#16a34a';
        button.setAttribute('title', 'Copied!');

        setTimeout(function () {
            button.style.background = '#64748b';
            button.setAttribute('title', 'Share / Copy link');
        }, 2000);
    }

    if (navigator.share) {
        navigator.share({ title: document.title, url: copyUrl }).catch(function () {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(copyUrl).then(showCopied).catch(function () {});
            }
        });
        return;
    }

    if (navigator.clipboard) {
        navigator.clipboard.writeText(copyUrl).then(showCopied).catch(function () {});
    }
});
</script>

<script>
// Messenger button (v5.0.0).
// Mobile: opens the Messenger app with the article link ready to send.
// Desktop: the link is copied to the clipboard and messenger.com opens in a
// new tab (no app id is needed this way).
document.addEventListener('click', function (event) {
    var button = event.target.closest('.pl-messenger');

    if (!button) {
        return;
    }

    var articleUrl = window.location.href.split('#')[0];

    if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        event.preventDefault();
        window.location.href = 'fb-messenger://share/?link=' + encodeURIComponent(articleUrl);
        return;
    }

    if (navigator.clipboard) {
        navigator.clipboard.writeText(articleUrl).catch(function () {});
    }
});
</script>

<script>
// Smart sticky headline (v5.0.0).
// Runs on DOMContentLoaded on purpose: LiteSpeed/JS optimizers may move this
// script into <head>, where #pl-post-header does not exist yet — the old
// version silently did nothing for that reason.
(function () {
    function initStickyHeader() {
        var header = document.getElementById('pl-post-header');
        if (!header) { return; }

        var COMPACT_AT = 120;   // px scrolled before the small sticky bar appears
        var DELTA      = 2;     // px of movement needed to react
        var lastY      = window.pageYOffset || 0;
        var ticking    = false;

        function update() {
            ticking = false;

            var y  = window.pageYOffset || document.documentElement.scrollTop || 0;
            var dy = y - lastY;

            if (y <= COMPACT_AT) {
                header.classList.remove('pl-head-compact');
                header.classList.remove('pl-head-hidden');
            } else {
                header.classList.add('pl-head-compact');

                if (dy > DELTA) {
                    header.classList.add('pl-head-hidden');      // scrolling down -> hide
                } else if (dy < -DELTA) {
                    header.classList.remove('pl-head-hidden');   // scrolling up -> show
                }
            }

            lastY = y;
        }

        window.addEventListener('scroll', function () {
            if (ticking) { return; }
            ticking = true;
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(update);
            } else {
                setTimeout(update, 16);
            }
        }, { passive: true });

        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStickyHeader);
    } else {
        initStickyHeader();
    }
})();
</script>

<script>
// Share counter (v5.0.0).
// Reads the number printed by PHP, refreshes it once after load (so cached
// pages still show a fresh value) and increases it on every share tap.
// Data lives in the post meta "_palika_share_count" (endpoint at the top of
// this file, POST to the post URL — never cached).
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        var chip = document.getElementById('pl-share-count');
        if (!chip) { return; }

        var numEl = chip.querySelector('.pl-share-count-n');
        var labEl = chip.querySelector('.pl-share-count-l');
        var endpoint = window.location.origin + window.location.pathname;
        var postId = parseInt(chip.getAttribute('data-post'), 10) || 0;

        if (!endpoint || !postId) { return; }

        function paint(count) {
            count = parseInt(count, 10);
            if (isNaN(count) || count < 0) { count = 0; }
            if (numEl) { numEl.textContent = String(count); }
            if (labEl) { labEl.textContent = (count === 1) ? 'Share' : 'Shares'; }
        }

        function call(action, amount) {
            try {
                fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ act: action, id: postId, n: amount || 0 })
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && typeof data.count !== 'undefined') { paint(data.count); }
                    })
                    .catch(function () {});
            } catch (e) {}
        }

        // Refresh once, shortly after load (keeps the number fresh on cached pages).
        setTimeout(function () { call('get', 0); }, 1500);

        // Every share tap counts once.
        var bar = document.querySelector('.pl-share-bar');

        if (bar) {
            bar.addEventListener('click', function (event) {
                var target = event.target;

                while (target && target !== bar) {
                    if (target.className && String(target.className).indexOf('pl-share-btn') > -1) { break; }
                    target = target.parentNode;
                }

                if (!target || target === bar) { return; }

                call('add', 1);
            }, true);
        }
    });
})();
</script>

<?php
endwhile;
get_footer();
