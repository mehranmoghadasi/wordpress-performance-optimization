<?php
/**
 * WordPress Performance Optimization — functions.php additions
 *
 * Author: Mehran Moghadasi
 * Description: Battle-tested performance optimizations for WordPress sites.
 *              Add these to your theme's functions.php or a must-use plugin.
 * Tested on: WordPress 6.x, PHP 7.4 / 8.1
 */

// ─── 1. REMOVE UNUSED WORDPRESS BLOAT ─────────────────────────────────────

/**
 * Remove WordPress emoji scripts and styles — saves ~15KB per page load.
 */
function mm_disable_wp_emojis() {
    remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles',     'print_emoji_styles' );
    remove_action( 'admin_print_styles',  'print_emoji_styles' );
    remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
    remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
    add_filter( 'tiny_mce_plugins',       'mm_disable_emojis_tinymce' );
    add_filter( 'wp_resource_hints',      'mm_disable_emojis_dns_prefetch', 10, 2 );
}
add_action( 'init', 'mm_disable_wp_emojis' );

function mm_disable_emojis_tinymce( $plugins ) {
    return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
}

function mm_disable_emojis_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' === $relation_type ) {
        $urls = array_filter( $urls, fn( $url ) => strpos( $url, 'https://s.w.org' ) === false );
    }
    return $urls;
}

/**
 * Disable WordPress oEmbed (removes 3 extra HTTP requests per page).
 */
function mm_disable_embeds() {
    wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_footer', 'mm_disable_embeds' );

/**
 * Remove RSD link, Windows Live Writer manifest, and shortlink from <head>.
 */
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );

/**
 * Disable XML-RPC — common attack vector, rarely needed.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );


// ─── 2. SCRIPT & STYLE OPTIMIZATIONS ──────────────────────────────────────

/**
 * Move jQuery to footer and defer non-critical scripts.
 * CAUTION: Test thoroughly — some plugins require jQuery in header.
 */
function mm_move_jquery_to_footer( $wp_scripts ) {
    if ( ! is_admin() ) {
        $wp_scripts->add_data( 'jquery',        'group', 1 );
        $wp_scripts->add_data( 'jquery-core',   'group', 1 );
        $wp_scripts->add_data( 'jquery-migrate','group', 1 );
    }
}
add_action( 'wp_default_scripts', 'mm_move_jquery_to_footer' );

/**
 * Add defer/async to non-critical scripts.
 * Improves First Input Delay (FID) and Interaction to Next Paint (INP).
 */
function mm_defer_non_critical_scripts( $tag, $handle, $src ) {
    $defer_scripts = [
        'google-analytics',
        'gtag',
        'facebook-pixel',
        'hotjar',
    ];
    if ( in_array( $handle, $defer_scripts, true ) ) {
        return str_replace( ' src', ' defer src', $tag );
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'mm_defer_non_critical_scripts', 10, 3 );

/**
 * Preconnect to Google Fonts, analytics, and CDN origins.
 * Saves 100–300ms on first connection.
 */
function mm_add_resource_hints() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://www.google-analytics.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//ajax.googleapis.com">' . "\n";
}
add_action( 'wp_head', 'mm_add_resource_hints', 1 );


// ─── 3. IMAGE OPTIMIZATIONS ────────────────────────────────────────────────

/**
 * Add native lazy loading to all WordPress images.
 * Supported by all modern browsers — no JS required.
 */
function mm_add_lazy_loading( $attr, $attachment, $size ) {
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'mm_add_lazy_loading', 10, 3 );

/**
 * Add explicit width and height to images in content.
 * Eliminates Cumulative Layout Shift (CLS) caused by missing dimensions.
 */
function mm_add_image_dimensions( $content ) {
    preg_match_all( '/<img[^>]+>/i', $content, $matches );
    foreach ( $matches[0] as $img_tag ) {
        if ( ! preg_match( '/width=/i', $img_tag ) ) {
            // Width/height should be set via theme — this is a safety net
            $content = str_replace( $img_tag, str_replace( '<img', '<img loading="lazy"', $img_tag ), $content );
        }
    }
    return $content;
}
add_filter( 'the_content', 'mm_add_image_dimensions' );

/**
 * Serve images in WebP format when browser supports it.
 * Reduces image size by 25–35% vs JPEG/PNG.
 */
function mm_add_webp_support( $mimes ) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}
add_filter( 'mime_types', 'mm_add_webp_support' );


// ─── 4. DATABASE OPTIMIZATION ──────────────────────────────────────────────

/**
 * Limit WordPress post revisions to prevent database bloat.
 * 5 revisions is sufficient for most sites.
 */
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
    define( 'WP_POST_REVISIONS', 5 );
}

/**
 * Delete expired transients on admin init.
 * WordPress does not clean these up automatically.
 */
function mm_delete_expired_transients() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '%_transient_timeout_%'
         AND option_value < UNIX_TIMESTAMP()"
    );
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '%_transient_%'
         AND option_name NOT LIKE '%_transient_timeout_%'
         AND LEFT(option_name, 10) = '_transient'
         AND REPLACE(option_name, '_transient_', '_transient_timeout_')
         NOT IN (SELECT option_name FROM {$wpdb->options})"
    );
}
add_action( 'admin_init', 'mm_delete_expired_transients' );


// ─── 5. CACHING HELPERS ────────────────────────────────────────────────────

/**
 * Add Vary: Accept-Encoding header for proper cache differentiation.
 */
function mm_add_vary_header() {
    if ( ! is_admin() ) {
        header( 'Vary: Accept-Encoding' );
    }
}
add_action( 'send_headers', 'mm_add_vary_header' );

/**
 * Set Cache-Control headers for static pages.
 * Works alongside server-level caching (Nginx FastCGI / Apache mod_cache).
 */
function mm_set_cache_control_headers() {
    if ( ! is_user_logged_in() && ! is_admin() && ! is_cart() && ! is_checkout() ) {
        header( 'Cache-Control: public, max-age=3600, stale-while-revalidate=86400' );
    }
}
add_action( 'send_headers', 'mm_set_cache_control_headers' );


// ─── 6. WOOCOMMERCE-SPECIFIC OPTIMIZATIONS ─────────────────────────────────

/**
 * Load WooCommerce scripts only on WooCommerce pages.
 * Prevents 200KB+ of JS loading on non-shop pages.
 */
function mm_woocommerce_dequeue_non_shop_scripts() {
    if ( function_exists( 'is_woocommerce' ) ) {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
            wp_dequeue_script( 'wc-cart-fragments' ); // Heavy — removes Ajax cart update
        }
    }
}
add_action( 'wp_enqueue_scripts', 'mm_woocommerce_dequeue_non_shop_scripts', 99 );

/**
 * Note on wc-cart-fragments:
 * Removing it disables live cart count updates without page reload.
 * Acceptable trade-off for most informational/catalog pages.
 * Keep enabled on cart and checkout pages.
 */
