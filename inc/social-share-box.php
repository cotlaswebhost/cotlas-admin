<?php
/**
 * Social Share Box — "Share this now" box on the post edit screen.
 *
 * Classic editor: rendered just below the post title (one line: label + icons)
 * and in a sidebar meta box above the Publish box.
 * Block editor: shown in the publish-confirmation panel (PluginPostPublishPanel)
 * right after publishing.
 *
 * Reuses the [social_share] shortcode with the networks and short-link
 * options configured under Cotlas Admin → Social Media → Social share box.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'cotlas_share_box_setup' );
add_action( 'admin_enqueue_scripts', 'cotlas_share_box_admin_assets' );
add_action( 'wp_ajax_cotlas_share_box_render', 'cotlas_share_box_ajax_render' );

/**
 * Register the box depending on the active editor.
 * Block editor: no meta box — shown via the publish panel instead.
 * Classic editor: meta box in the sidebar (above Publish) + below the title.
 */
function cotlas_share_box_setup() {
	if ( ! get_option( 'cotlas_share_box_enabled', 0 ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	// Block editor: no meta box — shown via PluginPostPublishPanel after publish.
	if ( ! empty( $screen->is_block_editor ) ) {
		return;
	}

	// Classic editor: render below the title only.
	add_action( 'edit_form_after_title', 'cotlas_share_box_classic_html' );
}

/**
 * Determine whether a post is published.
 */
function cotlas_share_box_is_published( $post ) {
	if ( is_numeric( $post ) ) {
		$post = get_post( $post );
	}

	if ( ! is_object( $post ) || empty( $post->ID ) ) {
		if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && ! empty( $GLOBALS['post']->ID ) ) {
			$post = $GLOBALS['post'];
		} elseif ( isset( $_GET['post'] ) ) {
			$post = get_post( intval( $_GET['post'] ) );
		} elseif ( isset( $_GET['post_id'] ) ) {
			$post = get_post( intval( $_GET['post_id'] ) );
		}
	}

	if ( ! is_object( $post ) || empty( $post->ID ) ) {
		return false;
	}

	$status = $post->post_status;
	if ( 'inherit' === $status && ! empty( $post->post_parent ) ) {
		$status = get_post_status( $post->post_parent );
	}

	return ! in_array( $status, array( 'auto-draft', 'draft', 'pending', 'trash' ), true );
}

/**
 * Block editor meta box callback — published posts only.
 */
function cotlas_share_box_meta_box_html( $post ) {
	if ( ! cotlas_share_box_is_published( $post ) ) {
		echo '<p><em>' . esc_html__( 'Share buttons appear once the post is published.', 'cotlas-admin' ) . '</em></p>';
		return;
	}

	cotlas_share_box_output( $post );
}

/**
 * Classic editor callback — below the title.
 */
function cotlas_share_box_classic_html( $post ) {
	if ( ! cotlas_share_box_is_published( $post ) ) {
		echo '<p><em>' . esc_html__( 'Share buttons appear once the post is published.', 'cotlas-admin' ) . '</em></p>';
		return;
	}

	cotlas_share_box_output( $post );
}

/**
 * Render the share box: label + icons (via the plugin's own [social_share]
 * shortcode) + the share URL with a copy button.
 */
function cotlas_share_box_output( $post ) {
	if ( ! is_object( $post ) || ! shortcode_exists( 'social_share' ) ) {
		return;
	}

	$available = array( 'facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'threads', 'copy', 'print' );

	$networks = array();
	foreach ( $available as $slug ) {
		if ( get_option( 'cotlas_share_box_' . $slug, 1 ) ) {
			$networks[] = $slug;
		}
	}
	if ( empty( $networks ) ) {
		$networks = $available;
	}

	$use_shortlink = (bool) get_option( 'cotlas_share_box_shortlink', 0 );

	$shortcode_atts = 'networks="' . esc_attr( implode( ',', $networks ) ) . '" size="24"';
	if ( $use_shortlink ) {
		$shortcode_atts .= ' urlstructure="short"';
	}
	$buttons = do_shortcode( '[social_share ' . $shortcode_atts . ']' );

	$custom_css = get_option( 'cotlas_share_box_css', '' );
	?>
	<style id="cotlas-share-box-style">
	.cotlas-share-box{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 10px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
	.cotlas-share-box-label{font-size:13px;font-weight:600;color:#1d2327;white-space:nowrap}
	.cotlas-share-box-icons{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
	.cotlas-share-box-icons .cotlas-social-share{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
	.cotlas-share-box-icons a{display:inline-flex;align-items:center;text-decoration:none}
	.cotlas-share-box-icons svg{width:24px;height:24px;display:block}
	.cotlas-social-share{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
	.cotlas-share-box-icons .facebook svg{color:#1877F2;fill:currentColor}
	.cotlas-share-box-icons .twitter svg{color:#000000;fill:currentColor}
	.cotlas-share-box-icons .linkedin svg{color:#0A66C2;fill:currentColor}
	.cotlas-share-box-icons .whatsapp svg{color:#0DA946;fill:currentColor}
	.cotlas-share-box-icons .telegram svg{color:#26A5E4;fill:currentColor}
	.cotlas-share-box-icons .pinterest svg{color:#E60023;fill:currentColor}
	.cotlas-share-box-icons .reddit svg{color:#FF4500;fill:currentColor}
	.cotlas-share-box-icons .threads svg{color:#000000;fill:currentColor}
	.cotlas-share-box-icons .copy-link svg{color:#089EE9;fill:currentColor}
	.cotlas-share-box-icons .print svg{color:#9FC80B;fill:currentColor}
	</style>
	<div class="cotlas-share-box">
		<span class="cotlas-share-box-label"><?php esc_html_e( 'Share this now:', 'cotlas-admin' ); ?></span>
		<div class="cotlas-share-box-icons"><?php echo $buttons; ?></div>
	</div>
	<?php
	if ( '' !== trim( (string) $custom_css ) ) {
		echo '<style id="cotlas-share-box-custom-style">' . wp_strip_all_tags( $custom_css ) . '</style>';
	}
	?>
	<?php
}

/**
 * Enqueue base styles and the publish-panel / copy-handler script.
 */
function cotlas_share_box_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	// --- CSS ---
	$base = '.cotlas-share-box{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 10px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}'
		. '.cotlas-share-box-label{font-size:13px;font-weight:600;color:#1d2327;white-space:nowrap}'
		. '.cotlas-share-box-icons{display:flex;align-items:center;gap:12px;flex-wrap:wrap}'
		. '.cotlas-share-box-icons .cotlas-social-share{display:flex;align-items:center;gap:12px;flex-wrap:wrap}'
		. '.cotlas-share-box-icons a{display:inline-flex;align-items:center;text-decoration:none}'
		. '.cotlas-share-box-icons svg{width:24px;height:24px;display:block}'
		. '.cotlas-social-share{display:flex;align-items:center;gap:12px;flex-wrap:wrap}'
		. '.cotlas-share-box-url{display:flex;align-items:center;gap:6px;width:100%}'
		. '.cotlas-share-box-url input{flex:1;min-width:0;font-size:12px;padding:5px 8px;border:1px solid #dcdcde;border-radius:4px;color:#50575e;background:#f6f7f7}'
		. '.cotlas-share-box-url .button{font-size:12px;padding:4px 10px}'
		. '.cotlas-share-box-icons .facebook svg{color:#1877F2;fill:currentColor}'
		. '.cotlas-share-box-icons .twitter svg{color:#000000;fill:currentColor}'
		. '.cotlas-share-box-icons .linkedin svg{color:#0A66C2;fill:currentColor}'
		. '.cotlas-share-box-icons .whatsapp svg{color:#0DA946;fill:currentColor}'
		. '.cotlas-share-box-icons .telegram svg{color:#26A5E4;fill:currentColor}'
		. '.cotlas-share-box-icons .pinterest svg{color:#E60023;fill:currentColor}'
		. '.cotlas-share-box-icons .reddit svg{color:#FF4500;fill:currentColor}'
		. '.cotlas-share-box-icons .threads svg{color:#000000;fill:currentColor}'
		. '.cotlas-share-box-icons .copy-link svg{color:#089EE9;fill:currentColor}'
		. '.cotlas-share-box-icons .print svg{color:#9FC80B;fill:currentColor}';

	wp_add_inline_style( 'common', $base );

	$custom = get_option( 'cotlas_share_box_css', '' );
	if ( '' !== trim( (string) $custom ) ) {
		wp_add_inline_style( 'common', wp_strip_all_tags( $custom ) );
	}

	// When the share box is active, hide Yoast SEO's own "Share your post!"
	// sidebar panel to avoid duplicate share buttons.
	if ( get_option( 'cotlas_share_box_enabled', 0 ) ) {
		wp_add_inline_style( 'common', '.yoast-seo-sidebar-panel{display:none !important;}' );
	}

	// --- JS ---
	$nonce = wp_create_nonce( 'cotlas_share_box_render' );

	$js = "(function(){"
		. "if(typeof wp!=='undefined'&&wp.plugins&&wp.plugins.registerPlugin&&wp.editPost&&wp.editPost.PluginPostPublishPanel&&wp.element){"
		. "var el=wp.element.createElement;var useState=wp.element.useState;var useEffect=wp.element.useEffect;var registerPlugin=wp.plugins.registerPlugin;var PluginPostPublishPanel=wp.editPost.PluginPostPublishPanel;"
		. "function CotlasShareBoxPanelContent(){var state=useState('');var html=state[0];var setHtml=state[1];useEffect(function(){var store=wp.data.select('core/editor');var postId=(store&&typeof store.getCurrentPostId==='function')?store.getCurrentPostId():0;if(!postId){return;}var fd=new FormData();fd.append('action','cotlas_share_box_render');fd.append('post_id',postId);fd.append('nonce','" . esc_js( $nonce ) . "');fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){if(res&&res.success&&res.data&&res.data.html){setHtml(res.data.html);}});},[]);if(!html){return null;}return el('div',{dangerouslySetInnerHTML:{__html:html}});}"
		. "registerPlugin('cotlas-share-box-publish-panel',{render:function(){return el(PluginPostPublishPanel,{title:'Share this post',initialOpen:true},el(CotlasShareBoxPanelContent,null));}});"
		. "}"
		. "})();";

	wp_add_inline_script( 'wp-edit-post', $js );
}

/**
 * AJAX handler: render the share box for a published post.
 */
function cotlas_share_box_ajax_render() {
	check_ajax_referer( 'cotlas_share_box_render', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => 'No post ID.' ) );
	}

	if ( ! cotlas_share_box_is_published( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Not published.' ) );
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$post = get_post( $post_id );

	ob_start();
	cotlas_share_box_output( $post );
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}