<?php
/**
 * Security Hardening Module
 *
 * Migrated from mu-plugins into Cotlas Admin for toggle-able control.
 * Each feature is gated by its own wp_options toggle (default: off).
 *
 * Features:
 *   Login Protection — account lockout, user-enumeration prevention,
 *       login-redirect hardening, right-click guard, single-session,
 *       session timeout (absolute + idle).
 *   Form Hardening — autocomplete off on auth/contact forms,
 *       password-complexity enforcement, re-authentication for password changes.
 *   Data & Access — REST user-endpoint block, RSS feed disable,
 *       jQuery Migrate removal + version fingerprint strip.
 *   Network — CORS strict allowlist for REST API, email obfuscation.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════════
 * LOGIN PROTECTION
 * ═══════════════════════════════════════════════════════════════════════════ */

/* ── Account Lockout ────────────────────────────────────────────────────── */

if ( ! defined( 'COTLAS_LOCKOUT_MAX_ATTEMPTS' ) ) {
	define( 'COTLAS_LOCKOUT_MAX_ATTEMPTS', 5 );
}

if ( ! defined( 'COTLAS_LOCKOUT_WINDOW_SECONDS' ) ) {
	define( 'COTLAS_LOCKOUT_WINDOW_SECONDS', 15 * MINUTE_IN_SECONDS );
}

if ( ! function_exists( 'cotlas_lockout_normalize_identifier' ) ) {
	function cotlas_lockout_normalize_identifier( $identifier ) {
		$identifier = is_string( $identifier ) ? trim( $identifier ) : '';
		return strtolower( $identifier );
	}
}

if ( ! function_exists( 'cotlas_lockout_key' ) ) {
	function cotlas_lockout_key( $identifier ) {
		return 'cotlas_acct_lock_' . md5( $identifier );
	}
}

if ( ! function_exists( 'cotlas_lockout_get_client_ip' ) ) {
	function cotlas_lockout_get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
		return '0.0.0.0';
	}
}

if ( ! function_exists( 'cotlas_lockout_get_ip_identifier' ) ) {
	function cotlas_lockout_get_ip_identifier() {
		return 'ip:' . cotlas_lockout_get_client_ip();
	}
}

if ( ! function_exists( 'cotlas_lockout_resolve_identifiers' ) ) {
	function cotlas_lockout_resolve_identifiers( $raw_identifier ) {
		$identifiers = array();
		$normalized  = cotlas_lockout_normalize_identifier( $raw_identifier );

		if ( $normalized === '' ) {
			return $identifiers;
		}

		$identifiers[] = $normalized;

		$user = false;
		if ( is_email( $normalized ) ) {
			$user = get_user_by( 'email', $normalized );
		} else {
			$user = get_user_by( 'login', $normalized );
			if ( ! $user && strpos( $normalized, '@') !== false ) {
				$user = get_user_by( 'email', $normalized );
			}
		}

		if ( $user instanceof WP_User ) {
			$identifiers[] = cotlas_lockout_normalize_identifier( $user->user_login );
			$identifiers[] = cotlas_lockout_normalize_identifier( $user->user_email );
		}

		return array_values( array_unique( array_filter( $identifiers ) ) );
	}
}

if ( ! function_exists( 'cotlas_lockout_get_state' ) ) {
	function cotlas_lockout_get_state( $identifier ) {
		$state = get_transient( cotlas_lockout_key( $identifier ) );
		if ( ! is_array( $state ) ) {
			$state = array( 'count' => 0, 'locked_until' => 0 );
		}
		$state['count']        = isset( $state['count'] ) ? (int) $state['count'] : 0;
		$state['locked_until'] = isset( $state['locked_until'] ) ? (int) $state['locked_until'] : 0;
		return $state;
	}
}

if ( ! function_exists( 'cotlas_lockout_set_state' ) ) {
	function cotlas_lockout_set_state( $identifier, array $state ) {
		set_transient( cotlas_lockout_key( $identifier ), $state, COTLAS_LOCKOUT_WINDOW_SECONDS );
	}
}

if ( ! function_exists( 'cotlas_lockout_clear_identifier' ) ) {
	function cotlas_lockout_clear_identifier( $identifier ) {
		if ( $identifier === '' ) {
			return;
		}
		delete_transient( cotlas_lockout_key( $identifier ) );
	}
}

if ( ! function_exists( 'cotlas_lockout_is_locked' ) ) {
	function cotlas_lockout_is_locked( $raw_identifier ) {
		$identifiers  = cotlas_lockout_resolve_identifiers( $raw_identifier );
		$identifiers[] = cotlas_lockout_get_ip_identifier();
		$identifiers   = array_values( array_unique( array_filter( $identifiers ) ) );

		foreach ( $identifiers as $identifier ) {
			$state = cotlas_lockout_get_state( $identifier );
			if ( $state['locked_until'] > time() ) {
				return true;
			}
			if ( $state['locked_until'] > 0 && $state['locked_until'] <= time() ) {
				cotlas_lockout_clear_identifier( $identifier );
			}
		}
		return false;
	}
}

