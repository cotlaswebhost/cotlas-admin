<?php
/**
 * All GenerateBlocks dynamic tag registrations for this plugin.
 *
 * Covers: company_info, company_social, yoast_primary_category,
 *         human_date, post_views, term_display, term_image, featured/popular query.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'cotlas_gb_tags_enabled' ) ) { return; }



// GenerateBlocks dynamic tag for one category inside query loops.
add_action( 'init', 'gp_register_yoast_primary_category_dynamic_tag', 20 );
function gp_register_yoast_primary_category_dynamic_tag() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag(
        array(
            'title'       => __( 'Yoast Primary Category', 'cotlas-news' ),
            'tag'         => 'yoast_primary_category',
            'type'        => 'post',
            'supports'    => array( 'source' ),
            'description' => __( 'Show the Yoast primary category, or the first category as a fallback.', 'cotlas-news' ),
            'options'     => array(
                'fallback' => array(
                    'type'    => 'select',
                    'label'   => __( 'Fallback', 'cotlas-news' ),
                    'default' => 'first',
                    'options' => array(
                        array(
                            'value' => 'first',
                            'label' => __( 'First category', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'none',
                            'label' => __( 'Nothing', 'cotlas-news' ),
                        ),
                    ),
                ),
                'output' => array(
                    'type'    => 'select',
                    'label'   => __( 'Output', 'cotlas-news' ),
                    'default' => 'name',
                    'options' => array(
                        array(
                            'value' => 'name',
                            'label' => __( 'Category name', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'linked_name',
                            'label' => __( 'Linked category name', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'url',
                            'label' => __( 'Category URL', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'slug',
                            'label' => __( 'Category slug', 'cotlas-news' ),
                        ),
                        array(
                            'value' => 'id',
                            'label' => __( 'Category ID', 'cotlas-news' ),
                        ),
                    ),
                ),
            ),
            'return'      => 'gp_yoast_primary_category_dynamic_tag',
        )
    );
}

function gp_yoast_primary_category_dynamic_tag( $options, $block, $instance ) {
    $post_id = class_exists( 'GenerateBlocks_Dynamic_Tags' )
        ? GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance )
        : get_the_ID();

    $fallback = isset( $options['fallback'] ) ? $options['fallback'] : 'first';
    $output   = isset( $options['output'] ) ? $options['output'] : 'name';
    $category = gp_get_yoast_primary_category( $post_id, $fallback );

    if ( ! $category ) {
        $value = '';
    } elseif ( 'url' === $output ) {
        $value = get_category_link( $category->term_id );
        $value = is_wp_error( $value ) ? '' : esc_url( $value );
    } elseif ( 'slug' === $output ) {
        $value = esc_html( $category->slug );
    } elseif ( 'id' === $output ) {
        $value = (string) absint( $category->term_id );
    } elseif ( 'linked_name' === $output ) {
        $url   = get_category_link( $category->term_id );
        $value = is_wp_error( $url )
            ? esc_html( $category->name )
            : sprintf(
                '<a href="%s" rel="category tag">%s</a>',
                esc_url( $url ),
                esc_html( $category->name )
            );
    } else {
        $value = esc_html( $category->name );
    }

    if ( class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $value, $options, $instance );
    }

    return $value;
}


/**
 * [human_date] shortcode — fallback for non-GenerateBlocks contexts.
 * Usage: [human_date] or [human_date type="modified"]
 */
/**
 * Human-readable relative date helper.
 * Returns "X minutes ago / X hours ago" within 24 hours, otherwise the formatted date.
 *
 * @param  int    $post_id  Post ID.
 * @param  string $type     'published' (default) | 'modified'.
 * @return string
 */
