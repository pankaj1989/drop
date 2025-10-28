<?php

/**
 * Manager User data
 *
 * @package droip
 */

namespace Droip\Ajax;

use Droip\HelperFunctions;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Apps API Class
 */
class Apps
{

	/**
	 * get_user_installed_apps_list
	 *
	 * @return void wp_send_json
	 */
	public static function get_app_list()
	{
		$apps = HelperFunctions::get_global_data_using_key('droip_installed_apps');
		if (!$apps) {
			$apps = array();
		}
		$apps = HelperFunctions::http_get(DROIP_APPS_BASE_URL . '/apps.json', array('sslverify' => false));
		wp_send_json_success(json_decode($apps));
	}

	/**
	 * get_user_installed_apps_list
	 *
	 * @return void wp_send_json
	 */
	public static function get_installed_apps_list()
	{
		$apps = HelperFunctions::get_global_data_using_key('droip_installed_apps');
		if (!$apps) {
			$apps = array();
		}
		wp_send_json_success($apps);
	}

	/**
	 * get_app_settings_using_slug
	 *
	 * @return void wp_send_json
	 */
	public static function get_app_settings_using_slug()
	{
		$user_id = get_current_user_id();
		if (! empty($user_id)) {
			$app_slug = HelperFunctions::sanitize_text(isset($_GET['slug']) ? $_GET['slug'] : null);
			$settings = HelperFunctions::get_global_data_using_key('droip_app_settings_' . $app_slug);
			if (!$settings) {
				$settings = array();
			}
			$settings = apply_filters('droip_apps_configuration_' . $app_slug, $settings);
			wp_send_json_success($settings);
		}
		wp_send_json_error('User authentication failed. Please log in.');
		die();
	}

	/**
	 * get_app_settings_using_slug
	 *
	 * @return void wp_send_json
	 */
	public static function save_app_settings_using_slug()
	{
		$user_id = get_current_user_id();
		if (! empty($user_id)) {
			$app_slug = HelperFunctions::sanitize_text(isset($_POST['slug']) ? $_POST['slug'] : null);
			$settings = HelperFunctions::sanitize_text(isset($_POST['settings']) ? $_POST['settings'] : null);
			$settings = json_decode(stripslashes($settings), true);
			HelperFunctions::update_global_data_using_key('droip_app_settings_' . $app_slug, $settings);
			wp_send_json_success(true);
		}
		wp_send_json_error('User authentication failed. Please log in.');
		die();
	}

