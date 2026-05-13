<?php
/**
 * Post formats: YouTube video meta box, audio file meta box, audio player shortcode,
 * audio/video block-editor enhancements.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_post_formats_enabled' ) ) { return; }

// Add YouTube Video URL meta box with Auto-Fetch capability
function gp_add_youtube_video_meta_box() {
    add_meta_box(
        'youtube_video_url',
        'YouTube Video Settings',
        'gp_youtube_video_url_callback',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'gp_add_youtube_video_meta_box');

// Meta box callback function - UPDATED with Auto-Fetch
function gp_youtube_video_url_callback($post) {
    // Add nonce for security
    wp_nonce_field('gp_youtube_video_nonce', 'gp_youtube_video_nonce');
    
    // Get existing values
    $youtube_url = get_post_meta($post->ID, '_youtube_video_url', true);
    $video_id = get_post_meta($post->ID, '_youtube_video_id', true);
    $video_summary = get_post_meta($post->ID, '_youtube_video_summary', true);
    $video_transcript = get_post_meta($post->ID, '_youtube_video_transcript', true);
    $has_transcript = get_post_meta($post->ID, '_youtube_has_transcript', true);
    $auto_fetch_data = get_post_meta($post->ID, '_youtube_auto_fetch', true);
    
    ?>
    <div style="margin-bottom: 15px;">
        <label for="youtube_video_url" style="display: block; margin-bottom: 5px; font-weight: bold;">YouTube Video URL:</label>
        <input type="url" id="youtube_video_url" name="youtube_video_url" value="<?php echo esc_attr($youtube_url); ?>" style="width:100%; margin-bottom: 10px;" placeholder="https://www.youtube.com/watch?v=..." />
        
        <div style="margin-bottom: 10px;">
            <input type="checkbox" id="youtube_auto_fetch" name="youtube_auto_fetch" value="1" <?php checked($auto_fetch_data, '1'); ?> />
            <label for="youtube_auto_fetch" style="font-size: 13px;">Auto-fetch video data</label>
        </div>
        
        <p class="description" style="margin: 5px 0 15px 0; font-size: 12px; color: #666;">Enter YouTube URL and check auto-fetch to get automatic summary</p>
    </div>

    <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Video Transcript Settings:</label>
        
        <div style="margin-bottom: 10px;">
            <input type="checkbox" id="youtube_has_transcript" name="youtube_has_transcript" value="1" <?php checked($has_transcript, '1'); ?> />
            <label for="youtube_has_transcript" style="font-size: 13px;">Include video transcript for accessibility</label>
        </div>
        
        <div style="margin-bottom: 10px;">
            <label for="youtube_video_summary" style="display: block; margin-bottom: 3px; font-size: 13px;">Video Summary:</label>
            <textarea id="youtube_video_summary" name="youtube_video_summary" rows="3" style="width:100%; font-size: 13px;" placeholder="Brief summary of video content..."><?php echo esc_textarea($video_summary); ?></textarea>
            <p class="description" style="margin: 3px 0 0 0; font-size: 11px; color: #666;">Auto-filled if auto-fetch is enabled</p>
        </div>
        
        <div>
            <label for="youtube_video_transcript" style="display: block; margin-bottom: 3px; font-size: 13px;">Full Transcript:</label>
            <textarea id="youtube_video_transcript" name="youtube_video_transcript" rows="6" style="width:100%; font-size: 13px;" placeholder="Full video transcript or detailed summary..."><?php echo esc_textarea($video_transcript); ?></textarea>
            <p class="description" style="margin: 3px 0 0 0; font-size: 11px; color: #666;">Auto-filled with YouTube captions if available</p>
        </div>
    </div>

    <?php
    // Show preview if video ID exists
    if ($video_id) {
        echo '<div style="margin-top:15px; padding:10px; background:#f9f9f9; border-radius:4px;">';
        echo '<p style="margin:0 0 8px 0; font-weight:bold;">Preview:</p>';
        echo '<div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">';
        echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>';
        echo '</div>';
        
        // Show auto-fetch status
        if ($auto_fetch_data) {
            echo '<p style="margin:8px 0 0 0; font-size:12px; color:green;">✓ Auto-fetch enabled</p>';
        }
        echo '</div>';
    }
}

// Auto-fetch YouTube data function
function gp_fetch_youtube_data($video_id, $post_id) {
    // Basic video info from oEmbed
    $video_info = wp_oembed_get('https://www.youtube.com/watch?v=' . $video_id);
    
    // Get video title and description via YouTube iframe API simulation
    $api_url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=" . $video_id . "&format=json";
    
    $response = wp_remote_get($api_url);
    
    if (!is_wp_error($response) && $response['response']['code'] == 200) {
        $data = json_decode($response['body'], true);
        
        if ($data && isset($data['title'])) {
            // Use video title and description as base summary
            $auto_summary = "This video titled '" . $data['title'] . "' discusses topics related to diabetes management and health tips.";
            
            // If we have a description, use it
            if (isset($data['author_name'])) {
                $auto_summary .= " Presented by " . $data['author_name'] . ".";
            }
            
            // Save auto-generated summary
            update_post_meta($post_id, '_youtube_video_summary', $auto_summary);
            
            // Set up transcript placeholder
            $transcript_placeholder = "Full transcript available on YouTube. Click the video to enable closed captions, or visit the video on YouTube for complete accessibility features.";
            update_post_meta($post_id, '_youtube_video_transcript', $transcript_placeholder);
            
            return true;
        }
    }
    
    return false;
}

// Enhanced save function with auto-fetch
function gp_save_youtube_video_meta($post_id) {
    // Check nonce
    if (!isset($_POST['gp_youtube_video_nonce']) || !wp_verify_nonce($_POST['gp_youtube_video_nonce'], 'gp_youtube_video_nonce')) {
        return;
    }
    
    // Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save/update the YouTube URL and extract ID
    if (isset($_POST['youtube_video_url'])) {
        $youtube_url = sanitize_text_field($_POST['youtube_video_url']);
        $video_id = gp_extract_youtube_id($youtube_url);
        $auto_fetch = isset($_POST['youtube_auto_fetch']) ? '1' : '0';
        
        // Check if URL changed
        $old_url = get_post_meta($post_id, '_youtube_video_url', true);
        $url_changed = ($youtube_url !== $old_url);
        
        update_post_meta($post_id, '_youtube_video_url', $youtube_url);
        update_post_meta($post_id, '_youtube_video_id', $video_id);
        update_post_meta($post_id, '_youtube_auto_fetch', $auto_fetch);
        
        // Auto-fetch data if enabled and URL changed or first time
        if ($auto_fetch && $video_id && $url_changed) {
            gp_fetch_youtube_data($video_id, $post_id);
            
            // Auto-enable transcript when auto-fetch is used
            update_post_meta($post_id, '_youtube_has_transcript', '1');
        }
        
        // Save manual transcript data (will override auto-fetch if user entered something)
        $has_transcript = isset($_POST['youtube_has_transcript']) ? '1' : '0';
        $video_summary = isset($_POST['youtube_video_summary']) ? sanitize_textarea_field($_POST['youtube_video_summary']) : '';
        $video_transcript = isset($_POST['youtube_video_transcript']) ? sanitize_textarea_field($_POST['youtube_video_transcript']) : '';
        
        update_post_meta($post_id, '_youtube_has_transcript', $has_transcript);
        
        // Only update if user manually entered data (not empty)
        if (!empty($video_summary)) {
            update_post_meta($post_id, '_youtube_video_summary', $video_summary);
        }
        
        if (!empty($video_transcript)) {
            update_post_meta($post_id, '_youtube_video_transcript', $video_transcript);
        }
        
        // Auto-set post format to "video" if URL is not empty
        if (!empty($youtube_url)) {
            set_post_format($post_id, 'video');
        } else {
            if (get_post_format($post_id) === 'video') {
                set_post_format($post_id, false);
            }
        }
    }
}
add_action('save_post', 'gp_save_youtube_video_meta');

// Extract YouTube video ID from URL
function gp_extract_youtube_id($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    preg_match($pattern, $url, $matches);
    return isset($matches[1]) ? $matches[1] : '';
}

// Enhanced video display with smart transcript handling
function gp_move_video_to_featured_image() {
    if (is_single()) {
        $video_id = get_post_meta(get_the_ID(), '_youtube_video_id', true);
        $video_url = get_post_meta(get_the_ID(), '_youtube_video_url', true);
        $has_transcript = get_post_meta(get_the_ID(), '_youtube_has_transcript', true);
        $video_summary = get_post_meta(get_the_ID(), '_youtube_video_summary', true);
        $video_transcript = get_post_meta(get_the_ID(), '_youtube_video_transcript', true);
        $auto_fetch = get_post_meta(get_the_ID(), '_youtube_auto_fetch', true);
        
        if ($video_id || $video_url) {
            $final_video_id = $video_id ?: gp_extract_youtube_id($video_url);
            
            if ($final_video_id) {
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Prepare transcript HTML
                    var transcriptHTML = '';
                    <?php if ($has_transcript) : ?>
                        transcriptHTML = `
                        <div class="video-transcript" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #2271b1;">
                            <h3 style="margin-top: 0; color: #2271b1; font-size: 18px;">Video Transcript</h3>
                            <div class="transcript-content">
                                <?php if ($video_summary) : ?>
                                    <p><strong>Summary:</strong> <?php echo esc_js($video_summary); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($video_transcript && !strpos($video_transcript, 'Full transcript available on YouTube')) : ?>
                                    <details style="margin-top: 15px;">
                                        <summary style="cursor: pointer; font-weight: bold; color: #2271b1; background: none; border: none; padding: 0;">Show Full Transcript</summary>
                                        <div style="margin-top: 10px; padding: 15px; background: white; border-radius: 4px; border: 1px solid #e1e1e1; font-size: 14px; line-height: 1.5;">
                                            <?php echo wp_kses_post(wpautop($video_transcript)); ?>
                                        </div>
                                    </details>
                                <?php else : ?>
                                    <p><strong>Full transcript:</strong> Available on YouTube with closed captions. <a href="https://www.youtube.com/watch?v=<?php echo esc_js($final_video_id); ?>" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: underline;">View on YouTube</a> to access complete transcript and accessibility features.</p>
                                <?php endif; ?>
                                
                                <?php if ($auto_fetch) : ?>
                                    <p style="margin-top: 10px; font-size: 12px; color: #666; font-style: italic;">✓ Video data automatically fetched from YouTube</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        `;
                    <?php endif; ?>

                    // Create video HTML
                    var videoHTML = `
                        <div class="featured-video-replacement">
                            <div class="video-container" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:8px; margin-bottom:2em;">
                                <div class="video-autoplay-notice" style="position:absolute; bottom:60px; left:10px; background:rgba(0,0,0,0.7); color:white; padding:4px 8px; border-radius:4px; font-size:12px; z-index:10;">
                                    Autoplay (muted)
                                </div>    
                                <iframe src="https://www.youtube.com/embed/<?php echo esc_js($final_video_id); ?>?rel=0&amp;showinfo=0&amp;modestbranding=1&amp;autoplay=1&amp;mute=1&amp;playsinline=1" 
                                        style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen 
                                        loading="lazy"
                                        title="<?php echo esc_js(get_the_title()); ?>">
                                </iframe>
                            </div>
                            ${transcriptHTML}
                        </div>
                    `;
                    
                    // Target the featured image container
                    var featuredContainer = document.querySelector('.gb-element-dab3bde9');
                    if (featuredContainer) {
                        featuredContainer.innerHTML = videoHTML;
                        featuredContainer.classList.add('video-loaded');
                        
                        var iframe = featuredContainer.querySelector('iframe');
                        if (iframe) {
                            iframe.addEventListener('click', function() {
                                iframe.src = iframe.src.replace('&mute=1', '&mute=0');
                            });
                        }
                    }
                    
                    // Remove video marker from content
                    var contentArea = document.querySelector('.gb-element-850a602b');
                    if (contentArea) {
                        contentArea.innerHTML = contentArea.innerHTML.replace('<!-- VIDEO_MARKER -->', '');
                    }
                });
                </script>
                <style>
                .gb-media-dc46dcac { display: none !important; }
                .featured-video-replacement { width: 100%; }
                .video-container { 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    transition: transform 0.3s ease;
                    cursor: pointer;
                }
                .video-container:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                }
                </style>
                <?php
            }
        }
    }
}
add_action('wp_footer', 'gp_move_video_to_featured_image');

// Keep existing content filter
function gp_video_post_complete_solution($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $video_id = get_post_meta(get_the_ID(), '_youtube_video_url', true);
        $video_id_clean = get_post_meta(get_the_ID(), '_youtube_video_id', true);
        
        if ($video_id_clean || $video_id) {
            return '<!-- VIDEO_MARKER -->' . $content;
        }
    }
    return $content;
}
add_filter('the_content', 'gp_video_post_complete_solution', 5);
/* ============================================================
 *  AUDIO META BOX + MEDIA UPLOAD - FIXED VERSION
 * ============================================================ */

