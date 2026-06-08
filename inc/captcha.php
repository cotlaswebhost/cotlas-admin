<?php
/**
 * Google reCAPTCHA v3 and Math CAPTCHA integration.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

function cotlas_captcha_request_has( $key ) {
	return isset( $_POST[ $key ] ) && $_POST[ $key ] !== ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

function cotlas_captcha_remote_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

function cotlas_sanitize_recaptcha_threshold( $value ) {
	$value = (float) $value;
	if ( $value <= 0 || $value > 1 ) {
		return '0.5';
	}
	return (string) $value;
}

function cotlas_challenge_provider_for_form( $form ) {
	$providers = array(
		'turnstile'  => array(
			'site_key' => 'turnstile_site_key',
			'forms'    => array(
				'wp_login'        => 'turnstile_enable_login',
				'wp_register'     => 'turnstile_enable_register',
				'comments'        => 'turnstile_enable_comments',
				'cotlas_login'    => 'cotlas_auth_turnstile_login',
				'cotlas_register' => 'cotlas_auth_turnstile_register',
			),
		),
		'recaptcha'  => array(
			'site_key' => 'recaptcha_v3_site_key',
			'forms'    => array(
				'wp_login'        => 'recaptcha_v3_enable_login',
				'wp_register'     => 'recaptcha_v3_enable_register',
				'comments'        => 'recaptcha_v3_enable_comments',
				'cotlas_login'    => 'cotlas_auth_recaptcha_login',
				'cotlas_register' => 'cotlas_auth_recaptcha_register',
			),
		),
		'math'       => array(
			'forms' => array(
				'wp_login'        => 'math_captcha_enable_login',
				'wp_register'     => 'math_captcha_enable_register',
				'comments'        => 'math_captcha_enable_comments',
				'cotlas_login'    => 'cotlas_auth_math_captcha_login',
				'cotlas_register' => 'cotlas_auth_math_captcha_register',
			),
		),
	);

	foreach ( $providers as $provider => $config ) {
		if ( empty( $config['forms'][ $form ] ) || ! get_option( $config['forms'][ $form ] ) ) {
			continue;
		}
		if ( ! empty( $config['site_key'] ) && ! get_option( $config['site_key'] ) ) {
			continue;
		}
		return $provider;
	}

	return '';
}

function cotlas_enqueue_recaptcha_v3_if_needed() {
	$site_key = get_option( 'recaptcha_v3_site_key' );
	if ( ! $site_key ) {
		return;
	}

	$enabled = get_option( 'recaptcha_v3_enable_login' )
		|| get_option( 'recaptcha_v3_enable_register' )
		|| get_option( 'recaptcha_v3_enable_comments' )
		|| get_option( 'cotlas_auth_recaptcha_login' )
		|| get_option( 'cotlas_auth_recaptcha_register' );

	if ( ! $enabled ) {
		return;
	}

	wp_enqueue_script(
		'google-recaptcha-v3',
		'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ),
		array(),
		null,
		true
	);
	wp_add_inline_script(
		'google-recaptcha-v3',
		'window.cotlasRecaptchaV3SiteKey=' . wp_json_encode( $site_key ) . ';',
		'before'
	);
	wp_add_inline_script(
		'google-recaptcha-v3',
		"(function(){function bind(f){if(!f||f.dataset.cotlasRecaptchaBound||f.matches('[data-cotlas-form],.ctc-form'))return;var i=f.querySelector('.cotlas-recaptcha-v3-response');if(!i)return;f.dataset.cotlasRecaptchaBound='1';f.addEventListener('submit',function(e){if(i.value)return;e.preventDefault();if(!window.grecaptcha||!window.cotlasRecaptchaV3SiteKey){f.submit();return;}window.grecaptcha.ready(function(){window.grecaptcha.execute(window.cotlasRecaptchaV3SiteKey,{action:i.getAttribute('data-recaptcha-action')||'submit'}).then(function(t){i.value=t;f.submit();});});});}document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('form').forEach(bind);});})();",
		'after'
	);
}
add_action( 'login_enqueue_scripts', 'cotlas_enqueue_recaptcha_v3_if_needed' );
add_action( 'wp_enqueue_scripts', 'cotlas_enqueue_recaptcha_v3_if_needed' );

function cotlas_render_recaptcha_v3_field( $action ) {
	echo '<input type="hidden" name="g-recaptcha-response" class="cotlas-recaptcha-v3-response" data-recaptcha-action="' . esc_attr( $action ) . '" value="">';
}

function cotlas_verify_recaptcha_v3( $expected_action = '' ) {
	static $results = array();

	$token_key = $expected_action ?: 'default';
	if ( isset( $results[ $token_key ] ) ) {
		return $results[ $token_key ];
	}

	$secret_key = get_option( 'recaptcha_v3_secret_key' );
	if ( empty( $secret_key ) ) {
		$results[ $token_key ] = new WP_Error( 'recaptcha_misconfigured', __( '<strong>ERROR</strong>: reCAPTCHA is not configured correctly.', 'cotlas-admin' ) );
		return $results[ $token_key ];
	}

	if ( ! cotlas_captcha_request_has( 'g-recaptcha-response' ) ) {
		$results[ $token_key ] = new WP_Error( 'recaptcha_missing', __( '<strong>ERROR</strong>: reCAPTCHA verification is missing.', 'cotlas-admin' ) );
		return $results[ $token_key ];
	}

	$token    = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'body' => array(
				'secret'   => $secret_key,
				'response' => $token,
				'remoteip' => cotlas_captcha_remote_ip(),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$results[ $token_key ] = new WP_Error( 'recaptcha_error', __( '<strong>ERROR</strong>: Unable to verify reCAPTCHA.', 'cotlas-admin' ) );
		return $results[ $token_key ];
	}

	$data      = json_decode( wp_remote_retrieve_body( $response ), true );
	$threshold = (float) get_option( 'recaptcha_v3_score_threshold', 0.5 );
	$score     = isset( $data['score'] ) ? (float) $data['score'] : 0;

	if ( empty( $data['success'] ) || $score < $threshold ) {
		$results[ $token_key ] = new WP_Error( 'recaptcha_invalid', __( '<strong>ERROR</strong>: reCAPTCHA verification failed.', 'cotlas-admin' ) );
		return $results[ $token_key ];
	}

	if ( $expected_action && ( empty( $data['action'] ) || $data['action'] !== $expected_action ) ) {
		$results[ $token_key ] = new WP_Error( 'recaptcha_action_mismatch', __( '<strong>ERROR</strong>: reCAPTCHA action mismatch.', 'cotlas-admin' ) );
		return $results[ $token_key ];
	}

	$results[ $token_key ] = true;
	return true;
}

function cotlas_math_captcha_hash( $answer, $nonce ) {
	return wp_hash( absint( $answer ) . '|' . sanitize_text_field( $nonce ) . '|cotlas_math_captcha' );
}

function cotlas_math_captcha_field() {
	$a      = wp_rand( 2, 9 );
	$b      = wp_rand( 1, 9 );
	$nonce  = wp_create_nonce( 'cotlas_math_captcha_' . $a . '_' . $b );
	$answer = $a + $b;

	return '<div class="cotlas-math-captcha">'
		. '<label>' . esc_html( sprintf( __( 'Security question: %1$d + %2$d = ?', 'cotlas-admin' ), $a, $b ) ) . '</label>'
		. '<input type="number" name="cotlas_math_answer" required autocomplete="off" inputmode="numeric">'
		. '<input type="hidden" name="cotlas_math_nonce" value="' . esc_attr( $nonce ) . '">'
		. '<input type="hidden" name="cotlas_math_hash" value="' . esc_attr( cotlas_math_captcha_hash( $answer, $nonce ) ) . '">'
		. '</div>';
}

function cotlas_display_math_captcha_field() {
	echo cotlas_math_captcha_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cotlas_verify_math_captcha() {
	if ( ! cotlas_captcha_request_has( 'cotlas_math_answer' ) || ! cotlas_captcha_request_has( 'cotlas_math_nonce' ) || ! cotlas_captcha_request_has( 'cotlas_math_hash' ) ) {
		return new WP_Error( 'math_captcha_missing', __( '<strong>ERROR</strong>: Please answer the security question.', 'cotlas-admin' ) );
	}

	$answer = absint( wp_unslash( $_POST['cotlas_math_answer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$nonce  = sanitize_text_field( wp_unslash( $_POST['cotlas_math_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$hash   = sanitize_text_field( wp_unslash( $_POST['cotlas_math_hash'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( ! hash_equals( cotlas_math_captcha_hash( $answer, $nonce ), $hash ) ) {
		return new WP_Error( 'math_captcha_invalid', __( '<strong>ERROR</strong>: The security question answer is incorrect.', 'cotlas-admin' ) );
	}

	return true;
}

function cotlas_verify_challenge_for_form( $form, $recaptcha_action = '' ) {
	$provider = cotlas_challenge_provider_for_form( $form );

	if ( 'turnstile' === $provider && function_exists( 'cotlas_verify_turnstile' ) ) {
		return cotlas_verify_turnstile();
	}
	if ( 'recaptcha' === $provider ) {
		return cotlas_verify_recaptcha_v3( $recaptcha_action ?: $form );
	}
	if ( 'math' === $provider ) {
		return cotlas_verify_math_captcha();
	}

	return true;
}

function cotlas_render_challenge_for_form( $form, $recaptcha_action = '' ) {
	$provider = cotlas_challenge_provider_for_form( $form );

	if ( 'turnstile' === $provider ) {
		$site_key = get_option( 'turnstile_site_key' );
		echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
	} elseif ( 'recaptcha' === $provider ) {
		cotlas_render_recaptcha_v3_field( $recaptcha_action ?: $form );
	} elseif ( 'math' === $provider ) {
		cotlas_display_math_captcha_field();
	}
}

function cotlas_disable_other_captcha_providers( $active_provider ) {
	$options = array(
		'turnstile' => array(
			'turnstile_enable_login',
			'turnstile_enable_register',
			'turnstile_enable_comments',
			'cotlas_auth_turnstile_login',
			'cotlas_auth_turnstile_register',
		),
		'recaptcha' => array(
			'recaptcha_v3_enable_login',
			'recaptcha_v3_enable_register',
			'recaptcha_v3_enable_comments',
			'cotlas_auth_recaptcha_login',
			'cotlas_auth_recaptcha_register',
		),
		'math'      => array(
			'math_captcha_enable_login',
			'math_captcha_enable_register',
			'math_captcha_enable_comments',
			'cotlas_auth_math_captcha_login',
			'cotlas_auth_math_captcha_register',
		),
	);

	foreach ( $options as $provider => $provider_options ) {
		if ( $provider === $active_provider ) {
			continue;
		}
		foreach ( $provider_options as $option ) {
			unset( $_POST[ $option ] );
			update_option( $option, 0 );
		}
	}
}

function cotlas_request_enables_provider( $provider ) {
	$provider_fields = array(
		'turnstile' => array( 'turnstile_enable_login', 'turnstile_enable_register', 'turnstile_enable_comments', 'cotlas_auth_turnstile_login', 'cotlas_auth_turnstile_register' ),
		'recaptcha' => array( 'recaptcha_v3_enable_login', 'recaptcha_v3_enable_register', 'recaptcha_v3_enable_comments', 'cotlas_auth_recaptcha_login', 'cotlas_auth_recaptcha_register' ),
		'math'      => array( 'math_captcha_enable_login', 'math_captcha_enable_register', 'math_captcha_enable_comments', 'cotlas_auth_math_captcha_login', 'cotlas_auth_math_captcha_register' ),
	);

	foreach ( $provider_fields[ $provider ] as $field ) {
		if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
	}

	return false;
}
