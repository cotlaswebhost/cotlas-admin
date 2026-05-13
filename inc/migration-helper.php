<?php
/**
 * Cotlas_Migration_Helper: post-migration cache clearing and URL replacement.
 * Hooks into WP Migrate DB Pro to regenerate GenerateBlocks/GeneratePress/Elementor
 * CSS caches and fix stale domain references in CSS files.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

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
