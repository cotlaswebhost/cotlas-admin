<?php
/**
 * OpenGraph tag output.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class OpenGraph {
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_tags' ), 5 );
	}

	public function output_tags() {
		if ( ! get_option( 'cotlas_seo_enable_opengraph', 1 ) || Seo_Plugin_Compatibility::should_defer_output() ) {
			return;
		}

		if ( is_admin() || is_feed() ) {
			return;
		}

		$post_id = is_singular() ? get_queried_object_id() : 0;
		$url     = $this->get_url( $post_id );
		$title   = apply_filters( 'cotlas_opengraph_title', $this->get_title( $post_id ), $post_id );
		$desc    = apply_filters( 'cotlas_opengraph_description', $this->get_description( $post_id ), $post_id );
		$image   = apply_filters( 'cotlas_opengraph_image', $this->get_image( $post_id ), $post_id );
		$type    = $this->get_type();

		echo "\n";
		$this->print_meta( 'og:title', $title );
		$this->print_meta( 'og:description', $desc );
		$this->print_meta( 'og:image', $image );
		$this->print_meta( 'og:url', $url );
		$this->print_meta( 'og:type', $type );

		if ( get_option( 'cotlas_seo_enable_twitter_cards', 1 ) ) {
			$this->print_meta( 'twitter:card', 'summary_large_image' );
			$this->print_meta( 'twitter:title', $title );
			$this->print_meta( 'twitter:description', $desc );
			$this->print_meta( 'twitter:image', $image );
			$this->print_meta( 'twitter:url', $url );
		}
	}

	private function get_title( $post_id ) {
		if ( $post_id ) {
			$title = Schema_Generator::get_title_for_post( $post_id );
			if ( $title ) {
				return $title;
			}
		}

		return get_bloginfo( 'name' );
	}

	private function get_description( $post_id ) {
		if ( $post_id ) {
			$description = Schema_Generator::get_description_for_post( $post_id );
			if ( $description ) {
				return $description;
			}
		}

		return get_bloginfo( 'description' );
	}

	private function get_image( $post_id ) {
		if ( $post_id ) {
			$image = Schema_Generator::get_primary_image_url( $post_id );
			if ( $image ) {
				return $image;
			}
		}

		return Schema_Generator::get_default_image_url();
	}

	private function get_url( $post_id ) {
		if ( $post_id ) {
			$url = get_permalink( $post_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	private function get_type() {
		if ( is_front_page() || is_home() || is_archive() ) {
			return 'website';
		}

		if ( is_singular( 'page' ) ) {
			return 'website';
		}

		return 'article';
	}

	private function print_meta( $name, $value ) {
		if ( '' === trim( (string) $value ) ) {
			return;
		}

		$attribute = 0 === strpos( $name, 'twitter:' ) ? 'name' : 'property';
		echo '<meta ' . esc_attr( $attribute ) . '="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />\n';
	}
}
