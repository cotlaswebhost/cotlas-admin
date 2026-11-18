<?php
/**
 * Site setup export / import: menus, widgets and Customizer settings of the
 * active theme. Separate from the plugin-settings export.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collect the site setup for export.
 *
 * @return array
 */
function cotlas_site_export_data() {
	$locations = get_nav_menu_locations();
	$menus     = array();

	foreach ( (array) wp_get_nav_menus() as $menu ) {
		$menu_items = wp_get_nav_menu_items( $menu->term_id );
		$positions  = array();
		$items      = array();

		if ( is_array( $menu_items ) ) {
			foreach ( $menu_items as $item ) {
				$positions[ (int) $item->ID ] = (int) $item->menu_order;
			}

			foreach ( $menu_items as $item ) {
				$items[] = array(
					'title'           => $item->title,
					'url'             => $item->url,
					'position'        => (int) $item->menu_order,
					'parent_position' => isset( $positions[ (int) $item->menu_item_parent ] ) ? $positions[ (int) $item->menu_item_parent ] : 0,
					'type'            => $item->type,
					'object'          => $item->object,
					'object_id'       => (int) $item->object_id,
					'target'          => $item->target,
					'classes'         => is_array( $item->classes ) ? array_values( array_filter( $item->classes ) ) : array(),
					'xfn'             => $item->xfn,
					'attr_title'      => $item->attr_title,
					'description'     => $item->description,
					'status'          => $item->post_status,
				);
			}
		}

		$menus[] = array(
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'term_id'   => (int) $menu->term_id,
			'locations' => array_values( array_keys( $locations, (int) $menu->term_id, true ) ),
			'items'     => $items,
		);
	}

	$widget_options = array();
	foreach ( wp_load_alloptions() as $option_name => $option_value ) {
		if ( preg_match( '/^widget_[A-Za-z0-9_-]+$/', $option_name ) ) {
			$widget_options[ $option_name ] = $option_value;
		}
	}

	$stylesheet = get_stylesheet();

	return array(
		'format'     => 'cotlas-site-export',
		'version'    => 1,
		'generated'  => gmdate( 'c' ),
		'site_url'   => home_url(),
		'theme'      => $stylesheet,
		'menus'      => $menus,
		'widgets'    => array(
			'sidebars_widgets' => get_option( 'sidebars_widgets', array() ),
			'widget_options'   => $widget_options,
		),
		'customizer' => array(
			'theme' => $stylesheet,
			'mods'  => get_option( 'theme_mods_' . $stylesheet, array() ),
		),
	);
}

/**
 * Stream the export file.
 */
function cotlas_handle_site_export_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}

	check_admin_referer( 'cotlas_site_export' );

	$json = wp_json_encode(
		cotlas_site_export_data(),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="cotlas-site-export-' . gmdate( 'Ymd-His' ) . '.json"' );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_cotlas_site_export', 'cotlas_handle_site_export_download' );

/**
 * Import menu items for a menu.
 *
 * @param int   $menu_id Menu term ID.
 * @param array $items   Exported items.
 * @return int Number of created items.
 */
function cotlas_site_import_menu_items( $menu_id, $items ) {
	$created      = 0;
	$position_map = array( 0 => 0 );
	$items        = is_array( $items ) ? $items : array();

	// Older exports stored no positions; fall back to the file order.
	$has_positions = false;
	foreach ( $items as $item ) {
		if ( (int) ( $item['position'] ?? 0 ) > 0 ) {
			$has_positions = true;
			break;
		}
	}
	if ( ! $has_positions ) {
		$position = 1;
		foreach ( $items as $index => $item ) {
			$items[ $index ]['position'] = $position;
			$position++;
		}
	}

	usort(
		$items,
		function ( $a, $b ) {
			return (int) ( $a['position'] ?? 0 ) <=> (int) ( $b['position'] ?? 0 );
		}
	);

	foreach ( $items as $item ) {
		$position    = (int) ( $item['position'] ?? 0 );
		$parent_pos  = (int) ( $item['parent_position'] ?? 0 );
		$parent_id   = $position_map[ $parent_pos ] ?? 0;
		$status      = in_array( ( $item['status'] ?? 'publish' ), array( 'publish', 'draft' ), true ) ? $item['status'] : 'publish';
		$classes     = array_filter( array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? array() ) ) );

		$new_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'       => sanitize_text_field( $item['title'] ?? '' ),
				'menu-item-url'         => esc_url_raw( $item['url'] ?? '' ),
				'menu-item-status'      => $status,
				'menu-item-type'        => sanitize_key( $item['type'] ?? 'custom' ),
				'menu-item-object'      => sanitize_key( $item['object'] ?? '' ),
				'menu-item-object-id'   => absint( $item['object_id'] ?? 0 ),
				'menu-item-target'      => sanitize_text_field( $item['target'] ?? '' ),
				'menu-item-classes'     => implode( ' ', $classes ),
				'menu-item-xfn'         => sanitize_text_field( $item['xfn'] ?? '' ),
				'menu-item-attr-title'  => sanitize_text_field( $item['attr_title'] ?? '' ),
				'menu-item-description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'menu-item-parent-id'   => $parent_id,
				'menu-item-position'    => $position,
			)
		);

		if ( ! is_wp_error( $new_id ) && $new_id ) {
			if ( $position > 0 ) {
				$position_map[ $position ] = (int) $new_id;
			}
			$created++;
		}
	}

	return $created;
}

/**
 * Import a site setup array.
 *
 * @param mixed $data Decoded export file.
 * @return array Result counts and warnings.
 */
