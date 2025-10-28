<?php
/**
 * Media routes and media api manager
 *
 * @package droip
 */

namespace Droip\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;


/**
 * Media Class
 */
class Media extends WP_REST_Controller {

	/**
	 * Initialize the media class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->namespace = 'droip/v1';
		$this->rest_base = 'media';
	}

	/**
	 * Register media routes
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get media.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . 'home',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_home_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<media_type>\S+)',
			array(
				'args'   => array(
					'media_type' => array(
						'description' => __( 'media type name to get specific media.', 'droip' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . 'trash-restore' . '/(?P<post_id>\S+)',
			array(
				'args'   => array(
					'post_id' => array(
						'description' => __( 'Unique post id for the object.', 'droip' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'trash_or_restore_item' ),
					'permission_callback' => array( $this, 'post_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
	public function get_items_permissions_check( $request ) {
		if(HelperFunctions::is_api_call_from_editor_preview() && HelperFunctions::is_api_header_post_editor_preview_token_valid()){
			return true;
		}
		$capability = 'edit_posts';

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
	public function post_items_permissions_check( $request ) {
		$capability = 'edit_posts';

		if ( current_user_can( $capability ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieves a list of address items.
	 *
	 * @param \WP_REST_Request $request user request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$args       = array();
		$media_type = 'normal';
		$params     = $this->get_collection_params();

		foreach ( $params as $key => $value ) {
			if ( isset( $request[ $key ] ) ) {
				$args[ $key ] = $request[ $key ];
			}
		}

		if ( isset( $request['media_type'] ) ) {
			if ( $request['media_type'] === 'trash' ) {
				$media_type = 'trash';
			} else {
				exit();
			}
		}

		$args['number'] = $args['per_page'];
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$args['offset'] = HelperFunctions::sanitize_text( isset( $_GET['offset'] ) ? $_GET['offset'] : 0 );
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$args['category'] = HelperFunctions::sanitize_text( isset( $_GET['category'] ) ? $_GET['category'] : '' );

		// unset others.
		unset( $args['per_page'] );
		unset( $args['page'] );

		$data  = array();
		$media = $this->bx_get_media( $args, $media_type );

		if ( ! $media ) {
			return array(
				'mediaData' => array(),
				'hasMore'   => false,
			);
		}

		foreach ( $media as $media_item ) {
			$response = $this->prepare_item_for_response( $media_item, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		$total     = $this->bx_media_count( $media_type );
		$max_pages = ceil( $total / (int) $args['number'] );

		$response_data = array(
			'mediaData' => array_slice( $data, 0, $args['number'] ),
			'hasMore'   => count( $data ) === $args['number'] + 1,
		);
		$response      = rest_ensure_response( $response_data );

		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		return $response;
	}

	/**
	 * Retrieves a list of home media items.
	 *
	 * @param \WP_REST_Request $request user request parameter.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_home_items( $request ) {
		$media_number = 3;
		$img_args     = array(
			'number'   => $media_number,
			'category' => 'image',
		);
		$audio_args   = array(
			'number'   => $media_number,
			'category' => 'audio',
		);
		$video_args   = array(
			'number'   => $media_number,
			'category' => 'video',
		);
		$svg_args     = array(
			'number'   => $media_number,
			'category' => 'svg',
		);
		$pdf_args     = array(
			'number'   => $media_number,
			'category' => 'pdf',
		);

		$params = $this->get_collection_params();
		foreach ( $params as $key => $value ) {
			if ( isset( $request[ $key ] ) ) {
				$img_args[ $key ]   = $request[ $key ];
				$audio_args[ $key ] = $request[ $key ];
				$video_args[ $key ] = $request[ $key ];
				$svg_args[ $key ]   = $request[ $key ];
				$pdf_args[ $key ]   = $request[ $key ];
			}
		}

		$img_data  = array();
		$img_media = $this->bx_get_media( $img_args );

		foreach ( $img_media as $media_item ) {
			$response   = $this->prepare_item_for_response( $media_item, $request );
			$img_data[] = $this->prepare_response_for_collection( $response );
		}

		$audio_data  = array();
		$audio_media = $this->bx_get_media( $audio_args );

		foreach ( $audio_media as $media_item ) {
			$response     = $this->prepare_item_for_response( $media_item, $request );
			$audio_data[] = $this->prepare_response_for_collection( $response );
		}

		$video_data  = array();
		$video_media = $this->bx_get_media( $video_args );

		foreach ( $video_media as $media_item ) {
			$response     = $this->prepare_item_for_response( $media_item, $request );
			$video_data[] = $this->prepare_response_for_collection( $response );
		}

		$svg_data  = array();
		$svg_media = $this->bx_get_media( $svg_args );

		foreach ( $svg_media as $media_item ) {
			$response   = $this->prepare_item_for_response( $media_item, $request );
			$svg_data[] = $this->prepare_response_for_collection( $response );
		}

		$pdf_data  = array();
		$pdf_media = $this->bx_get_media( $pdf_args );

		foreach ( $pdf_media as $media_item ) {
			$response   = $this->prepare_item_for_response( $media_item, $request );
			$pdf_data[] = $this->prepare_response_for_collection( $response );
		}

		$response_data = array(
			'image' => array(
				'data'  => $img_data,
				'total' => $this->bx_media_category_count( HelperFunctions::get_media_type_query_string( 'image' ), ),
			),
			'audio' => array(
				'data'  => $audio_data,
				'total' => $this->bx_media_category_count( HelperFunctions::get_media_type_query_string( 'audio' ), ),
			),
			'video' => array(
				'data'  => $video_data,
				'total' => $this->bx_media_category_count( HelperFunctions::get_media_type_query_string( 'video' ), ),
			),
			'svg'   => array(
				'data'  => $svg_data,
				'total' => $this->bx_media_category_count( HelperFunctions::get_media_type_query_string( 'svg' ), ),
			),
			'pdf'   => array(
				'data'  => $pdf_data,
				'total' => $this->bx_media_category_count( HelperFunctions::get_media_type_query_string( 'pdf' ), ),
			)
		);

		$response = rest_ensure_response( $response_data );
		return $response;
	}

	/**
	 * Remove or restore a media item.
	 *
	 * @param \WP_REST_Request $request user request parameter.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function trash_or_restore_item( $request ) {
		$post_id = $this->bx_trash_restore_media( $request['post_id'] );

		if ( $post_id === -1 ) {
			return new WP_Error(
				'rest_media_to_trash_or_restore',
				__( 'Media can not move to trash or restore.', 'droip' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );

		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );
		return $response;
	}

	/**
	 * Prepare media items for response
	 *
	 * @param object $item item info.
	 * @param array  $request user request.
	 */
	public function prepare_item_for_response($item, $request)
	{
		$data       = array();
		$fields     = $this->get_fields_for_response($request);
		$categories = DROIP_SUPPORTED_MEDIA_TYPES;

		if (in_array('id', $fields, true)) {
			$data['id'] = (int) $item->ID;
		}

		if (in_array('name', $fields, true)) {
			$data['name'] = $item->post_name;
		}

		if (in_array('alt', $fields, true)) {
			$data['alt'] = $item->post_name;
		}

		if (in_array('type', $fields, true)) {
			$data['type'] = $item->post_mime_type;
		}

		if (in_array('url', $fields, true)) {
			$data['url'] = $item->guid;
		}

		$data['thumbnail'] = wp_get_attachment_image_url($item->ID);
		
		if ( in_array( 'category', $fields, true ) ) {
			$category = '';

			foreach ($categories as $cat => $mime_types) {
				if (in_array($item->post_mime_type, $mime_types, true)) {
					$category = 'svg' === $cat ? 'image' : $cat;
					break;
				}
			}

			$data['category'] = $category;
		}

		if (in_array('trash', $fields, true)) {
			$data['trash'] = 'trash' === $item->post_status;
		}

		// media file size converting to human readable format
		$data['file_size'] = filesize(get_attached_file($item->ID));
		$data['file_size'] = size_format($data['file_size']);

		// media file extension
		$file_path = get_attached_file($item->ID);
		$data['file_extension'] = pathinfo($file_path, PATHINFO_EXTENSION);


		$context = !empty($request['context']) ? $request['context'] : 'view';
		$data    = $this->filter_response_by_context($data, $context);

		$response = rest_ensure_response($data);

		return $response;
	}

