<?php
/**
 * Styled threaded comments: [cotlas_comments] shortcode, inline-edit AJAX, avatar helper.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_comment_system_enabled' ) ) { return; }


/* ==========================================================================
 * Facebook-style Comments — [cotlas_comments]
 * Renders the comment list + form for the current post.
 * ========================================================================== */

/**
 * Get commenter avatar — Gravatar URL or a coloured-initial SVG data-URI.
 */
function cotlas_comment_avatar( $email, $name, $size = 40 ) {
    $gravatar = get_avatar_url( $email, array( 'size' => $size * 2, 'd' => '404' ) );
    // We return both so JS can fall back; but for PHP render we always use the initials
    // wrapper and let the <img> onerror swap itself.
    $initials = mb_strtoupper( mb_substr( strip_tags( $name ), 0, 1 ), 'UTF-8' );
    $colors   = array( '#e74c3c','#e67e22','#2ecc71','#3498db','#9b59b6','#1abc9c','#e91e63','#ff5722' );
    $color    = $colors[ abs( crc32( $email ) ) % count( $colors ) ];
    return array(
        'src'      => $gravatar,
        'initials' => $initials,
        'color'    => $color,
    );
}

/**
 * Render a single comment bubble (used recursively for replies).
 */
function cotlas_render_comment( $comment, $depth = 0, $top_id = 0 ) {
    $author   = esc_html( get_comment_author( $comment ) );
    $email    = get_comment_author_email( $comment );
    $content  = get_comment_text( $comment );
    $date_ts  = strtotime( $comment->comment_date_gmt );
    $now      = time();
    $diff     = $now - $date_ts;

    if ( $diff < 5 ) {
        $ago = 'Just now';
    } elseif ( $diff < 60 ) {
        $ago = $diff . ' seconds ago';
    } elseif ( $diff < 3600 ) {
        $mins = (int)( $diff / 60 );
        $ago  = $mins . ' minutes ago';
    } elseif ( $diff < 43200 ) {
        $hrs = (int)( $diff / 3600 );
        $ago = $hrs . ' hours ago';
    } else {
        $ago = date_i18n( 'd M Y, g:i a', $date_ts + ( get_option('gmt_offset') * 3600 ) );
    }

    $avatar   = cotlas_comment_avatar( $email, $author );
    $cid      = (int) $comment->comment_ID;
    $approved = '1' === $comment->comment_approved;

    // ── Edit permission check ─────────────────────────────────────
    $can_edit = false;
    $cu       = wp_get_current_user();
    if ( $cu->exists() && $cu->ID > 0 && (int) $comment->user_id === $cu->ID ) {
        $can_edit = true;
    } else {
        // Guest: cookie name+email match + within 60 minutes of posting
        $ck_author = isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] )
            ? sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) : '';
        $ck_email  = isset( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] )
            ? sanitize_email( wp_unslash( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] ) ) : '';
        if ( $ck_author !== '' && $ck_email !== ''
             && $ck_author === $comment->comment_author
             && $ck_email  === $comment->comment_author_email
             && $diff < 3600 ) {
            $can_edit = true;
        }
    }
    $edit_nonce = $can_edit ? wp_create_nonce( 'ctc_edit_' . $cid ) : '';

    ob_start();
    ?>
    <div class="ctc-comment<?php echo $depth > 0 ? ' ctc-comment--reply' : ''; ?>" id="ctc-c-<?php echo $cid; ?>">
        <div class="ctc-comment__avatar" style="background:<?php echo esc_attr( $avatar['color'] ); ?>">
            <img src="<?php echo esc_url( $avatar['src'] ); ?>"
                 alt="<?php echo esc_attr( $author ); ?>"
                 width="36" height="36"
                 loading="lazy"
                 onload="if(this.nextElementSibling) this.nextElementSibling.style.display='none';"
                 onerror="this.style.display='none'" />
            <span class="ctc-comment__initial"><?php echo esc_html( $avatar['initials'] ); ?></span>
        </div>
        <div class="ctc-comment__body">
            <div class="ctc-comment__bubble">
                <span class="ctc-comment__author"><?php echo $author; ?></span>
                <?php if ( ! $approved ) : ?>
                    <span class="ctc-comment__pending">⏳ Pending approval</span>
                <?php endif; ?>
                <div class="ctc-comment__text" data-raw="<?php echo esc_attr( $content ); ?>"><?php echo wp_kses_post( $content ); ?></div>
            </div>
            <div class="ctc-comment__meta">
                <span class="ctc-comment__time"><?php echo esc_html( $ago ); ?></span>
                <?php
                $reply_target = ( $depth === 0 ) ? $cid : (int) $top_id;
                if ( $reply_target ) : ?>
                    <button class="ctc-reply-btn" data-cid="<?php echo $reply_target; ?>">Reply</button>
                <?php endif; ?>
                <?php if ( $can_edit ) : ?>
                    <button class="ctc-edit-btn" data-cid="<?php echo $cid; ?>" data-nonce="<?php echo esc_attr( $edit_nonce ); ?>">Edit</button>
                <?php endif; ?>
            </div>
            <?php if ( $depth === 0 ) : ?>
            <div class="ctc-inline-reply" id="ctc-reply-<?php echo $cid; ?>" style="display:none;"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [cotlas_comments]
 * Renders comment list + form for the current post.
 */
