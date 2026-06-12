<?php
/**
 * Website schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Website_Schema {
	public static function build( $post_id = 0 ) {
		$data = array(
			'@type' => 'WebSite',
			'@id'   => home_url( '#website' ),
			'name'  => Schema_Generator::get_company_name(),
			'url'   => home_url( '/' ),
			'publisher' => array(
				'@id' => home_url( '#organization' ),
			),
		);

		if ( get_option( 'cotlas_seo_enable_search_action_schema', 1 ) ) {
			$data['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			);
		}

		return $data;
	}
}
