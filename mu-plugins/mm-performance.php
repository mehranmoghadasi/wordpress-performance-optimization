<?php
/**
 * Plugin Name: MM Performance Tweaks
 * Description: Small, safe WordPress front-end performance tweaks. Drop this file into wp-content/mu-plugins/.
 * Version:     2.0.0
 * Author:      Mehran Moghadasi
 * License:     MIT
 * Requires at least: 6.3
 * Requires PHP: 7.4
 *
 * Every behaviour can be switched off with a filter (see README). Nothing here touches the database on
 * page load, nothing calls WooCommerce without checking it exists, and nothing runs conditional tags before
 * the main query exists.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'MM_PERF_TESTING' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------------------------------------
 * Pure decision helpers (no WordPress calls) — these are what tests/run.php exercises.
 * ------------------------------------------------------------------------------------------------------- */

/**
 * Should a public Cache-Control header be sent for this response?
 *
 * @param bool     $logged_in    Current visitor is logged in.
 * @param bool     $has_wc_state Visitor has a WooCommerce cart/session or is on cart/checkout/account.
 * @param string   $method       HTTP request method.
 * @param string[] $cookie_names Names of cookies sent with the request.
 */
function mm_perf_should_cache_publicly( bool $logged_in, bool $has_wc_state, string $method, array $cookie_names ): bool {
	if ( $logged_in || $has_wc_state || 'GET' !== strtoupper( $method ) ) {
		return false;
	}
	foreach ( $cookie_names as $name ) {
		// Any WordPress auth/session cookie or comment-author cookie means a personalised response.
		if ( 0 === strpos( $name, 'wordpress_' ) || 0 === strpos( $name, 'wp-' ) || 0 === strpos( $name, 'comment_author' ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Add `defer` to a <script> tag when it has no loading strategy yet. Used only on WordPress < 6.3.
 */
function mm_perf_defer_tag( string $tag ): string {
	if ( preg_match( '/\s(defer|async)(\s|=|>)/i', $tag ) || false === strpos( $tag, ' src=' ) ) {
		return $tag;
	}
	return preg_replace( '/<script\b/i', '<script defer', $tag, 1 );
}

/**
 * Remove s.w.org (emoji CDN) from dns-prefetch hints.
 *
 * @param string[] $urls
 */
function mm_perf_strip_emoji_prefetch( array $urls, string $relation_type ): array {
	if ( 'dns-prefetch' !== $relation_type ) {
		return $urls;
	}
	return array_values( array_filter( $urls, static function ( $url ) {
		return false === strpos( (string) $url, 's.w.org' );
	} ) );
}

if ( defined( 'MM_PERF_TESTING' ) ) {
	return; // tests load only the helpers above
}

/* ---------------------------------------------------------------------------------------------------------
 * 1. Remove head/foot cruft (emoji, oEmbed, RSD/WLW/shortlink, XML-RPC)
 * ------------------------------------------------------------------------------------------------------- */

add_action( 'init', function () {
	if ( ! apply_filters( 'mm_perf_disable_emojis', true ) ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', static function ( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
	} );
	add_filter( 'wp_resource_hints', 'mm_perf_strip_emoji_prefetch', 10, 2 );
} );

add_action( 'init', function () {
	if ( ! apply_filters( 'mm_perf_disable_oembed', true ) ) {
		return;
	}
	// Stop advertising this site as an oEmbed provider and stop loading wp-embed.js.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	add_filter( 'embed_oembed_discover', '__return_false' );
	remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
	add_filter( 'rewrite_rules_array', static function ( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	} );
	// Embedding *other* sites' content in the editor still works; only the outbound provider is removed.
}, 9999 );

add_action( 'init', function () {
	if ( ! apply_filters( 'mm_perf_remove_head_links', true ) ) {
		return;
	}
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_generator' );
} );

if ( apply_filters( 'mm_perf_disable_xmlrpc', true ) ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
}

/* ---------------------------------------------------------------------------------------------------------
 * 2. Script loading strategy
 * ------------------------------------------------------------------------------------------------------- */

/**
 * Handles to load with `defer`. Filter `mm_perf_defer_handles` to match your own enqueues.
 * WordPress >= 6.3 uses the official strategy API; older versions fall back to tag rewriting.
 */
add_action( 'wp_enqueue_scripts', function () {
	$handles = apply_filters( 'mm_perf_defer_handles', [] );
	if ( empty( $handles ) || is_admin() ) {
		return;
	}
	if ( function_exists( 'wp_script_add_data' ) && version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) ) {
		foreach ( $handles as $handle ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
		return;
	}
	add_filter( 'script_loader_tag', static function ( $tag, $handle ) use ( $handles ) {
		return in_array( $handle, $handles, true ) ? mm_perf_defer_tag( $tag ) : $tag;
	}, 10, 2 );
}, 100 );

/**
 * Preconnect only to origins you actually use. Empty by default — set via filter, e.g.
 *   add_filter( 'mm_perf_preconnect_origins', fn() => [ 'https://fonts.gstatic.com' ] );
 */
add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
	if ( 'preconnect' !== $relation_type ) {
		return $urls;
	}
	foreach ( (array) apply_filters( 'mm_perf_preconnect_origins', [] ) as $origin ) {
		$urls[] = [ 'href' => $origin, 'crossorigin' => 'anonymous' ];
	}
	return $urls;
}, 10, 2 );

/* ---------------------------------------------------------------------------------------------------------
 * 3. Images — do NOT lazy-load the first images (LCP), let core handle the rest
 * ------------------------------------------------------------------------------------------------------- */

/**
 * WordPress >= 5.5 adds loading="lazy" and width/height itself. The one thing it cannot know is how many
 * images sit above the fold on *your* theme. The first N content images are left eager (default 2);
 * core (6.3+) also gives the first one fetchpriority="high".
 */
add_filter( 'wp_omit_loading_attr_threshold', function () {
	return (int) apply_filters( 'mm_perf_eager_image_count', 2 );
} );

/**
 * Allow WebP and AVIF uploads (core supports WebP since 5.8 and AVIF since 6.5; this covers older installs).
 * This does not convert images — use an image plugin or your CDN for that.
 */
add_filter( 'upload_mimes', function ( $mimes ) {
	$mimes['webp'] = 'image/webp';
	$mimes['avif'] = 'image/avif';
	return $mimes;
} );

/* ---------------------------------------------------------------------------------------------------------
 * 4. Database hygiene — constants only; core already deletes expired transients daily
 * ------------------------------------------------------------------------------------------------------- */

if ( ! defined( 'WP_POST_REVISIONS' ) ) {
	define( 'WP_POST_REVISIONS', 5 );
}
if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
	define( 'AUTOSAVE_INTERVAL', 120 );
}
// Expired transients: core runs delete_expired_transients() on the daily `wp_scheduled_delete` event
// (since 4.9). Running your own DELETE ... LIKE on every admin page load, as many snippets do, only adds load.

