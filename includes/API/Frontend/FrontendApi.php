<?php
/**
 * Frontend API calls handler
 *
 * @package droip
 */

namespace Droip\API\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\API\Frontend\Controllers\CollectionController;
use Droip\API\Frontend\Controllers\FormController;


/**
 * Frontend API Class
 */
class FrontendApi {

	/**
	 * Register all routes
	 *
	 * @return void
	 */
	public static function register() {
		( new FormController() )->register_routes();
		( new CollectionController() )->register_routes();
	}
}
