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
 * Collection API Class
 */
class Collection {

	/**
	 * Get collection return api response
	 *
	 * @return void wpjson response
	 */
	public static function get_collection() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$collectionType = HelperFunctions::sanitize_text( isset( $_GET['collectionType'] ) ? $_GET['collectionType'] : null );
		$name = HelperFunctions::sanitize_text( isset( $_GET['name'] ) ? $_GET['name'] : null );
		$taxonomy = HelperFunctions::sanitize_text( isset( $_GET['taxonomy'] ) ? $_GET['taxonomy'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$sorting_param = HelperFunctions::sanitize_text( isset( $_GET['sorting'] ) ? $_GET['sorting'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$filter_param = HelperFunctions::sanitize_text( isset( $_GET['filters'] ) ? $_GET['filters'] : null );
		$inherit = HelperFunctions::sanitize_text( $_GET['inherit'] ?? false );
		$related = HelperFunctions::sanitize_text( $_GET['related'] ?? false );
		$post_parent = HelperFunctions::sanitize_text( $_GET['post_parent'] ?? null );
		$related_post_parent = HelperFunctions::sanitize_text( $_GET['related_post_parent'] ?? false );
		$item_per_page = HelperFunctions::sanitize_text( $_GET['items'] ?? 3 );
		$current_page = HelperFunctions::sanitize_text( $_GET['current_page'] ?? 1 );
		$offset = HelperFunctions::sanitize_text( $_GET['offset'] ?? 0 );
		$context_param = HelperFunctions::sanitize_text( isset($_GET['context']) ? $_GET['context'] : null );
		$query = HelperFunctions::sanitize_text( isset( $_GET['q'] ) ? $_GET['q'] : '' );

		$sorting      = null;
		$filters       = null;
		$context = null;

		// handle bool
		$inherit = $inherit === 'true' ? true : false;
		$related = $related === 'true' ? true : false;

		if ( isset( $sorting_param ) ) {
			$sorting = json_decode( stripslashes( $sorting_param ), true );
		}

		if ( isset( $filter_param ) ) {
			$filters = json_decode( stripslashes( $filter_param ), true );
		}

		if ( isset( $context_param ) ) {
			$context = json_decode( stripslashes( $context_param ), true );
		}

		$args = array(
			'name'    => $name,
			'sorting' => $sorting,
			'filters'  => $filters,
			'inherit'	=> $inherit,
			'post_parent'	=> $post_parent,
			'post_status' => 'any',
			'related'	=> $related,
			'related_post_parent' => $related_post_parent,
			'item_per_page' => $item_per_page,
			'current_page' => $current_page,
			'offset' => $offset,
			'context' => $context,
			'q' => $query,
		);
		
		if($collectionType === 'posts'){
			$posts = HelperFunctions::get_posts( $args );
			wp_send_json( $posts );
		}else if($collectionType === 'users'){
			$users = Users::get_users($args);
			wp_send_json($users);
		}else if($collectionType === 'terms'){
			// $taxonomies = get_the_terms( $post_parent, $taxonomy );
			$args = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'inherit'	=> $inherit,
				'post_parent'	=> $post_parent,
				'item_per_page' => $item_per_page,
				'current_page' => $current_page,
				'offset' => $offset,
			);
			$terms = HelperFunctions::get_terms($args);
			wp_send_json($terms);
		}else{
			$collection = apply_filters( 'droip_collection_'.$collectionType, false, $args );
			wp_send_json($collection);
		}
		
	}

	public static function get_external_collection_options(){
		$collectionType = HelperFunctions::sanitize_text( isset( $_GET['collectionType'] ) ? $_GET['collectionType'] : null );
		$type = HelperFunctions::sanitize_text( isset( $_GET['type'] ) ? $_GET['type'] : null );

		$args = array(
			'type' => $type,
			'collectionType'	=> $collectionType
		);

		$collection = array();
		$collection = apply_filters( 'droip_external_collection_options', $collection, $args );

		wp_send_json($collection);
	} 

	public static function get_external_collection_item_type() {
		$type = HelperFunctions::sanitize_text( isset( $_GET['type'] ) ? $_GET['type'] : null );
		$itemType = 'post';
		$itemType = apply_filters( 'droip_external_collection_item_type', $itemType, $type );
		wp_send_json($itemType);
	}
}