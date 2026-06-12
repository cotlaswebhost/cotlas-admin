<?php
/**
 * WebPage schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class WebPage_Schema {
	public static function build( $post_id ) {
		return array(
			'@type'            => 'WebPage',
			'@id'              => get_permalink( $post_id ) . '#webpage',
			'url'              => get_permalink( $post_id ),
			'name'             => Schema_Generator::get_title_for_post( $post_id ),
			'description'      => Schema_Generator::get_description_for_post( $post_id ),
			'publisher'        => array(
				'@id' => home_url( '#organization' ),
			),
			'mainEntityOfPage' => get_permalink( $post_id ),
		);
	}
}
