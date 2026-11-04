# Cotlas Admin

A WordPress plugin providing core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.

- **Version:** 3.0.2

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
| Content Protect | `cotlas-content-protect` | Copy protection, image watermark, hotlink protection |
| Export / Import | `cotlas-export-import` | Export/import plugin settings as JSON |
| Admin Tools | `cotlas-tools` | Thumbnails · Notices · Maintenance · Database |

## Auto-Updates

This plugin supports automatic updates via GitHub releases. Updates are fetched from:
`https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest`

## Changelog

### 2.7.2
- New: **Content Protect** module — Content Copy Protection, Image Watermark, and Hotlink Protection under a single menu.
  - Copy Protection with granular controls: disable right-click, text selection, image dragging, copy shortcuts, and dev tools shortcuts — each individually toggleable.
  - Image Watermark at upload time — supports text and image (PNG) watermarks with 9-position grid, font size, color, opacity, and scale controls. Integrates with Image Conversion module: watermark is applied before WebP/AVIF conversion so converted files also contain the watermark. AVIF support via GD with Imagick fallback.
  - Hotlink Protection blocks external domains from directly linking to your images with an allowed-domains whitelist.
- New: **Export / Import** settings — Export all plugin settings to a JSON file and import on another site for fast setup replication.
- New: **Auto-Create Auth Pages** — One-click button in Login System settings to automatically create Login, Register, and Reset Password pages with the correct shortcodes.
- New: **Copy Link** button in `[social_share]` shortcode — copies the current page URL to clipboard with a custom toast notification popup.
- Improved: SEO & Schema **Images** tab now shows the Customizer logo as a fallback preview when no plugin logo is set. Added direct URL input fields and thumbnail previews for image selection.
- Improved: RSS/Atom Feed Disable now supports a **Secret Feed Slug** — set a secret word (e.g. `dailyhunt-feed`) and the normal `/feed` URLs redirect to the parent page, while the secret URL (`/dailyhunt-feed/`) serves the actual RSS feed. Works for category/tag feeds too (`/category/politics/dailyhunt-feed/`). Completely invisible to scrapers and hackers.
- Improved: Image Conversion module now serves converted AVIF images to browsers that support it (previously only served WebP).
- Fixed: Login System page creation and Content Protect save handlers no longer conflict with each other.

### 2.7.2
- New: hCaptcha integration with site key/secret key configuration and per-form toggles (login, register, comments, Cotlas forms).
- New: Math CAPTCHA difficulty levels — Easy (addition), Moderate (addition & subtraction), Advanced (addition, subtraction & multiplication).
- New: Security hardening module with 13 toggleable features migrated from mu-plugins — account lockout, user enumeration prevention, login redirect hardening, right-click guard, single session enforcement, session timeout, autocomplete hardening, password policy, REST user endpoint block, RSS feed disable, jQuery hardening, CORS allowlist, and email obfuscation.
- New: `ctap_select()` admin panel helper for dropdown fields.
- Fixed: Saving Page Hero settings no longer disables GenerateBlocks Tags (and vice versa). Each tab now has its own save handler.

## Requirements

- WordPress 6.0+
- PHP 8.0+
### 2.7.3

- Whitelabel settings added.
- Thumbnails Problem addressed. 

### 2.7.4

- Share Box on Post Edit Screen.
- Obfuscation caused image urls having @ to be hexed, we fixed it. 
- Password policy fixed.
- OG addittions Crawler exemptions.


### 3.0.0

- New: **Whitelabel** module (Admin Tools → Whitelabel tab) — replace all Cotlas branding: login/auth background images & logos, wp-admin sidebar logos (expanded + collapsed), dashboard footer credits (HTML), dashboard promo widget, and the "Latest Articles" RSS feed URL. Empty options fall back to bundled defaults; nothing changes on activation.
- New: **Social Share Box** on the post edit screen — "Share this now" box with network icons and a copy button, shown below the title in the classic editor and in the publish-confirmation panel in the block editor. Configurable under Social Media → Social share box (enable toggle, short-link toggle, per-network toggles, custom CSS box; 24px icons with per-network colors).
- New: **Social Share** shortcode gained a `urlstructure="short"` attribute to share the short URL (WP shortlink / `?p=ID`) instead of the full permalink. Documented in the Social Media → Codes tab and Shortcodes Info page.
- Improved: **Email Obfuscation** now only obfuscates text nodes (image filenames containing `@` are no longer mangled) and fully hex-encodes `mailto:` links so they still work.
- Improved: **Password Policy** current-password field is now rendered server-side and always visible; re-authentication is gated to profile/user-edit screens (fixes password changes on sites without GeneratePress).
- Improved: **OpenGraph / SEO** additions — `fb:app_id`, `theme-color`, `og:locale`, `og:site_name`, `og:logo`, `article:publisher`, `article:published_time`, `article:modified_time`, plus `og:image:alt`/width/height/type. Added Facebook App ID and Theme Color options.
- Improved: **Image Conversion** no longer serves WebP to social crawlers (WhatsApp, Facebook, Twitter, etc.) — `og:image` keeps the original JPEG/PNG so thumbnails render correctly.
- Improved: **Hotlink Protection** never blocks social/SEO crawlers, and ships with 16 built-in allowed domains (Facebook, WhatsApp, Twitter/X, LinkedIn, Pinterest, Telegram, Slack, Discord, Google, Bing, Threads, Tumblr, Reddit, Quora, Skype) merged with the custom whitelist.
- Improved: **Auth page creation** now sets the Authentication Page template automatically and auto-fills all empty option fields (slugs, redirects, rate limit) with defaults.
- Fixed: `[social_share]` default `class` attribute used commas instead of spaces, which broke all share-button CSS matching.
- Fixed: **WhatsApp og:image** thumbnail failure traced to CloudPanel nginx `pagespeed EnableFilters remove_quotes;` stripping attribute quotes — documented as a server-side fix (remove that line, reload nginx, purge caches).
- Fixed: **Latest Articles** widget now consumes the whitelabel feed URL and feed note; "Read more" follows the custom feed origin.

### 3.0.2

- New: **Contact Form** module — simple contact/newsletter form via `[cotlas_form]` shortcode with configurable fields (Name, Email, Mobile, Subject, Message), custom class, from_name/from_email overrides, and per-form-type email templates (TinyMCE) for admin and user notifications with placeholders.
- New: **SMTP Settings** module — configure SMTP (host, port, user, password, encryption, from name/email) with enable/disable and "deliver all messages" toggle; overrides form from settings for form emails and applies to all WordPress mail when enabled.
- New: **Form Submission Collection** — optional database storage of form submissions, viewable from a "Form Submissions" page (contact + newsletter sections) and a toolbar menu link, only when collection is enabled.
- New: **Master switches** added to Contact Form and Social Media modules (and verified across all other modules).
- Improved: Contact Form admin tab now auto-fills empty option fields with defaults and the save button sits below the collection section.
- Improved: Admin Tools gained "Contact Form" and "SMTP Settings" tabs (before Whitelabel) plus a "Starter Sites" tab.
- Improved: Default Site Setup notice removed from the dashboard; welcome notice retained.
