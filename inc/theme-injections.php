<?php
/**
 * Theme injections: related posts on single posts.
 *
 * Renders the related-posts block without depending on GenerateBlocks or
 * core/latest-posts, using the theme's existing CSS classes.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clamp the related posts count to a sane range.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function cotlas_sanitize_related_posts_count( $value ) {
    $count = absint( $value );

    if ( $count < 1 ) {
        $count = 3;
    }

    if ( $count > 12 ) {
        $count = 12;
    }

    return $count;
}

/**
 * Fetch related posts: same categories as the current post, most recent first.
 *
 * @param int $post_id Current post ID.
 * @param int $count   Number of posts to return.
 * @return WP_Post[]
 */
function cotlas_get_related_posts( $post_id, $count ) {
    $args = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => $count,
        'post__not_in'        => array( $post_id ),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
    );

    $categories = wp_get_post_categories( $post_id );

    if ( ! empty( $categories ) ) {
        $args['category__in'] = $categories;
    }

    return get_posts( $args );
}

/**
 * Heading label for the related posts block.
 *
 * @return string
 */
function cotlas_get_related_posts_label() {
    $label = get_option( 'cotlas_related_posts_label', '' );

    if ( ! is_string( $label ) || '' === trim( $label ) ) {
        return __( 'Related News', 'cotlas-admin' );
    }

    return $label;
}

/**
 * Build the related posts markup.
 *
 * @return string
 */
function cotlas_get_related_posts_html() {
    if ( ! is_singular( 'post' ) ) {
        return '';
    }

    if ( ! get_option( 'cotlas_related_posts_inject' ) ) {
        return '';
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $count = cotlas_sanitize_related_posts_count( get_option( 'cotlas_related_posts_count', 3 ) );
    $posts = cotlas_get_related_posts( $post_id, $count );

    if ( empty( $posts ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="cws-related-posts">
        <h5 class="cws-related-posts__title">
            <span class="cws-related-posts__label"><?php echo esc_html( cotlas_get_related_posts_label() ); ?></span>
            <span class="cws-related-posts__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"></path></svg>
            </span>
        </h5>
        <div class="cws-posts">
            <ul class="wp-block-latest-posts__list has-dates cws-posts-list wp-block-latest-posts is-layout-flow wp-block-latest-posts-is-layout-flow">
                <?php foreach ( $posts as $related ) : ?>
                    <li>
                        <?php
                        $thumbnail = get_the_post_thumbnail(
                            $related->ID,
                            'small-card',
                            array( 'class' => 'attachment-small-card size-small-card wp-post-image' )
                        );

                        if ( $thumbnail ) :
                            ?>
                            <div class="wp-block-latest-posts__featured-image aligncenter">
                                <a href="<?php echo esc_url( get_permalink( $related->ID ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $related->ID ) ); ?>">
                                    <?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated image markup. ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <a class="wp-block-latest-posts__post-title" href="<?php echo esc_url( get_permalink( $related->ID ) ); ?>"><?php echo esc_html( get_the_title( $related->ID ) ); ?></a>
                        <time datetime="<?php echo esc_attr( get_the_date( 'c', $related->ID ) ); ?>" class="wp-block-latest-posts__post-date"><?php echo esc_html( get_the_date( '', $related->ID ) ); ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action( 'generate_after_main_content', 'cotlas_render_related_posts', 10 );
/**
 * Output the related posts block after the main content.
 */
function cotlas_render_related_posts() {
    if ( ! cotlas_generatepress_frontend_enabled() ) {
        return;
    }

    echo cotlas_get_related_posts_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this plugin.
}

/**
 * Sanitize an ads paragraph-number field.
 *
 * Empty is allowed (slot disabled). Otherwise the value is clamped to 1–100.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cotlas_sanitize_ads_paragraph( $value ) {
    if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
        return '';
    }

    $value = trim( (string) $value );

    if ( '' === $value ) {
        return '';
    }

    $number = absint( $value );

    if ( $number < 1 ) {
        return '';
    }

    return (string) min( 100, $number );
}

/**
 * Sanitize an ads shortcode field.
 *
 * Keeps the shortcode brackets intact while stripping any stray HTML.
 *
 * @param string $value Raw field value.
 * @return string
 */
function cotlas_sanitize_ads_shortcode( $value ) {
    $value = is_string( $value ) ? $value : '';
    $value = wp_check_invalid_utf8( $value );
    $value = wp_strip_all_tags( $value );

    return trim( $value );
}

add_action( 'generate_after_logo', 'cotlas_render_header_ads', 10 );
/**
 * Output the Header Ads shortcode after the site logo.
 *
 * Mirrors the former GeneratePress Element so the markup stays identical:
 * the shortcode is wrapped in .header-right inside .inside-header.
 */
function cotlas_render_header_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() ) {
        return;
    }

    $shortcode = cotlas_sanitize_ads_shortcode( get_option( 'cotlas_ads_header', '' ) );

    if ( '' === $shortcode ) {
        return;
    }

    $html = do_shortcode( $shortcode );

    if ( '' === trim( $html ) ) {
        return;
    }

    echo '<div class="header-right">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is trusted plugin markup.
}

/**
 * Render a saved ads shortcode for one GeneratePress ad location.
 *
 * @param string $option        Option key holding the shortcode.
 * @param string $wrapper_class Extra class for the wrapper element.
 */
function cotlas_render_ads_location( $option, $wrapper_class ) {
    $shortcode = cotlas_sanitize_ads_shortcode( get_option( $option, '' ) );

    if ( '' === $shortcode ) {
        return;
    }

    $html = do_shortcode( $shortcode );

    if ( '' === trim( $html ) ) {
        return;
    }

    echo '<div class="cotlas-od-slot ' . esc_attr( $wrapper_class ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is trusted plugin markup.
}

/**
 * Whether the current request counts as a GeneratePress archive view.
 *
 * @return bool
 */
function cotlas_is_ads_archive_view() {
    return is_archive() || is_home();
}

add_action( 'generate_before_main_content', 'cotlas_render_above_archive_ads', 10 );
/**
 * Output the Above Archive ad. Mirrors GeneratePress Element #840.
 */
function cotlas_render_above_archive_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! cotlas_is_ads_archive_view() ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_above_archive', 'cotlas-ad-above-archive' );
}

add_action( 'generate_before_main_content', 'cotlas_render_above_post_ads', 10 );
/**
 * Output the Above Post ad. Mirrors GeneratePress Element #831.
 */
function cotlas_render_above_post_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_above_post', 'cotlas-ad-above-post' );
}

add_action( 'generate_after_entry_header', 'cotlas_render_above_content_ads', 20 );
/**
 * Output the Above Content ad on single posts.
 *
 * Runs after the featured image (priority 10) and directly before the
 * .entry-content div.
 */
function cotlas_render_above_content_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_above_content', 'cotlas-ad-above-content' );
}