function cotlas_comments_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'post_id' => 0,
        'title'   => 'Comments',
    ), $atts, 'cotlas_comments' );

    $post_id = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $post = get_post( $post_id );
    if ( ! $post || ! comments_open( $post_id ) ) {
        return '';
    }

    // ── fetch approved comments ──────────────────────────────────────
    $comments = get_comments( array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'order'   => 'ASC',
        'type'    => 'comment',
    ) );

    // ── separate top-level and replies ──────────────────────────────
    // Build lookup map and collect top-level comments
    $id_to_comment = array();
    $top           = array();
    foreach ( $comments as $c ) {
        $id_to_comment[ (int) $c->comment_ID ] = $c;
        if ( 0 == $c->comment_parent ) {
            $top[] = $c;
        }
    }

    // Walk up to find the top-level ancestor for any comment
    $get_top_ancestor = function( $comment ) use ( &$get_top_ancestor, $id_to_comment ) {
        if ( 0 == $comment->comment_parent ) {
            return (int) $comment->comment_ID;
        }
        $parent_id = (int) $comment->comment_parent;
        if ( isset( $id_to_comment[ $parent_id ] ) ) {
            return $get_top_ancestor( $id_to_comment[ $parent_id ] );
        }
        return (int) $comment->comment_ID; // orphan fallback
    };

    // Flatten ALL replies under their top-level ancestor (enforces 2-level depth)
    $reply_groups = array(); // top_level_id => [ reply comments ]
    foreach ( $comments as $c ) {
        if ( 0 == $c->comment_parent ) continue;
        $ancestor_id = $get_top_ancestor( $c );
        $reply_groups[ $ancestor_id ][] = $c;
    }

    $count = count( $comments );

    // ── current user info for form ──────────────────────────────────
    $current_user = wp_get_current_user();
    $logged_in    = $current_user->exists();
    $user_name    = $logged_in ? esc_html( $current_user->display_name ) : '';
    $user_email   = $logged_in ? esc_html( $current_user->user_email )  : '';

    $uid = 'ctc-' . $post_id;

    ob_start();
    ?>
    <div class="cotlas-comments" id="<?php echo esc_attr( $uid ); ?>">

        <!-- ── Comment count heading ── -->
        <div class="ctc-heading">
            <span class="ctc-heading__icon">💬</span>
            <span class="ctc-heading__title"><?php echo esc_html( $atts['title'] ); ?></span>
            <?php if ( $count ) : ?>
                <span class="ctc-heading__count"><?php echo $count; ?></span>
            <?php endif; ?>
        </div>

        <!-- ── Comment form ── -->
        <?php if ( is_user_logged_in() || get_option( 'comment_registration' ) == 0 ) : ?>
        <form class="ctc-form" method="post" action="<?php echo esc_url( site_url('/wp-comments-post.php') ); ?>">
            <div class="ctc-form__row">
                <?php if ( $logged_in ) : ?>
                    <?php $av = cotlas_comment_avatar( $user_email, $user_name ); ?>
                    <div class="ctc-comment__avatar ctc-form__avatar" style="background:<?php echo esc_attr($av['color']); ?>">
                        <img src="<?php echo esc_url($av['src']); ?>" width="36" height="36" loading="lazy" onload="if(this.nextElementSibling) this.nextElementSibling.style.display='none';" onerror="this.style.display='none'" />
                        <span class="ctc-comment__initial"><?php echo esc_html($av['initials']); ?></span>
                    </div>
                <?php else : ?>
                    <div class="ctc-comment__avatar ctc-form__avatar" style="background:#aaa;">
                        <span class="ctc-comment__initial">?</span>
                    </div>
                <?php endif; ?>

                <div class="ctc-form__inputs">
                    <?php if ( ! $logged_in ) : ?>
                        <div class="ctc-form__guest-fields">
                            <input type="text" name="author" class="ctc-input" placeholder="Your Name *" required maxlength="100" />
                            <input type="email" name="email" class="ctc-input" placeholder="Email *" required maxlength="200" />
                        </div>
                    <?php endif; ?>
                    <div class="ctc-form__textarea-wrap">
                        <textarea name="comment" class="ctc-textarea" placeholder="Leave your feedback…" rows="3" required maxlength="1000"></textarea>
                    </div>
                    <div class="ctc-form__footer">
                        <button type="submit" class="ctc-submit">Post</button>
                    </div>
                </div>
            </div>
            <?php wp_nonce_field( 'comment-post', '_wp_nonce' ); ?>
            <input type="hidden" name="comment_post_ID" value="<?php echo $post_id; ?>" />
            <input type="hidden" name="comment_parent" value="0" class="ctc-parent-id" />
            <?php if ( $logged_in ) : ?>
                <input type="hidden" name="author" value="<?php echo $user_name; ?>" />
                <input type="hidden" name="email"  value="<?php echo $user_email; ?>" />
            <?php endif; ?>
            <?php do_action( 'comment_form', $post_id ); ?>
        </form>
        <?php endif; ?>

        <!-- ── Comment list ── -->
        <div class="ctc-list">
            <?php if ( empty( $top ) ) : ?>
                <p class="ctc-empty">No comments yet. Be the first to comment!</p>
            <?php else : ?>
                <?php foreach ( $top as $c ) :
                    echo cotlas_render_comment( $c, 0 );
                    $cid = (int) $c->comment_ID;
                    if ( ! empty( $reply_groups[ $cid ] ) ) : ?>
                        <div class="ctc-replies">
                            <?php foreach ( $reply_groups[ $cid ] as $rc ) {
                                echo cotlas_render_comment( $rc, 1, $cid );
                            } ?>
                        </div>
                    <?php endif;
                endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- .cotlas-comments -->
    <?php
    return ob_get_clean();
}
add_shortcode( 'cotlas_comments', 'cotlas_comments_shortcode' );

