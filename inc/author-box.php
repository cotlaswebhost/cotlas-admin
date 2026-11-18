<?php
/**
 * Author box injection on single posts.
 *
 * Replaces the former GeneratePress Element (post 461) with configurable
 * output controlled from Cotlas Admin → User Settings → Feature Toggles.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the author box should render for the current request.
 *
 * @return bool
 */
function cotlas_author_box_is_enabled() {
    return get_option( 'cotlas_user_profile_enabled' )
        && get_option( 'cotlas_author_box_enabled' )
        && is_singular( 'post' );
}

/**
 * Build the author box markup.
 *
 * Mirrors the original GeneratePress Element markup and classes:
 * .cws-author > .cws-avatar + .cws-author-info.
 *
 * @param int $author_id Author user ID.
 * @return string
 */
function cotlas_get_author_box_html( $author_id ) {
    $author_id = absint( $author_id );

    if ( ! $author_id ) {
        return '';
    }

    $display_name = get_the_author_meta( 'display_name', $author_id );
    $description  = get_the_author_meta( 'description', $author_id );
    $archive_url  = get_author_posts_url( $author_id );
    $avatar_url   = get_avatar_url( $author_id, array( 'size' => 96 ) );

    ob_start();
    ?>
    <div class="cws-author">
        <div class="cws-avatar">
            <?php if ( $avatar_url ) : ?>
                <img class="cws-avatar-img" src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" width="96" height="96" loading="lazy" decoding="async">
            <?php endif; ?>
        </div>
        <div class="cws-author-info">
            <div class="cws-author-top">
                <span class="cws-author-name">
                    <a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $display_name ); ?></a>
                </span>
                <?php if ( shortcode_exists( 'author_social_links' ) ) : ?>
                    <div class="cws-author-social">
                        <?php echo do_shortcode( '[author_social_links class="cws-social-links"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is trusted plugin markup. ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="cws-author-bottom">
                <?php if ( '' !== $description ) : ?>
                    <span class="cws-author-bio"><?php echo wp_kses_post( $description ); ?></span>
                <?php endif; ?>
                <a class="cws-author-link" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'View all Post', 'cotlas-admin' ); ?></a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action( 'generate_after_content', 'cotlas_render_author_box', 10 );
/**
 * Output the author box after the single post content.
 */
function cotlas_render_author_box() {
    if ( ! cotlas_author_box_is_enabled() ) {
        return;
    }

    $author_id = get_post_field( 'post_author', get_the_ID() );
    $html      = cotlas_get_author_box_html( $author_id );

    if ( '' === $html ) {
        return;
    }

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in this plugin.
}