function cotlas_human_date( $post_id, $type = 'published' ) {
    $ts = 'modified' === $type ? get_post_modified_time( 'U', false, $post_id ) : get_post_time( 'U', false, $post_id );
    if ( ! $ts ) {
        return '';
    }

    $diff = time() - $ts;

    if ( $diff < 60 ) {
        return 'अभी-अभी';
    } elseif ( $diff < 3600 ) {
        $mins = (int) round( $diff / 60 );
        return $mins . ' मिनट पहले';
    } elseif ( $diff < 86400 ) {
        $hrs = (int) round( $diff / 3600 );
        return $hrs . ' घंटे पहले';
    } else {
        // Older than 24 h — show formatted date using site date format
        return get_the_date( get_option( 'date_format' ), $post_id );
    }
}

add_shortcode( 'human_date', function ( $atts ) {
    $atts = shortcode_atts( [ 'type' => 'published', 'id' => 0 ], $atts, 'human_date' );
    $id   = $atts['id'] ? (int) $atts['id'] : get_the_ID();
    if ( ! $id ) {
        return '';
    }
    return esc_html( cotlas_human_date( $id, sanitize_text_field( $atts['type'] ) ) );
} );

/**
 * Register {{human_date}} as a GenerateBlocks Dynamic Tag.
 * Appears in the Dynamic Tags modal as "Human Date (Relative)".
 * Supports: link, source (just like post_date).
 * Options:  type — published (default) | modified
 */
add_action( 'init', function () {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'    => __( 'Human Date (Relative)', 'generateblocks' ),
            'tag'      => 'human_date',
            'type'     => 'post',
            'supports' => [ 'link', 'source' ],
            'options'  => [
                'type' => [
                    'type'    => 'select',
                    'label'   => __( 'Date type', 'generateblocks' ),
                    'default' => '',
                    'options' => [
                        [ 'value' => '',         'label' => __( 'Published', 'generateblocks' ) ],
                        [ 'value' => 'modified', 'label' => __( 'Modified',  'generateblocks' ) ],
                    ],
                ],
            ],
            'return'   => function ( $options, $block, $instance ) {
                $id   = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
                if ( ! $id ) {
                    return '';
                }
                $type   = isset( $options['type'] ) && 'modified' === $options['type'] ? 'modified' : 'published';
                $output = cotlas_human_date( $id, $type );

                // Honour the link:post / link:none option the same way GB's post_date does
                if ( ! empty( $options['link'] ) && 'none' !== $options['link'] ) {
                    $url = get_permalink( $id );
                    if ( $url ) {
                        $output = '<a href="' . esc_url( $url ) . '">' . esc_html( $output ) . '</a>';
                        return $output; // already escaped
                    }
                }

                return esc_html( $output );
            },
        ]
    );
}, 20 );


// Register GenerateBlocks Dynamic Tags using Cotlas Site Settings
add_action('init', function() {
    if (!class_exists('GenerateBlocks_Register_Dynamic_Tag')) {
        return;
    }

    new GenerateBlocks_Register_Dynamic_Tag([
        'title'    => __('Company Info', 'cotlas'),
        'tag'      => 'company_info',
        'type'     => 'option',
        'supports' => [],
        'options'  => [
            'field' => [
                'type'    => 'select',
                'label'   => __('Field', 'cotlas'),
                'default' => 'company_name',
                'options' => [
                    ['value' => 'company_name',    'label' => __('Company Name', 'cotlas')],
                    ['value' => 'company_tagline', 'label' => __('Company Tagline', 'cotlas')],
                    ['value' => 'company_address', 'label' => __('Company Address', 'cotlas')],
                    ['value' => 'company_phone',   'label' => __('Company Phone', 'cotlas')],
                    ['value' => 'company_email',   'label' => __('Company Email', 'cotlas')],
                    ['value' => 'company_short_intro','label' => __('Company Short Intro', 'cotlas')],
                    ['value' => 'company_whatsapp','label' => __('Company WhatsApp', 'cotlas')],
                ],
            ],
        ],
        'return' => 'cotlas_company_info_dynamic_tag',
    ]);

    new GenerateBlocks_Register_Dynamic_Tag([
        'title'    => __('Company Social URL', 'cotlas'),
        'tag'      => 'company_social',
        'type'     => 'option',
        'supports' => [],
        'options'  => [
            'network' => [
                'type'    => 'select',
                'label'   => __('Social Network', 'cotlas'),
                'default' => 'facebook',
                'options' => [
                    ['value' => 'facebook',  'label' => __('Facebook', 'cotlas')],
                    ['value' => 'twitter',   'label' => __('Twitter/X', 'cotlas')],
                    ['value' => 'instagram', 'label' => __('Instagram', 'cotlas')],
                    ['value' => 'linkedin',  'label' => __('LinkedIn', 'cotlas')],
                    ['value' => 'youtube',   'label' => __('YouTube', 'cotlas')],
                    ['value' => 'threads',   'label' => __('Threads', 'cotlas')],
                    ['value' => 'whatsapp',  'label' => __('WhatsApp', 'cotlas')],
                ],
            ],
        ],
        'return' => 'cotlas_company_social_dynamic_tag',
    ]);
});

