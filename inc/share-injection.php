<?php
/**
 * Inject social share icons into GeneratePress entry meta.
 *
 * Top:    inside the post header entry meta (below the title), icons only.
 * Bottom: inside the post footer entry meta, just above the post navigation.
 *
 * Both are controlled from Cotlas Admin > Social Media > Social share box.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize the comma-separated networks field.
 *
 * @param string $value Raw field value.
 * @return string Comma-separated, deduplicated, whitelisted network slugs.
 */
function cotlas_sanitize_share_networks( $value ) {
    $allowed = array( 'facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'threads', 'copy', 'print' );

    $value = is_string( $value ) ? strtolower( $value ) : '';
    $parts = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

    if ( ! is_array( $parts ) ) {
        return '';
    }

    $parts = array_values( array_unique( array_intersect( $parts, $allowed ) ) );

    return implode( ',', $parts );
}

/**
 * Networks configured for one injected share position.
 *
 * @param string $position Either 'top' or 'bottom'.
 * @return string Comma-separated network slugs (may be empty).
 */
function cotlas_get_injected_share_networks( $position ) {
    $option = 'top' === $position
        ? 'cotlas_share_box_networks_top'
        : 'cotlas_share_box_networks_bottom';

    return cotlas_sanitize_share_networks(
        get_option( $option, 'facebook,twitter,linkedin,whatsapp,copy' )
    );
}

/**
 * Whether the [social_share] shortcode can render for a position.
 *
 * @param string $position Either 'top' or 'bottom'.
 * @return bool
 */
function cotlas_can_render_injected_share( $position ) {
    if ( ! get_option( 'cotlas_social_enabled', 1 ) ) {
        return false;
    }

    if ( ! shortcode_exists( 'social_share' ) ) {
        return false;
    }

    return '' !== cotlas_get_injected_share_networks( $position );
}

/**
 * Build the injected share block.
 *
 * @param string $position Either 'top' or 'bottom'.
 * @return string HTML, or an empty string when it should not render.
 */
function cotlas_get_injected_share_html( $position ) {
    if ( 'top' !== $position && 'bottom' !== $position ) {
        return '';
    }

    if ( ! cotlas_can_render_injected_share( $position ) ) {
        return '';
    }

    $is_top       = 'top' === $position;
    $show_names   = $is_top ? '0' : '1';
    $urlstructure = get_option( 'cotlas_share_box_shortlink', 0 ) ? 'short' : 'long';

    $shortcode_class = $is_top
        ? 'cotlas-social-share cotlas-social-share-top'
        : 'cotlas-social-share cotlas-social-share-footer';

    $wrapper_class = $is_top ? 'cws-social-share top' : 'cws-social-share';

    $shortcode = sprintf(
        '[social_share class="%1$s" size="24" show_names="%2$s" networks="%3$s" urlstructure="%4$s"]',
        esc_attr( $shortcode_class ),
        esc_attr( $show_names ),
        esc_attr( cotlas_get_injected_share_networks( $position ) ),
        esc_attr( $urlstructure )
    );

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $wrapper_class ); ?>">
        <?php if ( $is_top ) : ?>
            <span class="cws-social-share__label cotlas-share-icons">
                <span class="cws-social-share__icon" aria-hidden="true">
                    <svg aria-hidden="true" class="e-font-icon-svg e-fas-share-square" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path d="M568.482 177.448L424.479 313.433C409.3 327.768 384 317.14 384 295.985v-71.963c-144.575.97-205.566 35.113-164.775 171.353 4.483 14.973-12.846 26.567-25.006 17.33C155.252 383.105 120 326.488 120 269.339c0-143.937 117.599-172.5 264-173.312V24.012c0-21.174 25.317-31.768 40.479-17.448l144.003 135.988c10.02 9.463 10.028 25.425 0 34.896zM384 379.128V448H64V128h50.916a11.99 11.99 0 0 0 8.648-3.693c14.953-15.568 32.237-27.89 51.014-37.676C185.708 80.83 181.584 64 169.033 64H48C21.49 64 0 85.49 0 112v352c0 26.51 21.49 48 48 48h352c26.51 0 48-21.49 48-48v-88.806c0-8.288-8.197-14.066-16.011-11.302a71.83 71.83 0 0 1-34.189 3.377c-7.27-1.046-13.8 4.514-13.8 11.859z"></path></svg>
                </span>
                <span class="cws-social-share__text"><?php esc_html_e( 'Share', 'cotlas-admin' ); ?></span>
            </span>
        <?php else : ?>
            <h5 class="cws-social-share__title">
                <span class="cws-social-share__label"><?php esc_html_e( 'Share this article', 'cotlas-admin' ); ?></span>
                <span class="cws-social-share__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"></path></svg>
                </span>
            </h5>
        <?php endif; ?>
        <?php echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is built by this plugin. ?>
    </div>
    <?php
    return ob_get_clean();
}

if ( cotlas_is_generatepress_active() && ! function_exists( 'generate_post_meta' ) ) {
    /**
     * GeneratePress header entry meta, plus the optional top share icons.
     *
     * GeneratePress guards its own definition with function_exists(), so this
     * replaces it. When top injection is off the original markup is unchanged.
     */
    function generate_post_meta() {
        $share = '';

        if ( is_singular() && get_option( 'cotlas_share_box_inject_top' ) ) {
            $share = cotlas_get_injected_share_html( 'top' );
        }
        ?>
        <div class="entry-meta">
            <?php if ( '' !== $share ) : ?>
                <div class="cws-entry-meta">
                    <?php generate_posted_on(); ?>
                </div>
                <?php echo $share; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above. ?>
            <?php else : ?>
                <?php generate_posted_on(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

add_filter( 'generate_footer_entry_meta_items', 'cotlas_add_bottom_share_meta_item' );
/**
 * Insert a placeholder item just above the post navigation in the footer meta.
 *
 * @param array $items Footer entry meta items.
 * @return array
 */
function cotlas_add_bottom_share_meta_item( $items ) {
    if ( ! cotlas_is_generatepress_active() || ! is_singular() || ! get_option( 'cotlas_share_box_inject_bottom' ) ) {
        return $items;
    }

    if ( ! cotlas_can_render_injected_share( 'bottom' ) ) {
        return $items;
    }

    $items = (array) $items;
    $position = array_search( 'post-navigation', $items, true );

    if ( false !== $position ) {
        array_splice( $items, $position, 0, 'cotlas-share-bottom' );
    } else {
        $items[] = 'cotlas-share-bottom';
    }

    return $items;
}

add_action( 'generate_post_meta_items', 'cotlas_render_bottom_share_meta_item' );
/**
 * Render the bottom share block when its placeholder item is reached.
 *
 * @param string $item Current post meta item.
 */
function cotlas_render_bottom_share_meta_item( $item ) {
    if ( ! cotlas_is_generatepress_active() ) {
        return;
    }

    if ( 'cotlas-share-bottom' === $item ) {
        echo cotlas_get_injected_share_html( 'bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by this plugin.
    }
}
