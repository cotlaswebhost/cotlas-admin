<?php
/**
 * Plugin Name: Cotlas Admin
 * Plugin URI:  https://cotlas.net
 * Description: Core admin customizations, security hardening, site settings, shortcodes, and utility features for Cotlas client sites.
 * Version:     1.0.1
 * Author:      Vinay Shukla
 * Author URI:  https://cotlas.net
 * License:     Proprietary
 * Update URI:  https://api.github.com/repos/cotlaswebhost/cotlas-admin/releases/latest
 * Text Domain: cotlas-admin
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// GitHub Updater
// Public repo — no token required. Optionally define COTLAS_GITHUB_TOKEN in wp-config.php for higher API rate limits.
// ---------------------------------------------------------------------------
if ( is_admin() ) {
    new Cotlas_GitHub_Updater( __FILE__ );
}

class Cotlas_GitHub_Updater {

    private $file;
    private $plugin_slug;
    private $plugin_data = array();

    /** Change this to your GitHub username/repo-name */
    private $github_repo = 'cotlaswebhost/cotlas-admin';

    public function __construct( $file ) {
        $this->file        = $file;
        $this->plugin_slug = plugin_basename( $file );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );
    }

    private function load_plugin_data() {
        if ( empty( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->file );
        }
    }

    /**
     * Inject GitHub auth token for all requests to github.com.
     * Required for private repositories.
     */
    public function add_auth_header( $args, $url ) {
        $token = defined( 'COTLAS_GITHUB_TOKEN' ) ? COTLAS_GITHUB_TOKEN : '';
        if ( $token && false !== strpos( $url, 'github.com' ) ) {
            if ( ! isset( $args['headers'] ) ) {
                $args['headers'] = array();
            }
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        return $args;
    }

    /**
     * Fetch latest release info from GitHub API.
     */
    private function get_release_info() {
        static $release = null;

        if ( null !== $release ) {
            return $release;
        }

        $args = array(
            'headers' => array(
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
            'timeout' => 10,
        );

        $token = defined( 'COTLAS_GITHUB_TOKEN' ) ? COTLAS_GITHUB_TOKEN : '';
        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $url      = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            $release = false;
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );
        return $release;
    }

    /**
     * Tell WordPress there is an update available.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $this->load_plugin_data();
        $release = $this->get_release_info();

        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'v' );

        if ( version_compare( $this->plugin_data['Version'], $remote_version, '<' ) ) {
            // Prefer a release asset ZIP; fall back to source zipball.
            $zip_url = $release['zipball_url'];
            if ( ! empty( $release['assets'] ) ) {
                foreach ( $release['assets'] as $asset ) {
                    if ( isset( $asset['content_type'] ) && $asset['content_type'] === 'application/zip' ) {
                        $zip_url = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . $this->github_repo,
                'package'     => $zip_url,
                'icons'       => array(),
            );
        }

        return $transient;
    }

    /**
     * Show plugin info in the update popup.
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( dirname( $this->plugin_slug ) !== $args->slug ) {
            return $result;
        }

        $this->load_plugin_data();
        $release = $this->get_release_info();

        if ( ! $release ) {
            return $result;
        }

        return (object) array(
            'name'          => $this->plugin_data['Name'],
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => ltrim( $release['tag_name'], 'v' ),
            'author'        => $this->plugin_data['Author'],
            'homepage'      => $this->plugin_data['PluginURI'],
            'sections'      => array(
                'description' => $this->plugin_data['Description'],
                'changelog'   => nl2br( isset( $release['body'] ) ? esc_html( $release['body'] ) : '' ),
            ),
            'download_link' => isset( $release['zipball_url'] ) ? $release['zipball_url'] : '',
        );
    }

    /**
     * After install: rename GitHub's auto-generated folder to cotlas-admin/.
     * GitHub source ZIPs extract to something like USERNAME-cotlas-admin-abc123/.
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $result;
        }

        $dest = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );

        if ( $wp_filesystem->exists( $dest ) ) {
            $wp_filesystem->delete( $dest, true );
        }

        $wp_filesystem->move( $result['destination'], $dest );
        $result['destination'] = $dest;

        return $result;
    }
}


function is_valid_email_domain($login, $email, $errors ){
    $valid_email_domains = array("gmail.com","yahoo.com","yahoo.co.in","yahoo.co.uk","outlook.com","hotmail.com","live.com");// whitelist
    $valid = false;
    foreach( $valid_email_domains as $d ){
        $d_length = strlen( $d );
        $current_email_domain = strtolower( substr( $email, -($d_length), $d_length));
        if( $current_email_domain == strtolower($d) ){
            $valid = true;
            break;
        }
    }
    // if invalid, return error
    if( $valid === false ){
        $errors->add('domain_whitelist_error',__( '<strong>ERROR</strong>: you can only register using gmail, yahoo, outlook, hotmail or live' ));
    }
}

add_action('register_post', 'is_valid_email_domain',10,3 );


function cotlas_custom_login_styles() {
    $background_image = 'http://cotlas.net/wp-content/uploads/2026/05/login-banner.webp';
    $logo_image = 'http://cotlas.net/wp-content/uploads/2026/05/cotlas-logo-full.png';
    ?>
    <style type="text/css">
        body.login {
            min-height: 100vh;
            background: #eef3f9 url('<?php echo esc_url($background_image); ?>') center center / cover no-repeat fixed;
            color: #1e293b;
        }

        body.login div#login {
            position: relative;
            width: min(380px, calc(100% - 32px));
            padding: 100px 0 32px;
        }

        body.login h1 {
            margin-bottom: 22px;
        }

        body.login h1 a {
            width: 150px;
            height: 35px;
            margin: 0 auto;
            background: url('<?php echo esc_url($logo_image); ?>') center center / contain no-repeat;
        }

        .login #login_error,
        .login .message,
        .login .success {
            margin: 0 0 18px;
            border: 0;
            border-left: 4px solid #60a5fa;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 0 20px 50px rgba(8, 15, 30, 0.16);
            color: #1f2937;
        }

        .login form {
            margin-top: 0;
            padding: 28px 28px 24px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 6px;
            background: #f4faff;
            box-shadow: 0 28px 80px rgba(8, 15, 30, 0.22);
            backdrop-filter: blur(16px);
        }

        .login label {
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .login form .input,
        .login input[type="text"],
        .login input[type="password"] {
            min-height: 50px;
            margin-top: 6px;
            border: 1px solid #d7deea;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: none;
            color: #0f172a;
            font-size: 15px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .login form .input:focus,
        .login input[type="text"]:focus,
        .login input[type="password"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14);
        }

        .login .button.wp-hide-pw {
            color: #64748b;
        }

        .login .forgetmenot {
            margin-top: 6px;
        }

        .login .forgetmenot label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-weight: 500;
        }

        .login .button-primary {
            min-height: 48px;
            padding: 0 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.28);
            text-shadow: none;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .login .button-primary:hover,
        .login .button-primary:focus {
            transform: translateY(-1px);
            filter: brightness(1.04);
            box-shadow: 0 18px 34px rgba(37, 99, 235, 0.34);
        }

        .login #nav,
        .login #backtoblog {
            margin: 18px 0 0;
            padding: 0 4px;
            text-align: left;
        }

        .login #nav a,
        .login #backtoblog a {
            color: #334155;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.18s ease;
        }

        .login #nav a:hover,
        .login #backtoblog a:hover,
        .login #nav a:focus,
        .login #backtoblog a:focus {
            opacity: 0.8;
        }
        .login #backtoblog a:hover, .login #nav a:hover, .login h1 a:hover {
            color: #8bceff;
        }
        .login .privacy-policy-page-link {
            margin-top: 16px;
        }

        .login .privacy-policy-page-link a {
            color: #64748b;
        }

        @media (max-width: 480px) {
            body.login div#login {
                width: calc(100% - 75px);
                padding-top: 120px;
            }

            .login form {
                padding: 22px 18px 18px;
                border-radius: 20px;
            }

            body.login h1 a {
                width: 150px;
                height: 35px;
            }
        }
    </style>
    <?php
}
add_action('login_head', 'cotlas_custom_login_styles');

add_filter('login_headerurl', function () {
    return home_url('/');
});

add_filter('login_headertext', function () {
    return get_bloginfo('name');
});

add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter('upload_mimes','restrict_mime'); 
function restrict_mime($mimes) { 
$mimes = array( 
                'jpg|jpeg|jpe' => 'image/jpeg', 
                'gif' => 'image/gif', 
			    'png' => 'image/png',
  				'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
  				'avif' => 'image/avif',
  				'pdf' => 'application/pdf',
                'mp3' => 'audio/mpeg',
  				'txt' => 'text/plain',
);
return $mimes;
}



function wps_login_error() {
		  remove_action('login_head', 'wp_shake_js', 12);
		}
add_action('login_head', 'wps_login_error');


add_filter( 'show_admin_bar', '__return_false' );

add_action( 'wp_dashboard_setup', 'cotlas_remove_dashboard_widgets' );
add_action( 'wp_dashboard_setup', 'cotlas_register_dashboard_ad_widget' );
add_action( 'wp_dashboard_setup', 'cotlas_register_dashboard_feed_widget' );
add_action( 'wp_dashboard_setup', 'cotlas_move_dashboard_feed_widget_to_side', 100 );
add_action( 'admin_notices', 'cotlas_render_dashboard_welcome_notice' );
add_action( 'admin_notices', 'cotlas_render_default_site_setup_notice' );
add_action( 'admin_menu', 'cotlas_register_default_site_setup_menu' );
add_action( 'wp_ajax_cotlas_dismiss_dashboard_welcome_notice', 'cotlas_dismiss_dashboard_welcome_notice' );
add_action( 'wp_ajax_cotlas_dismiss_default_site_setup_notice', 'cotlas_dismiss_default_site_setup_notice' );
add_action( 'wp_ajax_cotlas_install_default_site_set', 'cotlas_install_default_site_set' );
add_action( 'wp_ajax_cotlas_activate_default_site_asset', 'cotlas_activate_default_site_asset' );

function cotlas_remove_dashboard_widgets() {

	remove_meta_box( 'dashboard_primary','dashboard','side' ); // WordPress.com Blog
	remove_meta_box( 'dashboard_plugins','dashboard','normal' ); // Plugins
	remove_meta_box( 'dashboard_right_now','dashboard', 'normal' ); // Right Now
	remove_action( 'welcome_panel','wp_welcome_panel' ); // Welcome Panel
    remove_action('admin_notices', 'update_nag');
    remove_meta_box( 'advads_dashboard_widget', 'dashboard', 'side');
	remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel'); // Try Gutenberg
	remove_meta_box('dashboard_quick_press','dashboard','side'); // Quick Press widget
	remove_meta_box('dashboard_recent_drafts','dashboard','side'); // Recent Drafts
	remove_meta_box('dashboard_secondary','dashboard','side'); // Other WordPress News
	remove_meta_box('dashboard_incoming_links','dashboard','normal'); //Incoming Links
	remove_meta_box('rg_forms_dashboard','dashboard','normal'); // Gravity Forms
	remove_meta_box('dashboard_recent_comments','dashboard','normal'); // Recent Comments
	remove_meta_box('icl_dashboard_widget','dashboard','normal'); // Multi Language Plugin
	remove_meta_box('dashboard_activity','dashboard', 'normal'); // Activity
    remove_meta_box( 'wpseo-dashboard-overview', 'dashboard', 'normal');
    remove_meta_box( 'jetpack_summary_widget', 'dashboard', 'normal');
  	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal');
    remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal');
  	remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
    remove_meta_box( 'wpseo-wincher-dashboard-overview', 'dashboard', 'normal' );
    remove_meta_box( 'pvc_dashboard', 'dashboard', 'normal' );
}

function cotlas_is_default_site_setup_user() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return false;
    }

    $current_user = wp_get_current_user();

    return $current_user instanceof WP_User && 'cotlasweb' === $current_user->user_login;
}

function cotlas_render_dashboard_welcome_notice() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $dismiss_meta_key = 'cotlas_dashboard_welcome_notice_dismissed_until';
    $dismiss_until = (int) get_user_meta(get_current_user_id(), $dismiss_meta_key, true);

    if (!$screen || 'dashboard' !== $screen->id) {
        return;
    }

    if ($dismiss_until > time()) {
        return;
    }

    $current_user = wp_get_current_user();
    $display_name = $current_user instanceof WP_User ? $current_user->display_name : __('there', 'cotlas-news');
    $dismiss_nonce = wp_create_nonce('cotlas_dashboard_welcome_notice');
    ?>
    <div class="notice cotlas-dashboard-welcome-notice" data-cotlas-dashboard-welcome-notice>
        <button type="button" class="cotlas-dashboard-welcome-dismiss" aria-label="<?php esc_attr_e('Dismiss welcome message', 'cotlas-news'); ?>">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
        <div class="cotlas-dashboard-welcome-content">
            <h5 class="cotlas-dashboard-welcome-title"><?php echo esc_html(sprintf(__('Welcome %s', 'cotlas-news'), $display_name)); ?></h5>
            <p class="cotlas-dashboard-welcome-text"><?php esc_html_e('Stay on top of your publishing goals with curated guidance from Cotlas. Explore actionable ideas to strengthen your content strategy, improve search visibility, and get more value from your portal.', 'cotlas-news'); ?></p>
            <div class="cotlas-dashboard-welcome-actions">
                <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="page-title-action"><?php esc_html_e('Publish an article', 'cotlas-news'); ?></a>
                <a href="https://cotlas.net/contact-us" class="page-title-action" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Need help ? Contact us now', 'cotlas-news'); ?></a>
            </div>
        </div>
    </div>
    <style>
        .cotlas-dashboard-welcome-notice {
            position: relative;
            margin: 16px 0 20px;
            padding: 18px 20px;
            border: 1px solid #dbe3f2;
            border-left: 4px solid #377dff;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }

        .cotlas-dashboard-welcome-content {
            max-width: 840px;
        }

        .cotlas-dashboard-welcome-title {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 18px;
            line-height: 1.35;
        }

        .cotlas-dashboard-welcome-text {
            margin: 0;
            color: #475569;
            font-size: 14px;
            line-height: 1.7;
        }

        .cotlas-dashboard-welcome-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .cotlas-dashboard-welcome-actions .page-title-action {
            margin: 0;
        }

        .cotlas-dashboard-welcome-dismiss {
            position: absolute;
            top: 12px;
            right: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .cotlas-dashboard-welcome-dismiss:hover,
        .cotlas-dashboard-welcome-dismiss:focus {
            background: #eef4ff;
            color: #1d4ed8;
        }

        @media (max-width: 782px) {
            .cotlas-dashboard-welcome-notice {
                padding: 16px;
            }

            .cotlas-dashboard-welcome-title {
                padding-right: 28px;
            }
        }
    </style>
    <script>
        (function() {
            var notice = document.querySelector('[data-cotlas-dashboard-welcome-notice]');

            if (!notice) {
                return;
            }

            var dismissButton = notice.querySelector('.cotlas-dashboard-welcome-dismiss');

            if (!dismissButton) {
                return;
            }

            dismissButton.addEventListener('click', function() {
                notice.style.display = 'none';

                if (!window.fetch) {
                    return;
                }

                window.fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'cotlas_dismiss_dashboard_welcome_notice',
                        nonce: '<?php echo esc_js($dismiss_nonce); ?>'
                    }).toString()
                }).catch(function() {
                    // Ignore network errors after hiding the notice in the current view.
                });
            });
        })();
    </script>
    <?php
}

function cotlas_dismiss_dashboard_welcome_notice() {
    check_ajax_referer('cotlas_dashboard_welcome_notice', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in.', 'cotlas-news')), 403);
    }

    update_user_meta(get_current_user_id(), 'cotlas_dashboard_welcome_notice_dismissed_until', time() + (30 * MINUTE_IN_SECONDS));

    wp_send_json_success();
}

/**
 * Define reusable starter kits for fresh WordPress builds.
 *
 * To add another site set later:
 * 1. Create a new top-level key like 'magazine-portal'.
 * 2. Add the label, description, themes, and plugins arrays.
 * 3. Each theme/plugin item should define a stable slug and label.
 * 4. Use 'package' for direct ZIP installs or 'repository' => 'wordpress' for wp.org assets.
 * 5. For plugins, optionally define 'plugin_file' when you want exact activation targeting.
 */
function cotlas_get_default_site_sets() {
    return array(
        'news-portal' => array(
            'label'       => __('News Site', 'cotlas-news'),
            'description' => __('Installs the standard Cotlas news portal stack for quick project setup.', 'cotlas-news'),
            'themes'      => array(
                array(
                    'slug'    => 'cotlas-news',
                    'label'   => 'Cotlas News',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/themes/cotlas-news.zip',
                ),
                array(
                    'slug'    => 'generatepress',
                    'label'   => 'GeneratePress',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/themes/generatepress.zip',
                ),
            ),
            'plugins'     => array(
                array(
                    'slug'    => 'gp-premium',
                    'label'   => 'GP Premium',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/plugins/gp-premium.zip',
                ),
                array(
                    'slug'    => 'generateblocks',
                    'label'   => 'GenerateBlocks',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/plugins/generateblocks.zip',
                ),
                array(
                    'slug'    => 'generateblocks-pro',
                    'label'   => 'GenerateBlocks Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/plugins/generateblocks-pro.zip',
                ),
                array(
                    'slug'    => 'cotlas-simple-forms',
                    'label'   => 'Cotlas Simple Forms',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/plugins/cotlas-simple-forms.zip',
                ),
                array(
                    'slug'    => 'wp-migrate-db-pro',
                    'label'   => 'WP Migrate DB Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/news-portal/plugins/wp-migrate-db-pro.zip',
                ),
                array(
                    'slug'        => 'wordpress-seo',
                    'label'       => 'Yoast SEO',
                    'repository'  => 'wordpress',
                    'plugin_file' => 'wordpress-seo/wp-seo.php',
                ),
                array(
                    'slug'        => 'post-views-counter',
                    'label'       => 'Post Views Counter',
                    'repository'  => 'wordpress',
                    'plugin_file' => 'post-views-counter/post-views-counter.php',
                ),
            ),
        ),
        'business-portal' => array(
            'label'       => __('Business Site', 'cotlas-news'),
            'description' => __('Installs the standard Cotlas business portal stack for service and company websites.', 'cotlas-news'),
            'themes'      => array(
                array(
                    'slug'    => 'cotlas-business',
                    'label'   => 'Cotlas Business',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/themes/cotlas-business.zip',
                ),
                array(
                    'slug'    => 'generatepress',
                    'label'   => 'GeneratePress',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/themes/generatepress.zip',
                ),
            ),
            'plugins'     => array(
                array(
                    'slug'    => 'gp-premium',
                    'label'   => 'GP Premium',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/gp-premium.zip',
                ),
                array(
                    'slug'    => 'generateblocks',
                    'label'   => 'GenerateBlocks',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/generateblocks.zip',
                ),
                array(
                    'slug'    => 'generateblocks-pro',
                    'label'   => 'GenerateBlocks Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/generateblocks-pro.zip',
                ),
                array(
                    'slug'    => 'cotlas-simple-forms',
                    'label'   => 'Cotlas Simple Forms',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/cotlas-simple-forms.zip',
                ),
                array(
                    'slug'    => 'wp-migrate-db-pro',
                    'label'   => 'WP Migrate DB Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/wp-migrate-db-pro.zip',
                ),
                array(
                    'slug'        => 'wordpress-seo',
                    'label'       => 'Yoast SEO',
                    'repository'  => 'wordpress',
                    'plugin_file' => 'wordpress-seo/wp-seo.php',
                ),
            ),
        ),
        'blog-portal' => array(
            'label'       => __('Blog Site', 'cotlas-news'),
            'description' => __('Installs the standard Cotlas blog portal stack for creating blogs and articles.', 'cotlas-news'),
            'themes'      => array(
                array(
                    'slug'    => 'cotlas-blog',
                    'label'   => 'Cotlas Blog',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/blog-portal/themes/cotlas-blog.zip',
                ),
                array(
                    'slug'    => 'generatepress',
                    'label'   => 'GeneratePress',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/blog-portal/themes/generatepress.zip',
                ),
            ),
            'plugins'     => array(
                array(
                    'slug'    => 'gp-premium',
                    'label'   => 'GP Premium',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/gp-premium.zip',
                ),
                array(
                    'slug'    => 'generateblocks',
                    'label'   => 'GenerateBlocks',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/generateblocks.zip',
                ),
                array(
                    'slug'    => 'generateblocks-pro',
                    'label'   => 'GenerateBlocks Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/generateblocks-pro.zip',
                ),
                array(
                    'slug'    => 'cotlas-simple-forms',
                    'label'   => 'Cotlas Simple Forms',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/cotlas-simple-forms.zip',
                ),
                array(
                    'slug'    => 'wp-migrate-db-pro',
                    'label'   => 'WP Migrate DB Pro',
                    'package' => 'https://cotlas.net/wp-content/starter-kit/default-set/business-portal/plugins/wp-migrate-db-pro.zip',
                ),
                array(
                    'slug'        => 'wordpress-seo',
                    'label'       => 'Yoast SEO',
                    'repository'  => 'wordpress',
                    'plugin_file' => 'wordpress-seo/wp-seo.php',
                ),
                array(
                    'slug'        => 'post-views-counter',
                    'label'       => 'Post Views Counter',
                    'repository'  => 'wordpress',
                    'plugin_file' => 'post-views-counter/post-views-counter.php',
                ),
            ),
        ),
    );
}

function cotlas_register_default_site_setup_menu() {
    if (!cotlas_is_default_site_setup_user()) {
        return;
    }

    // Register the page with no parent so it has no sidebar entry,
    // but is still accessible via admin.php?page=cotlas-default-site-setup
    add_submenu_page(
        null,
        __('Default Site Setup', 'cotlas-news'),
        __('Default Site Setup', 'cotlas-news'),
        'manage_options',
        'cotlas-default-site-setup',
        'cotlas_render_default_site_setup_page'
    );
}

function cotlas_render_default_site_setup_notice() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (!$screen || 'dashboard' !== $screen->id || !cotlas_is_default_site_setup_user()) {
        return;
    }

    if ((int) get_option('cotlas_default_site_setup_notice_dismissed', 0) === 1) {
        return;
    }

    $current_user = wp_get_current_user();
    $display_name = $current_user instanceof WP_User ? $current_user->display_name : __('there', 'cotlas-news');
    $dismiss_nonce = wp_create_nonce('cotlas_default_site_setup_notice');
    $setup_url = admin_url('admin.php?page=cotlas-default-site-setup');
    ?>
    <div class="notice cotlas-dashboard-setup-notice" data-cotlas-dashboard-setup-notice>
        <button type="button" class="cotlas-dashboard-setup-dismiss" aria-label="<?php esc_attr_e('Dismiss setup message', 'cotlas-news'); ?>">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
        <div class="cotlas-dashboard-setup-content">
            <h5 class="cotlas-dashboard-setup-title"><?php echo esc_html(sprintf(__('Welcome %s', 'cotlas-news'), $display_name)); ?></h5>
            <p class="cotlas-dashboard-setup-text"><?php esc_html_e('Setting up this new site? Install your default plugins and themes to get started faster.', 'cotlas-news'); ?></p>
            <div class="cotlas-dashboard-setup-actions">
                <a href="<?php echo esc_url($setup_url); ?>" class="page-title-action"><?php esc_html_e('Get started', 'cotlas-news'); ?></a>
            </div>
        </div>
    </div>
    <style>
        .cotlas-dashboard-setup-notice {
            position: relative;
            margin: 16px 0 20px;
            padding: 18px 20px;
            border: 1px solid #dbe3f2;
            border-left: 4px solid #377dff;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }

        .cotlas-dashboard-setup-content {
            max-width: 840px;
        }

        .cotlas-dashboard-setup-title {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 18px;
            line-height: 1.35;
        }

        .cotlas-dashboard-setup-text {
            margin: 0;
            color: #475569;
            font-size: 14px;
            line-height: 1.7;
        }

        .cotlas-dashboard-setup-actions {
            margin-top: 14px;
        }

        .cotlas-dashboard-setup-actions .page-title-action {
            margin: 0;
        }

        .cotlas-dashboard-setup-dismiss {
            position: absolute;
            top: 12px;
            right: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .cotlas-dashboard-setup-dismiss:hover,
        .cotlas-dashboard-setup-dismiss:focus {
            background: #eef4ff;
            color: #1d4ed8;
        }

        @media (max-width: 782px) {
            .cotlas-dashboard-setup-notice {
                padding: 16px;
            }

            .cotlas-dashboard-setup-title {
                padding-right: 28px;
            }
        }
    </style>
    <script>
        (function() {
            var notice = document.querySelector('[data-cotlas-dashboard-setup-notice]');

            if (!notice) {
                return;
            }

            var dismissButton = notice.querySelector('.cotlas-dashboard-setup-dismiss');

            if (!dismissButton) {
                return;
            }

            dismissButton.addEventListener('click', function() {
                notice.style.display = 'none';

                if (!window.fetch) {
                    return;
                }

                window.fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'cotlas_dismiss_default_site_setup_notice',
                        nonce: '<?php echo esc_js($dismiss_nonce); ?>'
                    }).toString()
                }).catch(function() {
                    // Ignore network errors after hiding the notice in the current view.
                });
            });
        })();
    </script>
    <?php
}

function cotlas_dismiss_default_site_setup_notice() {
    check_ajax_referer('cotlas_default_site_setup_notice', 'nonce');

    if (!cotlas_is_default_site_setup_user()) {
        wp_send_json_error(array('message' => __('You are not allowed to dismiss this notice.', 'cotlas-news')), 403);
    }

    update_option('cotlas_default_site_setup_notice_dismissed', 1, false);

    wp_send_json_success();
}

function cotlas_render_default_site_setup_page() {
    if (!cotlas_is_default_site_setup_user()) {
        wp_die(esc_html__('You are not allowed to access this page.', 'cotlas-news'));
    }

    $site_sets = cotlas_get_default_site_sets();
    $site_set_preview = array();
    $site_set_assets = array();

    foreach ($site_sets as $key => $site_set) {
        $site_set_preview[$key] = array(
            'label'       => $site_set['label'],
            'description' => $site_set['description'],
            'themes'      => wp_list_pluck($site_set['themes'], 'label'),
            'plugins'     => wp_list_pluck($site_set['plugins'], 'label'),
        );

        $site_set_assets[$key] = cotlas_get_default_site_set_assets($site_set);
    }

    $install_nonce = wp_create_nonce('cotlas_install_default_site_set');
    $activate_nonce = wp_create_nonce('cotlas_activate_default_site_asset');
    ?>
    <div class="wrap cotlas-default-site-setup-page">
        <h1><?php esc_html_e('Default Site Setup', 'cotlas-news'); ?></h1>
        <p><?php esc_html_e('Choose a starter kit below to install your default themes and plugins for this project.', 'cotlas-news'); ?></p>

        <div class="cotlas-default-site-setup-card">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cotlas-site-set-selector"><?php esc_html_e('Website type', 'cotlas-news'); ?></label></th>
                        <td>
                            <select id="cotlas-site-set-selector" class="regular-text">
                                <?php foreach ($site_sets as $site_set_key => $site_set) : ?>
                                    <option value="<?php echo esc_attr($site_set_key); ?>"><?php echo esc_html($site_set['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the starter kit you want to install on this site.', 'cotlas-news'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div id="cotlas-site-set-preview" class="cotlas-site-set-preview"></div>

            <div id="cotlas-site-set-status" class="cotlas-site-set-status" hidden></div>
        </div>
    </div>
    <style>
        .cotlas-default-site-setup-card {
            max-width: 980px;
            padding: 20px 24px 24px;
            border: 1px solid #dbe3f2;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .cotlas-site-set-preview {
            margin-top: 18px;
            padding: 18px;
            border-radius: 10px;
            background: #f8fbff;
        }

        .cotlas-site-set-preview h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .cotlas-site-set-preview p {
            margin: 0 0 14px;
            color: #475569;
        }

        .cotlas-site-set-preview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .cotlas-site-set-preview-section h3 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1d4ed8;
        }

        .cotlas-site-set-preview-section ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cotlas-site-set-preview-section li {
            margin: 0 0 10px;
        }

        .cotlas-site-set-asset {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #dbe3f2;
            border-radius: 10px;
            background: #fff;
        }

        .cotlas-site-set-asset-name {
            font-weight: 500;
            color: #1e293b;
        }

        .cotlas-site-set-asset-meta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cotlas-site-set-asset-status {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }

        .cotlas-site-set-asset-status.is-not-installed {
            color: #92400e;
            background: #fff7ed;
        }

        .cotlas-site-set-asset-status.is-installed {
            color: #0f766e;
            background: #ecfeff;
        }

        .cotlas-site-set-asset-status.is-active {
            color: #166534;
            background: #ecfdf5;
        }

        .cotlas-site-set-asset-button[disabled] {
            opacity: 0.65;
            cursor: default;
        }

        .cotlas-site-set-status {
            margin-top: 18px;
            padding: 18px;
            border: 1px solid #dbe3f2;
            border-radius: 10px;
            background: #f8fbff;
        }

        .cotlas-site-set-status.is-loading {
            color: #1d4ed8;
        }

        .cotlas-site-set-status-list {
            margin: 12px 0 0;
        }

        .cotlas-site-set-status-item {
            margin: 0 0 8px;
        }

        .cotlas-site-set-status-item.is-success {
            color: #166534;
        }

        .cotlas-site-set-status-item.is-warning {
            color: #92400e;
        }

        .cotlas-site-set-status-item.is-error {
            color: #b91c1c;
        }

        .cotlas-site-set-status-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        @media (max-width: 782px) {
            .cotlas-site-set-preview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        (function() {
            var siteSets = <?php echo wp_json_encode($site_set_preview); ?>;
            var siteSetAssets = <?php echo wp_json_encode($site_set_assets); ?>;
            var selector = document.getElementById('cotlas-site-set-selector');
            var preview = document.getElementById('cotlas-site-set-preview');
            var statusBox = document.getElementById('cotlas-site-set-status');

            if (!selector || !preview || !statusBox) {
                return;
            }

            function renderPreview() {
                var selectedSet = siteSets[selector.value];

                if (!selectedSet) {
                    preview.innerHTML = '';
                    return;
                }

                var selectedAssets = siteSetAssets[selector.value] || [];
                var themes = selectedAssets.filter(function(asset) {
                    return asset.type === 'theme';
                }).map(renderAsset).join('');

                var plugins = selectedAssets.filter(function(asset) {
                    return asset.type === 'plugin';
                }).map(renderAsset).join('');

                preview.innerHTML = '' +
                    '<h2>' + selectedSet.label + '</h2>' +
                    '<p>' + selectedSet.description + '</p>' +
                    '<div class="cotlas-site-set-preview-grid">' +
                        '<div class="cotlas-site-set-preview-section">' +
                            '<h3><?php echo esc_js(__('Themes', 'cotlas-news')); ?></h3>' +
                            '<ul>' + themes + '</ul>' +
                        '</div>' +
                        '<div class="cotlas-site-set-preview-section">' +
                            '<h3><?php echo esc_js(__('Plugins', 'cotlas-news')); ?></h3>' +
                            '<ul>' + plugins + '</ul>' +
                        '</div>' +
                    '</div>';
            }

            function renderAsset(asset) {
                var buttonText = asset.actionLabel || '<?php echo esc_js(__('Installed', 'cotlas-news')); ?>';
                var isDisabled = !asset.action;

                return '' +
                    '<li>' +
                        '<div class="cotlas-site-set-asset">' +
                            '<span class="cotlas-site-set-asset-name">' + asset.label + '</span>' +
                            '<span class="cotlas-site-set-asset-meta">' +
                                '<span class="cotlas-site-set-asset-status is-' + asset.status + '">' + asset.statusLabel + '</span>' +
                                '<button type="button" class="button cotlas-site-set-asset-button" data-asset-type="' + asset.type + '" data-asset-index="' + asset.index + '" data-asset-action="' + (asset.action || '') + '"' + (isDisabled ? ' disabled' : '') + '>' + buttonText + '</button>' +
                            '</span>' +
                        '</div>' +
                    '</li>';
            }

            function renderResult(payload) {
                var resultItems = (payload.results || []).map(function(item) {
                    var stateClass = 'is-success';

                    if (item.status === 'already_installed') {
                        stateClass = 'is-warning';
                    }

                    if (item.status === 'failed') {
                        stateClass = 'is-error';
                    }

                    return '<li class="cotlas-site-set-status-item ' + stateClass + '">' + item.message + '</li>';
                }).join('');

                var actions = '';

                if (payload.success) {
                    actions = '' +
                        '<div class="cotlas-site-set-status-actions">' +
                            '<a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-primary"><?php echo esc_js(__('Go to Plugins', 'cotlas-news')); ?></a>' +
                            '<a href="<?php echo esc_url(admin_url('themes.php')); ?>" class="button"><?php echo esc_js(__('Go to Themes', 'cotlas-news')); ?></a>' +
                        '</div>';
                }

                statusBox.className = 'cotlas-site-set-status';
                statusBox.hidden = false;
                statusBox.innerHTML = '' +
                    '<p><strong>' + payload.message + '</strong></p>' +
                    '<ul class="cotlas-site-set-status-list">' + resultItems + '</ul>' +
                    actions;
            }

            function getAssetByIdentity(siteSetKey, assetType, assetIndex) {
                var selectedAssets = siteSetAssets[siteSetKey] || [];

                return selectedAssets.find(function(asset) {
                    return asset.type === assetType && Number(asset.index) === Number(assetIndex);
                }) || null;
            }

            function refreshSiteSetAssets(siteSetKey, assets) {
                if (!Array.isArray(assets)) {
                    return;
                }

                siteSetAssets[siteSetKey] = assets;
                renderPreview();
            }

            function activateSingleAsset(siteSetKey, asset, onSuccess, onError) {
                window.fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'cotlas_activate_default_site_asset',
                        nonce: '<?php echo esc_js($activate_nonce); ?>',
                        site_set: siteSetKey,
                        asset_type: asset.type,
                        asset_index: String(asset.index)
                    }).toString()
                }).then(function(response) {
                    return response.text();
                }).then(function(responseText) {
                    var response;

                    try {
                        response = JSON.parse(responseText);
                    } catch (error) {
                        throw new Error(responseText.slice(0, 300) || '<?php echo esc_js(__('Unexpected empty response received from the activator.', 'cotlas-news')); ?>');
                    }

                    if (!response) {
                        throw new Error('<?php echo esc_js(__('Unexpected response received from the activator.', 'cotlas-news')); ?>');
                    }

                    if (response.success === false) {
                        throw new Error((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('The activator returned an error.', 'cotlas-news')); ?>');
                    }

                    if (!response.data || !response.data.result) {
                        throw new Error('<?php echo esc_js(__('Unexpected response received from the activator.', 'cotlas-news')); ?>');
                    }

                    onSuccess(response.data);
                }).catch(onError);
            }

            function installSingleAsset(siteSetKey, asset, onSuccess, onError) {
                window.fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'cotlas_install_default_site_set',
                        nonce: '<?php echo esc_js($install_nonce); ?>',
                        site_set: siteSetKey,
                        asset_type: asset.type,
                        asset_index: String(asset.index)
                    }).toString()
                }).then(function(response) {
                    return response.text();
                }).then(function(responseText) {
                    var response;

                    try {
                        response = JSON.parse(responseText);
                    } catch (error) {
                        throw new Error(responseText.slice(0, 300) || '<?php echo esc_js(__('Unexpected empty response received from the installer.', 'cotlas-news')); ?>');
                    }

                    if (!response) {
                        throw new Error('<?php echo esc_js(__('Unexpected response received from the installer.', 'cotlas-news')); ?>');
                    }

                    if (response.success === false) {
                        throw new Error((response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('The installer returned an error.', 'cotlas-news')); ?>');
                    }

                    if (!response.data || !response.data.result) {
                        throw new Error('<?php echo esc_js(__('Unexpected response received from the installer.', 'cotlas-news')); ?>');
                    }

                    onSuccess(response.data);
                }).catch(onError);
            }

            selector.addEventListener('change', renderPreview);

            preview.addEventListener('click', function(event) {
                var button = event.target.closest('.cotlas-site-set-asset-button');

                if (!button || button.disabled) {
                    return;
                }

                var assetType = button.getAttribute('data-asset-type');
                var assetIndex = Number(button.getAttribute('data-asset-index'));
                var assetAction = button.getAttribute('data-asset-action');
                var asset = getAssetByIdentity(selector.value, assetType, assetIndex);

                if (!asset) {
                    return;
                }

                button.disabled = true;
                button.textContent = assetAction === 'activate'
                    ? '<?php echo esc_js(__('Activating...', 'cotlas-news')); ?>'
                    : '<?php echo esc_js(__('Installing...', 'cotlas-news')); ?>';
                statusBox.hidden = false;
                statusBox.className = 'cotlas-site-set-status is-loading';
                statusBox.innerHTML = '<p><strong>' + (assetAction === 'activate'
                    ? '<?php echo esc_js(__('Activating selected item. Please wait...', 'cotlas-news')); ?>'
                    : '<?php echo esc_js(__('Installing selected item. Please wait...', 'cotlas-news')); ?>') + '</strong></p><p>' + asset.label + '</p>';

                if (assetAction === 'activate') {
                    activateSingleAsset(selector.value, asset, function(data) {
                        refreshSiteSetAssets(selector.value, data.assets);
                        renderResult({
                            success: data.result.status !== 'failed',
                            message: data.result.status === 'failed'
                                ? '<?php echo esc_js(__('The selected item could not be activated.', 'cotlas-news')); ?>'
                                : '<?php echo esc_js(__('The selected item has been activated successfully.', 'cotlas-news')); ?>',
                            results: [data.result]
                        });
                    }, function(error) {
                        statusBox.className = 'cotlas-site-set-status';
                        statusBox.hidden = false;
                        statusBox.innerHTML = '<p><strong><?php echo esc_js(__('Activation could not be completed.', 'cotlas-news')); ?></strong></p><p>' + error.message + '</p>';
                        renderPreview();
                    });
                    return;
                }

                installSingleAsset(selector.value, asset, function(data) {
                    refreshSiteSetAssets(selector.value, data.assets);
                    renderResult({
                        success: data.result.status !== 'failed',
                        message: data.result.status === 'failed'
                            ? '<?php echo esc_js(__('The selected item could not be installed.', 'cotlas-news')); ?>'
                            : '<?php echo esc_js(__('The selected item has been processed successfully.', 'cotlas-news')); ?>',
                        results: [data.result]
                    });
                }, function(error) {
                    statusBox.className = 'cotlas-site-set-status';
                    statusBox.hidden = false;
                    statusBox.innerHTML = '<p><strong><?php echo esc_js(__('Installation could not be completed.', 'cotlas-news')); ?></strong></p><p>' + error.message + '</p>';
                    renderPreview();
                });
            });

            renderPreview();
        })();
    </script>
    <?php
}

