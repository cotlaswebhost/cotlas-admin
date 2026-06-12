<?php
/**
 * Author schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class Author_Schema {
	public static function build( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$author_id = absint( $post->post_author );
		$user      = get_userdata( $author_id );
		if ( ! $user ) {
			return array();
		}

		$schema = array(
			'@type' => 'Person',
			'@id'   => home_url( '#author-' . $author_id ),
			'name'  => $user->display_name,
		);

		$avatar = get_avatar_url( $author_id, array( 'size' => 512 ) );
		if ( $avatar ) {
			$schema['image'] = $avatar;
		}

		return $schema;
	}
}
