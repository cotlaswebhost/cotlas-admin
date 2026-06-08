<?php
/**
 * Cloudflare Turnstile CAPTCHA integration.
 *
 * Adds a "Site Security" admin menu where the site key, secret key, and
 * per-form toggles (login, register, comments) can be configured.
 * Handles enqueueing the Turnstile script and rendering/verifying the widget.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// Cloudflare Turnstile Settings

/**
 * Register the "Site Security" top-level admin menu page.
 */
// Menu registration moved to admin-panel.php

/**
 * Enqueue the Cloudflare Turnstile JS on login/register and frontend pages
 * only when at least one protection toggle is enabled.
 */
function cotlas_turnstile_script() {
    // Only enqueue if at least one feature is enabled
    $login_enabled    = 'turnstile' === cotlas_challenge_provider_for_form( 'wp_login' );
    $register_enabled = 'turnstile' === cotlas_challenge_provider_for_form( 'wp_register' );
    $comments_enabled = 'turnstile' === cotlas_challenge_provider_for_form( 'comments' ) && ! is_user_logged_in() && ! get_option('comment_registration');

    if ($login_enabled || $register_enabled || $comments_enabled) {
        wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
    }
}
add_action('login_enqueue_scripts', 'cotlas_turnstile_script');
add_action('wp_enqueue_scripts', 'cotlas_turnstile_script');

/**
 * Render the Turnstile widget div inside the appropriate form.
 * Checks the current filter to decide whether to render.
 */
function cotlas_display_turnstile() {
    $current_filter = current_filter();

    if ($current_filter === 'login_form') {
        cotlas_render_challenge_for_form( 'wp_login', 'wp_login' );
    } elseif ($current_filter === 'register_form') {
        cotlas_render_challenge_for_form( 'wp_register', 'wp_register' );
    } elseif ($current_filter === 'comment_form' && ! get_option('comment_registration') && ! is_user_logged_in()) {
        cotlas_render_challenge_for_form( 'comments', 'comments' );
    }
}
add_action('login_form', 'cotlas_display_turnstile');
add_action('register_form', 'cotlas_display_turnstile');
add_action('comment_form', 'cotlas_display_turnstile');

/**
 * Detect Cotlas custom auth AJAX requests.
 *
 * Custom login/register already verify the active challenge in auth-ajax.php.
 * Running default form hooks again during wp_signon/register_new_user causes
 * duplicate verification and action mismatches (wp_login vs cotlas_login).
 *
 * @return bool
 */
function cotlas_is_custom_auth_ajax_request() {
    if ( ! wp_doing_ajax() ) {
        return false;
    }

    $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    return in_array( $action, array( 'cotlas_login', 'cotlas_register', 'cotlas_forgot_password' ), true );
}

/**
 * Verify the Turnstile token by calling the Cloudflare siteverify endpoint.
 * Returns true on success or a WP_Error on failure/missing token.
 *
 * @return true|WP_Error
 */
function cotlas_verify_turnstile() {
    static $verification_result = null;

    if ( $verification_result !== null ) {
        return $verification_result;
    }

    // Check which context we are in to decide if verification is needed
    $need_verify = false;
    
    // We can't easily check the current hook here because this function is called from inside the hooks.
    // So we need to pass context or deduce it.
    // However, the caller functions know the context.
    
    // But let's check keys first.
    $secret_key = get_option('turnstile_secret_key');
    if (empty($secret_key)) {
        $verification_result = true;
        return $verification_result; // No key, no check (fail open to avoid lockout)
    }

    if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
         $verification_result = new WP_Error('turnstile_missing', __('<strong>ERROR</strong>: Please verify you are human.'));
         return $verification_result;
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $_POST['cf-turnstile-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);

    if (is_wp_error($response)) {
        $verification_result = new WP_Error('turnstile_error', __('<strong>ERROR</strong>: Unable to verify Turnstile.'));
        return $verification_result;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || !isset($data['success']) || !$data['success']) {
        $verification_result = new WP_Error('turnstile_invalid', __('<strong>ERROR</strong>: Turnstile verification failed.'));
        return $verification_result;
    }

    $verification_result = true;
    return $verification_result;
}

/** Verify Turnstile token on wp_authenticate_user (login). */
add_filter('wp_authenticate_user', function($user, $password) {
    if ( cotlas_is_custom_auth_ajax_request() ) return $user;

    // If feature disabled, skip check
    if (!cotlas_challenge_provider_for_form( 'wp_login' )) return $user;
    
    if (is_wp_error($user)) return $user;
    $check = cotlas_verify_challenge_for_form( 'wp_login', 'wp_login' );
    if (is_wp_error($check)) {
        return $check;
    }
    return $user;
}, 10, 2);

/** Verify Turnstile token on registration_errors. */
add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
    if ( cotlas_is_custom_auth_ajax_request() ) return $errors;

    // If feature disabled, skip check
    if (!cotlas_challenge_provider_for_form( 'wp_register' )) return $errors;

    $check = cotlas_verify_challenge_for_form( 'wp_register', 'wp_register' );
    if (is_wp_error($check)) {
        $errors->add($check->get_error_code(), $check->get_error_message());
    }
    return $errors;
}, 10, 3);

/** Verify Turnstile token on preprocess_comment (comment submission). */
add_filter('preprocess_comment', function($commentdata) {
    if (is_user_logged_in()) return $commentdata;
    
    // If feature disabled, skip check
    if (!cotlas_challenge_provider_for_form( 'comments' )) return $commentdata;
    
    $check = cotlas_verify_challenge_for_form( 'comments', 'comments' );
    if (is_wp_error($check)) {
        wp_die($check->get_error_message());
    }
    return $commentdata;
});