function cotlas_install_default_site_set() {
    check_ajax_referer('cotlas_install_default_site_set', 'nonce');

    if (!cotlas_is_default_site_setup_user()) {
        wp_send_json_error(array('message' => __('You are not allowed to install starter kits on this site.', 'cotlas-news')), 403);
    }

    $site_set_key = isset($_POST['site_set']) ? sanitize_key(wp_unslash($_POST['site_set'])) : '';
    $asset_type = isset($_POST['asset_type']) ? sanitize_key(wp_unslash($_POST['asset_type'])) : '';
    $asset_index = isset($_POST['asset_index']) ? absint(wp_unslash($_POST['asset_index'])) : -1;
    $site_sets = cotlas_get_default_site_sets();

    if (empty($site_set_key) || empty($site_sets[$site_set_key])) {
        wp_send_json_error(array('message' => __('Please choose a valid website type before installing.', 'cotlas-news')), 400);
    }

    if (!in_array($asset_type, array('theme', 'plugin'), true) || $asset_index < 0) {
        wp_send_json_error(array('message' => __('A valid starter kit asset was not supplied.', 'cotlas-news')), 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    if (!WP_Filesystem()) {
        wp_send_json_error(array('message' => __('WordPress could not access the filesystem to install the starter kit.', 'cotlas-news')), 500);
    }

    $site_set = $site_sets[$site_set_key];
    $asset_group_key = 'theme' === $asset_type ? 'themes' : 'plugins';

    if (empty($site_set[$asset_group_key][$asset_index])) {
        wp_send_json_error(array('message' => __('The requested starter kit asset could not be found.', 'cotlas-news')), 400);
    }

    $asset = array_merge(
        $site_set[$asset_group_key][$asset_index],
        array(
            'type'  => $asset_type,
            'index' => $asset_index,
        )
    );

    $result = cotlas_install_default_site_asset($asset['type'], $asset);

    wp_send_json_success(array(
        'result' => $result,
        'assetState' => cotlas_get_default_site_asset_state($asset['type'], $asset['slug']),
        'assets' => cotlas_get_default_site_set_assets($site_set),
    ));
}

function cotlas_activate_default_site_asset() {
    check_ajax_referer('cotlas_activate_default_site_asset', 'nonce');

    if (!cotlas_is_default_site_setup_user()) {
        wp_send_json_error(array('message' => __('You are not allowed to activate starter kit items on this site.', 'cotlas-news')), 403);
    }

    $site_set_key = isset($_POST['site_set']) ? sanitize_key(wp_unslash($_POST['site_set'])) : '';
    $asset_type = isset($_POST['asset_type']) ? sanitize_key(wp_unslash($_POST['asset_type'])) : '';
    $asset_index = isset($_POST['asset_index']) ? absint(wp_unslash($_POST['asset_index'])) : -1;
    $site_sets = cotlas_get_default_site_sets();

    if (empty($site_set_key) || empty($site_sets[$site_set_key])) {
        wp_send_json_error(array('message' => __('Please choose a valid website type before activating.', 'cotlas-news')), 400);
    }

    if (!in_array($asset_type, array('theme', 'plugin'), true) || $asset_index < 0) {
        wp_send_json_error(array('message' => __('A valid starter kit asset was not supplied.', 'cotlas-news')), 400);
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';

    $site_set = $site_sets[$site_set_key];
    $asset_group_key = 'theme' === $asset_type ? 'themes' : 'plugins';

    if (empty($site_set[$asset_group_key][$asset_index])) {
        wp_send_json_error(array('message' => __('The requested starter kit asset could not be found.', 'cotlas-news')), 400);
    }

    $asset = array_merge(
        $site_set[$asset_group_key][$asset_index],
        array(
            'type'  => $asset_type,
            'index' => $asset_index,
        )
    );

    $result = cotlas_activate_default_site_asset_item($asset['type'], $asset);

    wp_send_json_success(array(
        'result' => $result,
        'assetState' => cotlas_get_default_site_asset_state($asset['type'], $asset['slug']),
        'assets' => cotlas_get_default_site_set_assets($site_set),
    ));
}

function cotlas_get_default_site_set_assets($site_set) {
    $assets = array();

    foreach ($site_set['themes'] as $index => $theme) {
        $state = cotlas_get_default_site_asset_state('theme', $theme['slug']);
        $assets[] = array_merge(
            $theme,
            array(
                'type'        => 'theme',
                'index'       => $index,
                'status'      => $state['status'],
                'statusLabel' => $state['label'],
                'action'      => $state['action'],
                'actionLabel' => $state['actionLabel'],
            )
        );
    }

    foreach ($site_set['plugins'] as $index => $plugin) {
        $state = cotlas_get_default_site_asset_state('plugin', $plugin['slug']);
        $assets[] = array_merge(
            $plugin,
            array(
                'type'        => 'plugin',
                'index'       => $index,
                'status'      => $state['status'],
                'statusLabel' => $state['label'],
                'action'      => $state['action'],
                'actionLabel' => $state['actionLabel'],
            )
        );
    }

    return $assets;
}

function cotlas_get_default_site_asset_state($type, $slug) {
    $action = '';
    $action_label = __('Installed', 'cotlas-news');

    if ('theme' === $type) {
        if (cotlas_is_theme_active($slug)) {
            return array(
                'status' => 'active',
                'label'  => __('Active', 'cotlas-news'),
                'action' => '',
                'actionLabel' => __('Active', 'cotlas-news'),
            );
        }

        if (cotlas_is_theme_installed($slug)) {
            return array(
                'status' => 'installed',
                'label'  => __('Installed', 'cotlas-news'),
                'action' => 'activate',
                'actionLabel' => __('Activate', 'cotlas-news'),
            );
        }
    }

    if ('plugin' === $type) {
        if (cotlas_is_plugin_active_by_slug($slug)) {
            return array(
                'status' => 'active',
                'label'  => __('Active', 'cotlas-news'),
                'action' => '',
                'actionLabel' => __('Active', 'cotlas-news'),
            );
        }

        if (cotlas_is_plugin_installed($slug)) {
            return array(
                'status' => 'installed',
                'label'  => __('Installed', 'cotlas-news'),
                'action' => cotlas_get_plugin_file_by_slug($slug) ? 'activate' : '',
                'actionLabel' => __('Activate', 'cotlas-news'),
            );
        }
    }

    return array(
        'status' => 'not-installed',
        'label'  => __('Not installed', 'cotlas-news'),
        'action' => 'install',
        'actionLabel' => __('Install', 'cotlas-news'),
    );
}

function cotlas_install_default_site_asset($type, $item) {
    if ('theme' === $type && cotlas_is_theme_installed($item['slug'])) {
        return array(
            'status'  => 'already_installed',
            'message' => sprintf(__('Theme already installed: %s', 'cotlas-news'), $item['label']),
        );
    }

    if ('plugin' === $type && cotlas_is_plugin_installed($item['slug'])) {
        return array(
            'status'  => 'already_installed',
            'message' => sprintf(__('Plugin already installed: %s', 'cotlas-news'), $item['label']),
        );
    }

    if ('plugin' === $type && isset($item['repository']) && 'wordpress' === $item['repository']) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $plugin_api = plugins_api(
            'plugin_information',
            array(
                'slug'   => $item['slug'],
                'fields' => array(
                    'sections' => false,
                ),
            )
        );

        if (is_wp_error($plugin_api) || empty($plugin_api->download_link)) {
            return array(
                'status'  => 'failed',
                'message' => sprintf(__('Could not fetch %1$s from the WordPress plugin repository.', 'cotlas-news'), $item['label']),
            );
        }

        $item['package'] = $plugin_api->download_link;
    }

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = 'theme' === $type ? new Theme_Upgrader($skin) : new Plugin_Upgrader($skin);

    ob_start();
    $result = $upgrader->install($item['package']);
    ob_end_clean();

    if (is_wp_error($result)) {
        return array(
            'status'  => 'failed',
            'message' => sprintf(__('Could not install %1$s: %2$s', 'cotlas-news'), $item['label'], $result->get_error_message()),
        );
    }

    if (is_wp_error($skin->result)) {
        return array(
            'status'  => 'failed',
            'message' => sprintf(__('Could not install %1$s: %2$s', 'cotlas-news'), $item['label'], $skin->result->get_error_message()),
        );
    }

    if (!$result) {
        return array(
            'status'  => 'failed',
            'message' => sprintf(__('Could not install %s because the installer returned an unknown error.', 'cotlas-news'), $item['label']),
        );
    }

    if ('theme' === $type) {
        wp_clean_themes_cache();
    }

    return array(
        'status'  => 'installed',
        'message' => sprintf(__('Installed successfully: %s', 'cotlas-news'), $item['label']),
    );
}

function cotlas_is_theme_installed($slug) {
    return wp_get_theme($slug)->exists();
}

function cotlas_is_theme_active($slug) {
    if (!cotlas_is_theme_installed($slug)) {
        return false;
    }

    $current_theme = wp_get_theme();

    return $current_theme->get_stylesheet() === $slug || $current_theme->get_template() === $slug;
}

function cotlas_is_plugin_installed($slug) {
    return (bool) cotlas_get_plugin_file_by_slug($slug);
}

function cotlas_is_plugin_active_by_slug($slug) {
    $plugin_file = cotlas_get_plugin_file_by_slug($slug);

    return $plugin_file ? is_plugin_active($plugin_file) : false;
}

function cotlas_get_plugin_file_by_slug($slug) {
    $installed_plugins = get_plugins();

    foreach (array_keys($installed_plugins) as $plugin_file) {
        if (0 === strpos($plugin_file, $slug . '/')) {
            return $plugin_file;
        }

        if ($plugin_file === $slug . '.php') {
            return $plugin_file;
        }
    }

    return '';
}

function cotlas_activate_default_site_asset_item($type, $item) {
    if ('theme' === $type) {
        if (!cotlas_is_theme_installed($item['slug'])) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Theme is not installed: %s', 'cotlas-news'), $item['label']),
            );
        }

        switch_theme($item['slug']);

        return array(
            'status' => 'activated',
            'message' => sprintf(__('Activated successfully: %s', 'cotlas-news'), $item['label']),
        );
    }

    $plugin_file = cotlas_get_plugin_file_by_slug($item['slug']);

    if (!$plugin_file) {
        return array(
            'status' => 'failed',
            'message' => sprintf(__('Plugin is not installed: %s', 'cotlas-news'), $item['label']),
        );
    }

    $activation_result = activate_plugin($plugin_file);

    if (is_wp_error($activation_result)) {
        return array(
            'status' => 'failed',
            'message' => sprintf(__('Could not activate %1$s: %2$s', 'cotlas-news'), $item['label'], $activation_result->get_error_message()),
        );
    }

    return array(
        'status' => 'activated',
        'message' => sprintf(__('Activated successfully: %s', 'cotlas-news'), $item['label']),
    );
}

function cotlas_register_dashboard_ad_widget() {
    wp_add_dashboard_widget(
        'cotlas_dashboard_ad_widget',
        __('We create your online presence', 'cotlas-news'),
        'cotlas_render_dashboard_ad_widget'
    );
}

function cotlas_render_dashboard_ad_widget() {
    $banner_link = 'https://cotlas.net';
    $banner_images = array(
        'http://cotlas.net/wp-content/uploads/2026/05/website-designing-banner.webp',
        'http://cotlas.net/wp-content/uploads/2026/05/website-designing-banner.webp',
        'http://cotlas.net/wp-content/uploads/2026/05/website-designing-banner.webp',
    );
    ?>
    <div class="cotlas-dashboard-ad-widget" data-rotation="true">
        <a class="cotlas-dashboard-ad-link" href="<?php echo esc_url($banner_link); ?>" target="_blank" rel="noopener noreferrer sponsored">
            <?php foreach ($banner_images as $index => $banner_image) : ?>
                <img
                    class="cotlas-dashboard-ad-slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
                    src="<?php echo esc_url($banner_image); ?>"
                    alt="<?php esc_attr_e('Cotlas promotional banner', 'cotlas-news'); ?>"
                    data-index="<?php echo esc_attr($index); ?>"
                />
            <?php endforeach; ?>
        </a>
        <p class="cotlas-dashboard-ad-note"><?php esc_html_e('Latest offers from cotlas web solution', 'cotlas-news'); ?></p>
    </div>
    <style>
        .cotlas-dashboard-ad-widget {
            position: relative;
            padding-top:10px;
            padding-bottom: 0;
            padding-left: 16px;
            padding-right: 16px;
        }

        .cotlas-dashboard-ad-link {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 14px;
            background: #eff6ff;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08)!important;
        }

        .cotlas-dashboard-ad-slide {
            display: none;
            width: 100%;
            height: auto;
            
        }

        .cotlas-dashboard-ad-slide.is-active {
            display: block;
        }

        .cotlas-dashboard-ad-note {
            margin: 12px 0 0;
            color: #061d3d;
            font-size: 14px;
        }
    </style>
    <script>
        (function() {
            var script = document.currentScript;
            var widget = script ? script.parentNode.querySelector('.cotlas-dashboard-ad-widget') : null;

            if (!widget) {
                return;
            }

            var slides = widget.querySelectorAll('.cotlas-dashboard-ad-slide');
            var activeIndex = 0;

            if (slides.length < 2) {
                return;
            }

            window.setInterval(function() {
                slides[activeIndex].classList.remove('is-active');
                activeIndex = (activeIndex + 1) % slides.length;
                slides[activeIndex].classList.add('is-active');
            }, 3500);
        })();
    </script>
    <?php
}

function cotlas_register_dashboard_feed_widget() {
    wp_add_dashboard_widget(
        'cotlas_dashboard_feed_widget',
        __('Latest Articles', 'cotlas-news'),
        'cotlas_render_dashboard_feed_widget'
    );
}

function cotlas_move_dashboard_feed_widget_to_side() {
    global $wp_meta_boxes;

    if (empty($wp_meta_boxes['dashboard']['normal']['core']['cotlas_dashboard_feed_widget'])) {
        return;
    }

    $widget = $wp_meta_boxes['dashboard']['normal']['core']['cotlas_dashboard_feed_widget'];
    unset($wp_meta_boxes['dashboard']['normal']['core']['cotlas_dashboard_feed_widget']);

    if (!isset($wp_meta_boxes['dashboard']['side']['core'])) {
        $wp_meta_boxes['dashboard']['side']['core'] = array();
    }

    $wp_meta_boxes['dashboard']['side']['core'] = array_merge(
        array('cotlas_dashboard_feed_widget' => $widget),
        $wp_meta_boxes['dashboard']['side']['core']
    );
}

function cotlas_render_dashboard_feed_widget() {
    $feed_url = 'https://cotlas.net/feed';
    $items = cotlas_get_dashboard_feed_items($feed_url, 2);
    ?>
    <div class="cotlas-dashboard-feed-widget">
        <?php if (empty($items)) : ?>
            <p class="cotlas-dashboard-feed-empty"><?php esc_html_e('No articles are available from the feed right now.', 'cotlas-news'); ?></p>
        <?php else : ?>
            <ul class="cotlas-dashboard-feed-list">
                <?php foreach ($items as $item) : ?>
                    <li class="cotlas-dashboard-feed-item">
                        <a class="cotlas-dashboard-feed-card" href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="cotlas-dashboard-feed-thumb-wrap">
                                <?php if (!empty($item['image'])) : ?>
                                    <img class="cotlas-dashboard-feed-thumb" src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" />
                                <?php else : ?>
                                    <span class="cotlas-dashboard-feed-thumb cotlas-dashboard-feed-thumb-placeholder" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <span class="cotlas-dashboard-feed-content">
                                <span class="cotlas-dashboard-feed-title"><?php echo esc_html($item['title']); ?></span>
                                <span class="cotlas-dashboard-feed-meta">
                                    <?php if (!empty($item['author'])) : ?>
                                        <span class="cotlas-dashboard-feed-meta-item">
                                            <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                                            <span><?php echo esc_html($item['author']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['date'])) : ?>
                                        <span class="cotlas-dashboard-feed-meta-item">
                                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                            <span><?php echo esc_html($item['date']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="cotlas-dashboard-feed-note"><?php esc_html_e('Read the latest articles from Cotlas to unlock more value from your portal, generate stronger leads, improve search visibility, and discover curated insights that help your website perform better.', 'cotlas-news'); ?></p>
        <a href="https://cotlas.net/blog" class="page-title-action">Read more</a>
    </div>
    <style>
        .cotlas-dashboard-feed-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
            gap: 18px;
            align-items: stretch;
        }

        .cotlas-dashboard-feed-item {
            margin: 0;
            display: flex;
        }

        .cotlas-dashboard-feed-card {
            display: flex;
            gap: 10px;
            align-items: stretch;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            text-decoration: none;
            flex-direction: column;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }

        .cotlas-dashboard-feed-thumb-wrap {
            display: block;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #f3f4f6;
            border-radius: 8px;
        }

        .cotlas-dashboard-feed-thumb {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .cotlas-dashboard-feed-thumb-placeholder {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
        }

        .cotlas-dashboard-feed-content {
            min-width: 0;
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            justify-content: space-between;
            gap: 5px;
        }

        .cotlas-dashboard-feed-title {
            display: -webkit-box;
            overflow: hidden;
            color: #111827;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.8;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            line-clamp: 3;
        }

        .cotlas-dashboard-feed-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            color: #032d4f;
            font-size: 12px;
            font-weight: 500;
        }

        .cotlas-dashboard-feed-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .cotlas-dashboard-feed-meta-item .dashicons {
            width: 15px;
            height: 15px;
            font-size: 15px;
        }

        .cotlas-dashboard-feed-empty {
            margin: 0;
            color: #475569;
        }

        .cotlas-dashboard-feed-note {
            margin: 16px 0 0;
            color: #475569;
            font-size: 16px;
            line-height: 1.6;
        }
        .cotlas-dashboard-feed-widget .page-title-action {
            margin-top: 12px;
        }

        @media (max-width: 900px) {
            .cotlas-dashboard-feed-card {
                grid-template-columns: 132px minmax(0, 1fr);
            }

            .cotlas-dashboard-feed-thumb-wrap {
                width: 132px;
                height: 100px;
            }

            .cotlas-dashboard-feed-title {
                font-size: 20px;
            }
        }

        @media (max-width: 640px) {
            .cotlas-dashboard-feed-card {
                grid-template-columns: 1fr;
            }

            .cotlas-dashboard-feed-thumb-wrap {
                width: 100%;
                height: 180px;
            }
        }
    </style>
    <?php
}

function cotlas_get_dashboard_feed_items($feed_url, $limit = 3) {
    if (!function_exists('fetch_feed')) {
        require_once ABSPATH . WPINC . '/feed.php';
    }

    $feed = fetch_feed($feed_url);

    if (is_wp_error($feed)) {
        return array();
    }

    $max_items = $feed->get_item_quantity($limit);
    $feed_items = $feed->get_items(0, $max_items);
    $items = array();

    foreach ($feed_items as $feed_item) {
        $items[] = array(
            'title'  => wp_strip_all_tags($feed_item->get_title()),
            'link'   => $feed_item->get_link(),
            'author' => $feed_item->get_author() ? $feed_item->get_author()->get_name() : '',
            'date'   => $feed_item->get_date(get_option('date_format')),
            'image'  => cotlas_get_feed_item_image($feed_item),
        );
    }

    return $items;
}

function cotlas_get_feed_item_image($feed_item) {
    $enclosure = $feed_item->get_enclosure();

    if ($enclosure && $enclosure->get_link()) {
        return $enclosure->get_link();
    }

    $media_content = $feed_item->get_item_tags('http://search.yahoo.com/mrss/', 'content');

    if (!empty($media_content[0]['attribs']['']['url'])) {
        return $media_content[0]['attribs']['']['url'];
    }

    $thumbnail = $feed_item->get_item_tags('http://search.yahoo.com/mrss/', 'thumbnail');

    if (!empty($thumbnail[0]['attribs']['']['url'])) {
        return $thumbnail[0]['attribs']['']['url'];
    }

    $content = $feed_item->get_content();

    if ($content && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        return $matches[1];
    }

    $description = $feed_item->get_description();

    if ($description && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
        return $matches[1];
    }

    return '';
}

add_filter('the_generator', '__return_empty_string');

function shapeSpace_remove_version_scripts_styles($src) {
	if (strpos($src, 'ver=') !== false) {
		$src = remove_query_arg('ver', $src);
	}
	return $src;
}
add_filter('style_loader_src', 'shapeSpace_remove_version_scripts_styles', 9999);
add_filter('script_loader_src', 'shapeSpace_remove_version_scripts_styles', 9999);

remove_action('welcome_panel', 'wp_welcome_panel');

add_filter('widget_text','do_shortcode');

add_action('wp_dashboard_setup', 'custom_hide_widgets');
function custom_hide_widgets() {
    	global $wp_meta_boxes;
   	
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_activity'])) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_activity']);
    }
    
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_right_now'])) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_right_now']);    
    }
}

remove_action('wp_head', 'wp_generator');

function replace_howdy_with_your_text( $wp_admin_bar ) {
  $account_info = $wp_admin_bar->get_node( 'my-account' );
  
  // Check if the node exists and has a title
  if ( $account_info && isset( $account_info->title ) ) {
      $your_title = str_replace( 'Howdy,', 'Welcome', $account_info->title );
      $wp_admin_bar->add_node( array(
          'id'    => 'my-account',
          'title' => $your_title,
      ) );
  }
}
// Use a higher priority to ensure the node exists (optional, but 25 should work)
add_action( 'admin_bar_menu', 'replace_howdy_with_your_text', 25 );

function remove_footer_admin () {
 
    echo 'Fueled by <a href="https://cotlas.net" target="_blank">Cotlas Web Solutions</a> | Designed by <a href="https://cotlas.net/author/vinay404" target="_blank">Vinay Shukla</a> | Site Tutorials: <a href="https://teklog.in" target="_blank">Teklog</a></p>';
     
    }
     
    add_filter('admin_footer_text', 'remove_footer_admin');

// Honeypot function for backend login and register by cotlas404

// Add honeypot field to default WordPress login form
function cotlas_add_login_honeypot() {
  echo '<p style="display:none;"><label for="cc-city">cc-city<input type="text" name="cc-city" id="cc-city" class="input" value="" autocomplete="off" /></label></p>';
}
add_action('login_form', 'cotlas_add_login_honeypot');

// Add honeypot field to default WordPress registration form
function cotlas_add_register_honeypot() {
  echo '<p style="display:none;"><label for="cc-city">cc-city<input type="text" name="cc-city" id="cc-city" class="input" value="" autocomplete="off" /></label></p>';
}
add_action('register_form', 'cotlas_add_register_honeypot');

// Validate honeypot field for default login
function cotlas_validate_login_honeypot($user, $username, $password) {
  if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
      wp_redirect(home_url());
      exit;
  }
  return $user;
}
add_filter('authenticate', 'cotlas_validate_login_honeypot', 30, 3);

// Validate honeypot field for default registration
function cotlas_validate_register_honeypot($errors, $sanitized_user_login, $user_email) {
  if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
      wp_redirect(home_url());
      exit;
  }
  return $errors;
}
add_filter('registration_errors', 'cotlas_validate_register_honeypot', 10, 3);

// Honeypot function for frontend login and register by cotlas404

// Validate honeypot field for custom login form
function cotlas_custom_login_honeypot() {
  if (isset($_POST['action']) && $_POST['action'] === 'houzez_login') {
      if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
          wp_redirect(home_url());
          exit;
      }
  }
}
add_action('init', 'cotlas_custom_login_honeypot');

// Validate honeypot field for custom registration form
function cotlas_custom_register_honeypot() {
  if (isset($_POST['action']) && $_POST['action'] === 'houzez_register') {
      if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
          wp_redirect(home_url());
          exit;
      }
  }
}
add_action('init', 'cotlas_custom_register_honeypot');

// Cloudflare Turnstile Settings
function cotlas_turnstile_menu() {
    add_menu_page("Site Security", "Site Security", "manage_options", "turnstile-options", "cotlas_turnstile_options_page", "dashicons-shield", 99);
}
add_action("admin_menu", "cotlas_turnstile_menu");