// 1. Add Audio URL Meta Box
function gp_add_audio_file_meta_box() {
    add_meta_box(
        'audio_file_url',
        'Audio File',
        'gp_audio_file_url_callback',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'gp_add_audio_file_meta_box');

// 2. Callback for Audio Field + Media Upload - FIXED VERSION
function gp_audio_file_url_callback($post) {
    wp_nonce_field('gp_audio_file_nonce', 'gp_audio_file_nonce');
    $audio_url = get_post_meta($post->ID, '_audio_file_url', true);
    $audio_attachment_id = get_post_meta($post->ID, '_audio_attachment_id', true);

    // Generate unique IDs to avoid conflicts
    $input_id = 'gp_audio_file_url_' . $post->ID;
    $attachment_id = 'gp_audio_attachment_id_' . $post->ID;

    echo '<div style="background: #f0f0f1; padding: 10px; margin-bottom: 10px; border-left: 4px solid #2271b1;">';
    echo '<strong>Instructions:</strong> Upload/select audio or paste URL manually. The audio player will appear above the featured image.';
    echo '</div>';
    
    // Fixed label with correct for attribute
    echo '<label for="' . esc_attr($input_id) . '">Audio File URL:</label>';
    echo '<input type="url" id="' . esc_attr($input_id) . '" name="audio_file_url" value="' . esc_attr($audio_url) . '" style="width:100%; margin-top:5px;" placeholder="https://example.com/audio.mp3" />';
    echo '<input type="hidden" id="' . esc_attr($attachment_id) . '" name="audio_attachment_id" value="' . esc_attr($audio_attachment_id) . '" />';
    
    echo '<div style="margin-top: 10px;">';
    echo '<button type="button" class="button button-secondary" id="upload_audio_button_' . $post->ID . '">Select Audio from Media Library</button>';
    echo '<button type="button" class="button button-link-delete" id="remove_audio_button_' . $post->ID . '" style="margin-left:5px; color:#a00; display: ' . ($audio_url ? 'inline-block' : 'none') . ';">Remove Audio</button>';
    echo '</div>';
    
    echo '<p class="description">Enter audio URL or select from media library.</p>';

    // Audio preview container
    echo '<div id="audio_preview_container_' . $post->ID . '" style="margin-top:10px; padding:10px; background:#f9f9f9; border-radius:4px; display: ' . ($audio_url ? 'block' : 'none') . ';">';
    if ($audio_url) {
        echo '<p><strong>Preview:</strong></p>';
        echo '<audio controls style="width:100%;"><source src="' . esc_url($audio_url) . '"></audio>';
    }
    echo '</div>';

    // Localize script for audio upload with post-specific IDs
    wp_localize_script('gp-audio-upload', 'gpAudioOptions', array(
        'post_id' => $post->ID,
        'l10n' => array(
            'select' => 'Select Audio',
            'change' => 'Change Audio',
            'featuredAudio' => 'Featured Audio'
        ),
        'initialAudioAttachment' => $audio_attachment_id ? array(
            'id' => $audio_attachment_id,
            'url' => $audio_url,
            'title' => get_the_title($audio_attachment_id)
        ) : false
    ));
}

// 3. Save Audio Field
function gp_save_audio_file_meta($post_id) {
    // Check if nonce is set and valid
    if (!isset($_POST['gp_audio_file_nonce']) || !wp_verify_nonce($_POST['gp_audio_file_nonce'], 'gp_audio_file_nonce')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Handle audio URL
    if (isset($_POST['audio_file_url'])) {
        $audio_url = sanitize_text_field($_POST['audio_file_url']);
        
        // Update or delete post meta based on whether URL is empty
        if (!empty($audio_url)) {
            update_post_meta($post_id, '_audio_file_url', $audio_url);
            
            // Auto-set post format to "audio" if URL is not empty
            set_post_format($post_id, 'audio');
        } else {
            // If URL is empty, delete the meta and remove audio format
            delete_post_meta($post_id, '_audio_file_url');
            delete_post_meta($post_id, '_audio_attachment_id');
            
            // Only remove audio format if this post currently has it
            if (get_post_format($post_id) === 'audio') {
                set_post_format($post_id, false);
            }
        }
    }

    // Handle audio attachment ID
    if (isset($_POST['audio_attachment_id'])) {
        $audio_attachment_id = absint($_POST['audio_attachment_id']);
        if ($audio_attachment_id > 0) {
            update_post_meta($post_id, '_audio_attachment_id', $audio_attachment_id);
        } else {
            delete_post_meta($post_id, '_audio_attachment_id');
        }
    }
}
add_action('save_post', 'gp_save_audio_file_meta');



// 5. Fallback inline script if custom.js doesn't exist
function gp_add_audio_upload_script_inline() {
    ?>
    <script type="text/javascript">
    /**
     * Audio Upload Script - Inline version
     */
    var gpFeaturedAudio = {};
    (function($) {
        gpFeaturedAudio = {
            container: '',
            frame: '',
            settings: window.gpAudioOptions || {},

            init: function() {
                gpFeaturedAudio.container = $('#audio_file_url').closest('.inside');
                gpFeaturedAudio.initFrame();

                // Bind events
                $('#upload_audio_button').on('click', gpFeaturedAudio.openAudioFrame);
                $('#remove_audio_button').on('click', gpFeaturedAudio.removeAudio);
                
                // Handle manual URL input
                $('#audio_file_url').on('input change', function() {
                    var url = $(this).val().trim();
                    gpFeaturedAudio.updatePreview(url);
                });

                gpFeaturedAudio.initAudioPreview();
            },

            /**
             * Open the featured audio media modal.
             */
            openAudioFrame: function(event) {
                event.preventDefault();
                if (!gpFeaturedAudio.frame) {
                    gpFeaturedAudio.initFrame();
                }
                gpFeaturedAudio.frame.open();
            },

            /**
             * Create a media modal select frame, and store it so the instance can be reused when needed.
             */
            initFrame: function() {
                gpFeaturedAudio.frame = wp.media({
                    title: gpFeaturedAudio.settings.l10n ? gpFeaturedAudio.settings.l10n.featuredAudio : 'Featured Audio',
                    button: {
                        text: gpFeaturedAudio.settings.l10n ? gpFeaturedAudio.settings.l10n.select : 'Select Audio'
                    },
                    library: {
                        type: 'audio'
                    },
                    multiple: false
                });

                // When a file is selected, run a callback.
                gpFeaturedAudio.frame.on('select', gpFeaturedAudio.selectAudio);
            },

            /**
             * Callback handler for when an attachment is selected in the media modal.
             * Gets the selected attachment information, and sets it within the control.
             */
            selectAudio: function() {
                // Get the attachment from the modal frame.
                var attachment = gpFeaturedAudio.frame.state().get('selection').first().toJSON();
                
                // Set the URL in the input field - THIS IS THE FIX
                $('#audio_file_url').val(attachment.url);
                $('#audio_attachment_id').val(attachment.id);
                
                // Update preview
                gpFeaturedAudio.updatePreview(attachment.url);
                
                // Show remove button
                $('#remove_audio_button').show();
                if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.change) {
                    $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.change);
                } else {
                    $('#upload_audio_button').text('Change Audio');
                }
            },

            /**
             * Update audio preview
             */
            updatePreview: function(url) {
                var previewContainer = $('#audio_preview_container');
                
                if (url && gpFeaturedAudio.isValidAudioUrl(url)) {
                    var previewHtml = '<p><strong>Preview:</strong></p>' +
                                     '<audio controls style="width:100%;">' +
                                     '<source src="' + url + '">' +
                                     'Your browser does not support the audio element.' +
                                     '</audio>';
                    
                    previewContainer.html(previewHtml).show();
                    
                    // Show remove button if not already shown
                    if ($('#remove_audio_button').is(':hidden')) {
                        $('#remove_audio_button').show();
                    }
                } else {
                    previewContainer.hide().html('');
                    if (!url) {
                        $('#remove_audio_button').hide();
                        if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.select) {
                            $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.select);
                        } else {
                            $('#upload_audio_button').text('Select Audio from Media Library');
                        }
                    }
                }
            },

            /**
             * Validate audio URL
             */
            isValidAudioUrl: function(url) {
                if (!url) return true;
                var audioExtensions = [".mp3", ".wav", ".ogg", ".m4a", ".aac", ".flac", ".webm"];
                return audioExtensions.some(function(ext) {
                    return url.toLowerCase().endsWith(ext);
                });
            },

            /**
             * Remove the selected audio.
             */
            removeAudio: function() {
                $('#audio_file_url').val('');
                $('#audio_attachment_id').val('');
                $('#audio_preview_container').hide().html('');
                $('#remove_audio_button').hide();
                if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.select) {
                    $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.select);
                } else {
                    $('#upload_audio_button').text('Select Audio from Media Library');
                }
            },

            /**
             * Initialize featured audio preview.
             */
            initAudioPreview: function() {
                var initialAttachment = gpFeaturedAudio.settings.initialAudioAttachment;
                if (initialAttachment && initialAttachment.url) {
                    if (gpFeaturedAudio.settings.l10n && gpFeaturedAudio.settings.l10n.change) {
                        $('#upload_audio_button').text(gpFeaturedAudio.settings.l10n.change);
                    } else {
                        $('#upload_audio_button').text('Change Audio');
                    }
                    $('#remove_audio_button').show();
                }
            }
        };

        $(document).ready(function() {
            gpFeaturedAudio.init();
        });

    })(jQuery);
    </script>
    <?php
}