	/**
	 * Retrieves the contact schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		// response from the cache.
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema',
			'title'      => 'media',
			'type'       => 'object',
			'properties' => array(
				'id'       => array(
					'description' => __( 'Unique identifier for the object.', 'droip' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'     => array(
					'description' => __( 'Name of the media item.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'alt'      => array(
					'description' => __( 'Alt of the media item.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'type'     => array(
					'description' => __( 'Media mime type.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'category' => array(
					'description' => __( 'Media category.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'url'      => array(
					'description' => __( 'Media url.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'trash'    => array(
					'description' => __( 'Media trash or not.', 'droip' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'total'    => array(
					'description' => __( 'Total media items.', 'droip' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date'     => array(
					'description' => __( "The date the object was published, in the site's timestamp.", 'droip' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params from collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();
		return $params;
	}

	/**
	 * Fetch media
	 *
	 * @param array  $args params.
	 * @param string $media_type normal.
	 *
	 * @return array
	 */
	private function bx_get_media( $args = array(), $media_type = 'normal' ) {
		global $wpdb;

		$order_list = array( 'ASC', 'DESC' );

	  //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order      = isset( $_GET['order'] ) && in_array( strtoupper( HelperFunctions::sanitize_text( $_GET['order'] ) ), $order_list, true ) ? strtoupper( HelperFunctions::sanitize_text( $_GET['order'] ) ) : 'DESC';
		$defaults   = array(
			'number'   => 20,
			'offset'   => 0,
			'orderby'  => 'post_date',
			'order'    => $order,
			'search'   => '',
			'category' => '',
		);
		$mime_types = array(
			'image'  => HelperFunctions::get_media_type_query_string( 'image' ),
			'audio'  => HelperFunctions::get_media_type_query_string( 'audio' ),
			'svg'    => HelperFunctions::get_media_type_query_string( 'svg' ),
			'video'  => HelperFunctions::get_media_type_query_string( 'video' ),
			'lottie' => HelperFunctions::get_media_type_query_string( 'lottie' ),
			'pdf'    => HelperFunctions::get_media_type_query_string( 'pdf' ),
		);

		$categories     = explode( ',', $args['category'] );
		$mime_types_str = '';

		foreach ( $categories as $key => $category ) {
			if ( $category ) {
				$mime_types_str .= $key > 0 ? ',' . $mime_types[ $category ] : $mime_types[ $category ];
			}
		}

		$args = wp_parse_args( $args, $defaults );

		$limit_plus_one = $args['number'] + 1;

		$post_status_condition = $media_type === 'normal' ? $wpdb->prepare( 'post_status<>%s', 'trash' ) : $wpdb->prepare( 'post_status=%s', 'trash' );
		$base_condition        = $wpdb->prepare( 'post_type=%s', 'attachment' ) . " AND {$post_status_condition}";
		$where_conditions      = $mime_types_str ? $base_condition . " AND post_mime_type IN ($mime_types_str)" : $base_condition;
		$where_conditions      = $args['search'] ? $where_conditions . " AND post_name LIKE '%%{$args['search']}%%'" : $where_conditions;

		$sql = "SELECT * FROM {$wpdb->prefix}posts WHERE {$where_conditions}" . $wpdb->prepare(
			' ORDER BY %1s %1s LIMIT %d, %d',
			$args['orderby'],
			$args['order'],
			$args['offset'],
			$limit_plus_one
		);
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$media = $wpdb->get_results( $sql );

		return $media;
	}