function cotlas_company_info_dynamic_tag($options, $block, $instance) {
    $field = isset($options['field']) ? $options['field'] : 'company_name';
    $map = [
        'company_name'     => 'cotlas_company_name',
        'company_tagline'  => 'cotlas_company_tagline',
        'company_address'  => 'cotlas_company_address',
        'company_phone'    => 'cotlas_company_phone',
        'company_email'    => 'cotlas_company_email',
        'company_short_intro' => 'cotlas_company_short_intro',
        'company_whatsapp' => 'cotlas_company_whatsapp',
    ];
    if (!isset($map[$field])) {
        return '';
    }
    $value = get_option($map[$field]);
    if ($field === 'company_address') {
        $value = nl2br(esc_html($value));
    }
    if ($field === 'company_tagline') {
        $value = esc_html($value);
    }
    if ($field === 'company_short_intro') {
        $value = wp_kses_post($value);
    }
    if (class_exists('GenerateBlocks_Dynamic_Tag_Callbacks')) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output($value, $options, $instance);
    }
    return $value;
}

function cotlas_company_social_dynamic_tag($options, $block, $instance) {
    $network = isset($options['network']) ? $options['network'] : 'facebook';
    $map = [
        'facebook'  => 'cotlas_social_facebook',
        'twitter'   => 'cotlas_social_twitter',
        'instagram' => 'cotlas_social_instagram',
        'linkedin'  => 'cotlas_social_linkedin',
        'youtube'   => 'cotlas_social_youtube',
        'threads'   => 'cotlas_social_threads',
        'whatsapp'  => 'cotlas_company_whatsapp',
    ];
    if (!isset($map[$network])) {
        return '';
    }
    $value = get_option($map[$network]);
    if ($network === 'whatsapp' && !empty($value)) {
        $clean = preg_replace('/\\D+/', '', $value);
        if ($clean) {
            $value = 'https://wa.me/' . $clean;
        }
    }
    if (class_exists('GenerateBlocks_Dynamic_Tag_Callbacks')) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output($value, $options, $instance);
    }
    return $value;
}

add_action( 'wp_loaded', 'gpc_register_post_views_tag' );
/**
 * gpc_register_post_views_tag.
 */
function gpc_register_post_views_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'post_views',
			'title'       => __( 'Post Views Count', 'gp-child' ),
			'description' => __( 'Returns the raw view count number for the current post (Post Views Counter plugin). No icon or HTML — just the number.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'return'      => 'gpc_post_views_callback',
		)
	);
}

