<?php
/**
 * Plugin Name: Cotlas Admin
 * Plugin URI:  https://cotlas.net
 * Description: Core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.
 * Version:     2.0.5
 * Author:      Vinay Shukla
 * Author URI:  https://cotlas.net
 * License:     Proprietary
 * Update URI:  https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest
 * Text Domain: cotlas-admin
 */

defined( 'ABSPATH' ) || exit;
// ---------------------------------------------------------------------------
// Custom Auth System (login / register / forgot-password shortcodes)
// ---------------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-redirects.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-forms.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-ajax.php';

// GitHub Updater – automatic updates from GitHub releases
define( 'COTLAS_ADMIN_FILE', __FILE__ );
require_once plugin_dir_path( __FILE__ ) . 'inc/github-updater.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/security.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/wp-login-branding.php';

// Admin Dashboard – widgets, welcome notice, starter-kit installer, feed widget
// ---------------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'inc/admin-dashboard.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/honeypot.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/turnstile.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/tracking-codes.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/admin-ui.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/admin-panel.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/social-media.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/migration-helper.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/trending-widgets.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/comment-system.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/generateblocks-tags.php';

require_once plugin_dir_path( __FILE__ ) . 'inc/user-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/category-features.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/post-formats.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/image-optimization.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/image-conversion.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/reading-list.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/wishlist.php';
