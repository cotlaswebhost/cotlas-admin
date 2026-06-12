<?php
/**
 * Local business schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class LocalBusiness_Schema {
	public static function build( $post_id = 0 ) {
		$type = Schema_Generator::get_organization_type();
		$logo = Schema_Generator::get_company_logo_url();

		$schema = array(
			'@type' => $type,
			'@id'   => home_url( '#local-business' ),
			'name'  => Schema_Generator::get_company_name(),
			'url'   => home_url( '/' ),
		);

		if ( $logo ) {
			$schema['image'] = $logo;
		}

		$address = Schema_Generator::get_company_address();
		if ( $address ) {
			$schema['address'] = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => $address,
			);
		}

		$email = Schema_Generator::get_company_email();
		if ( $email ) {
			$schema['email'] = $email;
		}

		$phone = Schema_Generator::get_company_phone();
		if ( $phone ) {
			$schema['telephone'] = $phone;
		}

		return $schema;
	}
}
