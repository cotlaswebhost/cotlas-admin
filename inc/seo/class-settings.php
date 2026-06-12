<?php
/**
 * SEO settings page and registration.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Settings {
	const OPTION_GROUP = 'cotlas_seo_settings';
	const PAGE_SLUG    = 'cotlas-seo-schema-settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_settings() {
		$bools = array(
			'cotlas_seo_enable_module',
			'cotlas_seo_enable_opengraph',
			'cotlas_seo_enable_schema',
			'cotlas_seo_enable_breadcrumb_schema',
			'cotlas_seo_enable_author_schema',
			'cotlas_seo_enable_faq_schema',
			'cotlas_seo_enable_local_business_schema',
			'cotlas_seo_enable_search_action_schema',
			'cotlas_seo_enable_twitter_cards',
			'cotlas_seo_enable_seo_plugin_integration',
			'cotlas_seo_enable_custom_post_type_detection',
			'cotlas_seo_enable_breadcrumb_shortcode',
			'cotlas_seo_use_seo_plugin_title',
			'cotlas_seo_use_seo_plugin_description',
		);

		foreach ( $bools as $key ) {
			register_setting(
				self::OPTION_GROUP,
				$key,
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_bool' ),
					'default'           => 0,
				)
			);
		}

		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_logo_id', array(
			'type'              => 'integer',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_absint' ),
			'default'           => 0,
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_default_image_id', array(
			'type'              => 'integer',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_absint' ),
			'default'           => 0,
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_localbusiness_type', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_text' ),
			'default'           => 'Organization',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_localbusiness_custom_type', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_text' ),
			'default'           => 'Organization',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_name', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_text' ),
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_website', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_url' ),
			'default'           => home_url( '/' ),
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_email', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => get_option( 'admin_email' ),
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_phone', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_text' ),
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_address', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_posttype_schema_map', array(
			'type'              => 'array',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Posttype_Mapping', 'sanitize_mapping' ),
			'default'           => array(),
		) );

		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_tagline', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'CotlasAdmin\\SEO\\Schema_Generator', 'sanitize_text' ),
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, 'cotlas_seo_company_sameas_note', array(
			'type'              => 'string',
			'sanitize_callback' => '__return_empty_string',
			'default'           => '',
		) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'cotlas-admin_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_register_script( 'cotlas-seo-settings', false, array( 'jquery' ), false, true );
		wp_enqueue_script( 'cotlas-seo-settings' );
		wp_add_inline_script( 'cotlas-seo-settings', $this->get_media_script() );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		ctap_page_open( 'SEO & Schema Settings', 'dashicons-chart-area', 'OpenGraph tags, JSON-LD schema, breadcrumb shortcodes, and SEO plugin compatibility controls.' );
		$tabs = array(
			array( 'id' => 'general',      'label' => 'General Settings',    'icon' => 'dashicons-admin-generic' ),
			array( 'id' => 'opengraph',    'label' => 'OpenGraph Settings',   'icon' => 'dashicons-share' ),
			array( 'id' => 'schema',       'label' => 'Schema Settings',      'icon' => 'dashicons-feedback' ),
			array( 'id' => 'images',       'label' => 'Default Images',       'icon' => 'dashicons-format-image' ),
			array( 'id' => 'organization', 'label' => 'Organization Info',    'icon' => 'dashicons-building' ),
			array( 'id' => 'social',       'label' => 'Social Profiles',      'icon' => 'dashicons-share' ),
			array( 'id' => 'mapping',      'label' => 'Post Type Mapping',    'icon' => 'dashicons-update' ),
			array( 'id' => 'breadcrumbs',  'label' => 'Breadcrumb Shortcode', 'icon' => 'dashicons-admin-links' ),
			array( 'id' => 'advanced',     'label' => 'Advanced Settings',    'icon' => 'dashicons-admin-tools' ),
		);
		$active = ctap_nav( $tabs );

		echo '<form method="post" action="options.php" class="ctap-form">';
		settings_fields( self::OPTION_GROUP );
		if ( function_exists( 'settings_errors' ) ) {
			settings_errors( self::OPTION_GROUP );
		}

		self::open_general_tab( $active );
		self::open_opengraph_tab( $active );
		self::open_schema_tab( $active );
		self::open_images_tab( $active );
		self::open_organization_tab( $active );
		self::open_social_tab( $active );
		self::open_mapping_tab( $active );
		self::open_breadcrumbs_tab( $active );
		self::open_advanced_tab( $active );

		echo '<div style="padding:0 8px 20px;">';
		submit_button( __( 'Save Changes', 'cotlas-admin' ), 'primary large' );
		echo '</div>';
		echo '</form>';
		ctap_page_close();
	}

	private static function open_general_tab( $active ) {
		ctap_pane_open( 'general', $active );
		ctap_card_open( 'Module Overview', 'dashicons-admin-generic' );
		self::toggle( 'cotlas_seo_enable_module', 'Enable SEO Module', 'Master switch for all SEO module output. When disabled, OpenGraph tags, schema output, and breadcrumbs are fully disabled.', 0 );
		ctap_info( 'Enable the SEO module first, then turn on only the features you need in the tabs below. New installs now default all SEO toggles to disabled.' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_opengraph_tab( $active ) {
		ctap_pane_open( 'opengraph', $active );
		ctap_card_open( 'OpenGraph Meta Tags', 'dashicons-share' );
		self::toggle( 'cotlas_seo_enable_opengraph', 'Enable OpenGraph Tags', 'Outputs og:* and twitter:* tags in wp_head.', 0 );
		self::toggle( 'cotlas_seo_use_seo_plugin_title', 'Use SEO plugin title if available', 'Uses Yoast, Rank Math, SEOPress, or AIOSEO title fields before falling back to the WordPress title.', 0 );
		self::toggle( 'cotlas_seo_use_seo_plugin_description', 'Use SEO plugin description if available', 'Uses SEO plugin meta descriptions before falling back to the excerpt or trimmed content.', 0 );
		self::toggle( 'cotlas_seo_enable_twitter_cards', 'Enable Twitter Cards', 'Adds twitter:card metadata alongside OpenGraph tags.', 0 );
		ctap_card_close();
		ctap_card_open( 'OpenGraph Priority', 'dashicons-editor-help' );
		ctap_info( '<ul style="margin:.5em 0 0 1.4em"><li><strong>Title</strong>: SEO plugin title if enabled, otherwise the current post or page title.</li><li><strong>Description</strong>: Yoast description, then Rank Math description, then excerpt, then trimmed content.</li><li><strong>Image</strong>: Featured image, then company logo, then default image.</li><li><strong>Type</strong>: article for posts and custom post types, website for pages, archives, and the homepage.</li></ul>' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_schema_tab( $active ) {
		ctap_pane_open( 'schema', $active );
		ctap_card_open( 'Schema Markup', 'dashicons-feedback' );
		self::toggle( 'cotlas_seo_enable_schema', 'Enable Schema Markup', 'Outputs JSON-LD in wp_head.', 0 );
		self::toggle( 'cotlas_seo_enable_breadcrumb_schema', 'Enable Breadcrumb Schema', 'Automatically outputs BreadcrumbList schema where applicable.', 0 );
		self::toggle( 'cotlas_seo_enable_author_schema', 'Enable Author Schema', 'Outputs Person schema for post authors.', 0 );
		self::toggle( 'cotlas_seo_enable_faq_schema', 'Enable FAQ Schema', 'Detects FAQ-style blocks or Elementor accordion widgets.', 0 );
		self::toggle( 'cotlas_seo_enable_search_action_schema', 'Enable SearchAction Schema', 'Includes SearchAction on the WebSite graph.', 0 );
		self::toggle( 'cotlas_seo_enable_local_business_schema', 'Enable Local Business Schema', 'Adds a local business node using your organization data and chosen schema type.', 0 );
		ctap_card_close();
		ctap_card_open( 'Supported Schema Types', 'dashicons-list-view' );
		ctap_info( 'Article, NewsArticle, BlogPosting, FAQPage, Event, Review, Recipe, Product, VideoObject, Service, LocalBusiness, Organization, Corporation, Person, JobPosting, Course, MedicalOrganization, Attorney, RealEstateAgent, Store, SoftwareApplication, WebPage, and custom schema types.' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_images_tab( $active ) {
		ctap_pane_open( 'images', $active );
		ctap_card_open( 'Default Images', 'dashicons-format-image' );
		self::image_field( 'cotlas_seo_company_logo_id', 'Company Logo', 'Used for Organization, LocalBusiness, and OpenGraph fallbacks.' );
		self::image_field( 'cotlas_seo_default_image_id', 'Default Image', 'Used when a post has no featured image and no company logo is available.' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_organization_tab( $active ) {
		ctap_pane_open( 'organization', $active );
		ctap_card_open( 'Organization Information', 'dashicons-building' );
		self::text_field( 'cotlas_seo_company_name', 'Company Name', get_option( 'cotlas_seo_company_name', Schema_Generator::get_company_name() ) );
		self::text_field( 'cotlas_seo_company_website', 'Website URL', get_option( 'cotlas_seo_company_website', home_url( '/' ) ), 'url' );
		self::text_field( 'cotlas_seo_company_email', 'Email', get_option( 'cotlas_seo_company_email', get_option( 'admin_email' ) ), 'email' );
		self::text_field( 'cotlas_seo_company_phone', 'Phone', get_option( 'cotlas_seo_company_phone', '' ) );
		self::textarea_field( 'cotlas_seo_company_address', 'Address', get_option( 'cotlas_seo_company_address', '' ) );
		self::select_field( 'cotlas_seo_localbusiness_type', 'Local Business Schema Type', get_option( 'cotlas_seo_localbusiness_type', 'Organization' ), array( 'LocalBusiness', 'Corporation', 'Organization', 'NewsMediaOrganization', 'ProfessionalService', 'Store', 'MedicalOrganization', 'EducationalOrganization', 'Attorney', 'RealEstateAgent', 'custom' ) );
		self::text_field( 'cotlas_seo_localbusiness_custom_type', 'Custom Schema Type', get_option( 'cotlas_seo_localbusiness_custom_type', 'Organization' ) );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_social_tab( $active ) {
		ctap_pane_open( 'social', $active );
		ctap_card_open( 'Social Profiles', 'dashicons-share' );
		self::text_field( 'cotlas_social_facebook', 'Facebook URL', get_option( 'cotlas_social_facebook', '' ), 'url' );
		self::text_field( 'cotlas_social_twitter', 'Twitter / X URL', get_option( 'cotlas_social_twitter', '' ), 'url' );
		self::text_field( 'cotlas_social_youtube', 'YouTube URL', get_option( 'cotlas_social_youtube', '' ), 'url' );
		self::text_field( 'cotlas_social_instagram', 'Instagram URL', get_option( 'cotlas_social_instagram', '' ), 'url' );
		self::text_field( 'cotlas_social_linkedin', 'LinkedIn URL', get_option( 'cotlas_social_linkedin', '' ), 'url' );
		self::text_field( 'cotlas_social_threads', 'Threads URL', get_option( 'cotlas_social_threads', '' ), 'url' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_mapping_tab( $active ) {
		ctap_pane_open( 'mapping', $active );
		ctap_card_open( 'Post Type Schema Mapping', 'dashicons-update' );
		ctap_info( 'Automatically detected public post types are listed below. Enable schema for any type and choose the schema class that should be emitted for that post type.' );
		$map       = Posttype_Mapping::get_mapping();
		$posttypes  = Posttype_Mapping::get_public_post_types();
		$schema_types = Posttype_Mapping::allowed_schema_types();

		echo '<div class="ctap-ref-wrap"><table class="ctap-ref-table"><thead><tr><th>Post Type</th><th>Enable Schema</th><th>Schema Type</th></tr></thead><tbody>';
		foreach ( $posttypes as $slug => $object ) {
			$item    = isset( $map[ $slug ] ) && is_array( $map[ $slug ] ) ? $map[ $slug ] : array();
			$enabled = ! empty( $item['enabled'] );
			$type    = ! empty( $item['type'] ) ? $item['type'] : ( 'page' === $slug ? 'WebPage' : 'Article' );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $slug ) . '</strong><br><span style="color:#6b7280;font-size:12px;">' . esc_html( $object->label ) . '</span></td>';
				echo '<td><label class="ctap-switch"><input type="checkbox" name="' . esc_attr( 'cotlas_seo_posttype_schema_map[' . $slug . '][enabled]' ) . '" value="1" ' . checked( $enabled, true, false ) . '><span class="ctap-slider"></span></label></td>';
				echo '<td><select name="' . esc_attr( 'cotlas_seo_posttype_schema_map[' . $slug . '][type]' ) . '">';
			foreach ( $schema_types as $schema_type ) {
				echo '<option value="' . esc_attr( $schema_type ) . '" ' . selected( $type, $schema_type, false ) . '>' . esc_html( $schema_type ) . '</option>';
			}
			echo '</select></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_breadcrumbs_tab( $active ) {
		ctap_pane_open( 'breadcrumbs', $active );
		ctap_card_open( 'Breadcrumb Shortcode', 'dashicons-admin-links' );
		self::toggle( 'cotlas_seo_enable_breadcrumb_shortcode', 'Enable Breadcrumb Shortcode', 'Registers [cotlas_breadcrumbs] and the GenerateBlocks dynamic tag when available.', 0 );
		ctap_info( 'Breadcrumb output starts with Home, then includes taxonomy or category hierarchy, and ends with the current title without a link. Use <code>[cotlas_breadcrumbs]</code> anywhere on the site.' );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function open_advanced_tab( $active ) {
		ctap_pane_open( 'advanced', $active );
		ctap_card_open( 'Advanced Settings', 'dashicons-admin-tools' );
		self::toggle( 'cotlas_seo_enable_seo_plugin_integration', 'Enable SEO Plugin Integration', 'When enabled, Cotlas reuses metadata from Yoast, Rank Math, SEOPress, or AIOSEO and avoids duplicate output.', 0 );
		self::toggle( 'cotlas_seo_enable_custom_post_type_detection', 'Enable Automatic Custom Post Type Detection', 'Automatically includes public custom post types in the schema mapping table.', 0 );
		ctap_card_close();
		ctap_pane_close();
	}

	private static function toggle( $name, $label, $desc = '', $default = 0 ) {
		$checked = get_option( $name, $default ) ? 'checked' : '';
		echo '<div class="ctap-toggle-row">';
		echo '<div class="ctap-toggle-info"><strong>' . esc_html( $label ) . '</strong>';
		if ( $desc ) {
			echo '<span>' . wp_kses_post( $desc ) . '</span>';
		}
		echo '</div>';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<label class="ctap-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . $checked . '><span class="ctap-slider"></span></label>';
		echo '</div>';
	}

	private static function text_field( $name, $label, $value, $type = 'text' ) {
		ctap_field( $label, '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text">' );
	}

	private static function textarea_field( $name, $label, $value ) {
		ctap_field( $label, '<textarea name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>' );
	}

	private static function select_field( $name, $label, $current, array $options ) {
		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="ctap-field-input"><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $current, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select></div></div>';
	}

	private static function image_field( $name, $label, $desc ) {
		$attachment_id = absint( get_option( $name, 0 ) );
		$url           = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		echo '<div class="ctap-field-row">';
		echo '<div class="ctap-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="ctap-field-input">';
		echo '<input type="number" min="0" name="' . esc_attr( $name ) . '" value="' . esc_attr( $attachment_id ) . '" data-cotlas-seo-image-input style="max-width:110px;margin-right:8px;">';
		echo '<button type="button" class="button" data-cotlas-seo-pick-image="' . esc_attr( $name ) . '">Select Image</button>';
		echo '<div class="cotlas-seo-image-preview" style="margin-top:10px;">';
		if ( $url ) {
			echo '<img src="' . esc_url( $url ) . '" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:8px;padding:4px;background:#fff;">';
		}
		echo '</div>';
		echo '<p class="ctap-field-desc">' . wp_kses_post( $desc ) . '</p>';
		echo '</div></div>';
	}

	private function get_media_script() {
		return <<<'JS'
(function($){
	$(document).on('click','[data-cotlas-seo-pick-image]',function(e){
		e.preventDefault();
		var targetName = $(this).data('cotlas-seo-pick-image');
		var field = $('input[name="' + targetName + '"]');
		var preview = field.closest('.ctap-field-input').find('.cotlas-seo-image-preview');
		var frame = wp.media({ title: 'Select image', button: { text: 'Use image' }, multiple: false });
		frame.on('select', function(){
			var attachment = frame.state().get('selection').first().toJSON();
			field.val(attachment.id).trigger('change');
			if ( attachment.sizes && attachment.sizes.medium ) {
				preview.html('<img src="' + attachment.sizes.medium.url + '" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:8px;padding:4px;background:#fff;">');
			} else if ( attachment.url ) {
				preview.html('<img src="' + attachment.url + '" alt="" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:8px;padding:4px;background:#fff;">');
			}
		});
		frame.open();
	});
})(jQuery);
JS;
	}
}