add_action( 'generate_after_entry_content', 'cotlas_render_below_content_ads', 5 );
/**
 * Output the Below Content ad on single posts.
 *
 * Runs before the footer entry-meta (priority 10), directly after the
 * .entry-content div.
 */
function cotlas_render_below_content_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_below_content', 'cotlas-ad-below-content' );
}

add_action( 'generate_after_main_content', 'cotlas_render_above_related_ads', 5 );
/**
 * Output the Above Related News ad on single posts.
 *
 * Runs before cotlas_render_related_posts() (priority 10) so the ad sits
 * directly above the .cws-related-posts block.
 */
function cotlas_render_above_related_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_above_related', 'cotlas-ad-above-related' );
}

// Runs after GeneratePress Premium's infinite-scroll "Load more" button on
// archives (generate_blog_load_more is hooked at priority 20), so the ad sits
// below it and before </main>.
add_action( 'generate_after_main_content', 'cotlas_render_below_archive_ads', 30 );
/**
 * Output the Below Archive ad. Mirrors GeneratePress Element #842.
 */
function cotlas_render_below_archive_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! cotlas_is_ads_archive_view() ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_below_archive', 'cotlas-ad-below-archive' );
}

add_action( 'generate_after_main_content', 'cotlas_render_below_post_ads', 20 );
/**
 * Output the Below Related Post ad. Mirrors GeneratePress Element #833.
 *
 * Runs after cotlas_render_related_posts() (priority 10) so the ad sits
 * below the related-posts block.
 */
function cotlas_render_below_post_ads() {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) ) {
        return;
    }

    cotlas_render_ads_location( 'cotlas_ads_below_post', 'cotlas-ad-below-post' );
}

add_filter( 'the_content', 'cotlas_inject_inside_content_ads', 12 );
/**
 * Inject the Inside Content ads after their configured paragraphs.
 *
 * Runs on single posts only, after wpautop/do_shortcode (priority 10/11) so
 * paragraph tags already exist, but before the Cotlas Ads engine (priority 20)
 * so paragraph counting is not affected by other injected creatives.
 *
 * @param string $content Post content.
 * @return string
 */
function cotlas_inject_inside_content_ads( $content ) {
    if ( ! cotlas_generatepress_frontend_enabled() || ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() || ! is_string( $content ) ) {
        return $content;
    }

    $targets = array();

    for ( $slot = 1; $slot <= 4; $slot++ ) {
        $shortcode = cotlas_sanitize_ads_shortcode( get_option( 'cotlas_ads_inside_content_' . $slot, '' ) );
        $paragraph = absint( get_option( 'cotlas_ads_inside_paragraph_' . $slot, '' ) );

        if ( '' === $shortcode || $paragraph < 1 ) {
            continue;
        }

        $html = do_shortcode( $shortcode );

        if ( '' === trim( $html ) ) {
            continue;
        }

        $targets[ $paragraph ][] = '<div class="cotlas-ad-slot cotlas-ad-inside-content" data-cotlas-inside="' . $slot . '">' . $html . '</div>';
    }

    if ( empty( $targets ) ) {
        return $content;
    }

    return cotlas_inject_ads_after_paragraphs( $content, $targets );
}

/**
 * Insert ad markup after the requested non-empty paragraphs.
 *
 * Empty, whitespace-only and nbsp/zero-width-only paragraphs are skipped,
 * matching the paragraph numbering used by the Cotlas Ads engine.
 *
 * @param string $content Post content.
 * @param array  $targets Map of paragraph number => array of markup strings.
 * @return string
 */
function cotlas_inject_ads_after_paragraphs( $content, array $targets ) {
    $count  = 0;
    $result = preg_replace_callback(
        '/<p\b[^>]*>.*?<\/p>/is',
        function ( array $match ) use ( &$count, $targets ) {
            $text = html_entity_decode( wp_strip_all_tags( $match[0] ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
            $text = preg_replace( '/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text );

            if ( '' === $text ) {
                return $match[0];
            }

            $count++;

            return isset( $targets[ $count ] ) ? $match[0] . implode( '', $targets[ $count ] ) : $match[0];
        },
        $content
    );

    return is_string( $result ) ? $result : $content;
}