function cotlas_turnstile_options_page() {
    ?>
    <div class="wrap">
        <h1>Site Security & Turnstile Configuration</h1>
        <p>This page allows you to configure Cloudflare Turnstile to protect your site from spam and bots. Follow the guide below to get started.</p>
        
        <div class="card" style="max-width: 100%; padding: 15px; margin-bottom: 20px;">
            <h2>How to Setup</h2>
            <ol>
                <li>Go to <a href="https://dash.cloudflare.com/" target="_blank">Cloudflare Dashboard</a> and navigate to "Turnstile".</li>
                <li>Add your site and generate a <strong>Site Key</strong> and <strong>Secret Key</strong>.</li>
                <li>Select "Managed" or "Non-Interactive" mode for the best user experience (invisible to humans).</li>
                <li>Enter the keys below and enable the protection for the forms you want to secure.</li>
            </ol>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields("cotlas_turnstile_section");
            do_settings_sections("turnstile-options");
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function cotlas_turnstile_settings_init() {
    add_settings_section("cotlas_turnstile_section", "Configuration", "cotlas_turnstile_section_cb", "turnstile-options");
    
    // Keys
    add_settings_field("turnstile_site_key", "Site Key", "cotlas_turnstile_site_key_cb", "turnstile-options", "cotlas_turnstile_section");
    add_settings_field("turnstile_secret_key", "Secret Key", "cotlas_turnstile_secret_key_cb", "turnstile-options", "cotlas_turnstile_section");
    
    // Toggles
    add_settings_field("turnstile_enable_login", "Enable on Login Page", "cotlas_turnstile_enable_login_cb", "turnstile-options", "cotlas_turnstile_section");
    add_settings_field("turnstile_enable_register", "Enable on Registration Page", "cotlas_turnstile_enable_register_cb", "turnstile-options", "cotlas_turnstile_section");
    add_settings_field("turnstile_enable_comments", "Enable on Comments", "cotlas_turnstile_enable_comments_cb", "turnstile-options", "cotlas_turnstile_section");

    register_setting("cotlas_turnstile_section", "turnstile_site_key");
    register_setting("cotlas_turnstile_section", "turnstile_secret_key");
    register_setting("cotlas_turnstile_section", "turnstile_enable_login");
    register_setting("cotlas_turnstile_section", "turnstile_enable_register");
    register_setting("cotlas_turnstile_section", "turnstile_enable_comments");
}
add_action("admin_init", "cotlas_turnstile_settings_init");

function cotlas_turnstile_section_cb() {
    echo '<p>Enter your Cloudflare Turnstile keys and choose where to enable the protection.</p>';
}

function cotlas_turnstile_site_key_cb() {
    echo '<input type="text" name="turnstile_site_key" value="' . esc_attr(get_option('turnstile_site_key')) . '" class="regular-text" />';
}

function cotlas_turnstile_secret_key_cb() {
    echo '<input type="text" name="turnstile_secret_key" value="' . esc_attr(get_option('turnstile_secret_key')) . '" class="regular-text" />';
}

function cotlas_turnstile_enable_login_cb() {
    $enabled = get_option('turnstile_enable_login');
    echo '<input type="checkbox" name="turnstile_enable_login" value="1" ' . checked(1, $enabled, false) . ' /> Enable Turnstile on Login Form';
}

function cotlas_turnstile_enable_register_cb() {
    $enabled = get_option('turnstile_enable_register');
    echo '<input type="checkbox" name="turnstile_enable_register" value="1" ' . checked(1, $enabled, false) . ' /> Enable Turnstile on Registration Form';
}

function cotlas_turnstile_enable_comments_cb() {
    $enabled = get_option('turnstile_enable_comments');
    echo '<input type="checkbox" name="turnstile_enable_comments" value="1" ' . checked(1, $enabled, false) . ' /> Enable Turnstile on Comment Forms';
}

// Enqueue Turnstile Script
function cotlas_turnstile_script() {
    // Only enqueue if at least one feature is enabled
    $login_enabled = get_option('turnstile_enable_login');
    $register_enabled = get_option('turnstile_enable_register');
    $comments_enabled = get_option('turnstile_enable_comments');
    
    if ($login_enabled || $register_enabled || $comments_enabled) {
        wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
    }
}
add_action('login_enqueue_scripts', 'cotlas_turnstile_script');
add_action('wp_enqueue_scripts', 'cotlas_turnstile_script');

// Display Turnstile Widget
function cotlas_display_turnstile() {
    $site_key = get_option('turnstile_site_key');
    if (!$site_key) return;

    $show = false;
    $current_filter = current_filter();

    if ($current_filter === 'login_form' && get_option('turnstile_enable_login')) {
        $show = true;
    } elseif ($current_filter === 'register_form' && get_option('turnstile_enable_register')) {
        $show = true;
    } elseif ($current_filter === 'comment_form' && get_option('turnstile_enable_comments')) {
        $show = true;
    }

    if ($show) {
        echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '"></div>';
    }
}
add_action('login_form', 'cotlas_display_turnstile');
add_action('register_form', 'cotlas_display_turnstile');
add_action('comment_form', 'cotlas_display_turnstile');

// Verify Turnstile Helper
function cotlas_verify_turnstile() {
    // Check which context we are in to decide if verification is needed
    $need_verify = false;
    
    // We can't easily check the current hook here because this function is called from inside the hooks.
    // So we need to pass context or deduce it.
    // However, the caller functions know the context.
    
    // But let's check keys first.
    $secret_key = get_option('turnstile_secret_key');
    if (empty($secret_key)) return true; // No key, no check (fail open to avoid lockout)

    if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
         return new WP_Error('turnstile_missing', __('<strong>ERROR</strong>: Please verify you are human.'));
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $_POST['cf-turnstile-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('turnstile_error', __('<strong>ERROR</strong>: Unable to verify Turnstile.'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || !isset($data['success']) || !$data['success']) {
        return new WP_Error('turnstile_invalid', __('<strong>ERROR</strong>: Turnstile verification failed.'));
    }

    return true;
}

// Hook into Login
add_filter('wp_authenticate_user', function($user, $password) {
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_login')) return $user;
    
    if (is_wp_error($user)) return $user;
    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        return $check;
    }
    return $user;
}, 10, 2);

// Hook into Registration
add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_register')) return $errors;

    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        $errors->add($check->get_error_code(), $check->get_error_message());
    }
    return $errors;
}, 10, 3);

// Hook into Comments
add_filter('preprocess_comment', function($commentdata) {
    if (is_user_logged_in()) return $commentdata;
    
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_comments')) return $commentdata;
    
    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        wp_die($check->get_error_message());
    }
    return $commentdata;
});

class Cotlas_Tracking_Codes {

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_head', array($this, 'inject_tracking_codes'));
        add_action('wp_footer', array($this, 'inject_footer_codes'));
        
        // Register shortcodes for company info
        add_shortcode('company_name', array($this, 'sc_company_name'));
        add_shortcode('company_tagline', array($this, 'sc_company_tagline'));
        add_shortcode('company_address', array($this, 'sc_company_address'));
        add_shortcode('company_phone', array($this, 'sc_company_phone'));
        add_shortcode('company_email', array($this, 'sc_company_email'));
        add_shortcode('company_short_intro', array($this, 'sc_company_short_intro'));
        add_shortcode('company_whatsapp', array($this, 'sc_company_whatsapp'));
        
        // Register shortcodes for social media
        add_shortcode('social_facebook', array($this, 'sc_social_facebook'));
        add_shortcode('social_twitter', array($this, 'sc_social_twitter'));
        add_shortcode('social_youtube', array($this, 'sc_social_youtube'));
        add_shortcode('social_instagram', array($this, 'sc_social_instagram'));
        add_shortcode('social_linkedin', array($this, 'sc_social_linkedin'));
        add_shortcode('social_threads', array($this, 'sc_social_threads'));
        
        // Dynamic Data Support for GenerateBlocks (if available)
        add_filter('generateblocks_dynamic_content_output', array($this, 'gb_dynamic_content'), 10, 4);
    }

    // Add admin menu
    public function add_admin_menu() {
        // Priority 98 to be above Site Security (99)
        add_menu_page(
            'Site Settings',
            'Site Settings',
            'manage_options',
            'cotlas-site-settings',
            array($this, 'settings_page_content'),
            'dashicons-admin-settings',
            98
        );
    }

    // Register settings
    public function register_settings() {
        // Analytics & Tracking
        register_setting('cotlas_site_settings', 'cotlas_ga4_code');
        register_setting('cotlas_site_settings', 'cotlas_search_console_code');
        register_setting('cotlas_site_settings', 'cotlas_adsense_code');
        // Custom Scripts
        register_setting('cotlas_site_settings', 'cotlas_header_scripts');
        register_setting('cotlas_site_settings', 'cotlas_footer_scripts');
        
        // Company Info
        register_setting('cotlas_site_settings', 'cotlas_company_name');
        register_setting('cotlas_site_settings', 'cotlas_company_tagline');
        register_setting('cotlas_site_settings', 'cotlas_company_address');
        register_setting('cotlas_site_settings', 'cotlas_company_phone');
        register_setting('cotlas_site_settings', 'cotlas_company_email');
        register_setting('cotlas_site_settings', 'cotlas_company_short_intro');
        register_setting('cotlas_site_settings', 'cotlas_company_whatsapp');
        
        // Social Media
        register_setting('cotlas_site_settings', 'cotlas_social_facebook');
        register_setting('cotlas_site_settings', 'cotlas_social_twitter');
        register_setting('cotlas_site_settings', 'cotlas_social_youtube');
        register_setting('cotlas_site_settings', 'cotlas_social_instagram');
        register_setting('cotlas_site_settings', 'cotlas_social_linkedin');
        register_setting('cotlas_site_settings', 'cotlas_social_threads');
    }

    // Settings page content
    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1>Site Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cotlas_site_settings');
                // do_settings_sections('cotlas_site_settings'); // Manually rendering for better layout
                ?>
                
                <h2 class="title">Company Information</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Company Name</th>
                        <td><input type="text" name="cotlas_company_name" value="<?php echo esc_attr(get_option('cotlas_company_name')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Company Tagline</th>
                        <td><input type="text" name="cotlas_company_tagline" value="<?php echo esc_attr(get_option('cotlas_company_tagline')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Address</th>
                        <td><textarea name="cotlas_company_address" rows="3" cols="50" class="large-text"><?php echo esc_textarea(get_option('cotlas_company_address')); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Phone Number</th>
                        <td><input type="text" name="cotlas_company_phone" value="<?php echo esc_attr(get_option('cotlas_company_phone')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Email Address</th>
                        <td><input type="email" name="cotlas_company_email" value="<?php echo esc_attr(get_option('cotlas_company_email')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Short Intro</th>
                        <td><textarea name="cotlas_company_short_intro" rows="3" cols="50" class="large-text"><?php echo esc_textarea(get_option('cotlas_company_short_intro')); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">WhatsApp Number</th>
                        <td>
                            <input type="text" name="cotlas_company_whatsapp" value="<?php echo esc_attr(get_option('cotlas_company_whatsapp')); ?>" class="regular-text" />
                            <p class="description">Enter number in international format (e.g., 15551234567) without + or spaces.</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Social Media Links</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Facebook Page</th><td><input type="url" name="cotlas_social_facebook" value="<?php echo esc_attr(get_option('cotlas_social_facebook')); ?>" class="regular-text" /></td></tr>
                    <tr valign="top"><th scope="row">Twitter/X Profile</th><td><input type="url" name="cotlas_social_twitter" value="<?php echo esc_attr(get_option('cotlas_social_twitter')); ?>" class="regular-text" /></td></tr>
                    <tr valign="top"><th scope="row">YouTube Channel</th><td><input type="url" name="cotlas_social_youtube" value="<?php echo esc_attr(get_option('cotlas_social_youtube')); ?>" class="regular-text" /></td></tr>
                    <tr valign="top"><th scope="row">Instagram Profile</th><td><input type="url" name="cotlas_social_instagram" value="<?php echo esc_attr(get_option('cotlas_social_instagram')); ?>" class="regular-text" /></td></tr>
                    <tr valign="top"><th scope="row">LinkedIn Page</th><td><input type="url" name="cotlas_social_linkedin" value="<?php echo esc_attr(get_option('cotlas_social_linkedin')); ?>" class="regular-text" /></td></tr>
                    <tr valign="top"><th scope="row">Threads Profile</th><td><input type="url" name="cotlas_social_threads" value="<?php echo esc_attr(get_option('cotlas_social_threads')); ?>" class="regular-text" /></td></tr>
                </table>

                <h2 class="title">Tracking & Scripts</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Analytics 4 (GA4)</th>
                        <td>
                            <input type="text" name="cotlas_ga4_code" value="<?php echo esc_attr(get_option('cotlas_ga4_code')); ?>" placeholder="G-XXXXXXXXXX" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Search Console Meta Tag</th>
                        <td>
                            <textarea name="cotlas_search_console_code" rows="3" cols="50" class="large-text" placeholder='<meta name="google-site-verification" ... />'><?php echo esc_textarea(get_option('cotlas_search_console_code')); ?></textarea>
                            <p class="description">Paste the full meta tag.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AdSense Code</th>
                        <td>
                            <textarea name="cotlas_adsense_code" rows="5" cols="50" class="large-text"><?php echo esc_textarea(get_option('cotlas_adsense_code')); ?></textarea>
                            <p class="description">Paste the full AdSense script. This will be automatically placed in the &lt;head&gt; (recommended by Google).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Header Scripts</th>
                        <td>
                            <textarea name="cotlas_header_scripts" rows="5" cols="50" class="large-text" placeholder="<script>...</script>"><?php echo esc_textarea(get_option('cotlas_header_scripts')); ?></textarea>
                            <p class="description">Any other scripts to be placed in &lt;head&gt;.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Footer Scripts</th>
                        <td>
                            <textarea name="cotlas_footer_scripts" rows="5" cols="50" class="large-text" placeholder="<script>...</script>"><?php echo esc_textarea(get_option('cotlas_footer_scripts')); ?></textarea>
                            <p class="description">Scripts to be placed before &lt;/body&gt;.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <div class="card" style="max-width: 100%; padding: 15px; margin-top: 20px;">
                <h2>Shortcodes Guide</h2>
                <p>Use these shortcodes to display your company information anywhere on the site.</p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Shortcode</th>
                            <th>Dynamic Data Key (GenerateBlocks)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Company Name</td><td><code>[company_name]</code></td><td><code>cotlas_company_name</code></td></tr>
                        <tr><td>Address</td><td><code>[company_address]</code></td><td><code>cotlas_company_address</code></td></tr>
                        <tr><td>Phone</td><td><code>[company_phone]</code></td><td><code>cotlas_company_phone</code></td></tr>
                        <tr><td>Email</td><td><code>[company_email]</code></td><td><code>cotlas_company_email</code></td></tr>
                        <tr><td>WhatsApp</td><td><code>[company_whatsapp]</code></td><td><code>cotlas_company_whatsapp</code></td></tr>
                        <tr><td>Facebook</td><td><code>[social_facebook]</code></td><td><code>cotlas_social_facebook</code></td></tr>
                        <tr><td>Twitter/X</td><td><code>[social_twitter]</code></td><td><code>cotlas_social_twitter</code></td></tr>
                        <tr><td>YouTube</td><td><code>[social_youtube]</code></td><td><code>cotlas_social_youtube</code></td></tr>
                        <tr><td>Instagram</td><td><code>[social_instagram]</code></td><td><code>cotlas_social_instagram</code></td></tr>
                        <tr><td>LinkedIn</td><td><code>[social_linkedin]</code></td><td><code>cotlas_social_linkedin</code></td></tr>
                        <tr><td>Threads</td><td><code>[social_threads]</code></td><td><code>cotlas_social_threads</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // Shortcode Handlers
    public function sc_company_name() { return get_option('cotlas_company_name'); }
    public function sc_company_tagline() { return esc_html(get_option('cotlas_company_tagline')); }
    public function sc_company_address() { return nl2br(esc_html(get_option('cotlas_company_address'))); }
    public function sc_company_phone() { return get_option('cotlas_company_phone'); }
    public function sc_company_email() { return get_option('cotlas_company_email'); }
    public function sc_company_short_intro() { return wp_kses_post(get_option('cotlas_company_short_intro')); }
    public function sc_company_whatsapp() { return get_option('cotlas_company_whatsapp'); }
    
    public function sc_social_facebook() { return get_option('cotlas_social_facebook'); }
    public function sc_social_twitter() { return get_option('cotlas_social_twitter'); }
    public function sc_social_youtube() { return get_option('cotlas_social_youtube'); }
    public function sc_social_instagram() { return get_option('cotlas_social_instagram'); }
    public function sc_social_linkedin() { return get_option('cotlas_social_linkedin'); }
    public function sc_social_threads() { return get_option('cotlas_social_threads'); }

    // GenerateBlocks Dynamic Content Support
    public function gb_dynamic_content($content, $attributes, $block) {
        if (!empty($attributes['dynamicContentType']) && $attributes['dynamicContentType'] === 'post-meta') {
            $meta_key = isset($attributes['metaFieldName']) ? $attributes['metaFieldName'] : '';
            
            // Map meta keys to our options
            $allowed_keys = [
                'cotlas_company_name', 'cotlas_company_tagline', 'cotlas_company_address', 'cotlas_company_phone', 
                'cotlas_company_email', 'cotlas_company_short_intro', 'cotlas_company_whatsapp',
                'cotlas_social_facebook', 'cotlas_social_twitter', 'cotlas_social_youtube',
                'cotlas_social_instagram', 'cotlas_social_linkedin', 'cotlas_social_threads'
            ];

            if (in_array($meta_key, $allowed_keys)) {
                return get_option($meta_key);
            }
        }
        return $content;
    }

    // Inject footer codes
    public function inject_footer_codes() {
        $footer_scripts = get_option('cotlas_footer_scripts');
        if ($footer_scripts) {
            echo "\n<!-- Custom Footer Scripts -->\n";
            echo $footer_scripts . "\n";
        }
    }

    // Inject tracking codes into head
    public function inject_tracking_codes() {
        $ga4_code = get_option('cotlas_ga4_code');
        $search_console_code = get_option('cotlas_search_console_code');
        $adsense_code = get_option('cotlas_adsense_code');
        $header_scripts = get_option('cotlas_header_scripts');

        // Search Console
        if ($search_console_code) {
            echo "\n" . wp_kses($search_console_code, array('meta' => array('name' => array(), 'content' => array()))) . "\n";
        }

        // Custom Header Scripts
        if ($header_scripts) {
            echo "\n<!-- Custom Header Scripts -->\n";
            echo $header_scripts . "\n";
        }

        // AdSense (Always in Head)
        if ($adsense_code) {
            echo "\n<!-- Google AdSense -->\n";
            echo $adsense_code . "\n";
        }

        // Google Analytics 4 — inject whenever a GA4 ID is saved
        if ($ga4_code) {
            echo "<!-- Global site tag (gtag.js) - Google Analytics 4 -->\n";
            echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . esc_js($ga4_code) . "\"></script>\n";
            echo "<script>\n";
            echo "  window.dataLayer = window.dataLayer || [];\n";
            echo "  function gtag(){dataLayer.push(arguments);}\n";
            echo "  gtag('js', new Date());\n";
            echo "  gtag('config', '" . esc_js($ga4_code) . "');\n";
            echo "</script>\n";
        }
    }
}

/**
 * Enqueue mu-plugins admin styles and scripts from local files
 */
function cotlas_admin_scripts() {
    // Get the mu-plugins directory URL
    $mu_plugins_url = plugin_dir_url(__FILE__);
    $mu_plugins_dir = plugin_dir_path(__FILE__);
    
    // Enqueue main admin style from local file
    $admin_css_path = $mu_plugins_url . 'assets/css/style.css';
    $admin_css_file = $mu_plugins_dir . 'assets/css/style.css';
    $admin_css_version = file_exists($admin_css_file) ? (string) filemtime($admin_css_file) : '1.0.0';
    
    wp_enqueue_style(
        'cotlas-admin-style',
        $admin_css_path,
        array(),
        $admin_css_version
    );

    $admin_js_file = $mu_plugins_dir . 'assets/js/admin-menu.js';
    if (file_exists($admin_js_file)) {
        wp_enqueue_script(
            'cotlas-admin-menu',
            $mu_plugins_url . 'assets/js/admin-menu.js',
            array(),
            (string) filemtime($admin_js_file),
            true
        );
    }
    
    // Note: admin-theme.min.js is not available locally, so we'll skip enqueuing it
    // If you need this JavaScript file, please add it to the mu-plugins directory
    // and uncomment the following code:
    /*
    $admin_js_path = $mu_plugins_url . 'admin-theme.min.js';
    wp_enqueue_script(
        'cotlas-admin-theme',
        $admin_js_path,
        array('jquery'),
        '1.0.0',
        true
    );
    */
}
add_action('admin_enqueue_scripts', 'cotlas_admin_scripts');

// Enqueue frontend styles (shortcodes, comments widget, etc.)
add_action('wp_enqueue_scripts', function() {
    $file = plugin_dir_path(__FILE__) . 'assets/css/frontend.css';
    wp_enqueue_style(
        'cotlas-frontend',
        plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
        array(),
        file_exists($file) ? (string) filemtime($file) : '1.0.0'
    );
});

function cotlas_admin_body_class($classes) {
    $classes .= ' cotlas-admin-light';

    return $classes;
}
add_filter('admin_body_class', 'cotlas_admin_body_class');

/**
 * Enqueue honeypot security script (frontend)
 */
function cotlas_enqueue_honeypot_script() {
    if (!is_admin()) {
        // Check if security.js exists locally in mu-plugins directory
        $mu_plugins_dir = plugin_dir_path(__FILE__);
        $local_js_path = $mu_plugins_dir . 'assets/js/security.js';
        
        if (file_exists($local_js_path)) {
            // Use local file if it exists
            $security_js_url = plugin_dir_url(__FILE__) . 'assets/js/security.js';
            $ver = filemtime($local_js_path);
        } else {
            // No local security.js file found
            // If you need honeypot JavaScript functionality, please add security.js to the mu-plugins directory
            return;
        }

        wp_enqueue_script(
            'cotlas-honeypot',
            $security_js_url,
            array('jquery'),
            $ver,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'cotlas_enqueue_honeypot_script');

// Initialize the Tracking Codes class only after plugins are loaded
add_action('plugins_loaded', function() {
    new Cotlas_Tracking_Codes();
});

add_filter('user_contactmethods', function($methods) {
    $methods['facebook']  = __('Facebook profile URL', 'cotlas');
    $methods['twitter']   = __('Twitter/X profile URL', 'cotlas');
    $methods['instagram'] = __('Instagram profile URL', 'cotlas');
    $methods['linkedin']  = __('LinkedIn profile URL', 'cotlas');
    $methods['youtube']   = __('YouTube profile URL', 'cotlas');
    $methods['pinterest'] = __('Pinterest profile URL', 'cotlas');
    return $methods;
});

// Register GenerateBlocks Dynamic Tags using Cotlas Site Settings
add_action('init', function() {
    if (!class_exists('GenerateBlocks_Register_Dynamic_Tag')) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag([
        'title'    => __('Company Info', 'cotlas'),
        'tag'      => 'company_info',
        'type'     => 'option',
        'supports' => [],
        'options'  => [
            'field' => [
                'type'    => 'select',
                'label'   => __('Field', 'cotlas'),
                'default' => 'company_name',
                'options' => [
                    ['value' => 'company_name',    'label' => __('Company Name', 'cotlas')],
                    ['value' => 'company_tagline', 'label' => __('Company Tagline', 'cotlas')],
                    ['value' => 'company_address', 'label' => __('Company Address', 'cotlas')],
                    ['value' => 'company_phone',   'label' => __('Company Phone', 'cotlas')],
                    ['value' => 'company_email',   'label' => __('Company Email', 'cotlas')],
                    ['value' => 'company_short_intro','label' => __('Company Short Intro', 'cotlas')],
                    ['value' => 'company_whatsapp','label' => __('Company WhatsApp', 'cotlas')],
                ],
            ],
        ],
        'return' => 'cotlas_company_info_dynamic_tag',
    ]);

    new GenerateBlocks_Register_Dynamic_Tag([
        'title'    => __('Company Social URL', 'cotlas'),
        'tag'      => 'company_social',
        'type'     => 'option',
        'supports' => [],
        'options'  => [
            'network' => [
                'type'    => 'select',
                'label'   => __('Social Network', 'cotlas'),
                'default' => 'facebook',
                'options' => [
                    ['value' => 'facebook',  'label' => __('Facebook', 'cotlas')],
                    ['value' => 'twitter',   'label' => __('Twitter/X', 'cotlas')],
                    ['value' => 'instagram', 'label' => __('Instagram', 'cotlas')],
                    ['value' => 'linkedin',  'label' => __('LinkedIn', 'cotlas')],
                    ['value' => 'youtube',   'label' => __('YouTube', 'cotlas')],
                    ['value' => 'threads',   'label' => __('Threads', 'cotlas')],
                    ['value' => 'whatsapp',  'label' => __('WhatsApp', 'cotlas')],
                ],
            ],
        ],
        'return' => 'cotlas_company_social_dynamic_tag',
    ]);
});

function cotlas_company_info_dynamic_tag($options, $block, $instance) {
    $field = isset($options['field']) ? $options['field'] : 'company_name';
    $map = [
        'company_name'     => 'cotlas_company_name',
        'company_tagline'  => 'cotlas_company_tagline',
        'company_address'  => 'cotlas_company_address',
        'company_phone'    => 'cotlas_company_phone',
        'company_email'    => 'cotlas_company_email',
        'company_short_intro' => 'cotlas_company_short_intro',
        'company_whatsapp' => 'cotlas_company_whatsapp',
    ];
    if (!isset($map[$field])) {
        return '';
    }
    $value = get_option($map[$field]);
    if ($field === 'company_address') {
        $value = nl2br(esc_html($value));
    }
    if ($field === 'company_tagline') {
        $value = esc_html($value);
    }
    if ($field === 'company_short_intro') {
        $value = wp_kses_post($value);
    }
    if (class_exists('GenerateBlocks_Dynamic_Tag_Callbacks')) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output($value, $options, $instance);
    }
    return $value;
}

function cotlas_company_social_dynamic_tag($options, $block, $instance) {
    $network = isset($options['network']) ? $options['network'] : 'facebook';
    $map = [
        'facebook'  => 'cotlas_social_facebook',
        'twitter'   => 'cotlas_social_twitter',
        'instagram' => 'cotlas_social_instagram',
        'linkedin'  => 'cotlas_social_linkedin',
        'youtube'   => 'cotlas_social_youtube',
        'threads'   => 'cotlas_social_threads',
        'whatsapp'  => 'cotlas_company_whatsapp',
    ];
    if (!isset($map[$network])) {
        return '';
    }
    $value = get_option($map[$network]);
    if ($network === 'whatsapp' && !empty($value)) {
        $clean = preg_replace('/\\D+/', '', $value);
        if ($clean) {
            $value = 'https://wa.me/' . $clean;
        }
    }
    if (class_exists('GenerateBlocks_Dynamic_Tag_Callbacks')) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output($value, $options, $instance);
    }
    return $value;
}

class CotlasSocialMedia {
    private $social_platforms;
    public function __construct() {
        // Register Dynamic URL Filter
        add_filter( 'generateblocks_dynamic_url_output', array($this, 'gb_dynamic_urls'), 10, 3 );

        $this->social_platforms = array(
            'facebook' => array(
                'name' => 'Facebook',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path></svg>'
            ),
            'twitter' => array(
                'name' => 'X (Twitter)',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.982 10.622 20.54 3h-1.554l-5.693 6.618L8.745 3H3.5l6.876 10.007L3.5 21h1.554l6.012-6.989L15.868 21h5.245l-7.131-10.378Zm-2.128 2.474-.697-.997-5.543-7.93H8l4.474 6.4.697.996 5.815 8.318h-2.387l-4.745-6.787Z"></path></svg>'
            ),
            'instagram' => array(
                'name' => 'Instagram',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12,4.622c2.403,0,2.688,0.009,3.637,0.052c0.877,0.04,1.354,0.187,1.671,0.31c0.42,0.163,0.72,0.358,1.035,0.673 c0.315,0.315,0.51,0.615,0.673,1.035c0.123,0.317,0.27,0.794,0.31,1.671c0.043,0.949,0.052,1.234,0.052,3.637 s-0.009,2.688-0.052,3.637c-0.04,0.877-0.187,1.354-0.31,1.671c-0.163,0.42-0.358,0.72-0.673,1.035 c-0.315,0.315-0.615,0.51-1.035,0.673c-0.317,0.123-0.794,0.27-1.671,0.31c-0.949,0.043-1.233,0.052-3.637,0.052 s-2.688-0.009-3.637-0.052c-0.877-0.04-1.354-0.187-1.671-0.31c-0.42-0.163-0.72-0.358-1.035-0.673 c-0.315-0.315-0.51-0.615-0.673-1.035c-0.123-0.317-0.27-0.794-0.31-1.671C4.631,14.688,4.622,14.403,4.622,12 s0.009-2.688,0.052-3.637c-0.04-0.877,0.187-1.354,0.31-1.671c0.163-0.42,0.358-0.72,0.673-1.035 c0.315-0.315,0.615-0.51,1.035-0.673c0.317-0.123,0.794-0.27,1.671-0.31C9.312,4.631,9.597,4.622,12,4.622 M12,3 C9.556,3,9.249,3.01,8.289,3.054C7.331,3.098,6.677,3.25,6.105,3.472C5.513,3.702,5.011,4.01,4.511,4.511 c-0.5,0.5-0.808,1.002-1.038,1.594C3.25,6.677,3.098,7.331,3.054,8.289C3.01,9.249,3,9.556,3,12c0,2.444,0.01,2.751,0.054,3.711 c0.044,0.958,0.196,1.612,0.418,2.185c0.23,0.592,0.538,1.094,1.038,1.594c0.5,0.5,1.002,0.808,1.594,1.038 c0.572,0.222,1.227,0.375,2.185,0.418C9.249,20.99,9.556,21,12,21s2.751-0.01,3.711-0.054c0.958-0.044,1.612-0.196,2.185-0.418 c0.592-0.23,1.094-0.538,1.594-1.038c0.5-0.5,0.808-1.002,1.038-1.594c0.222-0.572,0.375-1.227,0.418-2.185 C20.99,14.751,21,14.444,21,12s-0.01-2.751-0.054-3.711c-0.044-0.958-0.196-1.612-0.418-2.185c-0.23-0.592-0.538-1.094-1.038-1.594 c-0.5-0.5-1.002-0.808-1.594-1.038c-0.572-0.222-1.227-0.375-2.185-0.418C14.751,3.01,14.444,3,12,3L12,3z M12,7.378 c-2.552,0-4.622,2.069-4.622,4.622S9.448,16.622,12,16.622s4.622-2.069,4.622-4.622S14.552,7.378,12,7.378z M12,15 c-1.657,0-3-1.343-3-3s1.343-3,3-3s3,1.343,3,3S13.657,15,12,15z M16.804,6.116c-0.596,0-1.08,0.484-1.08,1.08 s0.484,1.08,1.08,1.08c0.596,0,1.08-0.4"></path></svg>'
            ),
            'youtube' => array(
                'name' => 'YouTube',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.8,8.001c0,0-0.195-1.378-0.795-1.985c-0.76-0.797-1.613-0.801-2.004-0.847c-2.799-0.202-6.997-0.202-6.997-0.202 h-0.009c0,0-4.198,0-6.997,0.202C4.608,5.216,3.756,5.22,2.995,6.016C2.395,6.623,2.2,8.001,2.2,8.001S2,9.62,2,11.238v1.517 c0,1.618,0.2,3.237,0.2,3.237s0.195,1.378,0.795,1.985c0.761,0.797,1.76,0.771,2.205,0.855c1.6,0.153,6.8,0.201,6.8,0.201 s4.203-0.006,7.001-0.209c0.391-0.047,1.243-0.051,2.004-0.847c0.6-0.607,0.795-1.985,0.795-1.985s0.2-1.618,0.2-3.237v-1.517 C22,9.62,21.8,8.001,21.8,8.001z M9.935,14.594l-0.001-5.62l5.404,2.82L9.935,14.594z"></path></svg>'
            ),
            'linkedin' => array(
                'name' => 'LinkedIn',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19.7,3H4.3C3.582,3,3,3.582,3,4.3v15.4C3,20.418,3.582,21,4.3,21h15.4c0.718,0,1.3-0.582,1.3-1.3V4.3 C21,3.582,20.418,3,19.7,3z M8.339,18.338H5.667v-8.59h2.672V18.338z M7.004,8.574c-0.857,0-1.549-0.694-1.549-1.548 c0-0.855,0.691-1.548,1.549-1.548c0.854,0,1.547,0.694,1.547,1.548C8.551,7.881,7.858,8.574,7.004,8.574z M18.339,18.338h-2.669 v-4.177c0-0.996-0.017-2.278-1.387-2.278c-1.389,0-1.601,1.086-1.601,2.206v4.249h-2.667v-8.59h2.559v1.174h0.037 c0.356-0.675,1.227-1.387,2.526-1.387c2.703,0,3.203,1.779,3.203,4.092V18.338z"></path></svg>'
            ),
            'threads' => array(
                'name' => 'Threads',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10c3.86 0 7.238-2.19 8.928-5.387l-2.2-1.151C17.91 17.8 15.145 19 12 19c-3.866 0-7-3.134-7-7s3.134-7 7-7c3.16 0 5.926 1.206 6.73 3.555l2.2-1.151C19.246 4.189 15.865 2 12 2zm0 5c-2.21 0-4 1.567-4 3.5S9.79 14 12 14c1.657 0 3-1.12 3-2.5 0-1.379-1.343-2.5-3-2.5z"></path></svg>'
            ),
            'whatsapp' => array(
                'name' => 'WhatsApp',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-label="WhatsApp" role="img" viewBox="0 0 512 512" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><rect width="512" height="512" rx="15%" fill="#25d366"></rect><path fill="#25d366" stroke="#ffffff" stroke-width="26" d="M123 393l14-65a138 138 0 1150 47z"></path><path fill="#ffffff" d="M308 273c-3-2-6-3-9 1l-12 16c-3 2-5 3-9 1-15-8-36-17-54-47-1-4 1-6 3-8l9-14c2-2 1-4 0-6l-12-29c-3-8-6-7-9-7h-8c-2 0-6 1-10 5-22 22-13 53 3 73 3 4 23 40 66 59 32 14 39 12 48 10 11-1 22-10 27-19 1-3 6-16 2-18"></path></g></svg>'
            )
        );
        add_shortcode('cotlas_social', array($this, 'social_shortcode'));
    }

