<?php
/**
 * Custom avatar upload, social share shortcode, and user contact methods.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

// ── Contact-method fields: always runs so it can also REMOVE Yoast's fields ──
// Priority 20 → executes after Yoast SEO (priority 10), letting us unset its additions.
add_filter( 'user_contactmethods', function( $methods ) {
    $enabled = get_option( 'cotlas_user_profile_enabled' )
               && get_option( 'cotlas_user_social_links_enabled' );

    if ( $enabled ) {
        $methods['facebook']  = __( 'Facebook profile URL',       'cotlas' );
        $methods['twitter']   = __( 'X / Twitter profile URL',    'cotlas' );
        $methods['instagram'] = __( 'Instagram profile URL',      'cotlas' );
        $methods['linkedin']  = __( 'LinkedIn profile URL',       'cotlas' );
        $methods['youtube']   = __( 'YouTube profile URL',        'cotlas' );
        $methods['pinterest'] = __( 'Pinterest profile URL',      'cotlas' );
    } else {
        // Remove our fields AND Yoast SEO's overlapping social-network keys.
        foreach ( array(
            'facebook', 'twitter', 'instagram', 'instagram_profile',
            'linkedin', 'linkedin_profile', 'youtube', 'youtube_profile',
            'pinterest', 'pinterest_profile', 'myspace', 'myspace_profile',
            'soundcloud', 'tumblr', 'wikipedia', 'googleplus_profile',
        ) as $key ) {
            unset( $methods[ $key ] );
        }
    }
    return $methods;
}, 20 );

// ── Everything below requires the master toggle ────────────────────────────
if ( ! get_option( 'cotlas_user_profile_enabled' ) ) { return; }

// Avatar features – respect the avatar sub-toggle
if ( get_option( 'cotlas_user_avatar_enabled' ) ) {
    add_action( 'show_user_profile',        'gp_add_custom_avatar_field' );
    add_action( 'edit_user_profile',        'gp_add_custom_avatar_field' );
    add_action( 'personal_options_update',  'gp_save_custom_avatar' );
    add_action( 'edit_user_profile_update', 'gp_save_custom_avatar' );
    add_filter( 'get_avatar',               'gp_custom_avatar', 10, 5 );
}

function gp_add_custom_avatar_field($user) {
    ?>
    <h3>Custom Avatar</h3>
    <table class="form-table">
        <tr>
            <th><label for="custom_avatar">Avatar</label></th>
            <td>
                <?php 
                $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
                if ($custom_avatar) {
                    echo wp_get_attachment_image($custom_avatar, [96, 96]); 
                } else {
                    echo get_avatar($user->ID, 96);
                }
                ?>
                <br />
                <input type="file" name="custom_avatar" id="custom_avatar" />
                <p class="description">Upload a custom avatar for this user.</p>
            </td>
        </tr>
    </table>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('form#your-profile, form#edituser').attr('enctype', 'multipart/form-data');
        });
    </script>
    <?php
}

// Save custom avatar
function gp_save_custom_avatar($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    if (!empty($_FILES['custom_avatar']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('custom_avatar', 0);
        
        if (!is_wp_error($attachment_id)) {
            // delete old avatar if exists
            $old_avatar = get_user_meta($user_id, 'custom_avatar', true);
            if (!empty($old_avatar) && is_numeric($old_avatar)) {
                wp_delete_attachment($old_avatar);
            }
            update_user_meta($user_id, 'custom_avatar', $attachment_id);
        }
    }
}

// Override get_avatar to use custom avatar
function gp_custom_avatar($avatar, $id_or_email, $size, $default, $alt) {
    $user = false;
    
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int) $id_or_email);
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user = get_user_by('id', (int) $id_or_email->user_id);
    } elseif (is_object($id_or_email) && isset($id_or_email->comment_author_email)) {
        // Handle WP_Comment objects - extract email from comment
        $user = get_user_by('email', $id_or_email->comment_author_email);
    } else {
        $user = get_user_by('email', $id_or_email);
    }
    
    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            $custom_avatar_src = wp_get_attachment_image_src($custom_avatar, [$size, $size]);
            if ($custom_avatar_src) {
                $avatar = sprintf(
                    '<img src="%s" width="%d" height="%d" alt="%s" class="avatar avatar-%d photo" />',
                    esc_url($custom_avatar_src[0]),
                    esc_attr($size),
                    esc_attr($size),
                    esc_attr($alt),
                    esc_attr($size)
                );
            }
        }
    }
    
    return $avatar;
}

// Author Social Links Shortcode - FIXED VERSION
function cotlas_author_social_links_shortcode($atts) {
    $atts = shortcode_atts(array(
        'class' => 'cotlas-author-social-links',
        'size' => '24',
        'show_names' => 'false',
        'networks' => ''
    ), $atts, 'author_social_links');
    
    $author_id = get_the_author_meta('ID');
    if (!$author_id) return '';
    
    // Get social media URLs from user meta
    $social_links = array(
        'facebook' => get_the_author_meta('facebook', $author_id),
        'twitter' => get_the_author_meta('twitter', $author_id),
        'instagram' => get_the_author_meta('instagram', $author_id),
        'linkedin' => get_the_author_meta('linkedin', $author_id),
        'youtube' => get_the_author_meta('youtube', $author_id),
        'pinterest' => get_the_author_meta('pinterest', $author_id),
        'email' => get_the_author_meta('user_email', $author_id),
    );
    
    // Fix Twitter URL
    if (!empty($social_links['twitter'])) {
        $twitter_url = $social_links['twitter'];
        
        if (strpos($twitter_url, 'twitter.com') !== false) {
            $twitter_url = str_replace('twitter.com', 'x.com', $twitter_url);
        }
        elseif (!preg_match('/^https?:\/\//', $twitter_url)) {
            $twitter_url = 'https://x.com/' . ltrim($twitter_url, '@');
        }
        
        $social_links['twitter'] = $twitter_url;
    }
    
    $class = esc_attr($atts['class']);
    $size = intval($atts['size']) > 0 ? intval($atts['size']) : 24;
    $show_names = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);
    $allowed = array_keys($social_links);
    if (!empty($atts['networks'])) {
        $requested = array_filter(array_map('trim', explode(',', strtolower($atts['networks']))));
        $normalized = array_map(function($n) { return $n === 'x' ? 'twitter' : $n; }, $requested);
        $allowed = $normalized;
    }
    
    ob_start();
    ?>
    <div class="<?php echo $class; ?>">
        <?php if (!empty($social_links['facebook']) && in_array('facebook', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['facebook']); ?>" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Visit author's Facebook profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span class="screen-reader-text">Facebook</span>
                <?php if ($show_names): ?><span class="social-platform-name">Facebook</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['twitter']) && in_array('twitter', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['twitter']); ?>" target="_blank" rel="noopener noreferrer" class="twitter" aria-label="Visit author's Twitter profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                </svg>
                <span class="screen-reader-text">Twitter</span>
                <?php if ($show_names): ?><span class="social-platform-name">Twitter</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['instagram']) && in_array('instagram', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['instagram']); ?>" target="_blank" rel="noopener noreferrer" class="instagram" aria-label="Visit author's Instagram profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <span class="screen-reader-text">Instagram</span>
                <?php if ($show_names): ?><span class="social-platform-name">Instagram</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['linkedin']) && in_array('linkedin', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="linkedin" aria-label="Visit author's LinkedIn profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
                <span class="screen-reader-text">LinkedIn</span>
                <?php if ($show_names): ?><span class="social-platform-name">LinkedIn</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['youtube']) && in_array('youtube', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['youtube']); ?>" target="_blank" rel="noopener noreferrer" class="youtube" aria-label="Visit author's YouTube channel (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
                <span class="screen-reader-text">YouTube</span>
                <?php if ($show_names): ?><span class="social-platform-name">YouTube</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['email']) && in_array('email', $allowed, true)) : ?>
            <a href="mailto:<?php echo esc_attr($social_links['email']); ?>" class="email" aria-label="Email the author">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 12.713l-11.985-9.713h23.97l-11.985 9.713zm0 2.574l-12-9.725v15.438h24v-15.438l-12 9.725z"/>
                </svg>
                <span class="screen-reader-text">Email</span>
                <?php if ($show_names): ?><span class="social-platform-name">Email</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($social_links['pinterest']) && in_array('pinterest', $allowed, true)) : ?>
            <a href="<?php echo esc_url($social_links['pinterest']); ?>" target="_blank" rel="noopener noreferrer" class="pinterest" aria-label="Visit author's Pinterest profile (opens in new window)">
                <svg width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 21.5c-5.238 0-9.5-4.262-9.5-9.5S6.762 2.5 12 2.5s9.5 4.262 9.5 9.5-4.262 9.5-9.5 9.5z"/>
                    <path d="M12.5 7.5c-2.5 0-4.5 2-4.5 4.5 0 1.5 1 2.5 2 3 0 0 .5-2 .5-2.5 0-.5-.5-1-.5-1.5 0-1.5 1-2.5 2-2.5 1 0 1.5.5 1.5 1.5 0 1-1 3-1 4.5 0 1 .5 1.5 1.5 1.5 2 0 3-2.5 3-5 0-2-1.5-4-4-4z"/>
                </svg>
                <span class="screen-reader-text">Pinterest</span>
                <?php if ($show_names): ?><span class="social-platform-name">Pinterest</span><?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('author_social_links', 'cotlas_author_social_links_shortcode');

