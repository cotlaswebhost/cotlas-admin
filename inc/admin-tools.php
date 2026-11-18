<?php
/**
 * Cotlas Admin Tools
 *
 * Admin toolbox registered under the existing Cotlas Admin menu:
 *
 *   1. Admin Notice Manager   – per-type toggles that suppress WP admin notices.
 *   2. Maintenance Mode       – toggle with custom message, role & IP whitelist.
 *   3. Database Cleanup       – live stats + individual / bulk cleanup actions.
 *   4. Whitelabel             – replace or remove Cotlas branding (login pages,
 *                               auth template, sidebar logo, footer credits,
 *                               dashboard promo widget).
 *
 * Class:   Cotlas_Admin_Tools
 * Menu:    Cotlas Admin → Admin Tools  (slug: cotlas-tools)
 * Options: all prefixed with  cotlas_
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════════
 * CLASS
 * ═══════════════════════════════════════════════════════════════════════════ */

class Cotlas_Admin_Tools {

	/* ── Constants ──────────────────────────────────────────────────────── */

	const PAGE_SLUG     = 'cotlas-tools';
	const NONCE_NOTICES = 'cotlas_tools_notices_save';
	const NONCE_MAINT   = 'cotlas_tools_maint_save';
	const NONCE_REGEN   = 'cotlas_tools_regen';
	const NONCE_DB      = 'cotlas_tools_db';
	const NONCE_WL      = 'cotlas_tools_whitelabel_save';
	const NONCE_CF      = 'cotlas_tools_contact_form_save';
	const NONCE_SMTP    = 'cotlas_tools_smtp_save';
	const NONCE_TS      = 'cotlas_tools_theme_switcher_save';
	const NONCE_DM      = 'cotlas_tools_dark_mode_save';
	const NONCE_FR      = 'cotlas_tools_font_resizer_save';
	const NONCE_SE      = 'cotlas_tools_site_export';
	const NONCE_RD      = 'cotlas_tools_reset_design';
	const NONCE_RF      = 'cotlas_tools_reset_full';

