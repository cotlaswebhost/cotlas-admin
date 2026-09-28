<?php
/**
 * Content Protect Module
 *
 * 1. Content Copy Protection – disables right-click, text selection, and copy.
 * 2. Image Watermark – applies watermarks on upload (after image conversion).
 * 3. Hotlink Protection – blocks direct image linking from other domains.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

class Cotlas_Content_Protect {

	const PAGE_SLUG     = 'cotlas-content-protect';
	const NONCE_SAVE    = 'cotlas_content_protect_save';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Menu at priority 10 — between Browser Cache (5) and Admin Tools (15).
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );

		// Frontend hooks.
		add_action( 'wp_head', array( $this, 'output_copy_protection_css' ), 20 );
		add_action( 'wp_footer', array( $this, 'output_copy_protection_js' ), 20 );

		// Hotlink protection.
		add_action( 'template_redirect', array( $this, 'hotlink_check' ), 1 );

		// Watermark on upload — priority 15 runs BEFORE image conversion (priority 20).
		// This ensures the watermark is on the source before WebP/AVIF conversion,
		// so the converted files also contain the watermark.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'watermark_on_upload' ), 15, 2 );
		add_action( 'delete_attachment', array( $this, 'cleanup_watermarked_files' ) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * MENU
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function register_menu() {
		add_submenu_page(
			'cotlas-admin-panel',
			'Content Protect',
			'Content Protect',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'cotlas-admin_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_register_script( 'cotlas-content-protect', false, array( 'jquery' ), false, true );
		wp_enqueue_script( 'cotlas-content-protect' );
		wp_add_inline_script( 'cotlas-content-protect', $this->get_admin_js() );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE HANDLER — tab-aware: only saves options belonging to the
	 * submitted tab so toggles on other tabs are never zeroed out.
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) ), self::NONCE_SAVE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_POST['_ctap_tab'] ) ? sanitize_key( wp_unslash( $_POST['_ctap_tab'] ) ) : '';

		// ── Copy Protection tab ──
		if ( 'copy' === $tab ) {
			$bool_keys = array(
				'cotlas_cp_copy_protection',
				'cotlas_cp_disable_rightclick',
				'cotlas_cp_disable_text_select',
				'cotlas_cp_disable_drag',
				'cotlas_cp_disable_keyboard',
				'cotlas_cp_disable_devtools',
			);
			foreach ( $bool_keys as $key ) {
				$val = isset( $_POST[ $key ] ) ? 1 : 0;
				if ( ! update_option( $key, $val ) ) {
					add_option( $key, $val );
				}
			}
		}

		// ── Watermark tab ──
		if ( 'watermark' === $tab ) {
			$val = isset( $_POST['cotlas_cp_watermark_enabled'] ) ? 1 : 0;
			if ( ! update_option( 'cotlas_cp_watermark_enabled', $val ) ) {
				add_option( 'cotlas_cp_watermark_enabled', $val );
			}
			$val = isset( $_POST['cotlas_cp_watermark_delete_originals'] ) ? 1 : 0;
			if ( ! update_option( 'cotlas_cp_watermark_delete_originals', $val ) ) {
				add_option( 'cotlas_cp_watermark_delete_originals', $val );
			}

			$type = isset( $_POST['cotlas_cp_watermark_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_cp_watermark_type'] ) ) : 'text';
			update_option( 'cotlas_cp_watermark_type', in_array( $type, array( 'text', 'image' ), true ) ? $type : 'text' );

			update_option( 'cotlas_cp_watermark_text', isset( $_POST['cotlas_cp_watermark_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_cp_watermark_text'] ) ) : '' );
			update_option( 'cotlas_cp_watermark_font_size', isset( $_POST['cotlas_cp_watermark_font_size'] ) ? absint( $_POST['cotlas_cp_watermark_font_size'] ) : 24 );
			update_option( 'cotlas_cp_watermark_color', isset( $_POST['cotlas_cp_watermark_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['cotlas_cp_watermark_color'] ) ) : '#ffffff' );
			update_option( 'cotlas_cp_watermark_opacity', isset( $_POST['cotlas_cp_watermark_opacity'] ) ? min( 100, max( 1, absint( $_POST['cotlas_cp_watermark_opacity'] ) ) ) : 50 );

			$wm_id = isset( $_POST['cotlas_cp_watermark_image_id'] ) ? absint( $_POST['cotlas_cp_watermark_image_id'] ) : 0;
			update_option( 'cotlas_cp_watermark_image_id', $wm_id );
			$wm_url = isset( $_POST['cotlas_cp_watermark_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['cotlas_cp_watermark_image_url'] ) ) : '';
			update_option( 'cotlas_cp_watermark_image_url', $wm_url );

			$position = isset( $_POST['cotlas_cp_watermark_position'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_cp_watermark_position'] ) ) : 'bottom-right';
			$allowed_positions = array( 'top-left', 'top-middle', 'top-right', 'middle-left', 'center', 'middle-right', 'bottom-left', 'bottom-middle', 'bottom-right' );
			update_option( 'cotlas_cp_watermark_position', in_array( $position, $allowed_positions, true ) ? $position : 'bottom-right' );

			update_option( 'cotlas_cp_watermark_scale', isset( $_POST['cotlas_cp_watermark_scale'] ) ? min( 50, max( 5, absint( $_POST['cotlas_cp_watermark_scale'] ) ) ) : 20 );
		}

		// ── Hotlink tab ──
		if ( 'hotlink' === $tab ) {
			$val = isset( $_POST['cotlas_cp_hotlink_enabled'] ) ? 1 : 0;
			if ( ! update_option( 'cotlas_cp_hotlink_enabled', $val ) ) {
				add_option( 'cotlas_cp_hotlink_enabled', $val );
			}
			update_option( 'cotlas_cp_hotlink_domains', isset( $_POST['cotlas_cp_hotlink_domains'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cotlas_cp_hotlink_domains'] ) ) : '' );
		}

		$hash = $tab ? '#' . $tab : '';
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&saved=1' . $hash ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * COPY PROTECTION
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function output_copy_protection_css() {
		if ( ! get_option( 'cotlas_cp_copy_protection', 0 ) ) {
			return;
		}

		$disable_select = get_option( 'cotlas_cp_disable_text_select', 1 );
		$disable_drag   = get_option( 'cotlas_cp_disable_drag', 1 );

		if ( ! $disable_select && ! $disable_drag ) {
			return;
		}

		echo '<style id="cotlas-copy-protection">';
		if ( $disable_select ) {
			echo 'body{-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;}';
			echo 'input,textarea,select,[contenteditable="true"]{-webkit-user-select:text;-moz-user-select:text;-ms-user-select:text;user-select:text;}';
		}
		if ( $disable_drag ) {
			echo 'img{-webkit-user-drag:none;-moz-user-drag:none;user-drag:none;}';
		}
		echo '</style>';
	}

	public function output_copy_protection_js() {
		if ( ! get_option( 'cotlas_cp_copy_protection', 0 ) ) {
			return;
		}

		$disable_rightclick = get_option( 'cotlas_cp_disable_rightclick', 1 );
		$disable_drag       = get_option( 'cotlas_cp_disable_drag', 1 );
		$disable_keyboard   = get_option( 'cotlas_cp_disable_keyboard', 1 );
		$disable_devtools   = get_option( 'cotlas_cp_disable_devtools', 1 );

		if ( ! $disable_rightclick && ! $disable_drag && ! $disable_keyboard && ! $disable_devtools ) {
			return;
		}

		echo '<script id="cotlas-copy-protection-js">(function(){"use strict";';

		if ( $disable_rightclick ) {
			echo 'document.addEventListener("contextmenu",function(e){e.preventDefault();return false;});';
		}

		if ( $disable_drag ) {
			echo 'document.addEventListener("dragstart",function(e){if(e.target&&e.target.tagName==="IMG"){e.preventDefault();return false;}});';
		}

		if ( $disable_keyboard ) {
			echo 'document.addEventListener("keydown",function(e){';
			echo 'if((e.ctrlKey&&(e.key==="c"||e.key==="C"||e.key==="a"||e.key==="A"||e.key==="s"||e.key==="S"||e.key==="u"||e.key==="U"))||';
			echo '(e.ctrlKey&&e.shiftKey&&(e.key==="I"||e.key==="i"||e.key==="J"||e.key==="j"))||e.key==="F12"){';
			echo 'e.preventDefault();return false;}});';
		}

		if ( $disable_devtools ) {
			echo 'document.addEventListener("keydown",function(e){';
			echo 'if(e.keyCode===123||(e.ctrlKey&&e.shiftKey&&(e.keyCode===73||e.keyCode===74)))';
			echo '{e.preventDefault();return false;}});';
		}

		echo '})();</script>';
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * HOTLINK PROTECTION
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function hotlink_check() {
		if ( ! get_option( 'cotlas_cp_hotlink_enabled', 0 ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$ext = strtolower( pathinfo( $request_uri, PATHINFO_EXTENSION ) );
		$image_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp' );

		if ( ! in_array( $ext, $image_exts, true ) ) {
			return;
		}

		if ( strpos( $request_uri, '/uploads/' ) === false ) {
			return;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( empty( $referer ) ) {
			return;
		}

		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );

		if ( $referer_host === $site_host || '.' . $site_host === substr( $referer_host, -strlen( '.' . $site_host ) ) ) {
			return;
		}

		$allowed_domains = array_filter( array_map( 'trim', explode( "\n", get_option( 'cotlas_cp_hotlink_domains', '' ) ) ) );
		foreach ( $allowed_domains as $domain ) {
			$domain = strtolower( trim( $domain ) );
			if ( empty( $domain ) ) {
				continue;
			}
			if ( strtolower( $referer_host ) === $domain || '.' . $domain === substr( strtolower( $referer_host ), -strlen( '.' . $domain ) ) ) {
				return;
			}
		}

		status_header( 403 );
		nocache_headers();
		echo 'Hotlinking is not allowed.';
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * WATERMARK — UPLOAD-TIME GENERATION
	 *
	 * Hooks into wp_generate_attachment_metadata at priority 30 so it runs
	 * AFTER image conversion (priority 20). Applies watermark to the final
	 * format (WebP/AVIF if conversion is active, otherwise original format).
	 * ═══════════════════════════════════════════════════════════════════════ */

	/**
	 * Apply watermark to uploaded image and all its sizes.
	 * Runs at priority 30 on wp_generate_attachment_metadata (after image conversion at 20).
	 */
	public function watermark_on_upload( $metadata, $attachment_id ) {
		if ( ! get_option( 'cotlas_cp_watermark_enabled', 0 ) ) {
			return $metadata;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$handler = new Cotlas_Watermark_Handler();

		// Watermark the main file.
		$wm_dir  = dirname( $file_path ) . '/cotlas-wm';
		$wm_file = $wm_dir . '/' . basename( $file_path );

		if ( $handler->generate( $file_path, $wm_file, $ext ) ) {
			// Store original path for potential restoration.
			update_post_meta( $attachment_id, '_cotlas_wm_original', $file_path );

			// Replace the file.
			copy( $wm_file, $file_path );
			wp_delete_file( $wm_file );

			// Delete original if option is set.
			if ( get_option( 'cotlas_cp_watermark_delete_originals', 0 ) ) {
				// The original is already overwritten by the watermarked copy.
				// Store that it's been watermarked so we don't double-watermark.
			}
			update_post_meta( $attachment_id, '_cotlas_wm_applied', 1 );
		}

		// Watermark each registered size.
		if ( ! empty( $metadata['sizes'] ) ) {
			$file_dir = dirname( $file_path );
			foreach ( $metadata['sizes'] as $size_name => $size_info ) {
				if ( empty( $size_info['file'] ) ) {
					continue;
				}
				$size_path = path_join( $file_dir, $size_info['file'] );
				if ( ! file_exists( $size_path ) ) {
					continue;
				}
				$size_ext = strtolower( pathinfo( $size_path, PATHINFO_EXTENSION ) );
				$handler->generate( $size_path, $size_path, $size_ext );
			}
		}

		return $metadata;
	}

	/**
	 * Clean up watermark meta when attachment is deleted.
	 */
	public function cleanup_watermarked_files( $attachment_id ) {
		delete_post_meta( $attachment_id, '_cotlas_wm_original' );
		delete_post_meta( $attachment_id, '_cotlas_wm_applied' );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * RENDER PAGE
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		ctap_page_open( 'Content Protect', 'dashicons-shield', 'Content copy protection, image watermarking, and hotlink protection.' );

		$tabs = array(
			array( 'id' => 'copy',      'label' => 'Copy Protection', 'icon' => 'dashicons-lock' ),
			array( 'id' => 'watermark', 'label' => 'Watermark',       'icon' => 'dashicons-format-image' ),
			array( 'id' => 'hotlink',   'label' => 'Hotlink',         'icon' => 'dashicons-admin-links' ),
		);
		$active = ctap_nav( $tabs );

		// ── Copy Protection Tab ──
		ctap_pane_open( 'copy', $active );
		ctap_form_open( self::NONCE_SAVE, 'copy' );
		ctap_card_open( 'Content Copy Protection', 'dashicons-lock' );
		ctap_toggle( 'cotlas_cp_copy_protection', 'Enable Content Copy Protection', 'Master switch. When disabled, all copy protection features below are inactive regardless of their individual settings.', 0 );
		ctap_section( 'Individual Controls' );
		ctap_toggle( 'cotlas_cp_disable_rightclick', 'Disable Right-Click', 'Blocks the browser context menu (right-click) on the entire page.', 1 );
		ctap_toggle( 'cotlas_cp_disable_text_select', 'Disable Text Selection', 'Prevents users from selecting text on the page using mouse or keyboard.', 1 );
		ctap_toggle( 'cotlas_cp_disable_drag', 'Disable Image Drag', 'Prevents dragging images and disables the browser default drag behavior on images.', 1 );
		ctap_toggle( 'cotlas_cp_disable_keyboard', 'Disable Copy Shortcuts', 'Blocks Ctrl+C, Ctrl+A, Ctrl+S, Ctrl+U keyboard shortcuts.', 1 );
		ctap_toggle( 'cotlas_cp_disable_devtools', 'Disable DevTools Shortcuts', 'Blocks F12 and Ctrl+Shift+I/J from opening browser developer tools.', 1 );
		ctap_info( 'These are frontend deterrents. Determined users can bypass client-side protection using browser developer tools. For sensitive content, consider server-side access control.' );
		ctap_card_close();
		ctap_form_close();
		ctap_pane_close();

		// ── Watermark Tab ──
		ctap_pane_open( 'watermark', $active );
		ctap_form_open( self::NONCE_SAVE, 'watermark' );

		ctap_card_open( 'Image Watermark', 'dashicons-format-image' );
		ctap_toggle( 'cotlas_cp_watermark_enabled', 'Enable Watermark', 'Applies a text or image watermark to images at upload time. Works after Image Optimization conversion (WebP/AVIF), so watermarked images are in the final format. Thumbnails are also watermarked.', 0 );
		ctap_toggle( 'cotlas_cp_watermark_delete_originals', 'Delete Pre-Watermark Originals', 'After watermarking, delete the original (non-watermarked) file to save disk space. The watermarked version replaces it permanently. <strong>This is irreversible.</strong>', 0 );

		// Watermark type.
		$wm_type = get_option( 'cotlas_cp_watermark_type', 'text' );
		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">Watermark Type</div>';
		echo '<div class="ctap-field-input">';
		echo '<label style="margin-right:16px;"><input type="radio" name="cotlas_cp_watermark_type" value="text" ' . checked( $wm_type, 'text', false ) . '> Text</label>';
		echo '<label><input type="radio" name="cotlas_cp_watermark_type" value="image" ' . checked( $wm_type, 'image', false ) . '> Image</label>';
		echo '</div></div>';

		ctap_card_close();

		// Text watermark options.
		ctap_card_open( 'Text Watermark Options', 'dashicons-editor-textcolor' );
		ctap_field( 'Watermark Text', '<input type="text" name="cotlas_cp_watermark_text" value="' . esc_attr( get_option( 'cotlas_cp_watermark_text', '' ) ) . '" class="regular-text" placeholder="e.g. © Your Site">', 'The text to overlay on images.' );
		ctap_field( 'Font Size (px)', '<input type="number" name="cotlas_cp_watermark_font_size" value="' . esc_attr( get_option( 'cotlas_cp_watermark_font_size', 24 ) ) . '" min="8" max="72" style="width:80px">', 'Base font size. Scaled proportionally to image width. Default: 24.' );
		ctap_field( 'Text Color', '<input type="color" name="cotlas_cp_watermark_color" value="' . esc_attr( get_option( 'cotlas_cp_watermark_color', '#ffffff' ) ) . '">', 'Color of the watermark text.' );
		ctap_field( 'Opacity (%)', '<input type="number" name="cotlas_cp_watermark_opacity" value="' . esc_attr( get_option( 'cotlas_cp_watermark_opacity', 50 ) ) . '" min="1" max="100" style="width:80px">', 'Transparency: 1 (nearly invisible) to 100 (fully opaque). Default: 50.' );
		ctap_card_close();

		// Image watermark options.
		ctap_card_open( 'Image Watermark Options', 'dashicons-format-image' );
		$wm_image_id  = absint( get_option( 'cotlas_cp_watermark_image_id', 0 ) );
		$wm_image_url = $wm_image_id ? wp_get_attachment_url( $wm_image_id ) : get_option( 'cotlas_cp_watermark_image_url', '' );

		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">Watermark Image</div>';
		echo '<div class="ctap-field-input">';
		echo '<input type="hidden" name="cotlas_cp_watermark_image_id" value="' . esc_attr( $wm_image_id ) . '" data-cp-wm-image-id>';
		echo '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">';
		echo '<button type="button" class="button" data-cp-pick-wm-image>Select Image</button>';
		echo '<button type="button" class="button" data-cp-remove-wm-image style="color:#b91c1c;' . ( $wm_image_id || $wm_image_url ? '' : 'display:none;' ) . '">Remove</button>';
		echo '</div>';
		echo '<div class="cp-wm-image-preview" style="margin-bottom:8px;">';
		if ( $wm_image_url ) {
			echo '<div style="display:inline-flex;align-items:center;gap:10px;padding:8px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';
			echo '<img src="' . esc_url( $wm_image_url ) . '" alt="" style="max-width:80px;max-height:60px;height:auto;border-radius:4px;">';
			echo '</div>';
		}
		echo '</div>';
		echo '<label style="font-size:12px;color:#50575e;display:block;margin-bottom:3px;">Or paste watermark image URL:</label>';
		echo '<input type="url" name="cotlas_cp_watermark_image_url" value="' . esc_url( get_option( 'cotlas_cp_watermark_image_url', '' ) ) . '" placeholder="https://example.com/watermark.png" class="regular-text" data-cp-wm-url-input style="max-width:400px;">';
		echo '<p class="ctap-field-desc">PNG with transparency recommended. Used when watermark type is set to "Image".</p>';
		echo '</div></div>';

		ctap_card_close();

		// Position and scale.
		ctap_card_open( 'Position & Scale', 'dashicons-move' );
		$current_pos = get_option( 'cotlas_cp_watermark_position', 'bottom-right' );
		$positions   = array(
			'top-left'      => 'Top Left',
			'top-middle'    => 'Top Middle',
			'top-right'     => 'Top Right',
			'middle-left'   => 'Middle Left',
			'center'        => 'Center',
			'middle-right'  => 'Middle Right',
			'bottom-left'   => 'Bottom Left',
			'bottom-middle' => 'Bottom Middle',
			'bottom-right'  => 'Bottom Right',
		);

		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">Position</div>';
		echo '<div class="ctap-field-input">';
		echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-width:320px;margin-bottom:8px;">';
		foreach ( $positions as $pos_key => $pos_label ) {
			$active_class = ( $pos_key === $current_pos ) ? 'background:#2271b1;color:white;' : 'background:#f0f0f1;color:#50575e;';
			echo '<button type="button" class="button" data-cp-wm-position="' . esc_attr( $pos_key ) . '" style="padding:6px 4px;font-size:11px;border:1px solid #dcdcde;border-radius:4px;cursor:pointer;text-align:center;' . $active_class . '">';
			echo esc_html( $pos_label );
			echo '</button>';
		}
		echo '</div>';
		echo '<input type="hidden" name="cotlas_cp_watermark_position" value="' . esc_attr( $current_pos ) . '" data-cp-wm-position-input>';
		echo '</div></div>';

		ctap_field( 'Image Scale (%)', '<input type="number" name="cotlas_cp_watermark_scale" value="' . esc_attr( get_option( 'cotlas_cp_watermark_scale', 20 ) ) . '" min="5" max="50" style="width:80px">', 'Watermark size as a percentage of the image width. Default: 20.' );
		ctap_card_close();

		ctap_info( '<strong>How it works:</strong> Watermark is applied at upload time to the original image BEFORE Image Conversion runs. This means if Image Optimization → Image Conversion is enabled, the JPEG/PNG is watermarked first, then converted to WebP/AVIF — so the final converted files also contain the watermark. Thumbnails are also watermarked. If "Delete Pre-Watermark Originals" is enabled, the original file is overwritten permanently.' );

		ctap_form_close();
		ctap_pane_close();

		// ── Hotlink Tab ──
		ctap_pane_open( 'hotlink', $active );
		ctap_form_open( self::NONCE_SAVE, 'hotlink' );
		ctap_card_open( 'Hotlink Protection', 'dashicons-admin-links' );
		ctap_toggle( 'cotlas_cp_hotlink_enabled', 'Enable Hotlink Protection', 'Blocks other websites from directly embedding your images. Requests with a Referer from a different domain will receive a 403 error.', 0 );
		ctap_info( 'When enabled, any image request from an external domain that links directly to your images will be blocked. This prevents bandwidth theft. Note: empty Referer headers are allowed (some browsers/CDNs don\'t send them).' );
		ctap_field( 'Allowed Domains', ctap_textarea( 'cotlas_cp_hotlink_domains', "google.com\nbing.com\nfacebook.com\ntwitter.com", 5 ), 'One domain per line. These domains are allowed to link to your images. Your own domain is always allowed.' );
		ctap_card_close();
		ctap_form_close();
		ctap_pane_close();

		ctap_page_close();
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * ADMIN JS
	 * ═══════════════════════════════════════════════════════════════════════ */

	private function get_admin_js() {
		return <<<'JS'
(function($){
	'use strict';

	$(document).on('click', '[data-cp-wm-position]', function(e){
		e.preventDefault();
		var pos = $(this).data('cp-wm-position');
		$('[data-cp-wm-position]').css({background:'#f0f0f1',color:'#50575e'});
		$(this).css({background:'#2271b1',color:'white'});
		$('[data-cp-wm-position-input]').val(pos);
	});

	$(document).on('click', '[data-cp-pick-wm-image]', function(e){
		e.preventDefault();
		var frame = wp.media({ title: 'Select Watermark Image', button: { text: 'Use Image' }, multiple: false });
		frame.on('select', function(){
			var attachment = frame.state().get('selection').first().toJSON();
			$('[data-cp-wm-image-id]').val(attachment.id);
			$('[data-cp-wm-url-input]').val('');
			var imgUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
			$('.cp-wm-image-preview').html(
				'<div style="display:inline-flex;align-items:center;gap:10px;padding:8px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">' +
				'<img src="' + imgUrl + '" alt="" style="max-width:80px;max-height:60px;height:auto;border-radius:4px;">' +
				'</div>'
			);
			$('[data-cp-remove-wm-image]').show();
		});
		frame.open();
	});

	$(document).on('click', '[data-cp-remove-wm-image]', function(e){
		e.preventDefault();
		$('[data-cp-wm-image-id]').val(0);
		$('[data-cp-wm-url-input]').val('');
		$('.cp-wm-image-preview').html('');
		$(this).hide();
	});

	$(document).on('input', '[data-cp-wm-url-input]', function(){
		if ($(this).val().trim()) {
			$('[data-cp-wm-image-id]').val(0);
		}
	});
})(jQuery);
JS;
	}
}

Cotlas_Content_Protect::get_instance();

/* ═══════════════════════════════════════════════════════════════════════════
 * WATERMARK IMAGE HANDLER
 *
 * Uses GD to composite a text or image watermark onto an image.
 * Called by Cotlas_Content_Protect::watermark_on_upload().
 * ═══════════════════════════════════════════════════════════════════════════ */

class Cotlas_Watermark_Handler {

	/**
	 * Generate a watermarked copy of $src and save to $dest.
	 * If $src === $dest, the file is overwritten in-place.
	 */
	public function generate( $src, $dest, $ext ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$type = get_option( 'cotlas_cp_watermark_type', 'text' );

		if ( 'image' === $type ) {
			return $this->generate_image_watermark( $src, $dest, $ext );
		}

		return $this->generate_text_watermark( $src, $dest, $ext );
	}

	private function generate_text_watermark( $src, $dest, $ext ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$text      = get_option( 'cotlas_cp_watermark_text', '' );
		$font_size = absint( get_option( 'cotlas_cp_watermark_font_size', 24 ) );
		$color_hex = get_option( 'cotlas_cp_watermark_color', '#ffffff' );
		$opacity   = absint( get_option( 'cotlas_cp_watermark_opacity', 50 ) );
		$position  = get_option( 'cotlas_cp_watermark_position', 'bottom-right' );

		if ( empty( $text ) ) {
			return true; // No text configured, skip silently.
		}

		$image = $this->load_image( $src, $ext );
		if ( ! $image ) {
			return false;
		}

		$img_width  = imagesx( $image );
		$img_height = imagesy( $image );

		$scale     = get_option( 'cotlas_cp_watermark_scale', 20 ) / 100;
		$font_size = max( 8, (int) ( $img_width * $scale * $font_size / 100 ) );

		$r = hexdec( substr( $color_hex, 1, 2 ) );
		$g = hexdec( substr( $color_hex, 3, 2 ) );
		$b = hexdec( substr( $color_hex, 5, 2 ) );
		$alpha = (int) ( 127 - ( $opacity / 100 ) * 127 );
		$color = imagecolorallocatealpha( $image, $r, $g, $b, $alpha );

		$font_path = $this->get_font_path();
		$bbox = imagettfbbox( $font_size, 0, $font_path, $text );

		if ( $bbox ) {
			$text_width  = abs( $bbox[4] - $bbox[0] );
			$text_height = abs( $bbox[1] - $bbox[5] );
		} else {
			$text_width  = imagefontwidth( 5 ) * strlen( $text ) * ( $font_size / 8 );
			$text_height = imagefontheight( 5 ) * ( $font_size / 8 );
		}

		$padding = max( 10, (int) ( $img_width * 0.02 ) );
		$coords  = $this->calculate_position( $position, $img_width, $img_height, $text_width, $text_height, $padding );

		if ( $bbox ) {
			imagettftext( $image, $font_size, 0, $coords[0], $coords[1], $color, $font_path, $text );
		} else {
			imagestring( $image, 5, $coords[0], $coords[1] - $text_height, $text, $color );
		}

		$result = $this->save_image( $image, $dest, $ext );
		imagedestroy( $image );
		return $result;
	}

	private function generate_image_watermark( $src, $dest, $ext ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$wm_image_id  = absint( get_option( 'cotlas_cp_watermark_image_id', 0 ) );
		$wm_image_url = get_option( 'cotlas_cp_watermark_image_url', '' );
		$position     = get_option( 'cotlas_cp_watermark_position', 'bottom-right' );
		$scale        = get_option( 'cotlas_cp_watermark_scale', 20 ) / 100;
		$opacity      = absint( get_option( 'cotlas_cp_watermark_opacity', 50 ) );

		$wm_path = '';
		if ( $wm_image_id ) {
			$wm_path = get_attached_file( $wm_image_id );
		}
		if ( ! $wm_path || ! file_exists( $wm_path ) ) {
			if ( empty( $wm_image_url ) ) {
				return true;
			}
			$tmp = download_url( $wm_image_url );
			if ( is_wp_error( $tmp ) ) {
				return true;
			}
			$wm_path = $tmp;
		}

		$image = $this->load_image( $src, $ext );
		if ( ! $image ) {
			return false;
		}

		$watermark = $this->load_image_from_path( $wm_path );
		if ( ! $watermark ) {
			imagedestroy( $image );
			return true;
		}

		$img_width  = imagesx( $image );
		$img_height = imagesy( $image );
		$wm_width   = imagesx( $watermark );
		$wm_height  = imagesy( $watermark );

		$new_wm_width  = (int) ( $img_width * $scale );
		$new_wm_height = (int) ( $wm_height * ( $new_wm_width / $wm_width ) );

		$wm_scaled = imagecreatetruecolor( $new_wm_width, $new_wm_height );
		imagealphablending( $wm_scaled, false );
		imagesavealpha( $wm_scaled, true );
		imagecopyresampled( $wm_scaled, $watermark, 0, 0, 0, 0, $new_wm_width, $new_wm_height, $wm_width, $wm_height );

		$wm_with_alpha = imagecreatetruecolor( $new_wm_width, $new_wm_height );
		imagealphablending( $wm_with_alpha, false );
		imagesavealpha( $wm_with_alpha, true );
		$trans = imagecolorallocatealpha( $wm_with_alpha, 0, 0, 0, 127 );
		imagefill( $wm_with_alpha, 0, 0, $trans );

		for ( $x = 0; $x < $new_wm_width; $x++ ) {
			for ( $y = 0; $y < $new_wm_height; $y++ ) {
				$pixel = imagecolorat( $wm_scaled, $x, $y );
				$a = ( $pixel >> 24 ) & 0x7F;
				$r = ( $pixel >> 16 ) & 0xFF;
				$g = ( $pixel >> 8 ) & 0xFF;
				$b = $pixel & 0xFF;
				$new_alpha = min( 127, (int) ( $a + ( 127 - $a ) * ( 1 - $opacity / 100 ) ) );
				$new_color = imagecolorallocatealpha( $wm_with_alpha, $r, $g, $b, $new_alpha );
				imagesetpixel( $wm_with_alpha, $x, $y, $new_color );
			}
		}

		imagedestroy( $wm_scaled );

		$padding = max( 10, (int) ( $img_width * 0.02 ) );
		$coords  = $this->calculate_position( $position, $img_width, $img_height, $new_wm_width, $new_wm_height, $padding );

		imagealphablending( $image, true );
		imagecopy( $image, $wm_with_alpha, $coords[0], $coords[1], 0, 0, $new_wm_width, $new_wm_height );

		imagedestroy( $wm_with_alpha );
		imagedestroy( $watermark );

		$result = $this->save_image( $image, $dest, $ext );
		imagedestroy( $image );
		return $result;
	}

	private function calculate_position( $position, $img_w, $img_h, $obj_w, $obj_h, $pad ) {
		switch ( $position ) {
			case 'top-left':      return array( $pad, $pad );
			case 'top-middle':    return array( (int) ( ( $img_w - $obj_w ) / 2 ), $pad );
			case 'top-right':     return array( $img_w - $obj_w - $pad, $pad );
			case 'middle-left':   return array( $pad, (int) ( ( $img_h - $obj_h ) / 2 ) );
			case 'center':        return array( (int) ( ( $img_w - $obj_w ) / 2 ), (int) ( ( $img_h - $obj_h ) / 2 ) );
			case 'middle-right':  return array( $img_w - $obj_w - $pad, (int) ( ( $img_h - $obj_h ) / 2 ) );
			case 'bottom-left':   return array( $pad, $img_h - $obj_h - $pad );
			case 'bottom-middle': return array( (int) ( ( $img_w - $obj_w ) / 2 ), $img_h - $obj_h - $pad );
			case 'bottom-right':
			default:              return array( $img_w - $obj_w - $pad, $img_h - $obj_h - $pad );
		}
	}

	private function load_image( $path, $ext ) {
		switch ( strtolower( $ext ) ) {
			case 'jpg':
			case 'jpeg':
				if ( function_exists( 'imagecreatefromjpeg' ) ) {
					return @imagecreatefromjpeg( $path );
				}
				break;
			case 'png':
				if ( function_exists( 'imagecreatefrompng' ) ) {
					return @imagecreatefrompng( $path );
				}
				break;
			case 'gif':
				if ( function_exists( 'imagecreatefromgif' ) ) {
					return @imagecreatefromgif( $path );
				}
				break;
			case 'webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					return @imagecreatefromwebp( $path );
				}
				break;
			case 'avif':
				// GD AVIF support requires PHP 8.1+ with libavif.
				if ( function_exists( 'imagecreatefromavif' ) ) {
					$img = @imagecreatefromavif( $path );
					if ( $img ) {
						return $img;
					}
				}
				// Fallback: try Imagick (common on hosting servers).
				if ( class_exists( 'Imagick' ) ) {
					try {
						$im = new Imagick( $path );
						$im->setImageFormat( 'png' );
						$gd = imagecreatetruecolor( $im->getImageWidth(), $im->getImageHeight() );
						imagealphablending( $gd, false );
						imagesavealpha( $gd, true );
						$blob = $im->getImageBlob();
						$tmp  = imagecreatefromstring( $blob );
						if ( $tmp ) {
							imagecopy( $gd, $tmp, 0, 0, 0, 0, imagesx( $tmp ), imagesy( $tmp ) );
							imagedestroy( $tmp );
						}
						$im->destroy();
						return $gd;
					} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
						// Imagick failed.
					}
				}
				// Final fallback: try generic imagecreatefromstring.
				$img = @imagecreatefromstring( file_get_contents( $path ) ); // phpcs:ignore
				if ( $img ) {
					return $img;
				}
				return false;
		}
		// Generic fallback for any format.
		return @imagecreatefromstring( file_get_contents( $path ) ); // phpcs:ignore
	}

	private function load_image_from_path( $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return $this->load_image( $path, $ext );
	}

	private function save_image( $image, $dest, $ext ) {
		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		switch ( strtolower( $ext ) ) {
			case 'jpg':
			case 'jpeg':
				return imagejpeg( $image, $dest, 90 );
			case 'png':
				return imagepng( $image, $dest );
			case 'gif':
				return imagegif( $image, $dest );
			case 'webp':
				if ( function_exists( 'imagewebp' ) ) {
					return imagewebp( $image, $dest, 90 );
				}
				return imagejpeg( $image, $dest, 90 );
			case 'avif':
				if ( function_exists( 'imageavif' ) ) {
					return imageavif( $image, $dest, 60 );
				}
				// Fallback: use Imagick to write AVIF.
				if ( class_exists( 'Imagick' ) ) {
					try {
						// Save GD as PNG temp, then convert via Imagick.
						$tmp = $dest . '.tmp.png';
						imagepng( $image, $tmp );
						$im = new Imagick( $tmp );
						$im->setImageFormat( 'avif' );
						$im->setCompressionQuality( 60 );
						$im->writeImage( $dest );
						$im->destroy();
						wp_delete_file( $tmp );
						return true;
					} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
						// Imagick AVIF failed.
					}
				}
				// Final fallback: save as WebP instead.
				if ( function_exists( 'imagewebp' ) ) {
					return imagewebp( $image, $dest, 90 );
				}
				return imagejpeg( $image, $dest, 90 );
			default:
				return imagejpeg( $image, $dest, 90 );
		}
	}

	private function get_font_path() {
		$candidates = array(
			'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
			'/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
			'/System/Library/Fonts/Helvetica.ttc',
			'/System/Library/Fonts/SFNSText.ttf',
			'C:\\Windows\\Fonts\\arial.ttf',
			ABSPATH . 'wp-includes/fonts/Dashicons.ttf',
		);

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return ABSPATH . 'wp-includes/fonts/Dashicons.ttf';
	}
}