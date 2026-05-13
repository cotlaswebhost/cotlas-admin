<?php
/**
 * Custom Auth – AJAX Handlers
 *
 * All handlers are nopriv (unauthenticated users).
 * Each handler runs the full protection stack:
 *   1. Nonce verification    (CSRF)
 *   2. Honeypot check        (if enabled)
 *   3. IP rate-limit         (transient-based, Cloudflare-aware)
 *   4. Cloudflare Turnstile  (if enabled + keys configured)
 *   5. Business logic        (wp_signon / register_new_user / retrieve_password)
 *
 * All success/error responses are JSON so the JS handler can update the UI
 * without a full page reload (ideal for overlay/modal use cases).
 *
 * @package Cotlas_Admin
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Resolve the real client IP, preferring the Cloudflare header when present.
 *
 * Note: HTTP_CF_CONNECTING_IP is set by Cloudflare's edge and cannot be
 * spoofed by the client (Cloudflare strips any client-supplied header of
 * the same name). REMOTE_ADDR is used as a reliable fallback.
 *
 * @return string
 */
function cotlas_auth_get_client_ip() {
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }

    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Rate-limit check: returns true if the request is within limits, false if exceeded.
 *
 * Uses a WP transient per (action, IP) pair.
 * Window: 10 minutes.  Threshold: cotlas_auth_rate_limit option (default 5).
 *
 * @param string $action  Logical name, e.g. 'login', 'register', 'forgot'.
 * @return bool
 */
function cotlas_auth_check_rate_limit( $action ) {
    $limit = max( 1, (int) get_option( 'cotlas_auth_rate_limit', 5 ) );
    $ip    = cotlas_auth_get_client_ip();
    $key   = 'cotlas_rl_' . md5( $action . $ip );
    $count = (int) get_transient( $key );

    if ( $count >= $limit ) {
        return false;
    }

    set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
    return true;
}

/**
 * Shared guard: nonce + honeypot + rate-limit + optional Turnstile.
 * Calls wp_send_json_error and exits on failure.
 *
 * @param string $action            Rate-limit action key.
 * @param bool   $check_turnstile   Whether to verify the Turnstile token.
 */
function cotlas_auth_run_guards( $action, $check_turnstile = false ) {
    // 1. Nonce
    $nonce = isset( $_POST['cotlas_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_nonce'] ) ) : ''; // phpcs:ignore
    if ( ! wp_verify_nonce( $nonce, 'cotlas_auth_nonce' ) ) {
        wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'cotlas-admin' ) ] );
    }

    // 2. Honeypot
    if ( get_option( 'cotlas_auth_honeypot', 1 ) ) {
        $hp = isset( $_POST['cc-city'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['cc-city'] ) ) ) : ''; // phpcs:ignore
        if ( $hp !== '' ) {
            // Silently succeed from the bot's perspective (do not reveal the mechanism)
            wp_send_json_success( [ 'message' => __( 'Done.', 'cotlas-admin' ) ] );
        }
    }

    // 3. Rate limit
    if ( ! cotlas_auth_check_rate_limit( $action ) ) {
        wp_send_json_error( [ 'message' => __( 'Too many attempts. Please wait 10 minutes and try again.', 'cotlas-admin' ) ] );
    }

    // 4. Cloudflare Turnstile
    if ( $check_turnstile && get_option( 'turnstile_site_key' ) ) {
        $result = cotlas_verify_turnstile(); // defined in the main plugin file
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => wp_strip_all_tags( $result->get_error_message() ) ] );
        }
    }
}

// ---------------------------------------------------------------------------
// AJAX: Login  (wp_ajax_nopriv_cotlas_login)
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_nopriv_cotlas_login', 'cotlas_ajax_login' );

