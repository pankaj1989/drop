<?php
/**
 * Taxonomy API
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;
use Exception;

/**
 * Taxonomy API Class
 */
class Taxonomy {
	public static function get_post_terms()
	{
		try {
			$id = HelperFunctions::sanitize_text( isset( $_GET['id'] ) ? $_GET['id'] : null );
	
			$taxonomy = HelperFunctions::sanitize_text( isset( $_GET['taxonomy'] ) ? $_GET['taxonomy'] : null );
	
			$taxonomies = get_the_terms( $id, $taxonomy );

			if ($taxonomies instanceof \WP_Error) {
				throw new Exception("Failed!");
			}
	
			if (is_array($taxonomies)) {
				wp_send_json_success(
					$taxonomies
				);
			} else {
				wp_send_json_success([]);
			}
		} catch(Exception $e) {
			wp_send_json_error(
				$e->getMessage(), $e->getCode() ?? 400
			);
		}

		die;
	}

	public static function get_terms()
	{
		try {
			$name = HelperFunctions::sanitize_text( isset( $_GET['name'] ) ? $_GET['name'] : null );

			$terms = get_terms( array(
				'taxonomy'   => $name,
				'hide_empty' => false,
			));

			if($terms instanceof \WP_Error) {
				throw new Exception("Error!");
			}

			wp_send_json_success($terms);
		} catch(Exception $e) {
			wp_send_json_error(
				$e->getMessage(), $e->getCode() ?? 400
			);
		}

		die;
	}

	public static function get_post_type_taxonomies(){
		try {
			$post_types = get_post_types([], 'objects');
      $response = [];

			foreach ($post_types as $post_type => $details) {
					$taxonomies = get_object_taxonomies($post_type, 'objects');
					if (!empty($taxonomies)) {
							// Format taxonomies as array with value and title
							$formatted_taxonomies = [];
							foreach ($taxonomies as $taxonomy) {
									$formatted_taxonomies[] = [
											'value' => $taxonomy->name,
											'title' => $taxonomy->label,
									];
							}

							if (!empty($formatted_taxonomies)) {
									$response[] = [
											'value' => $post_type,
											'title' => $details->label,
											'taxonomies' => $formatted_taxonomies,
									];
							}
					}
			}

	
			wp_send_json_success($response);
		}  catch(Exception $e) {
			wp_send_json_error(
				$e->getMessage(), $e->getCode() ?? 400
			);
		}
	}

	public static function get_post_type_from_term_id($term_id) {
    // Get the term object from the term ID
    $term = get_term($term_id);
    // Check if the term exists
    if (!$term || is_wp_error($term)) {
        return null;
    }
    // Get the taxonomy associated with the term
    $taxonomy = $term->taxonomy;
    // Get the post types associated with the taxonomy
    $post_types = get_post_types([]);
		foreach ($post_types as $key => $post_type) {
			// Get all taxonomies associated with the post type
			$taxonomies = get_object_taxonomies($post_type, 'objects');
			if (!empty($taxonomies)) {
					foreach ($taxonomies as $taxonomy_slug => $taxonomy_object) {
							if($taxonomy_slug === $taxonomy) {
									return $post_type;
							}
					}
			} 
		}
    return false;
}

	public static function get_term_by_id($term_id)
	{
		$term = get_term($term_id);
		$term = get_object_vars($term);

		$post_type = self::get_post_type_from_term_id($term_id);
		$term['post_type'] = $post_type;

		return $term;
	}

	public static function get_all_terms_by_post_type() {
		$post_type = HelperFunctions::sanitize_text( isset( $_GET['post_type'] ) ? $_GET['post_type'] : null );
	
		// Get taxonomy objects related to the post type
    $taxonomies = get_object_taxonomies($post_type, 'objects');
    $result = [];

    if(count($taxonomies) > 0) {
			// Loop through each taxonomy
			foreach ($taxonomies as $taxonomy_slug => $taxonomy_obj) {
					// Get all terms for this taxonomy
					$terms = get_terms([
							'taxonomy' => $taxonomy_slug,
							'hide_empty' => false,
					]);

					// Skip if error or empty
					if (is_wp_error($terms) || empty($terms)) {
							continue;
					}

					// Prepare term list with required fields
					$terms_array = [];
					foreach ($terms as $term) {
							$terms_array[] = [
									'term_id' => $term->term_id,
									'name' => $term->name,
									'slug' => $term->slug,
							];
					}

					// Add taxonomy with terms to result
					$result[] = [
							'value' => $taxonomy_slug,
							'title' => $taxonomy_obj->label,
							'terms' => $terms_array,
					];
			}
		}

		wp_send_json($result);
  }

}
