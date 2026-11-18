<?php
/**
 * Site setup export / import: navigation menus, widgets and Customizer mods.
 *
 * Separate from the plugin-settings export. Each part uses the format the
 * WordPress ecosystem already understands so files interoperate with the
 * Widget Importer & Exporter (.wie) and Customizer Export/Import (.dat)
 * plugins.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════════
 * EXPORT: MENUS
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Collect navigation menus for export.
 *
 * @return array
 */
function cotlas_export_menus_data() {
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

	return array(
		'format'    => 'cotlas-menus-export',
		'version'   => 2,
		'generated' => gmdate( 'c' ),
		'site_url'  => home_url(),
		'menus'     => $menus,
	);
}

/**
 * Stream the menus export file.
 */
function cotlas_handle_export_menus() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}

	check_admin_referer( 'cotlas_site_export' );

	cotlas_stream_download(
		wp_json_encode( cotlas_export_menus_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		'cotlas-menus-' . gmdate( 'Ymd-His' ) . '.json',
		'application/json'
	);
}
add_action( 'admin_post_cotlas_export_menus', 'cotlas_handle_export_menus' );

/* ═══════════════════════════════════════════════════════════════════════════
 * EXPORT: WIDGETS (.wie)
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Registered widget id_bases for the current site.
 *
 * @return array<string,string> id_base => widget name.
 */
function cotlas_registered_widget_bases() {
	$bases = array();

	if ( isset( $GLOBALS['wp_registered_widgets'] ) && is_array( $GLOBALS['wp_registered_widgets'] ) ) {
		foreach ( $GLOBALS['wp_registered_widgets'] as $widget_id => $widget ) {
			$id_base = preg_replace( '/-[0-9]+$/', '', (string) $widget_id );

			if ( '' === $id_base ) {
				continue;
			}

			$name = '';
			if ( isset( $widget['callback'][0] ) && is_object( $widget['callback'][0] ) && isset( $widget['callback'][0]->name ) ) {
				$name = (string) $widget['callback'][0]->name;
			} elseif ( isset( $widget['name'] ) ) {
				$name = (string) $widget['name'];
			}

			$bases[ $id_base ] = $name;
		}
	}

	return $bases;
}

/**
 * Build widget export data in Widget Importer & Exporter (.wie) format.
 *
 * The file is a JSON object keyed by sidebar ID, each holding
 * { "id_base-N": { instance } }.
 *
 * @return array
 */
function cotlas_export_widgets_data() {
	$bases     = cotlas_registered_widget_bases();
	$instances = array();

	foreach ( array_keys( $bases ) as $id_base ) {
		$option = get_option( 'widget_' . $id_base );

		if ( ! is_array( $option ) ) {
			continue;
		}

		foreach ( $option as $instance_id => $instance_data ) {
			if ( ! is_numeric( $instance_id ) ) {
				continue;
			}

			$instances[ $id_base . '-' . $instance_id ] = $instance_data;
		}
	}

	$sidebars_widgets = get_option( 'sidebars_widgets', array() );
	$export           = array();

	foreach ( (array) $sidebars_widgets as $sidebar_id => $widget_ids ) {
		if ( 'array_version' === $sidebar_id || 'wp_inactive_widgets' === $sidebar_id || ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
			continue;
		}

		foreach ( $widget_ids as $widget_id ) {
			if ( isset( $instances[ $widget_id ] ) ) {
				$export[ $sidebar_id ][ $widget_id ] = $instances[ $widget_id ];
			}
		}
	}

	return $export;
}

/**
 * Stream the widgets export file.
 */
function cotlas_handle_export_widgets() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}

	check_admin_referer( 'cotlas_site_export' );

	$host     = wp_parse_url( home_url(), PHP_URL_HOST );
	$filename = ( $host ? $host : 'site' ) . '-widgets.wie';

	cotlas_stream_download(
		wp_json_encode( cotlas_export_widgets_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		$filename,
		'application/octet-stream'
	);
}
add_action( 'admin_post_cotlas_export_widgets', 'cotlas_handle_export_widgets' );

/* ═══════════════════════════════════════════════════════════════════════════
 * EXPORT: CUSTOMIZER (.dat)
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Option keys that hold theme design settings on this stack.
 *
 * GeneratePress stores its layout configuration as an option rather than a
 * theme mod, so it would otherwise be missed by a theme-mod-only export.
 *
 * @return string[]
 */