if ( ! function_exists( 'cotlas_lockout_register_failure' ) ) {
	function cotlas_lockout_register_failure( $raw_identifier ) {
		$identifiers  = cotlas_lockout_resolve_identifiers( $raw_identifier );
		$identifiers[] = cotlas_lockout_get_ip_identifier();
		$identifiers   = array_values( array_unique( array_filter( $identifiers ) ) );

		if ( empty( $identifiers ) ) {
			return;
		}

		$now = time();
		foreach ( $identifiers as $identifier ) {
			$state = cotlas_lockout_get_state( $identifier );
			if ( $state['locked_until'] > $now ) {
				continue;
			}
			if ( $state['locked_until'] > 0 && $state['locked_until'] <= $now ) {
				$state = array( 'count' => 0, 'locked_until' => 0 );
			}
			$state['count']++;
			if ( $state['count'] >= COTLAS_LOCKOUT_MAX_ATTEMPTS ) {
				$state['locked_until'] = $now + COTLAS_LOCKOUT_WINDOW_SECONDS;
				$state['count']        = COTLAS_LOCKOUT_MAX_ATTEMPTS;
			}
			cotlas_lockout_set_state( $identifier, $state );
		}
	}
}

if ( ! function_exists( 'cotlas_lockout_clear_for_user' ) ) {
	function cotlas_lockout_clear_for_user( $user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}
		cotlas_lockout_clear_identifier( cotlas_lockout_normalize_identifier( $user_login ) );
		cotlas_lockout_clear_identifier( cotlas_lockout_normalize_identifier( $user->user_login ) );
		cotlas_lockout_clear_identifier( cotlas_lockout_normalize_identifier( $user->user_email ) );
	}
}

if ( ! function_exists( 'cotlas_lockout_prevent_locked_login' ) ) {
	function cotlas_lockout_prevent_locked_login( $user, $username, $password ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}
		if ( $username === null || trim( (string) $username ) === '' ) {
			return $user;
		}
		if ( cotlas_lockout_is_locked( $username ) ) {
			return new WP_Error( 'cotlas_account_locked', __( 'Too many failed login attempts. Please try again in 15 minutes.', 'cotlas-admin' ) );
		}
		return $user;
	}
}

if ( ! function_exists( 'cotlas_lockout_enforce_after_auth' ) ) {
	function cotlas_lockout_enforce_after_auth( $user, $username, $password ) {
		if ( $username === null || trim( (string) $username ) === '' ) {
			return $user;
		}
		if ( cotlas_lockout_is_locked( $username ) ) {
			return new WP_Error( 'cotlas_account_locked', __( 'Too many failed login attempts. Please try again in 15 minutes.', 'cotlas-admin' ) );
		}
		return $user;
	}
}

if ( ! function_exists( 'cotlas_lockout_on_failed_login' ) ) {
	function cotlas_lockout_on_failed_login( $username ) {
		cotlas_lockout_register_failure( $username );
	}
}

function cotlas_sec_hook_account_lockout() {
	add_filter( 'authenticate', 'cotlas_lockout_prevent_locked_login', 5, 3 );
	add_filter( 'authenticate', 'cotlas_lockout_enforce_after_auth', 99, 3 );
	add_action( 'wp_login_failed', 'cotlas_lockout_on_failed_login', 10, 1 );
	add_action( 'wp_login', 'cotlas_lockout_clear_for_user', 10, 2 );
}

