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
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/featured-post.js',
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

/* ═══════════════════════════════════════════════════════════════════════════
 * Dynamic Tag — {{bookmark_button}}
 *
 * Renders the same bookmark toggle button as [cotlas_bookmark].
 * Only registered when the reading-list module is active.
 *
 * Options:
 *   size  — button width/height in px (default 34)
 *   class — extra CSS class on the <button>
 * ═══════════════════════════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════════════════════════
 * Dynamic Tag — {{logout_url}} / {{login_url}}
 *
 * Return a plain URL (or label text) for use in the GB Text/Button block's
 * Link field or Content field. No HTML is returned — just a string.
 *
 * {{logout_url}}            → wp_logout_url( home_url() )  (only when logged in)
 * {{logout_url output:text}} → "Logout"  (localised label)
 * {{login_url}}             → wp_login_url( current URL )   (only when logged out)
 * {{login_url output:text}} → "Sign in"
 *
 * Because these are server-rendered, the block will simply output an empty
 * string when the condition is not met — use GB's visibility conditions or
 * a logged-in/logged-out wrapper to control visibility.
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'gpc_register_auth_url_tags', 20 );
function gpc_register_auth_url_tags() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	/* -- Logout URL -------------------------------------------------------- */
	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'logout_url',
			'title'       => __( 'Logout URL', 'cotlas-admin' ),
			'description' => __( 'Returns the logout URL (or the label text). Only outputs a value when the visitor is logged in.', 'cotlas-admin' ),
			'type'        => 'option',
			'supports'    => array(),
			'options'     => array(
				'output' => array(
					'type'    => 'select',
					'label'   => __( 'Output', 'cotlas-admin' ),
					'default' => 'url',
					'options' => array(
						array( 'value' => 'url',  'label' => __( 'URL',        'cotlas-admin' ) ),
						array( 'value' => 'text', 'label' => __( 'Label text', 'cotlas-admin' ) ),
					),
				),
				'label' => array(
					'type'    => 'text',
					'label'   => __( 'Custom label text', 'cotlas-admin' ),
					'default' => '',
				),
			),
			'return'      => 'gpc_logout_url_callback',
		)
	);

	/* -- Login URL --------------------------------------------------------- */
	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'login_url',
			'title'       => __( 'Login URL', 'cotlas-admin' ),
			'description' => __( 'Returns the login URL (or the label text). Only outputs a value when the visitor is logged out.', 'cotlas-admin' ),
			'type'        => 'option',
			'supports'    => array(),
			'options'     => array(
				'output' => array(
					'type'    => 'select',
					'label'   => __( 'Output', 'cotlas-admin' ),
					'default' => 'url',
					'options' => array(
						array( 'value' => 'url',  'label' => __( 'URL',        'cotlas-admin' ) ),
						array( 'value' => 'text', 'label' => __( 'Label text', 'cotlas-admin' ) ),
					),
				),
				'label' => array(
					'type'    => 'text',
					'label'   => __( 'Custom label text', 'cotlas-admin' ),
					'default' => '',
				),
				'redirect' => array(
					'type'    => 'select',
					'label'   => __( 'After login, redirect to', 'cotlas-admin' ),
					'default' => 'current',
					'options' => array(
						array( 'value' => 'current', 'label' => __( 'Current page', 'cotlas-admin' ) ),
						array( 'value' => 'home',    'label' => __( 'Homepage',     'cotlas-admin' ) ),
					),
				),
			),
			'return'      => 'gpc_login_url_callback',
		)
	);
}

/**
 * Callback for {{logout_url}}.
 */
function gpc_logout_url_callback( $options, $block, $instance ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$output = isset( $options['output'] ) ? $options['output'] : 'url';

	if ( 'text' === $output ) {
		$label = isset( $options['label'] ) && '' !== trim( $options['label'] )
			? $options['label']
			: __( 'Logout', 'cotlas-admin' );
		return esc_html( $label );
	}

	return esc_url( wp_logout_url( home_url() ) );
}

/**
 * Callback for {{login_url}}.
 */