/**
 * AJAX handler: submit a new comment without forcing a full page reload.
 */
function cotlas_ajax_submit_comment() {
    $nonce = isset( $_POST['_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wp_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'comment-post' ) ) {
        wp_send_json_error( 'Invalid request' );
    }

    $post_id = isset( $_POST['comment_post_ID'] ) ? (int) $_POST['comment_post_ID'] : 0;
    if ( ! $post_id || ! comments_open( $post_id ) ) {
        wp_send_json_error( 'Comments are closed for this post' );
    }

    if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
        wp_send_json_error( 'Please log in to comment' );
    }

    if ( ! is_user_logged_in() && get_option( 'turnstile_enable_comments' ) && function_exists( 'cotlas_verify_turnstile' ) ) {
        $turnstile = cotlas_verify_turnstile();
        if ( is_wp_error( $turnstile ) ) {
            wp_send_json_error( wp_strip_all_tags( $turnstile->get_error_message() ) );
        }
    }

    $comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
    if ( is_wp_error( $comment ) ) {
        wp_send_json_error( wp_strip_all_tags( $comment->get_error_message() ) );
    }

    $user = wp_get_current_user();
    do_action( 'set_comment_cookies', $comment, $user );

    if ( ! is_user_logged_in() ) {
        $_COOKIE[ 'comment_author_' . COOKIEHASH ]       = $comment->comment_author;
        $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = $comment->comment_author_email;
    }

    $parent_id = (int) $comment->comment_parent;
    $top_id    = $parent_id;
    if ( $parent_id ) {
        $parent = get_comment( $parent_id );
        if ( $parent && (int) $parent->comment_parent ) {
            $top_id = (int) $parent->comment_parent;
        }
    }

    wp_send_json_success( array(
        'html'      => cotlas_render_comment( $comment, $parent_id ? 1 : 0, $top_id ),
        'commentId' => (int) $comment->comment_ID,
        'parentId'  => $parent_id,
        'topId'     => $top_id,
        'approved'  => '1' === $comment->comment_approved,
    ) );
}
add_action( 'wp_ajax_cotlas_submit_comment',        'cotlas_ajax_submit_comment' );
add_action( 'wp_ajax_nopriv_cotlas_submit_comment', 'cotlas_ajax_submit_comment' );

