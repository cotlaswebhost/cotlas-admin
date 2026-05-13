<?php
/**
 * Custom Auth – Redirects
 *
 * Handles:
 *  1. Redirecting wp-login.php to the custom login page (allows reset/logout flows through).
 *  2. Redirecting /wp-admin/ access by unauthenticated users.
 *  3. Redirecting logged-in users away from the login/register pages.
 *  4. Role-based post-login redirect (login_redirect filter as safety-net for default WP login).
 *
 * @package Cotlas_Admin
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// 1. Redirect wp-login.php to custom login page
// ---------------------------------------------------------------------------
add_action( 'login_init', 'cotlas_auth_redirect_wp_login' );

function cotlas_auth_redirect_wp_login() {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return;
    }

    // Allow specific action flows to continue through default wp-login.php:
    //   logout           – WP handles the logout nonce/cookie clearing
    //   lostpassword     – not needed since we have a custom form, but safe to keep
    //   rp / resetpass   – password-reset link from email MUST land on wp-login.php
    //   confirmaction    – GDPR personal data export/erase confirmations
    //   postpass         – password-protected posts
    $allowed_actions = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction', 'postpass' ];

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'login'; // phpcs:ignore

    if ( in_array( $action, $allowed_actions, true ) ) {
        return;
    }

    // Also allow reset key links that contain a ?key= parameter (WP 6.x generates these)
    if ( ! empty( $_GET['key'] ) ) { // phpcs:ignore
        return;
    }

    $login_slug = get_option( 'cotlas_auth_login_slug' ) ?: 'login';
    $login_url  = home_url( '/' . $login_slug . '/' );

    // Forward any redirect_to parameter so the custom form can honour it
    if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore
        $login_url = add_query_arg(
            'redirect_to',
            rawurlencode( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ), // phpcs:ignore
            $login_url
        );
    }

    wp_safe_redirect( $login_url, 302 );
    exit;
}

// ---------------------------------------------------------------------------
// 2. Redirect unauthenticated /wp-admin/ visits to custom login page
//    (WP's own auth_redirect() would bounce the user to wp-login.php first;
//    this catches it one step earlier during init.)
// ---------------------------------------------------------------------------
add_action( 'init', 'cotlas_auth_redirect_admin_access', 1 );

function cotlas_auth_redirect_admin_access() {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return;
    }

    // Only act on admin-side requests, and never on AJAX or REST
    if ( ! is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( is_user_logged_in() ) {
        return;
    }

    $login_slug = get_option( 'cotlas_auth_login_slug' ) ?: 'login';
    $login_url  = home_url( '/' . $login_slug . '/' );
    $login_url  = add_query_arg( 'redirect_to', rawurlencode( admin_url() ), $login_url );

    wp_safe_redirect( $login_url, 302 );
    exit;
}

// ---------------------------------------------------------------------------
// 3. Redirect already-logged-in users away from login/register pages
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'cotlas_auth_redirect_logged_in_from_auth_pages' );

function cotlas_auth_redirect_logged_in_from_auth_pages() {
    if ( ! get_option( 'cotlas_auth_enabled' ) || ! is_user_logged_in() ) {
        return;
    }

    $login_slug    = get_option( 'cotlas_auth_login_slug' )    ?: 'login';
    $register_slug = get_option( 'cotlas_auth_register_slug' ) ?: 'register';

    // is_page() accepts slugs
    if ( is_page( [ $login_slug, $register_slug ] ) ) {
        $user     = wp_get_current_user();
        $redirect = cotlas_auth_get_redirect_url( $user );
        wp_safe_redirect( esc_url_raw( $redirect ), 302 );
        exit;
    }
}

// ---------------------------------------------------------------------------
// 4. Role-based redirect helper (used by AJAX handler and login_redirect filter)
// ---------------------------------------------------------------------------

/**
 * Resolve the post-login redirect URL for a user.
 *
 * Priority:
 *  1. Shortcode / form `redirect` attribute (if it validates as a local URL)
 *  2. Role-based setting from the admin settings page
 *
 * Privileged roles (administrator, editor, author) → cotlas_auth_redirect_privileged
 * All other roles                                  → cotlas_auth_redirect_default
 *
 * @param WP_User $user
 * @param string  $requested_redirect  Optional URL passed from the form.
 * @return string Absolute URL.
 */
function cotlas_auth_get_redirect_url( WP_User $user, $requested_redirect = '' ) {
    $privileged_roles = [ 'administrator', 'editor', 'author' ];
    $is_privileged    = ! empty( array_intersect( (array) $user->roles, $privileged_roles ) );

    // Honour a form-supplied redirect only if it points to the same host (prevents open redirect)
    if ( $requested_redirect ) {
        $validated = wp_validate_redirect( $requested_redirect, '' );
        if ( $validated ) {
            return $validated;
        }
    }

    if ( $is_privileged ) {
        $url = get_option( 'cotlas_auth_redirect_privileged', '' );
        return $url ? $url : admin_url();
    }

    $url = get_option( 'cotlas_auth_redirect_default', '' );
    return $url ? $url : home_url();
}

// ---------------------------------------------------------------------------
// 5. login_redirect filter – safety-net for users who reach default WP login
// ---------------------------------------------------------------------------
add_filter( 'login_redirect', 'cotlas_auth_login_redirect_filter', 10, 3 );

function cotlas_auth_login_redirect_filter( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return $redirect_to;
    }
    if ( ! $user instanceof WP_User ) {
        return $redirect_to;
    }
    return cotlas_auth_get_redirect_url( $user, $requested_redirect_to );
}