/**
 * Callback — returns the view count as a plain string.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_post_views_callback( $options, $block, $instance ) {
	if ( ! empty( $options['id'] ) ) {
		$post_id = absint( $options['id'] );
	} else {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return '0';
	}

	if ( ! function_exists( 'pvc_get_post_views' ) ) {
		return '0';
	}

	$views = pvc_get_post_views( $post_id );

	return (string) absint( $views );
}
/**
 * Custom GenerateBlocks Dynamic Tag: Primary Category
 *
 * Outputs the Yoast SEO primary category for the current post.
 * Falls back to the first assigned category when no primary is set.
 *
 * Usage in a GB Text block:
 *   {{primary_category}}                       – plain category name
 *   {{primary_category link:term}}             – linked category name
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register the dynamic tag once GB has loaded its own tags ─────────────────

add_action( 'init', 'gpc_register_primary_category_tag', 20 );
/**
 * gpc_register_primary_category_tag.
 */
function gpc_register_primary_category_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'primary_category',
			'title'       => __( 'Primary Category', 'gp-child' ),
			'description' => __( 'Get the Yoast SEO primary category for the current post. Falls back to the first assigned category.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'link', 'source', 'taxonomy' ),
			'return'      => 'gpc_primary_category_callback',
		)
	);
}

// ── Callback ─────────────────────────────────────────────────────────────────

/**
 * Returns the primary category name (or linked name).
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    The block data.
 * @param object $instance The block instance.
 * @return string
 */
function gpc_primary_category_callback( $options, $block, $instance ) {
	$post_id = isset( $options['source'] ) && 'current' !== $options['source']
		? absint( $options['source'] )
		: get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	// Try Yoast primary category first.
	$primary_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );

	if ( $primary_id ) {
		$term = get_term( $primary_id, 'category' );
	}

	// Fall back to the first assigned category.
	if ( empty( $primary_id ) || is_wp_error( $term ) || ! $term ) {
		$terms = get_the_terms( $post_id, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$term = reset( $terms );
	}

	$name = esc_html( $term->name );

	// If "Link To: Term" is selected, wrap in an anchor tag.
	if ( ! empty( $options['link'] ) ) {
		$url = get_term_link( $term, 'category' );
		if ( ! is_wp_error( $url ) ) {
			return '<a href="' . esc_url( $url ) . '" rel="tag">' . $name . '</a>';
		}
	}

	return $name;
}
/**
 * Custom GenerateBlocks Dynamic Tag: Term Display
 *
 * A structured alternative to Term Meta that provides predefined display
 * options for any taxonomy term — no manual meta key needed.
 *
 * Options available via KEY:
 *   term_title   – the term name (e.g. "Property Investment India")
 *   term_desc    – the term description
 *   term_image   – URL of the category featured image (set via category-image.php)
 *   term_count   – number of posts in the term
 *   term_url     – URL of the term archive page
 *
 * Usage examples in GB blocks:
 *   {{term_display key:term_title}}                      current term name
 *   {{term_display id:33|tax:category|key:term_title}}   specific term name
 *   {{term_display id:33|tax:category|key:term_image}}   image URL
 *   {{term_display id:33|tax:category|key:term_url}}     archive URL
 *   {{term_display id:33|tax:category|key:term_count}}   post count
 *
 * TIP — To link an Image block to the term archive:
 *   Image → IMAGE field  → {{term_display key:term_image}}
 *   Image → LINK field   → {{term_display key:term_url}}
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register the dynamic tag ─────────────────────────────────────────────────

add_action( 'init', 'gpc_register_term_display_tag', 20 );
/**
 * gpc_register_term_display_tag.
 */
function gpc_register_term_display_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'term_display',
			'title'       => __( 'Term Display', 'gp-child' ),
			'description' => __( 'Display term data (name, description, image, post count, or archive URL) for a category or any taxonomy term.', 'gp-child' ),
			'type'        => 'term',         // Enables Taxonomy + Term dropdowns in the editor UI.
			'supports'    => array( 'source' ), // 'source' enables Current Term / specific term selector.
			'options'     => array(
				'key' => array(
					'type'    => 'select',
					'label'   => __( 'Display', 'gp-child' ),
					'default' => 'term_title',
					'options' => array(
						'term_title',
						'term_desc',
						'term_image',
						'term_count',
						'term_url',
					),
				),
			),
			'return'      => 'gpc_term_display_callback',
		)
	);
}