/**
 * AJAX handler: edit own comment (logged-in user or matching guest cookie within 60 min).
 */
function cotlas_ajax_edit_comment() {
    $cid     = isset( $_POST['comment_id'] ) ? (int) $_POST['comment_id'] : 0;
    $content = isset( $_POST['content'] )    ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
    $nonce   = isset( $_POST['nonce'] )      ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    if ( ! $cid || ! $content || ! wp_verify_nonce( $nonce, 'ctc_edit_' . $cid ) ) {
        wp_send_json_error( 'Invalid request' );
    }

    $comment = get_comment( $cid );
    if ( ! $comment ) {
        wp_send_json_error( 'Comment not found' );
    }

    // Re-verify ownership server-side
    $can_edit = false;
    $cu = wp_get_current_user();
    if ( $cu->exists() && $cu->ID > 0 && (int) $comment->user_id === $cu->ID ) {
        $can_edit = true;
    } else {
        $ck_author = isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] )
            ? sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) : '';
        $ck_email  = isset( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] )
            ? sanitize_email( wp_unslash( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] ) ) : '';
        $posted    = (int) get_comment_date( 'U', $comment );
        if ( $ck_author !== '' && $ck_email !== ''
             && $ck_author === $comment->comment_author
             && $ck_email  === $comment->comment_author_email
             && ( time() - $posted ) < 3600 ) {
            $can_edit = true;
        }
    }

    if ( ! $can_edit ) {
        wp_send_json_error( 'Permission denied' );
    }

    wp_update_comment( array(
        'comment_ID'      => $cid,
        'comment_content' => $content,
    ) );

    wp_send_json_success( array(
        'html' => wpautop( esc_html( $content ) ),
        'raw'  => $content,
    ) );
}
add_action( 'wp_ajax_cotlas_edit_comment',        'cotlas_ajax_edit_comment' );
add_action( 'wp_ajax_nopriv_cotlas_edit_comment', 'cotlas_ajax_edit_comment' );

/**
 * Inline JS to handle Reply button — clones the main form into the reply slot
 * and sets comment_parent. No extra enqueue needed; runs after DOM is ready.
 */
