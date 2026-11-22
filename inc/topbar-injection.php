<?php
/**
 * Inject the date/time entry and social icons into GeneratePress's secondary
 * navigation (top bar).
 *
 * Both are controlled from Cotlas Admin > Social Media > Social Links.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize the top bar social networks field.
 *
 * @param string $value Raw field value.
 * @return string Comma-separated, deduplicated, whitelisted network slugs.
 */
function cotlas_sanitize_topbar_social_networks( $value ) {
    $allowed = array( 'facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'threads', 'whatsapp' );

    $value = is_string( $value ) ? strtolower( $value ) : '';
    $parts = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

    if ( ! is_array( $parts ) ) {
        return '';
    }

    $parts = array_map(
        function ( $network ) {
            return 'x' === $network ? 'twitter' : $network;
        },
        $parts
    );

    $parts = array_values( array_unique( array_intersect( $parts, $allowed ) ) );

    return implode( ',', $parts );
}

/**
 * Sanitize a datetime display/format select.
 *
 * @param string $value    Raw value.
 * @param array  $allowed  Allowed values.
 * @param string $fallback Value used when the input is not allowed.
 * @return string
 */
function cotlas_sanitize_topbar_datetime_choice( $value, array $allowed, $fallback ) {
    $value = is_string( $value ) ? $value : '';

    return in_array( $value, $allowed, true ) ? $value : $fallback;
}

/**
 * Render the date/time entry for the secondary navigation.
 *
 * @return string
 */
function cotlas_get_topbar_datetime_html() {
    if ( ! get_option( 'cotlas_social_enabled', 1 ) || ! get_option( 'cotlas_topbar_datetime_inject' ) ) {
        return '';
    }

    if ( ! shortcode_exists( 'local_datetime' ) ) {
        return '';
    }

    $display     = get_option( 'cotlas_topbar_datetime_display', 'both' );
    $date_format = get_option( 'cotlas_topbar_datetime_date_format', 'medium' );
    $time_format = get_option( 'cotlas_topbar_datetime_time_format', '12' );

    $shortcode = sprintf(
        '[local_datetime date_format="%1$s" time_format="%2$s" display="%3$s"]',
        esc_attr( $date_format ),
        esc_attr( $time_format ),
        esc_attr( $display )
    );

    return '<span class="cws-date-time">' . do_shortcode( $shortcode ) . '</span>';
}

/**
 * Render the social icons block for the secondary navigation.
 *
 * @return string
 */
function cotlas_get_topbar_social_html() {
    if ( ! get_option( 'cotlas_topbar_social_inject' ) ) {
        return '';
    }

    if ( ! get_option( 'cotlas_social_enabled', 1 ) || ! shortcode_exists( 'cotlas_social' ) ) {
        return '';
    }

    $networks = cotlas_sanitize_topbar_social_networks(
        get_option( 'cotlas_topbar_social_networks', 'facebook,twitter,instagram,youtube' )
    );

    if ( '' === $networks ) {
        return '';
    }

    $html = do_shortcode( sprintf( '[cotlas_social networks="%s"]', esc_attr( $networks ) ) );

    if ( '' === trim( $html ) ) {
        return '';
    }

    return '<div class="cws-top-social">' . $html . '</div>';
}

add_action( 'generate_inside_secondary_navigation', 'cotlas_render_secondary_navigation_extras', 10 );
/**
 * Output the date/time and social entries inside the secondary navigation.
 */
function cotlas_render_secondary_navigation_extras() {
    if ( ! cotlas_is_generatepress_active() ) {
        return;
    }

    echo cotlas_get_topbar_datetime_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this plugin.
    echo cotlas_get_topbar_social_html();   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this plugin.
}