	public static function install_app()
	{
		error_reporting(E_ALL);
		ini_set('display_errors', 1);

		$user_id = get_current_user_id();
		if (!empty($user_id)) {
			$app = HelperFunctions::sanitize_text(isset($_POST['app']) ? $_POST['app'] : null);
			$app = json_decode(stripslashes($app), true);

			$app_slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $app['slug']);

			$src = $app['src'];

			$version = isset($app['version']) ? $app['version'] : '1.0.0';

			// Get the installed apps data
			$apps = HelperFunctions::get_global_data_using_key('droip_installed_apps');
			if (!$apps) {
				$apps = array();
			}

			// Check if the app is already installed
			if (in_array($app_slug, $apps)) {
				wp_send_json_error('This app is already installed. Please check the droip-apps directory in wp-content folders.');
			}

			// Define the remote zip URL and generate a unique file name
			$remote_zip_url = DROIP_APPS_BASE_URL . $src;
			$file_name_new = uniqid('', true) . '.zip'; // 'random.ext'

			// Check if ZipArchive extension is loaded
			if (!class_exists('ZipArchive')) {
				wp_send_json_error('ZipArchive extension is not installed or enabled on the server.');
			}

			// Attempt to download the zip file
			$zip_file_path = HelperFunctions::download_zip_from_remote($remote_zip_url, $file_name_new);

			try {
				// If the file was successfully downloaded
				if ($zip_file_path) {
					$zip = new \ZipArchive;
					$res = $zip->open($zip_file_path);

					if ($res === TRUE) {
						// Get the WordPress wp-content directory
						$base_upload_dir = WP_CONTENT_DIR; // Absolute path to 'uploads'
						$temp_folder = $base_upload_dir . '/droip-apps';

						// Check if the wp-content directory is writable
						if (!is_writable($base_upload_dir)) {
							unlink($zip_file_path);
							wp_send_json_error('The wp-content directory is not writable. Please check folder permissions.');
							return;
						}

						// Create the 'droip-apps' folder if it doesn't exist
						if (!file_exists($temp_folder)) {
							if (!wp_mkdir_p($temp_folder)) {
								unlink($zip_file_path);
								wp_send_json_error('Failed to create directory: droip-apps. Please check folder permissions.');
								return;
							}
						}

						// Extract the zip file to the 'droip-apps' folder
						$zip->extractTo($temp_folder);
						$zip->close();

						// Clean up the zip file
						unlink($zip_file_path);

						// Update the list of installed apps
						$apps[] = array('app_slug' => $app_slug, 'version' => $version);
						HelperFunctions::update_global_data_using_key('droip_installed_apps', $apps);

						wp_send_json_success('App installed successfully!');
					} else {
						unlink($zip_file_path);
						wp_send_json_error('Failed to extract the zip file. Please ensure the file is valid.');
					}
				} else {
					wp_send_json_error('Failed to download the app zip file. Please check the remote URL.');
				}
			} catch (\Throwable $th) {
				// Log the error or handle it as needed
				unlink($zip_file_path); // Clean up the zip file if it exists
				wp_send_json_error('An error occurred during the app installation: ' . $th->getMessage());
			}
		} else {
			wp_send_json_error('User authentication failed. Please log in.');
		}
		die();
	}


	public static function delete_app_using_slug()
	{
		$user_id = get_current_user_id();
		if (! empty($user_id)) {
			$app_slug = HelperFunctions::sanitize_text(isset($_POST['slug']) ? $_POST['slug'] : null);

			$apps = HelperFunctions::get_global_data_using_key('droip_installed_apps');
			if (!$apps) {
				$apps = array();
			}

			$is_app_present = false;

			foreach ($apps as $app) {
				if (isset($app['app_slug']) && $app['app_slug'] === $app_slug) {
					$is_app_present = true;
					break;
				}
			}

			if ($is_app_present) {
				$base_upload_dir = WP_CONTENT_DIR; // Absolute path to 'uploads'
				$app_folder = $base_upload_dir . '/droip-apps/' . $app_slug;

				$arr = [];
				foreach ($apps as $key => $a) {
					if (isset($a['app_slug']) && $a['app_slug'] !== $app_slug) {
						$arr[] = $a;
					}
				}

				HelperFunctions::update_global_data_using_key('droip_installed_apps', $arr);
				HelperFunctions::delete_directory($app_folder);
				HelperFunctions::update_global_data_using_key('droip_app_settings_' . $app_slug, false);
				wp_send_json_success('success');
			}

			wp_send_json_error('App not found!');
		}
		die();
	}

	public static function update_app()
	{
		error_reporting(E_ALL);
		ini_set('display_errors', 1);

		$user_id = get_current_user_id();
		if (empty($user_id)) {
			wp_send_json_error('User authentication failed. Please log in.');
		}

		try {
			$app = HelperFunctions::sanitize_text(isset($_POST['app']) ? $_POST['app'] : null);
			$app = json_decode(stripslashes($app), true);

			if (!$app || !isset($app['slug']) || !isset($app['src']) || !isset($app['version'])) {
				wp_send_json_error('Invalid app data provided.');
			}

			$app_slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $app['slug']);
			$src = $app['src'];
			$new_version = $app['version'];

			// Check if the app is installed
			$apps = HelperFunctions::get_global_data_using_key('droip_installed_apps');
			if (!$apps) {
				wp_send_json_error('No apps are currently installed.');
			}

			$app_found = false;
			$current_version = '';
			foreach ($apps as $installed_app) {
				if (isset($installed_app['app_slug']) && $installed_app['app_slug'] === $app_slug) {
					$app_found = true;
					$current_version = $installed_app['version'];
					break;
				}
			}

			if (!$app_found) {
				wp_send_json_error('App not found in installed apps.');
			}

			// Check if ZipArchive is available
			if (!class_exists('ZipArchive')) {
				wp_send_json_error('ZipArchive extension is not installed or enabled on the server.');
			}

			$base_upload_dir = WP_CONTENT_DIR;
			$apps_folder = $base_upload_dir . '/droip-apps';
			$app_folder = $apps_folder . '/' . $app_slug;
			$backup_folder = $app_folder . '_backup_' . time();

			// Create backup of current app
			if (!rename($app_folder, $backup_folder)) {
				wp_send_json_error('Failed to create backup of current app version.');
			}

			// Download and extract new version
			$remote_zip_url = DROIP_APPS_BASE_URL . $src;
			$file_name_new = uniqid('', true) . '.zip';
			$zip_file_path = HelperFunctions::download_zip_from_remote($remote_zip_url, $file_name_new);

			if (!$zip_file_path) {
				// Restore backup
				rename($backup_folder, $app_folder);
				wp_send_json_error('Failed to download the new app version.');
			}

			$zip = new \ZipArchive;
			$res = $zip->open($zip_file_path);

			if ($res !== TRUE) {
				// Restore backup
				rename($backup_folder, $app_folder);
				unlink($zip_file_path);
				wp_send_json_error('Failed to extract the zip file. Please ensure the file is valid.');
			}

			// Extract new version
			if (!$zip->extractTo($apps_folder)) {
				// Restore backup
				rename($backup_folder, $app_folder);
				$zip->close();
				unlink($zip_file_path);
				wp_send_json_error('Failed to extract new app version.');
			}

			$zip->close();
			unlink($zip_file_path);

			// Update version in installed apps array
			foreach ($apps as &$installed_app) {
				if (isset($installed_app['app_slug']) && $installed_app['app_slug'] === $app_slug) {
					$installed_app['version'] = $new_version;
					break;
				}
			}

			HelperFunctions::update_global_data_using_key('droip_installed_apps', $apps);

			// If everything succeeded, remove the backup
			HelperFunctions::delete_directory($backup_folder);

			wp_send_json_success('App installed successfully!');
		} catch (\Throwable $th) {
			// If backup exists, restore it
			if (isset($backup_folder) && isset($app_folder) && file_exists($backup_folder)) {
				if (file_exists($app_folder)) {
					HelperFunctions::delete_directory($app_folder);
				}
				rename($backup_folder, $app_folder);
			}

			// Clean up zip file if it exists
			if (isset($zip_file_path) && file_exists($zip_file_path)) {
				unlink($zip_file_path);
			}

			wp_send_json_error('An error occurred during the app update: ' . $th->getMessage());
		}

		die();
	}
}
