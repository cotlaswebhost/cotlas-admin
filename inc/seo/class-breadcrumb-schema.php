<?php
/**
 * Breadcrumb shortcode and schema helpers.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Breadcrumb_Schema {
	public static function init() {
		if ( Schema_Generator::is_module_enabled() && get_option( 'cotlas_seo_enable_breadcrumb_shortcode', 0 ) ) {
			add_shortcode( 'cotlas_breadcrumbs', array( __CLASS__, 'shortcode' ) );
		}

		add_action( 'init', array( __CLASS__, 'register_dynamic_tag' ), 20 );
	}

	public static function shortcode( $atts = array() ) {
		if ( ! Schema_Generator::is_module_enabled() || ! get_option( 'cotlas_seo_enable_breadcrumb_shortcode', 0 ) ) {
			return '';
		}

		$items = self::get_items( get_queried_object_id() );
		if ( empty( $items ) ) {
			return '';
		}

		$output = '<nav class="cotlas-breadcrumbs" aria-label="Breadcrumb"><ol>';
		foreach ( $items as $index => $item ) {
			$is_last = ( count( $items ) - 1 ) === $index;
			$output .= '<li>';
			if ( ! $is_last && ! empty( $item['url'] ) ) {
				$output .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			} else {
				$output .= '<span>' . esc_html( $item['label'] ) . '</span>';
			}
			$output .= '</li>';
		}
		$output .= '</ol></nav>';

		return $output;
	}

	public static function register_dynamic_tag() {
		if ( ! Schema_Generator::is_module_enabled() || ! get_option( 'cotlas_seo_enable_breadcrumb_shortcode', 0 ) || ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			return;
		}

		new \GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'    => __( 'Cotlas Breadcrumbs', 'cotlas-admin' ),
				'tag'      => 'cotlas_breadcrumbs',
				'type'     => 'post',
				'supports' => array( 'source', 'link' ),
				'return'   => array( __CLASS__, 'shortcode' ),
			)
		);
	}

	public static function build_schema( $post_id ) {
		$items = self::get_items( $post_id );
		if ( empty( $items ) ) {
			return array();
		}

		$list = array();
		foreach ( $items as $index => $item ) {
			$entry = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['label'],
			);

			if ( ! empty( $item['url'] ) ) {
				$entry['item'] = $item['url'];
			}

			$list[] = $entry;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => home_url( '#breadcrumbs' ),
			'itemListElement' => $list,
		);
	}

	public static function get_items( $post_id ) {
		$items   = array();
		$items[] = array(
			'label' => __( 'Home', 'cotlas-admin' ),
			'url'   => home_url( '/' ),
		);

		if ( is_front_page() || is_home() ) {
			return $items;
		}

		if ( is_singular( 'page' ) ) {
			$page_items = self::get_page_ancestors( $post_id );
			$items      = array_merge( $items, $page_items );
			$items[]    = array(
				'label' => get_the_title( $post_id ),
				'url'   => '',
			);
			return $items;
		}

		if ( is_singular() ) {
			$trail = self::get_taxonomy_trail_for_post( $post_id );
			$items = array_merge( $items, $trail );
			$items[] = array(
				'label' => get_the_title( $post_id ),
				'url'   => '',
			);
			return $items;
		}

		if ( is_category() || is_tax() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$items = array_merge( $items, self::get_term_ancestors( $term ) );
				$items[] = array(
					'label' => $term->name,
					'url'   => '',
				);
			}
			return $items;
		}

		if ( is_post_type_archive() ) {
			$items[] = array(
				'label' => post_type_archive_title( '', false ),
				'url'   => '',
			);
			return $items;
		}

		if ( is_archive() ) {
			$items[] = array(
				'label' => wp_get_document_title(),
				'url'   => '',
			);
		}

		return $items;
	}

	private static function get_page_ancestors( $post_id ) {
		$items     = array();
		$ancestors = array_reverse( get_post_ancestors( $post_id ) );

		foreach ( $ancestors as $ancestor_id ) {
			$items[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => get_permalink( $ancestor_id ),
			);
		}

		return $items;
	}

	private static function get_taxonomy_trail_for_post( $post_id ) {
		$post_type  = get_post_type( $post_id );
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->hierarchical ) || empty( $taxonomy->public ) ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy->name );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$term = array_shift( $terms );
			return self::get_term_ancestors( $term );
		}

		return array();
	}

	private static function get_term_ancestors( \WP_Term $term ) {
		$items      = array();
		$ancestors  = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$items[] = array(
					'label' => $ancestor->name,
					'url'   => get_term_link( $ancestor ),
				);
			}
		}

		$items[] = array(
			'label' => $term->name,
			'url'   => get_term_link( $term ),
		);

		return $items;
	}
}
