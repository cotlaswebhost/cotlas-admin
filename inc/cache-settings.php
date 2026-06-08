<?php
/**
 * Cotlas Browser Cache
 *
 * Features:
 *   1. Sends Expires / Cache-Control headers for static assets (CSS, JS, images,
 *      fonts) when browser caching is enabled. Lifetimes are configurable per
 *      asset type.
 *   2. "Clear Cache" button in the admin panel that:
 *        a. Bumps a cache-version nonce (busts query strings for all visitors).
 *        b. Returns a Clear-Site-Data: "cache" header so the admin's own
 *           browser cache is flushed immediately.
 *
 * Options stored:
 *   cotlas_cache_enabled            bool   – master toggle
 *   cotlas_cache_lifetime_images    int    – seconds (default 2 592 000 = 30 days)
 *   cotlas_cache_lifetime_css_js    int    – seconds (default 604 800 = 7 days)
 *   cotlas_cache_lifetime_fonts     int    – seconds (default 2 592 000 = 30 days)
 *   cotlas_cache_version            string – auto-incremented on every purge
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// ─── Default lifetimes ───────────────────────────────────────────────────────
define( 'COTLAS_CACHE_DEFAULT_IMAGES', 2592000 ); // 30 days
define( 'COTLAS_CACHE_DEFAULT_CSS_JS', 604800  ); // 7 days
define( 'COTLAS_CACHE_DEFAULT_FONTS',  2592000 ); // 30 days

/* ═══════════════════════════════════════════════════════════════════════════
 * 1. CACHE HEADERS  –  hooked to send_headers (runs on every page request)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'send_headers', 'cotlas_send_cache_headers' );

/**
 * Add Expires + Cache-Control headers for static assets.
 *
 * WordPress itself never really sends these headers; the web-server config
 * normally does it.  This function adds them at the PHP / WordPress layer for
 * sites that cannot edit server configs (shared hosting, etc.).
 *
 * NOTE: These headers only affect *direct* requests to this WordPress
 * instance. If assets are served via a CDN, configure caching there too.
 */
