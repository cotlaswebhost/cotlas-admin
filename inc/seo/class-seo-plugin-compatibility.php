<?php
/**
 * SEO plugin compatibility helpers.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Seo_Plugin_Compatibility {
	public static function active_plugins() {
		$plugins = array();

		if ( self::is_yoast_active() ) {
			$plugins[] = 'yoast';
		}

		if ( self::is_rank_math_active() ) {
			$plugins[] = 'rank_math';
		}

		if ( self::is_seopress_active() ) {
			$plugins[] = 'seopress';
		}

		if ( self::is_aioseo_active() ) {
			$plugins[] = 'aioseo';
		}

		return $plugins;
	}

	public static function should_defer_output() {
		return (bool) get_option( 'cotlas_seo_enable_seo_plugin_integration', 1 ) && ! empty( self::active_plugins() );
	}

	public static function is_yoast_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	public static function is_rank_math_active() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' );
	}

	public static function is_seopress_active() {
		return defined( 'SEOPRESS_VERSION' ) || class_exists( '\\SEOPress\\Core\\Main' ) || class_exists( 'SEOPRESS' );
	}

	public static function is_aioseo_active() {
		return defined( 'AIOSEO_VERSION' ) || class_exists( '\\AIOSEO\\Plugin\\Common\\Main' ) || class_exists( 'AIOSEO' );
	}

	public static function get_title( $post_id ) {
		$plugins = self::active_plugins();

		foreach ( $plugins as $plugin ) {
			if ( 'yoast' === $plugin ) {
				$title = self::get_meta_value( $post_id, array( '_yoast_wpseo_title' ) );
				if ( '' !== $title ) {
					return $title;
				}
			}

			if ( 'rank_math' === $plugin ) {
				$title = self::get_meta_value( $post_id, array( 'rank_math_title' ) );
				if ( '' !== $title ) {
					return $title;
				}
			}

			if ( 'seopress' === $plugin ) {
				$title = self::get_meta_value( $post_id, array( '_seopress_titles_title' ) );
				if ( '' !== $title ) {
					return $title;
				}
			}

			if ( 'aioseo' === $plugin ) {
				$title = self::get_meta_value( $post_id, array( '_aioseo_title', 'aioseo_title' ) );
				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		return '';
	}

	public static function get_description( $post_id ) {
		$plugins = self::active_plugins();

		foreach ( $plugins as $plugin ) {
			if ( 'yoast' === $plugin ) {
				$description = self::get_meta_value( $post_id, array( '_yoast_wpseo_metadesc' ) );
				if ( '' !== $description ) {
					return $description;
				}
			}

			if ( 'rank_math' === $plugin ) {
				$description = self::get_meta_value( $post_id, array( 'rank_math_description' ) );
				if ( '' !== $description ) {
					return $description;
				}
			}

			if ( 'seopress' === $plugin ) {
				$description = self::get_meta_value( $post_id, array( '_seopress_titles_desc' ) );
				if ( '' !== $description ) {
					return $description;
				}
			}

			if ( 'aioseo' === $plugin ) {
				$description = self::get_meta_value( $post_id, array( '_aioseo_description', 'aioseo_description' ) );
				if ( '' !== $description ) {
					return $description;
				}
			}
		}

		return '';
	}

	private static function get_meta_value( $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( is_string( $value ) ) {
				$value = trim( wp_strip_all_tags( $value ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}
}
