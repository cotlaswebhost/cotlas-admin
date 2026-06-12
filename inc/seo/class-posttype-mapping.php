<?php
/**
 * Post type schema mapping helpers.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Posttype_Mapping {
	public static function get_public_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$ignored    = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_global_styles', 'user_request' );
		$result     = array();

		foreach ( $post_types as $slug => $object ) {
			if ( in_array( $slug, $ignored, true ) ) {
				continue;
			}

			$result[ $slug ] = $object;
		}

		return $result;
	}

	public static function get_mapping() {
		$map = get_option( 'cotlas_seo_posttype_schema_map', array() );
		return is_array( $map ) ? $map : array();
	}

	public static function sanitize_mapping( $raw ) {
		$clean       = array();
		$allowed     = self::allowed_schema_types();
		$post_types  = self::get_public_post_types();
		$raw         = is_array( $raw ) ? $raw : array();

		foreach ( $post_types as $slug => $object ) {
			$item = isset( $raw[ $slug ] ) && is_array( $raw[ $slug ] ) ? $raw[ $slug ] : array();
			$type = isset( $item['type'] ) ? sanitize_text_field( $item['type'] ) : 'Article';
			if ( ! in_array( $type, $allowed, true ) ) {
				$type = 'Article';
			}
			$clean[ $slug ] = array(
				'enabled' => empty( $item['enabled'] ) ? 0 : 1,
				'type'    => $type,
			);
		}

		return $clean;
	}

	public static function get_schema_type_for_post_type( $post_type ) {
		$map = self::get_mapping();
		if ( isset( $map[ $post_type ] ) && ! empty( $map[ $post_type ]['enabled'] ) ) {
			$type = isset( $map[ $post_type ]['type'] ) ? $map[ $post_type ]['type'] : 'Article';
			return self::normalize_schema_type( $type );
		}

		if ( 'page' === $post_type ) {
			return 'WebPage';
		}

		return 'Article';
	}

	public static function allowed_schema_types() {
		return array(
			'Article',
			'NewsArticle',
			'BlogPosting',
			'FAQPage',
			'Event',
			'Review',
			'Recipe',
			'Product',
			'VideoObject',
			'Service',
			'LocalBusiness',
			'Organization',
			'Corporation',
			'Person',
			'JobPosting',
			'Course',
			'MedicalOrganization',
			'Attorney',
			'RealEstateAgent',
			'Store',
			'SoftwareApplication',
			'WebPage',
			'custom',
		);
	}

	public static function normalize_schema_type( $type ) {
		$type = sanitize_text_field( $type );
		return $type ? $type : 'Article';
	}
}
