<?php
/**
 * Template Name: Authentication Template
 * Template Post Type: page
 * Description: Completely blank wrapper — renders your exact HTML containers and CSS 
 *              classes natively around your shortcode content while removing headers/footers.
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
	<div class="page-section" style="--inline-bg-image: url(https://news96india.test)">
		<div class="page-wrapper">
			
			<!-- Brand/Logo Header Section (Dynamic Customizer Logo) -->
			<div class="brand-inner-container">
				<div class="brand-container">
					<?php 
					if ( has_custom_logo() ) {
						// Get the custom logo image ID set in the Customizer
						$custom_logo_id = get_theme_mod( 'custom_logo' );
						$logo_img_data  = wp_get_attachment_image_src( $custom_logo_id, 'full' );
						
						if ( ! empty( $logo_img_data[0] ) ) {
							?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
								<img decoding="async" class="brand-logo" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" src="<?php echo esc_url( $logo_img_data[0] ); ?>">
							</a>
							<?php
						}
					} else {
						// Fallback to text link if no logo is uploaded in customizer
						?>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-logo-text">
							<?php bloginfo( 'name' ); ?>
						</a>
						<?php
					}
					?>
				</div>
			</div>

			<!-- Dynamic Login Widget / Content Container -->
			<div class="inner-container">
				<div class="auth-container">
					<?php
					if ( have_posts() ) :
						while ( have_posts() ) :
							the_post();
							// Automatically displays the login widget shortcode placed on the WordPress Page backend
							the_content();
						endwhile;
					endif;
					?>
				</div>
			</div>

		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
