<?php
/**
 * Reading List (Bookmark) System
 *
 * Guests  : bookmarks stored in localStorage + a first-party cookie (cotlas_rl)
 *           which lets the PHP-side GB Query filter work on the reading list page.
 * Logged-in: stored in user_meta (cotlas_reading_list) + kept in localStorage
 *            as a UI-speed cache; synced from server on each page load.
 *
 * Shortcode: [cotlas_bookmark] — bookmark icon button for use inside Query Loops.
 * GB Query parameter: readingListPosts — filters a GB Query Loop to show only
 *   the current user's bookmarked posts.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cotlas_reading_list_is_enabled' ) ) {
	function cotlas_reading_list_is_enabled() {
		return '0' !== (string) get_option( 'cotlas_reading_list_enabled', '1' );
	}
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 1. AJAX — toggle a bookmark (logged-in users only)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_toggle_bookmark', 'cotlas_ajax_toggle_bookmark' );
/**
 * Toggle a post ID in/out of the current user's reading list.
 */
function cotlas_ajax_toggle_bookmark() {
	if ( ! cotlas_reading_list_is_enabled() ) {
		wp_send_json_error( 'Reading list is disabled.' );
	}

	check_ajax_referer( 'cotlas_rl_nonce', 'nonce' );

	$post_id = absint( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
	if ( ! $post_id || ! get_post( $post_id ) ) {
		wp_send_json_error( 'Invalid post.' );
	}

	$user_id    = get_current_user_id();
	$list       = get_user_meta( $user_id, 'cotlas_reading_list', true );
	$list       = is_array( $list ) ? array_map( 'absint', $list ) : array();
	$bookmarked = false;

	if ( in_array( $post_id, $list, true ) ) {
		$list = array_values( array_diff( $list, array( $post_id ) ) );
	} else {
		$list[]     = $post_id;
		$bookmarked = true;
	}

	update_user_meta( $user_id, 'cotlas_reading_list', $list );

	wp_send_json_success(
		array(
			'bookmarked' => $bookmarked,
			'post_id'    => $post_id,
			'list'       => array_values( $list ),
		)
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 2. AJAX — get all bookmarks for current user (logged-in only)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_get_bookmarks', 'cotlas_ajax_get_bookmarks' );
/**
 * Return the complete reading list for the current user.
 */
function cotlas_ajax_get_bookmarks() {
	if ( ! cotlas_reading_list_is_enabled() ) {
		wp_send_json_error( 'Reading list is disabled.' );
	}

	check_ajax_referer( 'cotlas_rl_nonce', 'nonce' );

	$list = get_user_meta( get_current_user_id(), 'cotlas_reading_list', true );
	$list = is_array( $list ) ? array_map( 'absint', $list ) : array();

	wp_send_json_success( array( 'list' => array_values( $list ) ) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 3. GB QUERY LOOP FILTER — readingListPosts parameter
 *
 * Logged-in : reads user_meta.
 * Guests    : reads the cotlas_rl cookie (written by the JS layer).
 * ═══════════════════════════════════════════════════════════════════════════ */

add_filter( 'generateblocks_query_loop_args', 'cotlas_apply_reading_list_query', 10, 2 );
/**
 * Filter GB Query Loop args when the readingListPosts parameter is set.
 *
 * @param array $query_args  Current WP_Query args.
 * @param array $attributes  GB block attributes.
 * @return array
 */
function cotlas_apply_reading_list_query( $query_args, $attributes ) {
	if ( empty( $query_args['readingListPosts'] ) ) {
		return $query_args;
	}

	unset( $query_args['readingListPosts'] );

	if ( ! cotlas_reading_list_is_enabled() ) {
		$query_args['post__in'] = array( 0 );
		return $query_args;
	}

	$post_ids = array();

	if ( is_user_logged_in() ) {
		$saved    = get_user_meta( get_current_user_id(), 'cotlas_reading_list', true );
		$post_ids = is_array( $saved ) ? array_map( 'absint', $saved ) : array();
	} elseif ( ! empty( $_COOKIE['cotlas_rl'] ) ) {
		$raw      = sanitize_text_field( wp_unslash( $_COOKIE['cotlas_rl'] ) );
		$parts    = explode( ',', $raw );
		$post_ids = array_values( array_filter( array_map( 'absint', $parts ) ) );
	}

	if ( empty( $post_ids ) ) {
		// Return an empty result set when the reading list is empty.
		$query_args['post__in'] = array( 0 );
	} else {
		$query_args['post__in'] = $post_ids;
		$query_args['orderby']  = 'post__in'; // preserve bookmark order
	}

	return $query_args;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 4. SHORTCODE — [cotlas_bookmark]
 *
 * Usage inside a GB Query Loop or single post template:
 *   [cotlas_bookmark]
 *   [cotlas_bookmark size="34" class="my-btn"]
 * ═══════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'cotlas_bookmark', 'cotlas_bookmark_shortcode' );
/**
 * Render the bookmark toggle button.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML button.
 */
function cotlas_bookmark_shortcode( $atts ) {
	if ( ! cotlas_reading_list_is_enabled() ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'class'   => '',
			'size'    => '34',
			'post_id' => 0, // internal: lets dynamic tag callbacks pass the correct ID directly
		),
		$atts,
		'cotlas_bookmark'
	);

	$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$size      = absint( $atts['size'] );
	$extra     = ! empty( $atts['class'] ) ? ' ' . esc_attr( $atts['class'] ) : '';
	$logged_in = is_user_logged_in() ? 'true' : 'false';
	$nonce     = is_user_logged_in() ? wp_create_nonce( 'cotlas_rl_nonce' ) : '';
	$ajax_url  = esc_url( admin_url( 'admin-ajax.php' ) );

	return sprintf(
		'<button type="button"
			class="cotlas-bookmark-btn%s"
			data-post-id="%d"
			data-logged-in="%s"
			data-nonce="%s"
			data-ajax-url="%s"
			aria-label="%s"
			title="%s"
			style="width:%dpx;height:%dpx;padding:8px;border-radius:99px;background:var(--wp--preset--color--shadcn-accent,#f1f5f9);border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background .2s,color .2s;color:hsl(215,14%%,34%%);flex-shrink:0;"
		><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" /></svg></button>',
		$extra,
		$post_id,
		$logged_in,
		$nonce,
		$ajax_url,
		esc_attr__( 'Add to reading list', 'cotlas-admin' ),
		esc_attr__( 'Add to reading list', 'cotlas-admin' ),
		$size,
		$size
	);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * 5. FRONTEND ASSETS — CSS + inline JS
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_enqueue_scripts', 'cotlas_reading_list_assets' );
/**
 * Enqueue reading list styles and inline JS.
 */
function cotlas_reading_list_assets() {
	if ( ! cotlas_reading_list_is_enabled() ) {
		return;
	}

	wp_add_inline_style( 'wp-block-library', cotlas_rl_css() );

	wp_register_script( 'cotlas-reading-list', false, array(), false, true );
	wp_enqueue_script( 'cotlas-reading-list' );
	wp_add_inline_script( 'cotlas-reading-list', cotlas_rl_js() );
}

/**
 * Reading list button CSS.
 *
 * @return string
 */
function cotlas_rl_css() {
	return '
.cotlas-bookmark-btn { outline-offset: 2px; vertical-align: middle; }
.cotlas-bookmark-btn:focus-visible { outline: 2px solid #2271b1; }
.cotlas-bookmark-btn.is-bookmarked svg { fill: #1a1a1a !important; stroke: #1a1a1a !important; }
.cotlas-bookmark-btn:hover { opacity: .8; }
';
}

/**
 * Reading list frontend JS (localStorage + cookie + optional AJAX for logged-in users).
 *
 * @return string
 */
function cotlas_rl_js() {
	$logged_in = is_user_logged_in() ? 'true' : 'false';

	return '(function () {
"use strict";
var RL_KEY       = "cotlas_reading_list";
var RL_COOKIE    = "cotlas_rl";
var IS_LOGGED_IN = ' . $logged_in . ';

/* ── localStorage helpers ─────────────────────────────────────────── */

function getLocalList() {
	try { return JSON.parse( localStorage.getItem( RL_KEY ) || "[]" ); }
	catch (e) { return []; }
}

function saveLocalList( list ) {
	try { localStorage.setItem( RL_KEY, JSON.stringify( list ) ); }
	catch (e) {}
	/* Write first-party cookie so PHP query filter can read on next request */
	var val = list.join( "," );
	var exp = new Date( Date.now() + 365 * 86400 * 1000 ).toUTCString();
	document.cookie = RL_COOKIE + "=" + encodeURIComponent( val ) +
		";expires=" + exp + ";path=/;SameSite=Lax";
}

/* ── Resolve the real post ID — DOM walk first, data-post-id fallback */

function resolvePostId( btn ) {
	/* Check cache first */
	var cached = parseInt( btn.dataset.resolvedId, 10 );
	if ( cached > 0 ) { return cached; }

	/* Always walk the DOM: GB loop items carry post-{n} on their wrapper.
	   data-post-id from PHP may be the page ID, not the loop post ID. */
	var el = btn.parentElement;
	while ( el ) {
		var cls = ( typeof el.className === "string" ) ? el.className : "";
		var m = cls.match( /\bpost-(\d+)\b/ );
		if ( m ) {
			var id = parseInt( m[1], 10 );
			if ( id > 0 ) {
				btn.dataset.resolvedId = id; /* cache */
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

/* ── Update all bookmark buttons for a given post ID ──────────────── */

function setButtonState( postId, bookmarked ) {
	/* Match resolved and unresolved buttons for this post ID */
	document.querySelectorAll( ".cotlas-bookmark-btn" ).forEach( function (btn) {
		if ( resolvePostId( btn ) !== postId ) { return; }
		if ( bookmarked ) {
			btn.classList.add( "is-bookmarked" );
			btn.setAttribute( "aria-label", "Remove from reading list" );
			btn.setAttribute( "title",      "Remove from reading list" );
		} else {
			btn.classList.remove( "is-bookmarked" );
			btn.setAttribute( "aria-label", "Add to reading list" );
			btn.setAttribute( "title",      "Add to reading list" );
		}
	} );
}

/* ── Initialise button states from localStorage ───────────────────── */

function initStates() {
	var list = getLocalList();
	document.querySelectorAll( ".cotlas-bookmark-btn" ).forEach( function (btn) {
		var id = resolvePostId( btn );
		if ( ! id ) { return; }
		if ( list.indexOf( id ) !== -1 ) {
			btn.classList.add( "is-bookmarked" );
		} else {
			btn.classList.remove( "is-bookmarked" );
		}
	} );
}

/* ── AJAX helper ──────────────────────────────────────────────────── */

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

/* ── Sync server list on load (logged-in only) ────────────────────── */

function syncFromServer() {
	var btn = document.querySelector( ".cotlas-bookmark-btn[data-logged-in=\"true\"]" );
	if ( ! btn ) return;
	ajaxPost( "cotlas_get_bookmarks",
		{ nonce: btn.dataset.nonce, ajaxUrl: btn.dataset.ajaxUrl },
		function (resp) {
			if ( ! resp.success ) return;
			var serverList = resp.data.list.map( Number );
			saveLocalList( serverList );
			/* First mark all server-bookmarked items */
			serverList.forEach( function (id) { setButtonState( id, true ); } );
			/* Then un-mark any that fell off the server list */
			document.querySelectorAll( ".cotlas-bookmark-btn.is-bookmarked" )
				.forEach( function (b) {
					var id = parseInt( b.dataset.postId, 10 );
					if ( serverList.indexOf( id ) === -1 ) { setButtonState( id, false ); }
				} );
		}
	);
}

/* ── Click handler ────────────────────────────────────────────────── */

document.addEventListener( "click", function (e) {
	var btn = e.target.closest( ".cotlas-bookmark-btn" );
	if ( ! btn ) return;
	e.preventDefault();

	var postId = resolvePostId( btn );
	if ( ! postId ) { return; }

	if ( IS_LOGGED_IN ) {
		ajaxPost( "cotlas_toggle_bookmark",
			{ post_id: postId, nonce: btn.dataset.nonce, ajaxUrl: btn.dataset.ajaxUrl },
			function (resp) {
				if ( ! resp.success ) return;
				saveLocalList( resp.data.list.map( Number ) );
				setButtonState( postId, resp.data.bookmarked );
			}
		);
	} else {
		var list       = getLocalList();
		var idx        = list.indexOf( postId );
		var bookmarked = false;
		if ( idx !== -1 ) {
			list.splice( idx, 1 );
		} else {
			list.push( postId );
			bookmarked = true;
		}
		saveLocalList( list );
		setButtonState( postId, bookmarked );
	}
} );

/* ── Boot ─────────────────────────────────────────────────────────── */

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
