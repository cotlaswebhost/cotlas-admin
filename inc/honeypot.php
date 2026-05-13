<?php
/**
 * Honeypot spam protection for WordPress login, registration, and
 * Houzez custom front-end login/register forms.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// Honeypot function for backend login and register by cotlas404

// Add honeypot field to default WordPress login form
function cotlas_add_login_honeypot() {
  echo '<p style="display:none;"><label for="cc-city">cc-city<input type="text" name="cc-city" id="cc-city" class="input" value="" autocomplete="off" /></label></p>';
}
add_action('login_form', 'cotlas_add_login_honeypot');

// Add honeypot field to default WordPress registration form
function cotlas_add_register_honeypot() {
  echo '<p style="display:none;"><label for="cc-city">cc-city<input type="text" name="cc-city" id="cc-city" class="input" value="" autocomplete="off" /></label></p>';
}
add_action('register_form', 'cotlas_add_register_honeypot');

// Validate honeypot field for default login
function cotlas_validate_login_honeypot($user, $username, $password) {
  if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
      wp_redirect(home_url());
      exit;
  }
  return $user;
}
add_filter('authenticate', 'cotlas_validate_login_honeypot', 30, 3);

// Validate honeypot field for default registration
function cotlas_validate_register_honeypot($errors, $sanitized_user_login, $user_email) {
  if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
      wp_redirect(home_url());
      exit;
  }
  return $errors;
}
add_filter('registration_errors', 'cotlas_validate_register_honeypot', 10, 3);

// Honeypot function for frontend login and register by cotlas404

// Validate honeypot field for custom login form
function cotlas_custom_login_honeypot() {
  if (isset($_POST['action']) && $_POST['action'] === 'houzez_login') {
      if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
          wp_redirect(home_url());
          exit;
      }
  }
}
add_action('init', 'cotlas_custom_login_honeypot');

// Validate honeypot field for custom registration form
function cotlas_custom_register_honeypot() {
  if (isset($_POST['action']) && $_POST['action'] === 'houzez_register') {
      if (isset($_POST['cc-city']) && !empty($_POST['cc-city'])) {
          wp_redirect(home_url());
          exit;
      }
  }
}
add_action('init', 'cotlas_custom_register_honeypot');