function gpc_login_url_callback( $options, $block, $instance ) {
	if ( is_user_logged_in() ) {
		return '';
	}

	$output = isset( $options['output'] ) ? $options['output'] : 'url';

	if ( 'text' === $output ) {
		$label = isset( $options['label'] ) && '' !== trim( $options['label'] )
			? $options['label']
			: __( 'Sign in', 'cotlas-admin' );
		return esc_html( $label );
	}

	$redirect  = isset( $options['redirect'] ) ? $options['redirect'] : 'current';
	$redirect_url = ( 'home' === $redirect )
		? home_url()
		: ( ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url() ) );

	return esc_url( wp_login_url( $redirect_url ) );
}

add_action( 'init', 'gpc_register_bookmark_button_tag', 20 );
function gpc_register_bookmark_button_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'bookmark_button',
			'title'       => __( 'Bookmark Button', 'cotlas-admin' ),
			'description' => __( 'Renders the bookmark (reading list) toggle button for the current post. Works inside Query Loop or on single post pages.', 'cotlas-admin' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'options'     => array(
				'size'  => array(
					'type'    => 'text',
					'label'   => __( 'Button size (px)', 'cotlas-admin' ),
					'default' => '34',
				),
				'class' => array(
					'type'    => 'text',
					'label'   => __( 'Extra CSS class', 'cotlas-admin' ),
					'default' => '',
				),
			),
			'return'      => 'gpc_bookmark_button_callback',
		)
	);
}

/**
 * Dynamic tag callback — returns the bookmark button HTML.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_bookmark_button_callback( $options, $block, $instance ) {
	if ( function_exists( 'cotlas_reading_list_is_enabled' ) && ! cotlas_reading_list_is_enabled() ) {
		return '';
	}

	if ( ! function_exists( 'cotlas_bookmark_shortcode' ) ) {
		return '';
	}

	// Resolve the post ID the same way other post-type tags do.
	$post_id = 0;
	if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
		$post_id = (int) GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	// Render via the shortcode function (reuse all its logic).
	// We temporarily set up the post so get_the_ID() works inside the function.
	// Pass post_id explicitly so the shortcode never needs to call get_the_ID().
	// This is the fix for dynamic tags in Query Loops where get_the_ID() may
	// return the page/template ID rather than the current loop post's ID.
	$atts = array(
		'size'    => isset( $options['size'] ) && '' !== $options['size'] ? $options['size'] : '34',
		'class'   => isset( $options['class'] ) ? $options['class'] : '',
		'post_id' => $post_id,
	);

	return cotlas_bookmark_shortcode( $atts );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * Dynamic Tag — {{wishlist_button}}
 *
 * Renders the same wishlist heart toggle button as [cotlas_wishlist].
 * Only registered when the wishlist module is active.
 *
 * Options:
 *   size        — button width/height in px (default 34)
 *   show_count  — "true" (default) | "false"
 *   class       — extra CSS class on the <button>
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'gpc_register_wishlist_button_tag', 20 );
function gpc_register_wishlist_button_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'wishlist_button',
			'title'       => __( 'Wishlist Button', 'cotlas-admin' ),
			'description' => __( 'Renders the wishlist (heart) toggle button with wish count for the current post. Works inside Query Loop or on single post pages.', 'cotlas-admin' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'options'     => array(
				'size'       => array(
					'type'    => 'text',
					'label'   => __( 'Button size (px)', 'cotlas-admin' ),
					'default' => '34',
				),
				'show_count' => array(
					'type'    => 'select',
					'label'   => __( 'Show wish count', 'cotlas-admin' ),
					'default' => 'true',
					'options' => array(
						array( 'value' => 'true',  'label' => __( 'Yes', 'cotlas-admin' ) ),
						array( 'value' => 'false', 'label' => __( 'No',  'cotlas-admin' ) ),
					),
				),
				'class'      => array(
					'type'    => 'text',
					'label'   => __( 'Extra CSS class', 'cotlas-admin' ),
					'default' => '',
				),
			),
			'return'      => 'gpc_wishlist_button_callback',
		)
	);
}

/**
 * Dynamic tag callback — returns the wishlist button HTML.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_wishlist_button_callback( $options, $block, $instance ) {
	if ( function_exists( 'cotlas_wishlist_is_enabled' ) && ! cotlas_wishlist_is_enabled() ) {
		return '';
	}

	if ( ! function_exists( 'cotlas_wishlist_shortcode' ) ) {
		return '';
	}

	$post_id = 0;
	if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
		$post_id = (int) GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	// Pass post_id explicitly so the shortcode never needs to call get_the_ID().
	$atts = array(
		'size'       => isset( $options['size'] ) && '' !== $options['size'] ? $options['size'] : '34',
		'show_count' => isset( $options['show_count'] ) ? $options['show_count'] : 'true',
		'class'      => isset( $options['class'] ) ? $options['class'] : '',
		'post_id'    => $post_id,
	);

	return cotlas_wishlist_shortcode( $atts );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GB QUERY LOOP FILTER — myPosts parameter
 *
 * Filters a Query Loop to show only the currently logged-in user's own
 * published posts.  Logged-out visitors see zero results.
 * ═══════════════════════════════════════════════════════════════════════════ */

