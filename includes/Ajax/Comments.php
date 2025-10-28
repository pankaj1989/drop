<?php
/**
 * Dynamic content/Collection API
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;

/**
 * Comments API Class
 */
class Comments {

	/**
	 * Get collection return api response
	 *
	 * @return void wpjson response
	 */
	public static function get_comments() {
		$sorting_param = HelperFunctions::sanitize_text( isset( $_GET['sorting'] ) ? $_GET['sorting'] : null );
		$filter_param = HelperFunctions::sanitize_text( isset( $_GET['filters'] ) ? $_GET['filters'] : null );
		$post_parent = HelperFunctions::sanitize_text( $_GET['post_parent'] ?? 0 );
		$comment_parent = HelperFunctions::sanitize_text( $_GET['comment_parent'] ?? 0 );
		$comment_type = HelperFunctions::sanitize_text( $_GET['comment_type'] ?? 'comment' );
		$item_per_page = HelperFunctions::sanitize_text( $_GET['items'] ?? 3 );
		$current_page = HelperFunctions::sanitize_text( $_GET['current_page'] ?? 1 );
		$offset = HelperFunctions::sanitize_text( $_GET['offset'] ?? 0 );

		$sorting      = null;
		$filters       = null;

		if ( isset( $sorting_param ) ) {
			$sorting = json_decode( stripslashes( $sorting_param ), true );
		}

		if ( isset( $filter_param ) ) {
			$filters = json_decode( stripslashes( $filter_param ), true );
		}


		$comments = HelperFunctions::get_comments(
			array(
				'parent'    => $comment_parent,
				'post_id' => $post_parent,
				'type' => $comment_type,
				'offset'  => $offset,
				'sorting' => $sorting,
				'filters'  => $filters,
				'item_per_page' => $item_per_page,
				'current_page' => $current_page,
			)
		);


		wp_send_json( $comments );

		die();
	}
}
