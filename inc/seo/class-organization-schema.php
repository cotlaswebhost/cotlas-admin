<?php
/**
 * Organization schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Organization_Schema {
	public static function build( $post_id = 0 ) {
		$type = Schema_Generator::get_organization_type();
		$data = array(
			'@type' => $type,
			'@id'   => home_url( '#organization' ),
			'name'  => Schema_Generator::get_company_name(),
			'url'   => home_url( '/' ),
		);

		$logo = Schema_Generator::get_company_logo_url();
		if ( $logo ) {
			$data['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		$email = Schema_Generator::get_company_email();
		if ( $email ) {
			$data['email'] = $email;
		}

		$phone = Schema_Generator::get_company_phone();
		if ( $phone ) {
			$data['telephone'] = $phone;
		}

		$address = Schema_Generator::get_company_address();
		if ( $address ) {
			$data['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressCountry'  => '',
			);
		}

		$same_as = Schema_Generator::get_social_sameas();
		if ( ! empty( $same_as ) ) {
			$data['sameAs'] = $same_as;
		}

		$description = Schema_Generator::get_company_tagline();
		if ( $description ) {
			$data['description'] = $description;
		}

		return $data;
	}
}
