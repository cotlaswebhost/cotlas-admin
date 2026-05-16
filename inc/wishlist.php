<?php
/**
 * Wishlist System
 *
 * Guests  : wishlist stored in localStorage + a first-party cookie (cotlas_wl)
 *           which lets the PHP-side GB Query filter work on the wishlist page.
 *           Guest add/remove also updates the global _cotlas_wishlist_count post meta.
 * Logged-in: stored in user_meta (cotlas_wishlist) + localStorage cache.
 *            Total wish count stored in post_meta (_cotlas_wishlist_count).
 *
 * Shortcode: [cotlas_wishlist]       — heart icon button with wish count.
 * Shortcode: [cotlas_wishlist_count] — standalone count only.
 * GB Query parameter: wishlistPosts  — filters to current visitor's wishlisted posts.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_wishlist_enabled' ) ) {
	return;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 1. AJAX — toggle a wishlist entry (logged-in users only)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_toggle_wishlist', 'cotlas_ajax_toggle_wishlist' );
/**
 * Toggle a post ID in/out of the current user's wishlist and update the count.
 */
function cotlas_ajax_toggle_wishlist() {
	check_ajax_referer( 'cotlas_wl_nonce', 'nonce' );

	$post_id = absint( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
	if ( ! $post_id || ! get_post( $post_id ) ) {
		wp_send_json_error( 'Invalid post.' );
	}

	$user_id    = get_current_user_id();
	$list       = get_user_meta( $user_id, 'cotlas_wishlist', true );
	$list       = is_array( $list ) ? array_map( 'absint', $list ) : array();
	$wishlisted = false;
	$count      = (int) get_post_meta( $post_id, '_cotlas_wishlist_count', true );

	if ( in_array( $post_id, $list, true ) ) {
		$list  = array_values( array_diff( $list, array( $post_id ) ) );
		$count = max( 0, $count - 1 );
	} else {
		$list[]     = $post_id;
		$wishlisted = true;
		$count++;
	}

	update_user_meta( $user_id, 'cotlas_wishlist', $list );
	update_post_meta( $post_id, '_cotlas_wishlist_count', $count );

	wp_send_json_success(
		array(
			'wishlisted' => $wishlisted,
			'post_id'    => $post_id,
			'list'       => array_values( $list ),
			'count'      => $count,
		)
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 2. AJAX — guest count update (no login required)
 *
 * Guests send the action type ("add" or "remove") from their localStorage
 * state so the server can safely increment or decrement the count.
 * No nonce required — the operation is additive/subtractive on a public
 * counter, equivalent to a page view. Rate limiting is handled by the
 * fact that the JS only calls this once per user click.
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_nopriv_cotlas_wishlist_guest_count', 'cotlas_ajax_wishlist_guest_count' );
/**
 * Increment or decrement the wish count for a post (guest path).
 */
function cotlas_ajax_wishlist_guest_count() {
	$post_id     = absint( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
	$action_type = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : '';

	if ( ! $post_id || ! get_post( $post_id ) || ! in_array( $action_type, array( 'add', 'remove' ), true ) ) {
		wp_send_json_error( 'Invalid request.' );
	}

	$count = (int) get_post_meta( $post_id, '_cotlas_wishlist_count', true );

	if ( 'add' === $action_type ) {
		$count++;
	} else {
		$count = max( 0, $count - 1 );
	}

	update_post_meta( $post_id, '_cotlas_wishlist_count', $count );

	wp_send_json_success( array( 'count' => $count ) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 3. AJAX — get all wishlisted posts for current user (logged-in only)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_get_wishlists', 'cotlas_ajax_get_wishlists' );
/**
 * Return the complete wishlist for the current user.
 */
function cotlas_ajax_get_wishlists() {
	check_ajax_referer( 'cotlas_wl_nonce', 'nonce' );

	$list = get_user_meta( get_current_user_id(), 'cotlas_wishlist', true );
	$list = is_array( $list ) ? array_map( 'absint', $list ) : array();

	wp_send_json_success( array( 'list' => array_values( $list ) ) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 3. GB QUERY LOOP FILTER — wishlistPosts parameter
 *
 * Logged-in : reads user_meta.
 * Guests    : reads the cotlas_wl cookie (written by the JS layer).
 * ═══════════════════════════════════════════════════════════════════════════ */

add_filter( 'generateblocks_query_loop_args', 'cotlas_apply_wishlist_query', 10, 2 );
/**
 * Filter GB Query Loop args when the wishlistPosts parameter is set.
 *
 * @param array $query_args  Current WP_Query args.
 * @param array $attributes  GB block attributes.
 * @return array
 */
function cotlas_apply_wishlist_query( $query_args, $attributes ) {
	if ( empty( $query_args['wishlistPosts'] ) ) {
		return $query_args;
	}

	unset( $query_args['wishlistPosts'] );

	$post_ids = array();

	if ( is_user_logged_in() ) {
		$saved    = get_user_meta( get_current_user_id(), 'cotlas_wishlist', true );
		$post_ids = is_array( $saved ) ? array_map( 'absint', $saved ) : array();
	} elseif ( ! empty( $_COOKIE['cotlas_wl'] ) ) {
		$raw      = sanitize_text_field( wp_unslash( $_COOKIE['cotlas_wl'] ) ) ;
		$parts    = explode( ',', $raw );
		$post_ids = array_values( array_filter( array_map( 'absint', $parts ) ) );
	}

	if ( empty( $post_ids ) ) {
		$query_args['post__in'] = array( 0 );
	} else {
		$query_args['post__in'] = $post_ids;
		$query_args['orderby']  = 'post__in';
	}

	return $query_args;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 4. SHORTCODE — [cotlas_wishlist]
 *
 * Usage: [cotlas_wishlist]
 *        [cotlas_wishlist size="34" show_count="false" class="my-btn"]
 * ═══════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'cotlas_wishlist', 'cotlas_wishlist_shortcode' );
/**
 * Render the wishlist heart toggle button with optional count.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML button.
 */
function cotlas_wishlist_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'class'      => '',
			'size'       => '34',
			'show_count' => 'true',
			'post_id'    => 0, // internal: lets dynamic tag callbacks pass the correct ID directly
		),
		$atts,
		'cotlas_wishlist'
	);

	$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$size       = absint( $atts['size'] );
	$show_count = ( 'false' !== $atts['show_count'] );
	$extra      = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';
	$logged_in  = is_user_logged_in() ? 'true' : 'false';
	$nonce      = is_user_logged_in() ? wp_create_nonce( 'cotlas_wl_nonce' ) : '';
	$ajax_url   = esc_url( admin_url( 'admin-ajax.php' ) );
	$count      = (int) get_post_meta( $post_id, '_cotlas_wishlist_count', true );

	$count_html = '';
	if ( $show_count ) {
		$count_html = sprintf(
			'<span class="cotlas-wishlist-count" data-post-id="%d">%d</span>',
			$post_id,
			$count
		);
	}

	return sprintf(
		'<button type="button"
			class="cotlas-wishlist-btn%s"
			data-post-id="%d"
			data-logged-in="%s"
			data-nonce="%s"
			data-ajax-url="%s"
			aria-label="%s"
			title="%s"
			style="width:%dpx;height:%dpx;padding:8px;border-radius:99px;background:var(--wp--preset--color--shadcn-accent,#f1f5f9);border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background .2s,color .2s;color:hsl(215,14%%,34%%);flex-shrink:0;"
		><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M19.4626 3.99415C16.7809 2.34923 14.4404 3.01211 13.0344 4.06801C12.4578 4.50096 12.1696 4.71743 12 4.71743C11.8304 4.71743 11.5422 4.50096 10.9656 4.06801C9.55962 3.01211 7.21909 2.34923 4.53744 3.99415C1.01807 6.15294 0.221721 13.2749 8.33953 19.2834C9.88572 20.4278 10.6588 21 12 21C13.3412 21 14.1143 20.4278 15.6605 19.2834C23.7783 13.2749 22.9819 6.15294 19.4626 3.99415Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg></button>%s',
		$extra,
		$post_id,
		$logged_in,
		$nonce,
		$ajax_url,
		esc_attr__( 'Add to wishlist', 'cotlas-admin' ),
		esc_attr__( 'Add to wishlist', 'cotlas-admin' ),
		$size,
		$size,
		$count_html
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 5. SHORTCODE — [cotlas_wishlist_count]
 *
 * Standalone count span — use anywhere, including outside a Query Loop.
 * Usage: [cotlas_wishlist_count]
 * ═══════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'cotlas_wishlist_count', 'cotlas_wishlist_count_shortcode' );
/**
 * Render the wishlist count span for the current post.
 *
 * @return string
 */
function cotlas_wishlist_count_shortcode() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '0';
	}
	$count = (int) get_post_meta( $post_id, '_cotlas_wishlist_count', true );
	return sprintf(
		'<span class="cotlas-wishlist-count" data-post-id="%d">%d</span>',
		$post_id,
		$count
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 6. FRONTEND ASSETS — CSS + inline JS
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_enqueue_scripts', 'cotlas_wishlist_assets' );
/**
 * Enqueue wishlist styles and inline JS.
 */
function cotlas_wishlist_assets() {
	wp_add_inline_style( 'wp-block-library', cotlas_wl_css() );

	wp_register_script( 'cotlas-wishlist', false, array(), false, true );
	wp_enqueue_script( 'cotlas-wishlist' );
	wp_add_inline_script( 'cotlas-wishlist', cotlas_wl_js() );
}

/**
 * Wishlist button CSS.
 *
 * @return string
 */
function cotlas_wl_css() {
	return '
.cotlas-wishlist-btn { outline-offset: 2px; vertical-align: middle; }
.cotlas-wishlist-btn:focus-visible { outline: 2px solid #2271b1; }
.cotlas-wishlist-btn.is-wishlisted { color: #e11d48 !important; }
.cotlas-wishlist-btn.is-wishlisted svg { fill: #e11d48 !important; stroke: #e11d48 !important; }
.cotlas-wishlist-btn.is-wishlisted svg path { fill: #e11d48 !important; stroke: #e11d48 !important; }
.cotlas-wishlist-btn:hover { opacity: .8; }
.cotlas-wishlist-count { font-size: 13px; font-weight: 500; line-height: 1; pointer-events: none; }
';
}

/**
 * Wishlist frontend JS (localStorage + cookie + optional AJAX for logged-in users).
 *
 * @return string
 */
function cotlas_wl_js() {
	$logged_in = is_user_logged_in() ? 'true' : 'false';

	return '(function () {
"use strict";
var WL_KEY       = "cotlas_wishlist";
var WL_COOKIE    = "cotlas_wl";
var IS_LOGGED_IN = ' . $logged_in . ';

/* -- localStorage helpers ----------------------------------------- */

function getLocalList() {
	try { return JSON.parse( localStorage.getItem( WL_KEY ) || "[]" ); }
	catch (e) { return []; }
}

function saveLocalList( list ) {
	try { localStorage.setItem( WL_KEY, JSON.stringify( list ) ); }
	catch (e) {}
	/* Write first-party cookie so PHP query filter can read on next request */
	var val = list.join( "," );
	var exp = new Date( Date.now() + 365 * 86400 * 1000 ).toUTCString();
	document.cookie = WL_COOKIE + "=" + encodeURIComponent( val ) +
		";expires=" + exp + ";path=/;SameSite=Lax";
}

/* -- Resolve the real post ID — DOM walk first, data-post-id fallback */

function resolvePostId( btn ) {
	var cached = parseInt( btn.dataset.resolvedId, 10 );
	if ( cached > 0 ) { return cached; }

	/* Walk the DOM: GB loop items carry post-{n} on their wrapper */
	var el = btn.parentElement;
	while ( el ) {
		var cls = ( typeof el.className === "string" ) ? el.className : "";
		var m = cls.match( /\bpost-(\d+)\b/ );
		if ( m ) {
			var id = parseInt( m[1], 10 );
			if ( id > 0 ) {
				btn.dataset.resolvedId = id;
				return id;
			}
		}
		el = el.parentElement;
	}

	/* Fallback: single-post pages where no loop wrapper exists */
	var fb = parseInt( btn.dataset.postId, 10 );
	if ( fb > 0 ) {
		btn.dataset.resolvedId = fb;
		return fb;
	}
	return 0;
}

/* -- Update count spans for a given post ID ----------------------- */

function updateCountEl( postId, newCount ) {
	document.querySelectorAll( ".cotlas-wishlist-count[data-post-id]" )
		.forEach( function (el) {
			if ( parseInt( el.dataset.postId, 10 ) === postId ) {
				el.textContent = newCount;
			}
		} );
}

/* -- Update all wishlist buttons for a given post ID -------------- */

function setButtonState( postId, wishlisted ) {
	document.querySelectorAll( ".cotlas-wishlist-btn" ).forEach( function (btn) {
		if ( resolvePostId( btn ) !== postId ) { return; }
		if ( wishlisted ) {
			btn.classList.add( "is-wishlisted" );
			btn.setAttribute( "aria-label", "Remove from wishlist" );
			btn.setAttribute( "title",      "Remove from wishlist" );
		} else {
			btn.classList.remove( "is-wishlisted" );
			btn.setAttribute( "aria-label", "Add to wishlist" );
			btn.setAttribute( "title",      "Add to wishlist" );
		}
	} );
}

/* -- Initialise button states from localStorage ------------------- */

function initStates() {
	var list = getLocalList();
	document.querySelectorAll( ".cotlas-wishlist-btn" ).forEach( function (btn) {
		var id = resolvePostId( btn );
		if ( ! id ) { return; }
		if ( list.indexOf( id ) !== -1 ) {
			btn.classList.add( "is-wishlisted" );
		} else {
			btn.classList.remove( "is-wishlisted" );
		}
	} );
}

/* -- AJAX helper -------------------------------------------------- */

function ajaxPost( action, data, callback ) {
	var fd = new FormData();
	fd.append( "action", action );
	var ajaxUrl = data.ajaxUrl || "/wp-admin/admin-ajax.php";
	Object.keys( data ).forEach( function (k) {
		if ( k !== "ajaxUrl" ) { fd.append( k, data[k] ); }
	} );
	fetch( ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" } )
		.then( function (r) { return r.json(); } )
		.then( callback )
		.catch( function () {} );
}

/* -- Sync server list on load (logged-in only) -------------------- */

function syncFromServer() {
	var btn = document.querySelector( ".cotlas-wishlist-btn[data-logged-in=\"true\"]" );
	if ( ! btn ) return;
	ajaxPost( "cotlas_get_wishlists",
		{ nonce: btn.dataset.nonce, ajaxUrl: btn.dataset.ajaxUrl },
		function (resp) {
			if ( ! resp.success ) return;
			var serverList = resp.data.list.map( Number );
			saveLocalList( serverList );
			serverList.forEach( function (id) { setButtonState( id, true ); } );
			document.querySelectorAll( ".cotlas-wishlist-btn.is-wishlisted" )
				.forEach( function (b) {
					var id = parseInt( b.dataset.postId, 10 );
					if ( serverList.indexOf( id ) === -1 ) { setButtonState( id, false ); }
				} );
		}
	);
}

/* -- Click handler ------------------------------------------------ */

document.addEventListener( "click", function (e) {
	var btn = e.target.closest( ".cotlas-wishlist-btn" );
	if ( ! btn ) return;
	e.preventDefault();

	var postId = resolvePostId( btn );
	if ( ! postId ) { return; }

	if ( IS_LOGGED_IN ) {
		ajaxPost( "cotlas_toggle_wishlist",
			{ post_id: postId, nonce: btn.dataset.nonce, ajaxUrl: btn.dataset.ajaxUrl },
			function (resp) {
				if ( ! resp.success ) return;
				saveLocalList( resp.data.list.map( Number ) );
				setButtonState( postId, resp.data.wishlisted );
				updateCountEl( postId, resp.data.count );
			}
		);
	} else {
		var list       = getLocalList();
		var idx        = list.indexOf( postId );
		var wishlisted = false;
		var actionType = "add";
		if ( idx !== -1 ) {
			list.splice( idx, 1 );
			actionType = "remove";
		} else {
			list.push( postId );
			wishlisted = true;
		}
		saveLocalList( list );
		setButtonState( postId, wishlisted );
		/* Update server count for guests */
		var ajaxUrl = btn.dataset.ajaxUrl || "/wp-admin/admin-ajax.php";
		ajaxPost( "cotlas_wishlist_guest_count",
			{ post_id: postId, action_type: actionType, ajaxUrl: ajaxUrl },
			function (resp) {
				if ( resp.success ) { updateCountEl( postId, resp.data.count ); }
			}
		);
	}
} );

/* -- Boot --------------------------------------------------------- */

function boot() {
	initStates();
	if ( IS_LOGGED_IN ) { syncFromServer(); }
}

if ( document.readyState === "loading" ) {
	document.addEventListener( "DOMContentLoaded", boot );
} else {
	boot();
}
})();';
}
