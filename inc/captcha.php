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
		'hcaptcha'   => array(
			'site_key' => 'hcaptcha_site_key',
			'forms'    => array(
				'wp_login'        => 'hcaptcha_enable_login',
				'wp_register'     => 'hcaptcha_enable_register',
				'comments'        => 'hcaptcha_enable_comments',
				'cotlas_login'    => 'cotlas_auth_hcaptcha_login',
				'cotlas_register' => 'cotlas_auth_hcaptcha_register',
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

/**
 * Enqueue the script for whichever CAPTCHA provider is active.
 *
 * The one place that decides which third-party script to load: only a single
 * provider can be active at a time (see cotlas_disable_other_captcha_providers())
 * and the forms only emit markup, so each script is enqueued from exactly here.
 */
function cotlas_enqueue_challenge_scripts() {
	$active = array();
	foreach ( array( 'wp_login', 'wp_register', 'comments', 'cotlas_login', 'cotlas_register' ) as $form ) {
		$provider = cotlas_challenge_provider_for_form( $form );
		if ( $provider ) {
			$active[ $provider ] = true;
		}
	}

	if ( isset( $active['recaptcha'] ) ) {
		cotlas_enqueue_recaptcha_v3_if_needed();
	}

	if ( isset( $active['turnstile'] ) ) {
		wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
	}

	if ( isset( $active['hcaptcha'] ) ) {
		// Auto-render mode: hCaptcha scans for .h-captcha divs on load, so no hcaptcha.render() call is needed.
		wp_enqueue_script( 'hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
	}
}
add_action( 'login_enqueue_scripts', 'cotlas_enqueue_challenge_scripts' );
add_action( 'wp_enqueue_scripts', 'cotlas_enqueue_challenge_scripts' );

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

function cotlas_verify_hcaptcha() {
	static $verification_result = null;

	if ( $verification_result !== null ) {
		return $verification_result;
	}

	$secret_key = get_option( 'hcaptcha_secret_key' );
	if ( empty( $secret_key ) ) {
		$verification_result = true;
		return $verification_result;
	}

	if ( ! isset( $_POST['h-captcha-response'] ) || empty( $_POST['h-captcha-response'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$verification_result = new WP_Error( 'hcaptcha_missing', __( '<strong>ERROR</strong>: Please complete the hCaptcha challenge.', 'cotlas-admin' ) );
		return $verification_result;
	}

	$response = wp_remote_post(
		'https://api.hcaptcha.com/siteverify',
		array(
			'body' => array(
				'secret'   => $secret_key,
				'response' => sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'remoteip' => cotlas_captcha_remote_ip(),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$verification_result = new WP_Error( 'hcaptcha_error', __( '<strong>ERROR</strong>: Unable to verify hCaptcha.', 'cotlas-admin' ) );
		return $verification_result;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $data['success'] ) ) {
		$verification_result = new WP_Error( 'hcaptcha_invalid', __( '<strong>ERROR</strong>: hCaptcha verification failed.', 'cotlas-admin' ) );
		return $verification_result;
	}

	$verification_result = true;
	return true;
}

function cotlas_math_captcha_hash( $answer, $nonce, $op = '+' ) {
	return wp_hash( (int) $answer . '|' . sanitize_text_field( $nonce ) . '|' . $op . '|cotlas_math_captcha' );
}

function cotlas_math_captcha_generate() {
	$difficulty = get_option( 'math_captcha_difficulty', 'easy' );

	switch ( $difficulty ) {
		case 'advanced':
			$ops = array( '+', '-', '×' );
			$op  = $ops[ wp_rand( 0, 2 ) ];
			if ( '×' === $op ) {
				$a = wp_rand( 2, 12 );
				$b = wp_rand( 2, 12 );
			} else {
				$a = wp_rand( 10, 99 );
				$b = wp_rand( 10, 99 );
				if ( '-' === $op && $a < $b ) {
					$tmp = $a;
					$a   = $b;
					$b   = $tmp;
				}
			}
			break;

		case 'moderate':
			$op = wp_rand( 0, 1 ) ? '+' : '-';
			$a  = wp_rand( 5, 30 );
			$b  = wp_rand( 2, 20 );
			if ( '-' === $op && $a < $b ) {
				$tmp = $a;
				$a   = $b;
				$b   = $tmp;
			}
			break;

		default: // easy
			$op = '+';
			$a  = wp_rand( 1, 15 );
			$b  = wp_rand( 1, 15 );
			break;
	}

	switch ( $op ) {
		case '-':
			$answer = $a - $b;
			break;
		case '×':
			$answer = $a * $b;
			break;
		default:
			$answer = $a + $b;
			break;
	}

	return array( $a, $b, $op, $answer );
}

function cotlas_math_captcha_field() {
	list( $a, $b, $op, $answer ) = cotlas_math_captcha_generate();
	$nonce = wp_create_nonce( 'cotlas_math_captcha_' . $a . $op . $b );

	return '<div class="cotlas-math-captcha">'
		. '<label>' . esc_html( sprintf( __( 'Security question: %1$d %2$s %3$d = ?', 'cotlas-admin' ), $a, $op, $b ) ) . '</label>'
		. '<input type="number" name="cotlas_math_answer" required autocomplete="off" inputmode="numeric">'
		. '<input type="hidden" name="cotlas_math_nonce" value="' . esc_attr( $nonce ) . '">'
		. '<input type="hidden" name="cotlas_math_hash" value="' . esc_attr( cotlas_math_captcha_hash( $answer, $nonce, $op ) ) . '">'
		. '</div>';
}

function cotlas_display_math_captcha_field() {
	echo cotlas_math_captcha_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cotlas_verify_math_captcha() {
	if ( ! cotlas_captcha_request_has( 'cotlas_math_answer' ) || ! cotlas_captcha_request_has( 'cotlas_math_nonce' ) || ! cotlas_captcha_request_has( 'cotlas_math_hash' ) ) {
		return new WP_Error( 'math_captcha_missing', __( '<strong>ERROR</strong>: Please answer the security question.', 'cotlas-admin' ) );
	}

	$answer = (int) wp_unslash( $_POST['cotlas_math_answer'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$nonce  = sanitize_text_field( wp_unslash( $_POST['cotlas_math_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$hash   = sanitize_text_field( wp_unslash( $_POST['cotlas_math_hash'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// Try each operation since we don't store which one was used in the form.
	foreach ( array( '+', '-', '×' ) as $op ) {
		if ( hash_equals( cotlas_math_captcha_hash( $answer, $nonce, $op ), $hash ) ) {
			return true;
		}
	}

	return new WP_Error( 'math_captcha_invalid', __( '<strong>ERROR</strong>: The security question answer is incorrect.', 'cotlas-admin' ) );
}

function cotlas_verify_challenge_for_form( $form, $recaptcha_action = '' ) {
	$provider = cotlas_challenge_provider_for_form( $form );

	if ( 'turnstile' === $provider && function_exists( 'cotlas_verify_turnstile' ) ) {
		return cotlas_verify_turnstile();
	}
	if ( 'recaptcha' === $provider ) {
		return cotlas_verify_recaptcha_v3( $recaptcha_action ?: $form );
	}
	if ( 'hcaptcha' === $provider ) {
		return cotlas_verify_hcaptcha();
	}
	if ( 'math' === $provider ) {
		return cotlas_verify_math_captcha();
	}

	return true;
}

function cotlas_display_hcaptcha_field() {
	$site_key = get_option( 'hcaptcha_site_key' );
	if ( ! $site_key ) {
		return;
	}
	echo '<div class="h-captcha" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
}

function cotlas_render_challenge_for_form( $form, $recaptcha_action = '' ) {
	$provider = cotlas_challenge_provider_for_form( $form );

	if ( 'turnstile' === $provider ) {
		$site_key = get_option( 'turnstile_site_key' );
		echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
	} elseif ( 'recaptcha' === $provider ) {
		cotlas_render_recaptcha_v3_field( $recaptcha_action ?: $form );
	} elseif ( 'hcaptcha' === $provider ) {
		cotlas_display_hcaptcha_field();
	} elseif ( 'math' === $provider ) {
		cotlas_display_math_captcha_field();
	}
}

/**
 * Render the active challenge inside the core login, registration and comment forms.
 *
 * Kept in one place on purpose: cotlas_render_challenge_for_form() already picks
 * whichever provider is enabled for the form, so a hook per provider integration
 * echoes the active provider once per integration and duplicates the widget on
 * wp-login.php and the default forms.
 */
function cotlas_display_challenge() {
	$form_by_filter = array(
		'login_form'    => 'wp_login',
		'register_form' => 'wp_register',
		'comment_form'  => 'comments',
	);

	$filter = current_filter();
	$form   = isset( $form_by_filter[ $filter ] ) ? $form_by_filter[ $filter ] : '';

	if ( '' === $form ) {
		return;
	}

	if ( 'comments' === $form && ( is_user_logged_in() || get_option( 'comment_registration' ) ) ) {
		return;
	}

	cotlas_render_challenge_for_form( $form, $form );
}
add_action( 'login_form', 'cotlas_display_challenge' );
add_action( 'register_form', 'cotlas_display_challenge' );
add_action( 'comment_form', 'cotlas_display_challenge' );

/**
 * Detect Cotlas custom auth AJAX requests.
 *
 * Custom login/register already verify the active challenge in auth-ajax.php.
 * Running the default form hooks again during wp_signon/register_new_user causes
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
 * Verify the challenge on the login form.
 *
 * Registered once, here, for the same reason as cotlas_display_challenge():
 * cotlas_verify_challenge_for_form() resolves whichever provider is active, so
 * a copy per provider integration ran the check twice and, on registration,
 * added the identical error to the same WP_Error twice.
 */
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

/** Verify the challenge on the registration form. */
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

/** Verify the challenge on comment submission. */
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
		'hcaptcha'  => array(
			'hcaptcha_enable_login',
			'hcaptcha_enable_register',
			'hcaptcha_enable_comments',
			'cotlas_auth_hcaptcha_login',
			'cotlas_auth_hcaptcha_register',
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
		'hcaptcha'  => array( 'hcaptcha_enable_login', 'hcaptcha_enable_register', 'hcaptcha_enable_comments', 'cotlas_auth_hcaptcha_login', 'cotlas_auth_hcaptcha_register' ),
		'math'      => array( 'math_captcha_enable_login', 'math_captcha_enable_register', 'math_captcha_enable_comments', 'cotlas_auth_math_captcha_login', 'cotlas_auth_math_captcha_register' ),
	);

	foreach ( $provider_fields[ $provider ] as $field ) {
		if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
	}

	return false;
}
