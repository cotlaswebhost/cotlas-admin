<?php
/**
 * Image format conversion: converts JPEG/PNG uploads to WebP and/or AVIF,
 * cleans up converted files on deletion, and serves the best format to each browser.
 *
 * Loaded independently of the Image Optimization master toggle so conversion
 * can work even when the size/srcset/LCP features are disabled.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/* ── Server capability detection ─────────────────────────── */

function cimg_server_supports_avif() {
	if ( extension_loaded( 'imagick' ) ) {
		try {
			$im      = new Imagick();
			$formats = array_map( 'strtoupper', (array) $im->queryFormats() );
			if ( in_array( 'AVIF', $formats, true ) ) { return true; }
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
		$info = gd_info();
		if ( ! empty( $info['AVIF Support'] ) ) { return true; }
	}
	return false;
}

function cimg_server_supports_webp() {
	if ( extension_loaded( 'imagick' ) ) {
		try {
			$im      = new Imagick();
			$formats = array_map( 'strtoupper', (array) $im->queryFormats() );
			if ( in_array( 'WEBP', $formats, true ) ) { return true; }
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
		$info = gd_info();
		if ( ! empty( $info['WebP Support'] ) ) { return true; }
	}
	return false;
}

/* ── Browser capability detection ────────────────────────── */

function cimg_browser_supports_avif() {
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
	if ( strpos( $accept, 'image/avif' ) !== false ) { return true; }
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return (bool) preg_match( '/(Chrome\/(8[5-9]|9\d|[1-9]\d{2,})|Firefox\/(9[3-9]|[1-9]\d{2,})|Edg\/(8[5-9]|9\d|[1-9]\d{2,}))/', $ua );
}

function cimg_browser_supports_webp() {
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
	if ( strpos( $accept, 'image/webp' ) !== false ) { return true; }
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return ! preg_match( '/(MSIE |Trident\/|Edge\/12|Edge\/13)/', $ua );
}

/* ── Exclusion check ──────────────────────────────────────── */

function cimg_should_exclude( $src, $serve_mode = false ) {
	// Always exclude theme and plugin asset paths.
	if ( strpos( $src, '/themes/' ) !== false || strpos( $src, '/plugins/' ) !== false ) {
		return true;
	}

	if ( $serve_mode ) {
		return false;
	}
	// User-defined exclusion patterns (newline-separated; plain words or /regex/).
	$raw = get_option( 'cotlas_imgconv_exclude_patterns', '' );
	if ( trim( $raw ) === '' ) {
		$raw = "logo\nsite-logo\nbrand\nfavicon\nicon";
	}
	foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $pattern ) {
		if ( $pattern[0] !== '/' ) {
			$pattern = '/' . preg_quote( $pattern, '/' ) . '/i';
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( @preg_match( $pattern, $src ) ) {
			return true;
		}
	}
	return false;
}

/* ── Smart format selection ───────────────────────────────── */

/**
 * Determine the single target format for a given upload.
 * JPEG/JPG    → WebP  (fast, ~30% smaller, no quality loss for photos)
 * PNG < 300KB → WebP  (small graphics compress well as WebP)
 * PNG ≥ 300KB → AVIF  (large screenshots/illustrations get ~50% savings)
 *                      Falls back to WebP if server lacks AVIF support.
 * Returns 'webp', 'avif', or '' (skip conversion).
 */
function cimg_choose_format( $mime, $file_path ) {
	if ( 'image/jpeg' === $mime ) {
		return cimg_server_supports_webp() ? 'webp' : '';
	}
	if ( 'image/png' === $mime ) {
		$size = file_exists( $file_path ) ? (int) filesize( $file_path ) : 0;
		if ( $size >= 300 * 1024 ) {
			// Large PNG → AVIF preferred.
			if ( cimg_server_supports_avif() ) { return 'avif'; }
			if ( cimg_server_supports_webp() ) { return 'webp'; }
		} else {
			// Small PNG → WebP.
			if ( cimg_server_supports_webp() ) { return 'webp'; }
		}
	}
	return '';
}

/**
 * Given an image URL already in the HTML, pick the best available modern
 * format the current browser can handle. Returns 'avif', 'webp', or ''.
 * Checks what files actually exist on disk — no assumptions.
 */
function cimg_pick_serve_format( $src_url, $baseurl, $basedir ) {
	// If the URL already points to a modern format (delete-original case),
	// no further swapping is needed.
	if ( preg_match( '/\.(avif|webp)$/i', $src_url ) ) {
		return '';
	}
	// AVIF first (better compression).
	$avif_url  = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $src_url );
	$avif_path = str_replace( $baseurl, $basedir, $avif_url );
	if ( $avif_path !== $avif_url && file_exists( $avif_path ) && cimg_browser_supports_avif() ) {
		return 'avif';
	}
	// WebP fallback.
	$webp_url  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src_url );
	$webp_path = str_replace( $baseurl, $basedir, $webp_url );
	if ( $webp_path !== $webp_url && file_exists( $webp_path ) && cimg_browser_supports_webp() ) {
		return 'webp';
	}
	return '';
}

