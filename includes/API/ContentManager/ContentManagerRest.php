<?php
/**
 * Collection controller
 *
 * @package droip
 */

 namespace Droip\API\ContentManager;

 if ( ! defined( 'ABSPATH' ) ) {
	 exit; // Exit if accessed directly.
 }

use Droip\HelperFunctions;
use WP_Error;
 use WP_REST_Controller;
 use WP_REST_Server;

/**
 * ContentManagerRest
 * 
 */
class ContentManagerRest extends WP_REST_Controller {

	/**
	 * Initialize the media class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->namespace = DROIP_APP_PREFIX . '/v1';
		$this->rest_base = 'content-manager';
	}

	/**
	 * Register register
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_all_post_types' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post_types/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array($this, 'get_post_type_settings'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post_types/get_referenced_collection',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array($this, 'get_referenced_collection'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_or_update_a_post_type' ),
					'permission_callback' => array( $this, 'post_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_type/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_post_type' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_type/delete',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'delete_content_manager_post' ),
					'permission_callback' => array( $this, 'post_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post_type/duplicate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array($this, 'duplicate_content_manager_post_type'),
					'permission_callback' => array($this, 'post_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types/items',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_all_items' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types/items',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_or_update_a_post_type_item' ),
					'permission_callback' => array( $this, 'post_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		/**
		 * Get post type item by id
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/post_types/item/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array($this, 'get_post_type_item'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::READABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types/items/action',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_post_type_item_action' ),
					'permission_callback' => array( $this, 'post_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/post_types/items/bulk-action',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_post_type_item_bulk_action' ),
					'permission_callback' => array( $this, 'post_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/validate_slug',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'validate_slug' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);



	}


	/**
	 * Checks if a given request has access to read contacts.
	 *
	 * @param \WP_REST_Request $request user request(not used right now).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_item_permissions_check( $request ) {
		//TODO: need to chick write permissions
		$capability = 'read';

		if ( current_user_can( $capability ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if a given request has access to read contacts.
	 *
	 * @param \WP_REST_Request $request user request(not used right now).
	 *
	 * @return \WP_REST_Response
	 */
	public function post_item_permissions_check( $request ) {
		//TODO: need to chick write permissions
		$capability = 'edit_posts';

		if ( current_user_can( $capability ) ) {
			return true;
		}

		return false;
	}

