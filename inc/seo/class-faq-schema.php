<?php
/**
 * FAQ schema builder.
 *
 * @package CotlasAdmin
 */

namespace CotlasAdmin\SEO;

defined( 'ABSPATH' ) || exit;

class FAQ_Schema {
	public static function build( $post_id ) {
		$content = get_post_field( 'post_content', $post_id );
		if ( ! $content ) {
			return array();
		}

		$items = self::extract_faq_items( $content );
		if ( empty( $items ) ) {
			return array();
		}

		$main = array();
		foreach ( $items as $item ) {
			$main[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post_id ) . '#faq',
			'mainEntity' => $main,
		);
	}

	private static function extract_faq_items( $content ) {
		$items = array();

		if ( preg_match_all( '/<details[^>]*>.*?<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$items[] = array(
					'question' => trim( wp_strip_all_tags( $match[1] ) ),
					'answer'   => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}

		if ( preg_match_all( '/schema-faq-section.*?schema-faq-question[^>]*>(.*?)<.*?schema-faq-answer[^>]*>(.*?)<\/.*?>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$items[] = array(
					'question' => trim( wp_strip_all_tags( $match[1] ) ),
					'answer'   => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}

		if ( preg_match_all( '/elementor-widget-accordion.*?elementor-tab-title[^>]*>(.*?)<.*?elementor-tab-content[^>]*>(.*?)<\/.*?>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$items[] = array(
					'question' => trim( wp_strip_all_tags( $match[1] ) ),
					'answer'   => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}

		$items = array_filter( $items, static function( $item ) {
			return ! empty( $item['question'] ) && ! empty( $item['answer'] );
		} );

		return array_values( $items );
	}
}
