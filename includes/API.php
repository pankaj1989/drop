<?php

/**
 * Register routes for Media and Frontend
 *
 * @package droip
 */

namespace Droip;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\API\ContentManager\ContentManagerRest;
use Droip\API\DroipComments\DroipCommentsRest;
use Droip\API\Media;
use Droip\API\Frontend\FrontendApi;

/**
 * API Class
 */
class API
{


	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action('rest_api_init', array($this, 'register_api'));

		if (isset($_GET['page-export'], $_GET['file-name']) && $_GET['page-export'] === 'true') {
			//TODO: need to check nonce
			$this->downloadZIP();
		}
	}

	/**
	 * Register_api
	 *
	 * @return void
	 */
	public function register_api()
	{
		// Media apis.
		$media = new Media();
		$media->register_routes();
		
		$content_manager = new ContentManagerRest();
		$content_manager->register_routes();

		$droip_comments = new DroipCommentsRest();
		$droip_comments->register_routes();

		FrontendApi::register();
	}

	private function downloadZIP()
	{
		$upload_dir = wp_upload_dir();
		$file_name = HelperFunctions::sanitize_text($_GET['file-name']);
		$file_name = basename($file_name);
		// Check if the file has a .zip extension
		if ( !pathinfo($file_name, PATHINFO_EXTENSION) === 'zip' ) {
			echo "Invalid file type.";
			die();
		}
		$zipFilePath = $upload_dir['basedir'] .  "/$file_name";
		// Send the zip file to the client.
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $file_name . '"');
		header('Content-Length: ' . filesize($zipFilePath));
		readfile($zipFilePath);
		// Delete the zip file from the server.
		unlink($zipFilePath);
		exit;
	}
}