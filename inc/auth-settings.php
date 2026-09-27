<?php
/**
 * Custom Auth – Settings
 *
 * Options used by the login/register/forgot-password system:
 *
 *   cotlas_auth_enabled              bool   – master toggle
 *   cotlas_auth_login_slug           string – page slug for login page
 *   cotlas_auth_register_slug        string – page slug for register page
 *   cotlas_auth_forgot_slug          string – page slug for forgot-password page
 *   cotlas_auth_redirect_privileged  url    – redirect for admin/editor/author
 *   cotlas_auth_redirect_default     url    – redirect for subscriber / other roles
 *   cotlas_auth_redirect_register    url    – redirect after registration
 *   cotlas_auth_honeypot             bool   – enable honeypot on custom forms
 *   cotlas_auth_turnstile_login      bool   – enable Turnstile on custom login form
 *   cotlas_auth_turnstile_register   bool   – enable Turnstile on custom register form
 *   cotlas_auth_recaptcha_login      bool   – enable reCAPTCHA on custom login form
 *   cotlas_auth_recaptcha_register   bool   – enable reCAPTCHA on custom register form
 *   cotlas_auth_math_captcha_login   bool   – enable Math CAPTCHA on custom login form
 *   cotlas_auth_math_captcha_register bool  – enable Math CAPTCHA on custom register form
 *   cotlas_auth_rate_limit           int    – max login attempts per IP / 15 min
 *
 * All options are saved via the unified admin panel (inc/admin-panel.php).
 * Security/CAPTCHA toggles for Cotlas forms are managed from the
 * Site Security page only (not duplicated on the Login System page).
 *
 * @package Cotlas_Admin
 */

defined( 'ABSPATH' ) || exit;