function cimg_swap_image_url( $src_url ) {
	if ( is_admin() || empty( $src_url ) ) {
		return $src_url;
	}

	$is_logo_asset = (bool) preg_match( '/(logo|site-logo|brand|favicon|icon)/i', $src_url );
	if ( cimg_should_exclude( $src_url ) && ! $is_logo_asset ) {
		return $src_url;
	}

	$upload  = wp_get_upload_dir();
	$baseurl = trailingslashit( $upload['baseurl'] );
	$basedir = trailingslashit( $upload['basedir'] );

	$ext = cimg_pick_serve_format( $src_url, $baseurl, $basedir );
	if ( ! $ext ) {
		return $src_url;
	}

	$new_src  = preg_replace( '/\.(jpe?g|png)$/i', '.' . $ext, $src_url );
	$new_path = str_replace( $baseurl, $basedir, $new_src );

	return ( $new_path !== $new_src && file_exists( $new_path ) ) ? $new_src : $src_url;
}

function cimg_swap_srcset_urls( $srcset ) {
	if ( empty( $srcset ) ) {
		return $srcset;
	}

	$parts   = array();
	$upload  = wp_get_upload_dir();
	$baseurl = trailingslashit( $upload['baseurl'] );
	$basedir = trailingslashit( $upload['basedir'] );

	foreach ( explode( ', ', $srcset ) as $entry ) {
		$entry = trim( $entry );
		if ( ! preg_match( '/^(\S+)(\s+\d+[wx])$/', $entry, $m ) ) {
			$parts[] = $entry;
			continue;
		}

		$ext = cimg_pick_serve_format( $m[1], $baseurl, $basedir );
		if ( ! $ext ) {
			$parts[] = $entry;
			continue;
		}

		$new_url  = preg_replace( '/\.(jpe?g|png)$/i', '.' . $ext, $m[1] );
		$new_path = str_replace( $baseurl, $basedir, $new_url );
		$parts[]  = ( $new_path !== $new_url && file_exists( $new_path ) )
			? $new_url . $m[2]
			: $entry;
	}

	return implode( ', ', $parts );
}

/* ── Convert a single file to WebP and/or AVIF ───────────── */

