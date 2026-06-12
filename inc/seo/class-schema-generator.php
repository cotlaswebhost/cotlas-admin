<?php
/**
 * Central schema generator.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Schema_Generator {
	public static function is_enabled() {
		return (bool) get_option( 'cotlas_seo_enable_schema', 1 );
	}

	public static function build_graph( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : get_queried_object_id();
		$cache   = self::get_cached_graph( $post_id );
		if ( false !== $cache ) {
			return $cache;
		}

		$graph = array();

		$organization = Organization_Schema::build( $post_id );
		if ( $organization ) {
			$graph[] = $organization;
		}

		if ( is_front_page() || is_home() ) {
			$website = Website_Schema::build( $post_id );
			if ( $website ) {
				$graph[] = $website;
			}
		}

		if ( is_singular() && $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( 'page' === $post_type ) {
				$page = WebPage_Schema::build( $post_id );
				if ( $page ) {
					$graph[] = $page;
				}
			} else {
				$article = Article_Schema::build( $post_id );
				if ( $article ) {
					$graph[] = $article;
				}
			}

			$author = Author_Schema::build( $post_id );
			if ( $author ) {
				$graph[] = $author;
			}

			$faq = FAQ_Schema::build( $post_id );
			if ( $faq ) {
				$graph[] = $faq;
			}
		}

		if ( ( is_singular() || is_archive() || is_home() || is_front_page() ) && get_option( 'cotlas_seo_enable_breadcrumb_schema', 1 ) ) {
			$breadcrumb = Breadcrumb_Schema::build_schema( $post_id );
			if ( $breadcrumb ) {
				$graph[] = $breadcrumb;
			}
		}

		if ( is_singular() && get_option( 'cotlas_seo_enable_local_business_schema', 0 ) ) {
			$local = LocalBusiness_Schema::build( $post_id );
			if ( $local ) {
				$graph[] = $local;
			}
		}

		$graph = apply_filters( 'cotlas_schema_data', $graph, $post_id );
		self::set_cached_graph( $post_id, $graph );

		return $graph;
	}

	public static function build_schema_document( $post_id = 0 ) {
		$graph = self::build_graph( $post_id );
		if ( empty( $graph ) ) {
			return array();
		}

		$document = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		return apply_filters( 'cotlas_schema_document', $document, $post_id );
	}

	public static function output_json_ld() {
		if ( ! self::is_enabled() || Seo_Plugin_Compatibility::should_defer_output() ) {
			return;
		}

		$post_id = is_singular() ? get_queried_object_id() : 0;
		$schema  = self::build_schema_document( $post_id );
		if ( empty( $schema ) ) {
			return;
		}

		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public static function get_company_name() {
		$name = sanitize_text_field( get_option( 'cotlas_seo_company_name', get_option( 'cotlas_company_name', get_bloginfo( 'name' ) ) ) );
		return $name ? $name : get_bloginfo( 'name' );
	}

	public static function get_company_tagline() {
		$tagline = sanitize_text_field( get_option( 'cotlas_seo_company_tagline', get_option( 'cotlas_company_tagline', get_bloginfo( 'description' ) ) ) );
		return $tagline ? $tagline : get_bloginfo( 'description' );
	}

	public static function get_company_address() {
		return sanitize_textarea_field( get_option( 'cotlas_seo_company_address', get_option( 'cotlas_company_address', '' ) ) );
	}

	public static function get_company_phone() {
		return sanitize_text_field( get_option( 'cotlas_seo_company_phone', get_option( 'cotlas_company_phone', '' ) ) );
	}

	public static function get_company_email() {
		return sanitize_email( get_option( 'cotlas_seo_company_email', get_option( 'cotlas_company_email', get_option( 'admin_email' ) ) ) );
	}

	public static function get_company_logo_url() {
		$attachment_id = absint( get_option( 'cotlas_seo_company_logo_id', 0 ) );
		if ( $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		$site_logo = get_theme_mod( 'custom_logo' );
		if ( $site_logo ) {
			$url = wp_get_attachment_image_url( absint( $site_logo ), 'full' );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	public static function get_default_image_url() {
		$attachment_id = absint( get_option( 'cotlas_seo_default_image_id', 0 ) );
		if ( $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}

	public static function get_social_sameas() {
		$urls = array();
		$map  = array(
			'cotlas_social_facebook',
			'cotlas_social_twitter',
			'cotlas_social_youtube',
			'cotlas_social_instagram',
			'cotlas_social_linkedin',
			'cotlas_social_threads',
		);

		foreach ( $map as $key ) {
			$url = esc_url_raw( get_option( $key, '' ) );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	public static function get_organization_type() {
		$type = get_option( 'cotlas_seo_localbusiness_type', 'Organization' );
		$type = sanitize_text_field( $type );
		if ( 'custom' === $type ) {
			$type = sanitize_text_field( get_option( 'cotlas_seo_localbusiness_custom_type', 'Organization' ) );
		}
		return $type ? $type : 'Organization';
	}

	public static function get_primary_image_url( $post_id ) {
		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			return $image;
		}

		$logo = self::get_company_logo_url();
		if ( $logo ) {
			return $logo;
		}

		return self::get_default_image_url();
	}

	public static function get_excerpt_text( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$content = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
		if ( '' !== $content ) {
			return wp_trim_words( $content, 40, '' );
		}

		return '';
	}

	public static function get_title_for_post( $post_id ) {
		$plugin_title = Seo_Plugin_Compatibility::get_title( $post_id );
		if ( $plugin_title && get_option( 'cotlas_seo_use_seo_plugin_title', 1 ) ) {
			return $plugin_title;
		}

		return get_the_title( $post_id );
	}

	public static function get_description_for_post( $post_id ) {
		$plugin_desc = Seo_Plugin_Compatibility::get_description( $post_id );
		if ( $plugin_desc && get_option( 'cotlas_seo_use_seo_plugin_description', 1 ) ) {
			return $plugin_desc;
		}

		return self::get_excerpt_text( $post_id );
	}

	public static function get_cached_graph( $post_id ) {
		$key = self::get_cache_key( $post_id );
		return get_transient( $key );
	}

	public static function set_cached_graph( $post_id, $graph ) {
		set_transient( self::get_cache_key( $post_id ), $graph, DAY_IN_SECONDS );
	}

	public static function clear_cached_graph( $post_id ) {
		delete_transient( self::get_cache_key( $post_id ) );
	}

	private static function get_cache_key( $post_id ) {
		$post      = get_post( $post_id );
		$modified  = $post ? $post->post_modified_gmt : '';
		$signature = wp_json_encode( array(
			'post_id'           => absint( $post_id ),
			'post_modified_gmt'  => $modified,
			'schema'            => (int) get_option( 'cotlas_seo_enable_schema', 1 ),
			'og'                => (int) get_option( 'cotlas_seo_enable_opengraph', 1 ),
			'posttype_map'      => self::hash_value( Posttype_Mapping::get_mapping() ),
			'company_logo'      => absint( get_option( 'cotlas_seo_company_logo_id', 0 ) ),
			'default_image'     => absint( get_option( 'cotlas_seo_default_image_id', 0 ) ),
			'organization_type' => self::get_organization_type(),
		) );

		return 'cotlas_seo_schema_' . md5( $signature );
	}

	private static function hash_value( $value ) {
		return md5( wp_json_encode( $value ) );
	}

	public static function sanitize_bool( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	public static function sanitize_absint( $value ) {
		return absint( $value );
	}

	public static function sanitize_text( $value ) {
		return sanitize_text_field( $value );
	}

	public static function sanitize_url( $value ) {
		return esc_url_raw( $value );
	}
}