// ── Callback ─────────────────────────────────────────────────────────────────

/**
 * Returns the requested term data.
 *
 * @param array  $options  Parsed tag options (id, tax, key, source …).
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_term_display_callback( $options, $block, $instance ) {
	$key      = ! empty( $options['key'] ) ? sanitize_key( $options['key'] ) : 'term_title';
	$taxonomy = ! empty( $options['tax'] ) ? sanitize_text_field( $options['tax'] ) : 'category';

	// ── Resolve the term ─────────────────────────────────────────────────────
	$term = null;

	if ( ! empty( $options['id'] ) ) {
		// Explicit term ID passed via the editor.
		$term = get_term( absint( $options['id'] ), $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		// Try the current queried object (category/tag archive pages).
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			$term = $queried;
		}
	}

	if ( ! $term || is_wp_error( $term ) ) {
		// Inside a post loop — fall back to the Yoast primary category.
		$post_id = get_the_ID();
		if ( $post_id ) {
			$primary = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
			if ( $primary ) {
				$term = get_term( $primary, 'category' );
			}
			if ( ! $term || is_wp_error( $term ) ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$term = reset( $terms );
				}
			}
		}
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	// ── Return the requested data ─────────────────────────────────────────────
	switch ( $key ) {

		case 'term_title':
			return esc_html( $term->name );

		case 'term_desc':
			return wp_kses_post( $term->description );

		case 'term_count':
			return (string) absint( $term->count );

		case 'term_url':
			$url = get_term_link( $term, $taxonomy );
			return is_wp_error( $url ) ? '' : esc_url( $url );

		case 'term_image':
			$attachment_id = (int) get_term_meta( $term->term_id, '_category_image_id', true );
			if ( ! $attachment_id ) {
				return '';
			}
			$size = ! empty( $options['size'] ) ? sanitize_text_field( $options['size'] ) : 'full';
			$url  = wp_get_attachment_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';

		default:
			return '';
	}
}


/**
 * Category Featured Image
 *
 * - Adds an image upload field on the Category add/edit screens.
 * - Saves the attachment ID in term meta `_category_image_id`.
 * - Registers a GenerateBlocks dynamic tag `{{term_image}}` that returns
 *   the image URL (or id / alt) for the current or specified term.
 *
 * Usage in a GB Image / Media block dynamic tag:
 *   {{term_image}}                   – full-size image URL of current term
 *   {{term_image id:12}}             – image URL for category with term_id 12
 *   {{term_image size:medium}}       – specific registered image size
 *   {{term_image key:id}}            – returns the attachment post ID instead
 *   {{term_image key:alt}}           – returns the alt text
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. REGISTER TERM META ────────────────────────────────────────────────────
add_action( 'init', 'gpc_register_term_image_tag', 20 );
/**
 * gpc_register_term_image_tag.
 */
function gpc_register_term_image_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'term_image',
			'title'       => __( 'Term / Category Image', 'gp-child' ),
			'description' => __( 'Get the featured image for a category or taxonomy term. Use id: to specify a term, size: for image size, key: for url/id/alt.', 'gp-child' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'return'      => 'gpc_term_image_callback',
		)
	);
}

