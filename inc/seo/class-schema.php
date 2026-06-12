<?php
/**
 * JSON-LD schema output.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Schema {
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 20 );
	}

	public function output_schema() {
		Schema_Generator::output_json_ld();
	}
}
