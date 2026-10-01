<?php
/**
 * Custom template loader for Cotlas Admin plugin.
 * Loads custom templates for pages assigned in the WordPress Admin.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;
/**
 * 1. Define all custom plugin templates in one scalable array map.
 *    Key: The actual template filename inside your /templates/ directory.
 *    Value: The display name shown in the WordPress Admin attributes dropdown.
 */
function cotlas_get_custom_templates() {
    return array(
        'auth-template.php'     => 'Authentication Page',
        //'dashboard-template.php'=> 'User Dashboard Template', // Future template example
        'blank-canvas.php'      => 'Blank Canvas', // Future template example
    );
}

/**
 * 2. Register all templates dynamically in the Page Attributes dropdown.
 */
function cotlas_register_page_templates( $templates ) {
    $plugin_templates = cotlas_get_custom_templates();
    
    // Merge plugin templates seamlessly with existing theme templates
    return array_merge( $templates, $plugin_templates );
}
add_filter( 'theme_page_templates', 'cotlas_register_page_templates' );

/**
 * 3. Dynamically route and load whichever custom template is assigned.
 */
function cotlas_load_page_templates( $template ) {
    global $post;

    // Early exit if not on a page view or if post metadata context is missing
    if ( ! $post ) {
        return $template;
    }

    // Get the assigned template file slug for the current page
    $assigned_template = get_post_meta( $post->ID, '_wp_page_template', true );
    
    // Fetch our authorized plugin template list
    $plugin_templates = cotlas_get_custom_templates();

    // Check if the current page's assigned template belongs to our plugin array keys
    if ( array_key_exists( $assigned_template, $plugin_templates ) ) {
        $plugin_template_path = plugin_dir_path( __FILE__ ) . 'templates/' . $assigned_template;
        
        // Safety check to ensure the file exists physically in the folder before routing
        if ( file_exists( $plugin_template_path ) ) {
            return $plugin_template_path;
        }
    }

    return $template;
}
add_filter( 'template_include', 'cotlas_load_page_templates' );