add_filter( 'generateblocks_query_loop_args', 'gpc_apply_my_posts_query', 10, 2 );
/**
 * Filter GB Query Loop args when the myPosts parameter is set.
 *
 * @param array $query_args  Current WP_Query args.
 * @param array $attributes  GB block attributes.
 * @return array
 */
function gpc_apply_my_posts_query( $query_args, $attributes ) {
	if ( empty( $query_args['myPosts'] ) ) {
		return $query_args;
	}

	unset( $query_args['myPosts'] );

	if ( ! is_user_logged_in() ) {
		// Return empty result set for logged-out visitors.
		$query_args['post__in'] = array( 0 );
		return $query_args;
	}

	$query_args['author']      = get_current_user_id();
	$query_args['post_status'] = 'publish';

	return $query_args;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * Dynamic Tag — {{edit_post_link}}
 *
 * Returns the URL of your front-end edit page with the current post's ID
 * appended as a query-string parameter, e.g.:
 *   /edit-post?post=21
 *
 * Use this tag in the Link field of a GB Text block inside a Query Loop so
 * each loop item links to that post's edit page.
 *
 * Options:
 *   page   — slug of the edit page (default: edit-post)
 *   param  — query-string key       (default: post)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'gpc_register_edit_post_link_tag', 20 );
function gpc_register_edit_post_link_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'edit_post_link',
			'title'       => __( 'Edit Post Link', 'cotlas-admin' ),
			'description' => __( 'Returns the front-end edit page URL with the current post ID as a query parameter, e.g. /edit-post?post=21. Use in the Link field of a Text block inside a Query Loop.', 'cotlas-admin' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'options'     => array(
				'page'  => array(
					'type'    => 'text',
					'label'   => __( 'Edit page slug', 'cotlas-admin' ),
					'default' => 'edit-post',
				),
				'param' => array(
					'type'    => 'text',
					'label'   => __( 'Query param name', 'cotlas-admin' ),
					'default' => 'post',
				),
			),
			'return'      => 'gpc_edit_post_link_callback',
		)
	);
}

