<?php
/**
 * Security hardening: email domain restriction, XMLRPC disable, MIME types,
 * version fingerprint removal, admin bar/footer customisations.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

function is_valid_email_domain($login, $email, $errors ){
    $valid_email_domains = array("gmail.com","yahoo.com","yahoo.co.in","yahoo.co.uk","outlook.com","hotmail.com","live.com");// whitelist
    $valid = false;
    foreach( $valid_email_domains as $d ){
        $d_length = strlen( $d );
        $current_email_domain = strtolower( substr( $email, -($d_length), $d_length));
        if( $current_email_domain == strtolower($d) ){
            $valid = true;
            break;
        }
    }
    // if invalid, return error
    if( $valid === false ){
        $errors->add('domain_whitelist_error',__( '<strong>ERROR</strong>: you can only register using gmail, yahoo, outlook, hotmail or live' ));
    }
}

add_action('register_post', 'is_valid_email_domain',10,3 );


add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter('upload_mimes','restrict_mime'); 
function restrict_mime($mimes) { 
$mimes = array( 
                'jpg|jpeg|jpe' => 'image/jpeg', 
                'gif' => 'image/gif', 
			    'png' => 'image/png',
  				'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
  				'avif' => 'image/avif',
  				'pdf' => 'application/pdf',
                'mp3' => 'audio/mpeg',
  				'txt' => 'text/plain',
);
return $mimes;
}



function wps_login_error() {
		  remove_action('login_head', 'wp_shake_js', 12);
		}
add_action('login_head', 'wps_login_error');


add_filter( 'show_admin_bar', '__return_false' );


// ---------------------------------------------------------------------------

add_filter('the_generator', '__return_empty_string');

function shapeSpace_remove_version_scripts_styles($src) {
	if (strpos($src, 'ver=') !== false) {
		$src = remove_query_arg('ver', $src);
	}
	return $src;
}
add_filter('style_loader_src', 'shapeSpace_remove_version_scripts_styles', 9999);
add_filter('script_loader_src', 'shapeSpace_remove_version_scripts_styles', 9999);

remove_action('welcome_panel', 'wp_welcome_panel');

add_filter('widget_text','do_shortcode');

add_action('wp_dashboard_setup', 'custom_hide_widgets');
function custom_hide_widgets() {
    	global $wp_meta_boxes;
   	
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_activity'])) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_activity']);
    }
    
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_right_now'])) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['side']['dashboard_right_now']);    
    }
}

remove_action('wp_head', 'wp_generator');

function replace_howdy_with_your_text( $wp_admin_bar ) {
  $account_info = $wp_admin_bar->get_node( 'my-account' );
  
  // Check if the node exists and has a title
  if ( $account_info && isset( $account_info->title ) ) {
      $your_title = str_replace( 'Howdy,', 'Welcome', $account_info->title );
      $wp_admin_bar->add_node( array(
          'id'    => 'my-account',
          'title' => $your_title,
      ) );
  }
}
// Use a higher priority to ensure the node exists (optional, but 25 should work)
add_action( 'admin_bar_menu', 'replace_howdy_with_your_text', 25 );

function remove_footer_admin () {
 
    echo 'Fueled by <a href="https://cotlas.net" target="_blank">Cotlas Web Solutions</a> | Designed by <a href="https://cotlas.net/author/vinay404" target="_blank">Vinay Shukla</a> | Site Tutorials: <a href="https://teklog.in" target="_blank">Teklog</a></p>';
     
    }
     
    add_filter('admin_footer_text', 'remove_footer_admin');
