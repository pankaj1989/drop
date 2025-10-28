<?php

/**
 * Collection controller
 *
 * @package droip
 */

namespace Droip\API\Frontend\Controllers;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax\Users;
use Droip\Frontend\Preview\Preview;
use Droip\HelperFunctions;
use WP_REST_Server;


/**
 * CollectionController
 * Collection related routes and controller functions
 */
class CollectionController extends FrontendRESTController
{
	/**
	 * Register register
	 *
	 * @return void
	 */
	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/collection',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'get_collection'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/comments',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'get_comments'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/users',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'get_users'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/terms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'get_terms'),
					'permission_callback' => array($this, 'get_item_permissions_check'),
					'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
				),
				'schema' => array($this, 'get_item_schema'),
			)
		);
	}

	/**
	 * Creates a collection page
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_collection($request)
	{
		$page          = filter_var($request->get_param('page'), FILTER_VALIDATE_INT);
		$page = empty($page) ? 1 : $page;
		$collection_id = filter_var($request->get_param('collection_id'), FILTER_SANITIZE_STRING);
		$collection_param_filters     	 = json_decode($request->get_param('filters'), true);
		$droip_data     	 = json_decode($request->get_param('droip_data'), true);
		$context     	 = json_decode($request->get_param('context'), true);
		$query = filter_var($request->get_param('q'), FILTER_SANITIZE_STRING);

		$blocks = $droip_data['blocks'];
		$styles = $droip_data['styles'];

		$options = [];
		if ($context) {
			if (isset($context['type']) && $context['type'] === 'post') {
				$options = array(
					'post' => get_post($context['id'])
				);
			} else if (isset($context['type']) && $context['type'] === 'user') {
				$options = array(
					'user' => Users::get_user_by_id($context['id'])
				);
			} else if (isset($context['type']) && $context['type'] === 'term') {
				$options = array(
					'term' => get_term($context['id'])->to_array(),
				);
			} else if (isset($context['type']) && $context['type'] === 'comment') {
				$options = array(
					'post' => get_post($context['post_id']),
					'comment' => get_comment($context['comment']['comment_ID'])
				);
			}
		}

		$params = array(
			'blocks' => $blocks,
			'style_blocks' => $styles,
			'root' => $collection_id,
			'post_id' => null,
			'options' => array_merge(array(
				'page'				=> $page,
				'collection_param_filters'			=> $collection_param_filters,
				'q' => $query,
			), $options, $context)
		);

		$collection_wrapper_html_string = HelperFunctions::get_html_using_preview_script($params);

		return rest_ensure_response($collection_wrapper_html_string);
	}

	/**
	 * Creates a collection page
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_comments($request)
	{
		$page          = filter_var($request->get_param('page'), FILTER_VALIDATE_INT);
		$page = empty($page) ? 1 : $page;

		$collection_id = filter_var(($request->get_param('collection_id')), FILTER_SANITIZE_STRING);
		$post_id       = filter_var(($request->get_param('post_id')), FILTER_VALIDATE_INT);
		$droip_data     	 = json_decode($request->get_param('droip_data'), true);

		$blocks = $droip_data['blocks'];
		$styles    = $droip_data['styles'];


		$params = array(
			'blocks' => $blocks,
			'style_blocks' => $styles,
			'root' => $collection_id,
			'post_id' => null,
			'options' => array(
				'page'				=> $page,
				'post' => get_post($post_id),
			)
		);

		$collection_wrapper_html_string = HelperFunctions::get_html_using_preview_script($params);
		return rest_ensure_response($collection_wrapper_html_string);
	}


	/**
	 * Creates a collection page
	 *
	 * @param \WP_REST_Request $request all user request parameter.
	 *
	 * @return \WP_Error|WP_REST_Response
	 */
	public function get_users($request)
	{
		$page          = filter_var($request->get_param('page'), FILTER_VALIDATE_INT);
		$page = empty($page) ? 1 : $page;
		$collection_id = filter_var(($request->get_param('collection_id')), FILTER_SANITIZE_STRING);
		$post_id       = filter_var(($request->get_param('post_id')), FILTER_VALIDATE_INT);
		$droip_data     	 = json_decode($request->get_param('droip_data'), true);
		$query = filter_var($request->get_param('q'), FILTER_SANITIZE_STRING);

		$blocks = $droip_data['blocks'];
		$styles    = $droip_data['styles'];

		$params = array(
			'blocks' => $blocks,
			'style_blocks' => $styles,
			'root' => $collection_id,
			'post_id' => null,
			'options' => array(
				'post' => get_post($post_id),
				'page'				=> $page,
				'q' => $query,
			)
		);

		$collection_wrapper_html_string = HelperFunctions::get_html_using_preview_script($params);

		return rest_ensure_response($collection_wrapper_html_string);
	}

	/**
	 * Gets terms of a collection
	 */

	public function get_terms($request)
	{
		$page          = filter_var($request->get_param('page'), FILTER_VALIDATE_INT);
		$page = empty($page) ? 1 : $page;

		$collection_id = filter_var(($request->get_param('collection_id')), FILTER_SANITIZE_STRING);
		$post_id       = filter_var(($request->get_param('post_id')), FILTER_VALIDATE_INT);
		$droip_data     	 = json_decode($request->get_param('droip_data'), true);

		$blocks = $droip_data['blocks'];
		$styles    = $droip_data['styles'];


		$params = array(
			'blocks' => $blocks,
			'style_blocks' => $styles,
			'root' => $collection_id,
			'post_id' => null,
			'options' => array(
				'page'				=> $page,
				'post' => get_post($post_id),
			)
		);

		$collection_wrapper_html_string = HelperFunctions::get_html_using_preview_script($params);
		return rest_ensure_response($collection_wrapper_html_string);
	}
}