    // GenerateBlocks Dynamic URL Support for Options
    public function gb_dynamic_urls( $url, $attributes, $block ) {
        if ( ! empty( $attributes['dynamicLinkType'] ) && $attributes['dynamicLinkType'] === 'post-meta' ) {
            $meta_key = isset( $attributes['linkMetaFieldName'] ) ? $attributes['linkMetaFieldName'] : '';
            
            $allowed_keys = [
                'cotlas_company_phone', 'cotlas_company_email', 'cotlas_company_whatsapp',
                'cotlas_social_facebook', 'cotlas_social_twitter', 'cotlas_social_youtube',
                'cotlas_social_instagram', 'cotlas_social_linkedin', 'cotlas_social_threads'
            ];

            if ( in_array( $meta_key, $allowed_keys ) ) {
                return get_option( $meta_key );
            }
        }
        return $url;
    }

    public function social_shortcode($atts) {
        $atts = shortcode_atts(array(
            'size' => '24',
            'class' => '',
            'show_names' => 'false',
            'networks' => ''
        ), $atts);
        $links = array(
            'facebook'  => get_option('cotlas_social_facebook'),
            'twitter'   => get_option('cotlas_social_twitter'),
            'instagram' => get_option('cotlas_social_instagram'),
            'youtube'   => get_option('cotlas_social_youtube'),
            'linkedin'  => get_option('cotlas_social_linkedin'),
            'threads'   => get_option('cotlas_social_threads'),
            'whatsapp'  => get_option('cotlas_company_whatsapp'),
        );
        if (!empty($atts['networks'])) {
            $requested = array_filter(array_map('trim', explode(',', strtolower($atts['networks']))));
            $normalized = array_map(function($n) { return $n === 'x' ? 'twitter' : $n; }, $requested);
            $links = array_filter($links, function($key) use ($normalized) {
                return in_array($key, $normalized, true);
            }, ARRAY_FILTER_USE_KEY);
        }
        $has = false;
        foreach ($links as $u) { if (!empty($u)) { $has = true; break; } }
        if (!$has) return '';
        $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);
        $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
        ob_start();
        ?>
        <div class="cotlas-social-links <?php echo esc_attr($atts['class']); ?>">
            <ul class="social-icons-list">
                <?php foreach ($links as $platform => $url): ?>
                    <?php
                        if (empty($url) || !isset($this->social_platforms[$platform])) { continue; }
                        if ($platform === 'whatsapp') {
                            $clean = preg_replace('/\D+/', '', $url);
                            $url = $clean ? ('https://wa.me/' . $clean) : esc_url($url);
                        }
                        $icon = preg_replace('/width="\d+"/', 'width="' . $size . '"', $this->social_platforms[$platform]['icon']);
                        $icon = preg_replace('/height="\d+"/', 'height="' . $size . '"', $icon);
                    ?>
                    <li class="social-link social-link-<?php echo esc_attr($platform); ?>">
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($this->social_platforms[$platform]['name'] . ' (opens in a new window)'); ?>">
                            <?php echo $icon; ?>
                            <?php if ($show_names): ?>
                                <span class="social-platform-name"><?php echo esc_html($this->social_platforms[$platform]['name']); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_action('plugins_loaded', function() { new CotlasSocialMedia(); });

/**
 * Automate CSS/Font Regeneration after WP Migrate DB Pro migration
 */
class Cotlas_Migration_Helper {
    public function __construct() {
        // Hook into WP Migrate DB Pro completion actions
        // wpmdb_migration_complete is fired when a migration finishes
        add_action('wpmdb_migration_complete', array($this, 'post_migration_cleanup'), 10, 2);
        
        // Also hook into import completion if applicable
        add_action('wpmdb_import_complete', array($this, 'post_migration_cleanup'));
    }

    public function post_migration_cleanup($migration_type = '', $migration_id = '') {
        $this->regenerate_generateblocks();
        $this->regenerate_elementor();
        $this->regenerate_generatepress_fonts();
        
        // As a fallback, try to find and replace URLs in uploads/generatepress and uploads/generateblocks
        $this->replace_urls_in_css_files();
    }

    private function regenerate_generateblocks() {
        // Force GenerateBlocks to regenerate CSS by clearing the cache option
        update_option('generateblocks_dynamic_css_posts', array());
        update_option('generateblocks_dynamic_css_time', time());
    }

    private function regenerate_elementor() {
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    private function regenerate_generatepress_fonts() {
        // GeneratePress stores font settings in options, but the CSS file is static.
        // We can trigger a save if we know the function, but often clearing the cached file is enough.
        // GP Premium Font Library might need specific handling.
        
        // If GP Font Library is active, we might need to re-save to trigger regeneration.
        if (class_exists('GeneratePress_Pro_Font_Library')) {
             // This is a bit trickier as there isn't a public "regenerate" method.
             // We will rely on the file replacement method below for this.
        }
    }

    private function replace_urls_in_css_files() {
        $upload_dir = wp_upload_dir();
        $basedir = $upload_dir['basedir'];
        $baseurl = $upload_dir['baseurl'];
        
        // Current site URL (the destination URL)
        $current_site_url = home_url();
        $current_site_domain = parse_url($current_site_url, PHP_URL_HOST);
        
        // We need to find files that contain the OLD domain.
        // Since we don't know the old domain explicitly in this context without querying the DB for old revisions,
        // we can scan for ANY url that does NOT match the current site URL but looks like a development URL (.local, .test, etc)
        // OR, more safely, we just scan for the *files* we know about and replace any http/https URL that isn't the current one.
        
        $dirs_to_scan = [
            $basedir . '/generateblocks/',
            $basedir . '/generatepress/fonts/'
        ];

        foreach ($dirs_to_scan as $dir) {
            if (!is_dir($dir)) continue;

            $files = glob($dir . '*.css');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (!$content) continue;

                // Regex to find URLs in css: url('...') or url("...")
                // We want to replace the domain part.
                
                $new_content = preg_replace_callback('/url\([\'"]?(https?:\/\/[^\/]+)(.*?)[\'"]?\)/i', function($matches) use ($current_site_url) {
                    $found_url = $matches[1]; // e.g. https://advago.local
                    $path = $matches[2];      // e.g. /wp-content/...
                    
                    // If the found URL is NOT the current site URL, replace it
                    if ($found_url !== $current_site_url) {
                        return "url('" . $current_site_url . $path . "')";
                    }
                    return $matches[0];
                }, $content);

                if ($new_content !== $content) {
                    file_put_contents($file, $new_content);
                }
            }
        }
    }
}

new Cotlas_Migration_Helper();

/**
 * Trending Categories Shortcode
 *
 * Displays top categories ranked by total post views (Post Views Counter plugin).
 * Automatically falls back to categories with the most recent posts when no view
 * data is available — making it safe to use on new/boilerplate installs.
 *
 * Usage: [trending_categories count="6" label=""]
 *
 * Attributes:
 *   count  - number of categories to show (default: 6, max: 20)
 *   label  - optional heading text shown above the list
 */
class Cotlas_Trending_Categories {

    public function __construct() {
        add_shortcode( 'trending_categories', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'count' => 6,
            'label' => '',
        ), $atts, 'trending_categories' );

        $count = max( 1, min( 20, intval( $atts['count'] ) ) );
        $label = sanitize_text_field( $atts['label'] );

        // Transient cache — 1 hour per unique count value
        $cache_key  = 'cotlas_trending_cats_' . $count;
        $categories = get_transient( $cache_key );

        if ( false === $categories ) {
            $categories = $this->get_trending_categories( $count );
            set_transient( $cache_key, $categories, HOUR_IN_SECONDS );
        }

        if ( empty( $categories ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="cotlas-trending-wrap">
            <?php if ( $label ) : ?>
                <p class="cotlas-trending-label"><?php echo esc_html( $label ); ?></p>
            <?php endif; ?>
            <ul class="cotlas-trending-list">
                <?php foreach ( $categories as $cat ) : ?>
                    <li class="cotlas-trending-item">
                        <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="cotlas-trending-link">
                            <span class="cotlas-trending-icon" aria-hidden="true"><?php echo $this->flame_icon(); ?></span>
                            <span class="cotlas-trending-name"><?php echo esc_html( $cat->name ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build the ranked category list.
     * 1. Try Post Views Counter view-based ranking.
     * 2. Fill any remaining slots from most-recent posts.
     */
    private function get_trending_categories( $count ) {
        $view_based = $this->categories_by_views( $count );

        if ( count( $view_based ) >= $count ) {
            return $view_based;
        }

        $existing_ids = wp_list_pluck( $view_based, 'term_id' );
        $recent_based = $this->categories_by_recency( $count - count( $view_based ), $existing_ids );

        return array_merge( $view_based, $recent_based );
    }

    /**
     * Get categories from posts ordered by Post Views Counter total views.
     * Returns an empty array when PVC has no data yet (all views = 0).
     */
    private function categories_by_views( $count ) {
        if ( ! function_exists( 'pvc_get_post_views' ) ) {
            return array();
        }

        $post_ids = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count * 8,
            'orderby'        => 'post_views',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        if ( empty( $post_ids ) ) {
            return array();
        }

        // Bail out if the top post has 0 views — no useful data yet
        if ( pvc_get_post_views( $post_ids[0] ) < 1 ) {
            return array();
        }

        return $this->posts_to_unique_categories( $post_ids, $count );
    }

    /**
     * Get categories from the most recently published posts.
     */
    private function categories_by_recency( $count, $exclude_term_ids = array() ) {
        $post_ids = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count * 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        return $this->posts_to_unique_categories( $post_ids, $count, $exclude_term_ids );
    }

    /**
     * Walk a list of post IDs and collect unique primary categories up to $limit.
     */
    private function posts_to_unique_categories( $post_ids, $limit, $exclude_term_ids = array() ) {
        $seen   = array_flip( $exclude_term_ids );
        $result = array();

        foreach ( $post_ids as $post_id ) {
            if ( count( $result ) >= $limit ) {
                break;
            }

            $primary = $this->get_primary_category( (int) $post_id );

            if ( ! $primary || isset( $seen[ $primary->term_id ] ) ) {
                continue;
            }

            $seen[ $primary->term_id ] = true;
            $result[]                  = $primary;
        }

        return $result;
    }

    /**
     * Return the primary category for a post.
     * Respects Yoast SEO primary category when available.
     * Skips "Uncategorized" unless it is the only option.
     */
    private function get_primary_category( $post_id ) {
        // Yoast SEO primary category
        if ( class_exists( 'WPSEO_Primary_Term' ) ) {
            $pt      = new WPSEO_Primary_Term( 'category', $post_id );
            $term_id = $pt->get_primary_term();

            if ( $term_id ) {
                $term = get_term( (int) $term_id, 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    return $term;
                }
            }
        }

        $terms = get_the_terms( $post_id, 'category' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return null;
        }

        // Prefer any term that isn't "Uncategorized"
        foreach ( $terms as $term ) {
            if ( 'uncategorized' !== $term->slug ) {
                return $term;
            }
        }

        return $terms[0];
    }

    /**
     * Flame / trending SVG icon.
     */
    private function flame_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.66 11.2C17.43 10.9 17.15 10.64 16.89 10.38C16.22 9.78 15.46 9.35 14.82 8.72C13.33 7.26 13 4.85 13.95 3C13 3.23 12.17 3.75 11.46 4.32C8.87 6.4 7.85 10.07 9.07 13.22C9.11 13.32 9.15 13.42 9.15 13.55C9.15 13.77 9 13.97 8.8 14.05C8.57 14.15 8.33 14.09 8.14 13.93C8.08 13.88 8.04 13.83 8 13.76C6.87 12.33 6.69 10.28 7.45 8.64C5.78 10 4.87 12.3 5 14.47C5.06 14.97 5.12 15.47 5.29 15.97C5.43 16.57 5.7 17.17 6 17.7C7.08 19.43 8.95 20.67 10.96 20.92C13.1 21.19 15.39 20.8 17.03 19.32C18.86 17.66 19.5 15 18.56 12.72L18.43 12.46C18.22 12 17.66 11.2 17.66 11.2Z"/></svg>';
    }
}
add_action( 'plugins_loaded', function() { new Cotlas_Trending_Categories(); } );

/**
 * Most Read Articles Shortcode
 *
 * Displays top posts ordered by Post Views Counter total views.
 * Falls back to most recent posts when no view data is available.
 *
 * Usage: [most_read count="3"]
 *
 * Attributes:
 *   count - number of articles to show (default: 3, max: 10)
 */
class Cotlas_Most_Read {

    public function __construct() {
        add_shortcode( 'most_read', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts  = shortcode_atts( array( 'count' => 3 ), $atts, 'most_read' );
        $count = max( 1, min( 10, intval( $atts['count'] ) ) );

        $cache_key = 'cotlas_most_read_' . $count;
        $posts     = get_transient( $cache_key );

        if ( false === $posts ) {
            $posts = $this->get_posts( $count );
            set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
        }

        if ( empty( $posts ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="cotlas-most-read-wrap">
            <ol class="cotlas-most-read-list">
                <?php foreach ( $posts as $i => $post ) :
                    $thumb  = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
                    $title  = get_the_title( $post->ID );
                    $link   = get_permalink( $post->ID );
                    $cat    = $this->get_primary_cat_name( $post->ID );
                ?>
                <li class="cotlas-most-read-item">
                    <a href="<?php echo esc_url( $link ); ?>" class="cotlas-most-read-link">
                        <?php if ( $thumb ) : ?>
                            <div class="cotlas-most-read-thumb">
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" width="64" height="64">
                            </div>
                        <?php else : ?>
                            <div class="cotlas-most-read-thumb cotlas-most-read-thumb--no-img">
                                <span class="cotlas-most-read-num"><?php echo esc_html( $i + 1 ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="cotlas-most-read-body">
                            <?php if ( $cat ) : ?>
                                <span class="cotlas-most-read-cat"><?php echo esc_html( $cat ); ?></span>
                            <?php endif; ?>
                            <p class="cotlas-most-read-title"><?php echo esc_html( $title ); ?></p>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_posts( $count ) {
        $has_views = function_exists( 'pvc_get_post_views' );
        $orderby   = 'date';

        if ( $has_views ) {
            // Check if we have any real view data before ordering by views
            $probe = get_posts( array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'post_views',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ) );
            if ( ! empty( $probe ) && pvc_get_post_views( $probe[0] ) > 0 ) {
                $orderby = 'post_views';
            }
        }

        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => $orderby,
            'order'          => 'DESC',
        ) );
    }

    private function get_primary_cat_name( $post_id ) {
        if ( class_exists( 'WPSEO_Primary_Term' ) ) {
            $pt      = new WPSEO_Primary_Term( 'category', $post_id );
            $term_id = $pt->get_primary_term();
            if ( $term_id ) {
                $term = get_term( (int) $term_id, 'category' );
                if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
                    return $term->name;
                }
            }
        }

        $terms = get_the_terms( $post_id, 'category' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }

        foreach ( $terms as $term ) {
            if ( 'uncategorized' !== $term->slug ) {
                return $term->name;
            }
        }

        return '';
    }
}
add_action( 'plugins_loaded', function() { new Cotlas_Most_Read(); } );

/* ==========================================================================
 * Facebook-style Comments — [cotlas_comments]
 * Renders the comment list + form for the current post.
 * ========================================================================== */

/**
 * Get commenter avatar — Gravatar URL or a coloured-initial SVG data-URI.
 */
function cotlas_comment_avatar( $email, $name, $size = 40 ) {
    $gravatar = get_avatar_url( $email, array( 'size' => $size * 2, 'd' => '404' ) );
    // We return both so JS can fall back; but for PHP render we always use the initials
    // wrapper and let the <img> onerror swap itself.
    $initials = mb_strtoupper( mb_substr( strip_tags( $name ), 0, 1 ), 'UTF-8' );
    $colors   = array( '#e74c3c','#e67e22','#2ecc71','#3498db','#9b59b6','#1abc9c','#e91e63','#ff5722' );
    $color    = $colors[ abs( crc32( $email ) ) % count( $colors ) ];
    return array(
        'src'      => $gravatar,
        'initials' => $initials,
        'color'    => $color,
    );
}

/**
 * Render a single comment bubble (used recursively for replies).
 */
function cotlas_render_comment( $comment, $depth = 0, $top_id = 0 ) {
    $author   = esc_html( get_comment_author( $comment ) );
    $email    = get_comment_author_email( $comment );
    $content  = get_comment_text( $comment );
    $date_ts  = strtotime( $comment->comment_date_gmt );
    $now      = time();
    $diff     = $now - $date_ts;

    if ( $diff < 5 ) {
        $ago = 'अभी';
    } elseif ( $diff < 60 ) {
        $ago = $diff . ' सेकंड पहले';
    } elseif ( $diff < 3600 ) {
        $mins = (int)( $diff / 60 );
        $ago  = $mins . ' मिनट पहले';
    } elseif ( $diff < 43200 ) {
        $hrs = (int)( $diff / 3600 );
        $ago = $hrs . ' घंटे पहले';
    } else {
        $ago = date_i18n( 'd M Y, g:i a', $date_ts + ( get_option('gmt_offset') * 3600 ) );
    }

    $avatar   = cotlas_comment_avatar( $email, $author );
    $cid      = (int) $comment->comment_ID;
    $approved = '1' === $comment->comment_approved;

    // ── Edit permission check ─────────────────────────────────────
    $can_edit = false;
    $cu       = wp_get_current_user();
    if ( $cu->exists() && $cu->ID > 0 && (int) $comment->user_id === $cu->ID ) {
        $can_edit = true;
    } else {
        // Guest: cookie name+email match + within 60 minutes of posting
        $ck_author = isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] )
            ? sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) : '';
        $ck_email  = isset( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] )
            ? sanitize_email( wp_unslash( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] ) ) : '';
        if ( $ck_author !== '' && $ck_email !== ''
             && $ck_author === $comment->comment_author
             && $ck_email  === $comment->comment_author_email
             && $diff < 3600 ) {
            $can_edit = true;
        }
    }
    $edit_nonce = $can_edit ? wp_create_nonce( 'ctc_edit_' . $cid ) : '';

    ob_start();
    ?>
    <div class="ctc-comment<?php echo $depth > 0 ? ' ctc-comment--reply' : ''; ?>" id="ctc-c-<?php echo $cid; ?>">
        <div class="ctc-comment__avatar" style="background:<?php echo esc_attr( $avatar['color'] ); ?>">
            <img src="<?php echo esc_url( $avatar['src'] ); ?>"
                 alt="<?php echo esc_attr( $author ); ?>"
                 width="36" height="36"
                 loading="lazy"
                 onerror="this.style.display='none'" />
            <span class="ctc-comment__initial"><?php echo esc_html( $avatar['initials'] ); ?></span>
        </div>
        <div class="ctc-comment__body">
            <div class="ctc-comment__bubble">
                <span class="ctc-comment__author"><?php echo $author; ?></span>
                <?php if ( ! $approved ) : ?>
                    <span class="ctc-comment__pending">⏳ अनुमोदन में</span>
                <?php endif; ?>
                <div class="ctc-comment__text" data-raw="<?php echo esc_attr( $content ); ?>"><?php echo wp_kses_post( $content ); ?></div>
            </div>
            <div class="ctc-comment__meta">
                <span class="ctc-comment__time"><?php echo esc_html( $ago ); ?></span>
                <?php
                $reply_target = ( $depth === 0 ) ? $cid : (int) $top_id;
                if ( $reply_target ) : ?>
                    <button class="ctc-reply-btn" data-cid="<?php echo $reply_target; ?>">Reply</button>
                <?php endif; ?>
                <?php if ( $can_edit ) : ?>
                    <button class="ctc-edit-btn" data-cid="<?php echo $cid; ?>" data-nonce="<?php echo esc_attr( $edit_nonce ); ?>">Edit</button>
                <?php endif; ?>
            </div>
            <?php if ( $depth === 0 ) : ?>
            <div class="ctc-inline-reply" id="ctc-reply-<?php echo $cid; ?>" style="display:none;"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [cotlas_comments]
 * Renders comment list + form for the current post.
 */
function cotlas_comments_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'post_id' => 0,
        'title'   => 'Comments',
    ), $atts, 'cotlas_comments' );

    $post_id = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $post = get_post( $post_id );
    if ( ! $post || ! comments_open( $post_id ) ) {
        return '';
    }

    // ── fetch approved comments ──────────────────────────────────────
    $comments = get_comments( array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'order'   => 'ASC',
        'type'    => 'comment',
    ) );

    // ── separate top-level and replies ──────────────────────────────
    // Build lookup map and collect top-level comments
    $id_to_comment = array();
    $top           = array();
    foreach ( $comments as $c ) {
        $id_to_comment[ (int) $c->comment_ID ] = $c;
        if ( 0 == $c->comment_parent ) {
            $top[] = $c;
        }
    }

    // Walk up to find the top-level ancestor for any comment
    $get_top_ancestor = function( $comment ) use ( &$get_top_ancestor, $id_to_comment ) {
        if ( 0 == $comment->comment_parent ) {
            return (int) $comment->comment_ID;
        }
        $parent_id = (int) $comment->comment_parent;
        if ( isset( $id_to_comment[ $parent_id ] ) ) {
            return $get_top_ancestor( $id_to_comment[ $parent_id ] );
        }
        return (int) $comment->comment_ID; // orphan fallback
    };

    // Flatten ALL replies under their top-level ancestor (enforces 2-level depth)
    $reply_groups = array(); // top_level_id => [ reply comments ]
    foreach ( $comments as $c ) {
        if ( 0 == $c->comment_parent ) continue;
        $ancestor_id = $get_top_ancestor( $c );
        $reply_groups[ $ancestor_id ][] = $c;
    }

    $count = count( $comments );

    // ── current user info for form ──────────────────────────────────
    $current_user = wp_get_current_user();
    $logged_in    = $current_user->exists();
    $user_name    = $logged_in ? esc_html( $current_user->display_name ) : '';
    $user_email   = $logged_in ? esc_html( $current_user->user_email )  : '';

    $uid = 'ctc-' . $post_id;

    ob_start();
    ?>
    <div class="cotlas-comments" id="<?php echo esc_attr( $uid ); ?>">

        <!-- ── Comment count heading ── -->
        <div class="ctc-heading">
            <span class="ctc-heading__icon">💬</span>
            <span class="ctc-heading__title"><?php echo esc_html( $atts['title'] ); ?></span>
            <?php if ( $count ) : ?>
                <span class="ctc-heading__count"><?php echo $count; ?></span>
            <?php endif; ?>
        </div>

        <!-- ── Comment form ── -->
        <?php if ( is_user_logged_in() || get_option( 'comment_registration' ) == 0 ) : ?>
        <form class="ctc-form" method="post" action="<?php echo esc_url( site_url('/wp-comments-post.php') ); ?>">
            <div class="ctc-form__row">
                <?php if ( $logged_in ) : ?>
                    <?php $av = cotlas_comment_avatar( $user_email, $user_name ); ?>
                    <div class="ctc-comment__avatar ctc-form__avatar" style="background:<?php echo esc_attr($av['color']); ?>">
                        <img src="<?php echo esc_url($av['src']); ?>" width="36" height="36" loading="lazy" onerror="this.style.display='none'" />
                        <span class="ctc-comment__initial"><?php echo esc_html($av['initials']); ?></span>
                    </div>
                <?php else : ?>
                    <div class="ctc-comment__avatar ctc-form__avatar" style="background:#aaa;">
                        <span class="ctc-comment__initial">?</span>
                    </div>
                <?php endif; ?>

                <div class="ctc-form__inputs">
                    <?php if ( ! $logged_in ) : ?>
                        <div class="ctc-form__guest-fields">
                            <input type="text" name="author" class="ctc-input" placeholder="आपका नाम *" required maxlength="100" />
                            <input type="email" name="email" class="ctc-input" placeholder="ईमेल *" required maxlength="200" />
                        </div>
                    <?php endif; ?>
                    <div class="ctc-form__textarea-wrap">
                        <textarea name="comment" class="ctc-textarea" placeholder="अपनी राय लिखें…" rows="3" required maxlength="1000"></textarea>
                    </div>
                    <div class="ctc-form__footer">
                        <button type="submit" class="ctc-submit">पोस्ट करें</button>
                    </div>
                </div>
            </div>
            <?php wp_nonce_field( 'comment-post', '_wp_nonce' ); ?>
            <input type="hidden" name="comment_post_ID" value="<?php echo $post_id; ?>" />
            <input type="hidden" name="comment_parent" value="0" class="ctc-parent-id" />
            <?php if ( $logged_in ) : ?>
                <input type="hidden" name="author" value="<?php echo $user_name; ?>" />
                <input type="hidden" name="email"  value="<?php echo $user_email; ?>" />
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <!-- ── Comment list ── -->
        <div class="ctc-list">
            <?php if ( empty( $top ) ) : ?>
                <p class="ctc-empty">अभी तक कोई टिप्पणी नहीं। पहले आप लिखें!</p>
            <?php else : ?>
                <?php foreach ( $top as $c ) :
                    echo cotlas_render_comment( $c, 0 );
                    $cid = (int) $c->comment_ID;
                    if ( ! empty( $reply_groups[ $cid ] ) ) : ?>
                        <div class="ctc-replies">
                            <?php foreach ( $reply_groups[ $cid ] as $rc ) {
                                echo cotlas_render_comment( $rc, 1, $cid );
                            } ?>
                        </div>
                    <?php endif;
                endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- .cotlas-comments -->
    <?php
    return ob_get_clean();
}
add_shortcode( 'cotlas_comments', 'cotlas_comments_shortcode' );

/**
 * AJAX handler: edit own comment (logged-in user or matching guest cookie within 60 min).
 */
function cotlas_ajax_edit_comment() {
    $cid     = isset( $_POST['comment_id'] ) ? (int) $_POST['comment_id'] : 0;
    $content = isset( $_POST['content'] )    ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
    $nonce   = isset( $_POST['nonce'] )      ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    if ( ! $cid || ! $content || ! wp_verify_nonce( $nonce, 'ctc_edit_' . $cid ) ) {
        wp_send_json_error( 'Invalid request' );
    }

    $comment = get_comment( $cid );
    if ( ! $comment ) {
        wp_send_json_error( 'Comment not found' );
    }

    // Re-verify ownership server-side
    $can_edit = false;
    $cu = wp_get_current_user();
    if ( $cu->exists() && $cu->ID > 0 && (int) $comment->user_id === $cu->ID ) {
        $can_edit = true;
    } else {
        $ck_author = isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] )
            ? sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) : '';
        $ck_email  = isset( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] )
            ? sanitize_email( wp_unslash( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] ) ) : '';
        $posted    = (int) get_comment_date( 'U', $comment );
        if ( $ck_author !== '' && $ck_email !== ''
             && $ck_author === $comment->comment_author
             && $ck_email  === $comment->comment_author_email
             && ( time() - $posted ) < 3600 ) {
            $can_edit = true;
        }
    }

    if ( ! $can_edit ) {
        wp_send_json_error( 'Permission denied' );
    }

    wp_update_comment( array(
        'comment_ID'      => $cid,
        'comment_content' => $content,
    ) );

    wp_send_json_success( array(
        'html' => wpautop( esc_html( $content ) ),
        'raw'  => $content,
    ) );
}
add_action( 'wp_ajax_cotlas_edit_comment',        'cotlas_ajax_edit_comment' );
add_action( 'wp_ajax_nopriv_cotlas_edit_comment', 'cotlas_ajax_edit_comment' );

/**
 * Inline JS to handle Reply button — clones the main form into the reply slot
 * and sets comment_parent. No extra enqueue needed; runs after DOM is ready.
 */
add_action( 'wp_footer', function () { ?>
<script>
var ctcAjaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
(function(){
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.ctc-reply-btn');
        if (!btn) return;
        var cid   = btn.getAttribute('data-cid');
        var slot  = document.getElementById('ctc-reply-' + cid);
        if (!slot) return;
        // Toggle: if already open, close it
        if (slot.style.display !== 'none' && slot.innerHTML.trim()) {
            slot.style.display = 'none';
            slot.innerHTML = '';
            return;
        }
        // Clone the main form
        var widget = btn.closest('.cotlas-comments');
        var mainForm = widget ? widget.querySelector('.ctc-form') : null;
        if (!mainForm) return;
        var clone = mainForm.cloneNode(true);
        clone.querySelector('.ctc-parent-id').value = cid;
        clone.querySelector('.ctc-textarea').placeholder = 'जवाब लिखें…';
        clone.classList.add('ctc-form--reply');
        // Cancel button
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'ctc-cancel-reply';
        cancel.textContent = 'रद्द करें';
        cancel.addEventListener('click', function(){
            slot.style.display = 'none';
            slot.innerHTML = '';
        });
        clone.appendChild(cancel);
        slot.innerHTML = '';
        slot.appendChild(clone);
        slot.style.display = 'block';
        clone.querySelector('.ctc-textarea').focus();
        return;
    });

    // ── Inline edit ──────────────────────────────────────────────────
    document.addEventListener('click', function(e) {

        // Edit button
        var editBtn = e.target.closest('.ctc-edit-btn');
        if (editBtn) {
            var cid       = editBtn.getAttribute('data-cid');
            var nonce     = editBtn.getAttribute('data-nonce');
            var commentEl = document.getElementById('ctc-c-' + cid);
            if (!commentEl) return;
            var bubbleEl  = commentEl.querySelector('.ctc-comment__bubble');
            var textEl    = commentEl.querySelector('.ctc-comment__text');
            if (!textEl || bubbleEl.querySelector('.ctc-edit-wrap')) return;
            var raw = textEl.getAttribute('data-raw') || textEl.textContent.trim();
            var wrap = document.createElement('div');
            wrap.className = 'ctc-edit-wrap';
            wrap.innerHTML =
                '<textarea class="ctc-textarea ctc-edit-textarea" rows="3" maxlength="1000"></textarea>' +
                '<div class="ctc-edit-actions">' +
                  '<button type="button" class="ctc-submit ctc-edit-save" data-cid="' + cid + '" data-nonce="' + nonce + '">सहेजें</button>' +
                  '<button type="button" class="ctc-cancel-reply ctc-edit-cancel">रद्द करें</button>' +
                '</div>';
            wrap.querySelector('textarea').value = raw;
            textEl.style.display = 'none';
            bubbleEl.appendChild(wrap);
            wrap.querySelector('textarea').focus();
            editBtn.style.display = 'none';
            return;
        }

        // Cancel edit
        var cancelEdit = e.target.closest('.ctc-edit-cancel');
        if (cancelEdit) {
            var bubbleEl  = cancelEdit.closest('.ctc-comment__bubble');
            var commentEl = cancelEdit.closest('.ctc-comment');
            var wrap      = bubbleEl && bubbleEl.querySelector('.ctc-edit-wrap');
            var textEl    = bubbleEl && bubbleEl.querySelector('.ctc-comment__text');
            var editBtn   = commentEl && commentEl.querySelector('.ctc-edit-btn');
            if (wrap)    { bubbleEl.removeChild(wrap); }
            if (textEl)  { textEl.style.display = ''; }
            if (editBtn) { editBtn.style.display = ''; }
            return;
        }

        // Save edit
        var saveBtn = e.target.closest('.ctc-edit-save');
        if (saveBtn) {
            var cid   = saveBtn.getAttribute('data-cid');
            var nonce = saveBtn.getAttribute('data-nonce');
            var wrap  = saveBtn.closest('.ctc-edit-wrap');
            var ta    = wrap && wrap.querySelector('textarea');
            if (!ta) return;
            var newText = ta.value.trim();
            if (!newText) return;
            saveBtn.textContent = '…';
            saveBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'cotlas_edit_comment');
            fd.append('comment_id', cid);
            fd.append('content', newText);
            fd.append('nonce', nonce);
            fetch(ctcAjaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        saveBtn.textContent = 'सहेजें';
                        saveBtn.disabled = false;
                        alert('Error: ' + (data.data || 'unknown'));
                        return;
                    }
                    var bubbleEl  = wrap.closest('.ctc-comment__bubble');
                    var textEl    = bubbleEl && bubbleEl.querySelector('.ctc-comment__text');
                    var commentEl = wrap.closest('.ctc-comment');
                    var editBtn   = commentEl && commentEl.querySelector('.ctc-edit-btn');
                    if (textEl) {
                        textEl.innerHTML = data.data.html;
                        textEl.setAttribute('data-raw', data.data.raw);
                        textEl.style.display = '';
                    }
                    if (wrap && wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
                    if (editBtn) { editBtn.style.display = ''; }
                })
                .catch(function() {
                    saveBtn.textContent = 'सहेजें';
                    saveBtn.disabled = false;
                });
        }
    });
})();
</script>
<?php }, 20 );