function cotlas_site_import_data( $data ) {
	$result = array(
		'menus'            => 0,
		'menus_cleared'    => 0,
		'menu_items'       => 0,
		'widgets'          => 0,
		'widgets_cleared'  => 0,
		'sidebar_widgets'  => 0,
		'customizer'       => 0,
		'warnings'         => array(),
	);

	if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== 'cotlas-site-export' ) {
		$result['fatal'] = __( 'This file is not a Cotlas site setup export.', 'cotlas-admin' );
		return $result;
	}

	// Customizer first: menus restore nav_menu_locations afterwards.
	if ( ! empty( $data['customizer'] ) && is_array( $data['customizer'] ) ) {
		$source_theme = sanitize_text_field( $data['customizer']['theme'] ?? '' );
		$mods         = $data['customizer']['mods'] ?? null;

		if ( is_array( $mods ) ) {
			if ( '' === $source_theme || $source_theme === get_stylesheet() ) {
				unset( $mods['nav_menu_locations'], $mods['custom_css_post_id'] );
				update_option( 'theme_mods_' . get_stylesheet(), $mods );
				$result['customizer'] = 1;
			} else {
				$result['warnings'][] = sprintf(
					/* translators: 1: source theme, 2: active theme */
					__( 'Customizer settings skipped: the file was exported from "%1$s" but the active theme is "%2$s".', 'cotlas-admin' ),
					$source_theme,
					get_stylesheet()
				);
			}
		}
	}

	// Widgets: clear existing widget options and assignments, then import.
	if ( isset( $data['widgets'] ) && is_array( $data['widgets'] ) ) {
		global $wpdb;

		$existing_widget_options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $existing_widget_options as $option_name ) {
			if ( preg_match( '/^widget_[A-Za-z0-9_-]+$/', $option_name ) ) {
				delete_option( $option_name );
				$result['widgets_cleared']++;
			}
		}

		$widget_options = $data['widgets']['widget_options'] ?? array();
		if ( is_array( $widget_options ) ) {
			foreach ( $widget_options as $option_name => $option_value ) {
				if ( ! is_string( $option_name ) || ! preg_match( '/^widget_[A-Za-z0-9_-]+$/', $option_name ) ) {
					continue;
				}
				update_option( $option_name, $option_value );
				$result['widgets']++;
			}
		}

		$sidebars   = $data['widgets']['sidebars_widgets'] ?? array();
		$registered = isset( $GLOBALS['wp_registered_sidebars'] ) ? (array) $GLOBALS['wp_registered_sidebars'] : array();

		// Start from empty registered sidebars so nothing from the current site remains.
		$current = array();
		foreach ( array_keys( $registered ) as $sidebar_id ) {
			$current[ $sidebar_id ] = array();
		}
		$current['wp_inactive_widgets'] = array();

		if ( is_array( $sidebars ) ) {
			foreach ( $sidebars as $sidebar => $widgets ) {
				if ( ! is_string( $sidebar ) || ! is_array( $widgets ) ) {
					continue;
				}
				if ( 'wp_inactive_widgets' !== $sidebar && ! isset( $registered[ $sidebar ] ) ) {
					continue;
				}
				$ids = array_values( array_map( 'sanitize_text_field', $widgets ) );
				$current[ $sidebar ] = $ids;
				$result['sidebar_widgets'] += count( $ids );
			}
		}

		update_option( 'sidebars_widgets', $current );
	}

	// Menus: clear all existing menus, then recreate the exported ones.
	if ( isset( $data['menus'] ) && is_array( $data['menus'] ) ) {
		foreach ( (array) wp_get_nav_menus() as $existing_menu ) {
			$deleted = wp_delete_nav_menu( $existing_menu->term_id );
			if ( $deleted && ! is_wp_error( $deleted ) ) {
				$result['menus_cleared']++;
			}
		}

		set_theme_mod( 'nav_menu_locations', array() );

		$location_map = array();
		$menu_id_map  = array();
		$registered   = get_registered_nav_menus();

		foreach ( $data['menus'] as $menu ) {
			if ( ! is_array( $menu ) ) {
				continue;
			}

			$name = sanitize_text_field( $menu['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				$result['warnings'][] = sprintf(
					/* translators: 1: menu name, 2: error message */
					__( 'Menu "%1$s" could not be created: %2$s', 'cotlas-admin' ),
					$name,
					$menu_id->get_error_message()
				);
				continue;
			}

			$result['menus']++;
			$result['menu_items'] += cotlas_site_import_menu_items( $menu_id, (array) ( $menu['items'] ?? array() ) );

			if ( ! empty( $menu['term_id'] ) ) {
				$menu_id_map[ (int) $menu['term_id'] ] = (int) $menu_id;
			}

			foreach ( (array) ( $menu['locations'] ?? array() ) as $location ) {
				$location = sanitize_key( $location );
				if ( '' !== $location && ( empty( $registered ) || isset( $registered[ $location ] ) ) ) {
					$location_map[ $location ] = (int) $menu_id;
				}
			}
		}

		// Point navigation-menu widgets at the newly created menus.
		if ( $menu_id_map ) {
			$nav_widgets = get_option( 'widget_nav_menu', array() );
			if ( is_array( $nav_widgets ) ) {
				$changed = false;
				foreach ( $nav_widgets as $instance_id => $instance ) {
					if ( is_array( $instance ) && ! empty( $instance['nav_menu'] ) && isset( $menu_id_map[ (int) $instance['nav_menu'] ] ) ) {
						$nav_widgets[ $instance_id ]['nav_menu'] = $menu_id_map[ (int) $instance['nav_menu'] ];
						$changed = true;
					}
				}
				if ( $changed ) {
					update_option( 'widget_nav_menu', $nav_widgets );
				}
			}
		}

		set_theme_mod( 'nav_menu_locations', $location_map );
	}

	return $result;
}
