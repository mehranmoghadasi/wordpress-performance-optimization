# ✅ WordPress Performance Checklist — 50-Point Pre-Launch Audit

> A working checklist. Score yourself honestly; the target is a habit, not a number.

**How to use:** Run this checklist before every site launch and monthly thereafter.
Score 1 point per completed item. Target: **45+ / 50** before launch.

---

## 🖥️ Server & Hosting (10 points)

- [ ] PHP version 8.1+ enabled on server
- [ ] OPcache enabled and configured (`opcache.memory_consumption=256`)
- [ ] MySQL query cache or Redis object cache active
- [ ] GZIP or Brotli compression enabled (verify at gtmetrix.com)
- [ ] Server response time (TTFB) under 200ms
- [ ] SSL/HTTPS enabled with HTTP→HTTPS redirect
- [ ] CDN configured (Cloudflare or similar) for static assets
- [ ] HTTP/2 or HTTP/3 enabled on server
- [ ] Server located geographically near target audience
- [ ] Uptime monitoring configured (UptimeRobot or similar)

---

## 🔌 WordPress Configuration (10 points)

- [ ] WP_DEBUG set to false in production (`wp-config.php`)
- [ ] Post revisions limited to 5 (`define('WP_POST_REVISIONS', 5)`)
- [ ] Autosave interval increased (`define('AUTOSAVE_INTERVAL', 300)`)
- [ ] WordPress emojis script disabled
- [ ] WordPress oEmbed disabled (if not needed)
- [ ] XML-RPC disabled
- [ ] Unused plugins deactivated and deleted
- [ ] `wp_options` table autoloaded data under 800KB
- [ ] Transients cleaned (expired transients deleted from DB)
- [ ] `wp-cron` disabled and replaced with real server cron

---

## 🖼️ Image Optimization (10 points)

- [ ] All images compressed (TinyPNG / ShortPixel / Imagify)
- [ ] WebP format served where browser supports it
- [ ] Images sized correctly for their display size (no oversized images)
- [ ] Explicit `width` and `height` attributes on all `<img>` tags (prevents CLS)
- [ ] Lazy loading enabled on all below-fold images (`loading="lazy"`)
- [ ] Hero/above-fold image preloaded (`<link rel="preload">`)
- [ ] Responsive images using `srcset` and `sizes` attributes
- [ ] No images served over HTTP on HTTPS site (mixed content)
- [ ] SVG used for logos and icons where possible
- [ ] Total image payload on homepage under 500KB

---

## 🎨 CSS & JavaScript (10 points)

- [ ] CSS minified and combined (single request where possible)
- [ ] JavaScript minified
- [ ] Non-critical JavaScript deferred or async
- [ ] Render-blocking CSS eliminated (critical CSS inlined)
- [ ] Google Fonts loaded with `font-display: swap`
- [ ] Unused CSS removed (PurgeCSS or manual audit)
- [ ] No jQuery loaded on pages that don't use it
- [ ] WooCommerce scripts loaded only on WooCommerce pages
- [ ] No duplicate scripts or styles enqueued
- [ ] Third-party scripts (chat widgets, analytics) loaded asynchronously

---

## 📊 Core Web Vitals (10 points)

- [ ] **LCP under 2.5s** — Largest Contentful Paint (hero image or heading)
- [ ] **INP under 200ms** — Interaction to Next Paint (replaced FID as a Core Web Vital in March 2024)
- [ ] **CLS under 0.1** — No layout shift from ads, fonts, or dynamic content
- [ ] **FCP under 1.8s** — First Contentful Paint
- [ ] **TTFB under 800ms** — Time to First Byte
- [ ] Google PageSpeed score **80+** on Mobile
- [ ] Google PageSpeed score **90+** on Desktop
- [ ] No mixed content warnings in browser console
- [ ] All pages pass Google Search Console Core Web Vitals report
- [ ] Performance tested on real mobile device (not just emulation)

---

## 📋 Monthly Maintenance Tasks

After launch, run these monthly:
1. Re-test PageSpeed Insights — note any regression
2. Run database optimization (WP-Optimize or WP-CLI)
3. Update WordPress core, themes, and plugins
4. Check for unused media files in the Media Library
5. Review Google Search Console for new Core Web Vitals issues
6. Check `wp_options` autoload size (`wp db query "SELECT SUM(LENGTH(option_value)) FROM wp_options WHERE autoload='yes'"`)

---

*Part of the [WordPress Performance Optimization Toolkit](https://github.com/mehranmoghadasi/wordpress-performance-optimization) by Mehran Moghadasi*