// ============================================================
// GENERIC UTILITY FUNCTIONS
// (moved from cotlas-news/custom-mods.php)
// ============================================================

add_shortcode( 'gp_nav', 'tct_gp_nav' );
function tct_gp_nav( $atts ) {
    ob_start();
    generate_navigation_position();
    return ob_get_clean();
}

function gp_first_category_with_icon() {
    $categories = get_the_category();
    if ( ! empty( $categories ) ) {
        $cat     = $categories[0]; // First category
        $cat_url = esc_url( get_category_link( $cat->term_id ) );
        $cat_name = esc_html( $cat->name );

        return '<li class="gp-first-category">
                    <a href="' . $cat_url . '">
                        <span class="gp-first-category-icon">
                            <svg aria-hidden="true" class="gp-icon" viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0 512V48C0 21.49 21.49 0 48 0h288c26.51 0 48 21.49 48 48v464L192 400 0 512z"></path>
                            </svg>
                        </span>
                        <span class="gp-first-category-text">' . $cat_name . '</span>
                    </a>
                </li>';
    }
    return '';
}
add_shortcode( 'first_category', 'gp_first_category_with_icon' );

// Get the Yoast SEO primary category, with optional first-category fallback.
function gp_get_yoast_primary_category( $post_id = 0, $fallback = 'first' ) {
    $post_id = $post_id ? absint( $post_id ) : get_the_ID();

    if ( ! $post_id ) {
        return null;
    }

    if ( class_exists( 'WPSEO_Primary_Term' ) ) {
        $primary_term        = new WPSEO_Primary_Term( 'category', $post_id );
        $primary_category_id = absint( $primary_term->get_primary_term() );

        if ( $primary_category_id ) {
            $primary_category = get_category( $primary_category_id );

            if ( $primary_category && ! is_wp_error( $primary_category ) ) {
                return $primary_category;
            }
        }
    }

    if ( 'first' === $fallback ) {
        $categories = get_the_category( $post_id );

        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            return $categories[0];
        }
    }

    return null;
}

// Shortcode for Yoast SEO Primary Category
function gp_yoast_primary_category_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'show_link' => 'true',
        'class' => 'gp-post-category',
        'text_class' => 'gp-post-category-text',
        'fallback' => 'first' // 'first' to show first category, 'none' to show nothing
    ), $atts );

    $category = gp_get_yoast_primary_category( get_the_ID(), $atts['fallback'] );

    if ( $category ) {
        return gp_format_category_output( $category, $atts );
    }

    return '';
}

// Helper function to format category output
function gp_format_category_output( $category, $atts ) {
    $category_name = esc_html( $category->name );
    
    if ( $atts['show_link'] === 'true' ) {
        $category_link = esc_url( get_category_link( $category->term_id ) );
        $output = '<p class="' . esc_attr( $atts['class'] ) . '">
                    <a href="' . $category_link . '">
                        <span class="' . esc_attr( $atts['text_class'] ) . '">' . $category_name . '</span>
                    </a>
                  </p>';
    } else {
        $output = '<p class="' . esc_attr( $atts['class'] ) . '">
                    <span class="' . esc_attr( $atts['text_class'] ) . '">' . $category_name . '</span>
                  </p>';
    }
    
    return $output;
}

add_shortcode( 'yoast_primary_category', 'gp_yoast_primary_category_shortcode' );

// GenerateBlocks dynamic tag for one category inside query loops.
add_action( 'init', 'gp_register_yoast_primary_category_dynamic_tag', 20 );
function gp_register_yoast_primary_category_dynamic_tag() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag(
        array(
            'title'       => __( 'Yoast Primary Category', 'cotlas-news' ),
            'tag'         => 'yoast_primary_category',
            'type'        => 'post',
            'supports'    => array( 'source' ),
            'description' => __( 'Show the Yoast primary category, or the first category as a fallback.', 'cotlas-news' ),
            'options'     => array(
                'fallback' => array(
                    'type'    => 'select',
                    'label'   => __( 'Fallback', 'cotlas-news' ),
                    'default' => 'first',
                    'options' => array(
                        array(
                            'value' => 'first',
                            'label' => __( 'First category', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'none',
                            'label' => __( 'Nothing', 'cotlas-news' ),
                        ),
                    ),
                ),
                'output' => array(
                    'type'    => 'select',
                    'label'   => __( 'Output', 'cotlas-news' ),
                    'default' => 'name',
                    'options' => array(
                        array(
                            'value' => 'name',
                            'label' => __( 'Category name', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'linked_name',
                            'label' => __( 'Linked category name', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'url',
                            'label' => __( 'Category URL', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'slug',
                            'label' => __( 'Category slug', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'id',
                            'label' => __( 'Category ID', 'cotlas-news' ),
                        ),
                    ),
                ),
            ),
            'return'      => 'gp_yoast_primary_category_dynamic_tag',
        )
    );
}

function gp_yoast_primary_category_dynamic_tag( $options, $block, $instance ) {
    $post_id = class_exists( 'GenerateBlocks_Dynamic_Tags' )
        ? GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance )
        : get_the_ID();

    $fallback = isset( $options['fallback'] ) ? $options['fallback'] : 'first';
    $output   = isset( $options['output'] ) ? $options['output'] : 'name';
    $category = gp_get_yoast_primary_category( $post_id, $fallback );

    if ( ! $category ) {
        $value = '';
    } elseif ( 'url' === $output ) {
        $value = get_category_link( $category->term_id );
        $value = is_wp_error( $value ) ? '' : esc_url( $value );
    } elseif ( 'slug' === $output ) {
        $value = esc_html( $category->slug );
    } elseif ( 'id' === $output ) {
        $value = (string) absint( $category->term_id );
    } elseif ( 'linked_name' === $output ) {
        $url   = get_category_link( $category->term_id );
        $value = is_wp_error( $url )
            ? esc_html( $category->name )
            : sprintf(
                '<a href="%s" rel="category tag">%s</a>',
                esc_url( $url ),
                esc_html( $category->name )
            );
    } else {
        $value = esc_html( $category->name );
    }

    if ( class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $value, $options, $instance );
    }

    return $value;
}
// Add custom avatar upload field in user profile
add_action('show_user_profile', 'gp_add_custom_avatar_field');
add_action('edit_user_profile', 'gp_add_custom_avatar_field');

function gp_add_custom_avatar_field($user) {
    ?>
    <h3>Custom Avatar</h3>
    <table class="form-table">
        <tr>
            <th><label for="custom_avatar">Avatar</label></th>
            <td>
                <?php 
                $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
                if ($custom_avatar) {
                    echo wp_get_attachment_image($custom_avatar, [96, 96]); 
                } else {
                    echo get_avatar($user->ID, 96);
                }
                ?>
                <br />
                <input type="file" name="custom_avatar" id="custom_avatar" />
                <p class="description">Upload a custom avatar for this user.</p>
            </td>
        </tr>
    </table>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('form#your-profile, form#edituser').attr('enctype', 'multipart/form-data');
        });
    </script>
    <?php
}

// Save custom avatar
add_action('personal_options_update', 'gp_save_custom_avatar');
add_action('edit_user_profile_update', 'gp_save_custom_avatar');

function gp_save_custom_avatar($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    if (!empty($_FILES['custom_avatar']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('custom_avatar', 0);
        
        if (!is_wp_error($attachment_id)) {
            // delete old avatar if exists
            $old_avatar = get_user_meta($user_id, 'custom_avatar', true);
            if (!empty($old_avatar) && is_numeric($old_avatar)) {
                wp_delete_attachment($old_avatar);
            }
            update_user_meta($user_id, 'custom_avatar', $attachment_id);
        }
    }
}

// Override get_avatar to use custom avatar
add_filter('get_avatar', 'gp_custom_avatar', 10, 5);

function gp_custom_avatar($avatar, $id_or_email, $size, $default, $alt) {
    $user = false;
    
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int) $id_or_email);
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user = get_user_by('id', (int) $id_or_email->user_id);
    } elseif (is_object($id_or_email) && isset($id_or_email->comment_author_email)) {
        // Handle WP_Comment objects - extract email from comment
        $user = get_user_by('email', $id_or_email->comment_author_email);
    } else {
        $user = get_user_by('email', $id_or_email);
    }
    
    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            $custom_avatar_src = wp_get_attachment_image_src($custom_avatar, [$size, $size]);
            if ($custom_avatar_src) {
                $avatar = sprintf(
                    '<img src="%s" width="%d" height="%d" alt="%s" class="avatar avatar-%d photo" />',
                    esc_url($custom_avatar_src[0]),
                    esc_attr($size),
                    esc_attr($size),
                    esc_attr($alt),
                    esc_attr($size)
                );
            }
        }
    }
    
    return $avatar;
}

// Social Share Shortcode - FIXED VERSION
function cotlas_social_share_shortcode($atts) {
    $atts = shortcode_atts(array(
        'class'   => 'cotlas-social-share,cotlas-social-share-top,cotlas-social-share-aside,cotlas-social-share-footer',
        'networks' => 'facebook,twitter,linkedin,whatsapp,telegram,pinterest,reddit,threads,print',
        'size' => '24',
        'show_names' => 'false'
    ), $atts, 'social_share');

    $post_url   = urlencode(get_permalink());
    $post_title = urlencode(get_the_title());
    $post_image = has_post_thumbnail() ? wp_get_attachment_url(get_post_thumbnail_id()) : '';

    $networks = explode(',', $atts['networks']);
    $class    = esc_attr($atts['class']);
    $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
    $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    ?>
    <div class="<?php echo $class; ?>">
        <?php if (in_array('facebook', $networks)) : ?>
            <a href="https://www.facebook.com/sharer.php?u=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Share on Facebook (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-facebook" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z"></path></svg>
                <span class="screen-reader-text">Facebook</span>
                <?php if ($show_names): ?><span class="social-platform-name">Facebook</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('twitter', $networks)) : ?>
            <a href="https://x.com/share?text=<?php echo $post_title; ?>&url=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="twitter" aria-label="Share on Twitter (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-x-twitter" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path></svg>
                <span class="screen-reader-text">Twitter</span>
                <?php if ($show_names): ?><span class="social-platform-name">Twitter</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('whatsapp', $networks)) : ?>
            <a href="https://api.whatsapp.com/send?text=<?php echo $post_title; ?>%20<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="whatsapp" aria-label="Share on WhatsApp (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-whatsapp" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path></svg>
                <span class="screen-reader-text">WhatsApp</span>
                <?php if ($show_names): ?><span class="social-platform-name">WhatsApp</span><?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if (in_array('linkedin', $networks)) : ?>
            <a href="https://www.linkedin.com/shareArticle?url=<?php echo $post_url; ?>&title=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="linkedin" aria-label="Share on LinkedIn (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-linkedin" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path></svg>
                <span class="screen-reader-text">LinkedIn</span>
                <?php if ($show_names): ?><span class="social-platform-name">LinkedIn</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('pinterest', $networks)) : ?>
            <a href="https://pinterest.com/pin/create/button/?url=<?php echo $post_url; ?>&media=<?php echo $post_image; ?>&description=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="pinterest" aria-label="Share on Pinterest (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 21.5c-5.238 0-9.5-4.262-9.5-9.5S6.762 2.5 12 2.5s9.5 4.262 9.5 9.5-4.262 9.5-9.5 9.5z"/><path d="M12.5 7.5c-2.5 0-4.5 2-4.5 4.5 0 1.5 1 2.5 2 3 0 0 .5-2 .5-2.5 0-.5-.5-1-.5-1.5 0-1.5 1-2.5 2-2.5 1 0 1.5.5 1.5 1.5 0 1-1 3-1 4.5 0 1 .5 1.5 1.5 1.5 2 0 3-2.5 3-5 0-2-1.5-4-4-4z"/></svg>
                <span class="screen-reader-text">Pinterest</span>
                <?php if ($show_names): ?><span class="social-platform-name">Pinterest</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('reddit', $networks)) : ?>
            <a href="https://reddit.com/submit?url=<?php echo $post_url; ?>&title=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="reddit" aria-label="Share on Reddit (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>
                <span class="screen-reader-text">Reddit</span>
                <?php if ($show_names): ?><span class="social-platform-name">Reddit</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('threads', $networks)) : ?>
            <a href="<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="threads" aria-label="Share on Threads (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-threads" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M331.5 235.7c2.2 .9 4.2 1.9 6.3 2.8c29.2 14.1 50.6 35.2 61.8 61.4c15.7 36.5 17.2 95.8-30.3 143.2c-36.2 36.2-80.3 52.5-142.6 53h-.3c-70.2-.5-124.1-24.1-160.4-70.2c-32.3-41-48.9-98.1-49.5-169.6V256v-.2C17 184.3 33.6 127.2 65.9 86.2C102.2 40.1 156.2 16.5 226.4 16h.3c70.3 .5 124.9 24 162.3 69.9c18.4 22.7 32 50 40.6 81.7l-40.4 10.8c-7.1-25.8-17.8-47.8-32.2-65.4c-29.2-35.8-73-54.2-130.5-54.6c-57 .5-100.1 18.8-128.2 54.4C72.1 146.1 58.5 194.3 58 256c.5 61.7 14.1 109.9 40.3 143.3c28 35.6 71.2 53.9 128.2 54.4c51.4-.4 85.4-12.6 113.7-40.9c32.3-32.2 31.7-71.8 21.4-95.9c-6.1-14.2-17.1-26-31.9-34.9c-3.7 26.9-11.8 48.3-24.7 64.8c-17.1 21.8-41.4 33.6-72.7 35.3c-23.6 1.3-46.3-4.4-63.9-16c-20.8-13.8-33-34.8-34.3-59.3c-2.5-48.3 35.7-83 95.2-86.4c21.1-1.2 40.9-.3 59.2 2.8c-2.4-14.8-7.3-26.6-14.6-35.2c-10-11.7-25.6-17.7-46.2-17.8H227c-16.6 0-39 4.6-53.3 26.3l-34.4-23.6c19.2-29.1 50.3-45.1 87.8-45.1h.8c62.6 .4 99.9 39.5 103.7 107.7l-.2 .2zm-156 68.8c1.3 25.1 28.4 36.8 54.6 35.3c25.6-1.4 54.6-11.4 59.5-73.2c-13.2-2.9-27.8-4.4-43.4-4.4c-4.8 0-9.6 .1-14.4 .4c-42.9 2.4-57.2 23.2-56.2 41.8l-.1 .1z"/></svg>
                <span class="screen-reader-text">Threads</span>
                <?php if ($show_names): ?><span class="social-platform-name">Threads</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('telegram', $networks)) : ?>
            <a href="https://t.me/share/url?url=<?php echo $post_url; ?>&text=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="telegram" aria-label="Share on Telegram (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-telegram" viewBox="0 0 496 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm121.8 169.9l-40.7 191.8c-3 13.6-11.1 16.9-22.4 10.5l-62-45.7-29.9 28.8c-3.3 3.3-6.1 6.1-12.5 6.1l4.4-63.1 114.9-103.8c5-4.4-1.1-6.9-7.7-2.5l-142 89.4-61.2-19.1c-13.3-4.2-13.6-13.3 2.8-19.7l239.1-92.2c11.1-4 20.8 2.7 17.2 19.5z"/></svg>
                <span class="screen-reader-text">Telegram</span>
                <?php if ($show_names): ?><span class="social-platform-name">Telegram</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('print', $networks)) : ?>
            <a href="javascript:window.print()" class="print" aria-label="Print this page">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                <span class="screen-reader-text">Print</span>
                <?php if ($show_names): ?><span class="social-platform-name">Print</span><?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('social_share', 'cotlas_social_share_shortcode');
// Remove the default GeneratePress comments template
function remove_generatepress_default_comments() {
    remove_action( 'generate_after_do_template_part', 'generate_do_comments_template', 15 );
}
add_action( 'wp_loaded', 'remove_generatepress_default_comments' );


// Author Social Links Shortcode - FIXED VERSION
function cotlas_author_social_links_shortcode($atts) {
    $atts = shortcode_atts(array(
        'class' => 'cotlas-author-social-links',
        'size' => '24',
        'show_names' => 'false',
        'networks' => ''
    ), $atts, 'author_social_links');
    
    $author_id = get_the_author_meta('ID');
    if (!$author_id) return '';
    
    // Get social media URLs from user meta
    $social_links = array(
        'facebook' => get_the_author_meta('facebook', $author_id),
        'twitter' => get_the_author_meta('twitter', $author_id),
        'instagram' => get_the_author_meta('instagram', $author_id),
        'linkedin' => get_the_author_meta('linkedin', $author_id),
        'youtube' => get_the_author_meta('youtube', $author_id),
        'pinterest' => get_the_author_meta('pinterest', $author_id),
        'email' => get_the_author_meta('user_email', $author_id),
    );
    
    // Fix Twitter URL
    if (!empty($social_links['twitter'])) {
        $twitter_url = $social_links['twitter'];
        
        if (strpos($twitter_url, 'twitter.com') !== false) {
            $twitter_url = str_replace('twitter.com', 'x.com', $twitter_url);
        }
        elseif (!preg_match('/^https?:\/\//', $twitter_url)) {
            $twitter_url = 'https://x.com/' . ltrim($twitter_url, '@');
        }
        
        $social_links['twitter'] = $twitter_url;
    }
    
    $class = esc_attr($atts['class']);
    $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
    $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);
    $allowed = array_keys($social_links);
    if (!empty($atts['networks'])) {
        $requested = array_filter(array_map('trim', explode(',', strtolower($atts['networks']))));
        $normalized = array_map(function($n) { return $n === 'x' ? 'twitter' : $n; }, $requested);
        $allowed = $normalized;
    }
    
    ob_start();
    ?>
    <div class="<?php echo $class; ?>">
        <?php if (!empty($social_links['facebook']) && in_array('facebook', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['facebook']); ?>" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Visit author's Facebook profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span class="screen-reader-text">Facebook</span>
                <?php if ($show_names): ?><span class="social-platform-name">Facebook</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['twitter']) && in_array('twitter', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['twitter']); ?>" target="_blank" rel="noopener noreferrer" class="twitter" aria-label="Visit author's Twitter profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                </svg>
                <span class="screen-reader-text">Twitter</span>
                <?php if ($show_names): ?><span class="social-platform-name">Twitter</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['instagram']) && in_array('instagram', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['instagram']); ?>" target="_blank" rel="noopener noreferrer" class="instagram" aria-label="Visit author's Instagram profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <span class="screen-reader-text">Instagram</span>
                <?php if ($show_names): ?><span class="social-platform-name">Instagram</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['linkedin']) && in_array('linkedin', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="linkedin" aria-label="Visit author's LinkedIn profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
                <span class="screen-reader-text">LinkedIn</span>
                <?php if ($show_names): ?><span class="social-platform-name">LinkedIn</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['youtube']) && in_array('youtube', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['youtube']); ?>" target="_blank" rel="noopener noreferrer" class="youtube" aria-label="Visit author's YouTube channel (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
                <span class="screen-reader-text">YouTube</span>
                <?php if ($show_names): ?><span class="social-platform-name">YouTube</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['email']) && in_array('email', $allowed, true)) : ?>
            <a href="mailto:<?php echo esc_attr($social_links['email']); ?>" class="email" aria-label="Email the author">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 12.713l-11.985-9.713h23.97l-11.985 9.713zm0 2.574l-12-9.725v15.438h24v-15.438l-12 9.725z"/>
                </svg>
                <span class="screen-reader-text">Email</span>
                <?php if ($show_names): ?><span class="social-platform-name">Email</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['pinterest']) && in_array('pinterest', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['pinterest']); ?>" target="_blank" rel="noopener noreferrer" class="pinterest" aria-label="Visit author's Pinterest profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 21.5c-5.238 0-9.5-4.262-9.5-9.5S6.762 2.5 12 2.5s9.5 4.262 9.5 9.5-4.262 9.5-9.5 9.5z"/>
                    <path d="M12.5 7.5c-2.5 0-4.5 2-4.5 4.5 0 1.5 1 2.5 2 3 0 0 .5-2 .5-2.5 0-.5-.5-1-.5-1.5 0-1.5 1-2.5 2-2.5 1 0 1.5.5 1.5 1.5 0 1-1 3-1 4.5 0 1 .5 1.5 1.5 1.5 2 0 3-2.5 3-5 0-2-1.5-4-4-4z"/>
                </svg>
                <span class="screen-reader-text">Pinterest</span>
                <?php if ($show_names): ?><span class="social-platform-name">Pinterest</span><?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('author_social_links', 'cotlas_author_social_links_shortcode');


/**
 * Enable Audio Post Format in GeneratePress Child Theme
 */
function gp_child_add_audio_post_format() {

    // Get existing post formats supported by the theme
    $existing_formats = get_theme_support('post-formats');

    // Define formats if theme already supports them
    if (is_array($existing_formats) && isset($existing_formats[0]) && is_array($existing_formats[0])) {
        $formats = $existing_formats[0];
    } else {
        $formats = array();
    }

    // Add 'audio' if it's not already present
    if (!in_array('audio', $formats, true)) {
        $formats[] = 'audio';
    }

    // Re-register support for post formats including audio
    add_theme_support('post-formats', $formats);
}
add_action('after_setup_theme', 'gp_child_add_audio_post_format', 11);

// Add YouTube Video URL meta box with Auto-Fetch capability
function gp_add_youtube_video_meta_box() {
    add_meta_box(
        'youtube_video_url',
        'YouTube Video Settings',
        'gp_youtube_video_url_callback',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'gp_add_youtube_video_meta_box');

// Meta box callback function - UPDATED with Auto-Fetch
function gp_youtube_video_url_callback($post) {
    // Add nonce for security
    wp_nonce_field('gp_youtube_video_nonce', 'gp_youtube_video_nonce');
    
    // Get existing values
    $youtube_url = get_post_meta($post->ID, '_youtube_video_url', true);
    $video_id = get_post_meta($post->ID, '_youtube_video_id', true);
    $video_summary = get_post_meta($post->ID, '_youtube_video_summary', true);
    $video_transcript = get_post_meta($post->ID, '_youtube_video_transcript', true);
    $has_transcript = get_post_meta($post->ID, '_youtube_has_transcript', true);
    $auto_fetch_data = get_post_meta($post->ID, '_youtube_auto_fetch', true);
    
    ?>
    <div style="margin-bottom: 15px;">
        <label for="youtube_video_url" style="display: block; margin-bottom: 5px; font-weight: bold;">YouTube Video URL:</label>
        <input type="url" id="youtube_video_url" name="youtube_video_url" value="<?php echo esc_attr($youtube_url); ?>" style="width:100%; margin-bottom: 10px;" placeholder="https://www.youtube.com/watch?v=..." />
        
        <div style="margin-bottom: 10px;">
            <input type="checkbox" id="youtube_auto_fetch" name="youtube_auto_fetch" value="1" <?php checked($auto_fetch_data, '1'); ?> />
            <label for="youtube_auto_fetch" style="font-size: 13px;">Auto-fetch video data</label>
        </div>
        
        <p class="description" style="margin: 5px 0 15px 0; font-size: 12px; color: #666;">Enter YouTube URL and check auto-fetch to get automatic summary</p>
    </div>

    <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Video Transcript Settings:</label>
        
        <div style="margin-bottom: 10px;">
            <input type="checkbox" id="youtube_has_transcript" name="youtube_has_transcript" value="1" <?php checked($has_transcript, '1'); ?> />
            <label for="youtube_has_transcript" style="font-size: 13px;">Include video transcript for accessibility</label>
        </div>
        
        <div style="margin-bottom: 10px;">
            <label for="youtube_video_summary" style="display: block; margin-bottom: 3px; font-size: 13px;">Video Summary:</label>
            <textarea id="youtube_video_summary" name="youtube_video_summary" rows="3" style="width:100%; font-size: 13px;" placeholder="Brief summary of video content..."><?php echo esc_textarea($video_summary); ?></textarea>
            <p class="description" style="margin: 3px 0 0 0; font-size: 11px; color: #666;">Auto-filled if auto-fetch is enabled</p>
        </div>
        
        <div>
            <label for="youtube_video_transcript" style="display: block; margin-bottom: 3px; font-size: 13px;">Full Transcript:</label>
            <textarea id="youtube_video_transcript" name="youtube_video_transcript" rows="6" style="width:100%; font-size: 13px;" placeholder="Full video transcript or detailed summary..."><?php echo esc_textarea($video_transcript); ?></textarea>
            <p class="description" style="margin: 3px 0 0 0; font-size: 11px; color: #666;">Auto-filled with YouTube captions if available</p>
        </div>
    </div>

    <?php
    // Show preview if video ID exists
    if ($video_id) {
        echo '<div style="margin-top:15px; padding:10px; background:#f9f9f9; border-radius:4px;">';
        echo '<p style="margin:0 0 8px 0; font-weight:bold;">Preview:</p>';
        echo '<div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">';
        echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>';
        echo '</div>';
        
        // Show auto-fetch status
        if ($auto_fetch_data) {
            echo '<p style="margin:8px 0 0 0; font-size:12px; color:green;">✓ Auto-fetch enabled</p>';
        }
        echo '</div>';
    }
}

// Auto-fetch YouTube data function
function gp_fetch_youtube_data($video_id, $post_id) {
    // Basic video info from oEmbed
    $video_info = wp_oembed_get('https://www.youtube.com/watch?v=' . $video_id);
    
    // Get video title and description via YouTube iframe API simulation
    $api_url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=" . $video_id . "&format=json";
    
    $response = wp_remote_get($api_url);
    
    if (!is_wp_error($response) && $response['response']['code'] == 200) {
        $data = json_decode($response['body'], true);
        
        if ($data && isset($data['title'])) {
            // Use video title and description as base summary
            $auto_summary = "This video titled '" . $data['title'] . "' discusses topics related to diabetes management and health tips.";
            
            // If we have a description, use it
            if (isset($data['author_name'])) {
                $auto_summary .= " Presented by " . $data['author_name'] . ".";
            }
            
            // Save auto-generated summary
            update_post_meta($post_id, '_youtube_video_summary', $auto_summary);
            
            // Set up transcript placeholder
            $transcript_placeholder = "Full transcript available on YouTube. Click the video to enable closed captions, or visit the video on YouTube for complete accessibility features.";
            update_post_meta($post_id, '_youtube_video_transcript', $transcript_placeholder);
            
            return true;
        }
    }
    
    return false;
}

// Enhanced save function with auto-fetch
function gp_save_youtube_video_meta($post_id) {
    // Check nonce
    if (!isset($_POST['gp_youtube_video_nonce']) || !wp_verify_nonce($_POST['gp_youtube_video_nonce'], 'gp_youtube_video_nonce')) {
        return;
    }
    
    // Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save/update the YouTube URL and extract ID
    if (isset($_POST['youtube_video_url'])) {
        $youtube_url = sanitize_text_field($_POST['youtube_video_url']);
        $video_id = gp_extract_youtube_id($youtube_url);
        $auto_fetch = isset($_POST['youtube_auto_fetch']) ? '1' : '0';
        
        // Check if URL changed
        $old_url = get_post_meta($post_id, '_youtube_video_url', true);
        $url_changed = ($youtube_url !== $old_url);
        
        update_post_meta($post_id, '_youtube_video_url', $youtube_url);
        update_post_meta($post_id, '_youtube_video_id', $video_id);
        update_post_meta($post_id, '_youtube_auto_fetch', $auto_fetch);
        
        // Auto-fetch data if enabled and URL changed or first time
        if ($auto_fetch && $video_id && $url_changed) {
            gp_fetch_youtube_data($video_id, $post_id);
            
            // Auto-enable transcript when auto-fetch is used
            update_post_meta($post_id, '_youtube_has_transcript', '1');
        }
        
        // Save manual transcript data (will override auto-fetch if user entered something)
        $has_transcript = isset($_POST['youtube_has_transcript']) ? '1' : '0';
        $video_summary = isset($_POST['youtube_video_summary']) ? sanitize_textarea_field($_POST['youtube_video_summary']) : '';
        $video_transcript = isset($_POST['youtube_video_transcript']) ? sanitize_textarea_field($_POST['youtube_video_transcript']) : '';
        
        update_post_meta($post_id, '_youtube_has_transcript', $has_transcript);
        
        // Only update if user manually entered data (not empty)
        if (!empty($video_summary)) {
            update_post_meta($post_id, '_youtube_video_summary', $video_summary);
        }
        
        if (!empty($video_transcript)) {
            update_post_meta($post_id, '_youtube_video_transcript', $video_transcript);
        }
        
        // Auto-set post format to "video" if URL is not empty
        if (!empty($youtube_url)) {
            set_post_format($post_id, 'video');
        } else {
            if (get_post_format($post_id) === 'video') {
                set_post_format($post_id, false);
            }
        }
    }
}
add_action('save_post', 'gp_save_youtube_video_meta');

// Extract YouTube video ID from URL
function gp_extract_youtube_id($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    preg_match($pattern, $url, $matches);
    return isset($matches[1]) ? $matches[1] : '';
}

// Enhanced video display with smart transcript handling
function gp_move_video_to_featured_image() {
    if (is_single()) {
        $video_id = get_post_meta(get_the_ID(), '_youtube_video_id', true);
        $video_url = get_post_meta(get_the_ID(), '_youtube_video_url', true);
        $has_transcript = get_post_meta(get_the_ID(), '_youtube_has_transcript', true);
        $video_summary = get_post_meta(get_the_ID(), '_youtube_video_summary', true);
        $video_transcript = get_post_meta(get_the_ID(), '_youtube_video_transcript', true);
        $auto_fetch = get_post_meta(get_the_ID(), '_youtube_auto_fetch', true);
        
        if ($video_id || $video_url) {
            $final_video_id = $video_id ?: gp_extract_youtube_id($video_url);
            
            if ($final_video_id) {
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Prepare transcript HTML
                    var transcriptHTML = '';
                    <?php if ($has_transcript) : ?>
                        transcriptHTML = `
                        <div class="video-transcript" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #2271b1;">
                            <h3 style="margin-top: 0; color: #2271b1; font-size: 18px;">Video Transcript</h3>
                            <div class="transcript-content">
                                <?php if ($video_summary) : ?>
                                    <p><strong>Summary:</strong> <?php echo esc_js($video_summary); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($video_transcript && !strpos($video_transcript, 'Full transcript available on YouTube')) : ?>
                                    <details style="margin-top: 15px;">
                                        <summary style="cursor: pointer; font-weight: bold; color: #2271b1; background: none; border: none; padding: 0;">Show Full Transcript</summary>
                                        <div style="margin-top: 10px; padding: 15px; background: white; border-radius: 4px; border: 1px solid #e1e1e1; font-size: 14px; line-height: 1.5;">
                                            <?php echo wp_kses_post(wpautop($video_transcript)); ?>
                                        </div>
                                    </details>
                                <?php else : ?>
                                    <p><strong>Full transcript:</strong> Available on YouTube with closed captions. <a href="https://www.youtube.com/watch?v=<?php echo esc_js($final_video_id); ?>" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: underline;">View on YouTube</a> to access complete transcript and accessibility features.</p>
                                <?php endif; ?>
                                
                                <?php if ($auto_fetch) : ?>
                                    <p style="margin-top: 10px; font-size: 12px; color: #666; font-style: italic;">✓ Video data automatically fetched from YouTube</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        `;
                    <?php endif; ?>

                    // Create video HTML
                    var videoHTML = `
                        <div class="featured-video-replacement">
                            <div class="video-container" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:8px; margin-bottom:2em;">
                                <div class="video-autoplay-notice" style="position:absolute; bottom:60px; left:10px; background:rgba(0,0,0,0.7); color:white; padding:4px 8px; border-radius:4px; font-size:12px; z-index:10;">
                                    Autoplay (muted)
                                </div>    
                                <iframe src="https://www.youtube.com/embed/<?php echo esc_js($final_video_id); ?>?rel=0&amp;showinfo=0&amp;modestbranding=1&amp;autoplay=1&amp;mute=1&amp;playsinline=1" 
                                        style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen 
                                        loading="lazy"
                                        title="<?php echo esc_js(get_the_title()); ?>">
                                </iframe>
                            </div>
                            ${transcriptHTML}
                        </div>
                    `;
                    
                    // Target the featured image container
                    var featuredContainer = document.querySelector('.gb-element-dab3bde9');
                    if (featuredContainer) {
                        featuredContainer.innerHTML = videoHTML;
                        featuredContainer.classList.add('video-loaded');
                        
                        var iframe = featuredContainer.querySelector('iframe');
                        if (iframe) {
                            iframe.addEventListener('click', function() {
                                iframe.src = iframe.src.replace('&mute=1', '&mute=0');
                            });
                        }
                    }
                    
                    // Remove video marker from content
                    var contentArea = document.querySelector('.gb-element-850a602b');
                    if (contentArea) {
                        contentArea.innerHTML = contentArea.innerHTML.replace('<!-- VIDEO_MARKER -->', '');
                    }
                });
                </script>
                <style>
                .gb-media-dc46dcac { display: none !important; }
                .featured-video-replacement { width: 100%; }
                .video-container { 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    transition: transform 0.3s ease;
                    cursor: pointer;
                }
                .video-container:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                }
                </style>
                <?php
            }
        }
    }
}
add_action('wp_footer', 'gp_move_video_to_featured_image');

