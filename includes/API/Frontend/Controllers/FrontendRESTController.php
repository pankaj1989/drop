<?php
/**
 * FrontendRESTController
 *
 * @package droip
 */

namespace Droip\API\Frontend\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use WP_Error;
use WP_REST_Controller;


/**
 * FrontendRESTController class
 */
abstract class FrontendRESTController extends WP_REST_Controller {
	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->namespace = DROIP_APP_PREFIX . '/v1';
		$this->rest_base = 'frontend';
	}


	/**
	 * Checks if a given request has access to get a specific item.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}
}
