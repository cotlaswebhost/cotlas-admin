# Cotlas Admin

A WordPress plugin providing core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.

- **Version:** 2.3.6
- **Author:** [Vinay Shukla](https://cotlas.net)
- **License:** Proprietary

## Features

### Auth & Security

- **Custom Auth System** — `[cotlas_login]`, `[cotlas_register]`, `[cotlas_forgot_password]`, and `[cotlas_auth_panel]` shortcodes with full AJAX handling, role-based redirects, nonce verification, and rate limiting.
- **Security Hardening** — WordPress security tweaks (XML-RPC, user enumeration, file editing, etc.).
- **Honeypot Protection** — Invisible honeypot fields on login, register, and comment forms to silently reject bot submissions.
- **Cloudflare Turnstile** — Bot-protection widget on WP default and Cotlas custom forms (login, register, comments).
- **Google reCAPTCHA v3** — Invisible reCAPTCHA v3 on WP default and Cotlas custom forms with configurable score threshold. Custom forms skip the default WP hook verification to prevent double-verification conflicts.
- **Math CAPTCHA** — Simple addition-question fallback for sites without third-party CAPTCHA keys.

### Admin Panel & Dashboard

- **Unified Admin Panel** — Single top-level "Cotlas Admin" menu with tabbed sub-pages covering every module.
- **Admin Dashboard** — Custom dashboard widgets, welcome notice, and starter-kit installer.
- **WP Login Branding** — Custom logo and styles on the WordPress login page.
- **Admin UI** — Additional admin UI improvements and helpers.

### Content & Site Features

- **SEO & Schema Settings** — Dedicated SEO module with OpenGraph output, JSON-LD schema graph generation, breadcrumb schema/shortcode support, post-type schema mapping, SEO-plugin compatibility (Yoast, Rank Math, SEOPress, AIOSEO), and GenerateBlocks dynamic-tag integration.
- **Tracking Codes** — Manage GA4, Google Search Console, Bing, AdSense, Facebook Pixel, X Pixel, Quora Pixel, LinkedIn Insight Tag, and custom header/footer scripts.
- **Social Media** — Store and output social media profile URLs (Facebook, Twitter/X, YouTube, Instagram, LinkedIn, Threads, WhatsApp).
- **Trending Widgets** — Sidebar/widget area trending content.
- **Comment System** — Custom threaded comment rendering with coloured-initials avatars, inline editing, and spam protection.
- **GenerateBlocks Tags** — Dynamic tag integration for GenerateBlocks.
- **User Profile** — Extended user profile fields and avatar support.
- **Shortcodes** — Utility shortcodes for company info, login links, category data, post marquee, and more.
- **Category Features** — Featured category functionality with location/state taxonomy support.
- **Post Formats** — Custom post format support and "rename Post to News" option.
- **Reading List & Wishlist** — Frontend reading list and wishlist features for logged-in users.

### Performance

- **Image Optimization** — Automatic image compression on upload.
- **Image Conversion** — Convert uploaded images to modern formats (WebP/AVIF).
- **Browser Cache** *(new)* — Adds `Expires` and `Cache-Control` headers for CSS, JS, images, and fonts with configurable lifetimes per asset type. Includes a cache-busting version query string (`?cv=`) and a "Clear Cache" button that sends `Clear-Site-Data: "cache"` to flush the admin's browser instantly.

### Admin Tools *(new)*

A dedicated **Cotlas Admin → Admin Tools** page with four modules:

- **Regenerate Thumbnails** — Batch AJAX regeneration of all image sizes with a live progress bar, per-image status, elapsed time, and error count. Processes 5 images per request to avoid PHP timeouts.
- **Admin Notice Manager** — Per-type toggles to suppress core update banners, plugin/theme update counts, PHP deprecation warnings, and WSOD recovery notices. Includes a "Reset to Default" button.
- **Maintenance Mode** — Toggle a 503 maintenance page with a custom HTML message, per-role bypass (Administrators always bypass), and per-IP whitelist. An orange admin bar indicator is shown to admins when maintenance mode is active.
- **Database Cleanup** — Live stats and individual cleanup buttons for post revisions (keeps 2 most-recent per post), spam comments, trashed comments, orphaned post meta, and expired transients. Also includes a "Optimize Tables" action and a "Run All Cleanups" button that fires all actions sequentially.

### Other

- **GitHub Auto-Updater** — Automatic plugin updates from GitHub releases.
- **Migration Helper** — Tools to assist with site migrations.

## Installation

1. Upload the `cotlas-admin` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins** in the WordPress admin.

## Admin Panel Pages

| Menu Page | Slug | Description |
|---|---|---|
| Site Settings | `cotlas-admin-panel` | Company info and shortcode reference |
| Login System | `cotlas-login-system` | Custom auth slugs, redirects, rate limit, CAPTCHA toggles |
| Category Enhancements | `cotlas-category-enhancements` | Location/taxonomy features |
| Comment System | `cotlas-comment-system` | Custom comment module toggle |
| GenerateBlocks Tags | `cotlas-gb-tags` | Dynamic tag module toggle |
| Security Settings | `cotlas-security-settings` | Turnstile, reCAPTCHA v3, Math CAPTCHA, Honeypot |
| Image Optimization | `cotlas-image-optimization` | Compression settings |
| Post Formats | `cotlas-post-formats` | Post format and rename options |
| Social Media | `cotlas-social-media` | Social profile URLs |
| SEO & Schema Settings | `cotlas-seo-schema-settings` | OpenGraph, schema, SEO compatibility, and breadcrumb controls |
| Tracking Codes | `cotlas-tracking-codes` | Analytics and pixel scripts |
| User Settings | `cotlas-user-settings` | Profile, avatar, and social link options |
| Reading List | `cotlas-reading-list` | Reading list and wishlist toggles |
| Browser Cache | `cotlas-cache` | Cache headers, lifetimes, and purge button |
| Admin Tools | `cotlas-tools` | Thumbnails · Notices · Maintenance · Database |

## Auto-Updates

This plugin supports automatic updates via GitHub releases. Updates are fetched from:
`https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest`

## Requirements

- WordPress 6.0+
- PHP 8.0+
