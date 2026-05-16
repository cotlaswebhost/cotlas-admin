<?php
/**
 * Admin asset enqueueing, body class, honeypot script, user contact methods.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue mu-plugins admin styles and scripts from local files
 */
function cotlas_admin_scripts() {
    // Get the mu-plugins directory URL
    $mu_plugins_url = plugin_dir_url( dirname(__FILE__) );
    $mu_plugins_dir = plugin_dir_path( dirname(__FILE__) );
    
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
    $file = plugin_dir_path( dirname(__FILE__) ) . 'assets/css/frontend.css';
    wp_enqueue_style(
        'cotlas-frontend',
        plugin_dir_url( dirname(__FILE__) ) . 'assets/css/frontend.css',
        array(),
        file_exists($file) ? (string) filemtime($file) : '1.0.0'
    );

    $delete_confirm_file = plugin_dir_path( dirname(__FILE__) ) . 'assets/js/delete-post-confirm.js';
    if ( file_exists( $delete_confirm_file ) ) {
        wp_enqueue_script(
            'cotlas-delete-post-confirm',
            plugin_dir_url( dirname(__FILE__) ) . 'assets/js/delete-post-confirm.js',
            array(),
            (string) filemtime( $delete_confirm_file ),
            true
        );
    }
});

/**
 * cotlas_admin_body_class.
 */
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
        $mu_plugins_dir = plugin_dir_path( dirname(__FILE__) );
        $local_js_path = $mu_plugins_dir . 'assets/js/security.js';
        
        if (file_exists($local_js_path)) {
            // Use local file if it exists
            $security_js_url = plugin_dir_url( dirname(__FILE__) ) . 'assets/js/security.js';
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

