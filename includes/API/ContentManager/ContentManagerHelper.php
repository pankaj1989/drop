<?php
/**
 * Page or post data manager
 *
 * @package droip
 */

namespace Droip\API\ContentManager;

use Droip\HelperFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Content Manager data handler
 */
class ContentManagerHelper {

  const PARENT_POST_TYPE = DROIP_CONTENT_MANAGER_PREFIX;
  const POST_META_PREFIX = DROIP_CONTENT_MANAGER_PREFIX;
  const RESERVED_SLUG_WORDS = array('attachment', 'author', 'category', 'comment', 'feed', 'page', 'post', 'tag', 'profile', 'index');

  public static function get_all_post_types($args){
    $posts_per_page = isset($args['posts_per_page']) ? $args['posts_per_page'] : 20;
    $page = $args['page'];
    $offset = $posts_per_page * ($page - 1);
    $posts = get_posts(
			array(
				'post_type'   => self::PARENT_POST_TYPE,
				'post_status' => array('draft', 'publish', 'future'),
				'numberposts' => -1,
        'post_parent' => 0,
        'orderby'     => 'ID',
        'order'       =>'DESC',
        'posts_per_page' => $posts_per_page,
        'offset'      => $offset, // Use 'offset' for pagination
			)
		);

    foreach ($posts as $key => $post) {
      $posts[$key] = self::format_single_post($post);
    }

    return $posts;
  }

  public static function get_post_type($id, $hierarchy = false)
  {
    return self::format_single_post(get_post($id), $hierarchy);
  }

  // get post's settings by post id
  public static function get_post_type_settings($id)
  {
    $post = get_post($id);

    if ($post) {
      return self::format_single_post($post);
    }

    return null;
  }

  public static function get_referenced_collection($post_id, $field_id)
  {
    global $wpdb;

    $name = $field_id;
    $cm_ref_field_meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post_id, $name);

    $query = $wpdb->prepare("SELECT ref_post_id FROM {$wpdb->prefix}droip_cm_reference WHERE field_meta_key=%s", $cm_ref_field_meta_key);
    $results = $wpdb->get_results($query, ARRAY_A);

    $ref_parent_post = null;
    if (count($results) > 0) {
      $ref_parent_post = get_post($results[0]['ref_post_id']);
      $ref_parent_post = self::format_single_post(get_post($ref_parent_post->post_parent));
    }

