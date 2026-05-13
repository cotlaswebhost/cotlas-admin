<?php
/**
 * Image optimisation: custom sizes, srcset pruning, aspect-ratio helpers,
 * LCP lazy-load override, critical CSS, and AVIF preload with srcset.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_image_optimization_enabled' ) ) { return; }

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
