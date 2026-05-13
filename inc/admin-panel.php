<?php
/**
 * Unified Cotlas Admin Panel.
 *
 * Registers a single "Cotlas Admin" top-level menu with 11 stylish,
 * responsive tabbed sub-pages covering every plugin module.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════════
 * 0. SAVE HANDLER — must run on admin_init, before any output
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_init', 'cotlas_panel_process_saves' );

function cotlas_panel_process_saves() {
	if ( empty( $_POST['_ctap_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) );

	$maps = array(
		'ctap_save_site' => array(
			'page' => 'cotlas-admin-panel',
			'map'  => array(
				'cotlas_company_name'        => 'sanitize_text_field',
				'cotlas_company_tagline'     => 'sanitize_text_field',
				'cotlas_company_address'     => 'sanitize_textarea_field',
				'cotlas_company_phone'       => 'sanitize_text_field',
				'cotlas_company_email'       => 'sanitize_email',
				'cotlas_company_short_intro' => 'textarea',
				'cotlas_company_whatsapp'    => 'sanitize_text_field',
			),
		),
		'ctap_save_login_settings' => array(
			'page' => 'cotlas-login-system',
			'map'  => array(
				'cotlas_auth_enabled'             => 'checkbox',
				'cotlas_auth_login_slug'          => 'sanitize_text_field',
				'cotlas_auth_register_slug'       => 'sanitize_text_field',
				'cotlas_auth_forgot_slug'         => 'sanitize_text_field',
				'cotlas_auth_redirect_privileged' => 'sanitize_text_field',
				'cotlas_auth_redirect_default'    => 'sanitize_text_field',
				'cotlas_auth_rate_limit'          => 'absint',
			),
		),
		'ctap_save_login_spam' => array(
			'page' => 'cotlas-login-system',
			'map'  => array(
				'cotlas_auth_honeypot'           => 'checkbox',
				'cotlas_auth_turnstile_login'    => 'checkbox',
				'cotlas_auth_turnstile_register' => 'checkbox',
			),
		),
		'ctap_save_categories' => array(
			'page' => 'cotlas-category-enhancements',
			'map'  => array(
				'cotlas_category_features_enabled' => 'checkbox',
			),
		),
		'ctap_save_comments' => array(
			'page' => 'cotlas-comment-system',
			'map'  => array(
				'cotlas_comment_system_enabled' => 'checkbox',
			),
		),
		'ctap_save_gbtags' => array(
			'page' => 'cotlas-gb-tags',
			'map'  => array(
				'cotlas_gb_tags_enabled' => 'checkbox',
			),
		),
		'ctap_save_turnstile' => array(
			'page' => 'cotlas-security-settings',
			'map'  => array(
				'turnstile_site_key'        => 'sanitize_text_field',
				'turnstile_secret_key'      => 'sanitize_text_field',
				'turnstile_enable_login'    => 'checkbox',
				'turnstile_enable_register' => 'checkbox',
				'turnstile_enable_comments' => 'checkbox',
			),
		),
		'ctap_save_honeypot' => array(
			'page' => 'cotlas-security-settings',
			'map'  => array(
				'cotlas_honeypot_wp_login'        => 'checkbox',
				'cotlas_honeypot_wp_register'     => 'checkbox',
				'cotlas_auth_honeypot'            => 'checkbox',
				'cotlas_honeypot_cotlas_comments' => 'checkbox',
			),
		),
		'ctap_save_image_opt' => array(
			'page' => 'cotlas-image-optimization',
			'map'  => array(
				'cotlas_image_optimization_enabled' => 'checkbox',
			),
		),
		'ctap_save_image_conv' => array(
			'page' => 'cotlas-image-optimization',
			'map'  => array(
				'cotlas_imgconv_enabled'           => 'checkbox',
				'cotlas_imgconv_delete_original'   => 'checkbox',
				'cotlas_imgconv_webp_quality'      => 'sanitize_text_field',
				'cotlas_imgconv_avif_quality'      => 'sanitize_text_field',
				'cotlas_imgconv_exclude_patterns'  => 'sanitize_textarea_field',
			),
		),
		'ctap_save_post_formats_settings' => array(
			'page' => 'cotlas-post-formats',
			'map'  => array(
				'cotlas_post_formats_enabled' => 'checkbox',
			),
		),
		'ctap_save_post_formats_taxonomies' => array(
			'page' => 'cotlas-post-formats',
			'map'  => array(
				'cotlas_taxonomy_location_enabled'   => 'checkbox',
				'cotlas_taxonomy_state_city_enabled' => 'checkbox',
			),
		),
		'ctap_save_post_formats_posttype' => array(
			'page' => 'cotlas-post-formats',
			'map'  => array(
				'cotlas_rename_post_to_news' => 'checkbox',
			),
		),
		'ctap_save_social' => array(
			'page' => 'cotlas-social-media',
			'map'  => array(
				'cotlas_social_facebook'  => 'url',
				'cotlas_social_twitter'   => 'url',
				'cotlas_social_youtube'   => 'url',
				'cotlas_social_instagram' => 'url',
				'cotlas_social_linkedin'  => 'url',
				'cotlas_social_threads'   => 'url',
				'cotlas_company_whatsapp' => 'sanitize_text_field',
			),
		),
		'ctap_save_tracking_analytics' => array(
			'page' => 'cotlas-tracking-codes',
			'map'  => array(
				'cotlas_ga4_code'            => 'sanitize_text_field',
				'cotlas_search_console_code' => 'sanitize_textarea_field',
				'cotlas_adsense_code'        => 'sanitize_textarea_field',
			),
		),
		'ctap_save_tracking_scripts' => array(
			'page' => 'cotlas-tracking-codes',
			'map'  => array(
				'cotlas_header_scripts' => 'textarea',
				'cotlas_footer_scripts' => 'textarea',
			),
		),
		'ctap_save_users' => array(
			'page' => 'cotlas-user-settings',
			'map'  => array(
				'cotlas_user_profile_enabled'      => 'checkbox',
				'cotlas_user_avatar_enabled'       => 'checkbox',
				'cotlas_user_social_links_enabled' => 'checkbox',
			),
		),
	);

	foreach ( $maps as $nonce_action => $cfg ) {
		if ( wp_verify_nonce( $nonce, $nonce_action ) ) {
			ctap_save( $cfg['page'], $nonce_action, $cfg['map'] );
			return; // ctap_save() calls exit, but just in case
		}
	}
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 1. MENU REGISTRATION
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'cotlas_panel_register_menus', 5 );

function cotlas_panel_register_menus() {
	$svg = 'data:image/svg+xml;base64,' . base64_encode(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">' .
		'<path fill="black" d="M10 1L1 6.5V13l9 5 9-5V6.5L10 1zm0 2.36L17 7.5l-7 3.86L3 7.5 10 3.36zM3 9.36l7 3.86 7-3.86V12l-7 3.86L3 12V9.36z"/>' .
		'</svg>'
	);

	add_menu_page(
		__( 'Cotlas Admin', 'cotlas-admin' ),
		__( 'Cotlas Admin', 'cotlas-admin' ),
		'manage_options',
		'cotlas-admin-panel',
		'cotlas_panel_page_site_settings',
		$svg,
		58
	);

	$subs = array(
		array( 'cotlas-admin-panel',           __( 'Site Settings', 'cotlas-admin' ),          'cotlas_panel_page_site_settings' ),
		array( 'cotlas-login-system',          __( 'Login System', 'cotlas-admin' ),            'cotlas_panel_page_login'          ),
		array( 'cotlas-category-enhancements', __( 'Category Enhancements', 'cotlas-admin' ),   'cotlas_panel_page_categories'     ),
		array( 'cotlas-comment-system',        __( 'Comment System', 'cotlas-admin' ),           'cotlas_panel_page_comments'       ),
		array( 'cotlas-gb-tags',               __( 'GenerateBlocks Tags', 'cotlas-admin' ),      'cotlas_panel_page_gb_tags'        ),
		array( 'cotlas-security-settings',     __( 'Security Settings', 'cotlas-admin' ),        'cotlas_panel_page_security'       ),
		array( 'cotlas-image-optimization',    __( 'Image Optimization', 'cotlas-admin' ),       'cotlas_panel_page_image_opt'      ),
		array( 'cotlas-post-formats',          __( 'Post Formats', 'cotlas-admin' ),             'cotlas_panel_page_post_formats'   ),
		array( 'cotlas-social-media',          __( 'Social Media', 'cotlas-admin' ),             'cotlas_panel_page_social'         ),
		array( 'cotlas-tracking-codes',        __( 'Tracking Codes', 'cotlas-admin' ),           'cotlas_panel_page_tracking'       ),
		array( 'cotlas-user-settings',         __( 'User Settings', 'cotlas-admin' ),            'cotlas_panel_page_users'          ),
	);

	foreach ( $subs as $s ) {
		add_submenu_page( 'cotlas-admin-panel', $s[1], $s[1], 'manage_options', $s[0], $s[2] );
	}
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 2. ASSETS — enqueue CSS + JS only on panel pages
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_enqueue_scripts', 'cotlas_panel_assets' );

function cotlas_panel_assets( $hook ) {
	$hooks = array(
		'toplevel_page_cotlas-admin-panel',
		'cotlas-admin_page_cotlas-login-system',
		'cotlas-admin_page_cotlas-category-enhancements',
		'cotlas-admin_page_cotlas-comment-system',
		'cotlas-admin_page_cotlas-gb-tags',
		'cotlas-admin_page_cotlas-security-settings',
		'cotlas-admin_page_cotlas-image-optimization',
		'cotlas-admin_page_cotlas-post-formats',
		'cotlas-admin_page_cotlas-social-media',
		'cotlas-admin_page_cotlas-tracking-codes',
		'cotlas-admin_page_cotlas-user-settings',
	);
	if ( ! in_array( $hook, $hooks, true ) ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', cotlas_panel_css() );
	wp_register_script( 'cotlas-panel', false, array(), false, true );
	wp_enqueue_script( 'cotlas-panel' );
	wp_add_inline_script( 'cotlas-panel', cotlas_panel_js() );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 3. CSS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_css() {
	return '
/* ── Cotlas Admin Panel ─────────────────────────────────────────────────── */
.ctap-wrap { max-width: 1200px; margin-top: 16px; }
.ctap-wrap * { box-sizing: border-box; }

