<?php
/**
 * This class act like controller for Editor, Iframe, Frontend
 *
 * @package droip
 */

namespace Droip;

use Droip\API\ContentManager\ContentManagerHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend handler class
 */
class ContentManager {

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'parse_request', [$this, 'custom_parse_request'] );
    add_action( 'init', [$this, 'register_parent_post_type'] );
    add_filter('droip_collection_droip_cm_multi_reference', [$this, 'get_referenced_collection'], 10, 2);
    add_filter('droip_collection_droip_cm_gallery', [$this, 'get_gallery_collection'], 10, 2);
    add_filter('droip_dynamic_content', [$this, 'droip_dynamic_content'], 10, 2);
  }

  public function droip_dynamic_content($value, $args)
  {

    $dynamic_content = isset($args['dynamicContent']) ? $args['dynamicContent'] : [];

    if (isset($dynamic_content['type']) && $dynamic_content['type'] === 'gallery') {
      $content = array();
      $collection_type = array();

      if (isset($args['collectionItem'])) {
        $collection_type = isset($args['collectionItem']) ?  $args['collectionItem'] : array();
      } else {
        $collection_type = isset($args['post']) ? $args['post'] : array();
      }

      $src = isset($collection_type['url']) ? $collection_type['url'] : '';
      $attachment_id = isset($collection_type['id']) ? $collection_type['id'] : '';
      if(!$src && $attachment_id){
        $src = wp_get_attachment_image_url($attachment_id, 'full');
      } 

      $content = array(
        'wp_attachment_id' =>  $attachment_id,
        'src' => $src,
      );

      return $content;
    }

    if (isset($dynamic_content['type']) && $dynamic_content['type'] === 'reference') {

      global $wpdb;

      $collection_item_id = isset($args['collectionItem']['ID']) ? $args['collectionItem']['ID'] : '';

      // if collection item id is not set then get it from templateEditContext
      if (empty($collection_item_id)) {
        $collection_item_id = isset($args['templateEditContext']['id']) ? $args['templateEditContext']['id'] : '';
      }

      $ref_field_id = isset($dynamic_content['ref_field_id']) ? $dynamic_content['ref_field_id'] : '';
      $value = isset($dynamic_content['value']) ? $dynamic_content['value'] : '';
      $cm_post_id = isset($dynamic_content['cm_post_id']) ? $dynamic_content['cm_post_id'] : '';

      $meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($cm_post_id, $ref_field_id);

      $query = $wpdb->prepare("SELECT ref_post_id FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d AND field_meta_key=%s", $collection_item_id, $meta_key);

      $ref_field_id_value = $wpdb->get_results($query, ARRAY_A); // get one refector

      $cm_ref_field_post = null;

      if (count($ref_field_id_value) > 0) {
        $ref_field_id_value = $ref_field_id_value[0]['ref_post_id'];
        $cm_ref_field_post = get_post($ref_field_id_value);
      } else {
        return '';
      }


      $content = HelperFunctions::get_post_dynamic_content($value, $cm_ref_field_post);

      return $content;
    }

    return $value;
  }
  
  public function get_referenced_collection($value, $args)
  {
    global $wpdb;

    $name = $args['name'];
    $post = get_post($args['post_parent']);
    $post_parent_post = get_post($post->post_parent);

    // if parent post is not set then return empty array
    if(!$post_parent_post || !isset($post_parent_post->ID)){
      return [];
    }

    $cm_ref_field_meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post_parent_post->ID, $name);

    $query = $wpdb->prepare("SELECT ref_post_id FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d AND field_meta_key=%s", $args['post_parent'], $cm_ref_field_meta_key);

    $results = $wpdb->get_results($query);
  
    $IDs = [];
    if (count($results) > 0) {
      foreach ($results as $key => $result) {
        $IDs[] = $result->ref_post_id;
      }
    }

    $args['IDs'] = $IDs;
    $posts = HelperFunctions::get_posts( $args );
    return $posts;
  }

  public function get_gallery_collection($value, $args)
  {
    global $wpdb;
    $name = $args['name'];
    $post = get_post($args['post_parent']);
    $post_parent_post = get_post($post->post_parent);
    $item_per_page = isset($args['item_per_page']) ? $args['item_per_page'] : 3;
    $offset = isset($args['offset']) ? $args['offset'] : 0;
    
    $data_posts = [];

    if($post_parent_post && isset($post_parent_post->ID)) {
      $cm_gallery_field_meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post_parent_post->ID, $name);
      
      $value = get_post_meta($post->ID, $cm_gallery_field_meta_key, true);
      
      if(is_array($value)) {
        foreach (array_slice($value, $offset, $item_per_page) as $key => $item) {
          $data_posts[] = $item;
        }
      }
    }

    return ['data' => $data_posts, 'pagination' => [], 'itemType' => 'gallery', 'args' => $args];
  }

  public function register_parent_post_type($request) {
    $args = [
			'page' => HelperFunctions::sanitize_text(isset($request['page']) ? $request['page'] : 1),
		];
    $all_post_types = ContentManagerHelper::get_all_post_types($args);
    foreach ($all_post_types as $key => $post_type) {
      $args = array(
        'public' => true,
        'show_ui' => false,
        'label'  => $post_type['post_title'],
        // Add other arguments as needed
        'rewrite' => array( 'slug' => $post_type['post_name'] ), // Define the slug for parent posts
      );
      register_post_type( ContentManagerHelper::get_child_post_post_type_value($post_type['ID']), $args );
    }
  }

  public function custom_parse_request( $wp ) {
    global $wp_rewrite;

    // Get the site URL and path to account for subfolder installation
    $parsed_url_path = parse_url(site_url(), PHP_URL_PATH);
    $site_url_path = is_null($parsed_url_path) ? '' : trim($parsed_url_path, '/');

    // Get the requested URL path
    $requested_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

    // Remove the subfolder part from the requested path
    if ( $site_url_path && strpos( $requested_path, $site_url_path ) === 0 ) {
        $requested_path = substr( $requested_path, strlen( $site_url_path ) );
        $requested_path = trim( $requested_path, '/' );
    }

    // Get the parent and child slugs from the URL path
    $path_parts = explode( '/', $requested_path );
    $parent_slug = isset( $path_parts[0] ) ? $path_parts[0] : '';
    $child_slug = isset( $path_parts[1] ) ? $path_parts[1] : '';

    if(!$child_slug){
      $post_id = HelperFunctions::get_post_id_if_possible_from_url();
      if($post_id){
        $post = get_post($post_id);
        if($post){
          $child_slug = $post->post_name;
          $parent_post = get_post($post->post_parent);
          if($parent_post){
            $parent_slug = $parent_post->post_name;
          }
        }
      }
    }

    //TODO: Need to check ?p=post_id for draft post preview.
    // Check if the URL corresponds to a parent post
    $parent_post = get_page_by_path( $parent_slug, OBJECT, ContentManagerHelper::PARENT_POST_TYPE );
    if ( $parent_post ) {
        // This is a parent post
        $wp->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_parent_post'] = $parent_post->ID;

        // Check if the URL corresponds to a child post
        if ( ! empty( $child_slug ) ) {
            $args = array(
              'post_type'      => ContentManagerHelper::get_child_post_post_type_value($parent_post->ID), // Replace 'your_post_type' with your actual post type
              'posts_per_page' => 1, // Limit to one post
              'name'           => $child_slug, // Replace 'your_post_name' with the post_name value
              'post_parent'    => $parent_post->ID, // Replace 123 with the post parent ID
            );
            $posts = get_posts($args);
            if(count($posts) > 0){
                $child_post = $posts[0];
                $wp->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_child_post'] = $child_post->ID;
            }
        }

        // Load custom template for both parent and child posts
        // add_filter( 'template_include', [$this, 'custom_load_custom_template'] );
    }
  }

}