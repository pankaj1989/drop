<?php

/**
 * Droip
 *
 * @package     droip
 * Plugin Name: Droip
 * Plugin URI: https://droip.com
 * Description: Droip is an all-in-one no-code builder that empowers users to build professional-grade WordPress sites without writing any code. It’s a promising glimpse into the future of website development.
 * Version: 2.4.0
 * Author: Droip
 * Author URI: https://droip.com
 * Text Domain: droip
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Update URI:  https://droip.s3.amazonaws.com/dist/droip-builds/packages.json
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax;
use Droip\API;
use Droip\Apps;
use Droip\ContentManager;
use Droip\ElementVisibilityConditions;
use Droip\HelperFunctions;
use Droip\Manager\PluginActiveEvents;
use Droip\Manager\PluginDeactivateEvents;
use Droip\Manager\PluginInitEvents;
use Droip\Manager\PluginLoadedEvents;
use Droip\Manager\PluginUpdateEvents;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
if ( ! class_exists( 'Droip' ) ) {
	/**
	 * Droip Starting Class
	 */
	final class Droip {
		/**
		 * Class constructor
		 */
		private function __construct() {
			$this->define_constants();
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
			add_action('init', [$this, 'plugin_init']);
			new PluginLoadedEvents();
			new PluginInitEvents();
			new PluginUpdateEvents();
		}

		/**
		 * Initializes a singleton instance
		 *
		 * @return \Droip
		 */
		public static function init() {
			static $instance = false;

			if ( ! $instance ) {
				$instance = new self();
			}

			// loading ajax.
			new Ajax();

			// load API.
			new API();

			new ContentManager();

			new ElementVisibilityConditions();

			return $instance;
		}

		public function plugin_init(){
			new Apps();
		}

		/**
		 * Define plugin constants
		 *
		 * @return void
		 */
		public function define_constants() {
			require plugin_dir_path( __FILE__ ) . 'config.php';
		}

		/**
		 * Do stuff upon plugin activation
		 *
		 * @return void
		 */
		public function activate() {
			new PluginActiveEvents();
		}

		/**
		 * Do stuff upon plugin deactivation
		 *
		 * @return void
		 */
		public function deactivate() {
			new PluginDeactivateEvents();
		}
	}
}

/**
 * Initilizes the main plugin
 *
 * @return \Droip
 */
if ( ! function_exists( 'droip' ) ) {
	/**
	 * This function for entry point
	 */
	function droip() {
		return Droip::init();
	}

	try {
		// kick-off the plugin.
		droip();
	} catch ( Exception $e ) {
		HelperFunctions::store_error_log( wp_json_encode( $e ) );
	}
}