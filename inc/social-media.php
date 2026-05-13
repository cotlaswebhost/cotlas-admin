<?php
/**
 * CotlasSocialMedia class (site social links widget/shortcode) and
 * Social Share shortcode ([social_share]).
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

class CotlasSocialMedia {
    private $social_platforms;
    public function __construct() {
        // Register Dynamic URL Filter
        add_filter( 'generateblocks_dynamic_url_output', array($this, 'gb_dynamic_urls'), 10, 3 );

        $this->social_platforms = array(
            'facebook' => array(
                'name' => 'Facebook',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path></svg>'
            ),
            'twitter' => array(
                'name' => 'X (Twitter)',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.982 10.622 20.54 3h-1.554l-5.693 6.618L8.745 3H3.5l6.876 10.007L3.5 21h1.554l6.012-6.989L15.868 21h5.245l-7.131-10.378Zm-2.128 2.474-.697-.997-5.543-7.93H8l4.474 6.4.697.996 5.815 8.318h-2.387l-4.745-6.787Z"></path></svg>'
            ),
            'instagram' => array(
                'name' => 'Instagram',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12,4.622c2.403,0,2.688,0.009,3.637,0.052c0.877,0.04,1.354,0.187,1.671,0.31c0.42,0.163,0.72,0.358,1.035,0.673 c0.315,0.315,0.51,0.615,0.673,1.035c0.123,0.317,0.27,0.794,0.31,1.671c0.043,0.949,0.052,1.234,0.052,3.637 s-0.009,2.688-0.052,3.637c-0.04,0.877-0.187,1.354-0.31,1.671c-0.163,0.42-0.358,0.72-0.673,1.035 c-0.315,0.315-0.615,0.51-1.035,0.673c-0.317,0.123-0.794,0.27-1.671,0.31c-0.949,0.043-1.233,0.052-3.637,0.052 s-2.688-0.009-3.637-0.052c-0.877-0.04-1.354-0.187-1.671-0.31c-0.42-0.163-0.72-0.358-1.035-0.673 c-0.315-0.315-0.51-0.615-0.673-1.035c-0.123-0.317-0.27-0.794-0.31-1.671C4.631,14.688,4.622,14.403,4.622,12 s0.009-2.688,0.052-3.637c-0.04-0.877,0.187-1.354,0.31-1.671c0.163-0.42,0.358-0.72,0.673-1.035 c0.315-0.315,0.615-0.51,1.035-0.673c0.317-0.123,0.794-0.27,1.671-0.31C9.312,4.631,9.597,4.622,12,4.622 M12,3 C9.556,3,9.249,3.01,8.289,3.054C7.331,3.098,6.677,3.25,6.105,3.472C5.513,3.702,5.011,4.01,4.511,4.511 c-0.5,0.5-0.808,1.002-1.038,1.594C3.25,6.677,3.098,7.331,3.054,8.289C3.01,9.249,3,9.556,3,12c0,2.444,0.01,2.751,0.054,3.711 c0.044,0.958,0.196,1.612,0.418,2.185c0.23,0.592,0.538,1.094,1.038,1.594c0.5,0.5,1.002,0.808,1.594,1.038 c0.572,0.222,1.227,0.375,2.185,0.418C9.249,20.99,9.556,21,12,21s2.751-0.01,3.711-0.054c0.958-0.044,1.612-0.196,2.185-0.418 c0.592-0.23,1.094-0.538,1.594-1.038c0.5-0.5,0.808-1.002,1.038-1.594c0.222-0.572,0.375-1.227,0.418-2.185 C20.99,14.751,21,14.444,21,12s-0.01-2.751-0.054-3.711c-0.044-0.958-0.196-1.612-0.418-2.185c-0.23-0.592-0.538-1.094-1.038-1.594 c-0.5-0.5-1.002-0.808-1.594-1.038c-0.572-0.222-1.227-0.375-2.185-0.418C14.751,3.01,14.444,3,12,3L12,3z M12,7.378 c-2.552,0-4.622,2.069-4.622,4.622S9.448,16.622,12,16.622s4.622-2.069,4.622-4.622S14.552,7.378,12,7.378z M12,15 c-1.657,0-3-1.343-3-3s1.343-3,3-3s3,1.343,3,3S13.657,15,12,15z M16.804,6.116c-0.596,0-1.08,0.484-1.08,1.08 s0.484,1.08,1.08,1.08c0.596,0,1.08-0.4"></path></svg>'
            ),
            'youtube' => array(
                'name' => 'YouTube',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.8,8.001c0,0-0.195-1.378-0.795-1.985c-0.76-0.797-1.613-0.801-2.004-0.847c-2.799-0.202-6.997-0.202-6.997-0.202 h-0.009c0,0-4.198,0-6.997,0.202C4.608,5.216,3.756,5.22,2.995,6.016C2.395,6.623,2.2,8.001,2.2,8.001S2,9.62,2,11.238v1.517 c0,1.618,0.2,3.237,0.2,3.237s0.195,1.378,0.795,1.985c0.761,0.797,1.76,0.771,2.205,0.855c1.6,0.153,6.8,0.201,6.8,0.201 s4.203-0.006,7.001-0.209c0.391-0.047,1.243-0.051,2.004-0.847c0.6-0.607,0.795-1.985,0.795-1.985s0.2-1.618,0.2-3.237v-1.517 C22,9.62,21.8,8.001,21.8,8.001z M9.935,14.594l-0.001-5.62l5.404,2.82L9.935,14.594z"></path></svg>'
            ),
            'linkedin' => array(
                'name' => 'LinkedIn',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19.7,3H4.3C3.582,3,3,3.582,3,4.3v15.4C3,20.418,3.582,21,4.3,21h15.4c0.718,0,1.3-0.582,1.3-1.3V4.3 C21,3.582,20.418,3,19.7,3z M8.339,18.338H5.667v-8.59h2.672V18.338z M7.004,8.574c-0.857,0-1.549-0.694-1.549-1.548 c0-0.855,0.691-1.548,1.549-1.548c0.854,0,1.547,0.694,1.547,1.548C8.551,7.881,7.858,8.574,7.004,8.574z M18.339,18.338h-2.669 v-4.177c0-0.996-0.017-2.278-1.387-2.278c-1.389,0-1.601,1.086-1.601,2.206v4.249h-2.667v-8.59h2.559v1.174h0.037 c0.356-0.675,1.227-1.387,2.526-1.387c2.703,0,3.203,1.779,3.203,4.092V18.338z"></path></svg>'
            ),
            'threads' => array(
                'name' => 'Threads',
                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10c3.86 0 7.238-2.19 8.928-5.387l-2.2-1.151C17.91 17.8 15.145 19 12 19c-3.866 0-7-3.134-7-7s3.134-7 7-7c3.16 0 5.926 1.206 6.73 3.555l2.2-1.151C19.246 4.189 15.865 2 12 2zm0 5c-2.21 0-4 1.567-4 3.5S9.79 14 12 14c1.657 0 3-1.12 3-2.5 0-1.379-1.343-2.5-3-2.5z"></path></svg>'
            ),
            'whatsapp' => array(
                'name' => 'WhatsApp',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" aria-label="WhatsApp" role="img" viewBox="0 0 512 512" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><rect width="512" height="512" rx="15%" fill="#25d366"></rect><path fill="#25d366" stroke="#ffffff" stroke-width="26" d="M123 393l14-65a138 138 0 1150 47z"></path><path fill="#ffffff" d="M308 273c-3-2-6-3-9 1l-12 16c-3 2-5 3-9 1-15-8-36-17-54-47-1-4 1-6 3-8l9-14c2-2 1-4 0-6l-12-29c-3-8-6-7-9-7h-8c-2 0-6 1-10 5-22 22-13 53 3 73 3 4 23 40 66 59 32 14 39 12 48 10 11-1 22-10 27-19 1-3 6-16 2-18"></path></g></svg>'
            )
        );
        add_shortcode('cotlas_social', array($this, 'social_shortcode'));
    }

    // GenerateBlocks Dynamic URL Support for Options
    public function gb_dynamic_urls( $url, $attributes, $block ) {
        if ( ! empty( $attributes['dynamicLinkType'] ) && $attributes['dynamicLinkType'] === 'post-meta' ) {
            $meta_key = isset( $attributes['linkMetaFieldName'] ) ? $attributes['linkMetaFieldName'] : '';
            
            $allowed_keys = [
                'cotlas_company_phone', 'cotlas_company_email', 'cotlas_company_whatsapp',
                'cotlas_social_facebook', 'cotlas_social_twitter', 'cotlas_social_youtube',
                'cotlas_social_instagram', 'cotlas_social_linkedin', 'cotlas_social_threads'
            ];

            if ( in_array( $meta_key, $allowed_keys ) ) {
                return get_option( $meta_key );
            }
        }
        return $url;
    }

    public function social_shortcode($atts) {
        $atts = shortcode_atts(array(
            'size' => '24',
            'class' => '',
            'show_names' => 'false',
            'networks' => ''
        ), $atts);
        $links = array(
            'facebook'  => get_option('cotlas_social_facebook'),
            'twitter'   => get_option('cotlas_social_twitter'),
            'instagram' => get_option('cotlas_social_instagram'),
            'youtube'   => get_option('cotlas_social_youtube'),
            'linkedin'  => get_option('cotlas_social_linkedin'),
            'threads'   => get_option('cotlas_social_threads'),
            'whatsapp'  => get_option('cotlas_company_whatsapp'),
        );
        if (!empty($atts['networks'])) {
            $requested = array_filter(array_map('trim', explode(',', strtolower($atts['networks']))));
            $normalized = array_map(function($n) { return $n === 'x' ? 'twitter' : $n; }, $requested);
            $links = array_filter($links, function($key) use ($normalized) {
                return in_array($key, $normalized, true);
            }, ARRAY_FILTER_USE_KEY);
        }
        $has = false;
        foreach ($links as $u) { if (!empty($u)) { $has = true; break; } }
        if (!$has) return '';
        $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);
        $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
        ob_start();
        ?>
        <div class="cotlas-social-links <?php echo esc_attr($atts['class']); ?>">
            <ul class="social-icons-list">
                <?php foreach ($links as $platform => $url): ?>
                    <?php
                        if (empty($url) || !isset($this->social_platforms[$platform])) { continue; }
                        if ($platform === 'whatsapp') {
                            $clean = preg_replace('/\D+/', '', $url);
                            $url = $clean ? ('https://wa.me/' . $clean) : esc_url($url);
                        }
                        $icon = preg_replace('/width="\d+"/', 'width="' . $size . '"', $this->social_platforms[$platform]['icon']);
                        $icon = preg_replace('/height="\d+"/', 'height="' . $size . '"', $icon);
                    ?>
                    <li class="social-link social-link-<?php echo esc_attr($platform); ?>">
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($this->social_platforms[$platform]['name'] . ' (opens in a new window)'); ?>">
                            <?php echo $icon; ?>
                            <?php if ($show_names): ?>
                                <span class="social-platform-name"><?php echo esc_html($this->social_platforms[$platform]['name']); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_action('plugins_loaded', function() { new CotlasSocialMedia(); });
add_action('plugins_loaded', function() { new CotlasSocialMedia(); });

// Social Share Shortcode - FIXED VERSION
function cotlas_social_share_shortcode($atts) {
    $atts = shortcode_atts(array(
        'class'   => 'cotlas-social-share,cotlas-social-share-top,cotlas-social-share-aside,cotlas-social-share-footer',
        'networks' => 'facebook,twitter,linkedin,whatsapp,telegram,pinterest,reddit,threads,print',
        'size' => '24',
        'show_names' => 'false'
    ), $atts, 'social_share');

    $post_url   = urlencode(get_permalink());
    $post_title = urlencode(get_the_title());
    $post_image = has_post_thumbnail() ? wp_get_attachment_url(get_post_thumbnail_id()) : '';

    $networks = explode(',', $atts['networks']);
    $class    = esc_attr($atts['class']);
    $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
    $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    ?>
    <div class="<?php echo $class; ?>">
        <?php if (in_array('facebook', $networks)) : ?>
            <a href="https://www.facebook.com/sharer.php?u=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Share on Facebook (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-facebook" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z"></path></svg>
                <span class="screen-reader-text">Facebook</span>
                <?php if ($show_names): ?><span class="social-platform-name">Facebook</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('twitter', $networks)) : ?>
            <a href="https://x.com/share?text=<?php echo $post_title; ?>&url=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="twitter" aria-label="Share on Twitter (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-x-twitter" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path></svg>
                <span class="screen-reader-text">Twitter</span>
                <?php if ($show_names): ?><span class="social-platform-name">Twitter</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('whatsapp', $networks)) : ?>
            <a href="https://api.whatsapp.com/send?text=<?php echo $post_title; ?>%20<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="whatsapp" aria-label="Share on WhatsApp (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-whatsapp" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path></svg>
                <span class="screen-reader-text">WhatsApp</span>
                <?php if ($show_names): ?><span class="social-platform-name">WhatsApp</span><?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if (in_array('linkedin', $networks)) : ?>
            <a href="https://www.linkedin.com/shareArticle?url=<?php echo $post_url; ?>&title=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="linkedin" aria-label="Share on LinkedIn (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-linkedin" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path></svg>
                <span class="screen-reader-text">LinkedIn</span>
                <?php if ($show_names): ?><span class="social-platform-name">LinkedIn</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('pinterest', $networks)) : ?>
            <a href="https://pinterest.com/pin/create/button/?url=<?php echo $post_url; ?>&media=<?php echo $post_image; ?>&description=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="pinterest" aria-label="Share on Pinterest (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 21.5c-5.238 0-9.5-4.262-9.5-9.5S6.762 2.5 12 2.5s9.5 4.262 9.5 9.5-4.262 9.5-9.5 9.5z"/><path d="M12.5 7.5c-2.5 0-4.5 2-4.5 4.5 0 1.5 1 2.5 2 3 0 0 .5-2 .5-2.5 0-.5-.5-1-.5-1.5 0-1.5 1-2.5 2-2.5 1 0 1.5.5 1.5 1.5 0 1-1 3-1 4.5 0 1 .5 1.5 1.5 1.5 2 0 3-2.5 3-5 0-2-1.5-4-4-4z"/></svg>
                <span class="screen-reader-text">Pinterest</span>
                <?php if ($show_names): ?><span class="social-platform-name">Pinterest</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('reddit', $networks)) : ?>
            <a href="https://reddit.com/submit?url=<?php echo $post_url; ?>&title=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="reddit" aria-label="Share on Reddit (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>
                <span class="screen-reader-text">Reddit</span>
                <?php if ($show_names): ?><span class="social-platform-name">Reddit</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('threads', $networks)) : ?>
            <a href="<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="threads" aria-label="Share on Threads (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-threads" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M331.5 235.7c2.2 .9 4.2 1.9 6.3 2.8c29.2 14.1 50.6 35.2 61.8 61.4c15.7 36.5 17.2 95.8-30.3 143.2c-36.2 36.2-80.3 52.5-142.6 53h-.3c-70.2-.5-124.1-24.1-160.4-70.2c-32.3-41-48.9-98.1-49.5-169.6V256v-.2C17 184.3 33.6 127.2 65.9 86.2C102.2 40.1 156.2 16.5 226.4 16h.3c70.3 .5 124.9 24 162.3 69.9c18.4 22.7 32 50 40.6 81.7l-40.4 10.8c-7.1-25.8-17.8-47.8-32.2-65.4c-29.2-35.8-73-54.2-130.5-54.6c-57 .5-100.1 18.8-128.2 54.4C72.1 146.1 58.5 194.3 58 256c.5 61.7 14.1 109.9 40.3 143.3c28 35.6 71.2 53.9 128.2 54.4c51.4-.4 85.4-12.6 113.7-40.9c32.3-32.2 31.7-71.8 21.4-95.9c-6.1-14.2-17.1-26-31.9-34.9c-3.7 26.9-11.8 48.3-24.7 64.8c-17.1 21.8-41.4 33.6-72.7 35.3c-23.6 1.3-46.3-4.4-63.9-16c-20.8-13.8-33-34.8-34.3-59.3c-2.5-48.3 35.7-83 95.2-86.4c21.1-1.2 40.9-.3 59.2 2.8c-2.4-14.8-7.3-26.6-14.6-35.2c-10-11.7-25.6-17.7-46.2-17.8H227c-16.6 0-39 4.6-53.3 26.3l-34.4-23.6c19.2-29.1 50.3-45.1 87.8-45.1h.8c62.6 .4 99.9 39.5 103.7 107.7l-.2 .2zm-156 68.8c1.3 25.1 28.4 36.8 54.6 35.3c25.6-1.4 54.6-11.4 59.5-73.2c-13.2-2.9-27.8-4.4-43.4-4.4c-4.8 0-9.6 .1-14.4 .4c-42.9 2.4-57.2 23.2-56.2 41.8l-.1 .1z"/></svg>
                <span class="screen-reader-text">Threads</span>
                <?php if ($show_names): ?><span class="social-platform-name">Threads</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('telegram', $networks)) : ?>
            <a href="https://t.me/share/url?url=<?php echo $post_url; ?>&text=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="telegram" aria-label="Share on Telegram (opens in new window)">
                <svg aria-hidden="true" class="e-font-icon-svg e-fab-telegram" viewBox="0 0 496 512" xmlns="http://www.w3.org/2000/svg" width="<?php echo $size; ?>" height="<?php echo $size; ?>"><path d="M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm121.8 169.9l-40.7 191.8c-3 13.6-11.1 16.9-22.4 10.5l-62-45.7-29.9 28.8c-3.3 3.3-6.1 6.1-12.5 6.1l4.4-63.1 114.9-103.8c5-4.4-1.1-6.9-7.7-2.5l-142 89.4-61.2-19.1c-13.3-4.2-13.6-13.3 2.8-19.7l239.1-92.2c11.1-4 20.8 2.7 17.2 19.5z"/></svg>
                <span class="screen-reader-text">Telegram</span>
                <?php if ($show_names): ?><span class="social-platform-name">Telegram</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (in_array('print', $networks)) : ?>
            <a href="javascript:window.print()" class="print" aria-label="Print this page">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                <span class="screen-reader-text">Print</span>
                <?php if ($show_names): ?><span class="social-platform-name">Print</span><?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('social_share', 'cotlas_social_share_shortcode');