/**
 * Dynamic tag callback — builds the front-end edit URL.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_edit_post_link_callback( $options, $block, $instance ) {
	// Resolve post ID from the loop context.
	$post_id = 0;
	if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
		$post_id = (int) GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$page  = isset( $options['page'] ) && '' !== trim( $options['page'] )
		? sanitize_text_field( $options['page'] )
		: 'edit-post';

	$param = isset( $options['param'] ) && '' !== trim( $options['param'] )
		? sanitize_key( $options['param'] )
		: 'post';

	// Build the URL from the page slug so it works with any permalink structure.
	$page_obj = get_page_by_path( $page );
	$base_url = $page_obj ? get_permalink( $page_obj->ID ) : home_url( '/' . $page . '/' );

	return esc_url( add_query_arg( $param, $post_id, $base_url ) );
}

/* ═══════════════════════════════════════════════════════════════════════════
 * AJAX handler — trash a post from the frontend
 *
 * Triggered by visiting the URL produced by {{delete_post_link}}.
 * Security: per-post nonce + ownership check (author or edit_others_posts).
 * Result: post moved to trash, visitor redirected back to the specified page.
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_cotlas_trash_post', 'gpc_ajax_trash_post' );
function gpc_ajax_trash_post() {
	$post_id  = absint( isset( $_GET['post_id'] ) ? $_GET['post_id'] : 0 );
	$nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
	$redirect = isset( $_GET['redirect'] )
		? esc_url_raw( wp_unslash( urldecode( $_GET['redirect'] ) ) )
		: home_url( '/my-posts/' );

	if ( ! $post_id || ! wp_verify_nonce( $nonce, 'cotlas_trash_post_' . $post_id ) ) {
		wp_die( esc_html__( 'Invalid or expired request.', 'cotlas-admin' ), 403 );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_die( esc_html__( 'Post not found.', 'cotlas-admin' ), 404 );
	}

	// Only the post author or a user with edit_others_posts can trash it.
	if ( (int) $post->post_author !== get_current_user_id()
		&& ! current_user_can( 'edit_others_posts' )
	) {
		wp_die( esc_html__( 'You do not have permission to delete this post.', 'cotlas-admin' ), 403 );
	}

	wp_trash_post( $post_id );

	wp_safe_redirect( $redirect );
	exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * Dynamic Tag — {{delete_post_link}}
 *
 * Returns a nonce-protected AJAX URL that moves the current post to trash
 * when visited. Use in the Link field of a GB Text block inside a Query Loop.
 *
 * Only outputs a URL when the logged-in user owns the post (or is admin).
 * Empty string for logged-out visitors.
 *
 * Options:
 *   redirect — slug of the page to redirect to after trashing (default: my-posts)
 * ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'init', 'gpc_register_delete_post_link_tag', 20 );
function gpc_register_delete_post_link_tag() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	new GenerateBlocks_Register_Dynamic_Tag(
		array(
			'tag'         => 'delete_post_link',
			'title'       => __( 'Delete Post Link', 'cotlas-admin' ),
			'description' => __( 'Returns a secure URL that moves the current post to trash when visited. Only shown to the post author. Use in the Link field of a Text block inside a Query Loop.', 'cotlas-admin' ),
			'type'        => 'post',
			'supports'    => array( 'source' ),
			'options'     => array(
				'redirect' => array(
					'type'    => 'text',
					'label'   => __( 'Redirect page slug after delete', 'cotlas-admin' ),
					'default' => 'my-posts',
				),
			),
			'return'      => 'gpc_delete_post_link_callback',
		)
	);
}

/**
 * Dynamic tag callback — builds the secure trash URL.
 *
 * @param array  $options  Parsed tag options.
 * @param array  $block    Block data.
 * @param object $instance Block instance.
 * @return string
 */
function gpc_delete_post_link_callback( $options, $block, $instance ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$post_id = 0;
	if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
		$post_id = (int) GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	// Only render for the post author or admins.
	if ( (int) $post->post_author !== get_current_user_id()
		&& ! current_user_can( 'edit_others_posts' )
	) {
		return '';
	}

	$redirect_slug = isset( $options['redirect'] ) && '' !== trim( $options['redirect'] )
		? sanitize_text_field( $options['redirect'] )
		: 'my-posts';

	$page_obj     = get_page_by_path( $redirect_slug );
	$redirect_url = $page_obj
		? get_permalink( $page_obj->ID )
		: home_url( '/' . $redirect_slug . '/' );

	return esc_url(
		add_query_arg(
			array(
				'action'   => 'cotlas_trash_post',
				'post_id'  => $post_id,
				'nonce'    => wp_create_nonce( 'cotlas_trash_post_' . $post_id ),
				'redirect' => rawurlencode( $redirect_url ),
			),
			admin_url( 'admin-ajax.php' )
		)
	);
}
