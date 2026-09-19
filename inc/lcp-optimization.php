<?php
/**
 * Always-on LCP performance tweaks.
 *
 * These two are not tied to the Image Optimisation module toggle: they only
 * rewrite lazy loading on the first image and print a small critical CSS block
 * on the home/front page, and they were previously duplicated in the child
 * theme. Keep them here so every site gets the same behaviour regardless of
 * whether Image Optimisation is enabled.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// Remove lazy loading from the first image on the frontend output.
add_action( 'template_redirect', function() {
	ob_start( function( $html ) {
		// Match the first image tag with loading="lazy".
		$html = preg_replace( '/<img([^>]+)loading=("|\')lazy("|\')([^>]*)>/i', '<img$1loading="eager"$4 fetchpriority="high">', $html, 1 );
		return $html;
	} );
} );

// Critical CSS to force the LCP image to render early.
add_action( 'wp_head', function() {
	if ( is_home() || is_front_page() ) {
		?>
		<style id="critical-lcp-css">
		/* Force LCP image to render in initial viewport */
		.gb-query-loop-container .gb-grid-column:first-child,
		.gb-query-loop-container .gb-grid-column:first-child .style-big-image {
			content-visibility: auto;
			contain-intrinsic-size: 768px 402px;
		}

		/* Ensure LCP image is in the viewport */
		.style-big-image {
			display: block !important;
			width: 100% !important;
			height: auto !important;
			max-width: 768px !important;
			aspect-ratio: 768/402 !important;
		}

		/* Remove any lazy loading for LCP image */
		.gb-media-b463938c.style-big-image {
			loading: eager !important;
		}
		</style>
		<?php
	}
}, 1 );
