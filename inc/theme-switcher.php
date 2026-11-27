<?php
/**
 * Per-user theme switcher for administrators.
 *
 * WordPress resolves the active theme before the current user is set up, so the
 * preview choice is carried in a signed cookie. Only the browser where an
 * administrator saved the setting receives that cookie, which keeps visitors
 * and other users on the site's active theme.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

const COTLAS_PREVIEW_THEME_COOKIE = 'cws_preview_theme';

/**
 * Read the preview theme slug from the signed cookie.
 *
 * @return string Valid theme slug, or empty string.
 */
function cotlas_preview_theme_from_cookie() {
	if ( empty( $_COOKIE[ COTLAS_PREVIEW_THEME_COOKIE ] ) || ! is_string( $_COOKIE[ COTLAS_PREVIEW_THEME_COOKIE ] ) ) {
		return '';
	}

	/*
	 * The template/stylesheet option filters can fire while plugins are still
	 * being included (some plugins resolve the theme directory at that point).
	 * wp_hash() lives in pluggable.php, which loads after plugins, so verifying
	 * the signature there would fatal. Skip the preview until it is available;
	 * the real theme resolution happens later in wp-settings.php.
	 */
	if ( ! function_exists( 'wp_hash' ) ) {
		return '';
	}

	$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ COTLAS_PREVIEW_THEME_COOKIE ] ) ) );
	if ( 3 !== count( $parts ) ) {
		return '';
	}

	list( $theme, $expires, $signature ) = $parts;

	if ( (int) $expires < time() || ! hash_equals( wp_hash( $theme . '|' . $expires ), $signature ) ) {
		return '';
	}

	if ( ! preg_match( '/^[A-Za-z0-9._-]+$/', $theme ) ) {
		return '';
	}

	$wp_theme = wp_get_theme( $theme );

	return $wp_theme->exists() ? $theme : '';
}

/**
 * Store or clear the preview theme cookie.
 *
 * @param string $theme Theme slug, or empty string to clear.
 */
function cotlas_set_preview_theme_cookie( $theme ) {
	$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

	if ( '' === $theme ) {
		setcookie( COTLAS_PREVIEW_THEME_COOKIE, '', time() - YEAR_IN_SECONDS, $path, $domain, is_ssl(), true );
		return;
	}

	$expires = time() + ( 30 * DAY_IN_SECONDS );
	$value   = $theme . '|' . $expires . '|' . wp_hash( $theme . '|' . $expires );

	setcookie( COTLAS_PREVIEW_THEME_COOKIE, $value, $expires, $path, $domain, is_ssl(), true );
}

/**
 * Return the preview theme for the current request.
 *
 * Applies to both the frontend and wp-admin so the previewed theme's Customizer,
 * settings screens and menus are available to the administrator.
 *
 * @return string
 */
function cotlas_preview_theme() {
	if ( ! get_option( 'cotlas_theme_switcher_enabled', 0 ) ) {
		return '';
	}

	return cotlas_preview_theme_from_cookie();
}

/**
 * Filter template/stylesheet options so the preview theme renders.
 *
 * WordPress loads the parent theme's functions.php based on the `template`
 * option and the child theme's based on `stylesheet`. Returning the preview
 * slug for both would point `template` at a child theme, so the parent's
 * functions (e.g. GeneratePress) would never load and its template tags would
 * fatal. Resolve the preview theme's actual parent for the `template` option.
 *
 * @param mixed  $pre    Option short-circuit value.
 * @param string $option Option name.
 * @return mixed
 */
function cotlas_preview_theme_filter( $pre, $option = '' ) {
	$theme = cotlas_preview_theme();

	if ( '' === $theme ) {
		return $pre;
	}

	if ( 'template' === $option ) {
		$wp_theme = wp_get_theme( $theme );
		$parent   = $wp_theme->parent();

		return $parent ? $parent->get_stylesheet() : $wp_theme->get_template();
	}

	return $theme;
}
add_filter( 'pre_option_template', 'cotlas_preview_theme_filter', 10, 2 );
add_filter( 'pre_option_stylesheet', 'cotlas_preview_theme_filter', 10, 2 );

/**
 * Keep the cookie in sync with the administrator's saved preference.
 *
 * Runs in wp-admin so the next frontend request picks up the preview even if
 * the cookie expired or was cleared.
 */
function cotlas_refresh_preview_theme_cookie() {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! get_option( 'cotlas_theme_switcher_enabled', 0 ) ) {
		return;
	}

	$theme   = (string) get_user_meta( get_current_user_id(), 'cotlas_preview_theme', true );
	$current = cotlas_preview_theme_from_cookie();

	if ( $theme !== $current ) {
		cotlas_set_preview_theme_cookie( $theme );
	}
}
add_action( 'admin_init', 'cotlas_refresh_preview_theme_cookie' );

/**
 * Show the preview state in the admin bar with a link to change it.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function cotlas_preview_theme_admin_bar( $wp_admin_bar ) {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! cotlas_preview_theme() ) {
		return;
	}

	$theme = wp_get_theme( cotlas_preview_theme() );
	if ( ! $theme->exists() ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'cotlas-preview-theme',
			'title' => sprintf(
				/* translators: %s: theme name */
				__( 'Previewing: %s', 'cotlas-admin' ),
				$theme->get( 'Name' )
			),
			'href'  => admin_url( 'admin.php?page=cotlas-tools&tab=theme-switcher' ),
		)
	);
}
add_action( 'admin_bar_menu', 'cotlas_preview_theme_admin_bar', 100 );

/**
 * Drop the preview when a theme is actually activated so wp-admin reflects the
 * newly active theme instead of staying stuck on the preview.
 */
function cotlas_clear_preview_theme_on_switch() {
	if ( ! function_exists( 'cotlas_set_preview_theme_cookie' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id ) {
		delete_user_meta( $user_id, 'cotlas_preview_theme' );
	}

	cotlas_set_preview_theme_cookie( '' );
}
add_action( 'switch_theme', 'cotlas_clear_preview_theme_on_switch' );
