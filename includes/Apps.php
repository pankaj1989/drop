<?php
/**
 * Admin panel droip entry point
 *
 * @package droip
 */

namespace Droip;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Droip Apps
 */
class Apps {
	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->import_droip_component_library();
		$this->import_droip_external_apps();
	}

  private function import_droip_component_library(){
    require_once DROIP_ROOT_PATH . 'ComponentLibrary/index.php';
		if(IS_DEVELOPING_DROIP_APPS) require_once  DROIP_DEVELOPING_APPS_INCLUDES;;
  }
  
  private function import_droip_external_apps(){
    $apps = HelperFunctions::get_global_data_using_key( 'droip_installed_apps' );
    if(!$apps){
        $apps = array();
    }

		if(!IS_DEVELOPING_DROIP_APPS){
			foreach ($apps as $key => $app) {
					$app_slug = $app['app_slug'];
					$base_upload_dir = WP_CONTENT_DIR; // Absolute path to 'uploads'
					$app_folder_index_file = $base_upload_dir . '/droip-apps/' . $app_slug . '/index.php';

					// Check if the index.php file exists
					if(file_exists($app_folder_index_file)){
							// Import logic goes here
							$this->import_app($app_slug, $app_folder_index_file);
					}
    	}
		}
  }

  // Example import logic function
  private function import_app($app_slug, $index_file_path){
      // Custom logic for importing the app
      include_once($index_file_path); // Example: including the index.php file
      // Additional logic for handling the app
  }

  /**
	 * Get app settings based on the caller's file path
	 *
	 * @return array|null App settings or null if not found
	 */
	public static function get_settings($slug) {
		return self::get_app_settings_by_slug($slug);
		// Get the debug backtrace
		$backtrace = debug_backtrace();
		
		// Find the file from the calling context
		$caller_file = isset( $backtrace[0]['file'] ) ? $backtrace[0]['file'] : null;

		if ( ! $caller_file ) {
			return null; // Cannot determine caller
		}

		// Find the app_slug from the caller's file path
		$base_upload_dir = WP_CONTENT_DIR . '/droip-apps/'; // Base directory for apps

		// Check if the file is in the apps folder
		if ( strpos( $caller_file, $base_upload_dir ) === 0 ) {
			// Extract the app folder name
			$relative_path = str_replace( $base_upload_dir, '', $caller_file );
			$path_parts = explode( '/', $relative_path );
			$app_slug = $path_parts[0]; // The first part of the relative path is the app_slug
			// Retrieve app-specific settings
			return self::get_app_settings_by_slug( $app_slug );
		}

		return null; // Not an app file
	}

	/**
	 * Retrieve app settings by app_slug
	 *
	 * @param string $app_slug The app ID
	 * @return array|null App settings or null if not found
	 */
	private static function get_app_settings_by_slug( $app_slug ) {
		// Example logic to fetch app settings (e.g., from a database or configuration file)
		// Replace this with your own implementation
		$app_settings = HelperFunctions::get_global_data_using_key( 'droip_app_settings_' . $app_slug );

		return $app_settings ? $app_settings : null;
	}

}