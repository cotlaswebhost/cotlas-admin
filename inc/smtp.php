<?php
/**
 * SMTP Settings module.
 *
 * Configures PHPMailer to send all WordPress mail via a custom SMTP server
 * when enabled. Form-specific from-name/from-email override these for form
 * submissions, but these settings apply to all other wp_mail() calls.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

add_action( 'phpmailer_init', 'cotlas_smtp_configure' );

function cotlas_smtp_configure( $phpmailer ) {
	if ( ! get_option( 'cotlas_smtp_enabled', 0 ) ) {
		return;
	}

	$host = get_option( 'cotlas_smtp_host', '' );
	if ( '' === $host ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Port       = (int) get_option( 'cotlas_smtp_port', 587 );
	$phpmailer->Username   = get_option( 'cotlas_smtp_user', '' );
	$phpmailer->Password   = get_option( 'cotlas_smtp_pass', '' );

	$encryption = get_option( 'cotlas_smtp_encryption', 'tls' );
	if ( 'ssl' === $encryption ) {
		$phpmailer->SMTPSecure = 'ssl';
	} elseif ( 'tls' === $encryption ) {
		$phpmailer->SMTPSecure = 'tls';
	} else {
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}

	$from_name  = get_option( 'cotlas_smtp_from_name', '' );
	$from_email = get_option( 'cotlas_smtp_from_email', '' );
	if ( '' !== $from_name ) {
		$phpmailer->FromName = $from_name;
	}
	if ( '' !== $from_email && is_email( $from_email ) ) {
		$phpmailer->From = $from_email;
	}
}

// Override From / FromName for all wp_mail calls when SMTP is enabled and
// "deliver all messages" is on (already implied by phpmailer_init).
add_filter( 'wp_mail_from', 'cotlas_smtp_wp_mail_from' );
add_filter( 'wp_mail_from_name', 'cotlas_smtp_wp_mail_from_name' );

function cotlas_smtp_wp_mail_from( $from ) {
	if ( ! get_option( 'cotlas_smtp_enabled', 0 ) ) {
		return $from;
	}
	$email = get_option( 'cotlas_smtp_from_email', '' );
	return ( '' !== $email && is_email( $email ) ) ? $email : $from;
}

function cotlas_smtp_wp_mail_from_name( $name ) {
	if ( ! get_option( 'cotlas_smtp_enabled', 0 ) ) {
		return $name;
	}
	$from_name = get_option( 'cotlas_smtp_from_name', '' );
	return '' !== $from_name ? $from_name : $name;
}