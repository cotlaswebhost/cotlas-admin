<?php
/**
 * hCaptcha CAPTCHA integration.
 *
 * Handles enqueueing the hCaptcha script, rendering the widget inside
 * login, registration, and comment forms, and verifying the response
 * token server-side.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the hCaptcha JS on login/register and frontend pages
 * only when at least one protection toggle is enabled.
 */
function cotlas_hcaptcha_script() {
	$login_enabled    = 'hcaptcha' === cotlas_challenge_provider_for_form( 'wp_login' );
	$register_enabled = 'hcaptcha' === cotlas_challenge_provider_for_form( 'wp_register' );
	$comments_enabled = 'hcaptcha' === cotlas_challenge_provider_for_form( 'comments' ) && ! is_user_logged_in() && ! get_option( 'comment_registration' );

	if ( $login_enabled || $register_enabled || $comments_enabled ) {
		wp_enqueue_script( 'hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
	}
}
add_action( 'login_enqueue_scripts', 'cotlas_hcaptcha_script' );
add_action( 'wp_enqueue_scripts', 'cotlas_hcaptcha_script' );

/**
 * Render the hCaptcha widget div inside the appropriate form.
 */
function cotlas_display_hcaptcha() {
	$current_filter = current_filter();

	if ( 'login_form' === $current_filter ) {
		cotlas_render_challenge_for_form( 'wp_login', 'wp_login' );
	} elseif ( 'register_form' === $current_filter ) {
		cotlas_render_challenge_for_form( 'wp_register', 'wp_register' );
	} elseif ( 'comment_form' === $current_filter && ! get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
		cotlas_render_challenge_for_form( 'comments', 'comments' );
	}
}
add_action( 'login_form', 'cotlas_display_hcaptcha' );
add_action( 'register_form', 'cotlas_display_hcaptcha' );
add_action( 'comment_form', 'cotlas_display_hcaptcha' );

/** Verify hCaptcha on wp_authenticate_user (login). */
add_filter( 'wp_authenticate_user', function ( $user, $password ) {
	if ( cotlas_is_custom_auth_ajax_request() ) {
		return $user;
	}
	if ( ! cotlas_challenge_provider_for_form( 'wp_login' ) ) {
		return $user;
	}
	if ( is_wp_error( $user ) ) {
		return $user;
	}
	$check = cotlas_verify_challenge_for_form( 'wp_login', 'wp_login' );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	return $user;
}, 10, 2 );

/** Verify hCaptcha on registration_errors. */
add_filter( 'registration_errors', function ( $errors, $sanitized_user_login, $user_email ) {
	if ( cotlas_is_custom_auth_ajax_request() ) {
		return $errors;
	}
	if ( ! cotlas_challenge_provider_for_form( 'wp_register' ) ) {
		return $errors;
	}
	$check = cotlas_verify_challenge_for_form( 'wp_register', 'wp_register' );
	if ( is_wp_error( $check ) ) {
		$errors->add( $check->get_error_code(), $check->get_error_message() );
	}
	return $errors;
}, 10, 3 );

/** Verify hCaptcha on preprocess_comment (comment submission). */
add_filter( 'preprocess_comment', function ( $commentdata ) {
	if ( is_user_logged_in() ) {
		return $commentdata;
	}
	if ( ! cotlas_challenge_provider_for_form( 'comments' ) ) {
		return $commentdata;
	}
	$check = cotlas_verify_challenge_for_form( 'comments', 'comments' );
	if ( is_wp_error( $check ) ) {
		wp_die( $check->get_error_message() );
	}
	return $commentdata;
} );