function cotlas_ajax_login() {
    $use_turnstile = (bool) get_option( 'cotlas_auth_turnstile_login' );
    cotlas_auth_run_guards( 'login', $use_turnstile );

    $username = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : ''; // phpcs:ignore
    // Passwords may contain any character; wp_unslash is sufficient before passing to wp_signon.
    $password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore
    $remember = ! empty( $_POST['rememberme'] ); // phpcs:ignore

    if ( $username === '' || $password === '' ) {
        wp_send_json_error( [ 'message' => __( 'Please enter your username / email and password.', 'cotlas-admin' ) ] );
    }

    $user = wp_signon(
        [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ],
        is_ssl()
    );

    if ( is_wp_error( $user ) ) {
        // Return a generic message to prevent username enumeration
        wp_send_json_error( [ 'message' => __( 'Incorrect username or password.', 'cotlas-admin' ) ] );
    }

    $requested  = isset( $_POST['cotlas_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['cotlas_redirect'] ) ) : ''; // phpcs:ignore
    $redirect   = cotlas_auth_get_redirect_url( $user, $requested );

    wp_send_json_success( [ 'redirect' => $redirect ] );
}

// ---------------------------------------------------------------------------
// AJAX: Register  (wp_ajax_nopriv_cotlas_register)
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_nopriv_cotlas_register', 'cotlas_ajax_register' );

function cotlas_ajax_register() {
    if ( ! get_option( 'users_can_register' ) ) {
        wp_send_json_error( [ 'message' => __( 'User registration is currently disabled.', 'cotlas-admin' ) ] );
    }

    $use_turnstile = (bool) get_option( 'cotlas_auth_turnstile_register' );
    cotlas_auth_run_guards( 'register', $use_turnstile );

    $username = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : ''; // phpcs:ignore
    $email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ''; // phpcs:ignore

    if ( $username === '' || $email === '' ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'cotlas-admin' ) ] );
    }

    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'cotlas-admin' ) ] );
    }

    // Reuse the existing email-domain whitelist from the main plugin
    $valid_domains = [ 'gmail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk', 'outlook.com', 'hotmail.com', 'live.com' ];
    $email_domain  = strtolower( substr( $email, strrpos( $email, '@' ) + 1 ) );
    if ( ! in_array( $email_domain, $valid_domains, true ) ) {
        wp_send_json_error( [
            'message' => __( 'Please register using a Gmail, Yahoo, Outlook, Hotmail, or Live email address.', 'cotlas-admin' ),
        ] );
    }

    // register_new_user() handles duplicate username/email checks and sends the welcome email
    $result = register_new_user( $username, $email );

    if ( is_wp_error( $result ) ) {
        $messages = $result->get_error_messages();
        wp_send_json_error( [ 'message' => wp_strip_all_tags( implode( ' ', $messages ) ) ] );
    }

    // On success, send back a message – the form will show it and not redirect
    wp_send_json_success( [
        'message' => __( 'Account created! Check your email for your login details.', 'cotlas-admin' ),
    ] );
}

// ---------------------------------------------------------------------------
// AJAX: Forgot Password  (wp_ajax_nopriv_cotlas_forgot_password)
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_nopriv_cotlas_forgot_password', 'cotlas_ajax_forgot_password' );

function cotlas_ajax_forgot_password() {
    cotlas_auth_run_guards( 'forgot', false ); // Turnstile not required on this form

    $user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : ''; // phpcs:ignore

    if ( $user_login === '' ) {
        wp_send_json_error( [ 'message' => __( 'Please enter your username or email address.', 'cotlas-admin' ) ] );
    }

    // Look up the user regardless of whether they entered username or email.
    // We do NOT reveal whether the account exists (prevents user enumeration).
    $user = false;
    if ( strpos( $user_login, '@' ) !== false ) {
        $user = get_user_by( 'email', $user_login );
    }
    if ( ! $user ) {
        $user = get_user_by( 'login', $user_login );
    }

    $generic_success = [ 'message' => __( 'If an account exists for that username or email, a password reset link has been sent.', 'cotlas-admin' ) ];

    if ( ! $user ) {
        // Return success to prevent enumeration
        wp_send_json_success( $generic_success );
    }

    // retrieve_password() is available since WP 5.7; it generates a reset key and sends the email.
    // The reset link in the email will point to wp-login.php?action=rp which we allow through
    // in auth-redirects.php, so the standard WP reset flow works unchanged.
    if ( ! function_exists( 'retrieve_password' ) ) {
        // Fallback for WP < 5.7: generate key manually and send a simple email.
        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            wp_send_json_error( [ 'message' => __( 'Unable to generate a reset key. Please try again.', 'cotlas-admin' ) ] );
        }
        $reset_url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
        $blogname  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        /* translators: %s: site name */
        $subject   = sprintf( __( '[%s] Password Reset', 'cotlas-admin' ), $blogname );
        /* translators: 1: username, 2: reset URL */
        $message   = sprintf(
            __( "Username: %1\$s\n\nTo reset your password visit:\n%2\$s\n\nIf you did not request this, ignore this email.", 'cotlas-admin' ),
            $user->user_login,
            $reset_url
        );
        wp_mail( $user->user_email, $subject, $message );
    } else {
        $result = retrieve_password( $user->user_login );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => __( 'Unable to send the reset email. Please try again.', 'cotlas-admin' ) ] );
        }
    }

    wp_send_json_success( $generic_success );
}
