<?php
/**
 * Frontend shortcodes and utility functions.
 *
 * Covers: navigation, category display, search, login/logout, image sizes,
 *         post marquee, local datetime, and misc filters/actions.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// GENERIC UTILITY FUNCTIONS
// (moved from cotlas-news/custom-mods.php)
// ============================================================

add_shortcode( 'gp_nav', 'tct_gp_nav' );
function tct_gp_nav( $atts ) {
    $atts = shortcode_atts( array(
        'class' => '',
    ), $atts, 'gp_nav' );

    $wrapper_class = 'gp-nav-shortcode';
    if ( ! empty( $atts['class'] ) ) {
        $wrapper_class .= ' ' . esc_attr( $atts['class'] );
    }

    ob_start();
    echo '<div class="' . $wrapper_class . '">';
    generate_navigation_position();
    echo '</div>';
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

// Remove the default GeneratePress comments template
function remove_generatepress_default_comments() {
    remove_action( 'generate_after_do_template_part', 'generate_do_comments_template', 15 );
}
add_action( 'wp_loaded', 'remove_generatepress_default_comments' );




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

