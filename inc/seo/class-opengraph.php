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
		if ( ! Schema_Generator::is_module_enabled() || ! get_option( 'cotlas_seo_enable_opengraph', 0 ) || Seo_Plugin_Compatibility::should_defer_output() ) {
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
		$image_meta = $this->get_image_meta( $post_id, $image );
		$type    = $this->get_type();

		echo "\n";
		$this->print_meta( 'og:title', $title );
		$this->print_meta( 'og:description', $desc );
		$this->print_meta( 'og:image', $image );
		$this->print_meta( 'og:image:url', $image_meta['url'] );
		$this->print_meta( 'og:image:secure_url', $image_meta['secure_url'] );
		$this->print_meta( 'og:image:type', $image_meta['mime'] );
		$this->print_meta( 'og:image:width', $image_meta['width'] );
		$this->print_meta( 'og:image:height', $image_meta['height'] );
		$this->print_meta( 'og:image:alt', $image_meta['alt'] );
		$this->print_meta( 'og:url', $url );
		$this->print_meta( 'og:type', $type );

		if ( get_option( 'cotlas_seo_enable_twitter_cards', 0 ) ) {
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

	private function get_image_meta( $post_id, $image_url ) {
		$meta = array(
			'url'        => $image_url,
			'secure_url' => ( 0 === strpos( (string) $image_url, 'http://' ) ) ? preg_replace( '#^http://#', 'https://', (string) $image_url ) : $image_url,
			'mime'       => '',
			'width'      => '',
			'height'     => '',
			'alt'        => '',
		);

		$attachment_id = 0;
		if ( $post_id ) {
			$attachment_id = (int) get_post_thumbnail_id( $post_id );
		}

		if ( ! $attachment_id && $image_url ) {
			$attachment_id = (int) attachment_url_to_postid( $image_url );
		}

		if ( ! $attachment_id ) {
			return $meta;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( $mime ) {
			$meta['mime'] = $mime;
		}

		$image_data = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( is_array( $image_data ) ) {
			if ( ! empty( $image_data[0] ) ) {
				$meta['url'] = $image_data[0];
				$meta['secure_url'] = ( 0 === strpos( (string) $image_data[0], 'http://' ) ) ? preg_replace( '#^http://#', 'https://', (string) $image_data[0] ) : $image_data[0];
			}

			if ( ! empty( $image_data[1] ) ) {
				$meta['width'] = (string) absint( $image_data[1] );
			}

			if ( ! empty( $image_data[2] ) ) {
				$meta['height'] = (string) absint( $image_data[2] );
			}
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			$meta['alt'] = sanitize_text_field( $alt );
		}

		if ( '' === $meta['alt'] ) {
			$meta['alt'] = get_bloginfo( 'name' );
		}

		return $meta;
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
		echo '<meta ' . esc_attr( $attribute ) . '="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '" />' . "\n";
	}
}