	/**
	 * Get the count of total posts
	 *
	 * @param string $media_type normal.
	 * @return int
	 */
	private function bx_media_count( $media_type = 'normal' ) {
		global $wpdb;

		$post_status_condition = $media_type === 'normal' ? $wpdb->prepare( 'post_status<>%s', 'trash' ) : $wpdb->prepare( 'post_status=%s', 'trash' );

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$wpdb->prefix}posts WHERE post_type = %s AND ",
			'attachment',
		) . $post_status_condition;
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( $query );

		return $count;
	}

	/**
	 * Get the count of total media category items
	 *
	 * @param string $mime_types file type.
	 * @return int
	 */
	private function bx_media_category_count( $mime_types ) {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$wpdb->prefix}posts WHERE post_status <> %s",
			'trash'
		) . ' AND post_mime_type IN (' . $mime_types . ')';
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( $query );
		return $count;
	}

	/**
	 * Set post status to trash of a media item
	 *
	 * @param    integer $post_id    post id of the item.
	 *
	 * @return   integer
	 */
	private function bx_trash_restore_media( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return -1;
		}

		$new_post_status = $post->post_status === 'trash' ? 'publish' : 'trash';

		$args    = array(
			'ID'          => $post_id,
			'post_status' => $new_post_status,
		);
		$post_id = wp_update_post( $args );

		if ( ! $post_id ) {
			return -1;
		}

		return $post_id;
	}
}