// Keep existing content filter
function gp_video_post_complete_solution($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $video_id = get_post_meta(get_the_ID(), '_youtube_video_url', true);
        $video_id_clean = get_post_meta(get_the_ID(), '_youtube_video_id', true);
        
        if ($video_id_clean || $video_id) {
            return '<!-- VIDEO_MARKER -->' . $content;
        }
    }
    return $content;
}
add_filter('the_content', 'gp_video_post_complete_solution', 5);
/* ============================================================
 *  AUDIO META BOX + MEDIA UPLOAD - FIXED VERSION
 * ============================================================ */

// 1. Add Audio URL Meta Box
function gp_add_audio_file_meta_box() {
    add_meta_box(
        'audio_file_url',
        'Audio File',
        'gp_audio_file_url_callback',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'gp_add_audio_file_meta_box');

// 2. Callback for Audio Field + Media Upload - FIXED VERSION
function gp_audio_file_url_callback($post) {
    wp_nonce_field('gp_audio_file_nonce', 'gp_audio_file_nonce');
    $audio_url = get_post_meta($post->ID, '_audio_file_url', true);
    $audio_attachment_id = get_post_meta($post->ID, '_audio_attachment_id', true);

    // Generate unique IDs to avoid conflicts
    $input_id = 'gp_audio_file_url_' . $post->ID;
    $attachment_id = 'gp_audio_attachment_id_' . $post->ID;

    echo '<div style="background: #f0f0f1; padding: 10px; margin-bottom: 10px; border-left: 4px solid #2271b1;">';
    echo '<strong>Instructions:</strong> Upload/select audio or paste URL manually. The audio player will appear above the featured image.';
    echo '</div>';
    
    // Fixed label with correct for attribute
    echo '<label for="' . esc_attr($input_id) . '">Audio File URL:</label>';
    echo '<input type="url" id="' . esc_attr($input_id) . '" name="audio_file_url" value="' . esc_attr($audio_url) . '" style="width:100%; margin-top:5px;" placeholder="https://example.com/audio.mp3" />';
    echo '<input type="hidden" id="' . esc_attr($attachment_id) . '" name="audio_attachment_id" value="' . esc_attr($audio_attachment_id) . '" />';
    
    echo '<div style="margin-top: 10px;">';
    echo '<button type="button" class="button button-secondary" id="upload_audio_button_' . $post->ID . '">Select Audio from Media Library</button>';
    echo '<button type="button" class="button button-link-delete" id="remove_audio_button_' . $post->ID . '" style="margin-left:5px; color:#a00; display: ' . ($audio_url ? 'inline-block' : 'none') . ';">Remove Audio</button>';
    echo '</div>';
    
    echo '<p class="description">Enter audio URL or select from media library.</p>';

    // Audio preview container
    echo '<div id="audio_preview_container_' . $post->ID . '" style="margin-top:10px; padding:10px; background:#f9f9f9; border-radius:4px; display: ' . ($audio_url ? 'block' : 'none') . ';">';
    if ($audio_url) {
        echo '<p><strong>Preview:</strong></p>';
        echo '<audio controls style="width:100%;"><source src="' . esc_url($audio_url) . '"></audio>';
    }
    echo '</div>';

    // Localize script for audio upload with post-specific IDs
    wp_localize_script('gp-audio-upload', 'gpAudioOptions', array(
        'post_id' => $post->ID,
        'l10n' => array(
            'select' => 'Select Audio',
            'change' => 'Change Audio',
            'featuredAudio' => 'Featured Audio'
        ),
        'initialAudioAttachment' => $audio_attachment_id ? array(
            'id' => $audio_attachment_id,
            'url' => $audio_url,
            'title' => get_the_title($audio_attachment_id)
        ) : false
    ));
}

// 3. Save Audio Field
function gp_save_audio_file_meta($post_id) {
    // Check if nonce is set and valid
    if (!isset($_POST['gp_audio_file_nonce']) || !wp_verify_nonce($_POST['gp_audio_file_nonce'], 'gp_audio_file_nonce')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Handle audio URL
    if (isset($_POST['audio_file_url'])) {
        $audio_url = sanitize_text_field($_POST['audio_file_url']);
        
        // Update or delete post meta based on whether URL is empty
        if (!empty($audio_url)) {
            update_post_meta($post_id, '_audio_file_url', $audio_url);
            
            // Auto-set post format to "audio" if URL is not empty
            set_post_format($post_id, 'audio');
        } else {
            // If URL is empty, delete the meta and remove audio format
            delete_post_meta($post_id, '_audio_file_url');
            delete_post_meta($post_id, '_audio_attachment_id');
            
            // Only remove audio format if this post currently has it
            if (get_post_format($post_id) === 'audio') {
                set_post_format($post_id, false);
            }
        }
    }

    // Handle audio attachment ID
    if (isset($_POST['audio_attachment_id'])) {
        $audio_attachment_id = absint($_POST['audio_attachment_id']);
        if ($audio_attachment_id > 0) {
            update_post_meta($post_id, '_audio_attachment_id', $audio_attachment_id);
        } else {
            delete_post_meta($post_id, '_audio_attachment_id');
        }
    }
}
add_action('save_post', 'gp_save_audio_file_meta');



// 5. Fallback inline script if custom.js doesn't exist
function gp_add_audio_upload_script_inline() {
    ?>
    <script type="text/javascript">
    /**
     * Audio Upload Script - Inline version
     */
    var gpFeaturedAudio = {};
    (function($) {
        gpFeaturedAudio = {
            container: '',
            frame: '',
            settings: window.gpAudioOptions || {},

            init: function() {
                gpFeaturedAudio.container = $('#audio_file_url').closest('.inside');
                gpFeaturedAudio.initFrame();

                // Bind events
                $('#upload_audio_button').on('click', gpFeaturedAudio.openAudioFrame);
                $('#remove_audio_button').on('click', gpFeaturedAudio.removeAudio);
                
                // Handle manual URL input
                $('#audio_file_url').on('input change', function() {
                    var url = $(this).val().trim();
                    gpFeaturedAudio.updatePreview(url);
                });

                gpFeaturedAudio.initAudioPreview();
            },

            /**
             * Open the featured audio media modal.
             */
            openAudioFrame: function(event) {
                event.preventDefault();
                if (!gpFeaturedAudio.frame) {
                    gpFeaturedAudio.initFrame();
                }
                gpFeaturedAudio.frame.open();
            },

            /**
             * Create a media modal select frame, and store it so the instance can be reused when needed.
             */
            initFrame: function() {
                gpFeaturedAudio.frame = wp.media({
                    title: gpFeaturedAudio.settings.l10n ? gpFeaturedAudio.settings.l10n.featuredAudio : 'Featured Audio',
                    button: {
                        text: gpFeaturedAudio.settings.l10n ? gpFeaturedAudio.settings.l10n.select : 'Select Audio'
                    },
                    library: {
                        type: 'audio'
                    },
                    multiple: false
                });

                // When a file is selected, run a callback.
                gpFeaturedAudio.frame.on('select', gpFeaturedAudio.selectAudio);
            },

            /**
             * Callback handler for when an attachment is selected in the media modal.
             * Gets the selected attachment information, and sets it within the control.
             */
            selectAudio: function() {
                // Get the attachment from the modal frame.
                var attachment = gpFeaturedAudio.frame.state().get('selection').first().toJSON();
                
                // Set the URL in the input field - THIS IS THE FIX
                $('#audio_file_url').val(attachment.url);
                $('#audio_attachment_id').val(attachment.id);
                
                // Update preview
                gpFeaturedAudio.updatePreview(attachment.url);
                
                // Show remove button
                $('#remove_audio_button').show();
                if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.change) {
                    $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.change);
                } else {
                    $('#upload_audio_button').text('Change Audio');
                }
            },

            /**
             * Update audio preview
             */
            updatePreview: function(url) {
                var previewContainer = $('#audio_preview_container');
                
                if (url && gpFeaturedAudio.isValidAudioUrl(url)) {
                    var previewHtml = '<p><strong>Preview:</strong></p>' +
                                     '<audio controls style="width:100%;">' +
                                     '<source src="' + url + '">' +
                                     'Your browser does not support the audio element.' +
                                     '</audio>';
                    
                    previewContainer.html(previewHtml).show();
                    
                    // Show remove button if not already shown
                    if ($('#remove_audio_button').is(':hidden')) {
                        $('#remove_audio_button').show();
                    }
                } else {
                    previewContainer.hide().html('');
                    if (!url) {
                        $('#remove_audio_button').hide();
                        if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.select) {
                            $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.select);
                        } else {
                            $('#upload_audio_button').text('Select Audio from Media Library');
                        }
                    }
                }
            },

            /**
             * Validate audio URL
             */
            isValidAudioUrl: function(url) {
                if (!url) return true;
                var audioExtensions = [".mp3", ".wav", ".ogg", ".m4a", ".aac", ".flac", ".webm"];
                return audioExtensions.some(function(ext) {
                    return url.toLowerCase().endsWith(ext);
                });
            },

            /**
             * Remove the selected audio.
             */
            removeAudio: function() {
                $('#audio_file_url').val('');
                $('#audio_attachment_id').val('');
                $('#audio_preview_container').hide().html('');
                $('#remove_audio_button').hide();
                if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.select) {
                    $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.select);
                } else {
                    $('#upload_audio_button').text('Select Audio from Media Library');
                }
            },

            /**
             * Initialize featured audio preview.
             */
            initAudioPreview: function() {
                var initialAttachment = gpFeaturedAudio.settings.initialAudioAttachment;
                if (initialAttachment && initialAttachment.url) {
                    if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.change) {
                        $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.change);
                    } else {
                        $('#upload_audio_button').text('Change Audio');
                    }
                    $('#remove_audio_button').show();
                }
            }
        };

        $(document).ready(function() {
            gpFeaturedAudio.init();
        });

    })(jQuery);
    </script>
    <?php
}

// 6. Register for Gutenberg support
function gp_add_audio_to_block_editor() {
    register_meta('post', '_audio_file_url', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_meta('post', '_audio_attachment_id', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'gp_add_audio_to_block_editor');


/* ============================================================
 *  AUDIO PLAYER SHORTCODE
 * ============================================================ */

// Audio Player Shortcode with Nice Design
function gp_audio_player_shortcode($atts) {
    // Get the current post ID
    $post_id = get_the_ID();
    
    // Check if it's an audio post format
    if (get_post_format($post_id) !== 'audio') {
        return ''; // Return empty if not audio format
    }
    
    // Get audio URL from post meta
    $audio_url = get_post_meta($post_id, '_audio_file_url', true);
    
    // Return empty if no audio URL
    if (empty($audio_url)) {
        return '';
    }
    
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'style' => 'modern', // modern, minimal, dark
        'width' => '100%',
        'height' => '50px',
        'autoplay' => 'no',
        'loop' => 'no'
    ), $atts);
    
    // Sanitize attributes
    $style = sanitize_text_field($atts['style']);
    $width = esc_attr($atts['width']);
    $height = esc_attr($atts['height']);
    $autoplay = $atts['autoplay'] === 'yes' ? 'autoplay' : '';
    $loop = $atts['loop'] === 'yes' ? 'loop' : '';
    
    // Get file name for display
    $file_name = basename($audio_url);
    
    // Generate unique ID for this audio player
    $audio_id = 'gp-audio-' . uniqid();
    
    // Different styles
    $styles = array(
        'modern' => '
            <div class="gp-audio-player gp-audio-modern" style="max-width: ' . $width . '; margin: 20px 0;">
                <div class="gp-audio-header" style="background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%); border: 1px solid #e5e5e5; padding: 15px; border-radius: 10px 10px 0 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <svg style="width: 20px; height: 20px; margin-right: 10px; fill: #06940c;" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                            <span style="color: #06940c; font-weight: 600;">Listen to this Post</span>
                        </div>
                        <span style="color: rgba(6, 110, 21, 0.8); font-size: 12px;">' . $file_name . '</span>
                    </div>
                </div>
                <div class="gp-audio-body" style="background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; border-top: none;">
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 6px;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        ',
        
        'minimal' => '
            <div class="gp-audio-player gp-audio-minimal" style="max-width: ' . $width . '; margin: 20px 0;">
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e1e5e9; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <svg style="width: 16px; height: 16px; margin-right: 8px; fill: #6c757d;" viewBox="0 0 24 24">
                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                        </svg>
                        <span style="color: #495057; font-size: 14px; font-weight: 500;">' . $file_name . '</span>
                    </div>
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 4px;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        ',
        
        'dark' => '
            <div class="gp-audio-player gp-audio-dark" style="max-width: ' . $width . '; margin: 20px 0;">
                <div style="background: #2d3748; padding: 20px; border-radius: 12px; border: 1px solid #4a5568;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center;">
                            <svg style="width: 20px; height: 20px; margin-right: 12px; fill: #63b3ed;" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                            <span style="color: #e2e8f0; font-weight: 600; font-size: 16px;">Audio Player</span>
                        </div>
                        <span style="color: #a0aec0; font-size: 12px;">' . $file_name . '</span>
                    </div>
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 6px; background: #1a202c;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        '
    );
    
    // Add JavaScript to remove download button
    add_action('wp_footer', function() use ($audio_id) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var audio = document.getElementById('<?php echo $audio_id; ?>');
            if (audio) {
                // Remove download button using controlsList
                audio.controlsList = 'nodownload';
                
                // Additional method: Hide download button via CSS
                var style = document.createElement('style');
                style.textContent = 'audio::-webkit-media-controls-download-button { display: none !important; }';
                document.head.appendChild(style);
                
                // For Firefox and other browsers, remove context menu download option
                audio.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
        });
        </script>
        <?php
    });
    
    // Return the selected style or modern as default
    return isset($styles[$style]) ? $styles[$style] : $styles['modern'];
}
add_shortcode('audio_player', 'gp_audio_player_shortcode');

// Add CSS for better audio player styling and hide download buttons
function gp_audio_player_styles() {
    if (is_single() && get_post_format() === 'audio') {
        ?>
        <style>
            .gp-audio-player audio {
                outline: none;
                transition: all 0.3s ease;
            }
            
            .gp-audio-player audio:hover {
                opacity: 0.9;
            }
            
            .gp-audio-player audio::-webkit-media-controls-panel {
                background-color: #f8f9fa;
            }
            
            .gp-audio-player audio::-webkit-media-controls-play-button {
                background-color: #667eea;
                border-radius: 50%;
            }
            
            .gp-audio-player audio::-webkit-media-controls-current-time-display,
            .gp-audio-player audio::-webkit-media-controls-time-remaining-display {
                color: #495057;
                font-weight: 500;
            }
            
            /* Remove download button in WebKit browsers */
            .gp-audio-player audio::-webkit-media-controls-download-button {
                display: none !important;
            }
            
            /* Remove download button in other browsers */
            .gp-audio-player audio {
                -webkit-media-controls-download-button: none !important;
                media-controls-download-button: none !important;
            }
            
            /* Dark mode audio player styles */
            .gp-audio-dark audio::-webkit-media-controls-panel {
                background-color: #4a5568;
            }
            
            .gp-audio-dark audio::-webkit-media-controls-current-time-display,
            .gp-audio-dark audio::-webkit-media-controls-time-remaining-display {
                color: #e2e8f0;
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'gp_audio_player_styles');
/* ============================================================
 *  CUSTOM SEARCH SHORTCODE + SLIDEOUT FIX
 * ============================================================ */
// Custom Search Shortcode with Translation Support

function cotlas_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'placeholder' => __('Type to search...', 'textdomain'),
        'button_label' => __('Submit search', 'textdomain'),
        'input_label' => __('Search our website', 'textdomain'),
        'post_types'  => 'post,page', // Default to post and page
    ), $atts);
    
    // Generate ONE unique ID and use it for both label and input
    $unique_id = 'cotlas-search-input-' . uniqid();
    
    ob_start();
    ?>
    <div class="cotlas-search">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="wp-block-search__inside-wrapper">
            <label for="<?php echo esc_attr($unique_id); ?>" class="screen-reader-text">
                <?php echo esc_html__('Search for:', 'textdomain'); ?>
            </label>
            <input 
                type="search" 
                id="<?php echo esc_attr($unique_id); ?>"
                class="wp-block-search__input" 
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                value="" 
                name="s" 
                required
                aria-label="<?php echo esc_attr($atts['input_label']); ?>"
            >
            
            <?php if ( ! empty( $atts['post_types'] ) ) : ?>
                <input type="hidden" name="ctd_search_types" value="<?php echo esc_attr( $atts['post_types'] ); ?>" />
            <?php endif; ?>

            <button 
                alt="Search" type="submit" 
                class="wp-block-search__button has-small-font-size has-icon wp-element-button"
                aria-label="<?php echo esc_attr($atts['button_label']); ?>"
            >
                <svg class="search-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
                    <path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path>
                </svg>
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cotlas_search', 'cotlas_search_shortcode');

/**
 * Handle Custom Search Types from cotlas_search shortcode.
 * Reads the ctd_search_types hidden field and restricts the search query
 * to only the requested post types.
 */
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() && $query->is_search() && $query->is_main_query() ) {
        if ( isset( $_GET['ctd_search_types'] ) && ! empty( $_GET['ctd_search_types'] ) ) {
            $types = explode( ',', sanitize_text_field( $_GET['ctd_search_types'] ) );
            $types = array_map( 'trim', $types );
            $query->set( 'post_type', $types );
        }
    }
} );

// Add to functions.php - This might work with GeneratePress hooks
function fix_generatepress_slideout_accessibility() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Fix slideout close button
        $('.slideout-exit.has-svg-icon').attr('aria-label', 'Close menu');
        
        // Also fix when slideout is opened
        $(document).on('click', '.menu-toggle', function() {
            setTimeout(function() {
                $('.slideout-exit.has-svg-icon').attr('aria-label', 'Close menu');
            }, 100);
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'fix_generatepress_slideout_accessibility');

// Order posts by most viewed using Post Views Counter plugin
add_filter( 'generateblocks_query_loop_args', function( $query_args, $attributes ) {
    // Apply filter if loop has class: order-by-views
    if ( ! is_admin() && ! empty( $attributes['className'] ) && strpos( $attributes['className'], 'order-by-views' ) !== false ) {
       
        $query_args = array_merge( $query_args, array(
            'suppress_filters' => false, // Required by PVC
            'orderby' => 'post_views',   // PVC specific orderby
            'order' => 'DESC',           // DESC for most viewed first
        ));
    }

    return $query_args;
}, 10, 2 );

// Add logged-in / logged-out class to body tag
function cotlas_user_login_body_class( $classes ) {
    if ( is_user_logged_in() ) {
        $classes[] = 'user-logged-in';
    } else {
        $classes[] = 'user-logged-out';
    }
    return $classes;
}
add_filter( 'body_class', 'cotlas_user_login_body_class' );

add_shortcode( 'cotlas_logout_link', 'cotlas_logout_link_func' );
function cotlas_logout_link_func() {
    if ( ! is_user_logged_in() ) {
        return ''; // hide if not logged in
    }

    $icon_svg = '<span class="cotlas-logout-icon" style="display:inline-block;vertical-align:middle;width:1em;height:1em;margin-right:6px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
            <path d="M568.482 177.448L424.479 313.433C409.3 327.768 384 317.14 384 295.985v-71.963c-144.575.97-205.566 35.113-164.775 171.353 4.483 14.973-12.846 26.567-25.006 17.33C155.252 383.105 120 326.488 120 269.339c0-143.937 117.599-172.5 264-173.312V24.012c0-21.174 25.317-31.768 40.479-17.448l144.003 135.988c10.02 9.463 10.028 25.425 0 34.896zM384 379.128V448H64V128h50.916a11.99 11.99 0 0 0 8.648-3.693c14.953-15.568 32.237-27.89 51.014-37.676C185.708 80.83 181.584 64 169.033 64H48C21.49 64 0 85.49 0 112v352c0 26.51 21.49 48 48 48h352c26.51 0 48-21.49 48-48v-88.806c0-8.288-8.197-14.066-16.011-11.302a71.83 71.83 0 0 1-34.189 3.377c-7.27-1.046-13.8 4.514-13.8 11.859z"/>
        </svg>
    </span>';

    return '<a class="cotlas-log-out-link" href="' . wp_logout_url(home_url()) . '" title="Logout" class="logout-link">' . $icon_svg . 'Logout</a>';
}

add_shortcode( 'cotlas_login_link', 'cotlas_login_link_func' );
function cotlas_login_link_func() {
    if ( is_user_logged_in() ) {
        return ''; // hide if logged in
    }
    return '<a class="cotlas-log-in-link" href="/login" title="Sign in">Sign in</a>';
}
// Enhanced combined shortcode for category info with link support
function category_info_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'slug' => '',
        'field' => 'name', // 'name', 'description', or 'link'
        'link' => 'false' // 'true' to wrap name in link
    ), $atts);
    
    $category = null;
    
    if (!empty($atts['id'])) {
        $category = get_term($atts['id'], 'category');
    } elseif (!empty($atts['slug'])) {
        $category = get_term_by('slug', $atts['slug'], 'category');
    }
    
    if ($category && !is_wp_error($category)) {
        $category_link = get_category_link($category->term_id);
        
        switch ($atts['field']) {
            case 'description':
                return wp_kses_post($category->description);
                
            case 'link':
                return esc_url($category_link);
                
            case 'name':
            default:
                if ($atts['link'] === 'true') {
                    return '<a href="' . esc_url($category_link) . '" aria-label="' . esc_html($category->name) . '">' . esc_html($category->name) . '</a>';
                } else {
                    return esc_html($category->name);
                }
        }
    }
    
    return 'Category not found';
}
add_shortcode('category_info', 'category_info_shortcode');

/**
 * Async/Defer JavaScript - SAFE VERSION
 */
function async_defer_scripts($tag, $handle, $src) {
    if (is_admin()) return $tag;
    
    // Async scripts (load whenever ready)
    $async_scripts = array(
        'recaptcha-api'
    );
    
    // Defer scripts (load after HTML parsing) - ONLY non-critical scripts
    $defer_scripts = array(
        'gp-child-custom-js' // Only if this doesn't need to run immediately
    );
    
    // NEVER defer these critical scripts
    $critical_scripts = array(
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'wp-embed'
    );
    
    // Skip critical scripts
    if (in_array($handle, $critical_scripts)) {
        return $tag;
    }
    
    if (in_array($handle, $async_scripts)) {
        return str_replace(' src', ' async src', $tag);
    }
    
    if (in_array($handle, $defer_scripts)) {
        return str_replace(' src', ' defer src', $tag);
    }
    
    return $tag;
}

// Remove Dashicons from frontend for all users
function remove_dashicons_frontend_all() {
    wp_deregister_style('dashicons');
}
add_action('wp_enqueue_scripts', 'remove_dashicons_frontend_all', 100);

// Complete image size setup for aspect ratio preservation
function complete_image_size_setup() {
    // Remove ALL existing sizes to start fresh
    global $_wp_additional_image_sizes;
    
    // Remove default WordPress sizes
    remove_image_size('thumbnail');
    remove_image_size('medium');
    remove_image_size('medium_large');
    remove_image_size('large');
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
    
    // Remove any custom sizes we don't need
    remove_image_size('card-main');
    remove_image_size('avatar-main');
    remove_image_size('half-width-card');
    remove_image_size('small-card');

    // Add only the sizes we need with aspect ratio preservation (soft crop)
    // These will maintain the 1200x628 aspect ratio (1.91:1)
    add_image_size('big-featured', 1200, 628, false);  // For big featured - maintains aspect ratio
    add_image_size('large', 1024, 536, false);  // For big featured - maintains aspect ratio
    add_image_size('big-card', 768, 402, false);    // For big cards - maintains aspect ratio
    add_image_size('small-card', 640, 335, false);  // For small cards - maintains aspect ratio
    add_image_size('medium', 300, 157, true);  // For small cards - maintains aspect ratio
    add_image_size('thumbnail', 150, 150, true);  // For small cards - 
    
    // Register for block editor
    add_filter('image_size_names_choose', 'add_custom_sizes_to_editor');
}
add_action('after_setup_theme', 'complete_image_size_setup', 20);

function add_custom_sizes_to_editor($sizes) {
    $custom_sizes = [
        'big-featured' => __('Big Featured (1200x628)'),
        'large' => __('Large (1024x536)'),
        'big-card' => __('Big Card (768x402)'),
        'small-card' => __('Small Card (640x335)'),
        'medium' => __('Medium (300x157)'),
        'thumbnail' => __('Thumbnail (150x150)'),
    ];
    
    return array_merge($sizes, $custom_sizes);
}

// Completely disable all unwanted image sizes
function disable_all_unwanted_sizes($sizes) {
    $allowed_sizes = ['big-featured', 'large', 'big-card', 'small-card', 'medium', 'thumbnail']; // Only keep these
    
    foreach ($sizes as $size => $dimensions) {
        if (!in_array($size, $allowed_sizes)) {
            unset($sizes[$size]);
        }
    }
    
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'disable_all_unwanted_sizes');

// Remove WordPress default image generation completely
function remove_default_image_sizes() {
    // Remove the default sizes that WordPress creates automatically
    update_option('thumbnail_size_w', 0);
    update_option('thumbnail_size_h', 0);
    update_option('thumbnail_crop', 0);
    
    update_option('medium_size_w', 0);
    update_option('medium_size_h', 0);
    
    update_option('medium_large_size_w', 0);
    update_option('medium_large_size_h', 0);
    
    update_option('large_size_w', 0);
    update_option('large_size_h', 0);
}
add_action('after_setup_theme', 'remove_default_image_sizes', 1);

// Optimize srcset to only include our exact sizes
function optimize_srcset_sizes($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    $allowed_widths = [150, 300, 640, 768, 1024, 1200];
    
    foreach ($sources as $width => $source) {
        if (!in_array($width, $allowed_widths)) {
            unset($sources[$width]);
        }
    }
    
    return $sources;
}
add_filter('wp_calculate_image_srcset', 'optimize_srcset_sizes', 10, 5);

// Force aspect ratio preservation for our custom sizes
function force_aspect_ratio_sizes($downsize, $id, $size) {
    $custom_sizes = ['big-featured', 'large', 'big-card', 'small-card', 'medium', 'thumbnail'];
    
    if (in_array($size, $custom_sizes)) {
        // Ensure soft cropping (aspect ratio preservation)
        add_filter('image_resize_dimensions', 'aspect_ratio_dimensions', 10, 6);
    }
    
    return $downsize;
}
add_filter('image_downsize', 'force_aspect_ratio_sizes', 10, 3);

// Ensure aspect ratio is preserved (soft crop)
function aspect_ratio_dimensions($default, $orig_w, $orig_h, $new_w, $new_h, $crop) {
    if ($crop === false) {
        // Soft crop - resize to fit within dimensions while maintaining aspect ratio
        $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);
        
        $crop_w = round($new_w / $size_ratio);
        $crop_h = round($new_h / $size_ratio);
        
        $s_x = floor(($orig_w - $crop_w) / 2);
        $s_y = floor(($orig_h - $crop_h) / 2);
        
        return array(0, 0, (int) $s_x, (int) $s_y, (int) $crop_w, (int) $crop_h, (int) $new_w, (int) $new_h);
    }
    
    return $default;
}

// Ensure block editor uses our custom sizes
function custom_image_sizes_for_blocks($settings) {
    $settings['imageDimensions'] = [
        'big-featured' => ['width' => 1200, 'height' => 628],
        'large' => ['width' => 1024, 'height' => 536],
        'big-card' => ['width' => 768, 'height' => 402],
        'small-card' => ['width' => 640, 'height' => 335],
        'medium' => ['width' => 300, 'height' => 157],
        'thumbnail' => ['width' => 150, 'height' => 150],
    ];
    return $settings;
}
add_filter('block_editor_settings_all', 'custom_image_sizes_for_blocks');



// Remove any additional sizes created by plugins
function remove_plugin_image_sizes($sizes) {
    unset($sizes['woocommerce_thumbnail']);    // If WooCommerce is active
    unset($sizes['woocommerce_single']);
    unset($sizes['woocommerce_gallery_thumbnail']);
    unset($sizes['shop_catalog']);
    unset($sizes['shop_single']);
    unset($sizes['shop_thumbnail']);
    
    return $sizes;
}
add_filter('intermediate_image_sizes', 'remove_plugin_image_sizes');


// Remove lazy loading from the first image on the frontend output
add_action('template_redirect', function() {
    ob_start(function($html) {
        // Match the first image tag with loading="lazy"
        $html = preg_replace('/<img([^>]+)loading=("|\')lazy("|\')([^>]*)>/i', '<img$1loading="eager"$4 fetchpriority="high">', $html, 1);
        return $html;
    });
});

// Critical CSS to force LCP image to render early
add_action('wp_head', function() {
    if (is_home() || is_front_page()) {
        ?>
        <style id="critical-lcp-css">
        /* Force LCP image to render in initial viewport */
        .gb-query-loop-container .gb-grid-column:first-child,
        .gb-query-loop-container .gb-grid-column:first-child .style-big-image {
            content-visibility: auto;
            contain-intrinsic-size: 768px 402px;
        }
        
        /* Ensure LCP image is in the viewport */
        .style-big-image {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            max-width: 768px !important;
            aspect-ratio: 768/402 !important;
        }
        
        /* Remove any lazy loading for LCP image */
        .gb-media-b463938c.style-big-image {
            loading: eager !important;
        }
        </style>
        <?php
    }
}, 1);


/**
 * Dynamic featured image preloading with srcset
 */
function custom_preload_featured_image() {
    $latest_posts = get_posts(array(
        'numberposts' => 1,
        'post_status' => 'publish'
    ));
    
    if (empty($latest_posts)) {
        return;
    }
    
    $post_id = $latest_posts[0]->ID;
    $featured_image_id = get_post_thumbnail_id($post_id);
    
    if (!$featured_image_id) {
        return;
    }
    
    $image_metadata = wp_get_attachment_metadata($featured_image_id);
    
    if (!$image_metadata || !isset($image_metadata['sizes'])) {
        return;
    }
    
    // Find the medium_large size (768px wide) or closest available
    $target_size = custom_find_best_image_size($image_metadata['sizes'], 768, 402);
    
    if ($target_size) {
        custom_generate_image_preloads($featured_image_id, $target_size, $image_metadata['sizes']);
    }
}

/**
 * Find the best matching image size
 */
function custom_find_best_image_size($available_sizes, $target_width = 768, $target_height = 402) {
    $best_match = null;
    $smallest_diff = PHP_INT_MAX;
    
    foreach ($available_sizes as $size_name => $size_data) {
        $width_diff = abs($size_data['width'] - $target_width);
        $height_diff = abs($size_data['height'] - $target_height);
        $total_diff = $width_diff + $height_diff;
        
        if ($total_diff < $smallest_diff) {
            $smallest_diff = $total_diff;
            $best_match = $size_name;
        }
    }
    
    return $best_match;
}

/**
 * Generate image preload tags with multiple formats
 */
function custom_generate_image_preloads($image_id, $size_name, $available_sizes) {
    $base_image_url = wp_get_attachment_image_src($image_id, $size_name);
    
    if (!$base_image_url) {
        return;
    }
    
    $base_url = $base_image_url[0];
    $file_info = pathinfo($base_url);
    
    // Generate alternative format URLs
    $avif_url = custom_convert_image_format($base_url, 'avif');
    
    // Generate srcset for AVIF
    $avif_srcset = custom_generate_srcset($image_id, $available_sizes, 'avif');
    
    if ($avif_url && $avif_srcset) {
        echo '<link rel="preload" as="image" href="' . esc_url($avif_url) . '" fetchpriority="high" type="image/avif" imagesrcset="' . esc_attr($avif_srcset) . '" imagesizes="(max-width: 768px) 100vw, 768px">' . "\n";
    }

}

/**
 * Convert image URL to different format
 */
function custom_convert_image_format($image_url, $format) {
    $supported_formats = array('avif', 'webp');
    
    if (!in_array($format, $supported_formats)) {
        return false;
    }
    
    $pattern = '/\.(jpg|jpeg|png|webp|avif)$/i';
    return preg_replace($pattern, '.' . $format, $image_url);
}