/* ── User Enumeration Prevention ────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_user_enum_generic_error_text' ) ) {
	function cotlas_user_enum_generic_error_text() {
		return __( 'Invalid login credentials.', 'cotlas-admin' );
	}
}

if ( ! function_exists( 'cotlas_user_enum_genericize_auth_error' ) ) {
	function cotlas_user_enum_genericize_auth_error( $user, $username, $password ) {
		if ( ! ( $user instanceof WP_Error ) ) {
			return $user;
		}
		if ( $user->get_error_code() === 'cotlas_account_locked' ) {
			return $user;
		}
		return new WP_Error( 'authentication_failed', cotlas_user_enum_generic_error_text() );
	}
}

if ( ! function_exists( 'cotlas_user_enum_login_errors' ) ) {
	function cotlas_user_enum_login_errors( $errors ) {
		if ( is_string( $errors ) && $errors !== '' ) {
			if ( stripos( $errors, 'Too many failed login attempts' ) !== false ) {
				return $errors;
			}
		}
		return cotlas_user_enum_generic_error_text();
	}
}

if ( ! function_exists( 'cotlas_user_enum_shake_codes' ) ) {
	function cotlas_user_enum_shake_codes( $codes ) {
		if ( ! is_array( $codes ) ) {
			return array();
		}
		$remove = array( 'invalid_username', 'incorrect_password' );
		return array_values( array_diff( $codes, $remove ) );
	}
}

function cotlas_sec_hook_user_enumeration() {
	add_filter( 'authenticate', 'cotlas_user_enum_genericize_auth_error', 100, 3 );
	add_filter( 'login_errors', 'cotlas_user_enum_login_errors', 100 );
	add_filter( 'shake_error_codes', 'cotlas_user_enum_shake_codes', 100 );
}

/* ── Login Redirect Hardening ───────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_normalize_login_redirect' ) ) {
	function cotlas_normalize_login_redirect( $candidate ) {
		$fallback = '/wp-admin/';

		if ( ! is_string( $candidate ) ) {
			return $fallback;
		}

		$candidate = trim( wp_unslash( $candidate ) );

		if ( $candidate === '' ) {
			return $fallback;
		}

		if (
			preg_match( '/[\x00-\x1F\x7F]/', $candidate ) ||
			strpos( $candidate, '\\' ) !== false
		) {
			return $fallback;
		}

		if ( strpos( $candidate, '//' ) === 0 ) {
			return $fallback;
		}

		$parts = wp_parse_url( $candidate );

		if ( false === $parts ) {
			return $fallback;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return $fallback;
		}

		$home_parts = wp_parse_url( home_url( '/' ) );

		if ( false === $home_parts || empty( $home_parts['host'] ) ) {
			return $fallback;
		}

		if ( isset( $parts['host'] ) ) {
			$candidate_scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
			$home_scheme      = isset( $home_parts['scheme'] ) ? strtolower( $home_parts['scheme'] ) : 'https';
			$candidate_host   = strtolower( rtrim( $parts['host'], '.' ) );
			$home_host        = strtolower( rtrim( $home_parts['host'], '.' ) );
			$candidate_port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;
			$home_port        = isset( $home_parts['port'] ) ? (int) $home_parts['port'] : null;

			if (
				! in_array( $candidate_scheme, array( 'http', 'https' ), true ) ||
				$candidate_scheme !== $home_scheme ||
				$candidate_host !== $home_host ||
				$candidate_port !== $home_port
			) {
				return $fallback;
			}
		} elseif ( empty( $parts['path'] ) || strpos( $parts['path'], '/' ) !== 0 ) {
			return $fallback;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/wp-admin/';

		if ( strpos( $path, '/' ) !== 0 ) {
			return $fallback;
		}

		$relative_url = $path;
		if ( isset( $parts['query'] ) && $parts['query'] !== '' ) {
			$relative_url .= '?' . $parts['query'];
		}

		$validated = wp_validate_redirect( $relative_url, $fallback );

		if ( ! is_string( $validated ) || strpos( $validated, '/' ) !== 0 ) {
			return $fallback;
		}

		return $validated;
	}
}

if ( ! function_exists( 'cotlas_harden_login_redirect_request' ) ) {
	function cotlas_harden_login_redirect_request() {
		$sources = array( '_GET', '_POST', '_REQUEST' );
		foreach ( $sources as $source ) {
			if (
				! isset( $GLOBALS[ $source ] ) ||
				! is_array( $GLOBALS[ $source ] ) ||
				! isset( $GLOBALS[ $source ]['redirect_to'] )
			) {
				continue;
			}
			$GLOBALS[ $source ]['redirect_to'] = cotlas_normalize_login_redirect( $GLOBALS[ $source ]['redirect_to'] );
		}
	}
}

if ( ! function_exists( 'cotlas_enforce_safe_login_redirect' ) ) {
	function cotlas_enforce_safe_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		return cotlas_normalize_login_redirect( $requested_redirect_to );
	}
}

function cotlas_sec_hook_login_redirect_hardening() {
	add_action( 'login_init', 'cotlas_harden_login_redirect_request', 0 );
	add_filter( 'login_redirect', 'cotlas_enforce_safe_login_redirect', 99, 3 );
}

/* ── Login Right-Click Guard ────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_login_rightclick_guard_should_run' ) ) {
	function cotlas_login_rightclick_guard_should_run() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : 'login';
		return in_array( $action, array( 'login', 'register', 'lostpassword', 'retrievepassword', 'rp', 'resetpass' ), true );
	}
}

if ( ! function_exists( 'cotlas_login_rightclick_guard_script' ) ) {
	function cotlas_login_rightclick_guard_script() {
		if ( ! cotlas_login_rightclick_guard_should_run() ) {
			return;
		}
		cotlas_rightclick_guard_print_script();
	}
}

if ( ! function_exists( 'cotlas_rightclick_guard_print_script' ) ) {
	function cotlas_rightclick_guard_print_script() {
		?>
		<script>
		(function () {
			document.addEventListener('contextmenu', function (event) {
				event.preventDefault();
			}, { passive: false });
		})();
		</script>
		<?php
	}
}

if ( ! function_exists( 'cotlas_rightclick_guard_frontend' ) ) {
	function cotlas_rightclick_guard_frontend() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( ! get_option( 'cotlas_auth_enabled' ) ) {
			return;
		}
		// Only inject on pages that actually contain a Cotlas auth form.
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if (
			has_shortcode( $post->post_content, 'cotlas_login' ) ||
			has_shortcode( $post->post_content, 'cotlas_register' ) ||
			has_shortcode( $post->post_content, 'cotlas_forgot_password' ) ||
			has_shortcode( $post->post_content, 'cotlas_auth_panel' )
		) {
			cotlas_rightclick_guard_print_script();
		}
	}
}

function cotlas_sec_hook_rightclick_guard() {
	add_action( 'login_footer', 'cotlas_login_rightclick_guard_script', 100 );
	add_action( 'wp_footer', 'cotlas_rightclick_guard_frontend', 100 );
}

/* ── Single Session Enforcement ─────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_single_session_enforce_token' ) ) {
	function cotlas_single_session_enforce_token( $user_id, $token ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || $token === '' ) {
			return;
		}
		$manager = WP_Session_Tokens::get_instance( $user_id );
		if ( ! $manager ) {
			return;
		}
		$manager->destroy_others( $token );
	}
}

if ( ! function_exists( 'cotlas_single_session_on_logged_in_cookie' ) ) {
	function cotlas_single_session_on_logged_in_cookie( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		cotlas_single_session_enforce_token( $user_id, (string) $token );
	}
}

function cotlas_sec_hook_single_session() {
	add_action( 'set_logged_in_cookie', 'cotlas_single_session_on_logged_in_cookie', 100, 6 );
}

/* ── Session Timeout ────────────────────────────────────────────────────── */

