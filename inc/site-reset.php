<?php
/**
 * Site reset tools: full reset and design-only reset that keeps content.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request may run a reset.
 *
 * @return bool
 */
function cotlas_site_reset_allowed() {
	return current_user_can( 'manage_options' ) && ! is_multisite();
}

/**
 * Options that survive a full reset so the site stays reachable and usable.
 *
 * @return array
 */
function cotlas_site_reset_keep_options() {
	global $wpdb;

	return array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'blog_charset',
		'admin_email',
		'users_can_register',
		'default_role',
		'timezone_string',
		'gmt_offset',
		'date_format',
		'time_format',
		'start_of_week',
		'WPLANG',
		'template',
		'stylesheet',
		'current_theme',
		'active_plugins',
		'active_sitewide_plugins',
		'db_version',
		'db_upgraded',
		'cron',
		'permalink_structure',
		'category_base',
		'tag_base',
		'upload_path',
		'upload_url_path',
		'uploads_use_yearmonth_folders',
		'blog_public',
		$wpdb->prefix . 'user_roles',
	);
}

/**
 * Delete all navigation menus and any orphaned menu items.
 *
 * @return int Number of menus deleted.
 */
function cotlas_site_reset_menus() {
	$deleted = 0;

	foreach ( (array) wp_get_nav_menus() as $menu ) {
		$result = wp_delete_nav_menu( $menu->term_id );
		if ( $result && ! is_wp_error( $result ) ) {
			$deleted++;
		}
	}

	$orphans = get_posts(
		array(
			'post_type'      => 'nav_menu_item',
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( (array) $orphans as $orphan_id ) {
		wp_delete_post( $orphan_id, true );
	}

	return $deleted;
}

/**
 * Remove every widget option and empty the sidebar assignments.
 *
 * @return int Number of widget options deleted.
 */
function cotlas_site_reset_widgets() {
	global $wpdb;

	$deleted = 0;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'" );

	foreach ( (array) $names as $option_name ) {
		if ( preg_match( '/^widget_[A-Za-z0-9_-]+$/', $option_name ) ) {
			delete_option( $option_name );
			$deleted++;
		}
	}

	$registered = isset( $GLOBALS['wp_registered_sidebars'] ) ? (array) $GLOBALS['wp_registered_sidebars'] : array();
	$sidebars   = array();
	foreach ( array_keys( $registered ) as $sidebar_id ) {
		$sidebars[ $sidebar_id ] = array();
	}
	$sidebars['wp_inactive_widgets'] = array();

	update_option( 'sidebars_widgets', $sidebars );

	return $deleted;
}

/**
 * Remove all Customizer settings, Additional CSS, and block global styles.
 *
 * @return int Number of theme_mods options deleted.
 */
function cotlas_site_reset_customizer() {
	global $wpdb;

	$deleted = 0;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'theme_mods\\_%'" );

	foreach ( (array) $names as $option_name ) {
		delete_option( $option_name );
		$deleted++;
	}

	foreach ( array( 'custom_css', 'wp_global_styles', 'wp_global_styles_revision' ) as $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}
		$ids = get_posts(
			array(
				'post_type'     => $post_type,
				'post_status'   => 'any',
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);
		foreach ( (array) $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	return $deleted;
}

/**
 * Clear cached/transient data.
 */
function cotlas_site_reset_caches() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'" );

	wp_cache_flush();
}

/**
 * Remove everything inside the uploads directory.
 */
function cotlas_site_reset_uploads() {
	$uploads = wp_get_upload_dir();
	$base    = $uploads['basedir'];

	if ( empty( $base ) || ! is_dir( $base ) ) {
		return;
	}

	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
	} catch ( Exception $e ) {
		// Ignore unreadable paths; attachments were already removed individually.
	}
}

/**
 * Design reset: keep all content and media, remove theme design and config.
 *
 * @return array
 */
function cotlas_site_reset_design() {
	if ( ! cotlas_site_reset_allowed() ) {
		return array( 'fatal' => __( 'Site reset is not available in this environment.', 'cotlas-admin' ) );
	}

	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$result = array(
		'menus'      => cotlas_site_reset_menus(),
		'widgets'    => cotlas_site_reset_widgets(),
		'customizer' => cotlas_site_reset_customizer(),
		'caches'     => 1,
	);

	cotlas_site_reset_caches();
	flush_rewrite_rules( false );

	return $result;
}

/**
 * Full reset: wipe content, media, users, terms and options.
 *
 * @return array
 */
function cotlas_site_reset_full() {
	if ( ! cotlas_site_reset_allowed() ) {
		return array( 'fatal' => __( 'Site reset is not available on multisite.', 'cotlas-admin' ) );
	}

	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/post.php';
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$result = array(
		'attachments' => 0,
		'posts'       => 0,
		'comments'    => 0,
		'users'       => 0,
		'options'     => 0,
	);

	// Delete attachments (and their files) first so media is fully removed.
	$attachments = get_posts(
		array(
			'post_type'     => 'attachment',
			'post_status'   => 'any',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);
	foreach ( (array) $attachments as $attachment_id ) {
		if ( wp_delete_attachment( $attachment_id, true ) ) {
			$result['attachments']++;
		}
	}

	$result['posts']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$result['comments'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$wpdb->query( "DELETE FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->comments}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->commentmeta}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->links}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->term_relationships}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->terms}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->termmeta}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Remove all users except the one running the reset.
	$users = get_users(
		array(
			'exclude' => array( get_current_user_id() ),
			'fields'  => 'ID',
		)
	);
	foreach ( (array) $users as $user_id ) {
		if ( wp_delete_user( $user_id ) ) {
			$result['users']++;
		}
	}

	// Reset options, keeping only the essentials needed to reach the site.
	$keep         = cotlas_site_reset_keep_options();
	$placeholders = implode( ',', array_fill( 0, count( $keep ), '%s' ) );
	$where        = "option_name NOT IN ({$placeholders})";

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$result['options'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE {$where}", ...$keep ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE {$where}", ...$keep ) );
	// phpcs:enable

	// Recreate the default category so new posts work immediately.
	$default = wp_insert_term( 'Uncategorized', 'category' );
	if ( ! is_wp_error( $default ) ) {
		update_option( 'default_category', (int) $default['term_id'] );
	}

	cotlas_site_reset_uploads();
	wp_cache_flush();
	flush_rewrite_rules( false );

	return $result;
}
