<?php
/**
 * Frontend font resizer: "[cotlas_font_resizer]" shortcode and {{font_resizer}}
 * dynamic tag markup, backed by assets/js/font-resizer.js.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Font resizer button markup.
 *
 * @return string
 */
function cotlas_font_resizer_html() {
	return '<div class="wnav-holder font-resizer">'
		. '<a href="#" role="button" class="font-resizer-trigger" data-title="' . esc_attr__( 'Font Resizer', 'cotlas-admin' ) . '">'
		. '<span class="screen-reader-text">' . esc_html__( 'Font Resizer', 'cotlas-admin' ) . '</span><strong>Aa</strong>'
		. '</a></div>';
}

/**
 * Shortcode: [cotlas_font_resizer]
 *
 * @return string
 */
function cotlas_font_resizer_shortcode() {
	if ( ! get_option( 'cotlas_font_resizer_enabled', 0 ) ) {
		return '';
	}

	return cotlas_font_resizer_html();
}
add_shortcode( 'cotlas_font_resizer', 'cotlas_font_resizer_shortcode' );

/**
 * Enqueue the font resizer script.
 */
function cotlas_font_resizer_enqueue_assets() {
	if ( ! get_option( 'cotlas_font_resizer_enabled', 0 ) ) {
		return;
	}

	$dir    = plugin_dir_path( dirname( __FILE__ ) );
	$url    = plugin_dir_url( dirname( __FILE__ ) );
	$script = $dir . 'assets/js/font-resizer.js';

	if ( ! file_exists( $script ) ) {
		return;
	}

	$target = (string) get_option( 'cotlas_font_resizer_target', '.entry-content' );
	if ( '' === trim( $target ) ) {
		$target = '.entry-content';
	}

	wp_enqueue_script(
		'cotlas-font-resizer',
		$url . 'assets/js/font-resizer.js',
		array(),
		(string) filemtime( $script ),
		true
	);
	wp_localize_script(
		'cotlas-font-resizer',
		'cwsFontResizer',
		array( 'target' => $target )
	);
}
add_action( 'wp_enqueue_scripts', 'cotlas_font_resizer_enqueue_assets', 20 );