/**
 * Generate srcset string for all available sizes
 */
function custom_generate_srcset($image_id, $available_sizes, $format = 'avif') {
    $srcset_items = array();
    
    // Add all available sizes
    foreach ($available_sizes as $size_name => $size_data) {
        $size_url = wp_get_attachment_image_src($image_id, $size_name);
        
        if ($size_url) {
            $converted_url = custom_convert_image_format($size_url[0], $format);
            if ($converted_url) {
                $srcset_items[] = esc_url($converted_url) . ' ' . $size_data['width'] . 'w';
            }
        }
    }
    
    // Add full size
    $full_url = wp_get_attachment_image_src($image_id, 'full');
    if ($full_url) {
        $full_converted = custom_convert_image_format($full_url[0], $format);
        if ($full_converted) {
            $srcset_items[] = esc_url($full_converted) . ' ' . $full_url[1] . 'w';
        }
    }
    
    return implode(', ', $srcset_items);
}

// Preload for single post pages - full size (1200x628) with srcset
add_action('wp_head', function() {
    if (is_single()) {
        $post_id = get_the_ID();
        $featured_image_id = get_post_thumbnail_id($post_id);
        
        if ($featured_image_id) {
            $image_sizes = wp_get_attachment_metadata($featured_image_id);
            
            if ($image_sizes && isset($image_sizes['sizes'])) {
                $image_url = wp_get_attachment_image_src($featured_image_id, 'full');
                if ($image_url) {
                    $base_url = $image_url[0];
                    $avif_url = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '.avif', $base_url);
                    
                    // Get all image sizes for srcset
                    $srcset = [];
                    foreach ($image_sizes['sizes'] as $size_name => $size_data) {
                        $size_url = wp_get_attachment_image_src($featured_image_id, $size_name);
                        if ($size_url) {
                            $size_avif_url = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '.avif', $size_url[0]);
                            $srcset[] = $size_avif_url . ' ' . $size_data['width'] . 'w';
                        }
                    }
                    
                    // Add full size
                    $srcset[] = $avif_url . ' 1200w';
                    $srcset_string = implode(', ', $srcset);
                    
                    echo '<link rel="preload" as="image" href="' . esc_url($avif_url) . '" fetchpriority="high" type="image/avif" imagesrcset="' . esc_attr($srcset_string) . '" imagesizes="(max-width: 1200px) 100vw, 1200px">' . "\n";
                }
            }
        }
    }
}, 1);

function post_marquee_shortcode($atts) {
    // Shortcode attributes with defaults
    $atts = shortcode_atts([
        'count'    => 5,
        'category' => '',
        'speed'    => 20
    ], $atts, 'post_marquee');

    // Query posts
    $args = [
        'posts_per_page' => $atts['count'],
        'post_type'      => 'post',
        'post_status'    => 'publish'
    ];

    if (!empty($atts['category'])) {
        $args['category_name'] = sanitize_text_field($atts['category']);
    }

    $posts = new WP_Query($args);

    // Start output
    $output = '<div class="post-marquee-container">';
    $output .= '<div class="post-marquee-track" style="--marquee-speed: ' . absint($atts['speed']) . 's;">';

    if ($posts->have_posts()) {
        ob_start();
        $counter = 0;
        $total = $posts->post_count;

        while ($posts->have_posts()) {
            $posts->the_post();
            // Add dot separator except after last post
            
            echo '<a href="' . esc_url(get_permalink()) . '" class="marquee-item">';
            echo esc_html(get_the_title());
            echo '</a>';
        if (++$counter < $total) {
                echo '<span class="marquee-separator">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 .587l3.668 7.568L24 9.75l-6 5.848L19.335 24 12 19.771 4.665 24 6 15.598 0 9.75l8.332-1.595z"/>
                          </svg>
                      </span>';
            
            }
        }

        wp_reset_postdata();
        $post_items = ob_get_clean();

        // Duplicate post items for seamless loop
        $output .= $post_items . $post_items;
    }

    $output .= '</div></div>';

    // Inline CSS
    $output .= '<style>
        .post-marquee-container {
            overflow: hidden;
            white-space: nowrap;
            padding: 1px 0;
        }
        .post-marquee-track {
            display: inline-block;
            white-space: nowrap;
            animation: marquee var(--marquee-speed) linear infinite;
        }
        .post-marquee-container:hover .post-marquee-track {
            animation-play-state: paused;
        }
        .marquee-item {
            display: inline-block;
            margin-right: 20px;
            margin-left: 20px;
            color: #ffffff;
            font-size: 18px;
            font-weight:700;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .marquee-item:hover {
            color: #f7f7f7;
        }
        .marquee-separator {
            display: inline-block;
            margin-right: 15px;
            color: #ffffff;
            font-size: 16px;
        }
        @keyframes marquee {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
    </style>';

    return $output;
}
add_shortcode('post_marquee', 'post_marquee_shortcode');

/**
 * Local Date/Time Shortcode
 * Outputs a placeholder populated client-side with the visitor's local date & time.
 * Usage: [local_datetime]
 * Optional atts: class, date_format (JS Intl format: 'full'|'long'|'medium'|'short'), time_format ('12'|'24')
 */
function cotlas_local_datetime_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'class'       => 'cotlas-local-datetime',
        'date_format' => 'long',   // full | long | medium | short
        'time_format' => '12',     // 12 | 24
    ), $atts, 'local_datetime' );

    $class       = esc_attr( $atts['class'] );
    $date_format = esc_attr( $atts['date_format'] );
    $time_format = esc_attr( $atts['time_format'] );

    return sprintf(
        '<span class="%s" data-date-format="%s" data-time-format="%s" aria-live="polite" aria-label="Current date and time"></span>',
        $class,
        $date_format,
        $time_format
    );
}
add_shortcode( 'local_datetime', 'cotlas_local_datetime_shortcode' );

/**
 * Human-readable relative date helper.
 * Returns "X minutes ago / X hours ago" within 24 hours, otherwise the formatted date.
 *
 * @param  int    $post_id  Post ID.
 * @param  string $type     'published' (default) | 'modified'.
 * @return string
 */
function cotlas_human_date( $post_id, $type = 'published' ) {
    $ts = 'modified' === $type ? get_post_modified_time( 'U', false, $post_id ) : get_post_time( 'U', false, $post_id );
    if ( ! $ts ) {
        return '';
    }

    $diff = time() - $ts;

    if ( $diff < 60 ) {
        return 'अभी-अभी';
    } elseif ( $diff < 3600 ) {
        $mins = (int) round( $diff / 60 );
        return $mins . ' मिनट पहले';
    } elseif ( $diff < 86400 ) {
        $hrs = (int) round( $diff / 3600 );
        return $hrs . ' घंटे पहले';
    } else {
        // Older than 24 h — show formatted date using site date format
        return get_the_date( get_option( 'date_format' ), $post_id );
    }
}

/**
 * [human_date] shortcode — fallback for non-GenerateBlocks contexts.
 * Usage: [human_date] or [human_date type="modified"]
 */
add_shortcode( 'human_date', function ( $atts ) {
    $atts = shortcode_atts( [ 'type' => 'published', 'id' => 0 ], $atts, 'human_date' );
    $id   = $atts['id'] ? (int) $atts['id'] : get_the_ID();
    if ( ! $id ) {
        return '';
    }
    return esc_html( cotlas_human_date( $id, sanitize_text_field( $atts['type'] ) ) );
} );

/**
 * Register {{human_date}} as a GenerateBlocks Dynamic Tag.
 * Appears in the Dynamic Tags modal as "Human Date (Relative)".
 * Supports: link, source (just like post_date).
 * Options:  type — published (default) | modified
 */
add_action( 'init', function () {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'    => __( 'Human Date (Relative)', 'generateblocks' ),
            'tag'      => 'human_date',
            'type'     => 'post',
            'supports' => [ 'link', 'source' ],
            'options'  => [
                'type' => [
                    'type'    => 'select',
                    'label'   => __( 'Date type', 'generateblocks' ),
                    'default' => '',
                    'options' => [
                        [ 'value' => '',         'label' => __( 'Published', 'generateblocks' ) ],
                        [ 'value' => 'modified', 'label' => __( 'Modified',  'generateblocks' ) ],
                    ],
                ],
            ],
            'return'   => function ( $options, $block, $instance ) {
                $id   = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
                if ( ! $id ) {
                    return '';
                }
                $type   = isset( $options['type'] ) && 'modified' === $options['type'] ? 'modified' : 'published';
                $output = cotlas_human_date( $id, $type );

                // Honour the link:post / link:none option the same way GB's post_date does
                if ( ! empty( $options['link'] ) && 'none' !== $options['link'] ) {
                    $url = get_permalink( $id );
                    if ( $url ) {
                        $output = '<a href="' . esc_url( $url ) . '">' . esc_html( $output ) . '</a>';
                        return $output; // already escaped
                    }
                }

                return esc_html( $output );
            },
        ]
    );
}, 20 );

/* =============================================================================
 * FOCUSED CATEGORIES
 * Adds "Focused" and "Highlighted" toggles to category terms.
 * Shortcode: [focused_categories]
 * =============================================================================
 */

/**
 * Register term meta for 'focused' and 'highlighted' flags on categories.
 */
add_action( 'init', function () {
    register_term_meta( 'category', 'cotlas_focused', array(
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ) );
    register_term_meta( 'category', 'cotlas_highlighted', array(
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ) );
} );

/**
 * Add 'Focused' and 'Highlighted' fields on the Add Category screen.
 */
add_action( 'category_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="cotlas_focused"><?php esc_html_e( 'Focused', 'cotlas-admin' ); ?></label>
        <select name="cotlas_focused" id="cotlas_focused">
            <option value="no" selected="selected"><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
            <option value="yes"><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Mark this category as Focused to display it in the Focus Bar.', 'cotlas-admin' ); ?></p>
    </div>
    <div class="form-field">
        <label for="cotlas_highlighted"><?php esc_html_e( 'Highlighted', 'cotlas-admin' ); ?></label>
        <select name="cotlas_highlighted" id="cotlas_highlighted">
            <option value="no" selected="selected"><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
            <option value="yes"><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Show this category as the highlighted (orange pill) item in the Focus Bar.', 'cotlas-admin' ); ?></p>
    </div>
    <?php
} );

/**
 * Add 'Focused' and 'Highlighted' fields on the Edit Category screen.
 */
add_action( 'category_edit_form_fields', function ( $term ) {
    $focused_val     = get_term_meta( $term->term_id, 'cotlas_focused', true ) ?: 'no';
    $highlighted_val = get_term_meta( $term->term_id, 'cotlas_highlighted', true ) ?: 'no';
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="cotlas_focused"><?php esc_html_e( 'Focused', 'cotlas-admin' ); ?></label>
        </th>
        <td>
            <select name="cotlas_focused" id="cotlas_focused">
                <option value="no" <?php selected( $focused_val, 'no' ); ?>><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
                <option value="yes" <?php selected( $focused_val, 'yes' ); ?>><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Mark this category as Focused to display it in the Focus Bar.', 'cotlas-admin' ); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row">
            <label for="cotlas_highlighted"><?php esc_html_e( 'Highlighted', 'cotlas-admin' ); ?></label>
        </th>
        <td>
            <select name="cotlas_highlighted" id="cotlas_highlighted">
                <option value="no" <?php selected( $highlighted_val, 'no' ); ?>><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
                <option value="yes" <?php selected( $highlighted_val, 'yes' ); ?>><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Show this category as the highlighted (orange pill) item in the Focus Bar.', 'cotlas-admin' ); ?></p>
        </td>
    </tr>
    <?php
} );

/**
 * Save 'Focused' and 'Highlighted' meta when creating a category.
 */