if ( ! defined( 'COTLAS_SESSION_ABSOLUTE_TTL' ) ) {
	define( 'COTLAS_SESSION_ABSOLUTE_TTL', 8 * HOUR_IN_SECONDS );
}

if ( ! defined( 'COTLAS_SESSION_IDLE_TTL' ) ) {
	define( 'COTLAS_SESSION_IDLE_TTL', 30 * MINUTE_IN_SECONDS );
}

if ( ! function_exists( 'cotlas_session_timeout_cookie_expiry' ) ) {
	function cotlas_session_timeout_cookie_expiry( $length, $user_id, $remember ) {
		return COTLAS_SESSION_ABSOLUTE_TTL;
	}
}

if ( ! function_exists( 'cotlas_session_timeout_activity_key' ) ) {
	function cotlas_session_timeout_activity_key( $token ) {
		return 'cotlas_session_last_activity_' . hash( 'sha256', $token );
	}
}

if ( ! function_exists( 'cotlas_session_timeout_record_login' ) ) {
	function cotlas_session_timeout_record_login( $user_login, $user ) {
		$token = wp_get_session_token();
		if ( $token === '' ) {
			return;
		}
		$key = cotlas_session_timeout_activity_key( $token );
		update_user_meta( (int) $user->ID, $key, time() );
	}
}

if ( ! function_exists( 'cotlas_session_timeout_record_cookie_issue' ) ) {
	function cotlas_session_timeout_record_cookie_issue( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		if ( $token === '' ) {
			return;
		}
		$key = cotlas_session_timeout_activity_key( $token );
		update_user_meta( (int) $user_id, $key, time() );
	}
}

if ( ! function_exists( 'cotlas_session_timeout_is_heartbeat_request' ) ) {
	function cotlas_session_timeout_is_heartbeat_request() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}
		$action = '';
		if ( isset( $_POST['action'] ) ) {
			$action = sanitize_key( wp_unslash( (string) $_POST['action'] ) );
		} elseif ( isset( $_GET['action'] ) ) {
			$action = sanitize_key( wp_unslash( (string) $_GET['action'] ) );
		}
		return $action === 'heartbeat';
	}
}

if ( ! function_exists( 'cotlas_session_timeout_is_interactive_request' ) ) {
	function cotlas_session_timeout_is_interactive_request() {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}
		if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) {
			$requested_with = strtolower( trim( (string) wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) );
			if ( $requested_with === 'xmlhttprequest' ) {
				return false;
			}
		}
		if ( isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) {
			$fetch_dest = strtolower( trim( (string) wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) );
			if ( $fetch_dest !== '' && $fetch_dest !== 'document' ) {
				return false;
			}
		}
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		return $accept === '' || strpos( $accept, 'text/html' ) !== false;
	}
}

