<?php
/**
 * Whitelabel — branding overrides for the Cotlas Admin plugin.
 *
 * Provides the option helpers consumed across the plugin (login pages,
 * authentication template, admin sidebar logo, dashboard footer credits
 * and promo widget) and injects the sidebar logo override CSS.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Legacy footer credits shown when no whitelabel value has been saved yet.
 *
 * @return string
 */
function cotlas_whitelabel_default_footer_text() {
	return 'Fueled by <a href="https://cotlas.net" target="_blank">Cotlas Web Solutions</a> | Designed by <a href="https://cotlas.net/author/vinay404" target="_blank">Vinay Shukla</a> | Site Tutorials: <a href="https://teklog.in" target="_blank">Teklog</a>';
}

/**
 * Allowed HTML for the whitelabel footer credits field.
 *
 * @return array
 */
function cotlas_whitelabel_allowed_html() {
	return array(
		'a'      => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'u'      => array(),
		'span'   => array(
			'class' => array(),
			'style' => array(),
		),
		'div'    => array(
			'class' => array(),
			'style' => array(),
		),
		'img'    => array(
			'src'    => array(),
			'alt'    => array(),
			'class'  => array(),
			'width'  => array(),
			'height' => array(),
		),
	);
}

/**
 * Resolve a whitelabel image option, falling back to a bundled plugin image.
 *
 * @param string $option      Option name holding the image URL.
 * @param string $default_file Filename inside assets/img/ to fall back to (empty = no fallback).
 * @return string Image URL or empty string.
 */
function cotlas_whitelabel_image( $option, $default_file = '' ) {
	$value = (string) get_option( $option, '' );

	if ( '' !== trim( $value ) ) {
		return $value;
	}

	if ( '' === $default_file ) {
		return '';
	}

	return plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/' . $default_file;
}

/**
 * Inject CSS that swaps the admin sidebar logo (expanded + collapsed states).
 *
 * Attached to admin_enqueue_scripts after admin-ui.php enqueues
 * cotlas-admin-style so the inline rules print after the base stylesheet.
 */
function cotlas_whitelabel_sidebar_logo_css() {
	$full = get_option( 'cotlas_whitelabel_sidebar_logo', '' );
	$mini = get_option( 'cotlas_whitelabel_sidebar_logo_collapsed', '' );
	$hide = get_option( 'cotlas_whitelabel_hide_sidebar_logo', 0 );

	$css = '';

	if ( $hide ) {
		$css .= 'body.cotlas-admin-light #adminmenuwrap:before{display:none!important}';
	} else {
		if ( $full ) {
			$css .= 'body.cotlas-admin-light #adminmenuwrap:before{background-image:url("' . esc_url( $full ) . '")}';
		}
		if ( $mini ) {
			$css .= 'body.cotlas-admin-light.folded #adminmenuwrap:before{background-image:url("' . esc_url( $mini ) . '")}';
			$css .= '@media screen and (max-width:960px){body.cotlas-admin-light #adminmenuwrap:before{background-image:url("' . esc_url( $mini ) . '")}}';
		}
	}

	if ( '' !== $css ) {
		wp_add_inline_style( 'cotlas-admin-style', $css );
	}
}
add_action( 'admin_enqueue_scripts', 'cotlas_whitelabel_sidebar_logo_css', 20 );
