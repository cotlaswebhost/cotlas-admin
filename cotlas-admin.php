<?php
/**
 * Plugin Name: Cotlas Admin
 * Plugin URI:  https://cotlas.net
 * Description: Core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.
 * Version:     2.3.2
 * Author:      Vinay Shukla
 * Author URI:  https://cotlas.net
 * License:     Proprietary
 * Update URI:  https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest
 * Text Domain: cotlas-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seed missing toggle options with deterministic defaults on activation.
 *
 * This only creates options that do not exist yet and never overwrites
 * existing values from already-configured sites.
 */
function cotlas_admin_activate_seed_toggle_defaults() {
	$toggle_defaults = array(
		// Core module toggles.
		'cotlas_auth_enabled'                           => 0,
		'cotlas_category_features_enabled'              => 0,
		'cotlas_comment_system_enabled'                 => 0,
		'cotlas_gb_tags_enabled'                        => 0,
		'cotlas_image_optimization_enabled'             => 0,
		'cotlas_post_formats_enabled'                   => 0,
		'cotlas_user_profile_enabled'                   => 0,
		'cotlas_user_avatar_enabled'                    => 0,
		'cotlas_user_social_links_enabled'              => 0,
		'cotlas_reading_list_enabled'                   => 0,
		'cotlas_wishlist_enabled'                       => 0,
		'cotlas_cache_enabled'                          => 0,

		// Content feature toggles.
		'cotlas_taxonomy_location_enabled'              => 0,
		'cotlas_taxonomy_state_city_enabled'            => 0,
		'cotlas_rename_post_to_news'                    => 0,

		// Image conversion toggles.
		'cotlas_imgconv_enabled'                        => 0,
		'cotlas_imgconv_delete_original'                => 0,

		// Security provider toggles.
		'turnstile_enable_login'                        => 0,
		'turnstile_enable_register'                     => 0,
		'turnstile_enable_comments'                     => 0,
		'recaptcha_v3_enable_login'                     => 0,
		'recaptcha_v3_enable_register'                  => 0,
		'recaptcha_v3_enable_comments'                  => 0,
		'math_captcha_enable_login'                     => 0,
		'math_captcha_enable_register'                  => 0,
		'math_captcha_enable_comments'                  => 0,
		'cotlas_honeypot_wp_login'                      => 0,
		'cotlas_honeypot_wp_register'                   => 0,
		'cotlas_honeypot_cotlas_comments'               => 0,

		// Custom auth anti-spam toggles.
		'cotlas_auth_honeypot'                          => 0,
		'cotlas_auth_turnstile_login'                   => 0,
		'cotlas_auth_turnstile_register'                => 0,
		'cotlas_auth_recaptcha_login'                   => 0,
		'cotlas_auth_recaptcha_register'                => 0,
		'cotlas_auth_math_captcha_login'                => 0,
		'cotlas_auth_math_captcha_register'             => 0,

		// Admin tools toggles.
		'cotlas_hide_core_updates'                      => 0,
		'cotlas_hide_plugin_updates'                    => 0,
		'cotlas_hide_theme_updates'                     => 0,
		'cotlas_hide_php_warnings'                      => 0,
		'cotlas_hide_wsod_notices'                      => 0,
		'cotlas_maintenance_enabled'                    => 0,

		// SEO module toggles.
		'cotlas_seo_enable_module'                     => 0,
		'cotlas_seo_enable_opengraph'                  => 0,
		'cotlas_seo_enable_schema'                     => 0,
		'cotlas_seo_enable_breadcrumb_schema'          => 0,
		'cotlas_seo_enable_author_schema'              => 0,
		'cotlas_seo_enable_faq_schema'                 => 0,
		'cotlas_seo_enable_local_business_schema'      => 0,
		'cotlas_seo_enable_search_action_schema'       => 0,
		'cotlas_seo_enable_twitter_cards'              => 0,
		'cotlas_seo_enable_seo_plugin_integration'     => 0,
		'cotlas_seo_enable_custom_post_type_detection' => 0,
		'cotlas_seo_enable_breadcrumb_shortcode'       => 0,
		'cotlas_seo_use_seo_plugin_title'              => 0,
		'cotlas_seo_use_seo_plugin_description'        => 0,
	);

	/**
	 * Filter activation-time toggle defaults.
	 *
	 * @param array<string,int> $toggle_defaults Toggle options and defaults.
	 */
	$toggle_defaults = apply_filters( 'cotlas_admin_activation_toggle_defaults', $toggle_defaults );

	foreach ( $toggle_defaults as $option_key => $default_value ) {
		if ( false === get_option( $option_key, false ) ) {
			add_option( $option_key, absint( $default_value ) );
		}
	}
}
register_activation_hook( __FILE__, 'cotlas_admin_activate_seed_toggle_defaults' );
// ---------------------------------------------------------------------------
// Custom Auth System (login / register / forgot-password shortcodes)
// ---------------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/auth-redirects.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/captcha.php';
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
require_once plugin_dir_path( __FILE__ ) . 'inc/seo/bootstrap.php';

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
require_once plugin_dir_path( __FILE__ ) . 'inc/cache-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/admin-tools.php';
