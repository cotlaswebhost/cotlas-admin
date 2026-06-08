<?php
/**
 * Cotlas Admin Tools
 *
 * Four-module admin toolbox registered under the existing Cotlas Admin menu:
 *
 *   1. Regenerate Thumbnails  – batch AJAX regeneration with live progress bar.
 *   2. Admin Notice Manager   – per-type toggles that suppress WP admin notices.
 *   3. Maintenance Mode       – toggle with custom message, role & IP whitelist.
 *   4. Database Cleanup       – live stats + individual / bulk cleanup actions.
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
			'regenNonce' => wp_create_nonce( self::NONCE_REGEN ),
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
	 * ACTIVE FEATURE: SUPPRESS ADMIN NOTICES
	 * ═══════════════════════════════════════════════════════════════════════ */

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
		ctap_page_open( 'Admin Tools', 'dashicons-admin-tools', 'Thumbnails · Admin Notices · Maintenance Mode · Database Cleanup' );

		$tabs = array(
			array( 'id' => 'thumbnails',  'label' => 'Thumbnails',    'icon' => 'dashicons-format-image' ),
			array( 'id' => 'notices',     'label' => 'Admin Notices', 'icon' => 'dashicons-megaphone' ),
			array( 'id' => 'maintenance', 'label' => 'Maintenance',   'icon' => 'dashicons-warning' ),
			array( 'id' => 'database',    'label' => 'Database',      'icon' => 'dashicons-database' ),
		);
		$active = ctap_nav( $tabs );

		ctap_pane_open( 'thumbnails', $active );
		$this->tab_thumbnails();
		ctap_pane_close();

		ctap_pane_open( 'notices', $active );
		$this->tab_notices();
		ctap_pane_close();

		ctap_pane_open( 'maintenance', $active );
		$this->tab_maintenance();
		ctap_pane_close();

		ctap_pane_open( 'database', $active );
		$this->tab_database();
		ctap_pane_close();

		ctap_page_close();
	}

	/* ─── Tab: Thumbnails ────────────────────────────────────────────────── */

	private function tab_thumbnails() {
		ctap_card_open( 'Regenerate All Thumbnails', 'dashicons-format-image' );
		ctap_info(
			'<strong>Warning:</strong> This re-creates every image size for all uploaded images. ' .
			'On large media libraries it can take several minutes. ' .
			'<strong>Do not close this tab</strong> while processing.'
		);
		?>
		<div class="ctap-field-row ctls-regen-wrap" style="flex-direction:column;align-items:flex-start;gap:14px">

			<div id="ctls-regen-progress" style="display:none;width:100%">
				<p id="ctls-regen-label" style="font-size:13px;color:#555;margin:0 0 6px">Preparing…</p>
				<div style="background:#e0e7ef;border-radius:6px;height:16px;overflow:hidden;max-width:500px">
					<div id="ctls-regen-bar" style="background:#2271b1;height:100%;width:0%;border-radius:6px;transition:width .3s ease"></div>
				</div>
				<p id="ctls-regen-count" style="font-size:12px;color:#888;margin:4px 0 0">0 / 0</p>
				<p id="ctls-regen-time"  style="font-size:12px;color:#aaa;margin:2px 0 0"></p>
			</div>

			<div id="ctls-regen-result" class="notice notice-success inline" style="display:none;margin:0;padding:8px 14px">
				<p id="ctls-regen-result-msg" style="margin:0"></p>
			</div>

			<button type="button" id="ctls-regen-btn" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px">
				<span class="dashicons dashicons-update" style="line-height:28px"></span>
				<?php esc_html_e( 'Regenerate All Thumbnails', 'cotlas-admin' ); ?>
			</button>
		</div>
		<?php
		ctap_card_close();
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
			. '<span class="dashicons dashicons-update" style="line-height:26px"></span> '
			. esc_html__( 'Refresh Stats', 'cotlas-admin' )
			. '</button>';
		echo '<button type="button" id="ctls-db-run-all" class="button button-primary" disabled>'
			. '<span class="dashicons dashicons-database" style="line-height:26px"></span> '
			. esc_html__( 'Run All Cleanups', 'cotlas-admin' )
			. '</button>';
		echo '</div>';

		ctap_card_close();
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

/* Regen progress */
.ctls-regen-wrap{width:100%}
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
     * 1. THUMBNAIL REGENERATION
     * ════════════════════════════════════════════════════════════════════════ */
    var regenBtn    = document.getElementById("ctls-regen-btn");
    var regenProg   = document.getElementById("ctls-regen-progress");
    var regenBar    = document.getElementById("ctls-regen-bar");
    var regenLabel  = document.getElementById("ctls-regen-label");
    var regenCount  = document.getElementById("ctls-regen-count");
    var regenTime   = document.getElementById("ctls-regen-time");
    var regenResult = document.getElementById("ctls-regen-result");
    var regenMsg    = document.getElementById("ctls-regen-result-msg");

    if (regenBtn) {
        regenBtn.addEventListener("click", function () {
            if (!confirm("Regenerate thumbnails for ALL images? This may take a while on large sites.")) return;

            regenBtn.disabled     = true;
            regenProg.style.display = "block";
            regenResult.style.display = "none";
            regenLabel.textContent  = "Fetching image count…";
            regenBar.style.width    = "0%";

            var startTime  = Date.now();
            var totalErrors = 0;

            ajax("cotlas_regen_init", { _nonce: cfg.regenNonce })
                .then(function (res) {
                    if (!res.success) { regenError(res); return; }
                    var total = res.data.total;
                    if (total === 0) { regenFinish(0, 0, 0, startTime); return; }
                    regenBatch(0, total, totalErrors, startTime);
                })
                .catch(regenError);
        });
    }

    function regenBatch(offset, total, errors, startTime) {
        ajax("cotlas_regen_batch", { _nonce: cfg.regenNonce, offset: offset })
            .then(function (res) {
                if (!res.success) { regenError(res); return; }
                var d   = res.data;
                var pct = total > 0 ? Math.round((d.processed / total) * 100) : 100;

                regenBar.style.width    = pct + "%";
                regenCount.textContent  = d.processed + " / " + total + " (" + pct + "%)";
                regenLabel.textContent  = d.last ? "Last processed: " + d.last : "Processing…";
                regenTime.textContent   = "Elapsed: " + ((Date.now() - startTime) / 1000).toFixed(1) + "s";

                if (d.done) {
                    regenFinish(d.processed, total, errors + (d.errors || 0), startTime);
                } else {
                    regenBatch(d.next_offset, total, errors + (d.errors || 0), startTime);
                }
            })
            .catch(regenError);
    }

    function regenFinish(processed, total, errors, startTime) {
        var elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
        regenBar.style.width        = "100%";
        regenProg.style.display     = "none";
        regenResult.style.display   = "block";
        regenResult.className       = "notice notice-success inline";
        regenMsg.textContent        = processed + " of " + total + " thumbnails regenerated in " + elapsed + "s."
            + (errors > 0 ? " (" + errors + " file error" + (errors > 1 ? "s" : "") + ")" : "");
        regenBtn.disabled = false;
    }

    function regenError(res) {
        regenProg.style.display   = "none";
        regenResult.style.display = "block";
        regenResult.className     = "notice notice-error inline";
        var msg = (res && res.data && res.data.message) ? res.data.message : cfg.i18n.error;
        regenMsg.textContent = msg;
        if (regenBtn) regenBtn.disabled = false;
    }

    /* ════════════════════════════════════════════════════════════════════════
     * 2. ADMIN NOTICES – Reset button
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
     * 3. DATABASE CLEANUP
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
                    refreshBtn.innerHTML = '<span class="dashicons dashicons-update" style="line-height:26px"></span> Refresh Stats';
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