	/**
	 * get_all_post_types
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_all_post_types( $request ) {
		$args = [
			'page' => HelperFunctions::sanitize_text(isset($request['page']) ? $request['page'] : 1),
		];
		$posts = ContentManagerHelper::get_all_post_types($args);
		return rest_ensure_response( $posts );
	}

	/**
	 * get_post_type_s
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_post_type_settings($request)
	{
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');

		$post = ContentManagerHelper::get_post_type_settings($post_id);
		return rest_ensure_response($post);
	}

	public function get_referenced_collection($request)
	{
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
		$field_id = HelperFunctions::sanitize_text(isset($request['field_id']) ? $request['field_id'] : '');

		$data = ContentManagerHelper::get_referenced_collection($post_id, $field_id);
		return rest_ensure_response($data);
	}
	
	/**
	 * create_or_update_a_post_type
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function create_or_update_a_post_type( $request ) {
		$request = json_decode($request['data'], true);
		$args = [
			'ID' => HelperFunctions::sanitize_text(isset($request['ID']) ? $request['ID'] : ''),
			'post_title' => HelperFunctions::sanitize_text(isset($request['post_title']) ? $request['post_title'] : ''),
			'post_name' => HelperFunctions::sanitize_text(isset($request['post_name']) ? $request['post_name'] : $request['post_title']),
		];
		$othersArgs = [
			'fields' => $request['fields'],
			'basic_fields' => $request['basic_fields'],
		];
		$res = ContentManagerHelper::create_or_update_a_post_type( $args, $othersArgs );
		return rest_ensure_response( $res );
	}
	
	/**
	 * get_all_items
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_all_items( $request ) {
		$args = [
			'post_parent' => HelperFunctions::sanitize_text(isset($request['post_parent']) ? $request['post_parent'] : ''),
			'page' => HelperFunctions::sanitize_text(isset($request['page']) ? $request['page'] : 1),
			'query' => HelperFunctions::sanitize_text(isset($request['query']) ? $request['query'] : ''),
			'filter' => json_decode($request['filter'], true),
			'exclude_post_ids' => json_decode($request['exclude_post_ids'], true) ?: [],
		];
		$res = ContentManagerHelper::get_all_child_items( $args );
		return rest_ensure_response( $res );
	}


	/**
	 * get_post_type_item
	 *
	 * @param \WP_REST_Request $request Contains post id.
	 * 
	 * @param \WP_REST_Request $request Contains post parent id.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_post_type_item($request)
	{
		$post_id = HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '');

		$post = ContentManagerHelper::get_post_type_item($post_id);
		return rest_ensure_response($post);
	}

	/**
	 * get_post_type
	 *
	 * @param \WP_REST_Request $request Contains post id.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_post_type( $request ) {
		$post_id = HelperFunctions::sanitize_text(isset($request['id']) ? $request['id'] : '');
		$hierarchy = HelperFunctions::sanitize_text(isset($request['hierarchy']) ? $request['hierarchy'] : false);

		$post = ContentManagerHelper::get_post_type($post_id, $hierarchy);
		return rest_ensure_response( $post );
	}
	
	/**
	 * validate_slug
	 *
	 * @param \WP_REST_Request $request Contains post id.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function validate_slug( $request ) {
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
		$post_type = HelperFunctions::sanitize_text(isset($request['post_type']) ? $request['post_type'] : '');
		$post_name = HelperFunctions::sanitize_text(isset($request['post_name']) ? $request['post_name'] : '');

		$isValid = ContentManagerHelper::validate_slug($post_id, $post_type, $post_name);
		return rest_ensure_response( $isValid );
	}
	
	/**
	 * create_or_update_a_post_type_item
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function create_or_update_a_post_type_item( $request ) {		
		$request = json_decode($request['data'], true);
		$args = [
			'ID' => HelperFunctions::sanitize_text(isset($request['ID']) ? $request['ID'] : ''),
			'post_parent' => HelperFunctions::sanitize_text(isset($request['post_parent']) ? $request['post_parent'] : ''),
			'post_title' => HelperFunctions::sanitize_text(isset($request['post_title']) ? $request['post_title'] : ''),
			'post_name' => HelperFunctions::sanitize_text(isset($request['post_name']) ? $request['post_name'] : $request['post_title']),
			'post_status' => HelperFunctions::sanitize_text(isset($request['post_status']) ? $request['post_status'] : 'draft'),
			'post_date' => HelperFunctions::sanitize_text(isset($request['post_date']) ? $request['post_date'] : ''),
		];

		$othersArgs = [
			'fields' => $request['fields'],
		];

		$res = ContentManagerHelper::create_or_update_a_post_type_item( $args , $othersArgs);
		return rest_ensure_response( $res );
	}
	
	/**
	 * handle_post_type_item_action
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function handle_post_type_item_action( $request ) {		
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
		$action = HelperFunctions::sanitize_text(isset($request['action']) ? $request['action'] : '');
		$res = false;
		if($action === 'delete'){
			$res = ContentManagerHelper::delete_content_manager_post( $post_id );
		}else if($action === 'duplicate'){
			$res = ContentManagerHelper::duplicate_content_manager_post( $post_id );
		}
		return rest_ensure_response( $res );
	}
	
	/**
	 * handle_post_type_item_bulk_action
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function handle_post_type_item_bulk_action( $request ) {
		$post_ids = json_decode($request['post_ids'], true);
		$action = HelperFunctions::sanitize_text(isset($request['action']) ? $request['action'] : '');
		$post_parent = HelperFunctions::sanitize_text(isset($request['post_parent']) ? $request['post_parent'] : '');

		if(in_array('*', $post_ids)){
			$post_ids = get_posts(array(
				'fields'          => 'ids', // Only get post IDs
				'post_type'     => ContentManagerHelper::get_child_post_post_type_value($post_parent),
				'post_parent'   => $post_parent,
				'post_status'   => 'any',
				'numberposts'  => -1,
			));
		}

		if($action === 'delete'){
			foreach ($post_ids as $key => $post_id) {
				ContentManagerHelper::delete_content_manager_post( $post_id );
			}
			return true;
		}else if($action === 'duplicate'){
			$items = [];
			foreach ($post_ids as $key => $post_id) {
				$item = ContentManagerHelper::duplicate_content_manager_post( $post_id );
				$items[] = $item;
			}
			return $items;
		}
	}

	/**
	 * delete_content_manager_post
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function delete_content_manager_post( $request ) {
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
		$res = ContentManagerHelper::delete_content_manager_post( $post_id );
		return rest_ensure_response( $res );
	}

	/**
	 * duplicate_content_manager_post_type
	 * 
	 * @param \WP_REST_Request $request all user request parameter.
	 * 
	 * @return \WP_Error|WP_REST_Response
	 */
	public function duplicate_content_manager_post_type($request)
	{
		$post_id = HelperFunctions::sanitize_text(isset($request['post_id']) ? $request['post_id'] : '');
		$res = ContentManagerHelper::duplicate_content_manager_post_type($post_id);
		return rest_ensure_response($res);
	}
}