add_action( 'wp_footer', function () { ?>
<script>
var ctcAjaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
(function(){
    function resetTurnstile(form) {
        var widget = form && form.querySelector('.cf-turnstile');
        if (widget && window.turnstile) {
            window.turnstile.reset(widget);
        }
    }

    function updateHeadingCount(widget) {
        var heading = widget && widget.querySelector('.ctc-heading');
        if (!heading) return;
        var count = heading.querySelector('.ctc-heading__count');
        if (!count) {
            count = document.createElement('span');
            count.className = 'ctc-heading__count';
            count.textContent = '0';
            heading.appendChild(count);
        }
        count.textContent = String((parseInt(count.textContent, 10) || 0) + 1);
    }

    function insertComment(widget, data) {
        if (!widget || !data || !data.html) return;
        var list = widget.querySelector('.ctc-list');
        if (!list) return;

        var empty = list.querySelector('.ctc-empty');
        if (empty) {
            empty.parentNode.removeChild(empty);
        }

        var temp = document.createElement('div');
        temp.innerHTML = data.html;
        var commentNode = temp.firstElementChild;
        if (!commentNode) return;

        if (data.parentId && data.topId) {
            var topComment = document.getElementById('ctc-c-' + data.topId);
            var replies = topComment && topComment.nextElementSibling && topComment.nextElementSibling.classList.contains('ctc-replies')
                ? topComment.nextElementSibling
                : null;
            if (!replies && topComment) {
                replies = document.createElement('div');
                replies.className = 'ctc-replies';
                topComment.parentNode.insertBefore(replies, topComment.nextSibling);
            }
            if (replies) {
                replies.appendChild(commentNode);
            } else {
                list.appendChild(commentNode);
            }
        } else {
            list.appendChild(commentNode);
        }

        updateHeadingCount(widget);
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.ctc-reply-btn');
        if (!btn) return;
        var cid   = btn.getAttribute('data-cid');
        var slot  = document.getElementById('ctc-reply-' + cid);
        if (!slot) return;
        // Toggle: if already open, close it
        if (slot.style.display !== 'none' && slot.innerHTML.trim()) {
            slot.style.display = 'none';
            slot.innerHTML = '';
            return;
        }
        // Clone the main form
        var widget = btn.closest('.cotlas-comments');
        var mainForm = widget ? widget.querySelector('.ctc-form') : null;
        if (!mainForm) return;
        var clone = mainForm.cloneNode(true);
        clone.querySelector('.ctc-parent-id').value = cid;
        clone.querySelector('.ctc-textarea').placeholder = 'Write a reply…';
        clone.classList.add('ctc-form--reply');
        // Cancel button
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'ctc-cancel-reply';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', function(){
            slot.style.display = 'none';
            slot.innerHTML = '';
        });
        clone.appendChild(cancel);
        slot.innerHTML = '';
        slot.appendChild(clone);
        
        var tsDiv = clone.querySelector('.cf-turnstile');
        if (tsDiv && window.turnstile) {
            tsDiv.innerHTML = ''; // clear cloned iframe and inputs
            turnstile.render(tsDiv, { sitekey: tsDiv.getAttribute('data-sitekey') });
        }
        
        slot.style.display = 'block';
        clone.querySelector('.ctc-textarea').focus();
        return;
    });

    // ── New comment / reply submit ──────────────────────────────────
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('.ctc-form');
        if (!form || !form.closest('.cotlas-comments')) return;
        e.preventDefault();

        var submit = form.querySelector('.ctc-submit');
        var originalText = submit ? submit.textContent : '';
        if (submit) {
            submit.textContent = 'Posting...';
            submit.disabled = true;
        }

        var fd = new FormData(form);
        fd.set('action', 'cotlas_submit_comment');

        fetch(ctcAjaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    alert('Error: ' + (data.data || 'Unable to post comment'));
                    resetTurnstile(form);
                    return;
                }

                var widget = form.closest('.cotlas-comments');
                insertComment(widget, data.data);

                var textarea = form.querySelector('.ctc-textarea');
                if (textarea) {
                    textarea.value = '';
                }

                if (form.classList.contains('ctc-form--reply')) {
                    var slot = form.closest('.ctc-inline-reply');
                    if (slot) {
                        slot.style.display = 'none';
                        slot.innerHTML = '';
                    }
                } else {
                    resetTurnstile(form);
                }
            })
            .catch(function() {
                alert('Error: Unable to post comment');
                resetTurnstile(form);
            })
            .finally(function() {
                if (submit) {
                    submit.textContent = originalText || 'Post';
                    submit.disabled = false;
                }
            });
    });

    // ── Inline edit ──────────────────────────────────────────────────
    document.addEventListener('click', function(e) {

        // Edit button
        var editBtn = e.target.closest('.ctc-edit-btn');
        if (editBtn) {
            var cid       = editBtn.getAttribute('data-cid');
            var nonce     = editBtn.getAttribute('data-nonce');
            var commentEl = document.getElementById('ctc-c-' + cid);
            if (!commentEl) return;
            var bubbleEl  = commentEl.querySelector('.ctc-comment__bubble');
            var textEl    = commentEl.querySelector('.ctc-comment__text');
            if (!textEl || bubbleEl.querySelector('.ctc-edit-wrap')) return;
            var raw = textEl.getAttribute('data-raw') || textEl.textContent.trim();
            var wrap = document.createElement('div');
            wrap.className = 'ctc-edit-wrap';
            wrap.innerHTML =
                '<textarea class="ctc-textarea ctc-edit-textarea" rows="3" maxlength="1000"></textarea>' +
                '<div class="ctc-edit-actions">' +
                  '<button type="button" class="ctc-submit ctc-edit-save" data-cid="' + cid + '" data-nonce="' + nonce + '">Save</button>' +
                  '<button type="button" class="ctc-cancel-reply ctc-edit-cancel">Cancel</button>' +
                '</div>';
            wrap.querySelector('textarea').value = raw;
            textEl.style.display = 'none';
            bubbleEl.appendChild(wrap);
            wrap.querySelector('textarea').focus();
            editBtn.style.display = 'none';
            return;
        }

        // Cancel edit
        var cancelEdit = e.target.closest('.ctc-edit-cancel');
        if (cancelEdit) {
            var bubbleEl  = cancelEdit.closest('.ctc-comment__bubble');
            var commentEl = cancelEdit.closest('.ctc-comment');
            var wrap      = bubbleEl && bubbleEl.querySelector('.ctc-edit-wrap');
            var textEl    = bubbleEl && bubbleEl.querySelector('.ctc-comment__text');
            var editBtn   = commentEl && commentEl.querySelector('.ctc-edit-btn');
            if (wrap)    { bubbleEl.removeChild(wrap); }
            if (textEl)  { textEl.style.display = ''; }
            if (editBtn) { editBtn.style.display = ''; }
            return;
        }

        // Save edit
        var saveBtn = e.target.closest('.ctc-edit-save');
        if (saveBtn) {
            var cid   = saveBtn.getAttribute('data-cid');
            var nonce = saveBtn.getAttribute('data-nonce');
            var wrap  = saveBtn.closest('.ctc-edit-wrap');
            var ta    = wrap && wrap.querySelector('textarea');
            if (!ta) return;
            var newText = ta.value.trim();
            if (!newText) return;
            saveBtn.textContent = '…';
            saveBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'cotlas_edit_comment');
            fd.append('comment_id', cid);
            fd.append('content', newText);
            fd.append('nonce', nonce);
            fetch(ctcAjaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        saveBtn.textContent = 'Save';
                        saveBtn.disabled = false;
                        alert('Error: ' + (data.data || 'unknown'));
                        return;
                    }
                    var bubbleEl  = wrap.closest('.ctc-comment__bubble');
                    var textEl    = bubbleEl && bubbleEl.querySelector('.ctc-comment__text');
                    var commentEl = wrap.closest('.ctc-comment');
                    var editBtn   = commentEl && commentEl.querySelector('.ctc-edit-btn');
                    if (textEl) {
                        textEl.innerHTML = data.data.html;
                        textEl.setAttribute('data-raw', data.data.raw);
                        textEl.style.display = '';
                    }
                    if (wrap && wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
                    if (editBtn) { editBtn.style.display = ''; }
                })
                .catch(function() {
                    saveBtn.textContent = 'Save';
                    saveBtn.disabled = false;
                });
        }
    });
})();
</script>
<?php }, 20 );
