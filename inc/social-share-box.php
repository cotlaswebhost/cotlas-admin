<?php
/**
 * Social Share Box — "Share this now" box on the post edit screen.
 *
 * Classic editor: rendered just below the post title (one line: label + icons).
 * Block editor: rendered in a sidebar meta box, published posts only.
 *
 * Reuses the [social_share] shortcode with the networks and short-link
 * options configured under Cotlas Admin → Social Media → Social share box.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'cotlas_share_box_setup' );

/**
 * Register the box depending on the active editor.
 * Block editor: side meta box. Classic editor: below the title.
 */
function cotlas_share_box_setup() {
	if ( ! get_option( 'cotlas_share_box_enabled', 0 ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( ! empty( $screen->is_block_editor ) ) {
		add_meta_box(
			'cotlas-share-box',
			__( 'Share this post', 'cotlas-admin' ),
			'cotlas_share_box_meta_box_html',
			null,
			'side',
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
		return;
	}

	// Classic editor — just below the title.
	add_action( 'edit_form_after_title', 'cotlas_share_box_classic_html' );
}

/**
 * Block editor meta box callback — published posts only.
 */
function cotlas_share_box_meta_box_html( $post ) {
	if ( 'publish' !== get_post_status( $post ) ) {
		echo '<p><em>' . esc_html__( 'Share buttons appear once the post is published.', 'cotlas-admin' ) . '</em></p>';
		return;
	}

	cotlas_share_box_output( $post );
}

/**
 * Classic editor callback — below the title, any post status.
 */
function cotlas_share_box_classic_html( $post ) {
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

	$share_url = $use_shortlink ? wp_get_shortlink( $post->ID ) : get_permalink( $post->ID );
	if ( ! $share_url ) {
		$share_url = home_url( '?p=' . $post->ID );
	}

	$shortcode_atts = 'networks="' . esc_attr( implode( ',', $networks ) ) . '" size="24"';
	if ( $use_shortlink ) {
		$shortcode_atts .= ' urlstructure="short"';
	}
	$buttons = do_shortcode( '[social_share ' . $shortcode_atts . ']' );

	$custom_css = get_option( 'cotlas_share_box_css', '' );
	?>
	<style id="cotlas-share-box-style">
	.cotlas-share-box{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
	.cotlas-share-box-label{font-size:13px;font-weight:600;color:#1d2327;white-space:nowrap}
	.cotlas-share-box-icons{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
	.cotlas-share-box-icons .cotlas-social-share{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
	.cotlas-share-box-icons a{display:inline-flex;align-items:center;text-decoration:none}
	.cotlas-share-box-icons svg{width:24px;height:24px;display:block}
	.cotlas-share-box-url{display:flex;align-items:center;gap:6px;width:100%}
	.cotlas-share-box-url input{flex:1;min-width:0;font-size:12px;padding:5px 8px;border:1px solid #dcdcde;border-radius:4px;color:#50575e;background:#f6f7f7}
	.cotlas-share-box-url .button{font-size:12px;padding:4px 10px}
	</style>
	<div class="cotlas-share-box">
		<span class="cotlas-share-box-label"><?php esc_html_e( 'Share this now:', 'cotlas-admin' ); ?></span>
		<div class="cotlas-share-box-icons"><?php echo $buttons; ?></div>
		<div class="cotlas-share-box-url">
			<input type="text" readonly onfocus="this.select()" value="<?php echo esc_url( $share_url ); ?>" aria-label="<?php esc_attr_e( 'Share URL', 'cotlas-admin' ); ?>">
			<button type="button" class="button cotlas-share-box-copy" data-url="<?php echo esc_url( $share_url ); ?>"><?php esc_html_e( 'Copy', 'cotlas-admin' ); ?></button>
		</div>
	</div>
	<?php
	if ( '' !== trim( (string) $custom_css ) ) {
		echo '<style id="cotlas-share-box-custom-style">' . wp_strip_all_tags( $custom_css ) . '</style>';
	}
	?>
	<script>
	( function() {
		var btn = document.querySelector( '.cotlas-share-box-copy' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function() {
			var url = btn.getAttribute( 'data-url' );
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url );
			} else {
				var input = btn.previousElementSibling;
				if ( input ) { input.select(); }
				try { document.execCommand( 'copy' ); } catch ( e ) {}
			}
			var original = btn.textContent;
			btn.textContent = '<?php echo esc_js( __( 'Copied!', 'cotlas-admin' ) ); ?>';
			setTimeout( function() { btn.textContent = original; }, 2000 );
		} );
	} )();
	</script>
	<?php
}