add_action( 'created_category', function ( $term_id ) {
    if ( isset( $_POST['cotlas_focused'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_focused'] ) );
        update_term_meta( $term_id, 'cotlas_focused', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
    if ( isset( $_POST['cotlas_highlighted'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_highlighted'] ) );
        update_term_meta( $term_id, 'cotlas_highlighted', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
} );

/**
 * Save 'Focused' and 'Highlighted' meta when editing a category.
 */
add_action( 'edited_category', function ( $term_id ) {
    if ( isset( $_POST['cotlas_focused'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_focused'] ) );
        update_term_meta( $term_id, 'cotlas_focused', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
    if ( isset( $_POST['cotlas_highlighted'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_highlighted'] ) );
        update_term_meta( $term_id, 'cotlas_highlighted', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
} );

/**
 * Shortcode: [focused_categories]
 *
 * Attributes:
 *  label         — Left-side label text. Default: "फोकस"
 *  highlight     — slug of the category to highlight (orange pill). Default: first focused category.
 *  orderby       — name | count | id | term_order. Default: name
 *  order         — ASC | DESC. Default: ASC
 *  class         — extra CSS class on wrapper. Default: ""
 */
function cotlas_focused_categories_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'label'     => 'फोकस',
        'highlight' => '',
        'orderby'   => 'name',
        'order'     => 'ASC',
        'class'     => '',
    ), $atts, 'focused_categories' );

    $terms = get_terms( array(
        'taxonomy'   => 'category',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key'   => 'cotlas_focused',
                'value' => 'yes',
            ),
        ),
        'orderby' => sanitize_key( $atts['orderby'] ),
        'order'   => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    // Determine which slug gets the highlight pill.
    // Priority: 1) shortcode attr, 2) cotlas_highlighted term meta, 3) first term.
    $highlight_slug = sanitize_text_field( $atts['highlight'] );
    if ( empty( $highlight_slug ) ) {
        foreach ( $terms as $term ) {
            if ( get_term_meta( $term->term_id, 'cotlas_highlighted', true ) === 'yes' ) {
                $highlight_slug = $term->slug;
                break;
            }
        }
    }
    if ( empty( $highlight_slug ) ) {
        $highlight_slug = $terms[0]->slug;
    }

    // Move the highlighted term to the front of the list.
    if ( ! empty( $highlight_slug ) ) {
        $highlight_index = null;
        foreach ( $terms as $i => $term ) {
            if ( $term->slug === $highlight_slug ) {
                $highlight_index = $i;
                break;
            }
        }
        if ( $highlight_index !== null && $highlight_index > 0 ) {
            $highlighted_term = array_splice( $terms, $highlight_index, 1 );
            array_unshift( $terms, $highlighted_term[0] );
        }
    }

    $wrapper_class = 'cotlas-focus-bar';
    if ( ! empty( $atts['class'] ) ) {
        $wrapper_class .= ' ' . esc_attr( $atts['class'] );
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $wrapper_class ); ?>" role="navigation" aria-label="<?php esc_attr_e( 'Focused categories', 'cotlas-admin' ); ?>">
        <div class="cotlas-focus-bar__label" aria-hidden="true">
            <?php echo esc_html( $atts['label'] ); ?>
            <span class="cotlas-focus-bar__arrow">&#9658;</span>
        </div>
        <div class="cotlas-focus-bar__track">
            <ul class="cotlas-focus-bar__list">
                <?php foreach ( $terms as $term ) :
                    $is_highlight = ( $term->slug === $highlight_slug );
                    $item_class   = 'cotlas-focus-bar__item' . ( $is_highlight ? ' cotlas-focus-bar__item--highlight' : '' );
                    ?>
                    <li class="<?php echo esc_attr( $item_class ); ?>">
                        <a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'focused_categories', 'cotlas_focused_categories_shortcode' );

/**
 * Admin: Shortcodes Info Menu
 * Quick reference for all shortcodes and GenerateBlocks dynamic tags registered in this plugin.
 */
add_action('admin_menu', function () {
    add_menu_page(
        'Shortcodes Info',
        'Shortcodes Info',
        'manage_options',
        'cotlas-shortcodes-info',
        'cotlas_render_shortcodes_info_page',
        'dashicons-editor-code',
        99
    );
});

function cotlas_render_shortcodes_info_page() {

    echo '<style>
        .cotlas-sc-wrap { max-width:1200px; }
        .cotlas-sc-wrap h1 { margin-bottom:4px; }
        .cotlas-sc-wrap .sc-subtitle { color:#666; margin-bottom:24px; font-size:13px; }
        .cotlas-sc-wrap h2 { border-bottom:1px solid #dcdcde; padding-bottom:6px; margin-top:32px; }
        .cotlas-sc-wrap h3 { margin:0 0 6px; font-size:14px; }
        .cotlas-sc-wrap table code { background:#f0f0f1; padding:2px 5px; border-radius:3px; font-size:12px; }
        .cotlas-sc-wrap .usage-block { background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:16px 20px; margin-bottom:16px; }
        .cotlas-sc-wrap .usage-block p { margin:0 0 8px; color:#555; font-size:13px; }
        .cotlas-sc-wrap .usage-block ul { margin:4px 0 0; padding-left:18px; }
        .cotlas-sc-wrap .usage-block ul li { font-size:13px; margin-bottom:4px; line-height:1.6; }
        .cotlas-sc-wrap .usage-block ul li code { background:#f0f0f1; padding:1px 4px; border-radius:3px; }
        .cotlas-sc-wrap .tag-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:12px; }
        .cotlas-sc-wrap .gb-tag-card { background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:14px 16px; }
        .cotlas-sc-wrap .gb-tag-card h4 { margin:0 0 6px; font-size:13px; color:#1d2327; }
        .cotlas-sc-wrap .gb-tag-card p { margin:0 0 6px; font-size:12px; color:#666; }
        .cotlas-sc-wrap .gb-tag-card ul { margin:0; padding-left:16px; }
        .cotlas-sc-wrap .gb-tag-card ul li { font-size:12px; color:#555; margin-bottom:2px; }
        .cotlas-sc-wrap .notice-tip { background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 3px 3px 0; margin-bottom:20px; font-size:13px; }
    </style>';

    echo '<div class="wrap cotlas-sc-wrap">';
    echo '<h1>Shortcodes Info</h1>';
    echo '<p class="sc-subtitle">All shortcodes and GenerateBlocks dynamic tags registered by the <strong>cotlas-admin</strong> plugin. Site settings are managed under <strong>Settings &rarr; Site Settings</strong>.</p>';

    // ── 1. Plugin Shortcodes ─────────────────────────────────────────────────
    $plugin_shortcodes = [
        ['tag' => 'gp_nav',                 'desc' => 'Renders the GeneratePress navigation menu. No attributes.'],
        ['tag' => 'first_category',         'desc' => 'Shows the first category of the current post as a linked badge with icon. No attributes.'],
        ['tag' => 'yoast_primary_category', 'desc' => 'Displays Yoast primary category (or first category fallback). Supports: show_link, class, text_class, fallback.'],
        ['tag' => 'social_share',           'desc' => 'Social share buttons for the current post. Supports: class, size, show_names, networks.'],
        ['tag' => 'author_social_links',    'desc' => 'Author social profile links from their WP profile. Supports: class, size, show_names, networks.'],
        ['tag' => 'cotlas_social',          'desc' => 'Site social icons from Site Settings. Supports: class, size, show_names, networks.'],
        ['tag' => 'audio_player',           'desc' => 'HTML5 audio player for Audio-format posts. Requires _audio_file_url meta. Supports: style, width, height, autoplay, loop.'],
        ['tag' => 'cotlas_search',          'desc' => 'Accessible search form. Supports: placeholder, button_label, input_label, post_types.'],
        ['tag' => 'local_datetime',         'desc' => "Visitor's local date/time updating live via JS. Supports: class, date_format, time_format."],
        ['tag' => 'cotlas_comments',        'desc' => 'Styled threaded comments section. Supports: post_id, title.'],
        ['tag' => 'cotlas_logout_link',     'desc' => 'Logout link — only renders when user is logged in. No attributes.'],
        ['tag' => 'cotlas_login_link',      'desc' => 'Login link — only renders when user is logged out. No attributes.'],
        ['tag' => 'category_info',          'desc' => "Output any category's name, description, or URL. Supports: id, slug, field, link."],
        ['tag' => 'post_marquee',           'desc' => 'Scrolling headline ticker for recent posts. Supports: count, category, speed.'],
        ['tag' => 'trending_categories',    'desc' => 'List of trending categories by post activity. Cached 1 hour. Supports: count, label.'],
        ['tag' => 'most_read',              'desc' => 'Most-viewed posts (requires Post Views Counter plugin). Cached 1 hour. Supports: count.'],
        ['tag' => 'human_date',             'desc' => 'Relative date ("3 hours ago"). Falls back to formatted date after 24 h. Supports: type, id.'],
        ['tag' => 'focused_categories',     'desc' => 'Horizontal scrollable focus bar of tagged categories. Mark categories via Posts → Categories. Supports: label, highlight, orderby, order, class.'],
    ];

    echo '<h2>Plugin Shortcodes (cotlas-admin)</h2>';
    echo '<table class="widefat striped"><thead><tr><th style="width:240px">Shortcode</th><th>Description</th></tr></thead><tbody>';
    foreach ($plugin_shortcodes as $sc) {
        printf('<tr><td><code>[%s]</code></td><td>%s</td></tr>', esc_html($sc['tag']), esc_html($sc['desc']));
    }
    echo '</tbody></table>';

    // ── 2. Site Settings Shortcodes ──────────────────────────────────────────
    $settings_shortcodes = [
        ['tag' => 'company_name',        'desc' => 'Company/site name'],
        ['tag' => 'company_tagline',     'desc' => 'Company tagline or slogan'],
        ['tag' => 'company_address',     'desc' => 'Company address (line breaks preserved)'],
        ['tag' => 'company_phone',       'desc' => 'Company phone number'],
        ['tag' => 'company_email',       'desc' => 'Company email address'],
        ['tag' => 'company_short_intro', 'desc' => 'Short company introduction paragraph (HTML allowed)'],
        ['tag' => 'company_whatsapp',    'desc' => 'WhatsApp number'],
        ['tag' => 'social_facebook',     'desc' => 'Facebook page URL'],
        ['tag' => 'social_twitter',      'desc' => 'Twitter/X profile URL'],
        ['tag' => 'social_youtube',      'desc' => 'YouTube channel URL'],
        ['tag' => 'social_instagram',    'desc' => 'Instagram profile URL'],
        ['tag' => 'social_linkedin',     'desc' => 'LinkedIn page URL'],
        ['tag' => 'social_threads',      'desc' => 'Threads profile URL'],
    ];

    echo '<h2>Site Settings Shortcodes</h2>';
    echo '<p style="color:#555;font-size:13px;margin-top:6px;">Output values saved in <strong>Settings &rarr; Site Settings</strong>. These shortcodes take no attributes — they simply return the saved value.</p>';
    echo '<table class="widefat striped"><thead><tr><th style="width:240px">Shortcode</th><th>Description</th></tr></thead><tbody>';
    foreach ($settings_shortcodes as $sc) {
        printf('<tr><td><code>[%s]</code></td><td>%s</td></tr>', esc_html($sc['tag']), esc_html($sc['desc']));
    }
    echo '</tbody></table>';

    // ── 3. GenerateBlocks Dynamic Tags ───────────────────────────────────────
    echo '<h2>GenerateBlocks Dynamic Tags</h2>';
    echo '<div class="notice-tip">These tags appear as <strong>dropdown selections</strong> inside the GenerateBlocks editor (Dynamic Content &amp; Dynamic Link panels). No manual key entry needed &mdash; just pick from the dropdown.</div>';

    $gb_native_tags = [
        [
            'tag'     => 'Company Info',
            'tag_id'  => 'company_info',
            'type'    => 'Option value (global setting)',
            'use_for' => 'Dynamic Content — any text block',
            'desc'    => 'Outputs any company detail from Site Settings.',
            'options' => ['Company Name', 'Company Tagline', 'Company Address', 'Company Phone', 'Company Email', 'Company Short Intro', 'Company WhatsApp'],
        ],
        [
            'tag'     => 'Company Social URL',
            'tag_id'  => 'company_social',
            'type'    => 'Option value (global setting)',
            'use_for' => 'Dynamic Link — icon or button blocks',
            'desc'    => 'Outputs a social network URL from Site Settings. WhatsApp auto-generates a wa.me/... link.',
            'options' => ['Facebook', 'Twitter/X', 'Instagram', 'LinkedIn', 'YouTube', 'Threads', 'WhatsApp'],
        ],
        [
            'tag'     => 'Yoast Primary Category',
            'tag_id'  => 'yoast_primary_category',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link in query loops',
            'desc'    => 'Primary Yoast category of the current post. Falls back to first category.',
            'options' => ['Category name', 'Linked category name (full HTML)', 'Category URL', 'Category slug', 'Category ID'],
        ],
        [
            'tag'     => 'Human Date (Relative)',
            'tag_id'  => 'human_date',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content — date display blocks',
            'desc'    => 'Relative date string ("3 hours ago"). Falls back to formatted date after 24 hours. Supports Dynamic Link (links to post).',
            'options' => ['Published date (default)', 'Modified date'],
        ],
        [
            'tag'     => 'Post Views Count',
            'tag_id'  => 'post_views',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content — view count display',
            'desc'    => 'Returns the raw view count number for the current post. Requires Post Views Counter plugin. Outputs plain number, no HTML.',
            'options' => ['Current post (default)', 'Specific post via source picker'],
        ],
        [
            'tag'     => 'Primary Category',
            'tag_id'  => 'primary_category',
            'type'    => 'Post-based (query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link — category label',
            'desc'    => 'Yoast SEO primary category of the current post. Falls back to first assigned category. Supports Dynamic Link (wraps in <a> to category archive).',
            'options' => ['Category name (plain text)', 'Linked category name — enable via Dynamic Link → Term'],
        ],
        [
            'tag'     => 'Term Display',
            'tag_id'  => 'term_display',
            'type'    => 'Term-based (taxonomy archive or query loop)',
            'use_for' => 'Dynamic Content or Dynamic Link — any term data',
            'desc'    => 'Outputs any field from a category/taxonomy term. Works on archive pages and post loops. Key option controls what is returned.',
            'options' => ['term_title — term name', 'term_desc — term description', 'term_image — category featured image URL', 'term_count — number of posts in term', 'term_url — term archive URL'],
        ],
        [
            'tag'     => 'Term / Category Image',
            'tag_id'  => 'term_image',
            'type'    => 'Post-based (uses primary category)',
            'use_for' => 'Dynamic Image src or Dynamic Link — category image blocks',
            'desc'    => 'Returns the featured image set on the category (via Categories screen). Resolves via Yoast primary category inside post loops, or queried term on archives.',
            'options' => ['key:url — image URL (default)', 'key:id — attachment post ID', 'key:alt — image alt text', 'size: — any registered image size (e.g. medium, large)'],
        ],
    ];

    echo '<div class="tag-grid">';
    foreach ($gb_native_tags as $t) {
        echo '<div class="gb-tag-card">';
        printf(
            '<h4>%s &nbsp;<small style="color:#888;font-weight:400;">tag: <code>%s</code></small></h4>',
            esc_html($t['tag']),
            esc_html($t['tag_id'])
        );
        printf('<p><strong>Type:</strong> %s<br><strong>Use for:</strong> %s</p>', esc_html($t['type']), esc_html($t['use_for']));
        printf('<p>%s</p>', esc_html($t['desc']));
        echo '<ul>';
        foreach ($t['options'] as $opt) {
            printf('<li>%s</li>', esc_html($opt));
        }
        echo '</ul></div>';
    }
    echo '</div>';

    // ── 3b. Legacy post-meta keys ────────────────────────────────────────────
    echo '<h3 style="margin-top:24px;">Legacy post-meta Keys (manual entry)</h3>';
    echo '<p style="color:#555;font-size:13px;">Alternatively, set <em>Dynamic Content Type</em> to <strong>post-meta</strong> in GenerateBlocks and type one of these keys manually into the <em>Meta Field Name</em> field. The native dropdown tags above are the preferred method.</p>';

    $gb_keys = [
        ['key' => 'cotlas_company_name',        'desc' => 'Company name'],
        ['key' => 'cotlas_company_tagline',     'desc' => 'Company tagline'],
        ['key' => 'cotlas_company_address',     'desc' => 'Company address'],
        ['key' => 'cotlas_company_phone',       'desc' => 'Company phone'],
        ['key' => 'cotlas_company_email',       'desc' => 'Company email'],
        ['key' => 'cotlas_company_short_intro', 'desc' => 'Company short intro'],
        ['key' => 'cotlas_company_whatsapp',    'desc' => 'WhatsApp number'],
        ['key' => 'cotlas_social_facebook',     'desc' => 'Facebook URL'],
        ['key' => 'cotlas_social_twitter',      'desc' => 'Twitter/X URL'],
        ['key' => 'cotlas_social_youtube',      'desc' => 'YouTube URL'],
        ['key' => 'cotlas_social_instagram',    'desc' => 'Instagram URL'],
        ['key' => 'cotlas_social_linkedin',     'desc' => 'LinkedIn URL'],
        ['key' => 'cotlas_social_threads',      'desc' => 'Threads URL'],
    ];

    echo '<table class="widefat striped"><thead><tr><th style="width:280px">Key (Meta Field Name)</th><th>Description</th></tr></thead><tbody>';
    foreach ($gb_keys as $k) {
        printf('<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html($k['key']), esc_html($k['desc']));
    }
    echo '</tbody></table>';

    // ── 4. Detailed Usage & Attributes ───────────────────────────────────────
    echo '<h2>Shortcode Usage &amp; Attributes</h2>';

    // [gp_nav]
    echo '<div class="usage-block">';
    echo '<h3>[gp_nav]</h3>';
    echo '<p>Renders the GeneratePress navigation menu exactly as it appears in the theme. Useful inside custom layout elements or widget areas where the nav is not automatically inserted by the theme.</p>';
    echo '<p><em>No attributes.</em></p><p>Example: <code>[gp_nav]</code></p>';
    echo '</div>';

    // [first_category]
    echo '<div class="usage-block">';
    echo '<h3>[first_category]</h3>';
    echo '<p>Outputs the first category of the current post as a linked badge with an SVG icon. Designed for post headers and cards inside query loops.</p>';
    echo '<p><em>No attributes.</em></p><p>Example: <code>[first_category]</code></p>';
    echo '</div>';

    // [yoast_primary_category]
    echo '<div class="usage-block">';
    echo '<h3>[yoast_primary_category]</h3>';
    echo '<p>Displays the Yoast SEO primary category of the current post. Falls back to the first assigned category if no primary is set.</p>';
    echo '<ul>';
    echo '<li><strong>show_link</strong>: <code>true</code> (default) | <code>false</code> &mdash; wrap the category name in a link. Example: <code>[yoast_primary_category show_link="false"]</code></li>';
    echo '<li><strong>class</strong>: CSS class for the wrapper &lt;p&gt; element. Default: <code>gp-post-category</code>. Example: <code>[yoast_primary_category class="post-badge"]</code></li>';
    echo '<li><strong>text_class</strong>: CSS class for the inner &lt;span&gt;. Default: <code>gp-post-category-text</code>.</li>';
    echo '<li><strong>fallback</strong>: <code>first</code> (default, show first category if no Yoast primary) | <code>none</code> (show nothing). Example: <code>[yoast_primary_category fallback="none"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_comments]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_comments]</h3>';
    echo '<p>Renders a styled threaded comments section with a reply form. Automatically targets the current post when used inside the loop.</p>';
    echo '<ul>';
    echo '<li><strong>post_id</strong>: Target a specific post by ID. Default: current post. Example: <code>[cotlas_comments post_id="42"]</code></li>';
    echo '<li><strong>title</strong>: Heading text shown above the comments. Default: <code>Comments</code>. Example: <code>[cotlas_comments title="Reader Comments"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [social_share]
    echo '<div class="usage-block">';
    echo '<h3>[social_share]</h3>';
    echo '<p>Renders social share buttons for the current post. Typically placed at the top and/or bottom of post content.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: CSS classes for the wrapper element. Example: <code>[social_share class="cotlas-social-share cotlas-social-share-top"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[social_share size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default) &mdash; show platform label next to icon. Example: <code>[social_share show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Comma-separated platforms to include. Default: all. Allowed values: <code>facebook, twitter, linkedin, whatsapp, telegram, pinterest, reddit, threads, print</code>. Example: <code>[social_share networks="facebook,twitter,whatsapp"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [author_social_links]
    echo '<div class="usage-block">';
    echo '<h3>[author_social_links]</h3>';
    echo '<p>Shows the post author\'s social profile links. Values are pulled from the author\'s WordPress user profile fields. Best used on single-post or author archive templates.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: Wrapper CSS class. Default: <code>cotlas-author-social-links</code>. Example: <code>[author_social_links class="author-social compact"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[author_social_links size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default). Example: <code>[author_social_links show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Comma-separated platforms. Allowed: <code>facebook, twitter, instagram, linkedin, youtube, pinterest, email</code>. Use <code>x</code> as alias for Twitter. Example: <code>[author_social_links networks="facebook,x,instagram"]</code></li>';
    echo '<li>Profile fields read from user meta: <code>facebook, twitter, instagram, linkedin, youtube, pinterest</code>. Email is read from the WP account email.</li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_social]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_social]</h3>';
    echo '<p>Renders site-wide social icon links. URLs are pulled from <strong>Settings &rarr; Site Settings</strong>. Only platforms that have a saved URL are rendered.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: Wrapper CSS class. Example: <code>[cotlas_social class="footer-social"]</code></li>';
    echo '<li><strong>size</strong>: Icon size in pixels. Default: <code>24</code>. Example: <code>[cotlas_social size="20"]</code></li>';
    echo '<li><strong>show_names</strong>: <code>true</code> | <code>false</code> (default). Example: <code>[cotlas_social show_names="true"]</code></li>';
    echo '<li><strong>networks</strong>: Limit to specific platforms. Allowed: <code>facebook, twitter, instagram, youtube, linkedin, threads, whatsapp</code>. Use <code>x</code> as alias for Twitter. Example: <code>[cotlas_social networks="facebook,x,instagram,youtube"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_search]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_search]</h3>';
    echo '<p>Accessible styled search form that submits to the standard WordPress search results page.</p>';
    echo '<ul>';
    echo '<li><strong>placeholder</strong>: Input placeholder text. Default: <code>Type to search...</code>. Example: <code>[cotlas_search placeholder="Search news..."]</code></li>';
    echo '<li><strong>button_label</strong>: Aria-label for the submit button (for screen readers). Default: <code>Submit search</code>. Example: <code>[cotlas_search button_label="Go"]</code></li>';
    echo '<li><strong>input_label</strong>: Aria-label for the search input (for screen readers). Default: <code>Search our website</code>. Example: <code>[cotlas_search input_label="Search articles"]</code></li>';
    echo '<li><strong>post_types</strong>: Comma-separated post types to restrict results. Default: <code>post,page</code>. Example: <code>[cotlas_search post_types="post"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [audio_player]
    echo '<div class="usage-block">';
    echo '<h3>[audio_player]</h3>';
    echo '<p>Renders a styled HTML5 audio player. Requires the post\'s <em>Post Format</em> set to <strong>Audio</strong> and a custom field named <code>_audio_file_url</code> containing the audio file URL.</p>';
    echo '<ul>';
    echo '<li><strong>style</strong>: Visual theme. <code>modern</code> (default) | <code>minimal</code> | <code>dark</code>. Example: <code>[audio_player style="dark"]</code></li>';
    echo '<li><strong>width</strong>: CSS width of the player. Default: <code>100%</code>. Example: <code>[audio_player width="320px"]</code></li>';
    echo '<li><strong>height</strong>: CSS height of the player. Default: <code>50px</code>. Example: <code>[audio_player height="40px"]</code></li>';
    echo '<li><strong>autoplay</strong>: <code>yes</code> | <code>no</code> (default). Example: <code>[audio_player autoplay="yes"]</code></li>';
    echo '<li><strong>loop</strong>: <code>yes</code> | <code>no</code> (default). Example: <code>[audio_player loop="yes"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [local_datetime]
    echo '<div class="usage-block">';
    echo '<h3>[local_datetime]</h3>';
    echo '<p>Shows the visitor\'s local date and time, updating every second via JavaScript. No server request — the browser fills in the time using the visitor\'s own timezone.</p>';
    echo '<ul>';
    echo '<li><strong>class</strong>: CSS class for the output span. Default: <code>cotlas-local-datetime</code>. Example: <code>[local_datetime class="header-clock"]</code></li>';
    echo '<li><strong>date_format</strong>: Intl date style. <code>full</code> | <code>long</code> (default) | <code>medium</code> | <code>short</code>. Example: <code>[local_datetime date_format="short"]</code></li>';
    echo '<li><strong>time_format</strong>: <code>12</code> (default, AM/PM) | <code>24</code> (24-hour). Example: <code>[local_datetime time_format="24"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [category_info]
    echo '<div class="usage-block">';
    echo '<h3>[category_info]</h3>';
    echo '<p>Outputs a specific field from any category. Identify the category by its WordPress ID or slug.</p>';
    echo '<ul>';
    echo '<li><strong>id</strong>: Category ID. Example: <code>[category_info id="5" field="name"]</code></li>';
    echo '<li><strong>slug</strong>: Category slug — used when <code>id</code> is not set. Example: <code>[category_info slug="sports"]</code></li>';
    echo '<li><strong>field</strong>: What to output. <code>name</code> (default) | <code>description</code> | <code>link</code> (returns the category archive URL). Example: <code>[category_info slug="sports" field="link"]</code></li>';
    echo '<li><strong>link</strong>: <code>true</code> | <code>false</code> (default) &mdash; when <code>field="name"</code>, wraps the name in an &lt;a&gt; tag linking to the category archive. Example: <code>[category_info slug="sports" link="true"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [post_marquee]
    echo '<div class="usage-block">';
    echo '<h3>[post_marquee]</h3>';
    echo '<p>A CSS-animated scrolling ticker showing recent post headlines with links to each post.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of posts to include. Default: <code>5</code>. Example: <code>[post_marquee count="8"]</code></li>';
    echo '<li><strong>category</strong>: Restrict to a specific category by slug. Default: all categories. Example: <code>[post_marquee category="cricket"]</code></li>';
    echo '<li><strong>speed</strong>: Animation loop duration in seconds. Lower = faster scroll. Default: <code>20</code>. Example: <code>[post_marquee speed="35"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [trending_categories]
    echo '<div class="usage-block">';
    echo '<h3>[trending_categories]</h3>';
    echo '<p>Displays a list of popular categories ranked by number of published posts. Results are cached for 1 hour.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of categories to show. Min: 1, Max: 20. Default: <code>6</code>. Example: <code>[trending_categories count="8"]</code></li>';
    echo '<li><strong>label</strong>: Optional heading text shown above the category list. Default: none. Example: <code>[trending_categories label="Popular Topics"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [most_read]
    echo '<div class="usage-block">';
    echo '<h3>[most_read]</h3>';
    echo '<p>Shows the most-viewed posts on the site. Requires the <strong>Post Views Counter</strong> plugin to be active for accurate view data. Results are cached for 1 hour.</p>';
    echo '<ul>';
    echo '<li><strong>count</strong>: Number of posts to show. Min: 1, Max: 10. Default: <code>3</code>. Example: <code>[most_read count="5"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [human_date]
    echo '<div class="usage-block">';
    echo '<h3>[human_date]</h3>';
    echo '<p>Outputs a relative date string like <em>"5 minutes ago"</em> or <em>"2 days ago"</em>. Within the last 24 hours it shows elapsed time; beyond that it shows the formatted publish/modified date.</p>';
    echo '<ul>';
    echo '<li><strong>type</strong>: <code>published</code> (default) | <code>modified</code>. Example: <code>[human_date type="modified"]</code></li>';
    echo '<li><strong>id</strong>: Post ID to target. Default: current post in loop. Example: <code>[human_date id="42"]</code></li>';
    echo '</ul>';
    echo '</div>';

    // [cotlas_logout_link] & [cotlas_login_link]
    echo '<div class="usage-block">';
    echo '<h3>[cotlas_logout_link] &amp; [cotlas_login_link]</h3>';
    echo '<p><code>[cotlas_logout_link]</code> renders a styled logout link with icon. <strong>Only visible when a user is logged in.</strong> Redirects to the homepage after logout.</p>';
    echo '<p><code>[cotlas_login_link]</code> renders a &ldquo;Sign in&rdquo; link. <strong>Only visible when no user is logged in.</strong> Links to <code>/login</code>.</p>';
    echo '<p><em>Neither shortcode accepts attributes.</em></p>';
    echo '</div>';

    // [focused_categories]
    echo '<div class="usage-block">';
    echo '<h3>[focused_categories]</h3>';
    echo '<p>A horizontally scrollable pill-style navigation bar showing categories marked as <strong>Focused</strong>. One category can be marked <strong>Highlighted</strong> to display with a coloured pill and appear first. Manage flags under <strong>Posts &rarr; Categories</strong> (Focused / Highlighted dropdowns).</p>';
    echo '<ul>';
    echo '<li><strong>label</strong>: Left-side label text. Default: <code>फोकस</code>. Example: <code>[focused_categories label="Focus"]</code></li>';
    echo '<li><strong>highlight</strong>: Category slug to force as the highlighted pill. Default: the category with the Highlighted flag set, or the first item. Example: <code>[focused_categories highlight="cricket"]</code></li>';
    echo '<li><strong>orderby</strong>: <code>name</code> (default) | <code>count</code> | <code>id</code> | <code>term_order</code>. Example: <code>[focused_categories orderby="count" order="DESC"]</code></li>';
    echo '<li><strong>order</strong>: <code>ASC</code> (default) | <code>DESC</code>.</li>';
    echo '<li><strong>class</strong>: Extra CSS class on the wrapper element. Example: <code>[focused_categories class="compact-bar"]</code></li>';
    echo '</ul>';
    echo '<p><em>Returns empty output if no categories have the Focused flag set.</em></p>';
    echo '</div>';

    // ── 5. GenerateBlocks Dynamic Tag Reference ───────────────────────────────
    echo '<h2>GenerateBlocks Dynamic Tag Usage</h2>';

    // post_views
    echo '<div class="usage-block">';
    echo '<h3>post_views <small style="color:#888;font-weight:400;">tag: <code>post_views</code></small></h3>';
    echo '<p>Returns the raw view count number for the current (or specified) post. Requires the <strong>Post Views Counter</strong> plugin. Outputs a plain number — no icon or HTML wrapper.</p>';
    echo '<ul>';
    echo '<li><strong>source</strong>: Pick via the source selector in the GB editor — <em>Current post</em> (default) or a specific post.</li>';
    echo '<li>Use in a GB Text block as Dynamic Content to display a number like <code>4,821</code>.</li>';
    echo '<li>Combine with a static icon/SVG block and this tag to build a &ldquo;views&rdquo; counter display.</li>';
    echo '</ul>';
    echo '</div>';

    // primary_category
    echo '<div class="usage-block">';
    echo '<h3>Primary Category <small style="color:#888;font-weight:400;">tag: <code>primary_category</code></small></h3>';
    echo '<p>Outputs the Yoast SEO primary category of the current post. Falls back to the first assigned category when no primary is set. Works in query loops.</p>';
    echo '<ul>';
    echo '<li><strong>Dynamic Content:</strong> Outputs the plain category name. Set Dynamic Content type to <em>Dynamic Tag</em> &rarr; <em>Primary Category</em>.</li>';
    echo '<li><strong>Dynamic Link &rarr; Term:</strong> Wraps the output in an <code>&lt;a href&gt;</code> linking to the category archive page.</li>';
    echo '<li><strong>source:</strong> Current post (default) or choose a specific post via the source picker.</li>';
    echo '<li>Similar to <code>yoast_primary_category</code> but with broader link support via the GB link panel.</li>';
    echo '</ul>';
    echo '</div>';

    // term_display
    echo '<div class="usage-block">';
    echo '<h3>Term Display <small style="color:#888;font-weight:400;">tag: <code>term_display</code></small></h3>';
    echo '<p>Outputs any field from a category or taxonomy term. Works on taxonomy archive pages and inside post query loops. Resolves the term automatically — via the queried object on archives, or the Yoast primary category inside loops.</p>';
    echo '<ul>';
    echo '<li><strong>key: term_title</strong> &mdash; the term name. Example: <code>term_display id:33 tax:category key:term_title</code></li>';
    echo '<li><strong>key: term_desc</strong> &mdash; the term description (HTML preserved).</li>';
    echo '<li><strong>key: term_image</strong> &mdash; URL of the category featured image (set via the Categories screen). Example: use as Image block src.</li>';
    echo '<li><strong>key: term_count</strong> &mdash; number of published posts in the term.</li>';
    echo '<li><strong>key: term_url</strong> &mdash; the term archive URL. Example: use as Dynamic Link on a button or image.</li>';
    echo '<li><strong>id:</strong> Explicit term ID. Optional — omit to auto-resolve from context.</li>';
    echo '<li><strong>tax:</strong> Taxonomy slug. Default: <code>category</code>.</li>';
    echo '</ul>';
    echo '<p><strong>Tip:</strong> To link an Image block to the category archive, set <em>Image src</em> to <code>term_display key:term_image</code> and <em>Dynamic Link</em> to <code>term_display key:term_url</code>.</p>';
    echo '</div>';

    // term_image
    echo '<div class="usage-block">';
    echo '<h3>Term / Category Image <small style="color:#888;font-weight:400;">tag: <code>term_image</code></small></h3>';
    echo '<p>Returns the featured image set on a category (uploaded via the Categories admin screen). Inside post loops it uses the Yoast primary category; on archive pages it uses the queried term.</p>';
    echo '<ul>';
    echo '<li><strong>key:url</strong> (default) &mdash; returns the full image URL.</li>';
    echo '<li><strong>key:id</strong> &mdash; returns the WordPress attachment post ID.</li>';
    echo '<li><strong>key:alt</strong> &mdash; returns the image alt text.</li>';
    echo '<li><strong>size:</strong> Any registered image size. Default: <code>full</code>. Example: <code>term_image id:12 size:medium</code></li>';
    echo '<li><strong>id:</strong> Explicit term ID. Omit to auto-resolve from the current post or archive context.</li>';
    echo '</ul>';
    echo '<p><strong>How to set a category image:</strong> Go to <em>Posts &rarr; Categories</em>, edit any category, and use the <em>Category Image</em> upload field.</p>';
    echo '</div>';

    // ── 6. GenerateBlocks Query Loop Parameters ───────────────────────────────
    echo '<h2>GenerateBlocks Query Loop Parameters</h2>';
    echo '<div class="notice-tip">These are custom parameters added to GenerateBlocks Query Loop blocks via the <strong>Query Parameters</strong> panel in the block editor. They extend the standard query options.</div>';

    $query_params = [
        [
            'param'   => 'featuredPosts',
            'desc'    => 'Filter query results by the Featured Post flag set in the block editor sidebar.',
            'values'  => [
                '<code>only</code> — show only posts marked as featured',
                '<code>exclude</code> — hide all featured posts, show the rest',
                '(empty) — no filter, show all posts (default)',
            ],
            'note'    => 'The Featured Post toggle appears in the block editor sidebar under a "Featured Post" panel. It saves the <code>_is_featured</code> post meta.',
        ],
        [
            'param'   => 'popularPosts',
            'desc'    => 'Order query results by view count (most viewed first). Requires the Post Views Counter plugin.',
            'values'  => [
                '<code>1</code> or any truthy value — enable ordering by views descending',
            ],
            'note'    => 'When active, overrides the Order By setting and sets orderby to post_views with suppress_filters disabled.',
        ],
    ];

    foreach ($query_params as $qp) {
        echo '<div class="usage-block">';
        printf('<h3>%s</h3>', esc_html($qp['param']));
        printf('<p>%s</p>', esc_html($qp['desc']));
        echo '<ul>';
        foreach ($qp['values'] as $v) {
            echo '<li>' . wp_kses($v, ['code' => []]) . '</li>';
        }
        echo '</ul>';
        printf('<p><em>Note: %s</em></p>', esc_html($qp['note']));
        echo '</div>';
    }

    echo '</div>'; // .cotlas-sc-wrap
}
/**
 * Custom GenerateBlocks Dynamic Tag: Post Views Count
 *
 * Returns the raw view count number for the current (or specified) post,
 * from the Post Views Counter plugin. No HTML, no icon — just the number.
 *
 * Usage in a GB Text block:
 *   {{post_views}}            – view count of the current post in a loop
 *   {{post_views id:42}}      – view count of a specific post by ID
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_loaded', 'gpc_register_post_views_tag' );
function gpc_register_post_views_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'post_views',
			'title'       => __( 'Post Views Count', 'gp-child' ),
			'description' => __( 'Returns the raw view count number for the current post (Post Views Counter plugin). No icon or HTML — just the number.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'return'      => 'gpc_post_views_callback',
		)
	);
}

/**
 * Callback — returns the view count as a plain string.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_post_views_callback( $options, $block, $instance ) {
	if ( ! empty( $options['id'] ) ) {
		$post_id = absint( $options['id'] );
	} else {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return '0';
	}

	if ( ! function_exists( 'pvc_get_post_views' ) ) {
		return '0';
	}

	$views = pvc_get_post_views( $post_id );

	return (string) absint( $views );
}
/**
 * Custom GenerateBlocks Dynamic Tag: Primary Category
 *
 * Outputs the Yoast SEO primary category for the current post.
 * Falls back to the first assigned category when no primary is set.
 *
 * Usage in a GB Text block:
 *   {{primary_category}}                       – plain category name
 *   {{primary_category link:term}}             – linked category name
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register the dynamic tag once GB has loaded its own tags ─────────────────

add_action( 'init', 'gpc_register_primary_category_tag', 20 );
function gpc_register_primary_category_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'primary_category',
			'title'       => __( 'Primary Category', 'gp-child' ),
			'description' => __( 'Get the Yoast SEO primary category for the current post. Falls back to the first assigned category.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'link', 'source', 'taxonomy' ),
			'return'      => 'gpc_primary_category_callback',
		)
	);
}

// ── Callback ─────────────────────────────────────────────────────────────────

/**
 * Returns the primary category name (or linked name).
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    The block data.
 * @param object $instance The block instance.
 * @return string
 */
function gpc_primary_category_callback( $options, $block, $instance ) {
	$post_id = isset( $options['source'] ) && 'current' !== $options['source']
		? absint( $options['source'] )
		: get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	// Try Yoast primary category first.
	$primary_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );

	if ( $primary_id ) {
		$term = get_term( $primary_id, 'category' );
	}

	// Fall back to the first assigned category.
	if ( empty( $primary_id ) || is_wp_error( $term ) || ! $term ) {
		$terms = get_the_terms( $post_id, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$term = reset( $terms );
	}

	$name = esc_html( $term->name );

	// If "Link To: Term" is selected, wrap in an anchor tag.
	if ( ! empty( $options['link'] ) ) {
		$url = get_term_link( $term, 'category' );
		if ( ! is_wp_error( $url ) ) {
			return '<a href="' . esc_url( $url ) . '" rel="tag">' . $name . '</a>';
		}
	}

	return $name;
}
/**
 * Custom GenerateBlocks Dynamic Tag: Term Display
 *
 * A structured alternative to Term Meta that provides predefined display
 * options for any taxonomy term — no manual meta key needed.
 *
 * Options available via KEY:
 *   term_title   – the term name (e.g. "Property Investment India")
 *   term_desc    – the term description
 *   term_image   – URL of the category featured image (set via category-image.php)
 *   term_count   – number of posts in the term
 *   term_url     – URL of the term archive page
 *
 * Usage examples in GB blocks:
 *   {{term_display key:term_title}}                      current term name
 *   {{term_display id:33|tax:category|key:term_title}}   specific term name
 *   {{term_display id:33|tax:category|key:term_image}}   image URL
 *   {{term_display id:33|tax:category|key:term_url}}     archive URL
 *   {{term_display id:33|tax:category|key:term_count}}   post count
 *
 * TIP — To link an Image block to the term archive:
 *   Image → IMAGE field  → {{term_display key:term_image}}
 *   Image → LINK field   → {{term_display key:term_url}}
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register the dynamic tag ─────────────────────────────────────────────────

add_action( 'init', 'gpc_register_term_display_tag', 20 );
function gpc_register_term_display_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'term_display',
			'title'       => __( 'Term Display', 'gp-child' ),
			'description' => __( 'Display term data (name, description, image, post count, or archive URL) for a category or any taxonomy term.', 'gp-child' ),
			'type'        => 'term',         // Enables Taxonomy + Term dropdowns in the editor UI.
			'supports'    => array( 'source' ), // 'source' enables Current Term / specific term selector.
			'options'     => array(
				'key' => array(
					'type'    => 'select',
					'label'   => __( 'Display', 'gp-child' ),
					'default' => 'term_title',
					'options' => array(
						'term_title',
						'term_desc',
						'term_image',
						'term_count',
						'term_url',
					),
				),
			),
			'return'      => 'gpc_term_display_callback',
		)
	);
}

// ── Callback ─────────────────────────────────────────────────────────────────

/**
 * Returns the requested term data.
 *
 * @param array  $options  Parsed tag options (id, tax, key, source …).
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_term_display_callback( $options, $block, $instance ) {
	$key      = ! empty( $options['key'] ) ? sanitize_key( $options['key'] ) : 'term_title';
	$taxonomy = ! empty( $options['tax'] ) ? sanitize_text_field( $options['tax'] ) : 'category';

	// ── Resolve the term ─────────────────────────────────────────────────────
	$term = null;

	if ( ! empty( $options['id'] ) ) {
		// Explicit term ID passed via the editor.
		$term = get_term( absint( $options['id'] ), $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		// Try the current queried object (category/tag archive pages).
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			$term = $queried;
		}
	}

	if ( ! $term || is_wp_error( $term ) ) {
		// Inside a post loop — fall back to the Yoast primary category.
		$post_id = get_the_ID();
		if ( $post_id ) {
			$primary = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
			if ( $primary ) {
				$term = get_term( $primary, 'category' );
			}
			if ( ! $term || is_wp_error( $term ) ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$term = reset( $terms );
				}
			}
		}
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	// ── Return the requested data ─────────────────────────────────────────────
	switch ( $key ) {

		case 'term_title':
			return esc_html( $term->name );

		case 'term_desc':
			return wp_kses_post( $term->description );

		case 'term_count':
			return (string) absint( $term->count );

		case 'term_url':
			$url = get_term_link( $term, $taxonomy );
			return is_wp_error( $url ) ? '' : esc_url( $url );

		case 'term_image':
			$attachment_id = (int) get_term_meta( $term->term_id, '_category_image_id', true );
			if ( ! $attachment_id ) {
				return '';
			}
			$size = ! empty( $options['size'] ) ? sanitize_text_field( $options['size'] ) : 'full';
			$url  = wp_get_attachment_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';

		default:
			return '';
	}
}


/**
 * Category Featured Image
 *
 * - Adds an image upload field on the Category add/edit screens.
 * - Saves the attachment ID in term meta `_category_image_id`.
 * - Registers a GenerateBlocks dynamic tag `{{term_image}}` that returns
 *   the image URL (or id / alt) for the current or specified term.
 *
 * Usage in a GB Image / Media block dynamic tag:
 *   {{term_image}}                   – full-size image URL of current term
 *   {{term_image id:12}}             – image URL for category with term_id 12
 *   {{term_image size:medium}}       – specific registered image size
 *   {{term_image key:id}}            – returns the attachment post ID instead
 *   {{term_image key:alt}}           – returns the alt text
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. REGISTER TERM META ────────────────────────────────────────────────────

add_action( 'init', 'gpc_register_category_image_meta' );
function gpc_register_category_image_meta() {
	register_term_meta(
		'category',
		'_category_image_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		)
	);
}

// ── 2. ADMIN: ENQUEUE MEDIA UPLOADER ON TAXONOMY SCREENS ────────────────────

add_action( 'admin_enqueue_scripts', 'gpc_category_image_admin_scripts' );
function gpc_category_image_admin_scripts( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	if ( isset( $_GET['taxonomy'] ) && 'category' !== $_GET['taxonomy'] ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script(
		'jquery',
		"(function($){
			$(document).on('click', '.gpc-upload-cat-image', function(e){
				e.preventDefault();
				var btn     = $(this);
				var wrapper = btn.closest('.gpc-cat-image-wrap');
				var frame   = wp.media({
					title:    'Select Category Image',
					button:   { text: 'Use this image' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					wrapper.find('.gpc-cat-image-id').val(att.id);
					wrapper.find('.gpc-cat-preview').html(
						'<img src=\"' + att.url + '\" style=\"max-width:200px;margin-top:8px;\">' +
						'<br><a href=\"#\" class=\"gpc-remove-cat-image\" style=\"color:red;\">Remove image</a>'
					);
				});
				frame.open();
			});
			$(document).on('click', '.gpc-remove-cat-image', function(e){
				e.preventDefault();
				var wrapper = $(this).closest('.gpc-cat-image-wrap');
				wrapper.find('.gpc-cat-image-id').val('');
				wrapper.find('.gpc-cat-preview').html('');
			});
		})(jQuery);"
	);
}

// ── 3. ADMIN: ADD FIELD TO CATEGORY ADD FORM ────────────────────────────────

add_action( 'category_add_form_fields', 'gpc_category_image_add_field' );
function gpc_category_image_add_field() {
	?>
	<div class="form-field term-image-wrap">
		<label for="gpc-category-image"><?php esc_html_e( 'Category Image', 'gp-child' ); ?></label>
		<div class="gpc-cat-image-wrap">
			<input type="hidden" name="gpc_category_image_id" class="gpc-cat-image-id" value="">
			<button type="button" class="button gpc-upload-cat-image"><?php esc_html_e( 'Upload / Select Image', 'gp-child' ); ?></button>
			<div class="gpc-cat-preview"></div>
		</div>
		<p><?php esc_html_e( 'Upload or select a featured image for this category.', 'gp-child' ); ?></p>
	</div>
	<?php
}

// ── 4. ADMIN: ADD FIELD TO CATEGORY EDIT FORM ───────────────────────────────

add_action( 'category_edit_form_fields', 'gpc_category_image_edit_field' );
function gpc_category_image_edit_field( $term ) {
	$image_id  = (int) get_term_meta( $term->term_id, '_category_image_id', true );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<tr class="form-field term-image-wrap">
		<th scope="row"><label for="gpc-category-image"><?php esc_html_e( 'Category Image', 'gp-child' ); ?></label></th>
		<td>
			<div class="gpc-cat-image-wrap">
				<input type="hidden" name="gpc_category_image_id" class="gpc-cat-image-id" value="<?php echo esc_attr( $image_id ?: '' ); ?>">
				<button type="button" class="button gpc-upload-cat-image"><?php esc_html_e( 'Upload / Select Image', 'gp-child' ); ?></button>
				<div class="gpc-cat-preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:200px;margin-top:8px;">
						<br><a href="#" class="gpc-remove-cat-image" style="color:red;"><?php esc_html_e( 'Remove image', 'gp-child' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Upload or select a featured image for this category.', 'gp-child' ); ?></p>
		</td>
	</tr>
	<?php
}

// ── 5. SAVE META ON CREATE & EDIT ────────────────────────────────────────────

add_action( 'created_category', 'gpc_save_category_image' );
add_action( 'edited_category', 'gpc_save_category_image' );
function gpc_save_category_image( $term_id ) {
	if ( ! isset( $_POST['gpc_category_image_id'] ) ) {
		return;
	}
	$image_id = absint( $_POST['gpc_category_image_id'] );
	if ( $image_id ) {
		update_term_meta( $term_id, '_category_image_id', $image_id );
	} else {
		delete_term_meta( $term_id, '_category_image_id' );
	}
}

// ── 7. CATEGORY LIST TABLE: ADD IMAGE THUMBNAIL COLUMN ──────────────────────

add_filter( 'manage_edit-category_columns', 'gpc_category_image_column' );
function gpc_category_image_column( $columns ) {
	// Insert after the checkbox column (before Name).
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'name' === $key ) {
			$new['category_image'] = __( 'Image', 'gp-child' );
		}
		$new[ $key ] = $label;
	}
	return $new;
}

add_filter( 'manage_category_custom_column', 'gpc_category_image_column_content', 10, 3 );
function gpc_category_image_column_content( $content, $column_name, $term_id ) {
	if ( 'category_image' !== $column_name ) {
		return $content;
	}
	$image_id = (int) get_term_meta( $term_id, '_category_image_id', true );
	if ( $image_id ) {
		$url = wp_get_attachment_image_url( $image_id, array( 50, 50 ) );
		if ( $url ) {
			return '<img src="' . esc_url( $url ) . '" width="50" height="50" style="object-fit:cover;border-radius:4px;">';
		}
	}
	return '<span style="color:#aaa;">—</span>';
}

add_action( 'admin_head', 'gpc_category_image_column_css' );
function gpc_category_image_column_css() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-category' !== $screen->id ) {
		return;
	}
	echo '<style>.column-category_image { width: 60px; text-align: center; }</style>';
}

add_action( 'init', 'gpc_register_term_image_tag', 20 );
function gpc_register_term_image_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'term_image',
			'title'       => __( 'Term / Category Image', 'gp-child' ),
			'description' => __( 'Get the featured image for a category or taxonomy term. Use id: to specify a term, size: for image size, key: for url/id/alt.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'return'      => 'gpc_term_image_callback',
		)
	);
}

/**
 * Dynamic tag callback — returns the category image URL (or id/alt).
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_term_image_callback( $options, $block, $instance ) {

	// Get term_id: explicit option > current queried object > primary category of current post.
	if ( ! empty( $options['id'] ) ) {
		$term_id = absint( $options['id'] );
	} else {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			$term_id = $queried->term_id;
		} else {
			// Inside a post loop — use Yoast primary or first category.
			$post_id     = get_the_ID();
			$primary_raw = get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
			if ( $primary_raw ) {
				$term_id = (int) $primary_raw;
			} else {
				$terms = get_the_terms( $post_id, 'category' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return '';
				}
				$term_id = reset( $terms )->term_id;
			}
		}
	}

	if ( empty( $term_id ) ) {
		return '';
	}

	$attachment_id = (int) get_term_meta( $term_id, '_category_image_id', true );

	if ( ! $attachment_id ) {
		return '';
	}

	$key  = ! empty( $options['key'] ) ? sanitize_text_field( $options['key'] ) : 'url';
	$size = ! empty( $options['size'] ) ? sanitize_text_field( $options['size'] ) : 'full';

	switch ( $key ) {
		case 'id':
			return (string) $attachment_id;

		case 'alt':
			return esc_attr( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		case 'url':
		default:
			$url = wp_get_attachment_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';
	}
}

/**
 * Featured Post Functionality
 *
 * - Registers _is_featured post meta (REST-enabled for block editor).
 * - Adds 'Featured Post' toggle panel in the block-editor sidebar via JS.
 * - Injects a 'Featured posts' select param into GenerateBlocks Query blocks.
 * - Filters generateblocks_query_loop_args to apply meta_query on the server.
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. REGISTER POST META

add_action( 'init', 'gpc_register_featured_meta' );
function gpc_register_featured_meta() {
	register_post_meta(
		'post',
		'_is_featured',
		array(
			'type'          => 'boolean',
			'single'        => true,
			'default'       => false,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

// 2. ENQUEUE BLOCK-EDITOR SCRIPT
// Must depend on 'generateblocks' so our addFilter calls run after GB
// has registered its own filter hooks.

add_action( 'enqueue_block_editor_assets', 'gpc_enqueue_featured_editor_assets' );
function gpc_enqueue_featured_editor_assets() {
	wp_enqueue_script(
		'gpc-featured-post',
		plugin_dir_url( __FILE__ ) . 'assets/js/featured-post.js',
		array(
			'generateblocks',
			'wp-hooks',
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-i18n',
		),
		filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/featured-post.js' ),
		true
	);
}

// 3. PHP: APPLY featuredPosts QUERY PARAMETER

add_filter( 'generateblocks_query_loop_args', 'gpc_apply_featured_query', 10, 2 );
function gpc_apply_featured_query( $query_args, $attributes ) {

	$value = '';

	if ( isset( $query_args['featuredPosts'] ) ) {
		$value = sanitize_text_field( $query_args['featuredPosts'] );
		unset( $query_args['featuredPosts'] );
	}

	if ( empty( $value ) ) {
		return $query_args;
	}

	if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
		$query_args['meta_query'] = array();
	}

	if ( 'only' === $value ) {
		$query_args['meta_query'][] = array(
			'key'     => '_is_featured',
			'value'   => '1',
			'compare' => '=',
		);
	} elseif ( 'exclude' === $value ) {
		$query_args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_is_featured',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_is_featured',
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	return $query_args;
}

// 4. PHP: APPLY popularPosts QUERY PARAMETER
//
// PVC already hooks posts_join and posts_orderby for orderby:'post_views'.
// We just need to set orderby and ensure suppress_filters is false.

add_filter( 'generateblocks_query_loop_args', 'gpc_apply_popular_query', 10, 2 );
function gpc_apply_popular_query( $query_args, $attributes ) {

	if ( empty( $query_args['popularPosts'] ) ) {
		return $query_args;
	}

	unset( $query_args['popularPosts'] );

	$query_args['orderby']           = 'post_views';
	$query_args['order']             = 'DESC';
	$query_args['suppress_filters']  = false;

	return $query_args;
}
