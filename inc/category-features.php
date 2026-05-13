<?php
/**
 * Focused categories term meta/shortcode and category featured image admin field with GB dynamic tag.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_category_features_enabled' ) ) { return; }

/* =============================================================================
 * FOCUSED CATEGORIES
 * Adds "Focused" and "Highlighted" toggles to category terms.
 * Shortcode: [focused_categories]
 * =============================================================================
 */

/**
 * Register term meta for 'focused' and 'highlighted' flags on categories.
 */
add_action( 'init', function () {
    register_term_meta( 'category', 'cotlas_focused', array(
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ) );
    register_term_meta( 'category', 'cotlas_highlighted', array(
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ) );
} );

/**
 * Add 'Focused' and 'Highlighted' fields on the Add Category screen.
 */
add_action( 'category_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="cotlas_focused"><?php esc_html_e( 'Focused', 'cotlas-admin' ); ?></label>
        <select name="cotlas_focused" id="cotlas_focused">
            <option value="no" selected="selected"><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
            <option value="yes"><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Mark this category as Focused to display it in the Focus Bar.', 'cotlas-admin' ); ?></p>
    </div>
    <div class="form-field">
        <label for="cotlas_highlighted"><?php esc_html_e( 'Highlighted', 'cotlas-admin' ); ?></label>
        <select name="cotlas_highlighted" id="cotlas_highlighted">
            <option value="no" selected="selected"><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
            <option value="yes"><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Show this category as the highlighted (orange pill) item in the Focus Bar.', 'cotlas-admin' ); ?></p>
    </div>
    <?php
} );

/**
 * Add 'Focused' and 'Highlighted' fields on the Edit Category screen.
 */
