<?php
/**
 * Custom Auth – Settings Page
 *
 * Registers all options and adds a "Custom Auth" submenu under Site Security.
 *
 * Options:
 *   cotlas_auth_enabled              bool   – master toggle
 *   cotlas_auth_login_slug           string – page slug for login page
 *   cotlas_auth_register_slug        string – page slug for register page
 *   cotlas_auth_redirect_privileged  url    – redirect for admin/editor/author
 *   cotlas_auth_redirect_default     url    – redirect for subscriber / other roles
 *   cotlas_auth_honeypot             bool   – enable honeypot on custom forms
 *   cotlas_auth_turnstile_login      bool   – enable Turnstile on custom login form
 *   cotlas_auth_turnstile_register   bool   – enable Turnstile on custom register form
 *   cotlas_auth_rate_limit           int    – max login attempts per IP / 10 min
 *
 * @package Cotlas_Admin
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Register settings
// ---------------------------------------------------------------------------
add_action( 'admin_init', 'cotlas_auth_register_settings' );

function cotlas_auth_register_settings() {
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_enabled',             [ 'sanitize_callback' => 'absint',          'default' => 0 ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_login_slug',          [ 'sanitize_callback' => 'sanitize_title',  'default' => 'login' ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_register_slug',       [ 'sanitize_callback' => 'sanitize_title',  'default' => 'register' ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_redirect_privileged', [ 'sanitize_callback' => 'esc_url_raw',     'default' => '' ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_redirect_default',    [ 'sanitize_callback' => 'esc_url_raw',     'default' => '' ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_honeypot',            [ 'sanitize_callback' => 'absint',          'default' => 1 ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_turnstile_login',     [ 'sanitize_callback' => 'absint',          'default' => 0 ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_turnstile_register',  [ 'sanitize_callback' => 'absint',          'default' => 0 ] );
    register_setting( 'cotlas_auth_settings', 'cotlas_auth_rate_limit',          [ 'sanitize_callback' => 'absint',          'default' => 5 ] );
}

// Admin menu registration is handled by inc/admin-panel.php (Cotlas Admin → Login System)
