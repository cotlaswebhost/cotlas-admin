<?php
/**
 * Plugin Settings Export / Import
 *
 * Adds an "Export / Import" submenu under Cotlas Admin.
 * Exports all cotlas_ prefixed options to a JSON file,
 * and imports them back from a file upload.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

class Cotlas_Settings_Export {

	const PAGE_SLUG  = 'cotlas-export-import';
	const NONCE_NAME = 'cotlas_export_import';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Collect all plugin option keys and values.
	 */
	private static function collect_settings() {
		global $wpdb;
		$prefix = 'cotlas_';
		$other_keys = array(
			'turnstile_site_key', 'turnstile_secret_key',
			'turnstile_enable_login', 'turnstile_enable_register', 'turnstile_enable_comments',
			'recaptcha_v3_site_key', 'recaptcha_v3_secret_key', 'recaptcha_v3_score_threshold',
			'recaptcha_v3_enable_login', 'recaptcha_v3_enable_register', 'recaptcha_v3_enable_comments',
			'hcaptcha_site_key', 'hcaptcha_secret_key',
			'hcaptcha_enable_login', 'hcaptcha_enable_register', 'hcaptcha_enable_comments',
			'math_captcha_difficulty', 'math_captcha_enable_login', 'math_captcha_enable_register', 'math_captcha_enable_comments',
		);

		$keys = array();

		// Get all option keys starting with cotlas_.
		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'"
		);
		if ( $rows ) {
			$keys = array_merge( $keys, $rows );
		}

		// Get additional non-prefixed keys.
		foreach ( $other_keys as $key ) {
			$keys[] = $key;
		}

		$settings = array();
		foreach ( $keys as $key ) {
			$val = get_option( $key );
			if ( false !== $val ) {
				$settings[ $key ] = $val;
			}
		}

		return $settings;
	}

	/**
	 * Handle export download and import upload.
	 */
	public function handle_actions() {
		// Export.
		if ( isset( $_GET['cotlas_export'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_NAME ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Insufficient permissions.' );
			}

			$settings = self::collect_settings();
			$export = array(
				'plugin'    => 'cotlas-admin',
				'version'   => defined( 'COTLAS_ADMIN_VERSION' ) ? COTLAS_ADMIN_VERSION : '2.6.0',
				'exported'  => gmdate( 'Y-m-d H:i:s' ),
				'site_url'  => home_url( '/' ),
				'settings'  => $settings,
			);

			$json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="cotlas-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
			header( 'Content-Length: ' . strlen( $json ) );
			header( 'Cache-Control: no-cache, must-revalidate' );
			echo $json;
			exit;
		}

		// Import.
		if ( isset( $_POST['cotlas_import_submit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) ), self::NONCE_NAME ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Insufficient permissions.' );
			}

			if ( empty( $_FILES['cotlas_import_file'] ) || empty( $_FILES['cotlas_import_file']['tmp_name'] ) ) {
				add_settings_error( 'cotlas_import', 'no_file', 'Please select a file to import.', 'error' );
				return;
			}

			$file = $_FILES['cotlas_import_file'];

			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				add_settings_error( 'cotlas_import', 'upload_error', 'File upload failed. Please try again.', 'error' );
				return;
			}

			if ( $file['size'] > 2 * 1024 * 1024 ) {
				add_settings_error( 'cotlas_import', 'too_large', 'File is too large. Maximum 2 MB.', 'error' );
				return;
			}

			$content = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data    = json_decode( $content, true );

			if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				add_settings_error( 'cotlas_import', 'invalid', 'Invalid file format. Expected a Cotlas Admin export file.', 'error' );
				return;
			}

			$imported = 0;
			$skipped  = 0;

			// Allowed keys whitelist — only import known cotlas options.
			$allowed_prefixes = array( 'cotlas_', 'turnstile_', 'recaptcha_v3_', 'hcaptcha_', 'math_captcha_' );

			foreach ( $data['settings'] as $key => $value ) {
				$key = sanitize_text_field( $key );

				$allowed = false;
				foreach ( $allowed_prefixes as $prefix ) {
					if ( strpos( $key, $prefix ) === 0 ) {
						$allowed = true;
						break;
					}
				}

				if ( ! $allowed ) {
					$skipped++;
					continue;
				}

				// Sanitize based on value type.
				if ( is_string( $value ) ) {
					$value = wp_kses_post( $value );
				} elseif ( is_array( $value ) ) {
					$value = array_map( 'sanitize_text_field', $value );
				} elseif ( is_int( $value ) ) {
					$value = absint( $value );
				} elseif ( is_float( $value ) ) {
					$value = floatval( $value );
				}

				update_option( $key, $value );
				$imported++;
			}

			$source = isset( $data['site_url'] ) ? esc_url( $data['site_url'] ) : 'unknown site';
			$date   = isset( $data['exported'] ) ? esc_html( $data['exported'] ) : 'unknown date';

			add_settings_error(
				'cotlas_import',
				'success',
				sprintf(
					'Successfully imported %d settings from %s (exported %s). %s',
					$imported,
					$source,
					$date,
					$skipped > 0 ? $skipped . ' entries skipped.' : ''
				),
				'success'
			);
		}
	}

	/**
	 * Render the Export / Import tab inside Admin Tools.
	 */
	public function render_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ── Export ──
		ctap_card_open( 'Export Settings', 'dashicons-download' );

		$settings = self::collect_settings();
		$count    = count( $settings );
		$nonce    = wp_create_nonce( self::NONCE_NAME );
		$url      = admin_url( 'admin.php?page=cotlas-tools&tab=export-import&cotlas_export=1&_wpnonce=' . $nonce );

		ctap_info( 'Exports all Cotlas Admin plugin settings (including security keys, tracking codes, and all module configurations) to a JSON file. You can then import this file on another site to replicate the same configuration. For menus, widgets and Customizer settings, use the Site Export tab instead.' );

		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">Settings Count</div>';
		echo '<div class="ctap-field-input"><strong>' . esc_html( $count ) . '</strong> options will be exported.</div>';
		echo '</div>';

		echo '<div style="margin-top:16px;">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;font-size:13px;text-decoration:none;">';
		echo '<span class="dashicons dashicons-download" style="line-height:20px;"></span> Download Export File';
		echo '</a>';
		echo '</div>';

		ctap_card_close();

		// ── Import ──
		ctap_card_open( 'Import Settings', 'dashicons-upload' );

		if ( function_exists( 'settings_errors' ) ) {
			settings_errors( 'cotlas_import' );
		}

		ctap_info( 'Upload a <code>.json</code> export file from another Cotlas Admin installation. Existing settings will be overwritten. <strong>This action cannot be undone</strong> — consider exporting current settings first as a backup.' );

		echo '<form method="post" enctype="multipart/form-data" class="ctap-form">';
		echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE_NAME ) ) . '">';

		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">Select File</div>';
		echo '<div class="ctap-field-input">';
		echo '<input type="file" name="cotlas_import_file" accept=".json" required style="font-size:13px;">';
		echo '<p class="ctap-field-desc">Only <code>.json</code> files exported from Cotlas Admin are accepted.</p>';
		echo '</div></div>';

		echo '<div style="margin-top:16px;">';
		echo '<button type="submit" name="cotlas_import_submit" value="1" class="button button-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;font-size:13px;" onclick="return confirm(\'Import will overwrite existing settings. Continue?\')">';
		echo '<span class="dashicons dashicons-upload" style="line-height:20px;"></span> Import Settings';
		echo '</button>';
		echo '</div>';

		echo '</form>';
		ctap_card_close();
	}
}

Cotlas_Settings_Export::get_instance();