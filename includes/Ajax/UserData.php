<?php
/**
 * Manager User data
 *
 * @package droip
 */

namespace Droip\Ajax;

use Droip\HelperFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * UserData API Class
 */
class UserData {

	/**
	 * Save user controller data
	 *
	 * @return void wp_send_json
	 */
	public static function save_user_controller() {
		$user_id = get_current_user_id();
        //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$data = json_decode( stripslashes( $data ), true );
		if ( ! empty( $user_id ) ) {
			HelperFunctions::update_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY, $data );
			wp_send_json( array( 'status' => 'User controller data saved' ) );
		}
		die();
	}

	/**
	 * Save user saved data
	 *
	 * @return void wp_send_json
	 */
	public static function save_user_saved_data() {
		$user_id = get_current_user_id();
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$data = json_decode( stripslashes( $data ), true );
		if ( ! empty( $user_id ) ) {
			HelperFunctions::update_global_data_using_key( DROIP_USER_SAVED_DATA_META_KEY, $data );
			wp_send_json( array( 'status' => 'User saved data saved' ) );
		}
		die();
	}

	/**
	 * Save user custom fonts data
	 *
	 * @return void wp_send_json
	 */
	public static function save_user_custom_fonts_data()
	{
		$user_id = get_current_user_id();
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset($_POST['data']) ? $_POST['data'] : null;
		$data = json_decode(stripslashes($data), true);
		if (! empty($user_id)) {
			HelperFunctions::update_global_data_using_key(DROIP_USER_CUSTOM_FONTS_META_KEY, $data);
			wp_send_json(array('status' => 'User custom fonts data saved'));
		}
		die();
	}

	/**
	 * Save the updated font data in global data
	 * 
	 * @param object $font Font data.
	 */
	public static function save_the_font_global_data($font)
	{
		// first get the font data
		$custom_fonts = HelperFunctions::get_global_data_using_key(DROIP_USER_CUSTOM_FONTS_META_KEY);

		if (empty($custom_fonts)) {
			$custom_fonts = array();
		}

		if (isset($font['family'])) {
			// update the font data
			$custom_fonts[$font['family']] = $font;

			// save the font data
			HelperFunctions::update_global_data_using_key(DROIP_USER_CUSTOM_FONTS_META_KEY, $custom_fonts);
		}
	}

	/**
	 * Download google font in local
	 * 
	 * Requirements => must be a google font
	 * 
	 * @param object $font Font data.
	 * 
	 * @return object
	 */
	public static function make_google_font_offline()
	{
		$font = isset($_POST['font']) ? $_POST['font'] : null;
		$font = json_decode(stripslashes($font), true);

		$data = self::download_font_offline($font);

		if ($data['status']) {
			// save the font data in global data
			self::save_the_font_global_data($data['font']);
		}

		// return the response
		wp_send_json($data);
	}

	/**
	 * Download fonts
	 */
	private static function download_font_offline(&$font)
	{	
		if (empty($font['family']) || empty($font['fontUrl']) || strpos($font['fontUrl'], 'fonts.googleapis.com') === false) {
			return array(
				'font' => $font,
				'status' => false,
				'message' => "It's not a valid Google Font",
			);
		}

		$font_family_slug = sanitize_title_with_dashes($font['family']);
		$font_dir = WP_CONTENT_DIR . "/uploads/droip-fonts/{$font_family_slug}";

		$has_write_permission = HelperFunctions::get_upload_dir_has_write_permission();

		// Check if the directory has upload or write permission
		if (!$has_write_permission) {
			return array(
				'font' => $font,
				'status' => false,
				'message' => 'Font directory is not writable',
			);
		}

		try {
			if (!is_dir($font_dir)) {
				wp_mkdir_p($font_dir);
			}

			$css_file_path = $font_dir . "/{$font_family_slug}.css";

			// Already downloaded
			if (file_exists($css_file_path)) {
				return array(
					'font' => $font,
					'status' => false,
					'message' => 'Font already downloaded',
				);
			}

			$css = HelperFunctions::http_get($font['fontUrl']);

			if (!$css) {
				throw new Exception('Failed to fetch Google Fonts CSS');
			}

			if (empty($font['files']) || !is_array($font['files'])) {
				throw new Exception('No font files provided.');
			}
		  // update font-weight to 400 from regular
			if (isset($font['files']['regular'])) {
	    	$font['files']['400'] = $font['files']['regular'];
	    	unset($font['files']['regular']);
			}

			$local_css = '';
			$formats = [
				'woff2' => 'woff2',
				'woff' => 'woff',
				'ttf' => 'truetype',
				'otf' => 'opentype',
			];

			foreach ($font['files'] as $weight => $url) {
				$extension = pathinfo($url, PATHINFO_EXTENSION);
				$file_name = "{$weight}.{$extension}";
				$file_path = $font_dir . '/' . $file_name;

				$font_data = HelperFunctions::http_get($url);
				if (!$font_data) {
					throw new Exception("Failed to fetch font file");
				}

				file_put_contents($file_path, $font_data);

				$format = $formats[$extension] ?? 'truetype';
				$local_css .= "@font-face {
						font-family: '{$font['family']}';
						font-style: normal;
						font-weight: {$weight};
						font-display: swap;
						src: url('{$file_name}') format('{$format}');
				}\n";
			}

			if (empty($local_css)) {
				throw new Exception('No usable font files were downloaded.');
			}

			file_put_contents($css_file_path, $local_css);
			$font['localUrl'] = content_url("/uploads/droip-fonts/{$font_family_slug}/{$font_family_slug}.css");

			return array(
				'font' => $font,
				'status' => true,
				'message' => 'Font downloaded successfully',
			);
		} catch (Exception $e) {
			// Clean up on failure
			if (is_dir($font_dir)) {
				HelperFunctions::delete_directory($font_dir);
			}

			return array(
				'font' => $font,
				'status' => false,
				'message' => 'Error: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Delete google font from local
	 * 
	 * @param object $font Font data.
	 * 
	 * @return object
	 */
	public static function remove_google_font_offline()
	{
		$font = isset($_POST['font']) ? $_POST['font'] : null;
		$font = json_decode(stripslashes($font), true);

		$font_family_slug = sanitize_title_with_dashes(basename($font['family']));
		$font_dir = WP_CONTENT_DIR . "/uploads/droip-fonts/{$font_family_slug}";

		if (is_dir($font_dir)) {
			HelperFunctions::delete_directory($font_dir);
		}

		// remove the localUrl from font object and update the 
		unset($font['localUrl']);

		// save the font data in global data
		self::save_the_font_global_data($font);

		wp_send_json(array(
			'font' => $font,
			'status' => true,
			'message' => 'Font removed successfully',
		));

		die();
	}

	/**
	 * Get user controller data
	 *
	 * @return void wp_send_json
	 */
	public static function get_user_controller() {
		$control = HelperFunctions::get_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY );
		if ( $control ) {
			wp_send_json( $control );
		} else {
			wp_send_json( json_decode( '{}' ) );
		}
	}


	/**
	 * Get User Saved data
	 *
	 * @return void wp_send_json
	 */
	public static function get_user_saved_data() {
		$saved_data = HelperFunctions::get_global_data_using_key( DROIP_USER_SAVED_DATA_META_KEY );
		if(!$saved_data){
			$saved_data = array();
		}

		if(!isset($saved_data['variableData'])){
			$saved_data['variableData'] = self::initial_variable_data();
		}

		wp_send_json( $saved_data );
	}

	/**
	 * Get User login status
	 *
	 * @return void wp_send_json
	 */
	public static function check_user_login() {
		wp_send_json( is_user_logged_in() );
		die();
	}

	public static function initial_variable_data()
	{
		return json_decode('{"data":[]}', true);
	}
	
	public static function get_droip_variable_data()
	{
		$variables = array();
		$saved_data = HelperFunctions::get_global_data_using_key( DROIP_USER_SAVED_DATA_META_KEY );
		if($saved_data && isset($saved_data['variableData']) && count($saved_data['variableData']) > 0){
				$variables = $saved_data['variableData'];
		}else{
			$variables = self::initial_variable_data();
		}
		return $variables;
	}

	/**
	 * Get user custom fonts data
	 *
	 * @return void wp_send_json
	 */
	public static function get_user_custom_fonts_data() {
		$custom_fonts = HelperFunctions::get_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY );
		if ( $custom_fonts ) {
			wp_send_json( $custom_fonts );
		} else {
			wp_send_json( json_decode( '{}' ) );
		}
	}

	/**
	 * Get View port list.
	 * this method only for front end. not for editor. cause list data not completed data.
	 *
	 * @return array
	 */
	public static function get_view_port_list() {
		$control = HelperFunctions::get_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY );
		if ( ! $control || ! isset( $control['viewport'],  $control['viewport']['list'] ) ) {
			return self::sort_viewport_list( HelperFunctions::get_initial_view_ports()['list']);
		}
		return self::sort_viewport_list( $control['viewport']['list'] );
	}

	/**
	 * Sort view port list
	 *
	 * @param array $arr list of view port list.
	 * @return array
	 */
	private static function sort_viewport_list( $arr ) {
		$arr = (array) $arr;
		$list = array();
		array_multisort( array_column( $arr, 'minWidth' ), SORT_ASC, SORT_NUMERIC, $arr );
		$flag = false;
		foreach ( $arr as $key => $value ) {
			if ( $flag ) {
				$list[ $key ]         = $value;
				$list[ $key ]['type'] = 'min';
				$list[ $key ]['id']   = $key;
			}
			if ( $key === 'md' ) {
				$flag                 = true;
				$list[ $key ]         = $value;
				$list[ $key ]['id']   = $key;
				$list[ $key ]['type'] = 'max';
			}
		}
		$flag = false;
		foreach ( array_reverse( $arr ) as $key => $value ) {
			if ( $flag ) {
				$list[ $key ]         = $value;
				$list[ $key ]['type'] = 'max';
				$list[ $key ]['id']   = $key;
			}
			if ( $key === 'md' ) {
				$flag = true;
			}
		}
		return $list;
	}
}