function cimg_convert_file( $source, $do_webp, $do_avif ) {
	$result       = array( 'webp' => false, 'avif' => false );
	$webp_quality = (int) get_option( 'cotlas_imgconv_webp_quality', 75 );
	$avif_quality = (int) get_option( 'cotlas_imgconv_avif_quality', 50 );

	if ( $do_webp ) {
		$dest = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $source );
		if ( ! file_exists( $dest ) ) {
			$editor = wp_get_image_editor( $source );
			if ( ! is_wp_error( $editor ) ) {
				$editor->set_quality( $webp_quality );
				$saved          = $editor->save( $dest, 'image/webp' );
				$result['webp'] = ! is_wp_error( $saved ) && file_exists( $dest );
			}
		} else {
			$result['webp'] = true;
		}
	}

	if ( $do_avif ) {
		$dest = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $source );
		if ( ! file_exists( $dest ) ) {
			$editor = wp_get_image_editor( $source );
			if ( ! is_wp_error( $editor ) ) {
				$editor->set_quality( $avif_quality );
				$saved          = $editor->save( $dest, 'image/avif' );
				$result['avif'] = ! is_wp_error( $saved ) && file_exists( $dest );
			}
		} else {
			$result['avif'] = true;
		}
	}

	return $result;
}

/* ── Convert on upload ────────────────────────────────────── */

add_filter( 'wp_generate_attachment_metadata', 'cimg_handle_upload', 20, 2 );

function cimg_handle_upload( $metadata, $attachment_id ) {
	if ( ! get_option( 'cotlas_imgconv_enabled' ) ) {
		return $metadata;
	}
	if ( empty( $metadata ) || ! is_array( $metadata ) ) {
		return $metadata;
	}

	$file_path = get_attached_file( $attachment_id );
	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return $metadata;
	}

	$mime = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		return $metadata;
	}

	if ( cimg_should_exclude( $file_path ) ) {
		return $metadata;
	}

	// Smart hybrid: pick exactly ONE target format.
	$target = cimg_choose_format( $mime, $file_path );
	if ( ! $target ) {
		return $metadata;
	}

	$do_webp = ( 'webp' === $target );
	$do_avif = ( 'avif' === $target );

	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@set_time_limit( 120 );
	}

	$delete_orig = (bool) get_option( 'cotlas_imgconv_delete_original' );
	$file_dir    = dirname( $file_path );

	// Convert the original file.
	$result = cimg_convert_file( $file_path, $do_webp, $do_avif );

	// Convert each registered size.
	if ( ! empty( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size_info ) {
			if ( empty( $size_info['file'] ) ) { continue; }
			$size_path = path_join( $file_dir, $size_info['file'] );
			if ( ! file_exists( $size_path ) ) { continue; }
			cimg_convert_file( $size_path, $do_webp, $do_avif );
		}
	}

	if ( $delete_orig ) {
		if ( $do_avif && $result['avif'] ) {
			$primary_ext  = 'avif';
			$primary_mime = 'image/avif';
		} elseif ( $do_webp && $result['webp'] ) {
			$primary_ext  = 'webp';
			$primary_mime = 'image/webp';
		} else {
			$primary_ext  = '';
			$primary_mime = '';
		}

		if ( $primary_ext ) {
			// Keep the MAIN original file (my-image.png / .jpg) so thumbnails
			// can be regenerated from it later if conversion is ever disabled.
			// Only delete the RESIZED size variants — they are redundant because
			// converted (AVIF/WebP) versions of every size already exist on disk.

			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as &$size_info ) {
					if ( empty( $size_info['file'] ) ) { continue; }
					$size_path = path_join( $file_dir, $size_info['file'] );
					// Only delete if a converted variant was successfully created.
					$converted_path = preg_replace( '/\.(jpe?g|png)$/i', '.' . $primary_ext, $size_path );
					if ( file_exists( $converted_path ) && file_exists( $size_path ) ) {
						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						@unlink( $size_path );
					}
					// Update size metadata to reference the converted file.
					$size_info['file']      = preg_replace( '/\.(jpe?g|png)$/i', '.' . $primary_ext, $size_info['file'] );
					$size_info['mime-type'] = $primary_mime;
				}
				unset( $size_info );
			}
			// _wp_attached_file and post_mime_type intentionally NOT updated —
			// the main original is still on disk and WP should keep pointing to it.
			// The swap filters (cimg_swap_image_attrs / cimg_swap_content_images)
			// will serve the converted main file (my-image.avif/.webp) to browsers.
		}
	}

	return $metadata;
}