	/* ── Singleton ──────────────────────────────────────────────────────── */

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * HOOKS
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function init_hooks() {
		// Menu (priority 15 – after the main panel registers its items at 5)
		add_action( 'admin_menu', array( $this, 'register_menu' ), 15 );

		// Assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Settings saves (admin_init so they run before output)
		add_action( 'admin_init', array( $this, 'handle_notices_save' ) );
		add_action( 'admin_init', array( $this, 'handle_maintenance_save' ) );
		add_action( 'admin_init', array( $this, 'handle_whitelabel_save' ) );
		add_action( 'admin_init', array( $this, 'handle_contact_form_save' ) );
		add_action( 'admin_init', array( $this, 'handle_smtp_save' ) );
		add_action( 'admin_init', array( $this, 'handle_theme_switcher_save' ) );
		add_action( 'admin_init', array( $this, 'handle_dark_mode_save' ) );
		add_action( 'admin_init', array( $this, 'handle_font_resizer_save' ) );
		add_action( 'admin_init', array( $this, 'handle_site_import' ) );
		add_action( 'admin_init', array( $this, 'handle_reset_design' ) );
		add_action( 'admin_init', array( $this, 'handle_reset_full' ) );

		// Active features
		add_action( 'admin_notices',   array( $this, 'suppress_admin_notices' ), 1 );
		add_action( 'template_redirect', array( $this, 'maintenance_gate' ), 1 );
		add_action( 'admin_bar_menu',  array( $this, 'maintenance_admin_bar' ), 100 );

		// AJAX handlers
		add_action( 'wp_ajax_cotlas_regen_init',  array( $this, 'ajax_regen_init' ) );
		add_action( 'wp_ajax_cotlas_regen_batch', array( $this, 'ajax_regen_batch' ) );
		add_action( 'wp_ajax_cotlas_db_stats',    array( $this, 'ajax_db_stats' ) );
		add_action( 'wp_ajax_cotlas_db_cleanup',  array( $this, 'ajax_db_cleanup' ) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * MENU
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function register_menu() {
		add_submenu_page(
			'cotlas-admin-panel',
			__( 'Admin Tools', 'cotlas-admin' ),
			__( 'Admin Tools', 'cotlas-admin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * ASSETS  (CSS + JS – only on the tools page)
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function enqueue_assets( $hook ) {
		if ( 'cotlas-admin_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		// Media library picker for the Whitelabel image fields.
		wp_enqueue_media();

		// Load the shared ctap panel CSS (defined in admin-panel.php)
		if ( function_exists( 'cotlas_panel_css' ) ) {
			wp_add_inline_style( 'wp-admin', cotlas_panel_css() );
		}

		// Load the shared ctap panel JS (tab switching + save-button feedback)
		if ( function_exists( 'cotlas_panel_js' ) ) {
			wp_register_script( 'cotlas-tools-panel', false, array(), false, true );
			wp_enqueue_script( 'cotlas-tools-panel' );
			wp_add_inline_script( 'cotlas-tools-panel', cotlas_panel_js() );
		}

		// Tools-specific script
		wp_register_script( 'cotlas-tools', false, array(), false, true );
		wp_enqueue_script( 'cotlas-tools' );
		wp_localize_script( 'cotlas-tools', 'cotlasTools', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'dbNonce'    => wp_create_nonce( self::NONCE_DB ),
			'i18n'       => array(
				'processing' => __( 'Processing…', 'cotlas-admin' ),
				'done'       => __( 'Done!', 'cotlas-admin' ),
				'confirm'    => __( 'Are you sure? This action cannot be undone.', 'cotlas-admin' ),
				'error'      => __( 'An error occurred. Please try again.', 'cotlas-admin' ),
			),
		) );
		wp_add_inline_script( 'cotlas-tools', $this->tools_js() );

		// Tools-specific CSS
		wp_add_inline_style( 'wp-admin', $this->tools_css() );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: ADMIN NOTICE PREFERENCES
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_notices_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_NOTICES ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$checkboxes = array(
			'cotlas_hide_core_updates',
			'cotlas_hide_plugin_updates',
			'cotlas_hide_theme_updates',
			'cotlas_hide_php_warnings',
			'cotlas_hide_wsod_notices',
		);
		foreach ( $checkboxes as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 1 : 0 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=notices&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: MAINTENANCE MODE
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_maintenance_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_MAINT ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		update_option( 'cotlas_maintenance_enabled', isset( $_POST['cotlas_maintenance_enabled'] ) ? 1 : 0 );

		$message = isset( $_POST['cotlas_maintenance_message'] )
			? wp_kses_post( wp_unslash( $_POST['cotlas_maintenance_message'] ) )
			: '';
		update_option( 'cotlas_maintenance_message', $message );

		$allowed_roles = ( isset( $_POST['cotlas_maintenance_roles'] ) && is_array( $_POST['cotlas_maintenance_roles'] ) )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['cotlas_maintenance_roles'] ) )
			: array();
		update_option( 'cotlas_maintenance_roles', $allowed_roles );

		$allowed_ips = isset( $_POST['cotlas_maintenance_ips'] )
			? sanitize_textarea_field( wp_unslash( $_POST['cotlas_maintenance_ips'] ) )
			: '';
		update_option( 'cotlas_maintenance_ips', $allowed_ips );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=maintenance&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: WHITELABEL
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_whitelabel_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_WL ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$toggles = array(
			'cotlas_whitelabel_login_branding',
			'cotlas_whitelabel_dashboard_ad',
			'cotlas_whitelabel_hide_sidebar_logo',
		);
		foreach ( $toggles as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 1 : 0 );
		}

		$urls = array(
			'cotlas_whitelabel_login_background',
			'cotlas_whitelabel_login_logo',
			'cotlas_whitelabel_auth_background',
			'cotlas_whitelabel_auth_logo',
			'cotlas_whitelabel_sidebar_logo',
			'cotlas_whitelabel_sidebar_logo_collapsed',
			'cotlas_whitelabel_ad_image',
			'cotlas_whitelabel_ad_link',
			'cotlas_whitelabel_feed_url',
		);
		foreach ( $urls as $key ) {
			$val = isset( $_POST[ $key ] ) ? trim( wp_unslash( $_POST[ $key ] ) ) : '';
			update_option( $key, '' === $val ? '' : esc_url_raw( $val ) );
		}

		$text = array(
			'cotlas_whitelabel_ad_title',
			'cotlas_whitelabel_ad_note',
			'cotlas_whitelabel_feed_note',
		);
		foreach ( $text as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		$footer = isset( $_POST['cotlas_whitelabel_footer_text'] ) ? wp_unslash( $_POST['cotlas_whitelabel_footer_text'] ) : '';
		update_option( 'cotlas_whitelabel_footer_text', wp_kses( $footer, cotlas_whitelabel_allowed_html() ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=whitelabel&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: THEME SWITCHER
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_theme_switcher_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_TS ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		update_option( 'cotlas_theme_switcher_enabled', isset( $_POST['cotlas_theme_switcher_enabled'] ) ? 1 : 0 );

		$theme = isset( $_POST['cotlas_preview_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_preview_theme'] ) ) : '';
		if ( '' !== $theme ) {
			$wp_theme = wp_get_theme( $theme );
			if ( ! $wp_theme->exists() ) {
				$theme = '';
			}
		}

		$user_id = get_current_user_id();
		if ( '' === $theme ) {
			delete_user_meta( $user_id, 'cotlas_preview_theme' );
		} else {
			update_user_meta( $user_id, 'cotlas_preview_theme', $theme );
		}

		if ( function_exists( 'cotlas_set_preview_theme_cookie' ) ) {
			cotlas_set_preview_theme_cookie( $theme );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=theme-switcher&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: DARK MODE
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_dark_mode_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_DM ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		update_option( 'cotlas_dark_mode_enabled', isset( $_POST['cotlas_dark_mode_enabled'] ) ? 1 : 0 );

		$mode = isset( $_POST['cotlas_dark_mode_default'] ) ? sanitize_key( wp_unslash( $_POST['cotlas_dark_mode_default'] ) ) : 'system';
		if ( ! in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
			$mode = 'system';
		}
		update_option( 'cotlas_dark_mode_default', $mode );

		$css = isset( $_POST['cotlas_dark_mode_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['cotlas_dark_mode_css'] ) ) : '';
		update_option( 'cotlas_dark_mode_css', $css );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=dark-mode&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: FONT RESIZER
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_font_resizer_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_FR ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		update_option( 'cotlas_font_resizer_enabled', isset( $_POST['cotlas_font_resizer_enabled'] ) ? 1 : 0 );

		$target = isset( $_POST['cotlas_font_resizer_target'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_font_resizer_target'] ) ) : '';
		if ( '' === trim( $target ) ) {
			$target = '.entry-content';
		}
		update_option( 'cotlas_font_resizer_target', $target );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=font-resizer&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * IMPORT: SITE SETUP (MENUS, WIDGETS, CUSTOMIZER)
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_site_import() {
		if ( empty( $_POST['_ctap_nonce'] ) || empty( $_POST['cotlas_site_import_submit'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_SE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$result = array( 'fatal' => __( 'No file was uploaded.', 'cotlas-admin' ) );

		if ( ! empty( $_FILES['cotlas_site_import_file']['tmp_name'] ) ) {
			$file = $_FILES['cotlas_site_import_file'];

			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
				$result = array( 'fatal' => __( 'The upload failed. Please try again.', 'cotlas-admin' ) );
			} elseif ( (int) $file['size'] > 8 * MB_IN_BYTES ) {
				$result = array( 'fatal' => __( 'The file is larger than 8 MB.', 'cotlas-admin' ) );
			} elseif ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				$result = array( 'fatal' => __( 'Invalid upload.', 'cotlas-admin' ) );
			} else {
				$result = cotlas_site_import_file( $file['tmp_name'], (string) $file['name'] );
			}
		}

		set_transient( 'cotlas_site_import_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=site-export' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * RESET: DESIGN (KEEP CONTENT)
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_reset_design() {
		if ( empty( $_POST['_ctap_nonce'] ) || empty( $_POST['cotlas_reset_design_submit'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_RD ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$confirm = isset( $_POST['cotlas_reset_confirm'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['cotlas_reset_confirm'] ) ) ) : '';

		if ( 'RESET' !== $confirm ) {
			$result = array( 'fatal' => __( 'Type RESET to confirm. Nothing was changed.', 'cotlas-admin' ) );
		} else {
			$result         = cotlas_site_reset_design();
			$result['type'] = 'design';
		}

		set_transient( 'cotlas_site_reset_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=reset-site' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * RESET: FULL SITE
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_reset_full() {
		if ( empty( $_POST['_ctap_nonce'] ) || empty( $_POST['cotlas_reset_full_submit'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_RF ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$confirm = isset( $_POST['cotlas_reset_confirm'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['cotlas_reset_confirm'] ) ) ) : '';

		if ( 'RESET' !== $confirm ) {
			$result = array( 'fatal' => __( 'Type RESET to confirm. Nothing was changed.', 'cotlas-admin' ) );
		} else {
			$result         = cotlas_site_reset_full();
			$result['type'] = 'full';
		}

		set_transient( 'cotlas_site_reset_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=reset-site' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: CONTACT FORM
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_contact_form_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_CF ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$toggles = array(
			'cotlas_form_enabled',
			'cotlas_form_field_name',
			'cotlas_form_field_email',
			'cotlas_form_field_phone',
			'cotlas_form_field_url',
			'cotlas_form_url_local_only',
			'cotlas_form_field_subject',
			'cotlas_form_field_message',
			'cotlas_form_required_name',
			'cotlas_form_required_email',
			'cotlas_form_required_phone',
			'cotlas_form_required_url',
			'cotlas_form_required_subject',
			'cotlas_form_required_message',
			'cotlas_form_email_admin_contact_enabled',
			'cotlas_form_email_user_contact_enabled',
			'cotlas_form_email_admin_newsletter_enabled',
			'cotlas_form_email_user_newsletter_enabled',
			'cotlas_form_collection_enabled',
		);
		foreach ( $toggles as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 1 : 0 );
		}

		$text = array( 'cotlas_form_custom_class' );
		foreach ( $text as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		$editors = array(
			'cotlas_form_email_admin_contact',
			'cotlas_form_email_user_contact',
			'cotlas_form_email_admin_newsletter',
			'cotlas_form_email_user_newsletter',
		);
		foreach ( $editors as $key ) {
			$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			update_option( $key, wp_kses_post( $val ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=contact-form&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * SAVE: SMTP SETTINGS
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function handle_smtp_save() {
		if ( empty( $_POST['_ctap_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_SMTP ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
		}

		$toggles = array( 'cotlas_smtp_enabled', 'cotlas_smtp_deliver_all' );
		foreach ( $toggles as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 1 : 0 );
		}

		$text = array( 'cotlas_smtp_host', 'cotlas_smtp_port', 'cotlas_smtp_user', 'cotlas_smtp_pass', 'cotlas_smtp_encryption', 'cotlas_smtp_from_name', 'cotlas_smtp_from_email' );
		foreach ( $text as $key ) {
			$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			update_option( $key, $val );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=smtp&saved=1' ) );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * ACTIVE FEATURE: SUPPRESS ADMIN NOTICES

	/**
	 * Suppress selected WP admin notices based on saved options.
	 * Runs on admin_notices at priority 1 (before most notices are output).
	 */
	public function suppress_admin_notices() {
		// Core update nag ("A new version of WordPress is available.")
		if ( get_option( 'cotlas_hide_core_updates' ) ) {
			remove_action( 'admin_notices',         'update_nag', 3 );
			remove_action( 'network_admin_notices', 'update_nag', 3 );
		}

		// Plugin update transient – clears the "N plugin updates available" count
		if ( get_option( 'cotlas_hide_plugin_updates' ) ) {
			add_filter( 'site_transient_update_plugins', function ( $t ) {
				if ( is_object( $t ) ) {
					$t->response = array();
				}
				return $t;
			} );
		}

		// Theme update transient
		if ( get_option( 'cotlas_hide_theme_updates' ) ) {
			add_filter( 'site_transient_update_themes', function ( $t ) {
				if ( is_object( $t ) ) {
					$t->response = array();
				}
				return $t;
			} );
		}

		// PHP compatibility / deprecated-function nags
		if ( get_option( 'cotlas_hide_php_warnings' ) ) {
			add_filter( 'deprecated_function_trigger_error', '__return_false' );
			add_filter( 'deprecated_argument_trigger_error', '__return_false' );
			add_filter( 'deprecated_hook_trigger_error',     '__return_false' );
			// Also hide the PHP-nag notice banner via CSS (catches any WP version)
			add_action( 'admin_head', function () {
				echo '<style>.notice-warning.php-nag,.update-nag.php-nag{display:none!important}</style>';
			} );
		}

		// White Screen of Death recovery handler notices
		if ( get_option( 'cotlas_hide_wsod_notices' ) ) {
			add_filter( 'wp_fatal_error_handler_enabled', '__return_false' );
		}
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * ACTIVE FEATURE: MAINTENANCE MODE GATE
	 * ═══════════════════════════════════════════════════════════════════════ */

	/**
	 * Intercept frontend requests when maintenance mode is active.
	 * Hooked to template_redirect at priority 1.
	 */
	public function maintenance_gate() {
		if ( ! get_option( 'cotlas_maintenance_enabled' ) ) {
			return;
		}

		// Administrators always bypass.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check whitelisted roles.
		if ( is_user_logged_in() ) {
			$user          = wp_get_current_user();
			$allowed_roles = (array) get_option( 'cotlas_maintenance_roles', array() );
			foreach ( $user->roles as $role ) {
				if ( in_array( $role, $allowed_roles, true ) ) {
					return;
				}
			}
		}

		// Check whitelisted IPs.
		$ip          = $this->client_ip();
		$allowed_ips = get_option( 'cotlas_maintenance_ips', '' );
		if ( $allowed_ips ) {
			$ip_list = array_filter( array_map( 'trim', explode( "\n", $allowed_ips ) ) );
			if ( in_array( $ip, $ip_list, true ) ) {
				return;
			}
		}

		// Show the maintenance page.
		$message = get_option(
			'cotlas_maintenance_message',
			'<h1>We\'ll be back soon!</h1><p>We are performing scheduled maintenance. Please check back in a few hours.</p>'
		);
		$site = get_bloginfo( 'name' );

		status_header( 503 );
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $site ); ?> – Maintenance</title>
	<style>
		*{box-sizing:border-box;margin:0;padding:0}
		body{background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
		.box{background:#fff;border-radius:12px;box-shadow:0 4px 32px rgba(0,0,0,.10);padding:56px 48px;max-width:560px;width:90%;text-align:center}
		.box h1{font-size:2rem;color:#1a365d;margin-bottom:.6em}
		.box p{color:#555;line-height:1.7;font-size:1.05rem}
	</style>
</head>
<body>
	<div class="box"><?php echo wp_kses_post( $message ); ?></div>
</body>
</html>
		<?php
		exit;
	}

	/* ── Admin bar indicator ─────────────────────────────────────────────── */

	public function maintenance_admin_bar( $bar ) {
		if ( ! get_option( 'cotlas_maintenance_enabled' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node( array(
			'id'    => 'cotlas-maintenance-indicator',
			'title' => '<span style="color:#f97316;font-weight:700">&#9888; Maintenance Mode ON</span>',
			'href'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=maintenance' ),
			'meta'  => array( 'title' => __( 'Click to manage Maintenance Mode', 'cotlas-admin' ) ),
		) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * AJAX: THUMBNAIL REGENERATION
	 * ═══════════════════════════════════════════════════════════════════════ */

	/** Return total attachment image count so the JS can calculate progress. */
	public function ajax_regen_init() {
		$this->verify_ajax( self::NONCE_REGEN );
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			   AND post_mime_type LIKE 'image/%'
			   AND post_status = 'inherit'"
		);

		wp_send_json_success( array( 'total' => $total ) );
	}

	/**
	 * Process a batch of BATCH_SIZE images starting at $offset.
	 * Returns done=true when there are no more images.
	 */
	public function ajax_regen_batch() {
		$this->verify_ajax( self::NONCE_REGEN );

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 5; // small batches to avoid PHP timeouts

		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
		) );

		if ( empty( $ids ) ) {
			wp_send_json_success( array(
				'done'      => true,
				'processed' => $offset,
				'errors'    => 0,
			) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$processed = 0;
		$errors    = 0;
		$last      = '';

		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$meta = wp_generate_attachment_metadata( $id, $file );
				if ( ! is_wp_error( $meta ) && ! empty( $meta ) ) {
					wp_update_attachment_metadata( $id, $meta );
					$processed++;
					$last = get_the_title( $id );
				} else {
					$errors++;
				}
			} else {
				$errors++;
			}
		}

		wp_send_json_success( array(
			'done'        => false,
			'processed'   => $offset + $processed,
			'errors'      => $errors,
			'last'        => $last,
			'next_offset' => $offset + $batch_size,
		) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * AJAX: DATABASE STATS
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function ajax_db_stats() {
		$this->verify_ajax( self::NONCE_DB );
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revisions = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
		);

		$spam = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam' )
		);

		$trash_comments = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'trash' )
		);

		$orphaned_meta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE p.ID IS NULL"
		);

		$transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%',
				time()
			)
		);
		// phpcs:enable

		wp_send_json_success( array(
			'revisions'      => $revisions,
			'spam'           => $spam,
			'trash_comments' => $trash_comments,
			'orphaned_meta'  => $orphaned_meta,
			'transients'     => $transients,
		) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * AJAX: DATABASE CLEANUP
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function ajax_db_cleanup() {
		$this->verify_ajax( self::NONCE_DB );

		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		global $wpdb;

		$deleted = 0;
		$message = '';

		switch ( $type ) {

			// ── Post revisions (keep the 2 most-recent per post) ───────────
			case 'revisions':
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$parent_ids = $wpdb->get_col(
					"SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision'"
				);
				// phpcs:enable
				foreach ( $parent_ids as $pid ) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery
					$rev_ids = $wpdb->get_col( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						 WHERE post_type = 'revision' AND post_parent = %d
						 ORDER BY post_date DESC",
						$pid
					) );
					// phpcs:enable
					$to_delete = array_slice( $rev_ids, 2 ); // drop everything beyond the 2 newest
					foreach ( $to_delete as $rid ) {
						wp_delete_post_revision( (int) $rid );
						$deleted++;
					}
				}
				/* translators: %d: number of revisions deleted */
				$message = sprintf( _n( '%d revision deleted.', '%d revisions deleted.', $deleted, 'cotlas-admin' ), $deleted );
				break;

			// ── Spam comments ──────────────────────────────────────────────
			case 'spam':
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$deleted = (int) $wpdb->query(
					$wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam' )
				);
				// Clean up orphaned comment meta
				$wpdb->query(
					"DELETE FROM {$wpdb->commentmeta}
					 WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})"
				);
				// phpcs:enable
				/* translators: %d: number of spam comments deleted */
				$message = sprintf( _n( '%d spam comment deleted.', '%d spam comments deleted.', $deleted, 'cotlas-admin' ), $deleted );
				break;

			// ── Trashed comments ───────────────────────────────────────────
			case 'trash_comments':
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$deleted = (int) $wpdb->query(
					$wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_approved = %s", 'trash' )
				);
				$wpdb->query(
					"DELETE FROM {$wpdb->commentmeta}
					 WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})"
				);
				// phpcs:enable
				/* translators: %d: number of trashed comments deleted */
				$message = sprintf( _n( '%d trashed comment deleted.', '%d trashed comments deleted.', $deleted, 'cotlas-admin' ), $deleted );
				break;

			// ── Orphaned post meta ─────────────────────────────────────────
			case 'orphaned_meta':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$deleted = (int) $wpdb->query(
					"DELETE pm FROM {$wpdb->postmeta} pm
					 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					 WHERE p.ID IS NULL"
				);
				/* translators: %d: number of orphaned meta entries deleted */
				$message = sprintf( _n( '%d orphaned meta entry deleted.', '%d orphaned meta entries deleted.', $deleted, 'cotlas-admin' ), $deleted );
				break;

			// ── Expired transients ─────────────────────────────────────────
			case 'transients':
				// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$keys = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
					 WHERE option_name LIKE %s AND option_value < %d",
					'_transient_timeout_%',
					time()
				) );
				// phpcs:enable
				foreach ( $keys as $key ) {
					$transient_name = str_replace( '_transient_timeout_', '', $key );
					delete_transient( $transient_name );
					$deleted++;
				}
				/* translators: %d: number of transients deleted */
				$message = sprintf( _n( '%d expired transient deleted.', '%d expired transients deleted.', $deleted, 'cotlas-admin' ), $deleted );
				break;

			// ── Optimize tables ────────────────────────────────────────────
			case 'optimize':
				// phpcs:disable WordPress.DB.DirectDatabaseQuery
				$tables = $wpdb->get_col( 'SHOW TABLES' );
				foreach ( $tables as $table ) {
					$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $table ) . '`' ); // phpcs:ignore
					$deleted++;
				}
				// phpcs:enable
				/* translators: %d: number of tables optimized */
				$message = sprintf( __( '%d database tables optimized.', 'cotlas-admin' ), $deleted );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown cleanup type.', 'cotlas-admin' ) ) );
		}

		wp_send_json_success( array(
			'deleted' => $deleted,
			'message' => $message,
		) );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * PAGE RENDERER
	 * ═══════════════════════════════════════════════════════════════════════ */

	public function render_page() {
		ctap_page_open( 'Admin Tools', 'dashicons-admin-tools', 'Admin Notices · Maintenance Mode · Database Cleanup' );

		$tabs = array(
			array( 'id' => 'notices',     'label' => 'Admin Notices', 'icon' => 'dashicons-megaphone' ),
			array( 'id' => 'maintenance', 'label' => 'Maintenance',   'icon' => 'dashicons-warning' ),
			array( 'id' => 'database',    'label' => 'Database',      'icon' => 'dashicons-database' ),
			array( 'id' => 'starter',     'label' => 'Starter Sites', 'icon' => 'dashicons-admin-site' ),
			array( 'id' => 'contact-form','label' => 'Contact Form',  'icon' => 'dashicons-email' ),
			array( 'id' => 'smtp',        'label' => 'SMTP Settings', 'icon' => 'dashicons-email-alt' ),
			array( 'id' => 'whitelabel',  'label' => 'Whitelabel',    'icon' => 'dashicons-art' ),
			array( 'id' => 'theme-switcher', 'label' => 'Theme Switcher', 'icon' => 'dashicons-admin-appearance' ),
			array( 'id' => 'dark-mode',      'label' => 'Dark Mode',      'icon' => 'dashicons-visibility' ),
			array( 'id' => 'font-resizer',   'label' => 'Font Resizer',   'icon' => 'dashicons-editor-textcolor' ),
			array( 'id' => 'site-export',    'label' => 'Site Export',    'icon' => 'dashicons-migrate' ),
			array( 'id' => 'reset-site',     'label' => 'Reset Site',     'icon' => 'dashicons-database-remove' ),
		);
		$active = ctap_nav( $tabs );

		ctap_pane_open( 'notices', $active );
		$this->tab_notices();
		ctap_pane_close();

		ctap_pane_open( 'maintenance', $active );
		$this->tab_maintenance();
		ctap_pane_close();

		ctap_pane_open( 'database', $active );
		$this->tab_database();
		ctap_pane_close();

		ctap_pane_open( 'starter', $active );
		$this->tab_starter_sites();
		ctap_pane_close();

		ctap_pane_open( 'contact-form', $active );
		$this->tab_contact_form();
		ctap_pane_close();

		ctap_pane_open( 'smtp', $active );
		$this->tab_smtp_settings();
		ctap_pane_close();

		ctap_pane_open( 'whitelabel', $active );
		$this->tab_whitelabel();
		ctap_pane_close();

		ctap_pane_open( 'theme-switcher', $active );
		$this->tab_theme_switcher();
		ctap_pane_close();

		ctap_pane_open( 'dark-mode', $active );
		$this->tab_dark_mode();
		ctap_pane_close();

		ctap_pane_open( 'font-resizer', $active );
		$this->tab_font_resizer();
		ctap_pane_close();

		ctap_pane_open( 'site-export', $active );
		$this->tab_site_export();
		ctap_pane_close();

		ctap_pane_open( 'reset-site', $active );
		$this->tab_reset_site();
		ctap_pane_close();

		ctap_page_close();
	}

	/* ─── Tab: Admin Notices ─────────────────────────────────────────────── */

	private function tab_notices() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'notices' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Preferences saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_card_open( 'Notice Preferences', 'dashicons-megaphone' );
		ctap_info( 'Choose which WordPress admin notices to hide. Changes take effect immediately for all admin users.' );

		echo '<form method="post" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_NOTICES ) ) . '">';

		$options = array(
			'cotlas_hide_core_updates'   => array(
				'Core Update Notices',
				'Hides the "A new version of WordPress is available" banner.',
			),
			'cotlas_hide_plugin_updates' => array(
				'Plugin Update Notices',
				'Suppresses plugin update counts in the menu and dashboard.',
			),
			'cotlas_hide_theme_updates'  => array(
				'Theme Update Notices',
				'Suppresses theme update counts in the menu and dashboard.',
			),
			'cotlas_hide_php_warnings'   => array(
				'PHP Compatibility Warnings',
				'Hides deprecated-function and PHP nag notices.',
			),
			'cotlas_hide_wsod_notices'   => array(
				'WSOD Handler Notices',
				'Disables the White Screen of Death recovery mode handler notices.',
			),
		);

		foreach ( $options as $key => list( $label, $desc ) ) {
			ctap_toggle( $key, $label, $desc, 0 );
		}

		echo '<div style="display:flex;gap:10px;margin-top:16px;align-items:center">';
		echo '<button type="submit" class="ctap-save-btn"><span class="dashicons dashicons-saved"></span>' . esc_html__( 'Save Preferences', 'cotlas-admin' ) . '</button>';
		echo '<button type="button" id="ctls-notices-reset" class="button">' . esc_html__( 'Reset to Default (all off)', 'cotlas-admin' ) . '</button>';
		echo '</div>';

		echo '</form>';
		ctap_card_close();
	}

	/* ─── Tab: Maintenance Mode ──────────────────────────────────────────── */

	private function tab_maintenance() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'maintenance' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_card_open( 'Maintenance Mode', 'dashicons-warning' );
		ctap_info(
			'When enabled, non-authorised visitors see your maintenance message instead of the live site. ' .
			'<strong>Administrators always bypass maintenance mode</strong> and see the site normally.'
		);

		echo '<form method="post" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_MAINT ) ) . '">';

		ctap_module_status(
			'cotlas_maintenance_enabled',
			'Maintenance Mode',
			'Toggle to put the site into maintenance mode (503).',
			0
		);

		ctap_section( 'Maintenance Message' );
		ctap_field(
			'Message',
			'<textarea name="cotlas_maintenance_message" rows="5" style="width:100%;font-family:monospace;font-size:12px">'
			. esc_textarea( get_option(
				'cotlas_maintenance_message',
				'<h1>We\'ll be back soon!</h1><p>We are performing scheduled maintenance. Please check back in a few hours.</p>'
			) )
			. '</textarea>',
			'HTML is allowed: headings, paragraphs, strong, em, links.'
		);

		ctap_section( 'Access Exceptions' );

		// Role whitelist
		$all_roles     = wp_roles()->get_names();
		$allowed_roles = (array) get_option( 'cotlas_maintenance_roles', array() );
		echo '<div class="ctap-field-row"><div class="ctap-field-label">Bypass for Roles</div><div class="ctap-field-input">';
		foreach ( $all_roles as $slug => $name ) {
			if ( 'administrator' === $slug ) {
				continue; // admins always bypass — no checkbox needed
			}
			$checked = in_array( $slug, $allowed_roles, true ) ? 'checked' : '';
			echo '<label style="display:block;margin-bottom:6px">';
			echo '<input type="checkbox" name="cotlas_maintenance_roles[]" value="' . esc_attr( $slug ) . '" ' . $checked . '> ';
			echo esc_html( translate_user_role( $name ) );
			echo '</label>';
		}
		echo '<p class="ctap-field-desc">Administrators always bypass maintenance mode.</p>';
		echo '</div></div>';

		// IP whitelist
		ctap_field(
			'Whitelisted IPs',
			'<textarea name="cotlas_maintenance_ips" rows="4" style="width:100%;font-family:monospace;font-size:12px" placeholder="One IP per line">'
			. esc_textarea( get_option( 'cotlas_maintenance_ips', '' ) )
			. '</textarea>',
			'One IPv4 or IPv6 address per line. These visitors always see the live site.'
		);

		echo '<button type="submit" class="ctap-save-btn"><span class="dashicons dashicons-saved"></span>' . esc_html__( 'Save Settings', 'cotlas-admin' ) . '</button>';
		echo '</form>';
		ctap_card_close();
	}

	/* ─── Tab: Database ──────────────────────────────────────────────────── */

	private function tab_database() {
		ctap_card_open( 'Database Statistics & Cleanup', 'dashicons-database' );
		ctap_info( 'Click <strong>Refresh Stats</strong> to load current counts, then use the action buttons to clean up. Revisions cleanup keeps the 2 most-recent per post.' );

		// Stat rows definition: [ stat_key, label, description ]
		$rows = array(
			array( 'revisions',      'Post Revisions',     'Old revisions. The 2 most-recent per post will be kept.' ),
			array( 'spam',           'Spam Comments',      'Comments currently flagged as spam.' ),
			array( 'trash_comments', 'Trashed Comments',   'Comments in the trash.' ),
			array( 'orphaned_meta',  'Orphaned Post Meta', 'Meta entries whose parent post no longer exists.' ),
			array( 'transients',     'Expired Transients', 'Option-based transients that have already expired.' ),
		);

		echo '<div class="ctls-stat-table">';
		foreach ( $rows as list( $key, $label, $desc ) ) {
			printf(
				'<div class="ctls-stat-row">
					<div class="ctls-stat-info"><strong>%s</strong><span>%s</span></div>
					<div class="ctls-stat-count" id="ctls-stat-%s"><em>—</em></div>
					<div class="ctls-stat-btn">
						<button type="button" class="button button-small ctls-cleanup-btn" data-type="%s" disabled>%s</button>
						<span class="ctls-cleanup-msg" id="ctls-msg-%s" style="display:none"></span>
					</div>
				</div>',
				esc_html( $label ),
				esc_html( $desc ),
				esc_attr( $key ),
				esc_attr( $key ),
				esc_html__( 'Clean Up', 'cotlas-admin' ),
				esc_attr( $key )
			);
		}

		// Optimize tables row (no stat count – always available after refresh)
		printf(
			'<div class="ctls-stat-row">
				<div class="ctls-stat-info"><strong>%s</strong><span>%s</span></div>
				<div class="ctls-stat-count"></div>
				<div class="ctls-stat-btn">
					<button type="button" class="button button-small ctls-cleanup-btn" data-type="optimize" id="ctls-optimize-btn" disabled>%s</button>
					<span class="ctls-cleanup-msg" id="ctls-msg-optimize" style="display:none"></span>
				</div>
			</div>',
			esc_html__( 'Database Tables', 'cotlas-admin' ),
			esc_html__( 'Run MySQL OPTIMIZE TABLE on all tables to defragment and reclaim space.', 'cotlas-admin' ),
			esc_html__( 'Optimize Tables', 'cotlas-admin' )
		);

		echo '</div>';

		echo '<div class="ctls-db-actions">';
		echo '<button type="button" id="ctls-db-refresh" class="button button-secondary">'
			. '<span class="dashicons dashicons-update" style="line-height:36px"></span> '
			. esc_html__( 'Refresh Stats', 'cotlas-admin' )
			. '</button>';
		echo '<button type="button" id="ctls-db-run-all" class="button button-primary" disabled>'
			. '<span class="dashicons dashicons-database" style="line-height:36px"></span> '
			. esc_html__( 'Run All Cleanups', 'cotlas-admin' )
			. '</button>';
		echo '</div>';

		ctap_card_close();
	}

	/* ─── Tab: Starter Sites ───────────────────────────────────────────────── */

	private function tab_starter_sites() {
		ctap_card_open( 'Starter Sites', 'dashicons-admin-site' );
		ctap_info( 'Install a starter kit (theme + plugins) on this fresh site. Opens the Default Site Setup page where you can choose a kit and install it.' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cotlas-default-site-setup' ) ) . '" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;">';
		echo '<span class="dashicons dashicons-admin-site" style="line-height:20px;"></span> ' . esc_html__( 'Open Starter Sites', 'cotlas-admin' );
		echo '</a></p>';
		ctap_card_close();
	}

	/* ─── Tab: Contact Form ───────────────────────────────────────────────── */

	private function tab_contact_form() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'contact-form' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Contact form settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_CF, 'contact-form' );

		ctap_module_status(
			'cotlas_form_enabled',
			'Contact Form',
			'Master toggle. When off, the [cotlas_form] shortcode renders nothing and submissions are not processed.',
			1
		);

		ctap_card_open( 'Form Fields', 'dashicons-feedback' );
		ctap_info( 'Enable or disable fields for the contact form. Use <code>form_fields="name,email,phone,subject,message"</code> in the shortcode to override per form.' );
		ctap_toggle( 'cotlas_form_field_name',    'Name Field',    '', 1 );
		ctap_toggle( 'cotlas_form_field_email',   'Email Field',   '', 1 );
		ctap_toggle( 'cotlas_form_field_phone',   'Mobile Field',  '', 1 );
		ctap_toggle( 'cotlas_form_field_url',     'URL Field',     'Only used by contact and newsletter forms when enabled. The URL field is always included in grievance_form.', 0 );
		ctap_toggle( 'cotlas_form_url_local_only', 'Restrict URL to Site Articles', 'Accept only links or slugs that resolve to a published post on this site. The submitted value is replaced with that post permalink.', 1 );
		ctap_toggle( 'cotlas_form_field_subject', 'Subject Field', '', 1 );
		ctap_toggle( 'cotlas_form_field_message', 'Message Field', '', 1 );
		ctap_field( 'Custom Class', ctap_input( 'cotlas_form_custom_class' ), 'CSS class applied to the form wrapper. Example: <code>my-contact-form</code>.' );
		ctap_card_close();

		ctap_card_open( 'Required Fields', 'dashicons-star-filled' );
		ctap_info( 'Required fields show a <strong>*</strong> next to the label and are also enforced on submission. The URL field is always required for <code>grievance_form</code>.' );
		ctap_toggle( 'cotlas_form_required_name',    'Name Required',    '', 1 );
		ctap_toggle( 'cotlas_form_required_email',   'Email Required',   '', 1 );
		ctap_toggle( 'cotlas_form_required_phone',   'Mobile Required',  '', 0 );
		ctap_toggle( 'cotlas_form_required_url',     'URL Required',     '', 0 );
		ctap_toggle( 'cotlas_form_required_subject', 'Subject Required', '', 0 );
		ctap_toggle( 'cotlas_form_required_message', 'Message Required', '', 0 );
		ctap_card_close();

		ctap_card_open( 'Email Notifications — Contact Form', 'dashicons-email' );
		ctap_toggle( 'cotlas_form_email_admin_contact_enabled', 'Email Notification to Admin', 'Send an email to the admin when a contact form is submitted.', 1 );
		ctap_field( 'Email notification to Admin', '', 'This mail will be sent to admin.' );
		wp_editor( get_option( 'cotlas_form_email_admin_contact', '' ), 'cotlas_form_email_admin_contact', array( 'textarea_name' => 'cotlas_form_email_admin_contact', 'textarea_rows' => 8, 'media_buttons' => false ) );
		ctap_toggle( 'cotlas_form_email_user_contact_enabled', 'Email Notification to User', 'Send a confirmation email to the user who submitted the contact form.', 1 );
		ctap_field( 'Email notification to send to User', '', 'This email will be sent to user.' );
		wp_editor( get_option( 'cotlas_form_email_user_contact', '' ), 'cotlas_form_email_user_contact', array( 'textarea_name' => 'cotlas_form_email_user_contact', 'textarea_rows' => 8, 'media_buttons' => false ) );
		ctap_info( 'Placeholders: <code>{name}</code> <code>{email}</code> <code>{phone}</code> <code>{subject}</code> <code>{url}</code> <code>{message}</code> <code>{site_name}</code> <code>{site_url}</code>' );
		ctap_card_close();

		ctap_card_open( 'Email Notifications — Newsletter', 'dashicons-email' );
		ctap_toggle( 'cotlas_form_email_admin_newsletter_enabled', 'Email Notification to Admin', 'Send an email to the admin when a newsletter subscription is submitted.', 1 );
		ctap_field( 'Email notification to Admin', '', 'This mail will be sent to admin.' );
		wp_editor( get_option( 'cotlas_form_email_admin_newsletter', '' ), 'cotlas_form_email_admin_newsletter', array( 'textarea_name' => 'cotlas_form_email_admin_newsletter', 'textarea_rows' => 8, 'media_buttons' => false ) );
		ctap_toggle( 'cotlas_form_email_user_newsletter_enabled', 'Email Notification to User', 'Send a welcome email to the new subscriber.', 1 );
		ctap_field( 'Email notification to send to User', '', 'This email will be sent to user.' );
		wp_editor( get_option( 'cotlas_form_email_user_newsletter', '' ), 'cotlas_form_email_user_newsletter', array( 'textarea_name' => 'cotlas_form_email_user_newsletter', 'textarea_rows' => 8, 'media_buttons' => false ) );
		ctap_info( 'Placeholders: <code>{name}</code> <code>{email}</code> <code>{site_name}</code> <code>{site_url}</code>' );
		ctap_card_close();

		// Usage info
		ctap_card_open( 'Form Submission Collection', 'dashicons-list-view' );
		ctap_toggle( 'cotlas_form_collection_enabled', 'Enable Collection', 'When enabled, form submissions are saved to the database and viewable from the Form Submissions page.', 0 );
		if ( get_option( 'cotlas_form_collection_enabled', 0 ) ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cotlas-form-submissions' ) ) . '" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;">';
			echo '<span class="dashicons dashicons-list-view" style="line-height:20px;"></span> ' . esc_html__( 'Form Submissions', 'cotlas-admin' );
			echo '</a></p>';
		}
		ctap_card_close();

		ctap_form_close();

		ctap_card_open( 'Shortcode Usage', 'dashicons-shortcode' );
		ctap_info( 'Contact form: <code>[cotlas_form form_type="contact_form" class="contact_form" from_name="Site Contact" from_email="info@example.com" form_fields="name,email,phone,subject,message"]</code>' );
		ctap_info( 'Newsletter: <code>[cotlas_form form_type="newsletter" class="newsletter_form" from_name="Site Newsletter" from_email="info@example.com" form_fields="name,email"]</code>' );
		ctap_info( 'Grievance form: <code>[cotlas_form form_type="grievance_form" class="grievance_form" form_fields="name,email,phone,url,message"]</code> &mdash; the URL field accepts a published article link or slug; with the restriction enabled, anything else is rejected.' );
		ctap_card_close();
	}

	/* ─── Tab: SMTP Settings ─────────────────────────────────────────────── */

	private function tab_smtp_settings() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'smtp' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'SMTP settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_SMTP, 'smtp' );

		ctap_card_open( 'SMTP Configuration', 'dashicons-email-alt' );
		ctap_toggle( 'cotlas_smtp_enabled', 'Enable SMTP', 'Send all WordPress emails via the SMTP server below.', 0 );
		ctap_toggle( 'cotlas_smtp_deliver_all', 'Deliver All Messages', 'If enabled, all WordPress emails (including form submissions) will be sent using these SMTP details.', 1 );
		ctap_field( 'SMTP Server', ctap_input( 'cotlas_smtp_host' ), 'e.g. <code>smtp.gmail.com</code> or <code>mail.example.com</code>.' );
		ctap_field( 'Port', ctap_input( 'cotlas_smtp_port', '587', 'number' ), 'Common ports: 587 (TLS), 465 (SSL), 25.' );
		ctap_field( 'Encryption', ctap_select( 'cotlas_smtp_encryption', array( 'tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None' ), 'tls' ), '' );
		ctap_field( 'SMTP Username', ctap_input( 'cotlas_smtp_user' ), 'SMTP login username (usually your email address).' );
		ctap_field( 'SMTP Password', ctap_input( 'cotlas_smtp_pass', '', 'password' ), 'SMTP login password or app password.' );
		ctap_field( 'From Name', ctap_input( 'cotlas_smtp_from_name' ), 'Default sender name for all emails. Form-specific from_name overrides this.' );
		ctap_field( 'From Email', ctap_input( 'cotlas_smtp_from_email', '', 'email' ), 'Default sender email for all emails. Form-specific from_email overrides this.' );
		ctap_card_close();

		ctap_form_close();
	}

	/* ─── Tab: Whitelabel ──────────────────────────────────────────────────── */

	private function tab_whitelabel() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'whitelabel' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Whitelabel settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_WL, 'whitelabel' );

		/* ── Card: WordPress login page ─────────────────────────────────────── */
		ctap_card_open( 'WordPress Login Page', 'dashicons-lock' );
		ctap_info( 'Customises the default WordPress login, registration and lost-password pages (wp-login.php).' );
		ctap_toggle(
			'cotlas_whitelabel_login_branding',
			'Enable Login Page Branding',
			'Applies the background image and logo below to wp-login.php, the registration page and the lost-password page. Turn off to restore the plain WordPress login screen.',
			1
		);
		ctap_section( 'Login Page Assets' );
		$this->wl_image_field( 'cotlas_whitelabel_login_background', 'Background Image', 'Full-width background behind the login form. Leave empty to use the bundled banner.' );
		$this->wl_image_field( 'cotlas_whitelabel_login_logo', 'Login Logo', 'Shown above the login form. Leave empty to use the bundled logo.' );
		ctap_card_close();

		/* ── Card: Custom auth pages ────────────────────────────────────────── */
		ctap_card_open( 'Custom Auth Pages', 'dashicons-admin-page' );
		ctap_info( 'Applies to pages built with the Authentication Template (login, register and reset-password shortcodes).' );
		$this->wl_image_field( 'cotlas_whitelabel_auth_background', 'Page Background', 'Background image behind the whole authentication page. Leave empty to use the bundled banner.' );
		$this->wl_image_field( 'cotlas_whitelabel_auth_logo', 'Logo Above Forms', 'Displayed above the login and register forms. Falls back to the theme site logo, then the site name. Leave empty to use the theme logo.' );
		ctap_card_close();

		/* ── Card: Admin dashboard ──────────────────────────────────────────── */
		ctap_card_open( 'Admin Dashboard', 'dashicons-dashboard' );
		ctap_section( 'Footer Credits' );
		$footer = get_option( 'cotlas_whitelabel_footer_text', false );
		if ( false === $footer ) {
			$footer = cotlas_whitelabel_default_footer_text();
		}
		ctap_field(
			'Footer Credits',
			'<textarea name="cotlas_whitelabel_footer_text" rows="3" style="width:100%;font-family:monospace;font-size:12px">' . esc_textarea( $footer ) . '</textarea>',
			'HTML is allowed (links, strong, em). Leave empty to remove the footer credits completely.'
		);

		ctap_section( 'Sidebar Logo' );
		$this->wl_image_field( 'cotlas_whitelabel_sidebar_logo', 'Expanded Logo', 'Top of the admin menu when expanded. Leave empty to use the bundled logo.' );
		$this->wl_image_field( 'cotlas_whitelabel_sidebar_logo_collapsed', 'Collapsed Logo', 'Shown when the admin menu is folded and on compact screens. Leave empty to use the bundled icon.' );
		ctap_toggle( 'cotlas_whitelabel_hide_sidebar_logo', 'Hide Sidebar Logo', 'Removes the logo from the top of the admin menu completely.', 0 );
		ctap_card_close();

		/* ── Card: Dashboard promo widget ───────────────────────────────────── */
		ctap_card_open( 'Dashboard Promo Widget', 'dashicons-megaphone' );
		ctap_toggle(
			'cotlas_whitelabel_dashboard_ad',
			'Show Promo Widget',
			'Displays the promotional banner widget on the WordPress dashboard. Turn off to hide it completely.',
			1
		);
		ctap_section( 'Promo Widget Content' );

		$ad_title = get_option( 'cotlas_whitelabel_ad_title', '' );
		if ( '' === trim( (string) $ad_title ) ) {
			$ad_title = __( 'We create your online presence', 'cotlas-admin' );
		}
		ctap_field( 'Widget Title', '<input type="text" name="cotlas_whitelabel_ad_title" value="' . esc_attr( $ad_title ) . '">', '' );

		$this->wl_image_field( 'cotlas_whitelabel_ad_image', 'Banner Image', 'Leave empty to use the bundled banner.' );

		$ad_link = get_option( 'cotlas_whitelabel_ad_link', '' );
		if ( '' === trim( (string) $ad_link ) ) {
			$ad_link = 'https://cotlas.net';
		}
		ctap_field( 'Banner Link', '<input type="url" name="cotlas_whitelabel_ad_link" value="' . esc_attr( $ad_link ) . '">', '' );

		$ad_note = get_option( 'cotlas_whitelabel_ad_note', '' );
		if ( '' === trim( (string) $ad_note ) ) {
			$ad_note = __( 'Latest offers from cotlas web solution', 'cotlas-admin' );
		}
		ctap_field( 'Note Text', '<input type="text" name="cotlas_whitelabel_ad_note" value="' . esc_attr( $ad_note ) . '">', '' );

		ctap_card_close();

		/* ── Card: Dashboard articles widget ────────────────────────────────── */
		ctap_card_open( 'Dashboard Articles Widget', 'dashicons-rss' );
		ctap_info( 'The "Latest Articles" widget on the dashboard pulls posts from an RSS feed and renders each item as a card with thumbnail, title, author and date. Point the feed at your own site to replace the default source.' );
		ctap_field( 'Feed URL', '<input type="url" name="cotlas_whitelabel_feed_url" value="' . esc_attr( get_option( 'cotlas_whitelabel_feed_url', '' ) ) . '" placeholder="https://cotlas.net/feed">', 'Leave empty to keep the default feed. Example: <code>https://example.com/feed</code>. The "Read more" link follows the feed&rsquo;s site automatically.' );
		$feed_note = get_option( 'cotlas_whitelabel_feed_note', false );
		if ( false === $feed_note ) {
			$feed_note = __( 'Read the latest articles from Cotlas to unlock more value from your portal, generate stronger leads, improve search visibility, and discover curated insights that help your website perform better.', 'cotlas-admin' );
		}
		ctap_field(
			'Widget Note',
			'<textarea name="cotlas_whitelabel_feed_note" rows="2" style="width:100%;font-family:monospace;font-size:12px">' . esc_textarea( $feed_note ) . '</textarea>',
			'Short text shown under the article cards. Leave empty to show nothing.'
		);
		ctap_card_close();

		ctap_form_close( 'Save Whitelabel Settings' );
	}

	/* ─── Tab: Theme Switcher ─────────────────────────────────────────────── */

	private function tab_theme_switcher() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'theme-switcher' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Theme switcher settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_TS, 'theme-switcher' );

		ctap_card_open( 'Theme Switcher', 'dashicons-admin-appearance' );
		ctap_toggle(
			'cotlas_theme_switcher_enabled',
			'Enable Theme Switcher',
			'Only administrators can preview a theme, and the preview applies to your own browser. Visitors and other users keep seeing the active theme.',
			0
		);
		ctap_info( 'WordPress loads the theme before the current user is known, so the choice is remembered with a signed cookie in your browser. It applies to the frontend and wp-admin for your browser only, so the previewed theme&rsquo;s Customizer and settings screens are available.' );

		$current = (string) get_user_meta( get_current_user_id(), 'cotlas_preview_theme', true );
		$select  = '<select name="cotlas_preview_theme">';
		$select .= '<option value="">' . esc_html__( '— Use Site Theme —', 'cotlas-admin' ) . '</option>';
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$select .= sprintf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr( $stylesheet ),
				selected( $current, $stylesheet, false ),
				esc_html( $theme->get( 'Name' ) ),
				esc_html( $stylesheet )
			);
		}
		$select .= '</select>';

		ctap_field( 'Preview Theme', $select, 'Choose the theme to preview on the frontend and in wp-admin. Clear the selection to return to the site theme. Activating a theme from Appearance &rarr; Themes also clears the preview.' );
		ctap_card_close();

		ctap_form_close( 'Save Theme Switcher' );
	}

	/* ─── Tab: Dark Mode ──────────────────────────────────────────────────── */

	private function tab_dark_mode() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'dark-mode' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Dark mode settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_DM, 'dark-mode' );

		ctap_card_open( 'Dark Mode', 'dashicons-visibility' );
		ctap_toggle( 'cotlas_dark_mode_enabled', 'Enable Dark Mode', 'Adds the frontend dark mode toggle, its shortcode and dynamic tag.', 0 );
		ctap_field(
			'Default Mode',
			ctap_select( 'cotlas_dark_mode_default', array( 'system' => 'System (device preference)', 'light' => 'Light', 'dark' => 'Dark' ), 'system' ),
			'What visitors see before choosing their own mode. <strong>System</strong> follows the device dark mode setting.'
		);
		ctap_info( 'Visitors who use the toggle have their choice stored in the browser for 30 days and it overrides the default. Clearing site data returns them to the default.' );
		ctap_card_close();

		ctap_card_open( 'Dark Mode CSS Overrides', 'dashicons-editor-code' );
		$css = (string) get_option( 'cotlas_dark_mode_css', '' );
		ctap_field(
			'Custom CSS',
			'<textarea name="cotlas_dark_mode_css" rows="12" style="width:100%;font-family:monospace;font-size:12px">' . esc_textarea( $css ) . '</textarea>',
			'Applied only while dark mode is active. The <code>cws-dark-mode</code> class is added to <code>&lt;html&gt;</code>, e.g. <code>html.cws-dark-mode body{background:#111;color:#eee}</code>.'
		);
		ctap_card_close();

		ctap_form_close( 'Save Dark Mode Settings' );

		ctap_card_open( 'Usage', 'dashicons-shortcode' );
		ctap_info( 'Shortcode: <code>[cotlas_dark_mode_toggle]</code>' );
		ctap_info( 'Dynamic tag: <code>{{dark_mode_toggle}}</code>' );
		ctap_info( 'Both render the same switch and can be placed in GeneratePress header elements, widgets or blocks.' );
		ctap_card_close();
	}

	/* ─── Tab: Font Resizer ───────────────────────────────────────────────── */

	private function tab_font_resizer() {
		if ( ! empty( $_GET['saved'] ) && isset( $_GET['tab'] ) && 'font-resizer' === sanitize_key( $_GET['tab'] ) ) {
			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Font resizer settings saved.', 'cotlas-admin' ) . '</div>';
		}

		ctap_form_open( self::NONCE_FR, 'font-resizer' );

		ctap_card_open( 'Font Resizer', 'dashicons-editor-textcolor' );
		ctap_toggle( 'cotlas_font_resizer_enabled', 'Enable Font Resizer', 'Adds the article font size control.', 0 );

		$target = (string) get_option( 'cotlas_font_resizer_target', '.entry-content' );
		ctap_field(
			'Content Selector',
			'<input type="text" name="cotlas_font_resizer_target" value="' . esc_attr( $target ) . '" placeholder=".entry-content">',
			'CSS selector for the article content. Adjust it if your theme uses a different content wrapper.'
		);
		ctap_info( 'Clicking the button cycles the font size through 100%, 110%, 120% and 130%, then back to 100%. The choice is stored in the visitor&rsquo;s browser.' );
		ctap_card_close();

		ctap_form_close( 'Save Font Resizer Settings' );

		ctap_card_open( 'Usage', 'dashicons-shortcode' );
		ctap_info( 'Shortcode: <code>[cotlas_font_resizer]</code>' );
		ctap_info( 'Dynamic tag: <code>{{font_resizer}}</code>' );
		ctap_card_close();
	}

	/* ─── Tab: Site Export ────────────────────────────────────────────────── */

	private function tab_site_export() {
		$transient = 'cotlas_site_import_' . get_current_user_id();
		$result    = get_transient( $transient );
		if ( false !== $result ) {
			delete_transient( $transient );
			$this->render_site_import_result( $result );
		}

		$nonce       = wp_create_nonce( 'cotlas_site_export' );
		$export_base = admin_url( 'admin-post.php?_wpnonce=' . $nonce . '&action=' );

		ctap_card_open( 'Export Site Setup', 'dashicons-download' );
		ctap_info( 'Each part downloads in the format the WordPress ecosystem already uses, so the files also work with the <strong>Widget Importer &amp; Exporter</strong> and <strong>Customizer Export/Import</strong> plugins. Take all three to move a complete design.' );

		$exports = array(
			array(
				'action'  => 'cotlas_export_menus',
				'label'   => 'Menus',
				'file'    => 'cotlas-menus-YYYYMMDD-HHMMSS.json',
				'note'    => 'Navigation menus, their items, order and assigned locations.',
			),
			array(
				'action'  => 'cotlas_export_widgets',
				'label'   => 'Widgets',
				'file'    => 'your-site.com-widgets.wie',
				'note'    => 'Every widget instance and the sidebar it sits in (.wie).',
			),
			array(
				'action'  => 'cotlas_export_customizer',
				'label'   => 'Customizer',
				'file'    => esc_html( get_stylesheet() ) . '-export.dat',
				'note'    => 'Theme mods, GeneratePress settings and Additional CSS (.dat).',
			),
		);

		foreach ( $exports as $export ) {
			echo '<div class="ctap-field-row"><div class="ctap-field-label">' . esc_html( $export['label'] ) . '</div><div class="ctap-field-input">';
			echo '<a class="button button-primary" href="' . esc_url( $export_base . $export['action'] ) . '" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;font-size:13px;text-decoration:none;">';
			echo '<span class="dashicons dashicons-download" style="line-height:20px;"></span> ' . esc_html__( 'Download', 'cotlas-admin' ) . ' ' . esc_html( $export['label'] );
			echo '</a>';
			echo '<p class="ctap-field-desc">' . esc_html( $export['note'] ) . ' <code>' . esc_html( $export['file'] ) . '</code></p>';
			echo '</div></div>';
		}

		echo '<div style="margin-top:16px;border-top:1px solid #e0e0e0;padding-top:16px;">';
		echo '<a class="button" href="' . esc_url( $export_base . 'cotlas_site_export' ) . '" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;font-size:13px;text-decoration:none;">';
		echo '<span class="dashicons dashicons-database-export" style="line-height:20px;"></span> ' . esc_html__( 'Download All Three (single JSON)', 'cotlas-admin' );
		echo '</a>';
		echo '<p class="ctap-field-desc">Convenience backup of menus, widgets and Customizer in one file. Only this Site Export tool can import it.</p>';
		echo '</div>';

		ctap_card_close();

		ctap_card_open( 'Import Site Setup', 'dashicons-upload' );
		ctap_info( '<strong>Menus are replaced</strong> and widgets are appended; Customizer settings are applied when the file comes from the same parent theme. Importing any single file updates only that part. This cannot be undone &mdash; download a backup first.' );

		echo '<form method="post" enctype="multipart/form-data" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_SE ) ) . '">';
		echo '<input type="hidden" name="cotlas_site_import_submit" value="1">';
		echo '<div class="ctap-field-row"><div class="ctap-field-label">Select File</div><div class="ctap-field-input">';
		echo '<input type="file" name="cotlas_site_import_file" accept=".wie,.dat,.json,application/json,application/octet-stream" required style="font-size:13px;">';
		echo '<p class="ctap-field-desc">Accepts <code>.wie</code> (widgets), <code>.dat</code> (Customizer), menus <code>.json</code>, or a combined site-export <code>.json</code> (max 8 MB).</p>';
		echo '</div></div>';
		echo '<div style="margin-top:16px;">';
		echo '<button type="submit" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;font-size:13px;" onclick="return confirm(\'Importing will replace existing menus and add widgets. Continue?\')">';
		echo '<span class="dashicons dashicons-upload" style="line-height:20px;"></span> ' . esc_html__( 'Import Site Setup', 'cotlas-admin' );
		echo '</button>';
		echo '</div></form>';
		ctap_card_close();

		ctap_card_open( 'Plugin Settings Are Separate', 'dashicons-admin-settings' );
		ctap_info( 'This tool moves site structure and theme design. To move Cotlas Admin plugin configuration, use <a href="' . esc_url( admin_url( 'admin.php?page=cotlas-export-import' ) ) . '">Export / Import</a> instead.' );
		ctap_card_close();
	}

	/** Render the result of a site setup import. */
	private function render_site_import_result( $result ) {
		if ( ! is_array( $result ) ) {
			return;
		}

		if ( ! empty( $result['fatal'] ) ) {
			echo '<div class="ctap-notice ctap-notice-error"><span class="dashicons dashicons-warning"></span> ' . esc_html( $result['fatal'] ) . '</div>';
			return;
		}

		$lines = array();
		if ( ! empty( $result['menus_cleared'] ) ) {
			$lines[] = sprintf( _n( '%d existing menu removed', '%d existing menus removed', $result['menus_cleared'], 'cotlas-admin' ), $result['menus_cleared'] );
		}
		if ( ! empty( $result['menus'] ) ) {
			$lines[] = sprintf( _n( '%d menu imported', '%d menus imported', $result['menus'], 'cotlas-admin' ), $result['menus'] );
		}
		if ( ! empty( $result['menu_items'] ) ) {
			$lines[] = sprintf( _n( '%d menu item imported', '%d menu items imported', $result['menu_items'], 'cotlas-admin' ), $result['menu_items'] );
		}
		if ( ! empty( $result['widgets_cleared'] ) ) {
			$lines[] = sprintf( _n( '%d existing widget option cleared', '%d widget options cleared', $result['widgets_cleared'], 'cotlas-admin' ), $result['widgets_cleared'] );
		}
		if ( ! empty( $result['widgets'] ) ) {
			$lines[] = sprintf( _n( '%d widget imported', '%d widgets imported', $result['widgets'], 'cotlas-admin' ), $result['widgets'] );
		}
		if ( ! empty( $result['skipped'] ) ) {
			$lines[] = sprintf( _n( '%d widget skipped', '%d widgets skipped', $result['skipped'], 'cotlas-admin' ), $result['skipped'] );
		}
		if ( ! empty( $result['sidebar_widgets'] ) ) {
			$lines[] = sprintf( _n( '%d widget placed in a sidebar', '%d widgets placed in sidebars', $result['sidebar_widgets'], 'cotlas-admin' ), $result['sidebar_widgets'] );
		}
		if ( ! empty( $result['customizer'] ) ) {
			$lines[] = __( 'Customizer settings imported', 'cotlas-admin' );
		}
		if ( ! empty( $result['options'] ) ) {
			$lines[] = sprintf( _n( '%d design option imported', '%d design options imported', $result['options'], 'cotlas-admin' ), $result['options'] );
		}

		echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Site setup imported.', 'cotlas-admin' );
		if ( $lines ) {
			echo '<br>' . esc_html( implode( ' · ', $lines ) );
		}
		echo '</div>';

		foreach ( (array) ( $result['warnings'] ?? array() ) as $warning ) {
			echo '<div class="ctap-notice ctap-notice-error"><span class="dashicons dashicons-warning"></span> ' . esc_html( $warning ) . '</div>';
		}
	}

	/* ─── Tab: Reset Site ─────────────────────────────────────────────────── */

	private function tab_reset_site() {
		$transient = 'cotlas_site_reset_' . get_current_user_id();
		$result    = get_transient( $transient );
		if ( false !== $result ) {
			delete_transient( $transient );
			$this->render_site_reset_result( $result );
		}

		if ( is_multisite() ) {
			ctap_card_open( 'Site Reset', 'dashicons-database-remove' );
			ctap_info( 'Site reset is not available on multisite installations. Reset each site individually using its own tools.' );
			ctap_card_close();
			return;
		}

		ctap_card_open( 'Before You Reset', 'dashicons-info' );
		ctap_info( 'Resetting cannot be undone. Download a <strong>Site Export</strong> (menus, widgets, Customizer) and an <strong>Export / Import</strong> plugin-settings file first, and take a full database backup if possible.' );
		ctap_card_close();

		ctap_card_open( 'Reset Design — Keep Content', 'dashicons-art' );
		ctap_info( 'Keeps all posts, pages, custom post types, media, categories, tags, comments and users. Removes navigation menus, widgets, Customizer settings, Additional CSS, block global styles and cached data. Use this when a redesign starts over but the content must stay.' );

		echo '<form method="post" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_RD ) ) . '">';
		echo '<input type="hidden" name="cotlas_reset_design_submit" value="1">';
		echo '<div class="ctap-field-row"><div class="ctap-field-label">Confirmation</div><div class="ctap-field-input">';
		echo '<input type="text" name="cotlas_reset_confirm" placeholder="Type RESET" autocomplete="off" required style="max-width:220px">';
		echo '<p class="ctap-field-desc">Type <code>RESET</code> to confirm. The active theme and plugins stay active.</p>';
		echo '</div></div>';
		echo '<div style="margin-top:16px;">';
		echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Remove menus, widgets and design settings while keeping all content? This cannot be undone.\')">';
		echo esc_html__( 'Reset Design (Keep Content)', 'cotlas-admin' );
		echo '</button>';
		echo '</div></form>';
		ctap_card_close();

		ctap_card_open( 'Full Reset — Fresh WordPress', 'dashicons-warning' );
		ctap_info( '<strong>Deletes all posts, pages, custom post types, media files, comments, categories, tags, menus, widgets, Customizer settings and all users except your account.</strong> Nearly all database options are reset. The site URL/name/admin email, active theme and active plugins are kept so the site stays reachable. Plugin-created database tables are not dropped.' );

		echo '<form method="post" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_RF ) ) . '">';
		echo '<input type="hidden" name="cotlas_reset_full_submit" value="1">';
		echo '<div class="ctap-field-row"><div class="ctap-field-label">Confirmation</div><div class="ctap-field-input">';
		echo '<input type="text" name="cotlas_reset_confirm" placeholder="Type RESET" autocomplete="off" required style="max-width:220px">';
		echo '<p class="ctap-field-desc">Type <code>RESET</code> to confirm. Every post, page, media file and other user will be deleted.</p>';
		echo '</div></div>';
		echo '<div style="margin-top:16px;">';
		echo '<button type="submit" class="button" style="color:#b32d2e;border-color:#b32d2e;" onclick="return confirm(\'FULL RESET will delete all content, media, terms and other users. This cannot be undone. Continue?\')">';
		echo esc_html__( 'Full Reset Site', 'cotlas-admin' );
		echo '</button>';
		echo '</div></form>';
		ctap_card_close();
	}

	/** Render the result of a site reset. */
	private function render_site_reset_result( $result ) {
		if ( ! is_array( $result ) ) {
			return;
		}

		if ( ! empty( $result['fatal'] ) ) {
			echo '<div class="ctap-notice ctap-notice-error"><span class="dashicons dashicons-warning"></span> ' . esc_html( $result['fatal'] ) . '</div>';
			return;
		}

		$lines = array();

		if ( 'design' === ( $result['type'] ?? '' ) ) {
			$lines[] = sprintf( _n( '%d menu removed', '%d menus removed', (int) $result['menus'], 'cotlas-admin' ), (int) $result['menus'] );
			$lines[] = sprintf( _n( '%d widget option removed', '%d widget options removed', (int) $result['widgets'], 'cotlas-admin' ), (int) $result['widgets'] );
			$lines[] = sprintf( _n( '%d Customizer settings group removed', '%d Customizer settings groups removed', (int) $result['customizer'], 'cotlas-admin' ), (int) $result['customizer'] );

			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Design reset complete. All content and media were preserved.', 'cotlas-admin' );
		} else {
			$lines[] = sprintf( _n( '%d post deleted', '%d posts deleted', (int) $result['posts'], 'cotlas-admin' ), (int) $result['posts'] );
			$lines[] = sprintf( _n( '%d media attachment deleted', '%d media attachments deleted', (int) $result['attachments'], 'cotlas-admin' ), (int) $result['attachments'] );
			$lines[] = sprintf( _n( '%d comment deleted', '%d comments deleted', (int) $result['comments'], 'cotlas-admin' ), (int) $result['comments'] );
			$lines[] = sprintf( _n( '%d user deleted', '%d users deleted', (int) $result['users'], 'cotlas-admin' ), (int) $result['users'] );
			$lines[] = sprintf( _n( '%d option reset', '%d options reset', (int) $result['options'], 'cotlas-admin' ), (int) $result['options'] );

			echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Full reset complete. The site is now a fresh WordPress installation.', 'cotlas-admin' );
		}

		echo '<br>' . esc_html( implode( ' · ', $lines ) ) . '</div>';
	}

	/** Image URL field with media-library picker button and live preview. Stores the raw value — an empty field means "use the default". */
	private function wl_image_field( $name, $label, $desc ) {
		$value = (string) get_option( $name, '' );

		$input  = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		$input .= '<input type="url" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="https://example.com/image.webp" style="flex:1;min-width:220px">';
		$input .= '<button type="button" class="button ctls-media-btn" data-target="' . esc_attr( $name ) . '"><span class="dashicons dashicons-format-image"></span> ' . esc_html__( 'Select Image', 'cotlas-admin' ) . '</button>';
		$input .= '</div>';
		$input .= '<div class="ctls-wl-preview" id="preview-' . esc_attr( $name ) . '">';
		if ( '' !== trim( $value ) ) {
			$input .= '<img src="' . esc_url( $value ) . '" alt="">';
		}
		$input .= '</div>';

		ctap_field( $label, $input, $desc );
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * INLINE CSS
	 * ═══════════════════════════════════════════════════════════════════════ */

	private function tools_css() {
		return '
/* ── Cotlas Admin Tools ─────────────────────────────────────────────────── */

/* Database stat table */
.ctls-stat-table{border:1px solid #e0e7ef;border-radius:6px;overflow:hidden;margin-top:8px}
.ctls-stat-row{display:grid;grid-template-columns:1fr 80px 180px;align-items:center;gap:16px;padding:12px 16px;border-bottom:1px solid #f0f0f1}
.ctls-stat-row:last-child{border-bottom:none}
.ctls-stat-row:hover{background:#f9fafb}
.ctls-stat-info strong{display:block;font-size:13px}
.ctls-stat-info span{font-size:11px;color:#888}
.ctls-stat-count{font-size:22px;font-weight:700;color:#2271b1;text-align:center}
.ctls-stat-count em{color:#bbb;font-style:normal;font-size:14px}
.ctls-stat-btn{display:flex;align-items:center;gap:8px}
.ctls-cleanup-msg{font-size:12px}

/* Database action bar */
.ctls-db-actions{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}

/* Whitelabel image field preview */
.ctls-wl-preview{margin-top:8px}
.ctls-wl-preview img{max-width:240px;max-height:80px;display:block;border:1px solid #dcdcde;border-radius:4px;padding:4px;background:#fff}
		';
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * INLINE JAVASCRIPT
	 * ═══════════════════════════════════════════════════════════════════════ */

	private function tools_js() {
		return <<<'JS'
(function (cfg) {
    "use strict";
    if (!cfg || !cfg.ajaxurl) return;

    /* ── Shared fetch helper ─────────────────────────────────────────────── */
    function ajax(action, data) {
        var body = new URLSearchParams({ action: action });
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(cfg.ajaxurl, { method: "POST", credentials: "same-origin", body: body })
            .then(function (r) { return r.json(); });
    }

    /* ════════════════════════════════════════════════════════════════════════
	 * 1. ADMIN NOTICES – Reset button
     * ════════════════════════════════════════════════════════════════════════ */
    var resetBtn = document.getElementById("ctls-notices-reset");
    if (resetBtn) {
        resetBtn.addEventListener("click", function () {
            var form = resetBtn.closest("form");
            if (!form) return;
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                cb.checked = false;
            });
        });
    }

    /* ════════════════════════════════════════════════════════════════════════
		* 2. DATABASE CLEANUP
     * ════════════════════════════════════════════════════════════════════════ */
    var refreshBtn = document.getElementById("ctls-db-refresh");
    var runAllBtn  = document.getElementById("ctls-db-run-all");
    var optimizeBtn = document.getElementById("ctls-optimize-btn");

    function loadStats() {
        if (refreshBtn) {
            refreshBtn.disabled     = true;
            refreshBtn.textContent  = "Loading…";
        }
        ajax("cotlas_db_stats", { _nonce: cfg.dbNonce })
            .then(function (res) {
                if (!res.success) return;
                var d = res.data;
                // Map stat keys to display elements
                ["revisions", "spam", "trash_comments", "orphaned_meta", "transients"].forEach(function (k) {
                    var el = document.getElementById("ctls-stat-" + k);
                    if (el) el.textContent = Number(d[k]).toLocaleString();
                    // Enable the matching cleanup button
                    var btn = document.querySelector(".ctls-cleanup-btn[data-type=" + k + "]");
                    if (btn) btn.disabled = false;
                });
                // Always enable optimize after a stats refresh
                if (optimizeBtn) optimizeBtn.disabled = false;
                if (runAllBtn)  runAllBtn.disabled  = false;
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<span class="dashicons dashicons-update" style="line-height:36px"></span> Refresh Stats';
                }
            });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener("click", loadStats);
    }

    // Individual clean-up buttons (event delegation)
    document.addEventListener("click", function (e) {
        var btn = e.target.closest(".ctls-cleanup-btn");
        if (!btn || btn.disabled) return;

        var type  = btn.dataset.type;
        var msgEl = document.getElementById("ctls-msg-" + type);

        if (!confirm(cfg.i18n.confirm)) return;

        btn.disabled    = true;
        btn.textContent = cfg.i18n.processing;
        if (msgEl) msgEl.style.display = "none";

        ajax("cotlas_db_cleanup", { _nonce: cfg.dbNonce, type: type })
            .then(function (res) {
                btn.disabled    = false;
                btn.textContent = type === "optimize" ? "Optimize Tables" : "Clean Up";
                if (msgEl) {
                    msgEl.style.display = "inline";
                    msgEl.style.color   = res.success ? "#00a32a" : "#d63638";
                    msgEl.textContent   = res.success
                        ? (res.data.message || cfg.i18n.done)
                        : ((res.data && res.data.message) ? res.data.message : cfg.i18n.error);
                }
                // Refresh stats counters after cleanup
                loadStats();
            })
            .catch(function () {
                btn.disabled    = false;
                btn.textContent = "Clean Up";
                if (msgEl) { msgEl.style.display = "inline"; msgEl.style.color = "#d63638"; msgEl.textContent = cfg.i18n.error; }
            });
    });

    // Run all cleanups sequentially
    if (runAllBtn) {
        runAllBtn.addEventListener("click", function () {
            if (!confirm("Run ALL cleanup actions sequentially? This cannot be undone.")) return;
            runAllBtn.disabled = true;
            var types = ["revisions", "spam", "trash_comments", "orphaned_meta", "transients", "optimize"];
            runSequence(types, 0);
        });
    }

    function runSequence(types, i) {
        if (i >= types.length) {
            if (runAllBtn) runAllBtn.disabled = false;
            loadStats();
            return;
        }
        ajax("cotlas_db_cleanup", { _nonce: cfg.dbNonce, type: types[i] })
            .then(function (res) {
                // Show individual result message if element exists
                var msgEl = document.getElementById("ctls-msg-" + types[i]);
                if (msgEl && res.success && res.data) {
                    msgEl.style.display = "inline";
                    msgEl.style.color   = "#00a32a";
                    msgEl.textContent   = res.data.message || cfg.i18n.done;
                }
                runSequence(types, i + 1);
            })
            .catch(function () { runSequence(types, i + 1); }); // continue even on error
    }

    /* ════════════════════════════════════════════════════════════════════════
	 * 3. WHITELABEL – media library picker buttons
     * ════════════════════════════════════════════════════════════════════════ */
    document.addEventListener("click", function (e) {
        var btn = e.target.closest(".ctls-media-btn");
        if (!btn) return;
        e.preventDefault();
        if (!window.wp || !wp.media) return;

        var key = btn.getAttribute("data-target");
        var target = document.getElementById(key);
        var preview = document.getElementById("preview-" + key);
        if (!target) return;

        var frame = wp.media({
            title: "Select Image",
            library: { type: "image" },
            button: { text: "Use this image" },
            multiple: false
        });
        frame.on("select", function () {
            var att = frame.state().get("selection").first().toJSON();
            target.value = att.url;
            if (preview) {
                preview.innerHTML = '<img src="' + att.url + '" alt="">';
            }
        });
        frame.open();
    });

})(window.cotlasTools || {});
JS;
	}

	/* ═══════════════════════════════════════════════════════════════════════
	 * HELPERS
	 * ═══════════════════════════════════════════════════════════════════════ */

	/**
	 * Verify AJAX nonce + capability; dies with JSON error on failure.
	 *
	 * @param string $nonce_action The expected nonce action.
	 */
	private function verify_ajax( $nonce_action ) {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'cotlas-admin' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cotlas-admin' ) ) );
		}
	}

	/**
	 * Return the real client IP (Cloudflare-aware).
	 *
	 * @return string
	 */
	private function client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}

/* ═══════════════════════════════════════════════════════════════════════════
 * BOOT
 * ═══════════════════════════════════════════════════════════════════════════ */
Cotlas_Admin_Tools::get_instance();