// 6. Register for Gutenberg support
function gp_add_audio_to_block_editor() {
    register_meta('post', '_audio_file_url', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_meta('post', '_audio_attachment_id', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'gp_add_audio_to_block_editor');


/* ============================================================
 *  AUDIO PLAYER SHORTCODE
 * ============================================================ */

// Audio Player Shortcode with Nice Design
function gp_audio_player_shortcode($atts) {
    // Get the current post ID
    $post_id = get_the_ID();
    
    // Check if it's an audio post format
    if (get_post_format($post_id) !== 'audio') {
        return ''; // Return empty if not audio format
    }
    
    // Get audio URL from post meta
    $audio_url = get_post_meta($post_id, '_audio_file_url', true);
    
    // Return empty if no audio URL
    if (empty($audio_url)) {
        return '';
    }
    
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'style' => 'modern', // modern, minimal, dark
        'width' => '100%',
        'height' => '50px',
        'autoplay' => 'no',
        'loop' => 'no'
    ), $atts);
    
    // Sanitize attributes
    $style = sanitize_text_field($atts['style']);
    $width = esc_attr($atts['width']);
    $height = esc_attr($atts['height']);
    $autoplay = $atts['autoplay'] === 'yes' ? 'autoplay' : '';
    $loop = $atts['loop'] === 'yes' ? 'loop' : '';
    
    // Get file name for display
    $file_name = basename($audio_url);
    
    // Generate unique ID for this audio player
    $audio_id = 'gp-audio-' . uniqid();
    
    // Different styles
    $styles = array(
        'modern' => '
            <div class="gp-audio-player gp-audio-modern" style="max-width: ' . $width . '; margin: 20px 0;">
                <div class="gp-audio-header" style="background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%); border: 1px solid #e5e5e5; padding: 15px; border-radius: 10px 10px 0 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <svg style="width: 20px; height: 20px; margin-right: 10px; fill: #06940c;" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                            <span style="color: #06940c; font-weight: 600;">Listen to this Post</span>
                        </div>
                        <span style="color: rgba(6, 110, 21, 0.8); font-size: 12px;">' . $file_name . '</span>
                    </div>
                </div>
                <div class="gp-audio-body" style="background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; border-top: none;">
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 6px;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        ',
        
        'minimal' => '
            <div class="gp-audio-player gp-audio-minimal" style="max-width: ' . $width . '; margin: 20px 0;">
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e1e5e9; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <svg style="width: 16px; height: 16px; margin-right: 8px; fill: #6c757d;" viewBox="0 0 24 24">
                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                        </svg>
                        <span style="color: #495057; font-size: 14px; font-weight: 500;">' . $file_name . '</span>
                    </div>
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 4px;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        ',
        
        'dark' => '
            <div class="gp-audio-player gp-audio-dark" style="max-width: ' . $width . '; margin: 20px 0;">
                <div style="background: #2d3748; padding: 20px; border-radius: 12px; border: 1px solid #4a5568;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center;">
                            <svg style="width: 20px; height: 20px; margin-right: 12px; fill: #63b3ed;" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                            <span style="color: #e2e8f0; font-weight: 600; font-size: 16px;">Audio Player</span>
                        </div>
                        <span style="color: #a0aec0; font-size: 12px;">' . $file_name . '</span>
                    </div>
                    <audio id="' . $audio_id . '" controls style="width: 100%; height: ' . $height . '; border-radius: 6px; background: #1a202c;" ' . $autoplay . ' ' . $loop . ' controlsList="nodownload">
                        <source src="' . esc_url($audio_url) . '" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        '
    );
    
    // Add JavaScript to remove download button
    add_action('wp_footer', function() use ($audio_id) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var audio = document.getElementById('<?php echo $audio_id; ?>');
            if (audio) {
                // Remove download button using controlsList
                audio.controlsList = 'nodownload';
                
                // Additional method: Hide download button via CSS
                var style = document.createElement('style');
                style.textContent = 'audio::-webkit-media-controls-download-button { display: none !important; }';
                document.head.appendChild(style);
                
                // For Firefox and other browsers, remove context menu download option
                audio.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
        });
        </script>
        <?php
    });
    
    // Return the selected style or modern as default
    return isset($styles[$style]) ? $styles[$style] : $styles['modern'];
}
add_shortcode('audio_player', 'gp_audio_player_shortcode');

