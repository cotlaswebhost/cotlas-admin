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
    echo cotlas_get_related_posts_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this plugin.
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
