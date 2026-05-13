<?php
/**
 * Admin dashboard widgets, starter-kit installer, feed and ad widgets.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

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

/**
 * cotlas_remove_dashboard_widgets.
 */
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

/**
 * cotlas_is_default_site_setup_user.
 */
function cotlas_is_default_site_setup_user() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return false;
    }

    $current_user = wp_get_current_user();

    return $current_user instanceof WP_User && 'cotlasweb' === $current_user->user_login;
}

/**
 * cotlas_render_dashboard_welcome_notice.
 */
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

/**
 * cotlas_dismiss_dashboard_welcome_notice.
 */
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

/**
 * cotlas_register_default_site_setup_menu.
 */
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

/**
 * cotlas_render_default_site_setup_notice.
 */
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

/**
 * cotlas_dismiss_default_site_setup_notice.
 */
function cotlas_dismiss_default_site_setup_notice() {
    check_ajax_referer('cotlas_default_site_setup_notice', 'nonce');

    if (!cotlas_is_default_site_setup_user()) {
        wp_send_json_error(array('message' => __('You are not allowed to dismiss this notice.', 'cotlas-news')), 403);
    }

    update_option('cotlas_default_site_setup_notice_dismissed', 1, false);

    wp_send_json_success();
}

/**
 * cotlas_render_default_site_setup_page.
 */
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

            /**
             * renderPreview.
             */
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

            /**
             * renderAsset.
             */
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

            /**
             * renderResult.
             */
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

            /**
             * getAssetByIdentity.
             */
            function getAssetByIdentity(siteSetKey, assetType, assetIndex) {
                var selectedAssets = siteSetAssets[siteSetKey] || [];

                return selectedAssets.find(function(asset) {
                    return asset.type === assetType && Number(asset.index) === Number(assetIndex);
                }) || null;
            }

            /**
             * refreshSiteSetAssets.
             */
            function refreshSiteSetAssets(siteSetKey, assets) {
                if (!Array.isArray(assets)) {
                    return;
                }

                siteSetAssets[siteSetKey] = assets;
                renderPreview();
            }

            /**
             * activateSingleAsset.
             */
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

            /**
             * installSingleAsset.
             */
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

/**
 * cotlas_install_default_site_set.
 */
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

/**
 * cotlas_activate_default_site_asset.
 */
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

/**
 * cotlas_get_default_site_set_assets.
 */
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

/**
 * cotlas_get_default_site_asset_state.
 */
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

/**
 * cotlas_install_default_site_asset.
 */
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

/**
 * cotlas_is_theme_installed.
 */
function cotlas_is_theme_installed($slug) {
    return wp_get_theme($slug)->exists();
}

/**
 * cotlas_is_theme_active.
 */
function cotlas_is_theme_active($slug) {
    if (!cotlas_is_theme_installed($slug)) {
        return false;
    }

    $current_theme = wp_get_theme();

    return $current_theme->get_stylesheet() === $slug || $current_theme->get_template() === $slug;
}

/**
 * cotlas_is_plugin_installed.
 */
function cotlas_is_plugin_installed($slug) {
    return (bool) cotlas_get_plugin_file_by_slug($slug);
}

/**
 * cotlas_is_plugin_active_by_slug.
 */
function cotlas_is_plugin_active_by_slug($slug) {
    $plugin_file = cotlas_get_plugin_file_by_slug($slug);

    return $plugin_file ? is_plugin_active($plugin_file) : false;
}

/**
 * cotlas_get_plugin_file_by_slug.
 */
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

/**
 * cotlas_activate_default_site_asset_item.
 */
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

/**
 * cotlas_register_dashboard_ad_widget.
 */
function cotlas_register_dashboard_ad_widget() {
    wp_add_dashboard_widget(
        'cotlas_dashboard_ad_widget',
        __('We create your online presence', 'cotlas-news'),
        'cotlas_render_dashboard_ad_widget'
    );
}

/**
 * cotlas_render_dashboard_ad_widget.
 */
function cotlas_render_dashboard_ad_widget() {
    $banner_link = 'https://cotlas.net';
    $banner_images = array(
        plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/website-designing-banner.webp',
        plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/website-designing-banner.webp',
        plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/website-designing-banner.webp',
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

/**
 * cotlas_register_dashboard_feed_widget.
 */
function cotlas_register_dashboard_feed_widget() {
    wp_add_dashboard_widget(
        'cotlas_dashboard_feed_widget',
        __('Latest Articles', 'cotlas-news'),
        'cotlas_render_dashboard_feed_widget'
    );
}

/**
 * cotlas_move_dashboard_feed_widget_to_side.
 */
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

/**
 * cotlas_render_dashboard_feed_widget.
 */
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

/**
 * Fetches and returns a parsed array of items from an RSS/Atom feed URL.
 * Used to populate the Cotlas news dashboard widget with recent articles.
 *
 * @param string $feed_url  The full URL of the RSS/Atom feed to fetch.
 * @param int    $limit     Maximum number of items to return (default 3).
 * @return array            Array of associative arrays with title, link, author, date, image keys.
 */
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

/**
 * Extracts the best available image URL from a feed item.
 * Tries enclosure, media:content, media:thumbnail, then inline <img> tags
 * in the item content and description as a last resort.
 *
 * @param SimplePie_Item $feed_item  The feed item object.
 * @return string  Image URL or empty string if none found.
 */
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