function cotlas_send_cache_headers() {
	if ( ! get_option( 'cotlas_cache_enabled' ) ) {
		return;
	}

	// Don't interfere with admin, login or AJAX requests.
	if ( is_admin() || wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$ext         = strtolower( pathinfo( strtok( $request_uri, '?' ), PATHINFO_EXTENSION ) );

	$lifetime = 0;

	if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp' ), true ) ) {
		$lifetime = (int) get_option( 'cotlas_cache_lifetime_images', COTLAS_CACHE_DEFAULT_IMAGES );
	} elseif ( in_array( $ext, array( 'css', 'js' ), true ) ) {
		$lifetime = (int) get_option( 'cotlas_cache_lifetime_css_js', COTLAS_CACHE_DEFAULT_CSS_JS );
	} elseif ( in_array( $ext, array( 'woff', 'woff2', 'ttf', 'eot', 'otf' ), true ) ) {
		$lifetime = (int) get_option( 'cotlas_cache_lifetime_fonts', COTLAS_CACHE_DEFAULT_FONTS );
	}

	if ( $lifetime <= 0 ) {
		return;
	}

	$expires = gmdate( 'D, d M Y H:i:s', time() + $lifetime ) . ' GMT';
	header( 'Expires: ' . $expires );
	header( 'Cache-Control: public, max-age=' . $lifetime . ', immutable' );
	header( 'Pragma: public' );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 2. CACHE-BUSTING QUERY VERSION  –  appended to enqueued asset URLs
 * ═══════════════════════════════════════════════════════════════════════════ */

add_filter( 'style_loader_src',  'cotlas_append_cache_version', 10, 2 );
add_filter( 'script_loader_src', 'cotlas_append_cache_version', 10, 2 );

/**
 * Append ?cv=<version> to enqueued CSS/JS URLs so bumping the version option
 * immediately busts the browser cache for all visitors.
 *
 * Only runs on the front-end; skips external URLs.
 */
function cotlas_append_cache_version( $src, $handle ) {
	if ( is_admin() ) {
		return $src;
	}

	$version = get_option( 'cotlas_cache_version', '' );
	if ( ! $version ) {
		return $src;
	}

	// Only touch local / same-origin URLs.
	$home = home_url();
	if ( strpos( $src, $home ) === false && strpos( $src, '/' ) !== 0 ) {
		return $src;
	}

	$src = remove_query_arg( 'cv', $src );
	return add_query_arg( 'cv', $version, $src );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 3. AJAX: CLEAR CACHE  –  wp_ajax_cotlas_clear_cache
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_clear_cache', 'cotlas_ajax_clear_cache' );

/**
 * AJAX handler for the "Clear Cache" button.
 *
 * Actions taken:
 *   1. Verify nonce + capability.
 *   2. Bump cotlas_cache_version (invalidates ?cv= on all enqueued assets for
 *      every visitor on their next page load).
 *   3. Send Clear-Site-Data: "cache" so the admin's own browser cache is
 *      cleared immediately (Chrome/Edge/Firefox honour this header).
 *   4. Return JSON success so the JS button can show a confirmation.
 */
function cotlas_ajax_clear_cache() {
	// 1. Security checks.
	$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'cotlas_clear_cache' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cotlas-admin' ) ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cotlas-admin' ) ) );
	}

	// 2. Bump the cache version – affects all visitors.
	$new_version = gmdate( 'YmdHis' );
	update_option( 'cotlas_cache_version', $new_version );

	// 3. Instruct the admin's browser to clear its local cache for this site.
	header( 'Clear-Site-Data: "cache"' );

	// 4. Done.
	wp_send_json_success( array(
		'message' => __( 'Cache cleared! All visitors will receive fresh assets on their next page load. Your browser cache has also been flushed.', 'cotlas-admin' ),
	) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 4. ADMIN PANEL PAGE CALLBACK
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Render the "Cotlas Cache" admin page.
 * Called from admin-panel.php via the cotlas-cache submenu slug.
 */
function cotlas_panel_page_cache() {
	ctap_page_open( 'Browser Cache', 'dashicons-performance', 'Control browser-side caching headers and asset cache-busting for faster page loads.' );

	$tabs = array(
		array( 'id' => 'settings', 'label' => 'Settings',    'icon' => 'dashicons-admin-settings' ),
		array( 'id' => 'purge',    'label' => 'Clear Cache',  'icon' => 'dashicons-trash' ),
	);
	$active = ctap_nav( $tabs );

	// ── Settings tab ─────────────────────────────────────────────────────────
	ctap_pane_open( 'settings', $active );
	ctap_form_open( 'ctap_save_cache', 'settings' );
	ctap_card_open( 'Browser Caching', 'dashicons-performance' );
	ctap_info(
		'When enabled, <code>Expires</code> and <code>Cache-Control</code> headers are added to static asset responses so browsers cache them locally. ' .
		'This only applies to requests served directly by WordPress (PHP). If you use a CDN or nginx/Apache rules for assets, you may not need this.'
	);
	ctap_module_status(
		'cotlas_cache_enabled',
		'Browser Caching',
		'Adds Expires + Cache-Control headers to CSS, JS, image and font responses.'
	);
	ctap_card_close();

	ctap_card_open( 'Cache Lifetimes', 'dashicons-clock' );
	ctap_info( 'How long browsers should keep each asset type in their local cache. Values are in <strong>seconds</strong>.' );
	ctap_field(
		'Images (JPG, PNG, WebP …)',
		ctap_input( 'cotlas_cache_lifetime_images', '2592000', 'number' ),
		'Default: <code>2592000</code> (30 days). Recommended for images that rarely change.'
	);
	ctap_field(
		'CSS &amp; JavaScript',
		ctap_input( 'cotlas_cache_lifetime_css_js', '604800', 'number' ),
		'Default: <code>604800</code> (7 days). Use "Clear Cache" after theme/plugin updates.'
	);
	ctap_field(
		'Fonts (WOFF, TTF …)',
		ctap_input( 'cotlas_cache_lifetime_fonts', '2592000', 'number' ),
		'Default: <code>2592000</code> (30 days). Fonts rarely change.'
	);
	ctap_card_close();
	ctap_form_close();
	ctap_pane_close();

	// ── Clear Cache tab ───────────────────────────────────────────────────────
	ctap_pane_open( 'purge', $active );
	ctap_card_open( 'Clear Browser Cache', 'dashicons-trash' );
	ctap_info(
		'Clicking the button below will:<br>' .
		'<ul style="margin:.5em 0 0 1.4em">' .
		'<li>Bump the internal <strong>cache version</strong> appended to all enqueued CSS/JS URLs — every visitor\'s browser will fetch fresh copies on their next visit.</li>' .
		'<li>Send a <code>Clear-Site-Data: "cache"</code> header that instructs <strong>your own browser</strong> to flush its local cache for this site immediately.</li>' .
		'</ul>'
	);

	$cache_version = get_option( 'cotlas_cache_version', 'none' );
	echo '<div class="ctap-field-row" style="align-items:center">';
	echo '<div class="ctap-field-label">Current Cache Version</div>';
	echo '<div class="ctap-field-input"><code style="font-size:13px;background:#f0f0f1;padding:4px 8px;border-radius:4px">'
		. esc_html( $cache_version )
		. '</code></div></div>';

	// Nonce for the AJAX call
	$nonce = wp_create_nonce( 'cotlas_clear_cache' );
	echo '<div class="ctap-field-row" style="padding-top:8px">';
	echo '<div class="ctap-field-label"></div>';
	echo '<div class="ctap-field-input">';
	echo '<button type="button" id="cotlas-clear-cache-btn" class="button button-primary" '
		. 'style="display:inline-flex;align-items:center;gap:6px;font-size:13px;height:36px;padding:0 16px" '
		. 'data-nonce="' . esc_attr( $nonce ) . '">'
		. '<span class="dashicons dashicons-trash" style="line-height:36px"></span>'
		. esc_html__( 'Clear Cache Now', 'cotlas-admin' )
		. '</button>';
	echo '<span id="cotlas-clear-cache-msg" style="margin-left:12px;display:none;font-size:12px"></span>';
	echo '</div></div>';

	// Inline JS for the button (admin page only – no need for a separate file)
	?>
	<script>
	(function () {
		var btn = document.getElementById('cotlas-clear-cache-btn');
		var msg = document.getElementById('cotlas-clear-cache-msg');
		if (!btn) return;

		btn.addEventListener('click', function () {
			btn.disabled = true;
			btn.textContent = 'Clearing\u2026';
			msg.style.display = 'none';

			var data = new URLSearchParams();
			data.append('action', 'cotlas_clear_cache');
			data.append('_nonce', btn.dataset.nonce);

			fetch(ajaxurl, {
				method:      'POST',
				credentials: 'same-origin',
				body:        data,
			})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				btn.disabled = false;
				btn.innerHTML = '<span class="dashicons dashicons-trash" style="line-height:36px"></span>Clear Cache Now';
				if (res.success) {
					msg.style.color   = '#00a32a';
					msg.style.display = 'inline';
					msg.textContent   = res.data && res.data.message ? res.data.message : 'Cache cleared!';
					// Reload after 2 s so the new version number is visible.
					setTimeout(function () { location.reload(); }, 2200);
				} else {
					msg.style.color   = '#d63638';
					msg.style.display = 'inline';
					msg.textContent   = (res.data && res.data.message) ? res.data.message : 'An error occurred.';
				}
			})
			.catch(function () {
				btn.disabled  = false;
				msg.style.color   = '#d63638';
				msg.style.display = 'inline';
				msg.textContent   = 'Request failed. Please try again.';
			});
		});
	})();
	</script>
	<?php

	ctap_card_close();
	ctap_pane_close();

	ctap_page_close();
}
