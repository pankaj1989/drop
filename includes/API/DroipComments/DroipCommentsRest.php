<?php

/**
 * Collection controller
 *
 * @package droip
 */

namespace Droip\API\DroipComments;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

use Droip\Ajax\Collaboration\Collaboration;
use Droip\HelperFunctions;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * ContentManagerRest
 * 
 */
class DroipCommentsRest extends WP_REST_Controller
{

  /**
   * Initialize the media class
   *
   * @return void
   */
  public function __construct()
  {
    $this->namespace = DROIP_APP_PREFIX . '/v1';
    $this->rest_base = 'droip-comments';
  }

  /**
   * Register register
   *
   * @return void
   */
  public function register_routes()
  {
    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/all',
      array(
        array(
          'methods'             => 'GET',
          'callback'            => array($this, 'get_all_comments'),
          'permission_callback' => array($this, 'get_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/create',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'create_comment'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/resolve',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'resolve_comment'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/all-users',
      array(
        array(
          'methods'             => 'GET',
          'callback'            => array($this, 'get_all_users'),
          'permission_callback' => '__return_true',
          'args'                => array(
            'query' => array(
              'description' => 'Search query for user display name.',
              'type'        => 'string',
              'required'    => false,
            ),
          ),
        ),
        'schema' => null,
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/read',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'read_comment'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/unread',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'unread_comment'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/delete',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'delete_comment'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/read-all',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'read_all_comments'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );

    register_rest_route(
      $this->namespace,
      '/' . $this->rest_base . '/resolve-all',
      array(
        array(
          'methods'             => 'POST',
          'callback'            => array($this, 'resolve_all_comments'),
          'permission_callback' => array($this, 'create_item_permissions_check'),
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ),
        'schema' => array($this, 'get_item_schema'),
      )
    );
  }

  public function read_all_comments($request)
  {
    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
    $user_id = get_current_user_id();

    global $wpdb;
    $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';

    $sql = $wpdb->prepare(
      "INSERT IGNORE INTO " . $seen_table_name . " (user_id, comment_id) 
       SELECT %d, id FROM " . $wpdb->prefix . DROIP_COMMENTS_TABLE . " 
       WHERE post_id = %d AND parent_id = 0",
      $user_id,
      $post_id
    );
    $wpdb->query($sql);

    return rest_ensure_response(array(
      'status' => 'success',
      'message' => __('All comments marked as read.'),
    ));
  }


  public function resolve_all_comments($request)
  {
    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
    $session_id = HelperFunctions::sanitize_text(isset($request['session_id']) ? $request['session_id'] : '');

    $status = 2;

    global $wpdb;
    $sql = $wpdb->prepare(
      "UPDATE " . $wpdb->prefix . DROIP_COMMENTS_TABLE . " SET status = %d WHERE post_id = %d AND parent_id = 0",
      $status,
      $post_id
    );

    $wpdb->query($sql);

    $data = array(
      'type'    => 'COLLABORATION_UPDATE_ALL_DROIP_COMMENT',
      'payload' => array('data' => array('status' => '' . $status)),
    );
    Collaboration::save_action_to_db('post', $post_id, $data, 1, $session_id);

    return rest_ensure_response(array(
      'status' => 'success',
      'message' => __('All comments marked as resolved.'),
    ));
  }


  public function resolve_comment($request)
  {
    $comment_id = intval(HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '0'));
    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
    $status = intval(HelperFunctions::sanitize_text(isset($request['status']) ? $request['status'] : '1'));
    $session_id = HelperFunctions::sanitize_text(isset($request['session_id']) ? $request['session_id'] : '');

    global $wpdb;
    $sql = $wpdb->prepare(
      "UPDATE " . $wpdb->prefix . DROIP_COMMENTS_TABLE . " SET status = %d WHERE id = %d",
      $status,
      $comment_id
    );

    $wpdb->query($sql);

    $data = array(
      'type'    => 'COLLABORATION_UPDATE_DROIP_COMMENT',
      'payload' => array('data' => array('status' =>  '' . $status), 'id' => '' . $comment_id),
    );
    Collaboration::save_action_to_db('post', $post_id, $data, 1, $session_id);

    return rest_ensure_response($this->get_single_comment($comment_id));
  }

  public function read_comment($request)
  {
    $comment_id = intval(HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '0'));
    $user_id = get_current_user_id();

    global $wpdb;
    $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';

    $sql = $wpdb->prepare(
      "INSERT INTO " . $seen_table_name . " (user_id, comment_id) VALUES ( %d, %d )",
      $user_id,
      $comment_id
    );
    $wpdb->query($sql);
    return rest_ensure_response($this->get_single_comment($comment_id));
  }

  public function unread_comment($request)
  {
    $comment_id = intval(HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '0'));
    $user_id = get_current_user_id();

    global $wpdb;
    $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';

    $sql = $wpdb->prepare(
      "DELETE FROM " . $seen_table_name . " WHERE comment_id = %d AND user_id = %d",
      $comment_id,
      $user_id
    );
    $wpdb->query($sql);
    return rest_ensure_response($this->get_single_comment($comment_id));
  }

