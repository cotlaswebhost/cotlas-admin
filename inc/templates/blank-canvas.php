<?php
/**
 * Template Name: Blank Canvas
 * Description: Completely blank — no header, footer, sidebar, or theme chrome.
 *              Only page content is rendered. All WP assets (wp_head/wp_footer) are kept.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'blank-canvas' ); ?>>
<?php wp_body_open(); ?>
<div id="blank-canvas-wrap">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
	endif;
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
