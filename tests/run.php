<?php
/**
 * Minimal test runner for the pure helpers in mu-plugins/mm-performance.php (no WordPress needed).
 *   php tests/run.php
 */
define( 'MM_PERF_TESTING', true );
require __DIR__ . '/../mu-plugins/mm-performance.php';

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	if ( ! $ok ) {
		$failures++;
	}
}

// --- Cache-Control decision -----------------------------------------------------------------------------
check( 'anonymous GET is public', mm_perf_should_cache_publicly( false, false, 'GET', [ 'PHPSESSID' ] ) );
check( 'logged-in is private', ! mm_perf_should_cache_publicly( true, false, 'GET', [] ) );
check( 'woocommerce cart state is private', ! mm_perf_should_cache_publicly( false, true, 'GET', [] ) );
check( 'POST is never public', ! mm_perf_should_cache_publicly( false, false, 'POST', [] ) );
check( 'wordpress_logged_in cookie is private', ! mm_perf_should_cache_publicly( false, false, 'GET', [ 'wordpress_logged_in_abc' ] ) );
check( 'wp-settings cookie is private', ! mm_perf_should_cache_publicly( false, false, 'GET', [ 'wp-settings-1' ] ) );
check( 'comment author cookie is private', ! mm_perf_should_cache_publicly( false, false, 'GET', [ 'comment_author_x' ] ) );
check( 'lower-case get accepted', mm_perf_should_cache_publicly( false, false, 'get', [] ) );

// --- defer tag rewriting (WP < 6.3 fallback) ------------------------------------------------------------
$tag = '<script src="https://x.test/a.js" id="a-js"></script>';
check( 'defer added once', mm_perf_defer_tag( $tag ) === '<script defer src="https://x.test/a.js" id="a-js"></script>' );
check( 'async tag untouched', mm_perf_defer_tag( '<script async src="a.js"></script>' ) === '<script async src="a.js"></script>' );
check( 'defer tag untouched', mm_perf_defer_tag( '<script defer src="a.js"></script>' ) === '<script defer src="a.js"></script>' );
check( 'inline script untouched', mm_perf_defer_tag( '<script>var a=1;</script>' ) === '<script>var a=1;</script>' );
$two = "<script src='a.js'></script>\n<script src='b.js'></script>";
check( 'only the first script in a tag string gets defer', substr_count( mm_perf_defer_tag( $two ), 'defer' ) === 1 );

// --- emoji dns-prefetch stripping -------------------------------------------------------------------------
$urls = [ 'https://s.w.org', 'https://fonts.gstatic.com' ];
check( 'emoji prefetch removed', mm_perf_strip_emoji_prefetch( $urls, 'dns-prefetch' ) === [ 'https://fonts.gstatic.com' ] );
check( 'other relation types untouched', mm_perf_strip_emoji_prefetch( $urls, 'preconnect' ) === $urls );

echo PHP_EOL . ( $failures ? "$failures FAILED" : 'all passed' ) . PHP_EOL;
exit( $failures ? 1 : 0 );