/* ---------------------------------------------------------------------------------------------------------
 * 5. Cache-Control for anonymous HTML — decided at template_redirect, when conditional tags work
 * ------------------------------------------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if ( is_admin() || headers_sent() || is_feed() || is_404() || is_search() || is_preview() ) {
		return;
	}
	$has_wc_state = false;
	if ( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && function_exists( 'is_account_page' ) ) {
		$has_wc_state = is_cart() || is_checkout() || is_account_page();
	}
	if ( ! $has_wc_state && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		$has_wc_state = true;
	}
	if ( ! mm_perf_should_cache_publicly( is_user_logged_in(), $has_wc_state, $_SERVER['REQUEST_METHOD'] ?? 'GET', array_keys( $_COOKIE ) ) ) {
		return;
	}
	$max_age = (int) apply_filters( 'mm_perf_html_max_age', 300 );
	if ( $max_age > 0 ) {
		header( 'Cache-Control: public, max-age=' . $max_age . ', stale-while-revalidate=' . ( $max_age * 4 ) );
	}
} );

/* ---------------------------------------------------------------------------------------------------------
 * 6. WooCommerce — only when it is active
 * ------------------------------------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_woocommerce' ) || ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
		return;
	}
	if ( ! apply_filters( 'mm_perf_trim_woocommerce_assets', true ) ) {
		return;
	}
	if ( is_woocommerce() || is_cart() || is_checkout() || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		return;
	}
	// Non-shop pages: drop WooCommerce styles. The Ajax cart-fragments script is left alone unless you
	// opt in, because removing it breaks live header cart counts on every page.
	foreach ( [ 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style' ] as $style ) {
		wp_dequeue_style( $style );
	}
	if ( apply_filters( 'mm_perf_dequeue_cart_fragments', false ) ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}, 99 );
