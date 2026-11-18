<?php
/**
 * Frontend dark mode: toggle markup, assets, and CSS overrides.
 *
 * The preference is stored in the visitor's browser for 30 days. When no
 * preference is stored the admin default applies, falling back to the device's
 * prefers-color-scheme setting.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-configured default mode.
 *
 * @return string light|dark|system
 */
function cotlas_dark_mode_default_mode() {
	$mode = (string) get_option( 'cotlas_dark_mode_default', 'system' );

	return in_array( $mode, array( 'light', 'dark', 'system' ), true ) ? $mode : 'system';
}

/**
 * Dark/light toggle markup.
 *
 * @return string
 */
function cotlas_dark_mode_toggle_html() {
	$dark_icon = '<svg class="cws-svg-icon cws-svg-mode-dark" aria-hidden="true" role="img" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="-26 -26 1076 1076"><path fill="currentColor" d="M968.172 426.83c-2.349-12.673-13.274-21.954-26.16-22.226-12.876-0.22-24.192 8.54-27.070 21.102-27.658 120.526-133.48 204.706-257.334 204.706-145.582 0-264.022-118.44-264.022-264.020 0-123.86 84.18-229.676 204.71-257.33 12.558-2.88 21.374-14.182 21.102-27.066s-9.548-23.81-22.22-26.162c-27.996-5.196-56.654-7.834-85.176-7.834-123.938 0-240.458 48.266-328.098 135.904-87.638 87.638-135.902 204.158-135.902 328.096s48.264 240.458 135.902 328.096c87.64 87.638 204.16 135.904 328.098 135.904s240.458-48.266 328.098-135.904c87.638-87.638 135.902-204.158 135.902-328.096 0-28.498-2.638-57.152-7.828-85.17z"></path></svg>';

	$light_icon = '<svg class="cws-svg-icon cws-svg-mode-light" aria-hidden="true" role="img" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 232.447 232.447"><path fill="currentColor" d="M116.211,194.8c-4.143,0-7.5,3.357-7.5,7.5v22.643c0,4.143,3.357,7.5,7.5,7.5s7.5-3.357,7.5-7.5V202.3 C123.711,198.157,120.354,194.8,116.211,194.8z"></path><path fill="currentColor" d="M116.211,37.645c4.143,0,7.5-3.357,7.5-7.5V7.505c0-4.143-3.357-7.5-7.5-7.5s-7.5,3.357-7.5,7.5v22.641 C108.711,34.288,112.068,37.645,116.211,37.645z"></path><path fill="currentColor" d="M50.054,171.78l-16.016,16.008c-2.93,2.929-2.931,7.677-0.003,10.606c1.465,1.466,3.385,2.198,5.305,2.198 c1.919,0,3.838-0.731,5.302-2.195l16.016-16.008c2.93-2.929,2.931-7.677,0.003-10.606C57.731,168.852,52.982,168.851,50.054,171.78 z"></path><path fill="currentColor" d="M177.083,62.852c1.919,0,3.838-0.731,5.302-2.195L198.4,44.649c2.93-2.929,2.931-7.677,0.003-10.606 c-2.93-2.932-7.679-2.931-10.607-0.003l-16.016,16.008c-2.93,2.929-2.931,7.677-0.003,10.607 C173.243,62.12,175.163,62.852,177.083,62.852z"></path><path fill="currentColor" d="M37.645,116.224c0-4.143-3.357-7.5-7.5-7.5H7.5c-4.143,0-7.5,3.357-7.5,7.5s3.357,7.5,7.5,7.5h22.645 C34.287,123.724,37.645,120.366,37.645,116.224z"></path><path fill="currentColor" d="M224.947,108.724h-22.652c-4.143,0-7.5,3.357-7.5,7.5s3.357,7.5,7.5,7.5h22.652c4.143,0,7.5-3.357,7.5-7.5 S229.09,108.724,224.947,108.724z"></path><path fill="currentColor" d="M50.052,60.655c1.465,1.465,3.384,2.197,5.304,2.197c1.919,0,3.839-0.732,5.303-2.196c2.93-2.929,2.93-7.678,0.001-10.606 L44.652,34.042c-2.93-2.93-7.679-2.929-10.606-0.001c-2.93,2.929-2.93,7.678-0.001,10.606L50.052,60.655z"></path><path fill="currentColor" d="M182.395,171.782c-2.93-2.929-7.679-2.93-10.606-0.001c-2.93,2.929-2.93,7.678-0.001,10.607l16.007,16.008 c1.465,1.465,3.384,2.197,5.304,2.197c1.919,0,3.839-0.732,5.303-2.196c2.93-2.929,2.93-7.678,0.001-10.607L182.395,171.782z"></path><path fill="currentColor" d="M116.22,48.7c-37.232,0-67.523,30.291-67.523,67.523s30.291,67.523,67.523,67.523s67.522-30.291,67.522-67.523 S153.452,48.7,116.22,48.7z M116.22,168.747c-28.962,0-52.523-23.561-52.523-52.523S87.258,63.7,116.22,63.7 c28.961,0,52.522,23.562,52.522,52.523S145.181,168.747,116.22,168.747z"></path></svg>';

	return '<div class="cws-dark-mode-toggle-wrap">'
		. '<div class="cws-dark-mode-toggle" role="button" tabindex="0" aria-pressed="false" aria-label="' . esc_attr__( 'Toggle dark mode', 'cotlas-admin' ) . '">'
		. '<span class="cws-dark-mode-slide">'
		. '<i class="cws-dark-mode-slide-btn cws-mode-icon-dark" aria-hidden="true" data-title="' . esc_attr__( 'Switch to light mode', 'cotlas-admin' ) . '">' . $dark_icon . '</i>'
		. '<i class="cws-dark-mode-slide-btn cws-mode-icon-default" aria-hidden="true" data-title="' . esc_attr__( 'Switch to dark mode', 'cotlas-admin' ) . '">' . $light_icon . '</i>'
		. '</span></div></div>';
}