  public function delete_comment($request)
  {
    $comment_id = intval(HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '0'));
    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
    $session_id = HelperFunctions::sanitize_text(isset($request['session_id']) ? $request['session_id'] : '');
    $user = wp_get_current_user();

    $comment = $this->get_single_comment($comment_id);
    $allowed_roles = array('editor', 'administrator');
    if ($comment['user_id'] == $user->ID || array_intersect($allowed_roles, $user->roles)) {
      global $wpdb;
      $table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE;
      $sql = $wpdb->prepare(
        "DELETE FROM " . $table_name . " WHERE id = %d OR parent_id = %d",
        $comment_id,
        $comment_id
      );

      $wpdb->query($sql);

      $data = array(
        'type'    => 'COLLABORATION_DELETE_DROIP_COMMENT',
        'payload' => array('id' => '' . $comment_id),
      );
      Collaboration::save_action_to_db('post', $post_id, $data, 1, $session_id);

      return rest_ensure_response(array(
        'status' => 'success',
        'message' => __('Comment deleted successfully.'),
      ));
    } else {
      return rest_ensure_response(array(
        'status' => 'error',
        'message' => __('You do not have permission to delete this comment.'),
      ));
    }
  }

  public function get_all_users($request)
  {
    $roles = array('editor', 'administrator', 'author');
    $query = isset($request['query']) ? sanitize_text_field($request['query']) : '';
    $per_page = HelperFunctions::sanitize_text(isset($request['per_page']) ? $request['per_page'] : 20);

    $args = array(
      'role__in' => $roles,
      'orderby'  => 'display_name',
      'order'    => 'ASC',
      'number'   => $per_page,
      'search'   => '*' . esc_attr($query) . '*',
      'search_columns' => array('user_login', 'user_nicename', 'display_name', 'user_email'),
    );

    $users = get_users($args);
    $formatted_users = array();

    foreach ($users as $user) {
      $formatted_users[] = array(
        'user_id'     => $user->ID,
        'user_name'   => $user->display_name,
        'user_avatar' => get_avatar_url($user->ID),
      );
    }

    return rest_ensure_response($formatted_users);
  }



  public function create_comment($request)
  {
    $comment = HelperFunctions::sanitize_text(isset($request['comment']) ? $request['comment'] : '[]');
    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
    $parent_id = HelperFunctions::sanitize_text(isset($request['parent_id']) ? $request['parent_id'] : 0);
    $status = HelperFunctions::sanitize_text(isset($request['status']) ? $request['status'] : 1);
    $meta_data = HelperFunctions::sanitize_text(isset($request['meta_data']) ? $request['meta_data'] : '[]');
    $session_id = HelperFunctions::sanitize_text(isset($request['session_id']) ? $request['session_id'] : '');
    $user_id = get_current_user_id();

    // echo "<pre>";var_dump($comment, $post_id, $parent_id, $status, $meta_data);die;

    global $wpdb;
    $sql = $wpdb->prepare(
      "INSERT INTO " . $wpdb->prefix . DROIP_COMMENTS_TABLE . " (user_id, post_id, parent_id, comment, meta_data, status ) VALUES ( %d, %d, %d, %s, %s, %d )",
      $user_id,
      $post_id,
      $parent_id,
      $comment,
      $meta_data,
      $status
    );
    $wpdb->query($sql);
    $comment_id = $wpdb->insert_id;

    if ($parent_id === '0') {
      $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';
      $sql = $wpdb->prepare(
        "INSERT INTO " . $seen_table_name . " (user_id, comment_id) VALUES ( %d, %d )",
        $user_id,
        $comment_id
      );
      $wpdb->query($sql);
    } else {
      $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';
      $sql = $wpdb->prepare(
        "DELETE FROM " . $seen_table_name . " WHERE comment_id = %d AND user_id != %d",
        $parent_id,
        $user_id
      );
      $wpdb->query($sql);
    }

    $comment = $this->get_single_comment($comment_id);
    $data = array(
      'type'    => 'COLLABORATION_ADD_DROIP_COMMENT',
      'payload' => array('comment' => array_merge($comment, array("read" => "0"))),
    );
    Collaboration::save_action_to_db('post', $post_id, $data, 1, $session_id);

    return rest_ensure_response($comment);
  }


  /**
   * get_all_comments
   *
   * @param \WP_REST_Request $request all user request parameter.
   *
   * @return \WP_Error|WP_REST_Response
   */
  public function get_all_comments($request)
  {
    global $wpdb;

    $post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : null);
    $current_page = HelperFunctions::sanitize_text(isset($request['page']) ? $request['page'] : 1);
    $per_page = HelperFunctions::sanitize_text(isset($request['per_page']) ? $request['per_page'] : 20);
    $user_id = get_current_user_id();

    $table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE;
    $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';

    $offset = ($current_page - 1) * $per_page;


    $params = [];

    $read_data = "CASE WHEN $seen_table_name.comment_id IS NOT NULL THEN 1 ELSE 0 END AS `read`";
    $read_join = "LEFT JOIN $seen_table_name ON $table_name.id = $seen_table_name.comment_id AND $seen_table_name.user_id = %d";
    $params[] = $user_id;

    $where = "WHERE $table_name.parent_id = %d";
    $params[] = 0;

    if ($post_id !== null) {
      $where .= " AND $table_name.post_id = %d";
      $params[] = $post_id;
    }

    $sql = "
        SELECT $table_name.*, $read_data 
        FROM $table_name 
        $read_join 
        $where 
        ORDER BY $table_name.created_at DESC 
        LIMIT %d, %d
    ";

    $params[] = $offset;
    $params[] = $per_page;

    $prepared_sql = $wpdb->prepare($sql, $params);
    $all_comments = $wpdb->get_results($prepared_sql, ARRAY_A);

    foreach ($all_comments as $key => $comment) {
      $all_comments[$key] = $this->format_single_comment($comment);
    }

    // Count query (also conditionally add post_id)
    $count_where = "WHERE parent_id = 0";
    if ($post_id !== null) {
      $count_where .= $wpdb->prepare(" AND post_id = %d", $post_id);
    }

    $count_sql = "SELECT COUNT(*) FROM $table_name $count_where";
    $count = $wpdb->get_var($count_sql);

    $res = [
      'total' => $count,
      'per_page' => $per_page,
      'current_page' => $current_page,
      'data' => $all_comments
    ];

    return rest_ensure_response($res);
  }

  private function get_single_comment($comment_id)
  {
    global $wpdb;

    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE;
    $seen_table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE . '_seen';

    $read_data = "CASE WHEN $seen_table_name.comment_id IS NOT NULL THEN 1 ELSE 0 END AS `read`";
    $read_join = "LEFT JOIN $seen_table_name ON $table_name.id = $seen_table_name.comment_id AND $seen_table_name.user_id = $user_id";

    $sql = "SELECT $table_name.*, $read_data FROM $table_name $read_join WHERE $table_name.id = $comment_id";
    $comment = $wpdb->get_row($sql, ARRAY_A);
    return $this->format_single_comment($comment);
  }
  private function format_single_comment($comment)
  {
    $comment['replies'] = $this->get_all_replies($comment['id']);
    $comment['meta_data'] = json_decode($comment['meta_data'], true);
    $comment['comment'] = json_decode($comment['comment'], true);
    $comment['user_avatar'] = get_avatar_url($comment['user_id']);
    $comment['user_name'] = get_the_author_meta('display_name', $comment['user_id']);
    return $comment;
  }

  private function get_all_replies($comment_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . DROIP_COMMENTS_TABLE;
    $sql = "SELECT * FROM $table_name WHERE parent_id = $comment_id ORDER BY created_at ASC";
    $all_replies = $wpdb->get_results($sql, ARRAY_A);
    foreach ($all_replies as $key => $reply) {
      $all_replies[$key] = $this->format_single_comment($reply);
    }
    return $all_replies;
  }


  public function get_item_permissions_check($request)
  {
    //TODO: need to chick write permissions
    $capability = 'read';

    if (current_user_can($capability)) {
      return true;
    }

    return false;
  }
  public function create_item_permissions_check($request)
  {
    //TODO: need to chick write permissions
    $capability = 'read';

    if (current_user_can($capability)) {
      return true;
    }

    return false;
  }
}