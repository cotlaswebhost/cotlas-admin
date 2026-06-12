<?php
/**
 * Article schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Article_Schema {
	public static function build( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$post_type = get_post_type( $post_id );
		$type      = Posttype_Mapping::get_schema_type_for_post_type( $post_type );
		if ( 'page' === $post_type ) {
			$type = 'WebPage';
		}

		$schema = array(
			'@type'            => $type,
			'@id'              => get_permalink( $post_id ) . '#article',
			'headline'         => Schema_Generator::get_title_for_post( $post_id ),
			'description'      => Schema_Generator::get_description_for_post( $post_id ),
			'image'            => Schema_Generator::get_primary_image_url( $post_id ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'publisher'        => array(
				'@id' => home_url( '#organization' ),
			),
			'datePublished'    => get_the_date( DATE_W3C, $post_id ),
			'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
			'mainEntityOfPage' => get_permalink( $post_id ),
			'keywords'         => self::get_keywords( $post_id ),
			'wordCount'        => self::get_word_count( $post_id ),
		);

		if ( 'Article' === $schema['@type'] ) {
			$schema['articleSection'] = self::get_article_section( $post_id );
		}

		return array_filter( $schema );
	}

	private static function get_keywords( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::get_primary_taxonomy( $post_id ), array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			if ( is_wp_error( $tags ) || empty( $tags ) ) {
				return '';
			}
			return implode( ', ', $tags );
		}

		return implode( ', ', $terms );
	}

	private static function get_primary_taxonomy( $post_id ) {
		$post_type = get_post_type( $post_id );
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! empty( $taxonomy->hierarchical ) && $taxonomy->public ) {
				return $taxonomy->name;
			}
		}

		return 'category';
	}

	private static function get_article_section( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::get_primary_taxonomy( $post_id ), array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return $terms[0];
	}

	private static function get_word_count( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) );
		if ( '' === $text ) {
			return 0;
		}

		$tokens = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $tokens ) ? count( $tokens ) : 0;
	}
}
