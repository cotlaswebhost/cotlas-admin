<?php
/**
 * Cloudflare Turnstile CAPTCHA integration.
 *
 * Enqueues the Turnstile script and provides cotlas_verify_turnstile(), the token
 * verifier used by the shared challenge wiring in captcha.php. Settings live under
 * Site Security in admin-panel.php.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// Cloudflare Turnstile Settings

/**
 * Register the "Site Security" top-level admin menu page.
 */
// Menu registration moved to admin-panel.php

// Script enqueueing, widget rendering and challenge verification for the core
// forms all live in captcha.php (cotlas_enqueue_challenge_scripts(),
// cotlas_display_challenge() and the shared verify filters), so the active
// provider loads, renders and verifies exactly once.

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
