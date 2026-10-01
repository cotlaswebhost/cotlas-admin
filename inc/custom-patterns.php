<?php
/**
 * Dynamic Block Pattern and Multi-Category Registration for Cotlas Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Define all custom pattern categories in one place.
 *    Key: The unique category slug.
 *    Value: The display label shown in the block inserter dropdown.
 */
function cotlas_get_pattern_categories() {
    return array(
        'cotlas-headers' => __( 'Cotlas Headers', 'cotlas-admin' ),
    );
}

/**
 * 2. Loop and register all custom categories in the block inserter.
 */
function cotlas_register_pattern_categories() {
    $categories = cotlas_get_pattern_categories();

    foreach ( $categories as $slug => $label ) {
        register_block_pattern_category(
            $slug,
            array( 'label' => $label )
        );
    }
}
add_action( 'init', 'cotlas_register_pattern_categories' );

/**
 * 3. Define your patterns and assign them to one or more categories.
 */
function cotlas_get_custom_patterns() {
    return array(
        'header-one-layout' => array(
            'title'       => __( 'Cotlas Auth Login Form', 'cotlas-admin' ),
            'description' => _x( 'Custom header layout using design blocks.', 'Block pattern description', 'cotlas-admin' ),
            'categories'  => array( 'cotlas-headers' ), // Assigned to your new auth category
        ),
    );
}

/**
 * 4. Loop and register all pattern files from the /inc/patterns/ directory.
 */
function cotlas_register_block_patterns() {
    $patterns = cotlas_get_custom_patterns();

    foreach ( $patterns as $slug => $args ) {
        // __DIR__ points directly to cotlas-admin/inc/
        $pattern_file = __DIR__ . '/patterns/' . $slug . '.php';

        if ( file_exists( $pattern_file ) ) {
            // Read the block code markup directly from the file
            $args['content'] = file_get_contents( $pattern_file );
            
            register_block_pattern( 'cotlas-admin/' . $slug, $args );
        }
    }
}
add_action( 'init', 'cotlas_register_block_patterns' );
