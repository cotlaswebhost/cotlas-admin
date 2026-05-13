<?php
/**
 * Cotlas_Tracking_Codes class: site settings, company info/social shortcodes, tracking injection.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

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
        // Menu moved to admin-panel.php (Cotlas Admin → Site Settings / Tracking Codes)
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

// Instantiate the class once all plugins are loaded.
add_action( 'plugins_loaded', function() {
    new Cotlas_Tracking_Codes();
} );
