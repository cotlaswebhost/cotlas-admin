# Cotlas Admin

A WordPress plugin providing core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.

- **Version:** 2.0.2
- **Author:** [Vinay Shukla](https://cotlas.net)
- **License:** Proprietary

## Features

- **Custom Auth System** — Login, register, and forgot-password shortcodes with AJAX handling and redirect control
- **Security Hardening** — Various WordPress security tweaks and honeypot spam protection
- **Cloudflare Turnstile** — Bot protection on forms
- **GitHub Auto-Updater** — Automatic plugin updates from GitHub releases
- **Admin UI & Dashboard** — Custom admin panel, dashboard widgets, and branding (including WP login page branding)
- **Tracking Codes** — Easy management of analytics/tracking scripts
- **Social Media** — Social media link settings
- **Trending Widgets** — Sidebar/widget area trending content
- **Comment System** — Custom comment handling
- **GenerateBlocks Tags** — Tag integration for GenerateBlocks
- **User Profile** — Extended user profile fields
- **Shortcodes** — Utility shortcodes for use in content
- **Category Features** — Featured category functionality
- **Post Formats** — Custom post format support
- **Image Optimization & Conversion** — Automatic image optimization and format conversion
- **Migration Helper** — Tools to assist with site migrations

## Installation

1. Upload the `cotlas-admin` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins** in the WordPress admin.

## Auto-Updates

This plugin supports automatic updates via GitHub releases. Updates are fetched from:
`https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest`

## Requirements

- WordPress 6.0+
- PHP 8.0+