if ( ! function_exists( 'cotlas_session_timeout_enforce_idle' ) ) {
	function cotlas_session_timeout_enforce_idle() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		$token   = wp_get_session_token();
		if ( $token === '' ) {
			return;
		}

		$key         = cotlas_session_timeout_activity_key( $token );
		$now         = time();
		$last_active = (int) get_user_meta( $user_id, $key, true );

		if ( $last_active > 0 && ( $now - $last_active ) > COTLAS_SESSION_IDLE_TTL ) {
			wp_logout();
			if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				status_header( 401 );
				nocache_headers();
				wp_die( esc_html__( 'Session expired due to inactivity. Please log in again.', 'cotlas-admin' ) );
			}
			$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url( '/' );
			$login_url   = add_query_arg(
				array( 'reauth' => '1', 'session_expired' => '1' ),
				wp_login_url( $redirect_to )
			);
			wp_safe_redirect( $login_url );
			exit;
		}

		if ( cotlas_session_timeout_is_heartbeat_request() || ! cotlas_session_timeout_is_interactive_request() ) {
			return;
		}

		if ( $last_active === 0 || ( $now - $last_active ) >= MINUTE_IN_SECONDS ) {
			update_user_meta( $user_id, $key, $now );
		}
	}
}

function cotlas_sec_hook_session_timeout() {
	add_filter( 'auth_cookie_expiration', 'cotlas_session_timeout_cookie_expiry', 99, 3 );
	add_action( 'wp_login', 'cotlas_session_timeout_record_login', 10, 2 );
	add_action( 'set_logged_in_cookie', 'cotlas_session_timeout_record_cookie_issue', 10, 6 );
	add_action( 'init', 'cotlas_session_timeout_enforce_idle', 1 );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * FORM HARDENING
 * ═══════════════════════════════════════════════════════════════════════════ */

/* ── Autocomplete Hardening ─────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_ac_harden_comment_fields' ) ) {
	function cotlas_ac_harden_comment_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		foreach ( $fields as $key => $field_html ) {
			if ( ! is_string( $field_html ) || $field_html === '' ) {
				continue;
			}
			if ( stripos( $field_html, 'autocomplete=' ) === false ) {
				$fields[ $key ] = preg_replace( '/<input\b/i', '<input autocomplete="off"', $field_html, 1 );
			}
		}
		return $fields;
	}
}

if ( ! function_exists( 'cotlas_ac_harden_comment_textarea' ) ) {
	function cotlas_ac_harden_comment_textarea( $defaults ) {
		if ( ! is_array( $defaults ) || empty( $defaults['comment_field'] ) || ! is_string( $defaults['comment_field'] ) ) {
			return $defaults;
		}
		if ( stripos( $defaults['comment_field'], 'autocomplete=' ) === false ) {
			$defaults['comment_field'] = preg_replace( '/<textarea\b/i', '<textarea autocomplete="off"', $defaults['comment_field'], 1 );
		}
		return $defaults;
	}
}

if ( ! function_exists( 'cotlas_ac_inject_frontend_script' ) ) {
	function cotlas_ac_inject_frontend_script() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		?>
<script>
(function () {
  function hardenForm(form) {
    if (!form) return;
    form.setAttribute('autocomplete', 'off');
    var elements = form.querySelectorAll('input, textarea, select');
    for (var i = 0; i < elements.length; i++) {
      elements[i].setAttribute('autocomplete', 'off');
    }
  }
  function shouldHarden(form) {
    var marker = [form.id||'', form.className||'', form.getAttribute('name')||'', form.getAttribute('action')||''].join(' ').toLowerCase();
    return marker.indexOf('login')!==-1 || marker.indexOf('register')!==-1 || marker.indexOf('reset')!==-1 || marker.indexOf('lostpassword')!==-1 || marker.indexOf('comment')!==-1 || marker.indexOf('contact')!==-1 || marker.indexOf('elementor')!==-1 || marker.indexOf('wpforms')!==-1 || marker.indexOf('forminator')!==-1 || marker.indexOf('cotlas-auth')!==-1 || marker.indexOf('ctc-form')!==-1;
  }
  function run() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) { if (shouldHarden(forms[i])) hardenForm(forms[i]); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
		<?php
	}
}

if ( ! function_exists( 'cotlas_ac_inject_login_script' ) ) {
	function cotlas_ac_inject_login_script() {
		?>
<script>
(function () {
  function run() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      forms[i].setAttribute('autocomplete', 'off');
      var fields = forms[i].querySelectorAll('input, textarea, select');
      for (var j = 0; j < fields.length; j++) fields[j].setAttribute('autocomplete', 'off');
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
		<?php
	}
}

function cotlas_sec_hook_autocomplete_hardening() {
	add_filter( 'comment_form_default_fields', 'cotlas_ac_harden_comment_fields' );
	add_filter( 'comment_form_defaults', 'cotlas_ac_harden_comment_textarea' );
	add_action( 'wp_footer', 'cotlas_ac_inject_frontend_script', 100 );
	add_action( 'login_footer', 'cotlas_ac_inject_login_script', 100 );
}

/* ── Password Policy ────────────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_password_policy_validate' ) ) {
	function cotlas_password_policy_validate( $password ) {
		$errors = array();
		if ( strlen( $password ) < 12 ) {
			$errors[] = __( 'Password must be at least 12 characters long.', 'cotlas-admin' );
		}
		if ( ! preg_match( '/[a-z]/', $password ) ) {
			$errors[] = __( 'Password must include at least one lowercase letter.', 'cotlas-admin' );
		}
		if ( ! preg_match( '/[A-Z]/', $password ) ) {
			$errors[] = __( 'Password must include at least one uppercase letter.', 'cotlas-admin' );
		}
		if ( ! preg_match( '/[0-9]/', $password ) ) {
			$errors[] = __( 'Password must include at least one number.', 'cotlas-admin' );
		}
		if ( ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
			$errors[] = __( 'Password must include at least one special character.', 'cotlas-admin' );
		}
		return $errors;
	}
}

if ( ! function_exists( 'cotlas_password_policy_add_wp_errors' ) ) {
	function cotlas_password_policy_add_wp_errors( $wp_error, $password ) {
		foreach ( cotlas_password_policy_validate( $password ) as $message ) {
			$wp_error->add( 'cotlas_weak_password', $message );
		}
	}
}

if ( ! function_exists( 'cotlas_password_policy_has_new_password' ) ) {
	function cotlas_password_policy_has_new_password() {
		if ( ! isset( $_POST['pass1'] ) ) {
			return false;
		}
		return wp_unslash( (string) $_POST['pass1'] ) !== '';
	}
}

if ( ! function_exists( 'cotlas_password_policy_render_current_password_field' ) ) {
	function cotlas_password_policy_render_current_password_field( $profile_user ) {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->exists() ) {
			return;
		}
		$is_own_profile = get_current_user_id() === (int) $profile_user->ID;
		?>
		<table id="cotlas-password-verification-source" class="form-table" role="presentation" style="display:none;">
			<tbody>
				<tr id="cotlas-current-password-row">
					<th scope="row"><label for="cotlas_current_password"><?php esc_html_e( 'Current Password', 'cotlas-admin' ); ?></label></th>
					<td>
						<input type="password" id="cotlas_current_password" name="cotlas_current_password" class="regular-text" value="" autocomplete="current-password" spellcheck="false" />
						<p class="description">
							<?php
							if ( $is_own_profile ) {
								esc_html_e( 'Required when changing your password. Enter your existing password to authorize the change.', 'cotlas-admin' );
							} else {
								esc_html_e( 'Required when changing this user\'s password. Enter your own administrator password to authorize the change.', 'cotlas-admin' );
							}
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}

if ( ! function_exists( 'cotlas_password_policy_position_current_password_field' ) ) {
	function cotlas_password_policy_position_current_password_field() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
			return;
		}
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var sourceTable = document.getElementById('cotlas-password-verification-source');
			var currentPasswordRow = document.getElementById('cotlas-current-password-row');
			if (!sourceTable || !currentPasswordRow) return;
			var newPasswordRow = document.querySelector('tr.user-pass1-wrap');
			if (newPasswordRow) { newPasswordRow.insertAdjacentElement('afterend', currentPasswordRow); sourceTable.remove(); return; }
			var sessionsRow = document.querySelector('tr.user-sessions-wrap');
			if (sessionsRow) { sessionsRow.insertAdjacentElement('beforebegin', currentPasswordRow); sourceTable.remove(); return; }
			sourceTable.style.display = '';
		});
		</script>
		<?php
	}
}

if ( ! function_exists( 'cotlas_password_policy_handle_profile_update' ) ) {
	function cotlas_password_policy_handle_profile_update( $errors, $update, $user ) {
		if ( ! cotlas_password_policy_has_new_password() ) {
			return;
		}
		$new_password = wp_unslash( (string) $_POST['pass1'] );
		cotlas_password_policy_add_wp_errors( $errors, $new_password );

		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->exists() ) {
			$errors->add( 'cotlas_reauthentication_required', __( 'Your authenticated session could not be verified. Please sign in again.', 'cotlas-admin' ) );
			return;
		}

		$current_password = isset( $_POST['cotlas_current_password'] ) ? wp_unslash( (string) $_POST['cotlas_current_password'] ) : '';
		if ( $current_password === '' ) {
			$errors->add( 'cotlas_current_password_required', __( 'Enter your current account password to authorize this password change.', 'cotlas-admin' ) );
			return;
		}

		if ( ! wp_check_password( $current_password, $actor->user_pass, $actor->ID ) ) {
			$errors->add( 'cotlas_current_password_incorrect', __( 'The current account password is incorrect. The password was not changed.', 'cotlas-admin' ) );
		}
	}
}

if ( ! function_exists( 'cotlas_password_policy_handle_password_reset' ) ) {
	function cotlas_password_policy_handle_password_reset( $errors, $user ) {
		if ( ! cotlas_password_policy_has_new_password() ) {
			return;
		}
		$password = wp_unslash( (string) $_POST['pass1'] );
		cotlas_password_policy_add_wp_errors( $errors, $password );
	}
}

if ( ! function_exists( 'cotlas_password_policy_handle_registration' ) ) {
	function cotlas_password_policy_handle_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( ! cotlas_password_policy_has_new_password() ) {
			return $errors;
		}
		$password = wp_unslash( (string) $_POST['pass1'] );
		cotlas_password_policy_add_wp_errors( $errors, $password );
		return $errors;
	}
}

function cotlas_sec_hook_password_policy() {
	add_action( 'show_user_profile', 'cotlas_password_policy_render_current_password_field' );
	add_action( 'edit_user_profile', 'cotlas_password_policy_render_current_password_field' );
	add_action( 'admin_footer-profile.php', 'cotlas_password_policy_position_current_password_field' );
	add_action( 'admin_footer-user-edit.php', 'cotlas_password_policy_position_current_password_field' );
	add_action( 'user_profile_update_errors', 'cotlas_password_policy_handle_profile_update', 10, 3 );
	add_action( 'validate_password_reset', 'cotlas_password_policy_handle_password_reset', 10, 2 );
	add_filter( 'registration_errors', 'cotlas_password_policy_handle_registration', 10, 3 );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * DATA & ACCESS
 * ═══════════════════════════════════════════════════════════════════════════ */

/* ── REST User Endpoint Block ───────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_sensitive_disclosure_block_user_rest' ) ) {
	function cotlas_sensitive_disclosure_block_user_rest( $response, $handler, $request ) {
		$route = $request->get_route();
		if ( strpos( $route, '/wp/v2/users' ) !== 0 ) {
			return $response;
		}
		if ( is_user_logged_in() ) {
			return $response;
		}
		return new WP_Error( 'rest_forbidden', __( 'User data endpoint is not publicly accessible.', 'cotlas-admin' ), array( 'status' => 403 ) );
	}
}

function cotlas_sec_hook_rest_user_block() {
	add_filter( 'rest_request_before_callbacks', 'cotlas_sensitive_disclosure_block_user_rest', 10, 3 );
}

/* ── Disable RSS Feeds ──────────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_sensitive_disclosure_disable_all_feeds' ) ) {
	function cotlas_sensitive_disclosure_disable_all_feeds() {
		wp_die( esc_html__( 'Feed access is disabled.', 'cotlas-admin' ), '', array( 'response' => 410 ) );
	}
}

function cotlas_sec_hook_disable_feeds() {
	add_action( 'do_feed', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_rdf', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_rss', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_rss2', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_atom', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_rss2_comments', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
	add_action( 'do_feed_atom_comments', 'cotlas_sensitive_disclosure_disable_all_feeds', 1 );
}

/* ── jQuery Hardening ───────────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_jquery_hardening_remove_migrate_dependency' ) ) {
	function cotlas_jquery_hardening_remove_migrate_dependency( $scripts ) {
		if ( ! ( $scripts instanceof WP_Scripts ) ) {
			return;
		}
		if ( defined( 'COTLAS_KEEP_JQUERY_MIGRATE' ) && COTLAS_KEEP_JQUERY_MIGRATE ) {
			return;
		}
		if ( isset( $scripts->registered['jquery'] ) && is_array( $scripts->registered['jquery']->deps ) ) {
			$scripts->registered['jquery']->deps = array_values(
				array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) )
			);
		}
		if ( isset( $scripts->registered['jquery-migrate'] ) ) {
			$scripts->registered['jquery-migrate']->deps = array();
		}
	}
}

if ( ! function_exists( 'cotlas_jquery_hardening_strip_jquery_versions' ) ) {
	function cotlas_jquery_hardening_strip_jquery_versions( $src, $handle ) {
		$jquery_handles = array(
			'jquery', 'jquery-core', 'jquery-migrate',
			'jquery-ui-core', 'jquery-ui-accordion', 'jquery-ui-autocomplete',
			'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-dialog',
			'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-menu',
			'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-progressbar',
			'jquery-ui-resizable', 'jquery-ui-selectable', 'jquery-ui-slider',
			'jquery-ui-sortable', 'jquery-ui-spinner', 'jquery-ui-tabs',
			'jquery-ui-tooltip', 'jquery-ui-widget',
		);
		if ( ! in_array( $handle, $jquery_handles, true ) ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}
}

function cotlas_sec_hook_jquery_hardening() {
	add_action( 'wp_default_scripts', 'cotlas_jquery_hardening_remove_migrate_dependency', 100 );
	add_filter( 'script_loader_src', 'cotlas_jquery_hardening_strip_jquery_versions', 20, 2 );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * NETWORK
 * ═══════════════════════════════════════════════════════════════════════════ */

/* ── CORS Hardening ─────────────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_rest_cors_allowed_origins' ) ) {
	function cotlas_rest_cors_allowed_origins() {
		$raw = get_option( 'cotlas_sec_cors_origins', '' );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return array();
		}
		$origins = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		return array_values( $origins );
	}
}

if ( ! function_exists( 'cotlas_rest_cors_disable_default' ) ) {
	function cotlas_rest_cors_disable_default() {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	}
}

if ( ! function_exists( 'cotlas_rest_cors_send_headers' ) ) {
	function cotlas_rest_cors_send_headers( $served, $result, $request, $server ) {
		$origin = get_http_origin();
		if ( empty( $origin ) ) {
			return $served;
		}
		$origin  = strtolower( untrailingslashit( $origin ) );
		$allowed = array_map( 'strtolower', cotlas_rest_cors_allowed_origins() );
		if ( ! in_array( $origin, $allowed, true ) ) {
			return $served;
		}
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE' );
		header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type' );
		header( 'Vary: Origin', false );
		return $served;
	}
}

function cotlas_sec_hook_cors_hardening() {
	add_action( 'rest_api_init', 'cotlas_rest_cors_disable_default', 15 );
	add_filter( 'rest_pre_serve_request', 'cotlas_rest_cors_send_headers', 15, 4 );
}

/* ── Email Obfuscation ──────────────────────────────────────────────────── */

if ( ! function_exists( 'cotlas_email_obfuscation_encode' ) ) {
	function cotlas_email_obfuscation_encode( $email ) {
		$encoded = '';
		$length  = strlen( $email );
		for ( $i = 0; $i < $length; $i++ ) {
			$encoded .= '&#x' . dechex( ord( $email[ $i ] ) ) . ';';
		}
		return $encoded;
	}
}

if ( ! function_exists( 'cotlas_email_obfuscation_filter_output' ) ) {
	function cotlas_email_obfuscation_filter_output( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}
		$html = preg_replace( '/mailto:\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', '$1', $html );
		$html = preg_replace_callback(
			'/\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b/i',
			static function ( $matches ) {
				return cotlas_email_obfuscation_encode( $matches[1] );
			},
			$html
		);
		return $html;
	}
}

