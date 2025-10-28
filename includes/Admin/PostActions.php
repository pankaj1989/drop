<?php
/**
 * Post Actions triggers
 *
 * @package droip
 */

namespace Droip\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;


/**
 * PostActions Class
 */
class PostActions {


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'deleted_post', array( new HelperFunctions(), 'delete_post_with_meta_key' ) );
	}
}
