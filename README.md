# WordPress Performance Toolkit

> A must-use plugin of safe front-end tweaks, a corrected Apache `.htaccess`, an nginx FastCGI-cache snippet, and a 50-point checklist. Every claim in this README is something you can verify in the code.

[![WordPress 6.3+](https://img.shields.io/badge/WordPress-6.3%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-15%20passing-brightgreen)](tests/run.php)

## What is in here

| Path | What it does |
|---|---|
| `mu-plugins/mm-performance.php` | Drop-in must-use plugin: removes emoji/oEmbed/head cruft, disables XML-RPC, `defer` for the scripts *you* name, opt-in preconnects, keeps the first images eager for LCP, allows WebP/AVIF uploads, sane revision/autosave constants, `Cache-Control` for anonymous HTML decided at `template_redirect`, and WooCommerce style trimming on non-shop pages — guarded so it cannot fatal without WooCommerce. |
| `apache/.htaccess` | Brotli/gzip, long-lived static asset caching with `immutable`, CORS for fonts, security headers, sensitive-file denial in Apache 2.4 syntax. |
| `nginx/nginx-cache.conf` | FastCGI page cache with correct bypass rules for logged-in users, WooCommerce cookies, REST, feeds and query strings; `X-Cache` header for debugging. |
| `checklist/performance-checklist.md` | 50-point pre-launch and monthly checklist (server, WordPress config, images, CSS/JS, Core Web Vitals). |
| `tests/run.php` | 15 checks on the plugin's decision logic, runnable with plain `php` — no WordPress install needed. |

## Install

**Plugin** — copy one file; must-use plugins load automatically and cannot be deactivated from the admin:

```bash
cp mu-plugins/mm-performance.php /path/to/wp-content/mu-plugins/
```

**Apache** — merge `apache/.htaccess` into the site's `.htaccess` (keep any lines a caching plugin added).

**nginx** — add the `fastcgi_cache_path` / `fastcgi_cache_key` lines from the top of `nginx/nginx-cache.conf` to `http {}`, then `include` the file inside the site's `server {}` and reload.

## Configuring the plugin

Everything is a filter; add these to a small site plugin or your theme's `functions.php`:

```php
// Scripts to load with defer (handles as enqueued). Uses the WP 6.3 strategy API.
add_filter( 'mm_perf_defer_handles', fn() => [ 'my-analytics', 'my-chat-widget' ] );

// Origins worth a preconnect on every page (empty by default — do not preconnect to things you do not use).
add_filter( 'mm_perf_preconnect_origins', fn() => [ 'https://fonts.gstatic.com' ] );

// How many content images stay eager (above the fold). Default 2.
add_filter( 'mm_perf_eager_image_count', fn() => 1 );

// Cache-Control max-age for anonymous HTML, seconds. Default 300; 0 disables the header.
add_filter( 'mm_perf_html_max_age', fn() => 600 );

// Opt in to dropping the WooCommerce cart-fragments script on non-shop pages
// (saves an Ajax call per page view; disables the live header cart count there).
add_filter( 'mm_perf_dequeue_cart_fragments', '__return_true' );

// Switches: mm_perf_disable_emojis, mm_perf_disable_oembed, mm_perf_remove_head_links,
// mm_perf_disable_xmlrpc, mm_perf_trim_woocommerce_assets — all default true.
add_filter( 'mm_perf_disable_xmlrpc', '__return_false' ); // e.g. if you use Jetpack
```

## Why the plugin looks the way it does

- **Cache headers are decided on `template_redirect`, not `send_headers`.** `send_headers` fires before the main query runs, so `is_cart()` / `is_page()` there always return false (and WordPress logs a "doing it wrong" notice). The decision itself is a pure function, `mm_perf_should_cache_publicly()`, so it is unit-tested: logged-in users, any `wordpress_*` / `wp-*` / `comment_author*` cookie, WooCommerce cart state, and non-GET requests all get no public caching.
- **Images: core already does lazy-loading and width/height.** Since 5.5 WordPress adds `loading="lazy"` and dimensions to content images itself. Forcing `loading="lazy"` on *every* image (a common snippet) delays the hero image and worsens LCP. The plugin only sets `wp_omit_loading_attr_threshold` so the first images stay eager.
- **Expired transients: core deletes them daily** (`delete_expired_transients()` on `wp_scheduled_delete`, since 4.9). Running `DELETE … LIKE` on `wp_options` on every admin page load, as many snippets do, adds load and removes nothing extra. The plugin does not touch the database.
- **WooCommerce functions are always guarded** with `function_exists()`. Calling `is_cart()` on a site without WooCommerce is a fatal error.
- **`.htaccess` fixes:** `Header append Vary "Accept-Encoding"` (the widespread `Vary:` with a colon is a syntax error), Apache 2.4 `Require all denied` with a 2.2 fallback, no `no-store` on `.php` (it would override the `Cache-Control` WordPress sends), no deprecated `X-XSS-Protection`, and `xmlrpc.php` is not hard-blocked because Jetpack and the mobile apps need it.

## Measuring

Use [PageSpeed Insights](https://pagespeed.web.dev/) or WebPageTest before and after, on the same URL, in the same device mode, and read the **field data** (CrUX) rather than a single lab run. This repository does not publish before/after numbers because they depend entirely on the theme, plugins and hosting of the site you apply it to.

## Development

```bash
php -l mu-plugins/mm-performance.php
php tests/run.php
```

`ci/ci.yml` is a GitHub Actions workflow running both on PHP 7.4 and 8.3 — copy it to `.github/workflows/`.

## Limitations

- The plugin is deliberately small. It does not minify, concatenate, generate critical CSS, convert images, or page-cache; use a caching plugin or your CDN for those.
- `.htaccess` and nginx snippets assume you control the server config; managed hosts often override both.
- Tests cover the pure decision functions. Hook wiring is exercised only by installing the plugin on a site.

## Changelog

- **2.0.0 (2026-09-20)** — Rewritten as a must-use plugin with filters. Fixed: unguarded `is_cart()`/`is_checkout()` (fatal without WooCommerce) and their use on `send_headers` where conditionals cannot work; a function that claimed to add image dimensions but only injected `loading="lazy"`; forced lazy-loading of above-the-fold images; per-admin-page-load `DELETE … LIKE` on `wp_options`; partial oEmbed removal; hard-coded Google Fonts/analytics preconnects; `.htaccess` `Vary:` syntax error, Apache 2.2 access syntax, deprecated `X-XSS-Protection`, and a `no-store` rule that overrode WordPress cache headers. Added the nginx config and lazy-load file the old README listed but did not contain (lazy-load JS dropped as obsolete — native `loading` is universal), tests, CI, LICENSE. Removed unverifiable before/after results and case-study figures.
- **1.0.0** — functions.php snippet, `.htaccess`, checklist.

## License

MIT — see [LICENSE](LICENSE).

## About the author

**Mehran Moghadasi** — Digital Marketing & Brand Manager (SEO · Google Ads · Meta Ads · Social Media), Calgary, AB.
[github.com/mehranmoghadasi](https://github.com/mehranmoghadasi) · [linkedin.com/in/mehranmoghadasi](https://www.linkedin.com/in/mehranmoghadasi)
