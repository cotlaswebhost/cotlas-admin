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
    $login_enabled = get_option('turnstile_enable_login');
    $register_enabled = get_option('turnstile_enable_register');
    $comments_enabled = get_option('turnstile_enable_comments');
    
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
    $site_key = get_option('turnstile_site_key');
    if (!$site_key) return;

    $show = false;
    $current_filter = current_filter();

    if ($current_filter === 'login_form' && get_option('turnstile_enable_login')) {
        $show = true;
    } elseif ($current_filter === 'register_form' && get_option('turnstile_enable_register')) {
        $show = true;
    } elseif ($current_filter === 'comment_form' && get_option('turnstile_enable_comments')) {
        $show = true;
    }

    if ($show) {
        echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '"></div>';
    }
}
add_action('login_form', 'cotlas_display_turnstile');
add_action('register_form', 'cotlas_display_turnstile');
add_action('comment_form', 'cotlas_display_turnstile');

/**
 * Verify the Turnstile token by calling the Cloudflare siteverify endpoint.
 * Returns true on success or a WP_Error on failure/missing token.
 *
 * @return true|WP_Error
 */
function cotlas_verify_turnstile() {
    // Check which context we are in to decide if verification is needed
    $need_verify = false;
    
    // We can't easily check the current hook here because this function is called from inside the hooks.
    // So we need to pass context or deduce it.
    // However, the caller functions know the context.
    
    // But let's check keys first.
    $secret_key = get_option('turnstile_secret_key');
    if (empty($secret_key)) return true; // No key, no check (fail open to avoid lockout)

    if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
         return new WP_Error('turnstile_missing', __('<strong>ERROR</strong>: Please verify you are human.'));
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $_POST['cf-turnstile-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('turnstile_error', __('<strong>ERROR</strong>: Unable to verify Turnstile.'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || !isset($data['success']) || !$data['success']) {
        return new WP_Error('turnstile_invalid', __('<strong>ERROR</strong>: Turnstile verification failed.'));
    }

    return true;
}

/** Verify Turnstile token on wp_authenticate_user (login). */
add_filter('wp_authenticate_user', function($user, $password) {
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_login')) return $user;
    
    if (is_wp_error($user)) return $user;
    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        return $check;
    }
    return $user;
}, 10, 2);

/** Verify Turnstile token on registration_errors. */
add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_register')) return $errors;

    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        $errors->add($check->get_error_code(), $check->get_error_message());
    }
    return $errors;
}, 10, 3);

/** Verify Turnstile token on preprocess_comment (comment submission). */
add_filter('preprocess_comment', function($commentdata) {
    if (is_user_logged_in()) return $commentdata;
    
    // If feature disabled, skip check
    if (!get_option('turnstile_enable_comments')) return $commentdata;
    
    $check = cotlas_verify_turnstile();
    if (is_wp_error($check)) {
        wp_die($check->get_error_message());
    }
    return $commentdata;
});