if ( ! function_exists( 'cotlas_email_obfuscation_start_buffer' ) ) {
	function cotlas_email_obfuscation_start_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_trackback() || is_robots() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		ob_start( 'cotlas_email_obfuscation_filter_output' );
	}
}

function cotlas_sec_hook_email_obfuscation() {
	add_action( 'template_redirect', 'cotlas_email_obfuscation_start_buffer', 0 );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * BOOT — wire up enabled features
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_security_hardening_boot() {
	// Login Protection.
	if ( get_option( 'cotlas_sec_account_lockout' ) ) {
		cotlas_sec_hook_account_lockout();
	}
	if ( get_option( 'cotlas_sec_user_enumeration' ) ) {
		cotlas_sec_hook_user_enumeration();
	}
	if ( get_option( 'cotlas_sec_login_redirect' ) ) {
		cotlas_sec_hook_login_redirect_hardening();
	}
	if ( get_option( 'cotlas_sec_rightclick_guard' ) ) {
		cotlas_sec_hook_rightclick_guard();
	}
	if ( get_option( 'cotlas_sec_single_session' ) ) {
		cotlas_sec_hook_single_session();
	}
	if ( get_option( 'cotlas_sec_session_timeout' ) ) {
		cotlas_sec_hook_session_timeout();
	}

	// Form Hardening.
	if ( get_option( 'cotlas_sec_autocomplete' ) ) {
		cotlas_sec_hook_autocomplete_hardening();
	}
	if ( get_option( 'cotlas_sec_password_policy' ) ) {
		cotlas_sec_hook_password_policy();
	}

	// Data & Access.
	if ( get_option( 'cotlas_sec_rest_user_block' ) ) {
		cotlas_sec_hook_rest_user_block();
	}
	if ( get_option( 'cotlas_sec_disable_feeds' ) ) {
		cotlas_sec_hook_disable_feeds();
	}
	if ( get_option( 'cotlas_sec_jquery_hardening' ) ) {
		cotlas_sec_hook_jquery_hardening();
	}

	// Network.
	if ( get_option( 'cotlas_sec_cors_hardening' ) ) {
		cotlas_sec_hook_cors_hardening();
	}
	if ( get_option( 'cotlas_sec_email_obfuscation' ) ) {
		cotlas_sec_hook_email_obfuscation();
	}
}
add_action( 'init', 'cotlas_security_hardening_boot', 0 );