/* ── Clean up converted files when attachment is deleted ─── */

add_action( 'delete_attachment', 'cimg_cleanup_converted_files' );

function cimg_cleanup_converted_files( $attachment_id ) {
	$file_path = get_attached_file( $attachment_id );
	if ( ! $file_path ) { return; }

	$metadata = wp_get_attachment_metadata( $attachment_id );
	$file_dir = dirname( $file_path );
	$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

	// Build sibling paths to delete based on what the current primary format is.
	$to_delete = array();
	if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		// Original is still the source — delete both modern variants.
		$to_delete[] = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );
		$to_delete[] = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $file_path );
	} elseif ( 'avif' === $ext ) {
		// AVIF is the stored primary — delete sibling WebP variant.
		$to_delete[] = substr( $file_path, 0, -4 ) . 'webp';
	} elseif ( 'webp' === $ext ) {
		// WebP is the stored primary — delete sibling AVIF variant.
		$to_delete[] = substr( $file_path, 0, -4 ) . 'avif';
	}

	// Add size-level siblings.
	if ( ! empty( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size_info ) {
			if ( empty( $size_info['file'] ) ) { continue; }
			$base     = path_join( $file_dir, $size_info['file'] );
			$size_ext = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
			if ( in_array( $size_ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				$to_delete[] = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $base );
				$to_delete[] = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $base );
			} elseif ( 'avif' === $size_ext ) {
				$to_delete[] = substr( $base, 0, -4 ) . 'webp';
			} elseif ( 'webp' === $size_ext ) {
				$to_delete[] = substr( $base, 0, -4 ) . 'avif';
			}
		}
	}

	foreach ( $to_delete as $f ) {
		if ( $f && file_exists( $f ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $f );
		}
	}
}

/* ── Serve modern formats in attachment image tags ────────── */

add_filter( 'wp_get_attachment_url', 'cimg_swap_attachment_url', 10, 2 );
add_filter( 'wp_get_attachment_image_src', 'cimg_swap_attachment_image_src', 10, 4 );
add_filter( 'wp_get_attachment_image_attributes', 'cimg_swap_image_attrs', 10, 2 );

function cimg_swap_attachment_url( $url, $attachment_id ) {
	return cimg_swap_image_url( $url );
}

function cimg_swap_attachment_image_src( $image, $attachment_id, $size, $icon ) {
	if ( is_admin() || empty( $image[0] ) ) {
		return $image;
	}

	$image[0] = cimg_swap_image_url( $image[0] );
	return $image;
}

function cimg_swap_image_attrs( $attr, $attachment ) {
	if ( is_admin() || empty( $attr['src'] ) ) { return $attr; }

	$attr['src'] = cimg_swap_image_url( $attr['src'] );
	if ( ! empty( $attr['srcset'] ) ) {
		$attr['srcset'] = cimg_swap_srcset_urls( $attr['srcset'] );
	}

	return $attr;
}

/* ── Serve modern formats in post content images ──────────── */

add_filter( 'wp_content_img_tag', 'cimg_swap_content_images', 10, 3 );

function cimg_swap_content_images( $filtered_image, $context, $attachment_id ) {
	if ( is_admin() ) { return $filtered_image; }

	if ( ! preg_match( '/src="([^"]+)"/', $filtered_image, $sm ) ) { return $filtered_image; }
	$new_src = cimg_swap_image_url( $sm[1] );
	if ( $new_src !== $sm[1] ) {
		$filtered_image = str_replace( 'src="' . $sm[1] . '"', 'src="' . $new_src . '"', $filtered_image );
	}

	// Swap srcset.
	if ( preg_match( '/srcset="([^"]+)"/', $filtered_image, $ssm ) ) {
		$new_srcset     = cimg_swap_srcset_urls( $ssm[1] );
		$filtered_image = str_replace( 'srcset="' . $ssm[1] . '"', 'srcset="' . $new_srcset . '"', $filtered_image );
	}

	return $filtered_image;
}
