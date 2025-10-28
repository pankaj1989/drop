<?php
/**
 * Plugin deactive events handler
 *
 * @package droip
 */

namespace Droip\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;


/**
 * Do some task during plugin deactivation
 */
class PluginDeactivateEvents {


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->store_log();
		// Flush rewrite rules on deactivation
    flush_rewrite_rules(true);
	}

	/**
	 * Stores the log.
	 *
	 * @return void
	 */
	private function store_log() {
		$site_url = get_site_url();

		HelperFunctions::http_get( DROIP_CORE_PLUGIN_URL . '?log_data=activity&event=deactivate&version=' . DROIP_VERSION . '&domain=' . $site_url );
	}

}