function cotlas_customizer_export_option_keys() {
	$candidates = array(
		'generate_settings',
		'generate_spacing_settings',
		'generate_font_settings',
		'generate_colors_settings',
		'generate_blog_settings',
		'generate_menu_plus_settings',
		'generate_secondary_nav_settings',
	);

	$keys = array();

	foreach ( $candidates as $key ) {
		if ( false !== get_option( $key, false ) ) {
			$keys[] = $key;
		}
	}

	/**
	 * Filter the option keys included in the Customizer export.
	 *
	 * @param string[] $keys Option names.
	 */
	return (array) apply_filters( 'cotlas_customizer_export_option_keys', $keys );
}

/**
 * Build Customizer export data in Customizer Export/Import (.dat) format.
 *
 * @return array
 */
function cotlas_export_customizer_data() {
	$mods = get_theme_mods();

	if ( ! is_array( $mods ) ) {
		$mods = array();
	}

	// Handled by the menu exporter / site-specific; never portable.
	unset( $mods['nav_menu_locations'], $mods['custom_css_post_id'] );

	$options = array();

	foreach ( cotlas_customizer_export_option_keys() as $key ) {
		$options[ $key ] = get_option( $key );
	}

	$data = array(
		'template'   => get_template(),
		'stylesheet' => get_stylesheet(),
		'mods'       => $mods,
		'options'    => $options,
	);

	if ( function_exists( 'wp_get_custom_css' ) ) {
		$data['wp_css'] = wp_get_custom_css();
	}

	return $data;
}

/**
 * Stream the Customizer export file.
 */
function cotlas_handle_export_customizer() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}

	check_admin_referer( 'cotlas_site_export' );

	cotlas_stream_download(
		serialize( cotlas_export_customizer_data() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required by the .dat format.
		get_stylesheet() . '-export.dat',
		'application/octet-stream'
	);
}
add_action( 'admin_post_cotlas_export_customizer', 'cotlas_handle_export_customizer' );

/* ═══════════════════════════════════════════════════════════════════════════
 * EXPORT: ALL-IN-ONE (legacy JSON)
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Collect the whole site setup in one array.
 *
 * @return array
 */
function cotlas_site_export_data() {
	return array(
		'format'     => 'cotlas-site-export',
		'version'    => 2,
		'generated'  => gmdate( 'c' ),
		'site_url'   => home_url(),
		'theme'      => get_stylesheet(),
		'menus'      => cotlas_export_menus_data()['menus'],
		'widgets'    => cotlas_export_widgets_data(),
		'customizer' => cotlas_export_customizer_data(),
	);
}

/**
 * Stream the combined export file.
 */
function cotlas_handle_site_export_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}

	check_admin_referer( 'cotlas_site_export' );

	cotlas_stream_download(
		wp_json_encode( cotlas_site_export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		'cotlas-site-export-' . gmdate( 'Ymd-His' ) . '.json',
		'application/json'
	);
}
add_action( 'admin_post_cotlas_site_export', 'cotlas_handle_site_export_download' );

/**
 * Send a download and stop.
 *
 * @param string $body     File contents.
 * @param string $filename Download filename.
 * @param string $mime     Content type.
 */