add_action( 'category_edit_form_fields', function ( $term ) {
    $focused_val     = get_term_meta( $term->term_id, 'cotlas_focused', true ) ?: 'no';
    $highlighted_val = get_term_meta( $term->term_id, 'cotlas_highlighted', true ) ?: 'no';
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="cotlas_focused"><?php esc_html_e( 'Focused', 'cotlas-admin' ); ?></label>
        </th>
        <td>
            <select name="cotlas_focused" id="cotlas_focused">
                <option value="no" <?php selected( $focused_val, 'no' ); ?>><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
                <option value="yes" <?php selected( $focused_val, 'yes' ); ?>><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Mark this category as Focused to display it in the Focus Bar.', 'cotlas-admin' ); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row">
            <label for="cotlas_highlighted"><?php esc_html_e( 'Highlighted', 'cotlas-admin' ); ?></label>
        </th>
        <td>
            <select name="cotlas_highlighted" id="cotlas_highlighted">
                <option value="no" <?php selected( $highlighted_val, 'no' ); ?>><?php esc_html_e( 'No', 'cotlas-admin' ); ?></option>
                <option value="yes" <?php selected( $highlighted_val, 'yes' ); ?>><?php esc_html_e( 'Yes', 'cotlas-admin' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Show this category as the highlighted (orange pill) item in the Focus Bar.', 'cotlas-admin' ); ?></p>
        </td>
    </tr>
    <?php
} );

/**
 * Save 'Focused' and 'Highlighted' meta when creating a category.
 */
add_action( 'created_category', function ( $term_id ) {
    if ( isset( $_POST['cotlas_focused'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_focused'] ) );
        update_term_meta( $term_id, 'cotlas_focused', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
    if ( isset( $_POST['cotlas_highlighted'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_highlighted'] ) );
        update_term_meta( $term_id, 'cotlas_highlighted', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
} );

/**
 * Save 'Focused' and 'Highlighted' meta when editing a category.
 */
add_action( 'edited_category', function ( $term_id ) {
    if ( isset( $_POST['cotlas_focused'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_focused'] ) );
        update_term_meta( $term_id, 'cotlas_focused', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
    if ( isset( $_POST['cotlas_highlighted'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['cotlas_highlighted'] ) );
        update_term_meta( $term_id, 'cotlas_highlighted', ( $value === 'yes' ) ? 'yes' : 'no' );
    }
} );

/**
 * Shortcode: [focused_categories]
 *
 * Attributes:
 *  label         — Left-side label text. Default: "फोकस"
 *  highlight     — slug of the category to highlight (orange pill). Default: first focused category.
 *  orderby       — name | count | id | term_order. Default: name
 *  order         — ASC | DESC. Default: ASC
 *  class         — extra CSS class on wrapper. Default: ""
 */
function cotlas_focused_categories_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'label'     => 'फोकस',
        'highlight' => '',
        'orderby'   => 'name',
        'order'     => 'ASC',
        'class'     => '',
    ), $atts, 'focused_categories' );

    $terms = get_terms( array(
        'taxonomy'   => 'category',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key'   => 'cotlas_focused',
                'value' => 'yes',
            ),
        ),
        'orderby' => sanitize_key( $atts['orderby'] ),
        'order'   => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    // Determine which slug gets the highlight pill.
    // Priority: 1) shortcode attr, 2) cotlas_highlighted term meta, 3) first term.
    $highlight_slug = sanitize_text_field( $atts['highlight'] );
    if ( empty( $highlight_slug ) ) {
        foreach ( $terms as $term ) {
            if ( get_term_meta( $term->term_id, 'cotlas_highlighted', true ) === 'yes' ) {
                $highlight_slug = $term->slug;
                break;
            }
        }
    }
    if ( empty( $highlight_slug ) ) {
        $highlight_slug = $terms[0]->slug;
    }

    // Move the highlighted term to the front of the list.
    if ( ! empty( $highlight_slug ) ) {
        $highlight_index = null;
        foreach ( $terms as $i => $term ) {
            if ( $term->slug === $highlight_slug ) {
                $highlight_index = $i;
                break;
            }
        }
        if ( $highlight_index !== null && $highlight_index > 0 ) {
            $highlighted_term = array_splice( $terms, $highlight_index, 1 );
            array_unshift( $terms, $highlighted_term[0] );
        }
    }

    $wrapper_class = 'cotlas-focus-bar';
    if ( ! empty( $atts['class'] ) ) {
        $wrapper_class .= ' ' . esc_attr( $atts['class'] );
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $wrapper_class ); ?>" role="navigation" aria-label="<?php esc_attr_e( 'Focused categories', 'cotlas-admin' ); ?>">
        <div class="cotlas-focus-bar__label" aria-hidden="true">
            <?php echo esc_html( $atts['label'] ); ?>
            <span class="cotlas-focus-bar__arrow">&#9658;</span>
        </div>
        <div class="cotlas-focus-bar__track">
            <ul class="cotlas-focus-bar__list">
                <?php foreach ( $terms as $term ) :
                    $is_highlight = ( $term->slug === $highlight_slug );
                    $item_class   = 'cotlas-focus-bar__item' . ( $is_highlight ? ' cotlas-focus-bar__item--highlight' : '' );
                    ?>
                    <li class="<?php echo esc_attr( $item_class ); ?>">
                        <a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'focused_categories', 'cotlas_focused_categories_shortcode' );


// ── 1. REGISTER TERM META ────────────────────────────────────────────────────

add_action( 'init', 'gpc_register_category_image_meta' );
/**
 * gpc_register_category_image_meta.
 */
function gpc_register_category_image_meta() {
	register_term_meta(
		'category',
		'_category_image_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		)
	);
}

// ── 2. ADMIN: ENQUEUE MEDIA UPLOADER ON TAXONOMY SCREENS ────────────────────

add_action( 'admin_enqueue_scripts', 'gpc_category_image_admin_scripts' );
/**
 * gpc_category_image_admin_scripts.
 */
function gpc_category_image_admin_scripts( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	if ( isset( $_GET['taxonomy'] ) && 'category' !== $_GET['taxonomy'] ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script(
		'jquery',
		"(function($){
			$(document).on('click', '.gpc-upload-cat-image', function(e){
				e.preventDefault();
				var btn     = $(this);
				var wrapper = btn.closest('.gpc-cat-image-wrap');
				var frame   = wp.media({
					title:    'Select Category Image',
					button:   { text: 'Use this image' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					wrapper.find('.gpc-cat-image-id').val(att.id);
					wrapper.find('.gpc-cat-preview').html(
						'<img src=\"' + att.url + '\" style=\"max-width:200px;margin-top:8px;\">' +
						'<br><a href=\"#\" class=\"gpc-remove-cat-image\" style=\"color:red;\">Remove image</a>'
					);
				});
				frame.open();
			});
			$(document).on('click', '.gpc-remove-cat-image', function(e){
				e.preventDefault();
				var wrapper = $(this).closest('.gpc-cat-image-wrap');
				wrapper.find('.gpc-cat-image-id').val('');
				wrapper.find('.gpc-cat-preview').html('');
			});
		})(jQuery);"
	);
}

// ── 3. ADMIN: ADD FIELD TO CATEGORY ADD FORM ────────────────────────────────

add_action( 'category_add_form_fields', 'gpc_category_image_add_field' );
/**
 * gpc_category_image_add_field.
 */
function gpc_category_image_add_field() {
	?>
	<div class="form-field term-image-wrap">
		<label for="gpc-category-image"><?php esc_html_e( 'Category Image', 'gp-child' ); ?></label>
		<div class="gpc-cat-image-wrap">
			<input type="hidden" name="gpc_category_image_id" class="gpc-cat-image-id" value="">
			<button type="button" class="button gpc-upload-cat-image"><?php esc_html_e( 'Upload / Select Image', 'gp-child' ); ?></button>
			<div class="gpc-cat-preview"></div>
		</div>
		<p><?php esc_html_e( 'Upload or select a featured image for this category.', 'gp-child' ); ?></p>
	</div>
	<?php
}

// ── 4. ADMIN: ADD FIELD TO CATEGORY EDIT FORM ───────────────────────────────

add_action( 'category_edit_form_fields', 'gpc_category_image_edit_field' );
/**
 * gpc_category_image_edit_field.
 */
function gpc_category_image_edit_field( $term ) {
	$image_id  = (int) get_term_meta( $term->term_id, '_category_image_id', true );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<tr class="form-field term-image-wrap">
		<th scope="row"><label for="gpc-category-image"><?php esc_html_e( 'Category Image', 'gp-child' ); ?></label></th>
		<td>
			<div class="gpc-cat-image-wrap">
				<input type="hidden" name="gpc_category_image_id" class="gpc-cat-image-id" value="<?php echo esc_attr( $image_id ?: '' ); ?>">
				<button type="button" class="button gpc-upload-cat-image"><?php esc_html_e( 'Upload / Select Image', 'gp-child' ); ?></button>
				<div class="gpc-cat-preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:200px;margin-top:8px;">
						<br><a href="#" class="gpc-remove-cat-image" style="color:red;"><?php esc_html_e( 'Remove image', 'gp-child' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Upload or select a featured image for this category.', 'gp-child' ); ?></p>
		</td>
	</tr>
	<?php
}

// ── 5. SAVE META ON CREATE & EDIT ────────────────────────────────────────────

add_action( 'created_category', 'gpc_save_category_image' );
add_action( 'edited_category', 'gpc_save_category_image' );
/**
 * gpc_save_category_image.
 */
function gpc_save_category_image( $term_id ) {
	if ( ! isset( $_POST['gpc_category_image_id'] ) ) {
		return;
	}
	$image_id = absint( $_POST['gpc_category_image_id'] );
	if ( $image_id ) {
		update_term_meta( $term_id, '_category_image_id', $image_id );
	} else {
		delete_term_meta( $term_id, '_category_image_id' );
	}
}

// ── 7. CATEGORY LIST TABLE: ADD IMAGE THUMBNAIL COLUMN ──────────────────────

add_filter( 'manage_edit-category_columns', 'gpc_category_image_column' );
/**
 * gpc_category_image_column.
 */
function gpc_category_image_column( $columns ) {
	// Insert after the checkbox column (before Name).
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'name' === $key ) {
			$new['category_image'] = __( 'Image', 'gp-child' );
		}
		$new[ $key ] = $label;
	}
	return $new;
}

add_filter( 'manage_category_custom_column', 'gpc_category_image_column_content', 10, 3 );
/**
 * gpc_category_image_column_content.
 */
function gpc_category_image_column_content( $content, $column_name, $term_id ) {
	if ( 'category_image' !== $column_name ) {
		return $content;
	}
	$image_id = (int) get_term_meta( $term_id, '_category_image_id', true );
	if ( $image_id ) {
		$url = wp_get_attachment_image_url( $image_id, array( 50, 50 ) );
		if ( $url ) {
			return '<img src="' . esc_url( $url ) . '" width="50" height="50" style="object-fit:cover;border-radius:4px;">';
		}
	}
	return '<span style="color:#aaa;">—</span>';
}

add_action( 'admin_head', 'gpc_category_image_column_css' );
/**
 * gpc_category_image_column_css.
 */
function gpc_category_image_column_css() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-category' !== $screen->id ) {
		return;
	}
	echo '<style>.column-category_image { width: 60px; text-align: center; }</style>';
}

