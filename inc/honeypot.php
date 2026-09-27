<?php
/**
 * Honeypot spam protection for WordPress login, registration, and
 * Houzez custom front-end login/register forms.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the honeypot field came back filled. Bots fill it, humans never see it.
 *
 * @return bool
 */
function cotlas_honeypot_is_filled() {
	return isset( $_POST['cc-city'] ) && ! empty( $_POST['cc-city'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/**
 * Map a default-form hook to the toggle that controls it.
 *
 * @param string $hook Hook name.
 * @return string Option name, or '' when the hook is not a default form.
 */
function cotlas_honeypot_default_form_option( $hook ) {
	$options = array(
		'login_form'          => 'cotlas_honeypot_wp_login',
		'register_form'       => 'cotlas_honeypot_wp_register',
		'authenticate'        => 'cotlas_honeypot_wp_login',
		'registration_errors' => 'cotlas_honeypot_wp_register',
	);

	return isset( $options[ $hook ] ) ? $options[ $hook ] : '';
}

/**
 * Whether the honeypot is switched on for the current default form.
 *
 * @return bool
 */
function cotlas_honeypot_default_form_enabled() {
	$option = cotlas_honeypot_default_form_option( current_filter() );

	return $option && get_option( $option );
}

/**
 * Add the hidden honeypot field to the default login and registration forms.
 */
function cotlas_add_honeypot_field() {
	if ( ! cotlas_honeypot_default_form_enabled() ) {
		return;
	}

	echo '<p style="display:none;"><label for="cc-city">cc-city<input type="text" name="cc-city" id="cc-city" class="input" value="" autocomplete="off" /></label></p>';
}
add_action( 'login_form', 'cotlas_add_honeypot_field' );
add_action( 'register_form', 'cotlas_add_honeypot_field' );

/**
 * Reject a submission whose honeypot field was filled, on the default login
 * (authenticate) and registration (registration_errors) forms.
 *
 * @param mixed $value Value passed through by the filter.
 * @return mixed
 */
function cotlas_honeypot_reject_default_form( $value ) {
	if ( cotlas_honeypot_default_form_enabled() && cotlas_honeypot_is_filled() ) {
		wp_redirect( home_url() );
		exit;
	}

	return $value;
}
add_filter( 'authenticate', 'cotlas_honeypot_reject_default_form', 30, 1 );
add_filter( 'registration_errors', 'cotlas_honeypot_reject_default_form', 10, 1 );

/**
 * Honeypot check for the Houzez theme front-end login and registration forms.
 */
function cotlas_houzez_form_honeypot() {
	if ( ! get_option( 'cotlas_honeypot_houzez' ) ) {
		return;
	}

	$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( in_array( $action, array( 'houzez_login', 'houzez_register' ), true ) && cotlas_honeypot_is_filled() ) {
		wp_redirect( home_url() );
		exit;
	}
}
add_action( 'init', 'cotlas_houzez_form_honeypot' );