/**
 * Dynamic tag callback — returns the category image URL (or id/alt).
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_term_image_callback( $options, $block, $instance ) {

	// Get term_id: explicit option > current queried object > primary category of current post.
	if ( ! empty( $options['id'] ) ) {
		$term_id = absint( $options['id'] );
	} else {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			$term_id = $queried->term_id;
		} else {
			// Inside a post loop — use Yoast primary or first category.
			$post_id     = get_the_ID();
			$primary_raw = get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
			if ( $primary_raw ) {
				$term_id = (int) $primary_raw;
			} else {
				$terms = get_the_terms( $post_id, 'category' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return '';
				}
				$term_id = reset( $terms )->term_id;
			}
		}
	}

	if ( empty( $term_id ) ) {
		return '';
	}

	$attachment_id = (int) get_term_meta( $term_id, '_category_image_id', true );

	if ( ! $attachment_id ) {
		return '';
	}

	$key  = ! empty( $options['key'] ) ? sanitize_text_field( $options['key'] ) : 'url';
	$size = ! empty( $options['size'] ) ? sanitize_text_field( $options['size'] ) : 'full';

	switch ( $key ) {
		case 'id':
			return (string) $attachment_id;

		case 'alt':
			return esc_attr( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		case 'url':
		default:
			$url = wp_get_attachment_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';
	}
}

/**
 * Featured Post Functionality
 *
 * - Registers _is_featured post meta (REST-enabled for block editor).
 * - Adds 'Featured Post' toggle panel in the block-editor sidebar via JS.
 * - Injects a 'Featured posts' select param into GenerateBlocks Query blocks.
 * - Filters generateblocks_query_loop_args to apply meta_query on the server.
 *
 * @package GeneratePress Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. REGISTER POST META

add_action( 'init', 'gpc_register_featured_meta' );
/**
 * gpc_register_featured_meta.
 */
function gpc_register_featured_meta() {
	register_post_meta(
		'post',
		'_is_featured',
		array(
			'type'          => 'boolean',
			'single'        => true,
			'default'       => false,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

// 2. ENQUEUE BLOCK-EDITOR SCRIPT
// Must depend on 'generateblocks' so our addFilter calls run after GB
// has registered its own filter hooks.

add_action( 'enqueue_block_editor_assets', 'gpc_enqueue_featured_editor_assets' );
/**
 * gpc_enqueue_featured_editor_assets.
 */
function gpc_enqueue_featured_editor_assets() {
	wp_enqueue_script(
		'gpc-featured-post',
		plugin_dir_url( __FILE__ ) . 'assets/js/featured-post.js',
		array(
			'generateblocks',
			'wp-hooks',
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-i18n',
		),
		filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/featured-post.js' ),
		true
	);
}

// 3. PHP: APPLY featuredPosts QUERY PARAMETER

add_filter( 'generateblocks_query_loop_args', 'gpc_apply_featured_query', 10, 2 );
/**
 * gpc_apply_featured_query.
 */
function gpc_apply_featured_query( $query_args, $attributes ) {

	$value = '';

	if ( isset( $query_args['featuredPosts'] ) ) {
		$value = sanitize_text_field( $query_args['featuredPosts'] );
		unset( $query_args['featuredPosts'] );
	}

	if ( empty( $value ) ) {
		return $query_args;
	}

	if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
		$query_args['meta_query'] = array();
	}

	if ( 'only' === $value ) {
		$query_args['meta_query'][] = array(
			'key'     => '_is_featured',
			'value'   => '1',
			'compare' => '=',
		);
	} elseif ( 'exclude' === $value ) {
		$query_args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_is_featured',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_is_featured',
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	return $query_args;
}

// 4. PHP: APPLY popularPosts QUERY PARAMETER
//
// PVC already hooks posts_join and posts_orderby for orderby:'post_views'.
// We just need to set orderby and ensure suppress_filters is false.

add_filter( 'generateblocks_query_loop_args', 'gpc_apply_popular_query', 10, 2 );
/**
 * gpc_apply_popular_query.
 */
function gpc_apply_popular_query( $query_args, $attributes ) {

	if ( empty( $query_args['popularPosts'] ) ) {
		return $query_args;
	}

	unset( $query_args['popularPosts'] );

	$query_args['orderby']           = 'post_views';
	$query_args['order']             = 'DESC';
	$query_args['suppress_filters']  = false;

	return $query_args;
}