/* Header */
.ctap-header {
  background: linear-gradient(135deg, #1a365d 0%, #2271b1 55%, #0ea5e9 100%);
  border-radius: 8px 8px 0 0;
  padding: 18px 28px;
  display: flex; align-items: center; gap: 14px;
}
.ctap-header-icon {
  width: 44px; height: 44px;
  background: rgba(255,255,255,.15);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ctap-header-icon .dashicons { font-size: 24px; color: white; }
.ctap-header-text h1 { margin: 0; padding: 0; color: white; font-size: 20px; font-weight: 700; line-height: 1.2; }
.ctap-header-text p  { margin: 3px 0 0; color: rgba(255,255,255,.78); font-size: 13px; }

/* Body */
.ctap-body {
  display: flex;
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-top: none;
  border-radius: 0 0 8px 8px;
  min-height: 520px;
  overflow: hidden;
}

/* Sidebar nav */
.ctap-nav {
  width: 196px; flex-shrink: 0;
  background: white;
  border-right: 1px solid #dcdcde;
  padding: 10px 0;
}
.ctap-tab-btn {
  display: flex; align-items: center; gap: 8px;
  width: 100%; padding: 10px 16px;
  background: none; border: none;
  border-left: 3px solid transparent;
  cursor: pointer; text-align: left;
  font-size: 13px; color: #50575e;
  text-decoration: none; line-height: 1.4;
  transition: background .12s, color .12s;
}
.ctap-tab-btn:hover { background: #f0f6fc; color: #2271b1; }
.ctap-tab-btn.ctap-active {
  background: #f0f6fc;
  border-left-color: #2271b1;
  color: #2271b1; font-weight: 600;
}
.ctap-tab-btn .dashicons { font-size: 16px; width: 18px; height: 18px; opacity: .7; flex-shrink: 0; }
.ctap-tab-btn.ctap-active .dashicons { opacity: 1; }
.ctap-nav-sep { height: 1px; background: #f0f0f1; margin: 6px 12px; }

/* Content area */
.ctap-content { flex: 1; padding: 24px; overflow-y: auto; min-width: 0; }
.ctap-pane { display: none; }
.ctap-pane.ctap-active { display: block; }

/* Notices */
.ctap-notice {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: 5px;
  margin-bottom: 16px; font-size: 13px;
}
.ctap-notice-success { background: #edfaef; border: 1px solid #6ee7b7; color: #065f46; }
.ctap-notice-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

/* Cards */
.ctap-card {
  background: white; border: 1px solid #dcdcde;
  border-radius: 6px; margin-bottom: 20px;
  overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.ctap-card-head {
  padding: 13px 20px; border-bottom: 1px solid #f0f0f1;
  display: flex; align-items: center; gap: 8px;
}
.ctap-card-head h3 { margin: 0; font-size: 14px; font-weight: 600; color: #1d2327; }
.ctap-card-head .dashicons { font-size: 18px; color: #2271b1; }
.ctap-card-body { padding: 20px; }

/* Module status banner */
.ctap-status-card {
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap; gap: 12px;
  padding: 14px 20px; border-radius: 6px;
  margin-bottom: 20px; border: 1px solid;
}
.ctap-status-card.ctap-enabled  { background: #f0fdf4; border-color: #86efac; }
.ctap-status-card.ctap-disabled { background: #fef2f2; border-color: #fca5a5; }
.ctap-status-label { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; }
.ctap-status-card.ctap-enabled  .ctap-status-label { color: #15803d; }
.ctap-status-card.ctap-disabled .ctap-status-label { color: #b91c1c; }
.ctap-status-label .dashicons { font-size: 20px; }
.ctap-status-desc { font-size: 13px; color: #374151; margin: 4px 0 0; }

/* Toggle switch */
.ctap-toggle-row {
  display: flex; align-items: center;
  justify-content: space-between;
  padding: 12px 0; border-bottom: 1px solid #f0f0f1;
}
.ctap-toggle-row:last-child { border-bottom: none; }
.ctap-toggle-info { flex: 1; padding-right: 20px; }
.ctap-toggle-info strong { display: block; font-size: 13px; color: #1d2327; margin-bottom: 2px; }
.ctap-toggle-info span   { font-size: 12px; color: #646970; }
.ctap-switch { position: relative; display: inline-block; width: 46px; height: 26px; flex-shrink: 0; }
.ctap-switch input { opacity: 0; width: 0; height: 0; }
.ctap-slider {
  position: absolute; inset: 0;
  background: #c3c4c7; border-radius: 13px;
  cursor: pointer; transition: .2s;
}
.ctap-slider::before {
  content: ""; position: absolute;
  width: 20px; height: 20px; left: 3px; top: 3px;
  background: white; border-radius: 50%;
  transition: .2s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
}
.ctap-switch input:checked + .ctap-slider { background: #2271b1; }
.ctap-switch input:checked + .ctap-slider::before { transform: translateX(20px); }
.ctap-switch input:focus-visible + .ctap-slider { box-shadow: 0 0 0 3px rgba(34,113,177,.25); }

/* Form fields */
.ctap-field-row {
  display: grid; grid-template-columns: 200px 1fr;
  gap: 12px; align-items: start;
  padding: 13px 0; border-bottom: 1px solid #f0f0f1;
}
.ctap-field-row:last-child { border-bottom: none; }
.ctap-field-label { font-size: 13px; font-weight: 500; color: #1d2327; padding-top: 8px; }
.ctap-field-input input[type="text"],
.ctap-field-input input[type="url"],
.ctap-field-input input[type="email"],
.ctap-field-input input[type="number"],
.ctap-field-input textarea {
  width: 100%; max-width: 460px;
  padding: 8px 12px;
  border: 1px solid #dcdcde; border-radius: 4px;
  font-size: 13px; color: #1d2327;
  background: white; font-family: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.ctap-field-input input:focus,
.ctap-field-input textarea:focus {
  outline: none; border-color: #2271b1;
  box-shadow: 0 0 0 3px rgba(34,113,177,.12);
}
.ctap-field-input textarea { resize: vertical; min-height: 80px; }
.ctap-field-desc { margin: 5px 0 0; font-size: 12px; color: #646970; }

/* Save button */
.ctap-save-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 20px; background: #2271b1;
  color: white; border: none; border-radius: 4px;
  font-size: 13px; font-weight: 500; cursor: pointer;
  transition: background .15s; margin-top: 6px;
}
.ctap-save-btn:hover { background: #135e96; }
.ctap-save-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }
.ctap-save-btn:disabled { background: #8c8f94; cursor: default; }

/* Info banner */
.ctap-info {
  display: flex; gap: 10px; align-items: flex-start;
  background: #f0f6fc; border: 1px solid #bfdbfe;
  border-radius: 5px; padding: 12px 16px;
  font-size: 13px; color: #1e40af; margin-bottom: 16px;
}
.ctap-info .dashicons { flex-shrink: 0; font-size: 18px; margin-top: 1px; }

/* Reference tables */
.ctap-ref-wrap { overflow-x: auto; }
.ctap-ref-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ctap-ref-table th {
  background: #f6f7f7; padding: 9px 14px;
  text-align: left; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  color: #50575e; border-bottom: 1px solid #dcdcde;
}
.ctap-ref-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
.ctap-ref-table tr:last-child td { border-bottom: none; }
.ctap-ref-table tr:hover td { background: #fafafa; }
code.ctap-code {
  background: #f3f4f6; padding: 2px 7px; border-radius: 3px;
  font-size: 12px; font-family: "SFMono-Regular", Consolas, monospace;
  color: #1d4ed8; white-space: nowrap;
}

/* GB tag cards */
.ctap-tag-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 14px; margin-top: 4px; }
.ctap-tag-card {
  background: white; border: 1px solid #dcdcde; border-radius: 6px;
  padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
  transition: box-shadow .15s;
}
.ctap-tag-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,.1); }
.ctap-tag-title { font-size: 13px; font-weight: 700; color: #2271b1; margin: 0 0 5px; }
.ctap-tag-id {
  display: inline-block; background: #eff6ff; color: #2271b1;
  font-size: 11px; font-family: monospace;
  padding: 2px 8px; border-radius: 10px; margin-bottom: 8px;
}
.ctap-tag-card p { margin: 0 0 6px; font-size: 12px; color: #646970; line-height: 1.5; }
.ctap-tag-card ul { margin: 0; padding-left: 16px; }
.ctap-tag-card li { font-size: 12px; color: #646970; margin-bottom: 3px; }

/* Section subheading */
.ctap-section-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: #50575e;
  margin: 20px 0 10px; padding-bottom: 6px;
  border-bottom: 1px solid #f0f0f1;
}
.ctap-section-title:first-child { margin-top: 0; }

/* Responsive */
@media (max-width: 782px) {
  .ctap-body { flex-direction: column; }
  .ctap-nav {
    width: 100%; border-right: none; border-bottom: 1px solid #dcdcde;
    padding: 6px; display: flex; flex-wrap: wrap; gap: 2px;
  }
  .ctap-tab-btn {
    border-left: none; border-bottom: 3px solid transparent;
    border-radius: 4px 4px 0 0; padding: 8px 12px; font-size: 12px; width: auto;
  }
  .ctap-tab-btn.ctap-active { border-left: none; border-bottom-color: #2271b1; }
  .ctap-field-row { grid-template-columns: 1fr; gap: 4px; }
  .ctap-field-label { padding-top: 0; }
  .ctap-content { padding: 16px; }
}
';
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 4. JS — tab switching, URL hash, form feedback
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_js() {
	return '
document.addEventListener("DOMContentLoaded", function() {
  var btns  = document.querySelectorAll(".ctap-tab-btn");
  var panes = document.querySelectorAll(".ctap-pane");
  if (!btns.length) return;

  function activate(id) {
    btns.forEach(function(b) {
      b.classList.toggle("ctap-active", b.getAttribute("data-tab") === id);
    });
    panes.forEach(function(p) {
      p.classList.toggle("ctap-active", p.id === "ctap-pane-" + id);
    });
    try {
      var url = window.location.pathname + window.location.search.replace(/[?&]tab=[^&]*/g, "") + "#" + id;
      history.replaceState(null, "", url);
    } catch(e) {}
  }

  var hash  = window.location.hash.replace("#", "");
  var first = btns[0].getAttribute("data-tab");
  activate(hash && document.getElementById("ctap-pane-" + hash) ? hash : first);

  btns.forEach(function(b) {
    b.addEventListener("click", function() { activate(this.getAttribute("data-tab")); });
  });

  document.querySelectorAll("form.ctap-form").forEach(function(f) {
    f.addEventListener("submit", function() {
      var btn = f.querySelector(".ctap-save-btn");
      if (btn) { btn.textContent = "Saving\u2026"; btn.disabled = true; }
    });
  });
});
';
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 5. SHARED HELPERS
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Process a POST form save. Returns true if saved, false otherwise.
 * $map: [ 'option_key' => 'sanitize_callback_or_type' ]
 * Special types: 'checkbox', 'textarea' (wp_kses_post), 'url' (esc_url_raw)
 */
function ctap_save( $page_slug, $nonce_action, array $map ) {
	if ( empty( $_POST['_ctap_nonce'] ) ) {
		return false;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ctap_nonce'] ) ), $nonce_action ) ) {
		wp_die( esc_html__( 'Security check failed.', 'cotlas-admin' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}
	foreach ( $map as $key => $type ) {
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		switch ( $type ) {
			case 'checkbox':
				$val = isset( $_POST[ $key ] ) ? 1 : 0;
				// update_option() skips writing when the new value equals the registered
				// default and no row exists yet in the DB. add_option() ensures the row
				// is created so subsequent saves work correctly.
				if ( ! update_option( $key, $val ) ) {
					add_option( $key, $val );
				}
				break;
			case 'textarea':
				update_option( $key, wp_kses_post( $raw ) );
				break;
			case 'url':
				update_option( $key, esc_url_raw( $raw ) );
				break;
			default:
				if ( is_callable( $type ) ) {
					update_option( $key, call_user_func( $type, $raw ) );
				} else {
					update_option( $key, sanitize_text_field( $raw ) );
				}
		}
	}
	$tab = isset( $_POST['_ctap_tab'] ) ? '#' . sanitize_key( wp_unslash( $_POST['_ctap_tab'] ) ) : '';
	wp_safe_redirect( admin_url( 'admin.php?page=' . $page_slug . '&saved=1' . $tab ) );
	exit;
}

/** Open the page wrapper + gradient header. */
function ctap_page_open( $title, $dashicon, $desc ) {
	echo '<div class="wrap ctap-wrap">';
	echo '<div class="ctap-header">';
	echo '<div class="ctap-header-icon"><span class="dashicons ' . esc_attr( $dashicon ) . '"></span></div>';
	echo '<div class="ctap-header-text"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $desc ) . '</p></div>';
	echo '</div>';
	if ( ! empty( $_GET['saved'] ) ) {
		echo '<div class="ctap-notice ctap-notice-success"><span class="dashicons dashicons-yes-alt"></span> Settings saved.</div>';
	}
	echo '<div class="ctap-body">';
}

/** Output sidebar tab buttons. Returns the active tab ID. */
function ctap_nav( array $tabs ) {
	$req    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$ids    = array_column( $tabs, 'id' );
	$active = in_array( $req, $ids, true ) ? $req : $ids[0];

	echo '<nav class="ctap-nav">';
	foreach ( $tabs as $t ) {
		$cls = $t['id'] === $active ? ' ctap-active' : '';
		printf(
			'<button type="button" class="ctap-tab-btn%s" data-tab="%s"><span class="dashicons %s"></span>%s</button>',
			esc_attr( $cls ),
			esc_attr( $t['id'] ),
			esc_attr( $t['icon'] ),
			esc_html( $t['label'] )
		);
	}
	echo '</nav><div class="ctap-content">';
	return $active;
}

/** Open a tab pane; $active is from ctap_nav(). */
function ctap_pane_open( $id, $active ) {
	$cls = $id === $active ? ' ctap-active' : '';
	echo '<div class="ctap-pane' . esc_attr( $cls ) . '" id="ctap-pane-' . esc_attr( $id ) . '">';
}

/** Close a tab pane. */
function ctap_pane_close() {
	echo '</div>';
}

/** Close content area + body + wrap. */
function ctap_page_close() {
	echo '</div></div></div>';
}

/** Open a card with heading. */
function ctap_card_open( $title, $dashicon = '' ) {
	echo '<div class="ctap-card"><div class="ctap-card-head">';
	if ( $dashicon ) {
		echo '<span class="dashicons ' . esc_attr( $dashicon ) . '"></span>';
	}
	echo '<h3>' . esc_html( $title ) . '</h3></div><div class="ctap-card-body">';
}

/** Close a card. */
function ctap_card_close() {
	echo '</div></div>';
}

/** Open a form with nonce + hidden tab field. */
function ctap_form_open( $nonce_action, $tab_id = '' ) {
	echo '<form method="post" class="ctap-form">';
	echo '<input type="hidden" name="_ctap_nonce" value="' . esc_attr( wp_create_nonce( $nonce_action ) ) . '">';
	echo '<input type="hidden" name="_ctap_tab" value="' . esc_attr( $tab_id ) . '">';
}

/** Save button + close form. */
function ctap_form_close( $label = 'Save Changes' ) {
	echo '<button type="submit" class="ctap-save-btn"><span class="dashicons dashicons-saved"></span>' . esc_html( $label ) . '</button>';
	echo '</form>';
}

/** Render a field row: label + right-side input area. */
function ctap_field( $label, $input_html, $desc = '' ) {
	echo '<div class="ctap-field-row">';
	echo '<div class="ctap-field-label">' . esc_html( $label ) . '</div>';
	echo '<div class="ctap-field-input">' . $input_html;
	if ( $desc ) {
		echo '<p class="ctap-field-desc">' . wp_kses_post( $desc ) . '</p>';
	}
	echo '</div></div>';
}

/** Return a text/email/number input HTML string. */
function ctap_input( $name, $placeholder = '', $type = 'text' ) {
	return sprintf(
		'<input type="%s" name="%s" value="%s" placeholder="%s">',
		esc_attr( $type ),
		esc_attr( $name ),
		esc_attr( get_option( $name, '' ) ),
		esc_attr( $placeholder )
	);
}

/** Return a URL input HTML string. */
function ctap_url( $name, $placeholder = '' ) {
	return ctap_input( $name, $placeholder, 'url' );
}

/** Return a textarea HTML string. */
function ctap_textarea( $name, $placeholder = '', $rows = 4 ) {
	return sprintf(
		'<textarea name="%s" placeholder="%s" rows="%d">%s</textarea>',
		esc_attr( $name ),
		esc_attr( $placeholder ),
		intval( $rows ),
		esc_textarea( get_option( $name, '' ) )
	);
}

/** Render a toggle row (checkbox-as-switch). */
function ctap_toggle( $name, $label, $desc = '', $default = 1 ) {
	$checked = get_option( $name, $default ) ? 'checked' : '';
	echo '<div class="ctap-toggle-row">';
	echo '<div class="ctap-toggle-info"><strong>' . esc_html( $label ) . '</strong>';
	if ( $desc ) {
		echo '<span>' . esc_html( $desc ) . '</span>';
	}
	echo '</div>';
	echo '<label class="ctap-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . $checked . '><span class="ctap-slider"></span></label>';
	echo '</div>';
}

/** Module enable/disable status card (also acts as a toggle in a form). */
function ctap_module_status( $option_key, $feature_label, $desc, $default = 1 ) {
	$on    = (bool) get_option( $option_key, $default );
	$class = $on ? 'ctap-enabled' : 'ctap-disabled';
	$icon  = $on ? 'dashicons-yes-alt' : 'dashicons-dismiss';
	$badge = $on ? 'Active' : 'Inactive';
	echo '<div class="ctap-status-card ' . esc_attr( $class ) . '">';
	echo '<div><div class="ctap-status-label"><span class="dashicons ' . esc_attr( $icon ) . '"></span>' . esc_html( $badge ) . '</div>';
	echo '<p class="ctap-status-desc">' . esc_html( $desc ) . '</p></div>';
	$checked = $on ? 'checked' : '';
	echo '<label class="ctap-switch" title="Toggle ' . esc_attr( $feature_label ) . '"><input type="checkbox" name="' . esc_attr( $option_key ) . '" value="1" ' . $checked . '><span class="ctap-slider"></span></label>';
	echo '</div>';
}

/** Info banner. */
function ctap_info( $html ) {
	echo '<div class="ctap-info"><span class="dashicons dashicons-info-outline"></span><div>' . wp_kses_post( $html ) . '</div></div>';
}

/** Section sub-heading. */
function ctap_section( $label ) {
	echo '<p class="ctap-section-title">' . esc_html( $label ) . '</p>';
}

/**
 * Render a shortcode reference table.
 * $rows: array of [ tag_slug, description, attributes_html (optional) ]
 */
function ctap_ref_table( array $rows, $show_attrs = true ) {
	echo '<div class="ctap-ref-wrap"><table class="ctap-ref-table"><thead><tr>';
	echo '<th style="width:220px">Shortcode</th><th>Description</th>';
	if ( $show_attrs ) echo '<th>Key Attributes</th>';
	echo '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr>';
		echo '<td><code class="ctap-code">[' . esc_html( $r[0] ) . ']</code></td>';
		echo '<td>' . esc_html( $r[1] ) . '</td>';
		if ( $show_attrs ) {
			echo '<td>' . ( isset( $r[2] ) ? wp_kses_post( $r[2] ) : '<em style="color:#999">none</em>' ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}


/* ═══════════════════════════════════════════════════════════════════════════
 * 6. PAGE: SITE SETTINGS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_site_settings() {
	ctap_page_open( 'Site Settings', 'dashicons-admin-settings', 'Company information used across shortcodes and GenerateBlocks dynamic tags.' );
	$tabs = array(
		array( 'id' => 'info',  'label' => 'Company Info', 'icon' => 'dashicons-building' ),
		array( 'id' => 'codes', 'label' => 'Shortcodes',   'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'info', $active );
	ctap_form_open( 'ctap_save_site', 'info' );
	ctap_card_open( 'Company Information', 'dashicons-building' );
	ctap_field( 'Company Name',    ctap_input( 'cotlas_company_name' ) );
	ctap_field( 'Tagline',         ctap_input( 'cotlas_company_tagline' ) );
	ctap_field( 'Address',         ctap_textarea( 'cotlas_company_address', '', 3 ) );
	ctap_field( 'Phone',           ctap_input( 'cotlas_company_phone' ) );
	ctap_field( 'Email',           ctap_input( 'cotlas_company_email', '', 'email' ) );
	ctap_field( 'Short Intro',     ctap_textarea( 'cotlas_company_short_intro', '', 3 ), 'HTML allowed (links, strong, em).' );
	ctap_field( 'WhatsApp Number', ctap_input( 'cotlas_company_whatsapp' ), 'International format without + or spaces, e.g. <code>15551234567</code>.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Available Shortcodes', 'dashicons-shortcode' );
	ctap_info( 'These shortcodes output the values saved above. No attributes — they simply return the saved value. Also available as <strong>GenerateBlocks Dynamic Tags</strong> (company_info tag).' );
	ctap_ref_table( array(
		array( 'company_name',        'Company / site name' ),
		array( 'company_tagline',     'Company tagline or slogan' ),
		array( 'company_address',     'Address (line breaks preserved)' ),
		array( 'company_phone',       'Phone number' ),
		array( 'company_email',       'Email address' ),
		array( 'company_short_intro', 'Short intro paragraph — HTML output' ),
		array( 'company_whatsapp',    'WhatsApp number' ),
	), false );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 7. PAGE: LOGIN SYSTEM
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_login() {
	ctap_page_open( 'Login System', 'dashicons-lock', 'Frontend login, register and forgot-password system using shortcodes.' );
	$tabs = array(
		array( 'id' => 'settings', 'label' => 'Settings',       'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'spam',     'label' => 'Spam Protection', 'icon' => 'dashicons-shield' ),
		array( 'id' => 'codes',    'label' => 'Shortcodes',      'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_login_settings', 'settings' );
	ctap_card_open( 'General Settings', 'dashicons-admin-generic' );
	ctap_module_status( 'cotlas_auth_enabled', 'Login System', 'When enabled, unauthenticated visits to /wp-admin/ and wp-login.php redirect to the custom login page.' );
	ctap_section( 'Page Slugs' );
	ctap_field( 'Login Page Slug',           ctap_input( 'cotlas_auth_login_slug', 'login' ), 'Create a WordPress page with this slug and add <code>[cotlas_login]</code>.' );
	ctap_field( 'Register Page Slug',        ctap_input( 'cotlas_auth_register_slug', 'register' ), 'Create a WordPress page with this slug and add <code>[cotlas_register]</code>.' );
	ctap_field( 'Forgot Password Page Slug', ctap_input( 'cotlas_auth_forgot_slug', 'reset-password' ), 'Create a WordPress page with this slug and add <code>[cotlas_forgot_password]</code>.' );
	ctap_section( 'Redirects After Login' );
	ctap_field( 'Privileged Roles (Admin/Editor/Author)', ctap_input( 'cotlas_auth_redirect_privileged', '/wp-admin/' ), 'Relative path or full URL, e.g. <code>/wp-admin/</code> or <code>https://example.com/dashboard/</code>.' );
	ctap_field( 'Default Roles (Subscriber etc.)',        ctap_input( 'cotlas_auth_redirect_default',    '/' ),          'Relative path or full URL, e.g. <code>/</code> or <code>/my-account/</code>.' );
	ctap_section( 'Rate Limiting' );
	ctap_field( 'Max Login Attempts', ctap_input( 'cotlas_auth_rate_limit', '5', 'number' ), 'Failed attempts before a 15-minute lockout. Default: 5.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'spam', $active );
	ctap_form_open( 'ctap_save_login_spam', 'spam' );
	ctap_card_open( 'Spam Protection', 'dashicons-shield' );
	ctap_toggle( 'cotlas_auth_honeypot', 'Honeypot on Custom Auth Forms', 'Adds a hidden field to login, register, and forgot-password forms. Bots that fill it are silently blocked.' );
	if ( get_option( 'turnstile_site_key' ) ) {
		ctap_toggle( 'cotlas_auth_turnstile_login',    'Cloudflare Turnstile on Login Form' );
		ctap_toggle( 'cotlas_auth_turnstile_register', 'Cloudflare Turnstile on Register Form' );
	} else {
		ctap_info( 'Cloudflare Turnstile keys are not configured yet. Set them in <a href="' . esc_url( admin_url( 'admin.php?page=cotlas-security-settings' ) ) . '">Security Settings</a> to enable CAPTCHA protection.' );
	}
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Login System Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'cotlas_login',           'Frontend login form',
			'<code>redirect="/dashboard/"</code> — post-login URL<br><code>class="my-class"</code> — extra CSS class' ),
		array( 'cotlas_register',        'Frontend registration form',
			'<code>redirect="/dashboard/"</code> — post-register URL<br><code>class="my-class"</code> — extra CSS class' ),
		array( 'cotlas_forgot_password', 'Forgot / reset password form',
			'<code>class="my-class"</code> — extra CSS class' ),
		array( 'cotlas_auth_panel',      'Combined login + register + forgot — for modal popups. Links switch panels in-place, no page redirect.',
			'<code>panel="login"</code> — open on login (default)<br><code>panel="register"</code> — open on register<br><code>panel="forgot"</code> — open on forgot password<br><code>redirect="/dashboard/"</code> — post-login URL<br><code>class="my-class"</code> — extra CSS class' ),
		array( 'cotlas_logout_link',     'Logout link — only shown when logged in',
			'<em style="color:#999">none</em>' ),
		array( 'cotlas_login_link',      'Login link — only shown when logged out',
			'<em style="color:#999">none</em>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 8. PAGE: CATEGORY ENHANCEMENTS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_categories() {
	ctap_page_open( 'Category Enhancements', 'dashicons-category', 'Focused/Highlighted category flags, featured image upload, and shortcodes.' );
	$tabs = array(
		array( 'id' => 'settings', 'label' => 'Settings',  'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'codes',    'label' => 'Shortcodes', 'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_categories', 'settings' );
	ctap_card_open( 'Category Features', 'dashicons-category' );
	ctap_module_status( 'cotlas_category_features_enabled', 'Category Enhancements', 'Adds Focused/Highlighted toggles to category terms, category featured image upload, and the [focused_categories] scrollable bar.' );
	ctap_card_close();
	ctap_card_open( 'How It Works', 'dashicons-info-outline' );
	ctap_info( 'Go to <strong>Posts → Categories</strong>, edit any category, and you will find:<ul style="margin:.5em 0 0 1.4em"><li><strong>Focused</strong> — marks the category for inclusion in the <code>[focused_categories]</code> scrollable bar.</li><li><strong>Highlighted</strong> — pins that category as the first coloured pill in the bar.</li><li><strong>Category Image</strong> — upload a featured image used by the <code>term_image</code> GB dynamic tag.</li></ul>' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'focused_categories', 'Horizontal scrollable pill bar of Focused categories. Highlighted category appears first with a coloured pill.',
			'<code>label="Breaking"</code> — pill text prefix<br><code>highlight="news"</code> — category slug to pin first<br><code>orderby="name"</code> <code>orderby="count"</code> <code>orderby="id"</code><br><code>order="ASC"</code> <code>order="DESC"</code><br><code>class="my-class"</code>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 9. PAGE: COMMENT SYSTEM
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_comments() {
	ctap_page_open( 'Comment System', 'dashicons-admin-comments', 'Styled threaded comments with inline editing and AJAX reply.' );
	$tabs = array(
		array( 'id' => 'settings', 'label' => 'Settings',  'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'codes',    'label' => 'Shortcodes', 'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_comments', 'settings' );
	ctap_card_open( 'Comment System', 'dashicons-admin-comments' );
	ctap_module_status( 'cotlas_comment_system_enabled', 'Comment System', 'Provides a Facebook-style threaded comment section, inline edit, coloured initials avatars, and AJAX replies.' );
	ctap_card_close();
	ctap_card_open( 'Features', 'dashicons-info-outline' );
	ctap_info( '<ul style="margin:.5em 0 0 1.4em"><li>Threaded replies up to any depth</li><li>Coloured-initials avatar (Gravatar fallback)</li><li>Inline comment editing for post author and commenter (via cookie)</li><li>Reply form cloned inline — no page reload</li><li>Spam protection: guests must pass honeypot / Turnstile if enabled in Security Settings</li></ul>' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'cotlas_comments', 'Styled threaded comments section. Use inside single post templates.',
			'<code>post_id="123"</code> — specific post ID (defaults to current post)<br><code>title="Comments"</code> — heading above the list' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 10. PAGE: GENERATEBLOCKS TAGS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_gb_tags() {
	ctap_page_open( 'GenerateBlocks Tags', 'dashicons-block-default', 'Custom dynamic tags and query parameters for GenerateBlocks Pro.' );
	$tabs = array(
		array( 'id' => 'tags',       'label' => 'Dynamic Tags', 'icon' => 'dashicons-tag' ),
		array( 'id' => 'query',      'label' => 'Query Params',  'icon' => 'dashicons-database-view' ),
		array( 'id' => 'shortcodes', 'label' => 'Shortcodes',    'icon' => 'dashicons-shortcode' ),
		array( 'id' => 'settings',   'label' => 'Settings',      'icon' => 'dashicons-admin-generic' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'tags', $active );
	ctap_card_open( 'Registered Dynamic Tags', 'dashicons-tag' );
	ctap_info( 'These tags appear in the <strong>Dynamic Content</strong> and <strong>Dynamic Link</strong> dropdowns inside GenerateBlocks. No manual key entry needed.' );
	$tag_cards = array(
		array( 'Human Date',          'human_date',       'post',   'Relative date ("3 hours ago"). Falls back to formatted date after 24 h.',             array( 'type: published (default) | modified', 'source: current or specific post' ) ),
		array( 'Post Views',          'post_views',       'post',   'Raw view count for a post. Requires Post Views Counter plugin.',                       array( 'source: current post or pick a specific one' ) ),
		array( 'Primary Category',    'primary_category', 'post',   'Yoast SEO primary category name. Falls back to first category.',                       array( 'Dynamic Link → Term links to category archive' ) ),
		array( 'Term Display',        'term_display',     'term',   'Any field from a category: name, desc, image URL, count, or archive URL.',             array( 'key: term_title | term_desc | term_image | term_count | term_url', 'id: explicit term ID (auto-resolved when omitted)', 'tax: taxonomy slug (default: category)' ) ),
		array( 'Term Image',          'term_image',       'term',   'Featured image of a category (set via Categories screen).',                            array( 'key: url (default) | id | alt', 'size: any registered image size (default: full)', 'id: explicit term ID' ) ),
		array( 'Company Info',        'company_info',     'option', 'Any company detail saved in Site Settings.',                                           array( 'Options: name, tagline, address, phone, email, whatsapp, short_intro' ) ),
		array( 'Company Social URL',  'company_social',   'option', 'A social media URL from Site Settings. Use as Dynamic Link on button/image.',          array( 'Options: facebook, twitter, youtube, instagram, linkedin, threads' ) ),
		array( 'Featured Post Query', 'featuredPosts',    'query',  'Filter GenerateBlocks Query Loop by Featured Post flag (_is_featured meta).',          array( 'only — show only featured', 'exclude — hide featured' ) ),
		array( 'Popular Posts Query', 'popularPosts',     'query',  'Sort a GB Query Loop by view count (most viewed first). Requires Post Views Counter.', array( '1 — enable sorting by views' ) ),
	);
	echo '<div class="ctap-tag-grid">';
	foreach ( $tag_cards as $card ) {
		echo '<div class="ctap-tag-card">';
		echo '<p class="ctap-tag-title">' . esc_html( $card[0] ) . '</p>';
		echo '<span class="ctap-tag-id">{{' . esc_html( $card[1] ) . '}}</span> ';
		echo '<span style="font-size:11px;background:#f3f4f6;padding:2px 7px;border-radius:10px;color:#50575e;">' . esc_html( $card[2] ) . '</span>';
		echo '<p>' . esc_html( $card[3] ) . '</p>';
		echo '<ul>';
		foreach ( $card[4] as $opt ) {
			echo '<li>' . esc_html( $opt ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '</div>';
	ctap_card_close();
	ctap_pane_close();

	ctap_pane_open( 'query', $active );
	ctap_card_open( 'GB Query Loop Parameters', 'dashicons-database-view' );
	ctap_info( 'Add these in the <strong>Query Parameters</strong> panel of a GenerateBlocks Query Loop block in the editor.' );
	ctap_ref_table( array(
		array( 'featuredPosts', 'Filter by Featured Post flag (_is_featured meta).', '<code>only</code> — show only featured<br><code>exclude</code> — hide featured posts' ),
		array( 'popularPosts',  'Sort by view count descending (Post Views Counter required).', '<code>1</code> — enable sorting' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_pane_open( 'shortcodes', $active );
	ctap_card_open( 'All Plugin Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'gp_nav',                 'Renders the GeneratePress navigation menu.',
			'<em style="color:#999">none</em>' ),
		array( 'first_category',         'First category of the current post as a linked badge with icon.',
			'<em style="color:#999">none</em>' ),
		array( 'yoast_primary_category', 'Yoast primary category (falls back to first category).',
			'<code>show_link="1"</code> — wrap in anchor<br><code>class="my-class"</code><br><code>text_class="my-class"</code><br><code>fallback="Uncategorised"</code> — text when no category' ),
		array( 'human_date',             'Relative date string ("3 hours ago").',
			'<code>type="published"</code> <code>type="modified"</code><br><code>id="123"</code> — specific post ID (defaults to current)' ),
		array( 'post_marquee',           'CSS-animated scrolling headline ticker.',
			'<code>count="10"</code> — number of posts<br><code>category="slug"</code> — filter by category<br><code>speed="40"</code> — scroll speed (px/s)' ),
		array( 'trending_categories',    'Most popular categories by post count. Cached 1 h.',
			'<code>count="5"</code> — number to show<br><code>label="Trending"</code> — heading text' ),
		array( 'most_read',              'Most-viewed posts (Post Views Counter required). Cached 1 h.',
			'<code>count="5"</code> — number of posts' ),
		array( 'category_info',          'Output a category field (name/description/link).',
			'<code>id="5"</code> — term ID<br><code>slug="news"</code> — term slug<br><code>field="name"</code> <code>field="description"</code> <code>field="link"</code><br><code>link="1"</code> — wrap in anchor' ),
		array( 'cotlas_search',          'Accessible styled search form.',
			'<code>placeholder="Search…"</code><br><code>button_label="Go"</code><br><code>post_types="post,page"</code> — comma-separated post types' ),
		array( 'local_datetime',         "Visitor's local date/time updating live.",
			'<code>class="my-class"</code><br><code>date_format="DD/MM/YYYY"</code><br><code>time_format="HH:mm"</code>' ),
		array( 'audio_player',           'HTML5 audio player for Audio-format posts.',
			'<code>style="modern"</code> <code>style="minimal"</code> <code>style="dark"</code><br><code>width="100%"</code> <code>height="54px"</code><br><code>autoplay="0"</code> or <code>autoplay="1"</code><br><code>loop="0"</code> or <code>loop="1"</code>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_gbtags', 'settings' );
	ctap_card_open( 'Module Settings', 'dashicons-admin-generic' );
	ctap_module_status( 'cotlas_gb_tags_enabled', 'GenerateBlocks Tags', 'When disabled, no custom dynamic tags will be registered with GenerateBlocks. All tags will disappear from the GB dropdowns.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 11. PAGE: SECURITY SETTINGS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_security() {
	ctap_page_open( 'Security Settings', 'dashicons-shield', 'Cloudflare Turnstile CAPTCHA and honeypot spam protection per form.' );
	$tabs = array(
		array( 'id' => 'turnstile', 'label' => 'Turnstile', 'icon' => 'dashicons-shield-alt' ),
		array( 'id' => 'honeypot',  'label' => 'Honeypot',  'icon' => 'dashicons-hidden' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'turnstile', $active );
	ctap_form_open( 'ctap_save_turnstile', 'turnstile' );
	ctap_card_open( 'Cloudflare Turnstile', 'dashicons-shield-alt' );
	ctap_info( 'Get your keys from <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare Dashboard &rarr; Turnstile</a>. Choose <strong>Managed</strong> or <strong>Non-Interactive</strong> mode for the best user experience.' );
	ctap_section( 'API Keys' );
	ctap_field( 'Site Key',   ctap_input( 'turnstile_site_key',   'Paste your Turnstile site key' ) );
	ctap_field( 'Secret Key', ctap_input( 'turnstile_secret_key', 'Paste your Turnstile secret key' ) );
	ctap_section( 'Enable on Forms' );
	ctap_toggle( 'turnstile_enable_login',    'WP Default Login Form',        'Adds Turnstile to the standard wp-login.php login form.' );
	ctap_toggle( 'turnstile_enable_register', 'WP Default Registration Form', 'Adds Turnstile to the standard wp-login.php registration form.' );
	ctap_toggle( 'turnstile_enable_comments', 'WP Default Comment Form',      'Adds Turnstile to the standard WordPress comment form (guest users only).' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'honeypot', $active );
	ctap_form_open( 'ctap_save_honeypot', 'honeypot' );
	ctap_card_open( 'Honeypot Protection', 'dashicons-hidden' );
	ctap_info( 'Honeypots add an invisible field that bots fill in but humans never see. Submission is silently rejected if the field is filled. Zero UX impact.' );
	ctap_section( 'WordPress Default Forms' );
	ctap_toggle( 'cotlas_honeypot_wp_login',    'WP Login Form',    'Adds a hidden field to wp-login.php.', 1 );
	ctap_toggle( 'cotlas_honeypot_wp_register', 'WP Register Form', 'Adds a hidden field to the default WP registration form.', 1 );
	ctap_section( 'Custom Auth Forms' );
	ctap_toggle( 'cotlas_auth_honeypot', 'Custom Login & Register Forms', 'Honeypot on the [cotlas_login] and [cotlas_register] shortcode forms.', 1 );
	ctap_section( 'Comment Form' );
	ctap_toggle( 'cotlas_honeypot_cotlas_comments', 'Cotlas Comment Form', 'Honeypot on the [cotlas_comments] comment submission form (guest users only).', 1 );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 12. PAGE: IMAGE OPTIMIZATION
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_image_opt() {
	ctap_page_open( 'Image Optimization', 'dashicons-format-image', 'Custom image sizes, srcset pruning, aspect-ratio helpers, LCP preloads, and WebP/AVIF format conversion.' );
	$tabs = array(
		array( 'id' => 'settings',   'label' => 'Settings',          'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'conversion', 'label' => 'Image Conversion',   'icon' => 'dashicons-images-alt2' ),
	);
	$active = ctap_nav( $tabs );

	/* ── Settings tab ──────────────────────────────────────── */
	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_image_opt', 'settings' );
	ctap_card_open( 'Image Optimization', 'dashicons-format-image' );
	ctap_module_status( 'cotlas_image_optimization_enabled', 'Image Optimization', 'When enabled: custom aspect-ratio image sizes, srcset pruning, WP default sizes disabled, LCP preloads, AVIF srcset preloads, and format conversion.' );
	ctap_card_close();
	ctap_card_open( 'What This Module Does', 'dashicons-info-outline' );
	ctap_info( '<ul style="margin:.5em 0 0 1.4em"><li><strong>Custom image sizes</strong> — registers aspect-ratio-aware sizes (16:9) and disables WordPress default sizes to prevent disk bloat.</li><li><strong>srcset pruning</strong> — limits srcset to the allowed widths [150, 300, 640, 768, 1024, 1200] to keep HTML lean.</li><li><strong>LCP optimisation</strong> — disables lazy-load on the first image, injects critical CSS for the hero section, and preloads the featured image as an AVIF srcset.</li><li><strong>Image conversion</strong> — converts JPEG/PNG uploads to WebP and/or AVIF and serves the best format to each browser. Configure in the <em>Image Conversion</em> tab.</li></ul>' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	/* ── Image Conversion tab ──────────────────────────────── */
	ctap_pane_open( 'conversion', $active );
	ctap_form_open( 'ctap_save_image_conv', 'conversion' );

	// Server support status card (read-only).
	$avif_ok = function_exists( 'cimg_server_supports_avif' ) && cimg_server_supports_avif();
	$webp_ok = function_exists( 'cimg_server_supports_webp' ) && cimg_server_supports_webp();
	$mk_badge = function( $label, $ok ) {
		$color  = $ok ? '#166534' : '#991b1b';
		$bg     = $ok ? '#f0fdf4' : '#fef2f2';
		$border = $ok ? '#86efac' : '#fca5a5';
		$icon   = $ok ? 'dashicons-yes-alt' : 'dashicons-dismiss';
		$text   = $ok ? $label . ' Supported' : $label . ' Not Available';
		return '<span style="display:inline-flex;align-items:center;gap:4px;background:' . $bg . ';border:1px solid ' . $border . ';color:' . $color . ';padding:5px 12px;border-radius:4px;font-size:12px;font-weight:600;"><span class="dashicons ' . $icon . '" style="font-size:15px;color:' . $color . '"></span>' . esc_html( $text ) . '</span>';
	};
	ctap_card_open( 'Server Support', 'dashicons-performance' );
	echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">';
	echo $mk_badge( 'AVIF', $avif_ok ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $mk_badge( 'WebP', $webp_ok ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	ctap_info( 'Detected from your server&rsquo;s Imagick/GD capabilities. AVIF requires Imagick with AVIF support or GD compiled with libavif. WebP requires Imagick with WebP support or GD with libwebp. If a format shows &ldquo;Not Available&rdquo; its conversion toggle will have no effect.' );
	ctap_card_close();

	// Conversion toggles card.
	ctap_card_open( 'Smart Conversion', 'dashicons-images-alt2' );
	ctap_toggle( 'cotlas_imgconv_enabled', 'Enable Smart Image Conversion', 'Automatically picks the best single format per image on upload. JPEG &rarr; WebP. Small PNG (&lt;300 KB) &rarr; WebP. Large PNG (&ge;300 KB) &rarr; AVIF (falls back to WebP if AVIF is unavailable on this server).', 0 );
	ctap_toggle( 'cotlas_imgconv_delete_original', 'Delete original file after conversion', 'Remove the original JPEG/PNG after a successful conversion to save disk space. <strong>This is irreversible.</strong> Leave off if you need to regenerate thumbnails later.', 0 );
	ctap_card_close();

	// How it works card.
	ctap_card_open( 'How Smart Conversion Works', 'dashicons-info-outline' );
	echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
	echo '<thead><tr>';
	echo '<th style="text-align:left;padding:8px 12px;background:#f6f7f7;border-bottom:1px solid #dcdcde;color:#50575e;font-size:11px;text-transform:uppercase;letter-spacing:.05em">Source</th>';
	echo '<th style="text-align:left;padding:8px 12px;background:#f6f7f7;border-bottom:1px solid #dcdcde;color:#50575e;font-size:11px;text-transform:uppercase;letter-spacing:.05em">Output format</th>';
	echo '<th style="text-align:left;padding:8px 12px;background:#f6f7f7;border-bottom:1px solid #dcdcde;color:#50575e;font-size:11px;text-transform:uppercase;letter-spacing:.05em">Why</th>';
	echo '</tr></thead><tbody>';
	$rows = array(
		array( 'JPEG / JPG',          'WebP',            'JPEG already compresses photos well. WebP encoding is fast and saves ~30% with no visible loss.' ),
		array( 'PNG &lt; 300 KB',      'WebP',            'Small graphics (logos, icons, UI) compress efficiently as WebP without the heavier AVIF encoder.' ),
		array( 'PNG &ge; 300 KB',      'AVIF',            'Large screenshots and illustrations gain ~50% savings with AVIF. Falls back to WebP if your server lacks AVIF support.' ),
	);
	foreach ( $rows as $i => $r ) {
		$bg = $i % 2 ? '#fafafa' : '#fff';
		echo '<tr style="background:' . $bg . '">';
		echo '<td style="padding:9px 12px;border-bottom:1px solid #f0f0f1;font-weight:600;color:#1d2327">' . $r[0] . '</td>';
		echo '<td style="padding:9px 12px;border-bottom:1px solid #f0f0f1"><code style="background:#eff6ff;color:#2271b1;padding:2px 7px;border-radius:3px;font-size:12px">' . $r[1] . '</code></td>';
		echo '<td style="padding:9px 12px;border-bottom:1px solid #f0f0f1;color:#50575e">' . $r[2] . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	ctap_card_close();

	// Quality card.
	ctap_card_open( 'Output Quality', 'dashicons-admin-appearance' );
	ctap_field(
		'WebP quality (1–100)',
		'<input type="number" name="cotlas_imgconv_webp_quality" value="' . esc_attr( get_option( 'cotlas_imgconv_webp_quality', 75 ) ) . '" min="1" max="100" style="width:80px">',
		'Default: 75. Lower values = smaller files, slightly less detail.'
	);
	ctap_field(
		'AVIF quality (1–100)',
		'<input type="number" name="cotlas_imgconv_avif_quality" value="' . esc_attr( get_option( 'cotlas_imgconv_avif_quality', 50 ) ) . '" min="1" max="100" style="width:80px">',
		'Default: 50. AVIF achieves high quality at lower values than WebP or JPEG.'
	);
	ctap_card_close();

	// Exclude patterns card.
	ctap_card_open( 'Excluded Filename Patterns', 'dashicons-shield' );
	ctap_field(
		'Exclude patterns',
		ctap_textarea( 'cotlas_imgconv_exclude_patterns', "logo\nsite-logo\nbrand\nfavicon\nicon", 6 ),
		'One pattern per line. Files whose path contains a matching pattern will not be converted. Enter plain words (e.g. <code>logo</code>) or PHP regex (e.g. <code>/logo/i</code>). Defaults apply when left blank: logo, site-logo, brand, favicon, icon.'
	);
	ctap_card_close();

	ctap_form_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 13. PAGE: POST FORMATS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_post_formats() {
	ctap_page_open( 'Post Formats', 'dashicons-format-audio', 'Custom metaboxes, audio player, block editor enhancements, taxonomies, and post type rename.' );
	$tabs = array(
		array( 'id' => 'settings',   'label' => 'Settings',    'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'taxonomies', 'label' => 'Taxonomies',  'icon' => 'dashicons-category' ),
		array( 'id' => 'posttype',   'label' => 'Post Type',   'icon' => 'dashicons-admin-post' ),
		array( 'id' => 'codes',      'label' => 'Shortcodes',  'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_post_formats_settings', 'settings' );
	ctap_card_open( 'Post Formats', 'dashicons-format-audio' );
	ctap_module_status( 'cotlas_post_formats_enabled', 'Post Formats', 'Enables YouTube/audio metaboxes on posts, an HTML5 audio player shortcode, and a block-editor audio embed block.' );
	ctap_card_close();
	ctap_card_open( 'What This Module Does', 'dashicons-info-outline' );
	ctap_info( '<ul style="margin:.5em 0 0 1.4em"><li><strong>YouTube Video metabox</strong> — paste a YouTube URL; the plugin extracts the video ID and stores it as post meta.</li><li><strong>Audio metabox</strong> — stores an audio file URL (<code>_audio_file_url</code>) for the post.</li><li><strong>Block editor audio block</strong> — a custom block that renders an inline audio player in the editor.</li><li><strong>[audio_player] shortcode</strong> — styled HTML5 audio player with themes (modern / minimal / dark).</li><li><em>More post-format types and custom post type support coming soon.</em></li></ul>' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'taxonomies', $active );
	ctap_form_open( 'ctap_save_post_formats_taxonomies', 'taxonomies' );
	ctap_card_open( 'Taxonomies for Posts', 'dashicons-category' );
	ctap_toggle( 'cotlas_taxonomy_location_enabled', 'Location Taxonomy', 'Registers a hierarchical <code>location</code> taxonomy on Posts. Slug: <code>/location/</code>. Adds a Location column and panel in the block editor.' );
	ctap_toggle( 'cotlas_taxonomy_state_city_enabled', 'State &amp; City Taxonomies', 'Registers hierarchical <code>state</code> and flat <code>city</code> taxonomies on Posts. Slugs: <code>/state/</code> and <code>/city/</code>.' );
	ctap_card_close();
	ctap_info( 'After enabling or disabling a taxonomy, visit <strong>Settings → Permalinks</strong> and click Save to flush rewrite rules.' );
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'posttype', $active );
	ctap_form_open( 'ctap_save_post_formats_posttype', 'posttype' );
	ctap_card_open( 'Rename Post Type', 'dashicons-admin-post' );
	ctap_toggle( 'cotlas_rename_post_to_news', 'Rename "Post" to "News"', 'Replaces all admin labels for the default <em>post</em> type with <em>News</em> — menu name, editor messages, bulk action messages, and the admin bar label.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'audio_player', 'Styled HTML5 audio player for Audio-format posts. Requires <code>_audio_file_url</code> post meta.',
			'<code>style="modern"</code> <code>style="minimal"</code> <code>style="dark"</code><br><code>width="100%"</code> <code>height="54px"</code><br><code>autoplay="0"</code> or <code>autoplay="1"</code><br><code>loop="0"</code> or <code>loop="1"</code>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 14. PAGE: SOCIAL MEDIA
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_social() {
	ctap_page_open( 'Social Media', 'dashicons-share', 'Site-wide social media URLs used by shortcodes and GenerateBlocks dynamic tags.' );
	$tabs = array(
		array( 'id' => 'links', 'label' => 'Social Links', 'icon' => 'dashicons-share' ),
		array( 'id' => 'codes', 'label' => 'Shortcodes',   'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'links', $active );
	ctap_form_open( 'ctap_save_social', 'links' );
	ctap_card_open( 'Social Media URLs', 'dashicons-share' );
	ctap_field( 'Facebook Page',     ctap_url( 'cotlas_social_facebook',  'https://facebook.com/...' ) );
	ctap_field( 'Twitter / X',       ctap_url( 'cotlas_social_twitter',   'https://x.com/...' ) );
	ctap_field( 'YouTube Channel',   ctap_url( 'cotlas_social_youtube',   'https://youtube.com/...' ) );
	ctap_field( 'Instagram Profile', ctap_url( 'cotlas_social_instagram', 'https://instagram.com/...' ) );
	ctap_field( 'LinkedIn Page',     ctap_url( 'cotlas_social_linkedin',  'https://linkedin.com/...' ) );
	ctap_field( 'Threads Profile',   ctap_url( 'cotlas_social_threads',   'https://threads.net/...' ) );
	ctap_field( 'WhatsApp Number',   ctap_input( 'cotlas_company_whatsapp' ), 'International format without + or spaces, e.g. <code>15551234567</code>.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Social Shortcodes & GB Tags', 'dashicons-shortcode' );
	ctap_info( 'The <code>company_social</code> GenerateBlocks dynamic tag returns any of these URLs as a Dynamic Link on buttons or images.' );
	ctap_ref_table( array(
		array( 'cotlas_social',       'Site social icons. Only platforms with a saved URL are rendered.',
			'<code>class="my-class"</code><br><code>size="24"</code> — icon size in px<br><code>show_names="1"</code> — show platform names beside icons<br><code>networks="facebook,twitter,youtube,instagram,linkedin,threads"</code>' ),
		array( 'social_share',        'Share buttons for the current post.',
			'<code>class="my-class"</code> <code>size="24"</code> <code>show_names="1"</code><br><code>networks="facebook,twitter,linkedin,whatsapp,telegram,pinterest,reddit,threads,print"</code>' ),
		array( 'author_social_links', "Post author's social links from their user profile.",
			'<code>class="my-class"</code> <code>size="24"</code> <code>show_names="1"</code><br><code>networks="facebook,twitter,youtube,instagram,linkedin,pinterest"</code>' ),
		array( 'social_facebook',     'Facebook URL shortcode — returns the saved URL.',
			'<em style="color:#999">none</em>' ),
		array( 'social_twitter',      'Twitter/X URL shortcode.',
			'<em style="color:#999">none</em>' ),
		array( 'social_youtube',      'YouTube URL shortcode.',
			'<em style="color:#999">none</em>' ),
		array( 'social_instagram',    'Instagram URL shortcode.',
			'<em style="color:#999">none</em>' ),
		array( 'social_linkedin',     'LinkedIn URL shortcode.',
			'<em style="color:#999">none</em>' ),
		array( 'social_threads',      'Threads URL shortcode.',
			'<em style="color:#999">none</em>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 15. PAGE: TRACKING CODES
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_tracking() {
	ctap_page_open( 'Tracking Codes', 'dashicons-chart-line', 'Google Analytics, Search Console verification, AdSense, and custom head/foot scripts.' );
	$tabs = array(
		array( 'id' => 'analytics', 'label' => 'Analytics',     'icon' => 'dashicons-chart-bar' ),
		array( 'id' => 'scripts',   'label' => 'Custom Scripts', 'icon' => 'dashicons-editor-code' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'analytics', $active );
	ctap_form_open( 'ctap_save_tracking_analytics', 'analytics' );
	ctap_card_open( 'Analytics & Verification', 'dashicons-chart-bar' );
	ctap_field( 'Google Analytics 4 (GA4) ID', ctap_input( 'cotlas_ga4_code', 'G-XXXXXXXXXX' ), 'Measurement ID only (e.g. G-XXXXXXXXXX). The gtag.js script is injected automatically.' );
	ctap_field( 'Search Console Meta Tag', ctap_textarea( 'cotlas_search_console_code', '<meta name="google-site-verification" content="..." />', 2 ), 'Paste the full meta verification tag.' );
	ctap_field( 'AdSense Code', ctap_textarea( 'cotlas_adsense_code', '<script async src="https://pagead2.googlesyndication.com/...">', 4 ), 'Injected in &lt;head&gt; as recommended by Google.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'scripts', $active );
	ctap_form_open( 'ctap_save_tracking_scripts', 'scripts' );
	ctap_card_open( 'Custom Scripts', 'dashicons-editor-code' );
	ctap_info( 'Scripts here are output verbatim — include full <code>&lt;script&gt;</code> tags. Not sanitised beyond XSS-safe wp_kses_post rules.' );
	ctap_field( 'Header Scripts', ctap_textarea( 'cotlas_header_scripts', '<script>...</script>', 6 ), 'Injected inside &lt;head&gt; before &lt;/head&gt;.' );
	ctap_field( 'Footer Scripts', ctap_textarea( 'cotlas_footer_scripts', '<script>...</script>', 6 ), 'Injected before &lt;/body&gt;.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_page_close();
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 16. PAGE: USER SETTINGS
 * ═══════════════════════════════════════════════════════════════════════════ */

function cotlas_panel_page_users() {
	ctap_page_open( 'User Settings', 'dashicons-admin-users', 'Custom avatar upload and author social links profile enhancements.' );
	$tabs = array(
		array( 'id' => 'settings', 'label' => 'Settings',  'icon' => 'dashicons-admin-generic' ),
		array( 'id' => 'codes',    'label' => 'Shortcodes', 'icon' => 'dashicons-shortcode' ),
	);
	$active = ctap_nav( $tabs );

	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_users', 'settings' );
	ctap_card_open( 'User Profile Enhancements', 'dashicons-admin-users' );
	ctap_module_status( 'cotlas_user_profile_enabled', 'User Profile Enhancements', 'Master toggle. When off, no profile enhancements are registered.' );
	ctap_card_close();
	ctap_card_open( 'Feature Toggles', 'dashicons-admin-generic' );
	ctap_toggle( 'cotlas_user_avatar_enabled',       'Custom Avatar Upload',  'Adds a file-upload field to user profiles for uploading a custom avatar. Replaces Gravatar when set.' );
	ctap_toggle( 'cotlas_user_social_links_enabled', 'Author Social Links',   'Adds Facebook, Twitter, Instagram, LinkedIn, YouTube, and Pinterest fields to user profiles. Used by [author_social_links] shortcode.' );
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	ctap_pane_open( 'codes', $active );
	ctap_card_open( 'Shortcodes', 'dashicons-shortcode' );
	ctap_ref_table( array(
		array( 'author_social_links', "Post author's social links from their WP profile. Only platforms with a saved URL are shown.",
			'<code>class="my-class"</code><br><code>size="24"</code> — icon size in px<br><code>show_names="1"</code> — show platform names<br><code>networks="facebook,twitter,youtube,instagram,linkedin,pinterest"</code>' ),
	) );
	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}