function cotlas_stream_download( $body, $filename, $mime ) {
	nocache_headers();
	header( 'Content-Type: ' . $mime . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
	header( 'Content-Length: ' . strlen( $body ) );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File download.
	exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * IMPORT: ROUTER
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Import a file, choosing the handler from its extension.
 *
 * @param string $path Uploaded file path.
 * @param string $name Original filename.
 * @return array Result.
 */
function cotlas_site_import_file( $path, $name ) {
	$extension = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	$contents  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload.

	if ( '' === trim( $contents ) ) {
		return array( 'fatal' => __( 'The file is empty.', 'cotlas-admin' ) );
	}

	switch ( $extension ) {
		case 'wie':
			$data = json_decode( $contents, true );

			if ( ! is_array( $data ) ) {
				return array( 'fatal' => __( 'The .wie file is not valid JSON.', 'cotlas-admin' ) );
			}

			return cotlas_import_widgets_data( $data );

		case 'dat':
			$data = @unserialize( trim( $contents ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- .dat format.

			if ( ! is_array( $data ) ) {
				return array( 'fatal' => __( 'The .dat file is not a valid Customizer export.', 'cotlas-admin' ) );
			}

			return cotlas_import_customizer_data( $data );

		case 'json':
		default:
			$data = json_decode( $contents, true );

			if ( ! is_array( $data ) ) {
				return array( 'fatal' => __( 'The file is not valid JSON.', 'cotlas-admin' ) );
			}

			$format = isset( $data['format'] ) ? (string) $data['format'] : '';

			if ( 'cotlas-menus-export' === $format ) {
				return cotlas_import_menus_data( $data['menus'] ?? array() );
			}

			if ( 'cotlas-site-export' === $format ) {
				return cotlas_site_import_data( $data );
			}

			return array( 'fatal' => __( 'This file is not a Cotlas export. Expected a .wie, .dat, menus JSON, or site-export JSON file.', 'cotlas-admin' ) );
	}
}

/* ═══════════════════════════════════════════════════════════════════════════
 * IMPORT: MENUS
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Import menu items for a menu.
 *
 * @param int   $menu_id Menu term ID.
 * @param array $items   Exported items.
 * @return int Number of created items.
 */
function cotlas_site_import_menu_items( $menu_id, $items ) {
	require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

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
		$position   = (int) ( $item['position'] ?? 0 );
		$parent_pos = (int) ( $item['parent_position'] ?? 0 );
		$parent_id  = $position_map[ $parent_pos ] ?? 0;
		$status     = in_array( ( $item['status'] ?? 'publish' ), array( 'publish', 'draft' ), true ) ? $item['status'] : 'publish';
		$classes    = array_filter( array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? array() ) ) );

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
 * Import menus, replacing existing ones.
 *
 * @param array $menus Exported menu list.
 * @return array Result including the old→new menu ID map.
 */
function cotlas_import_menus_data( $menus ) {
	$result = array(
		'menus'         => 0,
		'menus_cleared' => 0,
		'menu_items'    => 0,
		'warnings'      => array(),
	);

	if ( ! is_array( $menus ) ) {
		$result['fatal'] = __( 'No menu data found in this file.', 'cotlas-admin' );
		return $result;
	}

	require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

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

	foreach ( $menus as $menu ) {
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

	set_theme_mod( 'nav_menu_locations', $location_map );

	$result['menu_id_map'] = $menu_id_map;

	return $result;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * IMPORT: WIDGETS (.wie)
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Run an imported widget instance through its own update()/sanitize path.
 *
 * @param array  $instance Widget instance.
 * @param string $id_base  Widget id_base.
 * @return array|false
 */
function cotlas_sanitize_widget_instance( $instance, $id_base ) {
	global $wp_widget_factory;

	if ( ! is_array( $instance ) ) {
		return $instance;
	}

	$widget_object = null;

	if ( isset( $wp_widget_factory->widgets ) && is_array( $wp_widget_factory->widgets ) ) {
		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( isset( $widget->id_base ) && $id_base === $widget->id_base ) {
				$widget_object = $widget;
				break;
			}
		}
	}

	if ( $widget_object instanceof WP_Widget ) {
		$new_instance = $instance;
		$old_instance = array();
		$instance     = $widget_object->update( $new_instance, $old_instance );

		return apply_filters( 'widget_update_callback', $instance, $new_instance, $old_instance, $widget_object );
	}

	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$instance = map_deep( $instance, 'wp_kses_post' );
	}

	return $instance;
}

/**
 * Point legacy-widget blocks at the newly created menu IDs and refresh their
 * signed instance payloads.
 *
 * The `encoded`/`hash` pair is signed with this site's salts, so a block
 * widget copied from another site renders empty until it is re-signed.
 *
 * @param string $content     Serialized block markup.
 * @param array  $menu_id_map Old → new menu term IDs.
 * @return string
 */
function cotlas_resign_legacy_widget_blocks( $content, $menu_id_map ) {
	if ( ! is_string( $content ) || false === strpos( $content, 'wp:legacy-widget' ) ) {
		return $content;
	}

	$blocks = parse_blocks( $content );

	$walk = function ( $items ) use ( &$walk, $menu_id_map ) {
		foreach ( $items as $index => $block ) {
			if ( is_array( $block ) && isset( $block['blockName'] ) && 'core/legacy-widget' === $block['blockName'] && is_array( $block['attrs'] ) ) {
				$attrs    = $block['attrs'];
				$instance = isset( $attrs['instance']['raw'] ) && is_array( $attrs['instance']['raw'] ) ? $attrs['instance']['raw'] : array();
				$id_base  = isset( $attrs['idBase'] ) ? (string) $attrs['idBase'] : '';

				if ( 'nav_menu' === $id_base && ! empty( $instance['nav_menu'] ) && isset( $menu_id_map[ (int) $instance['nav_menu'] ] ) ) {
					$instance['nav_menu'] = (int) $menu_id_map[ (int) $instance['nav_menu'] ];
				}

				$serialized = serialize( $instance ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Core block format.
				$encoded    = base64_encode( $serialized ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Core block format.

				$items[ $index ]['attrs']['instance'] = array(
					'encoded' => $encoded,
					'hash'    => wp_hash( $serialized ),
					'raw'     => $instance,
				);

				continue;
			}

			if ( is_array( $block ) && ! empty( $block['innerBlocks'] ) ) {
				$items[ $index ]['innerBlocks'] = $walk( $block['innerBlocks'] );
			}
		}

		return $items;
	};

	return serialize_blocks( $walk( $blocks ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Block serializer output.
}

/**
 * Import widgets from .wie data, appending to the current site.
 *
 * @param array $data Sidebar => { widget_id: instance } map.
 * @param array $menu_id_map Old → new menu term IDs, for nav_menu widgets.
 * @return array Result.
 */
function cotlas_import_widgets_data( $data, $menu_id_map = array() ) {
	global $wp_registered_sidebars;

	$result = array(
		'widgets'         => 0,
		'sidebar_widgets' => 0,
		'skipped'         => 0,
		'warnings'        => array(),
	);

	if ( ! is_array( $data ) || empty( $data ) ) {
		$result['fatal'] = __( 'The .wie file contains no widget data.', 'cotlas-admin' );
		return $result;
	}

	$bases = cotlas_registered_widget_bases();

	foreach ( $data as $sidebar_id => $widgets ) {
		if ( ! is_array( $widgets ) ) {
			continue;
		}

		if ( isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
			$use_sidebar_id = $sidebar_id;
		} elseif ( 'wp_inactive_widgets' === $sidebar_id ) {
			$use_sidebar_id = 'wp_inactive_widgets';
		} else {
			$use_sidebar_id       = 'wp_inactive_widgets';
			$result['warnings'][] = sprintf(
				/* translators: %s: sidebar ID from the file */
				__( 'Widget area "%s" does not exist in this theme; its widgets were imported to Inactive.', 'cotlas-admin' ),
				(string) $sidebar_id
			);
		}

		foreach ( $widgets as $widget_id => $instance ) {
			$id_base = preg_replace( '/-[0-9]+$/', '', (string) $widget_id );

			if ( '' === $id_base || ! isset( $bases[ $id_base ] ) ) {
				$result['skipped']++;
				continue;
			}

			// Round-trip through JSON so nested objects become arrays.
			$instance = json_decode( wp_json_encode( $instance ), true );

			if ( 'nav_menu' === $id_base && ! empty( $instance['nav_menu'] ) && isset( $menu_id_map[ (int) $instance['nav_menu'] ] ) ) {
				$instance['nav_menu'] = (int) $menu_id_map[ (int) $instance['nav_menu'] ];
			}

			if ( 'block' === $id_base && isset( $instance['content'] ) ) {
				$instance['content'] = cotlas_resign_legacy_widget_blocks( $instance['content'], $menu_id_map );
			}

			$instance = cotlas_sanitize_widget_instance( $instance, $id_base );

			if ( false === $instance ) {
				$result['skipped']++;
				continue;
			}

			$option = get_option( 'widget_' . $id_base );
			if ( ! is_array( $option ) ) {
				$option = array( '_multiwidget' => 1 );
			}

			// Skip exact duplicates already present in this sidebar.
			$sidebars_widgets = get_option( 'sidebars_widgets', array() );
			$in_sidebar       = isset( $sidebars_widgets[ $use_sidebar_id ] ) && is_array( $sidebars_widgets[ $use_sidebar_id ] ) ? $sidebars_widgets[ $use_sidebar_id ] : array();
			$duplicate        = false;

			foreach ( $option as $existing_id => $existing_instance ) {
				if ( ! is_numeric( $existing_id ) ) {
					continue;
				}

				if ( in_array( $id_base . '-' . $existing_id, $in_sidebar, true ) && $existing_instance === $instance ) {
					$duplicate = true;
					break;
				}
			}

			if ( $duplicate ) {
				$result['skipped']++;
				continue;
			}

			$option[] = $instance;
			end( $option );
			$new_id = key( $option );

			if ( '0' === (string) $new_id ) {
				$new_id                     = 1;
				$option[1]                  = $option[0];
				unset( $option[0] );
			}

			if ( isset( $option['_multiwidget'] ) ) {
				$multiwidget = $option['_multiwidget'];
				unset( $option['_multiwidget'] );
				$option['_multiwidget'] = $multiwidget;
			}

			update_option( 'widget_' . $id_base, $option );

			$sidebars_widgets                    = get_option( 'sidebars_widgets', array() );
			$sidebars_widgets                    = is_array( $sidebars_widgets ) ? $sidebars_widgets : array();
			$sidebars_widgets[ $use_sidebar_id ] = isset( $sidebars_widgets[ $use_sidebar_id ] ) && is_array( $sidebars_widgets[ $use_sidebar_id ] ) ? $sidebars_widgets[ $use_sidebar_id ] : array();
			$sidebars_widgets[ $use_sidebar_id ][] = $id_base . '-' . $new_id;

			update_option( 'sidebars_widgets', $sidebars_widgets );

			$result['widgets']++;
			$result['sidebar_widgets']++;
		}
	}

	return $result;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * IMPORT: CUSTOMIZER (.dat)
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Import Customizer settings from .dat data.
 *
 * @param array $data Decoded .dat payload.
 * @return array Result.
 */
function cotlas_import_customizer_data( $data ) {
	$result = array(
		'customizer' => 0,
		'options'    => 0,
		'warnings'   => array(),
	);

	$template = isset( $data['template'] ) ? (string) $data['template'] : '';

	if ( '' === $template || ! isset( $data['mods'] ) || ! is_array( $data['mods'] ) ) {
		$result['fatal'] = __( 'This .dat file is not a Customizer export.', 'cotlas-admin' );
		return $result;
	}

	if ( $template !== get_template() ) {
		$result['fatal'] = sprintf(
			/* translators: 1: template from file, 2: active parent theme */
			__( 'These settings were exported from the "%1$s" theme but this site uses "%2$s". Nothing was imported.', 'cotlas-admin' ),
			$template,
			get_template()
		);
		return $result;
	}

	$source_stylesheet = isset( $data['stylesheet'] ) ? (string) $data['stylesheet'] : '';

	if ( '' !== $source_stylesheet && $source_stylesheet !== get_stylesheet() ) {
		$result['warnings'][] = sprintf(
			/* translators: 1: stylesheet from file, 2: active child theme */
			__( 'Imported settings came from the "%1$s" theme; they were applied to "%2$s".', 'cotlas-admin' ),
			$source_stylesheet,
			get_stylesheet()
		);
	}

	$mods = $data['mods'];

	// Handled by the menu importer, or site-specific.
	unset( $mods['nav_menu_locations'], $mods['custom_css_post_id'] );

	// Keep the current logo when the referenced attachment is missing here.
	if ( isset( $mods['custom_logo'] ) && ! wp_attachment_is_image( (int) $mods['custom_logo'] ) ) {
		unset( $mods['custom_logo'] );
		$result['warnings'][] = __( 'Logo skipped: that media file does not exist on this site.', 'cotlas-admin' );
	}

	if ( is_array( $mods ) ) {
		foreach ( $mods as $key => $value ) {
			set_theme_mod( (string) $key, $value );
		}

		$result['customizer'] = 1;
	}

	if ( ! empty( $data['options'] ) && is_array( $data['options'] ) ) {
		foreach ( $data['options'] as $option_key => $option_value ) {
			$option_key = (string) $option_key;

			if ( '' === $option_key ) {
				continue;
			}

			update_option( $option_key, $option_value );
			$result['options']++;
		}
	}

	if ( function_exists( 'wp_update_custom_css_post' ) && ! empty( $data['wp_css'] ) && is_string( $data['wp_css'] ) ) {
		wp_update_custom_css_post( $data['wp_css'] );
	}

	do_action( 'customize_save_after' );

	return $result;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * IMPORT: ALL-IN-ONE
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Import a combined site export, in dependency order.
 *
 * Menus first so menu IDs exist, then widgets (which reference those IDs),
 * then Customizer settings.
 *
 * @param array $data Decoded export file.
 * @return array Result.
 */
function cotlas_site_import_data( $data ) {
	if ( ! is_array( $data ) ) {
		return array( 'fatal' => __( 'This file is not a Cotlas site setup export.', 'cotlas-admin' ) );
	}

	$format = isset( $data['format'] ) ? (string) $data['format'] : '';
	$legacy = isset( $data['widgets']['widget_options'] );

	if ( 'cotlas-site-export' !== $format && 'cotlas-menus-export' !== $format && ! $legacy ) {
		return array( 'fatal' => __( 'This file is not a Cotlas site setup export.', 'cotlas-admin' ) );
	}

	$result = array(
		'menus'           => 0,
		'menus_cleared'   => 0,
		'menu_items'      => 0,
		'widgets'         => 0,
		'sidebar_widgets' => 0,
		'skipped'         => 0,
		'customizer'      => 0,
		'options'         => 0,
		'warnings'        => array(),
	);

	$menu_id_map = array();

	if ( ! empty( $data['menus'] ) && is_array( $data['menus'] ) ) {
		$menus_result = cotlas_import_menus_data( $data['menus'] );

		$menu_id_map = $menus_result['menu_id_map'] ?? array();

		foreach ( array( 'menus', 'menus_cleared', 'menu_items' ) as $key ) {
			$result[ $key ] = $menus_result[ $key ] ?? 0;
		}

		$result['warnings'] = array_merge( $result['warnings'], $menus_result['warnings'] ?? array() );
	}

	if ( ! empty( $data['widgets'] ) && is_array( $data['widgets'] ) ) {
		$widgets = $data['widgets'];

		// Version 1 stored raw serialized option strings; re-save the whole
		// option array so it is never double-serialized.
		if ( isset( $widgets['widget_options'] ) && is_array( $widgets['widget_options'] ) ) {
			$restored = 0;

			foreach ( $widgets['widget_options'] as $option_name => $option_value ) {
				if ( ! is_string( $option_name ) || ! preg_match( '/^widget_[A-Za-z0-9_-]+$/', $option_name ) ) {
					continue;
				}

				$value = maybe_unserialize( $option_value );
				update_option( $option_name, is_array( $value ) ? $value : array() );
				$restored++;
			}

			$sidebars = isset( $widgets['sidebars_widgets'] ) && is_array( $widgets['sidebars_widgets'] ) ? $widgets['sidebars_widgets'] : array();
			$clean    = array();

			foreach ( $sidebars as $sidebar => $ids ) {
				if ( 'array_version' === $sidebar || ! is_array( $ids ) ) {
					continue;
				}
				$clean[ $sidebar ] = array_values( array_map( 'sanitize_text_field', $ids ) );
			}

			update_option( 'sidebars_widgets', $clean );
			$result['widgets']         = $restored;
			$result['sidebar_widgets'] = (int) array_sum( array_map( 'count', $clean ) );
		} else {
			$widgets_result = cotlas_import_widgets_data( $widgets, $menu_id_map );

			foreach ( array( 'widgets', 'sidebar_widgets', 'skipped' ) as $key ) {
				$result[ $key ] = $widgets_result[ $key ] ?? 0;
			}

			$result['warnings'] = array_merge( $result['warnings'], $widgets_result['warnings'] ?? array() );
		}
	}

	if ( ! empty( $data['customizer'] ) && is_array( $data['customizer'] ) ) {
		$customizer = $data['customizer'];

		// Version 1 wrapped mods as { theme, mods } and lacked the template key.
		if ( isset( $customizer['mods'] ) && ! isset( $customizer['template'] ) ) {
			$customizer = array(
				'template'   => get_template(),
				'stylesheet' => isset( $customizer['theme'] ) ? (string) $customizer['theme'] : get_stylesheet(),
				'mods'       => $customizer['mods'],
			);
		}

		$customizer_result = cotlas_import_customizer_data( $customizer );

		if ( ! empty( $customizer_result['fatal'] ) ) {
			$result['warnings'][] = $customizer_result['fatal'];
		} else {
			$result['customizer'] = $customizer_result['customizer'] ?? 0;
			$result['options']    = $customizer_result['options'] ?? 0;
			$result['warnings']   = array_merge( $result['warnings'], $customizer_result['warnings'] ?? array() );
		}
	}

	// Restore menu locations last so nothing overwrites the mapping.
	if ( $menu_id_map || ! empty( $data['menus'] ) ) {
		$registered = get_registered_nav_menus();
		$locations  = array();

		foreach ( (array) ( $data['menus'] ?? array() ) as $menu ) {
			if ( empty( $menu['term_id'] ) || empty( $menu['locations'] ) ) {
				continue;
			}

			$new_id = $menu_id_map[ (int) $menu['term_id'] ] ?? 0;
			if ( ! $new_id ) {
				continue;
			}

			foreach ( (array) $menu['locations'] as $location ) {
				$location = sanitize_key( $location );
				if ( '' !== $location && ( empty( $registered ) || isset( $registered[ $location ] ) ) ) {
					$locations[ $location ] = $new_id;
				}
			}
		}

		if ( $locations ) {
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	return $result;
}
