<?php
/**
 * Cotlas_Trending_Categories and Cotlas_Most_Read shortcodes with 1-hour transient caching.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trending Categories Shortcode
 *
 * Displays top categories ranked by total post views (Post Views Counter plugin).
 * Automatically falls back to categories with the most recent posts when no view
 * data is available — making it safe to use on new/boilerplate installs.
 *
 * Usage: [trending_categories count="6" label=""]
 *
 * Attributes:
 *   count  - number of categories to show (default: 6, max: 20)
 *   label  - optional heading text shown above the list
 */
class Cotlas_Trending_Categories {

    public function __construct() {
        add_shortcode( 'trending_categories', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'count' => 6,
            'label' => '',
        ), $atts, 'trending_categories' );

        $count = max( 1, min( 20, intval( $atts['count'] ) ) );
        $label = sanitize_text_field( $atts['label'] );

        // Transient cache — 1 hour per unique count value
        $cache_key  = 'cotlas_trending_cats_' . $count;
        $categories = get_transient( $cache_key );

        if ( false === $categories ) {
            $categories = $this->get_trending_categories( $count );
            set_transient( $cache_key, $categories, HOUR_IN_SECONDS );
        }

        if ( empty( $categories ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="cotlas-trending-wrap">
            <?php if ( $label ) : ?>
                <p class="cotlas-trending-label"><?php echo esc_html( $label ); ?></p>
            <?php endif; ?>
            <ul class="cotlas-trending-list">
                <?php foreach ( $categories as $cat ) : ?>
                    <li class="cotlas-trending-item">
                        <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="cotlas-trending-link">
                            <span class="cotlas-trending-icon" aria-hidden="true"><?php echo $this->flame_icon(); ?></span>
                            <span class="cotlas-trending-name"><?php echo esc_html( $cat->name ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build the ranked category list.
     * 1. Try Post Views Counter view-based ranking.
     * 2. Fill any remaining slots from most-recent posts.
     */
    private function get_trending_categories( $count ) {
        $view_based = $this->categories_by_views( $count );

        if ( count( $view_based ) >= $count ) {
            return $view_based;
        }

        $existing_ids = wp_list_pluck( $view_based, 'term_id' );
        $recent_based = $this->categories_by_recency( $count - count( $view_based ), $existing_ids );

        return array_merge( $view_based, $recent_based );
    }

    /**
     * Get categories from posts ordered by Post Views Counter total views.
     * Returns an empty array when PVC has no data yet (all views = 0).
     */
    private function categories_by_views( $count ) {
        if ( ! function_exists( 'pvc_get_post_views' ) ) {
            return array();
        }

        $post_ids = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count * 8,
            'orderby'        => 'post_views',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        if ( empty( $post_ids ) ) {
            return array();
        }

        // Bail out if the top post has 0 views — no useful data yet
        if ( pvc_get_post_views( $post_ids[0] ) < 1 ) {
            return array();
        }

        return $this->posts_to_unique_categories( $post_ids, $count );
    }

    /**
     * Get categories from the most recently published posts.
     */
    private function categories_by_recency( $count, $exclude_term_ids = array() ) {
        $post_ids = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count * 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        return $this->posts_to_unique_categories( $post_ids, $count, $exclude_term_ids );
    }

    /**
     * Walk a list of post IDs and collect unique primary categories up to $limit.
     */
    private function posts_to_unique_categories( $post_ids, $limit, $exclude_term_ids = array() ) {
        $seen   = array_flip( $exclude_term_ids );
        $result = array();

        foreach ( $post_ids as $post_id ) {
            if ( count( $result ) >= $limit ) {
                break;
            }

            $primary = $this->get_primary_category( (int) $post_id );

            if ( ! $primary || isset( $seen[ $primary->term_id ] ) ) {
                continue;
            }

            $seen[ $primary->term_id ] = true;
            $result[]                  = $primary;
        }

        return $result;
    }

    /**
     * Return the primary category for a post.
     * Respects Yoast SEO primary category when available.
     * Skips "Uncategorized" unless it is the only option.
     */
    private function get_primary_category( $post_id ) {
        // Yoast SEO primary category
        if ( class_exists( 'WPSEO_Primary_Term' ) ) {
            $pt      = new WPSEO_Primary_Term( 'category', $post_id );
            $term_id = $pt->get_primary_term();

            if ( $term_id ) {
                $term = get_term( (int) $term_id, 'category' );
                if ( $term && ! is_wp_error( $term ) ) {
                    return $term;
                }
            }
        }

        $terms = get_the_terms( $post_id, 'category' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return null;
        }

        // Prefer any term that isn't "Uncategorized"
        foreach ( $terms as $term ) {
            if ( 'uncategorized' !== $term->slug ) {
                return $term;
            }
        }

        return $terms[0];
    }

    /**
     * Flame / trending SVG icon.
     */
    private function flame_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.66 11.2C17.43 10.9 17.15 10.64 16.89 10.38C16.22 9.78 15.46 9.35 14.82 8.72C13.33 7.26 13 4.85 13.95 3C13 3.23 12.17 3.75 11.46 4.32C8.87 6.4 7.85 10.07 9.07 13.22C9.11 13.32 9.15 13.42 9.15 13.55C9.15 13.77 9 13.97 8.8 14.05C8.57 14.15 8.33 14.09 8.14 13.93C8.08 13.88 8.04 13.83 8 13.76C6.87 12.33 6.69 10.28 7.45 8.64C5.78 10 4.87 12.3 5 14.47C5.06 14.97 5.12 15.47 5.29 15.97C5.43 16.57 5.7 17.17 6 17.7C7.08 19.43 8.95 20.67 10.96 20.92C13.1 21.19 15.39 20.8 17.03 19.32C18.86 17.66 19.5 15 18.56 12.72L18.43 12.46C18.22 12 17.66 11.2 17.66 11.2Z"/></svg>';
    }
}
add_action( 'plugins_loaded', function() { new Cotlas_Trending_Categories(); } );

/**
 * Most Read Articles Shortcode
 *
 * Displays top posts ordered by Post Views Counter total views.
 * Falls back to most recent posts when no view data is available.
 *
 * Usage: [most_read count="3"]
 *
 * Attributes:
 *   count - number of articles to show (default: 3, max: 10)
 */
class Cotlas_Most_Read {

    public function __construct() {
        add_shortcode( 'most_read', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $atts  = shortcode_atts( array( 'count' => 3 ), $atts, 'most_read' );
        $count = max( 1, min( 10, intval( $atts['count'] ) ) );

        $cache_key = 'cotlas_most_read_' . $count;
        $posts     = get_transient( $cache_key );

        if ( false === $posts ) {
            $posts = $this->get_posts( $count );
            set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
        }

        if ( empty( $posts ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="cotlas-most-read-wrap">
            <ol class="cotlas-most-read-list">
                <?php foreach ( $posts as $i => $post ) :
                    $thumb  = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
                    $title  = get_the_title( $post->ID );
                    $link   = get_permalink( $post->ID );
                    $cat    = $this->get_primary_cat_name( $post->ID );
                ?>
                <li class="cotlas-most-read-item">
                    <a href="<?php echo esc_url( $link ); ?>" class="cotlas-most-read-link">
                        <?php if ( $thumb ) : ?>
                            <div class="cotlas-most-read-thumb">
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" width="64" height="64">
                            </div>
                        <?php else : ?>
                            <div class="cotlas-most-read-thumb cotlas-most-read-thumb--no-img">
                                <span class="cotlas-most-read-num"><?php echo esc_html( $i + 1 ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="cotlas-most-read-body">
                            <?php if ( $cat ) : ?>
                                <span class="cotlas-most-read-cat"><?php echo esc_html( $cat ); ?></span>
                            <?php endif; ?>
                            <p class="cotlas-most-read-title"><?php echo esc_html( $title ); ?></p>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_posts( $count ) {
        $has_views = function_exists( 'pvc_get_post_views' );
        $orderby   = 'date';

        if ( $has_views ) {
            // Check if we have any real view data before ordering by views
            $probe = get_posts( array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'post_views',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ) );
            if ( ! empty( $probe ) && pvc_get_post_views( $probe[0] ) > 0 ) {
                $orderby = 'post_views';
            }
        }

        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => $orderby,
            'order'          => 'DESC',
        ) );
    }

    private function get_primary_cat_name( $post_id ) {
        if ( class_exists( 'WPSEO_Primary_Term' ) ) {
            $pt      = new WPSEO_Primary_Term( 'category', $post_id );
            $term_id = $pt->get_primary_term();
            if ( $term_id ) {
                $term = get_term( (int) $term_id, 'category' );
                if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
                    return $term->name;
                }
            }
        }

        $terms = get_the_terms( $post_id, 'category' );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }

        foreach ( $terms as $term ) {
            if ( 'uncategorized' !== $term->slug ) {
                return $term->name;
            }
        }

        return '';
    }
}
add_action( 'plugins_loaded', function() { new Cotlas_Most_Read(); } );