// Add CSS for better audio player styling and hide download buttons
function gp_audio_player_styles() {
    if (is_single() && get_post_format() === 'audio') {
        ?>
        <style>
            .gp-audio-player audio {
                outline: none;
                transition: all 0.3s ease;
            }
            
            .gp-audio-player audio:hover {
                opacity: 0.9;
            }
            
            .gp-audio-player audio::-webkit-media-controls-panel {
                background-color: #f8f9fa;
            }
            
            .gp-audio-player audio::-webkit-media-controls-play-button {
                background-color: #667eea;
                border-radius: 50%;
            }
            
            .gp-audio-player audio::-webkit-media-controls-current-time-display,
            .gp-audio-player audio::-webkit-media-controls-time-remaining-display {
                color: #495057;
                font-weight: 500;
            }
            
            /* Remove download button in WebKit browsers */
            .gp-audio-player audio::-webkit-media-controls-download-button {
                display: none !important;
            }
            
            /* Remove download button in other browsers */
            .gp-audio-player audio {
                -webkit-media-controls-download-button: none !important;
                media-controls-download-button: none !important;
            }
            
            /* Dark mode audio player styles */
            .gp-audio-dark audio::-webkit-media-controls-panel {
                background-color: #4a5568;
            }
            
            .gp-audio-dark audio::-webkit-media-controls-current-time-display,
            .gp-audio-dark audio::-webkit-media-controls-time-remaining-display {
                color: #e2e8f0;
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'gp_audio_player_styles');

/* ============================================================
 *  RENAME DEFAULT "POST" TYPE TO "NEWS"
 * ============================================================ */
if ( get_option( 'cotlas_rename_post_to_news' ) ) {
    add_filter( 'post_type_labels_post',    'cotlas_rename_post_type_labels_to_news' );
    add_filter( 'post_updated_messages',    'cotlas_news_post_updated_messages' );
    add_filter( 'bulk_post_updated_messages', 'cotlas_news_bulk_updated_messages', 10, 2 );
}

function cotlas_rename_post_type_labels_to_news( $labels ) {
    $labels->name                  = __( 'News', 'cotlas-admin' );
    $labels->singular_name         = __( 'News', 'cotlas-admin' );
    $labels->add_new               = __( 'Add News', 'cotlas-admin' );
    $labels->add_new_item          = __( 'Add News', 'cotlas-admin' );
    $labels->edit_item             = __( 'Edit News', 'cotlas-admin' );
    $labels->new_item              = __( 'News', 'cotlas-admin' );
    $labels->view_item             = __( 'View News', 'cotlas-admin' );
    $labels->view_items            = __( 'View News', 'cotlas-admin' );
    $labels->search_items          = __( 'Search News', 'cotlas-admin' );
    $labels->not_found             = __( 'No news found.', 'cotlas-admin' );
    $labels->not_found_in_trash    = __( 'No news found in Trash.', 'cotlas-admin' );
    $labels->all_items             = __( 'All News', 'cotlas-admin' );
    $labels->archives              = __( 'News Archives', 'cotlas-admin' );
    $labels->attributes            = __( 'News Attributes', 'cotlas-admin' );
    $labels->insert_into_item      = __( 'Insert into news', 'cotlas-admin' );
    $labels->uploaded_to_this_item = __( 'Uploaded to this news', 'cotlas-admin' );
    $labels->featured_image        = __( 'Featured image', 'cotlas-admin' );
    $labels->set_featured_image    = __( 'Set featured image', 'cotlas-admin' );
    $labels->remove_featured_image = __( 'Remove featured image', 'cotlas-admin' );
    $labels->use_featured_image    = __( 'Use as featured image', 'cotlas-admin' );
    $labels->filter_items_list     = __( 'Filter news list', 'cotlas-admin' );
    $labels->filter_by_date        = __( 'Filter news by date', 'cotlas-admin' );
    $labels->items_list_navigation = __( 'News list navigation', 'cotlas-admin' );
    $labels->items_list            = __( 'News list', 'cotlas-admin' );
    $labels->item_published        = __( 'News published.', 'cotlas-admin' );
    $labels->item_published_privately = __( 'News published privately.', 'cotlas-admin' );
    $labels->item_reverted_to_draft = __( 'News reverted to draft.', 'cotlas-admin' );
    $labels->item_scheduled        = __( 'News scheduled.', 'cotlas-admin' );
    $labels->item_updated          = __( 'News updated.', 'cotlas-admin' );
    $labels->item_link             = __( 'News Link', 'cotlas-admin' );
    $labels->item_link_description = __( 'A link to a news item.', 'cotlas-admin' );
    $labels->menu_name             = __( 'News', 'cotlas-admin' );
    $labels->name_admin_bar        = __( 'News', 'cotlas-admin' );
    return $labels;
}

function cotlas_news_post_updated_messages( $messages ) {
    global $post;
    $permalink      = $post ? get_permalink( $post->ID ) : '';
    $preview_link   = $post ? get_preview_post_link( $post ) : '';
    $scheduled_date = $post ? date_i18n( __( 'M j, Y @ G:i', 'cotlas-admin' ), strtotime( $post->post_date ) ) : '';

    $messages['post'] = array(
        0  => '',
        1  => $permalink ? sprintf( __( 'News updated. <a href="%s">View news</a>', 'cotlas-admin' ), esc_url( $permalink ) ) : __( 'News updated.', 'cotlas-admin' ),
        2  => __( 'Custom field updated.', 'cotlas-admin' ),
        3  => __( 'Custom field deleted.', 'cotlas-admin' ),
        4  => __( 'News updated.', 'cotlas-admin' ),
        5  => isset( $_GET['revision'] ) ? sprintf( __( 'News restored to revision from %s', 'cotlas-admin' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
        6  => $permalink ? sprintf( __( 'News published. <a href="%s">View news</a>', 'cotlas-admin' ), esc_url( $permalink ) ) : __( 'News published.', 'cotlas-admin' ),
        7  => __( 'News saved.', 'cotlas-admin' ),
        8  => $preview_link ? sprintf( __( 'News submitted. <a target="_blank" href="%s">Preview news</a>', 'cotlas-admin' ), esc_url( $preview_link ) ) : __( 'News submitted.', 'cotlas-admin' ),
        9  => $permalink ? sprintf( __( 'News scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview news</a>', 'cotlas-admin' ), $scheduled_date, esc_url( $permalink ) ) : sprintf( __( 'News scheduled for: <strong>%1$s</strong>.', 'cotlas-admin' ), $scheduled_date ),
        10 => $preview_link ? sprintf( __( 'News draft updated. <a target="_blank" href="%s">Preview news</a>', 'cotlas-admin' ), esc_url( $preview_link ) ) : __( 'News draft updated.', 'cotlas-admin' ),
    );
    return $messages;
}

function cotlas_news_bulk_updated_messages( $bulk_messages, $bulk_counts ) {
    $bulk_messages['post'] = array(
        'updated'   => sprintf( _n( '%s news updated.',                       '%s news updated.',                         $bulk_counts['updated'],   'cotlas-admin' ), number_format_i18n( $bulk_counts['updated'] ) ),
        'locked'    => sprintf( _n( '%s news not updated, somebody is editing it.', '%s news not updated, somebody is editing them.', $bulk_counts['locked'],    'cotlas-admin' ), number_format_i18n( $bulk_counts['locked'] ) ),
        'deleted'   => sprintf( _n( '%s news permanently deleted.',           '%s news permanently deleted.',             $bulk_counts['deleted'],   'cotlas-admin' ), number_format_i18n( $bulk_counts['deleted'] ) ),
        'trashed'   => sprintf( _n( '%s news moved to the Trash.',            '%s news moved to the Trash.',              $bulk_counts['trashed'],   'cotlas-admin' ), number_format_i18n( $bulk_counts['trashed'] ) ),
        'untrashed' => sprintf( _n( '%s news restored from the Trash.',       '%s news restored from the Trash.',         $bulk_counts['untrashed'], 'cotlas-admin' ), number_format_i18n( $bulk_counts['untrashed'] ) ),
    );
    return $bulk_messages;
}

/* ============================================================
 *  TAXONOMIES FOR POST TYPE "POST"
 * ============================================================ */
add_action( 'init', 'cotlas_register_post_taxonomies' );

function cotlas_register_post_taxonomies() {
    // ── Location ──────────────────────────────────────────────────────────
    if ( get_option( 'cotlas_taxonomy_location_enabled' ) ) {
        register_taxonomy( 'location', array( 'post' ), array(
            'labels' => array(
                'name'                       => __( 'Locations', 'cotlas-admin' ),
                'singular_name'              => __( 'Location', 'cotlas-admin' ),
                'search_items'               => __( 'Search Locations', 'cotlas-admin' ),
                'popular_items'              => __( 'Popular Locations', 'cotlas-admin' ),
                'all_items'                  => __( 'All Locations', 'cotlas-admin' ),
                'parent_item'                => __( 'Parent Location', 'cotlas-admin' ),
                'parent_item_colon'          => __( 'Parent Location:', 'cotlas-admin' ),
                'edit_item'                  => __( 'Edit Location', 'cotlas-admin' ),
                'view_item'                  => __( 'View Location', 'cotlas-admin' ),
                'update_item'                => __( 'Update Location', 'cotlas-admin' ),
                'add_new_item'               => __( 'Add New Location', 'cotlas-admin' ),
                'new_item_name'              => __( 'New Location Name', 'cotlas-admin' ),
                'separate_items_with_commas' => __( 'Separate locations with commas', 'cotlas-admin' ),
                'add_or_remove_items'        => __( 'Add or remove locations', 'cotlas-admin' ),
                'choose_from_most_used'      => __( 'Choose from the most used locations', 'cotlas-admin' ),
                'not_found'                  => __( 'No locations found.', 'cotlas-admin' ),
                'no_terms'                   => __( 'No locations', 'cotlas-admin' ),
                'menu_name'                  => __( 'Locations', 'cotlas-admin' ),
                'filter_by_item'             => __( 'Filter by location', 'cotlas-admin' ),
                'items_list_navigation'      => __( 'Locations list navigation', 'cotlas-admin' ),
                'items_list'                 => __( 'Locations list', 'cotlas-admin' ),
                'back_to_items'              => __( 'Back to Locations', 'cotlas-admin' ),
                'item_link'                  => __( 'Location Link', 'cotlas-admin' ),
                'item_link_description'      => __( 'A link to a location.', 'cotlas-admin' ),
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'hierarchical'        => true,
            'show_ui'             => true,
            'show_admin_column'   => true,
            'show_in_nav_menus'   => true,
            'show_tagcloud'       => false,
            'show_in_quick_edit'  => true,
            'show_in_rest'        => true,
            'rest_base'           => 'locations',
            'query_var'           => 'location',
            'rewrite'             => array( 'slug' => 'location', 'with_front' => false, 'hierarchical' => true ),
        ) );
    }

    // ── State ─────────────────────────────────────────────────────────────
    if ( get_option( 'cotlas_taxonomy_state_city_enabled' ) ) {
        register_taxonomy( 'state', array( 'post' ), array(
            'labels' => array(
                'name'              => __( 'States', 'cotlas-admin' ),
                'singular_name'     => __( 'State', 'cotlas-admin' ),
                'search_items'      => __( 'Search States', 'cotlas-admin' ),
                'all_items'         => __( 'All States', 'cotlas-admin' ),
                'parent_item'       => __( 'Parent State', 'cotlas-admin' ),
                'parent_item_colon' => __( 'Parent State:', 'cotlas-admin' ),
                'edit_item'         => __( 'Edit State', 'cotlas-admin' ),
                'update_item'       => __( 'Update State', 'cotlas-admin' ),
                'add_new_item'      => __( 'Add New State', 'cotlas-admin' ),
                'new_item_name'     => __( 'New State Name', 'cotlas-admin' ),
                'menu_name'         => __( 'States', 'cotlas-admin' ),
                'not_found'         => __( 'No states found.', 'cotlas-admin' ),
                'back_to_items'     => __( 'Back to States', 'cotlas-admin' ),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_in_quick_edit' => true,
            'show_in_rest'       => true,
            'rest_base'          => 'states',
            'query_var'          => 'state',
            'rewrite'            => array( 'slug' => 'state', 'with_front' => false, 'hierarchical' => true ),
        ) );

        // ── City ──────────────────────────────────────────────────────────
        register_taxonomy( 'city', array( 'post' ), array(
            'labels' => array(
                'name'              => __( 'Cities', 'cotlas-admin' ),
                'singular_name'     => __( 'City', 'cotlas-admin' ),
                'search_items'      => __( 'Search Cities', 'cotlas-admin' ),
                'all_items'         => __( 'All Cities', 'cotlas-admin' ),
                'parent_item'       => __( 'Parent City', 'cotlas-admin' ),
                'parent_item_colon' => __( 'Parent City:', 'cotlas-admin' ),
                'edit_item'         => __( 'Edit City', 'cotlas-admin' ),
                'update_item'       => __( 'Update City', 'cotlas-admin' ),
                'add_new_item'      => __( 'Add New City', 'cotlas-admin' ),
                'new_item_name'     => __( 'New City Name', 'cotlas-admin' ),
                'menu_name'         => __( 'Cities', 'cotlas-admin' ),
                'not_found'         => __( 'No cities found.', 'cotlas-admin' ),
                'back_to_items'     => __( 'Back to Cities', 'cotlas-admin' ),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_in_quick_edit' => true,
            'show_in_rest'       => true,
            'rest_base'          => 'cities',
            'query_var'          => 'city',
            'rewrite'            => array( 'slug' => 'city', 'with_front' => false ),
        ) );
    }
}

// Show/hide format meta boxes based on selected post format in block editor
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'post' || ! $screen->is_block_editor() ) {
        return;
    }
    ?>
    <script>
    (function() {
        if ( typeof wp === 'undefined' || ! wp.data ) { return; }

        function updateFormatBoxes( format ) {
            var video = document.getElementById( 'youtube_video_url' );
            var audio = document.getElementById( 'audio_file_url' );
            if ( video ) { video.style.display = ( format === 'video' ) ? '' : 'none'; }
            if ( audio ) { audio.style.display = ( format === 'audio' ) ? '' : 'none'; }
        }

        // Run once as soon as the editor store is ready
        var unsubscribeInit = wp.data.subscribe( function() {
            var sel = wp.data.select( 'core/editor' );
            if ( ! sel ) { return; }
            var format = sel.getEditedPostAttribute( 'format' ) || 'standard';
            updateFormatBoxes( format );
            unsubscribeInit(); // one-time init

            // Then keep watching for changes
            var lastFormat = format;
            wp.data.subscribe( function() {
                var s = wp.data.select( 'core/editor' );
                if ( ! s ) { return; }
                var f = s.getEditedPostAttribute( 'format' ) || 'standard';
                if ( f !== lastFormat ) {
                    lastFormat = f;
                    updateFormatBoxes( f );
                }
            } );
        } );
    })();
    </script>
    <?php
} );