    return [
      'data' => $ref_parent_post,
      'args' => ['post_parent' => $post_id, 'name' => $field_id]
    ];
  }

  public static function validate_slug($post_id, $post_type, $post_name){
    if (in_array($post_name, self::RESERVED_SLUG_WORDS)) {
      return false;
    }
    return HelperFunctions::validate_slug($post_id, $post_type, $post_name);
  }
  
  /**
   * Create or update parent post
   * 
   * @param object $args parent post ID and post_title
   * @param object $othersArgs parent post slug and other configuration
   */
  public static function create_or_update_a_post_type($args, $othersArgs){
    $wp_post = [
      'post_type'=> self::PARENT_POST_TYPE,
      'post_title'=>$args['post_title'],
      'post_name'=> $args['post_name'],
      'post_status'=> 'publish',
    ];
    if(isset($args['ID']) && !empty($args['ID'])){
      $wp_post['ID'] = $args['ID'];
      $post_id = $args['ID'];
      wp_update_post($wp_post);
    }else{
      $post_id = wp_insert_post( $wp_post );
    }

    self::add_or_update_post_type_fields($post_id, $othersArgs['fields'], $othersArgs['basic_fields']);

    if ( $post_id ) {
      return self::format_single_post(get_post($post_id));
    }
    wp_send_json_error( 'Creation failed!', 422 );
  }

  /**
   * Update post type fields.
   * first check prev meta then validate every filed and update child post field data accordingly
   * remove if any unused filed has in the child post.
   * 
   * @param object $args parent post ID and post_title
   * @param object $othersArgs parent post slug and other configuration
   */
  private static function add_or_update_post_type_fields($post_id, $fields, $basic_fields){
    $prev_fields = self::get_parent_post_fields($post_id);

    //check new fields and prv_fields value;
    $changed = self::find_changed_ids($prev_fields, $fields);

    if(count($changed['deleted_ids']) > 0){
      global $wpdb;
      foreach ($changed['deleted_ids'] as $key => $field_id) {
        $meta_key = self::get_child_post_meta_key_using_field_id($post_id, $field_id);
        $query = $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key=%s", $meta_key );
        $wpdb->query($query);

        // delete the all the fields from wp_droip_cm_reference table where field_meta_key = $meta_key
        $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}droip_cm_reference WHERE field_meta_key=%s", $meta_key);
        $wpdb->query($query);
      }
    }

    //TODO: new_ids //set default value. 

    foreach ($fields as &$field) {
      if ($field['type'] === 'reference' || $field['type'] === 'multi-reference') {
          $field['editRefCollection'] ??= false;
      }
    }
    
    update_post_meta( $post_id, self::POST_META_PREFIX . '_fields', $fields );
    // if(!empty($fields)){
    //   update_post_meta( $post_id, self::POST_META_PREFIX . '_fields', $fields );
    // }
    if(!empty($basic_fields)){
      update_post_meta( $post_id, self::POST_META_PREFIX . '_basic_fields', $basic_fields );
    }
  }

  public static function find_changed_ids($prev_fields, $new_fields) {
    $prev_ids = array_column($prev_fields, 'id');
    $new_ids = array_column($new_fields, 'id');
    
    // Find new IDs in $new_fields
    $new_ids_only = array_diff($new_ids, $prev_ids);
    
    // Find deleted IDs in $prev_fields
    $deleted_ids_only = array_diff($prev_ids, $new_ids);

    return [
        'new_ids' => $new_ids_only,
        'deleted_ids' => $deleted_ids_only
    ];
  }
  
  public static function get_child_post_post_type_value($post_parent){
    return self::PARENT_POST_TYPE .'_'.$post_parent;
  }

  /**
   * Create or update child post
   * 
   * @param object $args parent post ID and post_title
   * @param object $othersArgs parent post slug and other configuration
   */
  public static function create_or_update_a_post_type_item($args, $othersArgs)
  {
    $wp_post = [
      'post_parent' => $args['post_parent'],
      'post_type' => self::get_child_post_post_type_value($args['post_parent']),
      'post_title' => $args['post_title'],
      'post_name' => $args['post_name'],
      'post_status' => $args['post_status'],
    ];
    if (isset($args['post_date']) && !empty($args['post_date'])) {
      $wp_post['post_date']     = $args['post_date'];
      $wp_post['post_date_gmt'] = get_gmt_from_date($args['post_date']);
    }

    if (isset($args['ID']) && !empty($args['ID'])) {
      $wp_post['ID'] = $args['ID'];
      $post_id = $args['ID'];
      wp_update_post($wp_post);
    } else {
      $post_id = wp_insert_post($wp_post);
    }


    if (!empty($othersArgs['fields'])) {
      $parent_fields = self::get_parent_post_fields($args['post_parent']);
      $fields = $othersArgs['fields'];

      //TODO: need to validate fields data with parent fields
      foreach ($parent_fields as $key => $parent_field) {
        if (isset($fields[$parent_field['id']])) {

          $meta_key = self::get_child_post_meta_key_using_field_id($args['post_parent'], $parent_field['id']);

          // Check if the $parent_field type is 'reference'/ 'multi-reference' then save the field value in the wp_droip_cm_reference table
          if ($parent_field['type'] === 'reference' || $parent_field['type'] === 'multi-reference') {
            global $wpdb;
            $ref_post_id = $fields[$parent_field['id']];

            // Check if the meta key exists
            $is_meta_key_exist = metadata_exists('post', $post_id, $meta_key);

            // Get the meta value
            $value = get_post_meta($post_id, $meta_key, true);

            // if meta key is not exist then set default value of the field
            if (!$is_meta_key_exist) {
              $value = $parent_field['default_value'];
            }

            // Format the field value
            $fields[$parent_field['id']] = $value;

            // Delete the all the fields from wp_droip_cm_reference table where post_id = $post_id and field_meta_key = $meta_key
            $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d AND field_meta_key=%s", $post_id, $meta_key);
            $wpdb->query($query);

            // Check if the ref_post_id is not empty
            if (!empty($ref_post_id)) {

              // before insert the ref_post_id into wp_droip_cm_reference table, check if the ref_post_id is exist in wp_droip_cm_reference table or not with the same post_id and field_meta_key
              foreach ($ref_post_id as $key => $single_ref_post_id) {
                $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d AND field_meta_key=%s AND ref_post_id=%d", $post_id, $meta_key, $single_ref_post_id['value']);
                $result = $wpdb->get_results($query);

                // If the ref_post_id is not exist in wp_droip_cm_reference table then insert the ref_post_id into wp_droip_cm_reference table
                if (empty($result)) {
                  $wpdb->insert(
                    $wpdb->prefix . 'droip_cm_reference',
                    array(
                      'post_id' => $post_id,
                      'field_meta_key' => $meta_key,
                      'ref_post_id' => $single_ref_post_id['value'],
                    ),
                    array('%d', '%s', '%d')
                  );
                }
              }
            }
          } else {
            update_post_meta($post_id, $meta_key, $fields[$parent_field['id']]);
          }
        }
      }
    }

    if ($post_id) {
      return self::format_single_child_post(get_post($post_id));
    }

    wp_send_json_error('Creation failed!', 422);
  }

  public static function get_child_post_meta_key_using_field_id($post_parent, $field_id){
    return self::POST_META_PREFIX . "_field_" . $post_parent . "_" . $field_id;
  }

  private static function format_single_post($post, $hierarchy = false)
  {
    $post = (array) $post;
    $post['fields'] = self::get_parent_post_fields($post['ID'], $hierarchy);
    $post['item_count'] = self::get_child_post_count($post['ID']);
    $post['basic_fields'] = self::get_post_type_basic_fields($post['ID']);
    return $post;
  }

  private static function get_post_type_basic_fields($post_id){
    $basic_fields = get_post_meta($post_id, self::POST_META_PREFIX . '_basic_fields', true);
    return $basic_fields?$basic_fields:array('post_title'=> array('title'=>'Name', 'help_text'=>''), 'post_name'=>array('title'=>'Slug', 'help_text'=>''));
  }

  private static function get_parent_post_fields($post_id, $hierarchy = false)
  {
    $_fields = get_post_meta($post_id, self::POST_META_PREFIX . '_fields', true);

    if ($hierarchy === true) {
      foreach ($_fields as $key => $field) {
        if ($field['type'] === 'reference') {
          $ref_field_post = self::format_single_post(get_post($field['ref_collection']), $hierarchy);
          $field['fields'][] = $ref_field_post;
        }

        $_fields[$key] = $field;
      }
    }

    return $_fields ? $_fields : [];
  }

  public static function format_single_child_post($post)
  {
    $post = (array) $post;
    $post_id = $post['ID'];
    $post_parent = $post['post_parent'];

    $parent_fields = self::get_parent_post_fields($post_parent);
    $fields = [];
    foreach ($parent_fields as $key => $parent_field) {
      $meta_key = self::get_child_post_meta_key_using_field_id($post_parent, $parent_field['id']);

      // Check if the meta key exists
      $is_meta_key_exist = metadata_exists('post', $post_id, $meta_key);

      // Get the meta value
      $value = get_post_meta($post_id, $meta_key, true);

      // if meta key is not exist then set default value of the field
      if (!$is_meta_key_exist) {
        $value = isset($parent_field['default_value']) ? $parent_field['default_value'] : '';
      }

      // Format the field value
      // check if the field type is 'reference' or 'multi-reference' then get the ref_post_id from wp_droip_cm_reference table
      if ($parent_field['type'] === 'reference' || $parent_field['type'] === 'multi-reference') {
        global $wpdb;
        $query = $wpdb->prepare("SELECT ref_post_id FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d AND field_meta_key=%s", $post_id, $meta_key);
        $ref_post_ids = $wpdb->get_results($query);

        $ref_post_id = [];
        if (!empty($ref_post_ids)) {
          // check is ref_post_id is an array
          if (is_array($ref_post_ids)) {
            foreach ($ref_post_ids as $key => $ref_post) {
              $ref_post_id[] = array('value' => $ref_post->ref_post_id, 'title' => get_the_title($ref_post->ref_post_id));
            }
          } else {
            $ref_post_id[] = array('value' => $ref_post_ids->ref_post_id, 'title' => get_the_title($ref_post_ids->ref_post_id));
          }
        }

        $value = $ref_post_id;
      }

      $fields[$parent_field['id']] = $value;


    }

    $post['fields'] = $fields;

    $post_perma_link = get_permalink( $post_id );
		$protocol = strpos(home_url(), 'https://') !== false ? 'https' : 'http';
		if ( $protocol === 'https' ) {
			$post_perma_link = str_replace( 'http://', 'https://', $post_perma_link );
		} else {
			$post_perma_link = str_replace( 'https://', 'http://', $post_perma_link );
		}

    $guid = add_query_arg(
      array(
        'post_id' => $post_id,
      ),
      $post_perma_link
    );
    $post['guid'] = $guid;
    return $post;
  }
  
  public static function get_all_child_items($args){
    $posts_per_page = isset($args['posts_per_page']) ? $args['posts_per_page'] : 20;
    $exclude_post_ids = isset($args['exclude_post_ids']) ? $args['exclude_post_ids'] : [];
    $post_parent = $args['post_parent'];
    $page = $args['page'];
    $offset = $posts_per_page * ($page - 1);
    $query = isset($args['query'])?$args['query']:'';
    $filter = isset($args['filter'])?$args['filter']:'';
    $args = array(
      'post_type'      => self::get_child_post_post_type_value($post_parent),
      'post_status'    => array('draft', 'publish', 'future'),
      'post_parent'    => $post_parent,
      'orderby'        => 'ID',
      'order'          => 'DESC',
      'posts_per_page' => $posts_per_page,
      'offset'         => $offset, // Use 'offset' for pagination
      's'              => $query, // Add query parameter
      'post__not_in'   => $exclude_post_ids, // Exclude the post IDs
    );

    
    //TODO: Need to check if we can use helper getpost method
    $args = self::generate_args_with_filter_data($args, $filter);


    $posts = get_posts($args);

    foreach ($posts as $key => $post) {
      $posts[$key] = self::format_single_child_post($post);
    }
    return $posts;
  }

  public static function get_post_type_item($post_id)
  {
    $post = get_post($post_id);

    if ($post) {
      return self::format_single_child_post($post);
    }

    return null;
  }

  private static function generate_args_with_filter_data($args, $filter){
    if($filter){
      foreach ($filter as $key => $singleFilter) {
        if($key === 'post_status'){
          if($singleFilter === 'all'){
            $args['post_status']  = array('draft', 'publish', 'future');
          }else{
            $args['post_status']  = $singleFilter;
          }
        }

        if ($key === 'post_date') {
          $current_time = current_time('timestamp'); // Get the current timestamp
      
          if ($singleFilter === 'last_24_hrs') {
              $args['date_query'] = array(
                  array(
                      'after'     => date('Y-m-d H:i:s', strtotime('-24 hours', $current_time)),
                      'inclusive' => true,
                  ),
              );
          } else if ($singleFilter === 'last_7_days') {
              $args['date_query'] = array(
                  array(
                      'after'     => date('Y-m-d H:i:s', strtotime('-7 days', $current_time)),
                      'inclusive' => true,
                  ),
              );
          } else if ($singleFilter === 'last_30_days') {
              $args['date_query'] = array(
                  array(
                      'after'     => date('Y-m-d H:i:s', strtotime('-30 days', $current_time)),
                      'inclusive' => true,
                  ),
              );
          }
        }
        
        if ($key === 'post_modified') {
          $current_time = current_time('timestamp'); // Get the current timestamp
      
          if ($singleFilter === 'last_24_hrs') {
              $args['date_query'] = array(
                  array(
                      'column'    => 'post_modified', // Use 'post_modified' to compare with GMT time
                      'after'     => date('Y-m-d H:i:s', strtotime('-24 hours', $current_time)),
                      'inclusive' => true,
                  ),
              );
          } else if ($singleFilter === 'last_7_days') {
              $args['date_query'] = array(
                  array(
                      'column'    => 'post_modified',
                      'after'     => date('Y-m-d H:i:s', strtotime('-7 days', $current_time)),
                      'inclusive' => true,
                  ),
              );
          } else if ($singleFilter === 'last_30_days') {
              $args['date_query'] = array(
                  array(
                      'column'    => 'post_modified',
                      'after'     => date('Y-m-d H:i:s', strtotime('-30 days', $current_time)),
                      'inclusive' => true,
                  ),
              );
          }
        }
      }
    }
    return $args;
  }

  private static function get_child_post_count( $parent_post_id ) {
    $child_post_count = 0;

    // Query child posts based on parent post ID
    $child_posts = get_posts( array(
        'post_type'      => self::get_child_post_post_type_value($parent_post_id),
        'post_status'    => ['draft', 'publish', 'future'],
        'post_parent'    => $parent_post_id,
        'posts_per_page' => -1, // Retrieve all child posts
    ));
    // Count the number of child posts
    if ( $child_posts ) {
        $child_post_count = count( $child_posts );
    }
    return $child_post_count;
  }

  /**
   * delete post
   * 
   * @param object $post_id post ID
   */
  public static function delete_content_manager_post($post_id){
    
    $post = get_post($post_id);

    if($post){
      self::delete_post_with_children($post->ID);
      return true;
    }
    
    wp_send_json_error( 'Delete failed!', 422 );
  }
  
  /**
   * duplicate post
   * 
   * @param object $post_id post ID
   */
  public static function duplicate_content_manager_post($post_id){
    
    $post = get_post($post_id);

    if($post){
      $new_post = self::duplicate_post($post->ID, true);
      $new_post_id = $new_post->ID;
      
      self::duplicate_cm_reference_data($post_id, $new_post_id);

      return self::format_single_child_post($new_post);
    }
    
    wp_send_json_error( 'Duplicate failed!', 422 );
  }
  
  public static function duplicate_cm_reference_data($post_id, $new_post_id){
    global $wpdb;
    $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}droip_cm_reference WHERE post_id=%d", $post_id);
    $results = $wpdb->get_results($query, ARRAY_A);

    if (!empty($results)) {
      foreach ($results as $key => $result) {
        $wpdb->insert(
          $wpdb->prefix . 'droip_cm_reference',
          array(
            'post_id' => $new_post_id,
            'field_meta_key' => $result['field_meta_key'],
            'ref_post_id' => $result['ref_post_id'],
          ),
          array('%d', '%s', '%d')
        );
      }
    }
  }

  private static function duplicate_post($post_id, $with_children = true)
  {
    // Get the post to duplicate.
    $post = get_post($post_id);

    // Duplicate the post
    $new_post_id = wp_insert_post(array(
        'post_title'   => $post->post_title . ' Copy',
        'post_content' => $post->post_content,
        'post_type'    => $post->post_type,
        'post_parent'    => $post->post_parent,
        // Add more fields as needed
    ));

    if (is_wp_error($new_post_id)) {
        // Handle error if post duplication fails
        wp_send_json_error( 'Duplicate failed!', 422 );
    }

    // Duplicate post meta data
    $post_meta = get_post_meta($post_id);
    foreach ($post_meta as $key => $values) {
        foreach ($values as $value) {
            add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );// it is important to unserialize data to avoid conflicts.
        }
    }

    if ($with_children) {
      // Get the child posts of the original post
      $children = get_children(array(
        'post_parent' => $post_id,
        'post_type'   => $post->post_type,
      ));

      // Duplicate child posts recursively
      if (!empty($children)) {
        foreach ($children as $child) {
          self::duplicate_post($child->ID, $with_children);
        }
      }
    }

    // Return the ID of the duplicated post
    return get_post($new_post_id);
  }

  // 
  /**
   * duplicate data post
   * 
   * @param object $post_id post ID
   */
  public static function duplicate_content_manager_post_type($post_id)
  {
    $new_post = self::duplicate_post($post_id, false);
    if ($new_post) {
      return self::format_single_post($new_post);
    }
    wp_send_json_error('Duplicate failed!', 422);
  }

  private static function delete_post_with_children($post_id) {
    // Delete child posts recursively
    $children = get_children(array(
        'post_parent' => $post_id,
        'post_type'   => self::get_child_post_post_type_value($post_id), // Change 'any' to specific post types if needed
    ));

    if (!empty($children)) {
        foreach ($children as $child) {
            self::delete_post_with_children($child->ID);
        }
    }

    // // Delete post all meta data
    $metas = get_post_meta($post_id);
    foreach ($metas as $key => $value) {
      delete_post_meta($post_id, $key);
    }
    // Delete the post itself
    wp_delete_post($post_id, true); // Set the second parameter to true to force delete
  }

  public static function get_post_type_custom_field_keys($post_id) {
    $post_type_fields = self::get_parent_post_fields($post_id);

    $droip_content_manager_post_type_fields = array();

    if (is_array($post_type_fields)) {
      foreach($post_type_fields as $field) {
        $droip_content_manager_post_type_fields[$field['id']] = $field;
      }
    }

    return $droip_content_manager_post_type_fields;
  }

  /**
   * Get all custom fields of a post in the droip_cm_reference table by post ID.
   *
   * @param int $post_id Post ID.
   * @return array Custom fields.
   */
  public static function get_post_cm_ref_fields()
  {
    global $wpdb;

    $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}droip_cm_reference");
    $results = $wpdb->get_results($query, ARRAY_A);

    $ref_fields = [];
    if (count($results) > 0) {
      foreach ($results as $key => $result) {
        $ref_fields[] = $result;
      }
    }

    return $ref_fields;
  }

  /**
   * Get post ids by ref_post_id from droip_cm_reference table
   * 
   * @param int $ref_post_id post IDs -> Array of post IDs
   * @return array post ids
   */
  public static function get_post_ids_by_ref_post_ids($ref_post_ids)
  {
    // first check if $ref_post_ids is an array or not
    if (!is_array($ref_post_ids)) {
      $ref_post_ids = array($ref_post_ids);
    }

    global $wpdb;
    $ids_int = array_map('intval', $ref_post_ids);

    $placeholders    = array();
		$placeholder_str = '';

   if ( is_array( $ids_int ) ) {
				$placeholders    = array_fill( 0, count( $ids_int ), '%s' );
				$placeholder_str = implode( ', ', $placeholders );
			}

      
      $post_ids = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT post_id FROM {$wpdb->prefix}droip_cm_reference WHERE ref_post_id IN ($placeholder_str)",
          $ids_int
        ),
        ARRAY_A
      );
      
      $post_ids = array_map('intval', wp_list_pluck($post_ids, 'post_id'));
      $post_ids = array_unique($post_ids);
      
      if (!empty($post_ids)) {
        return $post_ids;
      }
      
      return array();
    }
  }