/**
 * Shortcode: [cotlas_dark_mode_toggle]
 *
 * @return string
 */
function cotlas_dark_mode_toggle_shortcode() {
	if ( ! get_option( 'cotlas_dark_mode_enabled', 0 ) ) {
		return '';
	}

	return cotlas_dark_mode_toggle_html();
}
add_shortcode( 'cotlas_dark_mode_toggle', 'cotlas_dark_mode_toggle_shortcode' );

/**
 * Enqueue the dark mode script (head, so the class is set before paint) and CSS.
 */
function cotlas_dark_mode_enqueue_assets() {
	if ( ! get_option( 'cotlas_dark_mode_enabled', 0 ) ) {
		return;
	}

	$dir = plugin_dir_path( dirname( __FILE__ ) );
	$url = plugin_dir_url( dirname( __FILE__ ) );

	$script = $dir . 'assets/js/dark-mode.js';
	if ( file_exists( $script ) ) {
		wp_enqueue_script(
			'cotlas-dark-mode',
			$url . 'assets/js/dark-mode.js',
			array(),
			(string) filemtime( $script ),
			false
		);
		wp_localize_script(
			'cotlas-dark-mode',
			'cwsDarkMode',
			array( 'defaultMode' => cotlas_dark_mode_default_mode() )
		);
	}

	$css = 'html.cws-dark-mode{color-scheme:dark;}';
	$custom = (string) get_option( 'cotlas_dark_mode_css', '' );
	if ( '' !== trim( $custom ) ) {
		$css .= "\n" . $custom;
	}

	wp_register_style( 'cotlas-dark-mode', false, array(), '1.0.0' );
	wp_enqueue_style( 'cotlas-dark-mode' );
	wp_add_inline_style( 'cotlas-dark-mode', $css );
}
add_action( 'wp_enqueue_scripts', 'cotlas_dark_mode_enqueue_assets', 20 );
