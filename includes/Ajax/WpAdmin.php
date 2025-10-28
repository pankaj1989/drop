<?php
/**
 * WpAdmin dashboard api calls
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;


/**
 * WpAdmin API Class
 */
class WpAdmin {

	/**
	 * Save common data from dashboard
	 *
	 * @return void wp_send_json.
	 */
	public static function save_common_data() {
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$data = json_decode( stripslashes( $data ), true );

		$new_data = self::get_common_data(true);

		if ( isset( $data['license_key'], $data['license_key']['key'] ) ) {
			if($data['license_key']['key'] !== ''){
				$license_key             = $data['license_key']['key'];
				$license_info            = HelperFunctions::get_my_license_info( $license_key );
				$new_data['license_key'] = $license_info;
			}else{
				$new_data['license_key'] = ['key' => '', 'valid' => false];
			}
		}

		if ( isset( $data['json_upload'] ) ) {
			$new_data['json_upload'] = $data['json_upload'];
		}
		if ( isset( $data['mailchimp_api_key'] ) ) {
			$new_data['mailchimp_api_key'] = $data['mailchimp_api_key'];
		}
		if ( isset( $data['mailchimp_dc'] ) ) {
			$new_data['mailchimp_dc'] = $data['mailchimp_dc'];
		}
		if ( isset( $data['mailchimp_status'] ) ) {
			$new_data['mailchimp_status'] = $data['mailchimp_status'];
		}
		if ( isset( $data['pexels_api_key'] ) ) {
			$new_data['pexels_api_key'] = $data['pexels_api_key'];
		}
		if ( isset( $data['pexels_status'] ) ) {
			$new_data['pexels_status'] = $data['pexels_status'];
		}
		if ( isset( $data['svg_upload'] ) ) {
			$new_data['svg_upload'] = $data['svg_upload'];
		}
		if ( isset( $data['image_optimization'] ) ) {
			$new_data['image_optimization'] = $data['image_optimization'];
		}

		if ( isset( $data['unsplash_api_key'] ) ) {
			$new_data['unsplash_api_key'] = $data['unsplash_api_key'];
		}
		if ( isset( $data['unsplash_status'] ) ) {
			$new_data['unsplash_status'] = $data['unsplash_status'];
		}

		if ( isset( $data['reCAPTCHA_status'] ) ) {
			$new_data['reCAPTCHA_status'] = $data['reCAPTCHA_status'];
		}

		if (isset($data['smooth_scroll'])) {
			$new_data['smooth_scroll'] = $data['smooth_scroll'];
		}

		if ( isset( $data['recaptcha'] ) ) {
			// set data version wise, e.g:     { GRC_version: '2.0', '2.0:{}, '3.0:{} }.
			$new_data['recaptcha']['GRC_version']                           = $data['recaptcha']['GRC_version'];
			$new_data['recaptcha'][ $new_data['recaptcha']['GRC_version'] ] = $data['recaptcha'][ $data['recaptcha']['GRC_version'] ];
		}

		if ( isset( $data['chatGPT_api_key'] ) ) {
			$new_data['chatGPT_api_key'] = $data['chatGPT_api_key'];
		}
		if ( isset( $data['chatGPT_status'] ) ) {
			$new_data['chatGPT_status'] = $data['chatGPT_status'];
		}

		update_option( DROIP_WP_ADMIN_COMMON_DATA, $new_data, false );

		
		wp_send_json(
			array(
				'status' => 'success',
				'data'   => self::get_common_data(true),
			)
		);
	}

	/**
	 * Get common data
	 *
	 * @param boolean $inernal if this method call from intenally.
	 * @return void|array wp_send_json.
	 */
	public static function get_common_data( $inernal = false ) {
		$data                  = get_option( DROIP_WP_ADMIN_COMMON_DATA, array() );
		$data['post_max_size'] = ini_get( 'post_max_size' );
		$data['php_zip_ext_enabled'] = class_exists('ZipArchive');
		$data['trial_info'] = self::get_trial_info(true);
		if ( $inernal ) {
			return $data;
		} else {
			if(HelperFunctions::is_api_call_from_editor_preview()){
				wp_send_json( ['license_key' => $data['license_key']] );
			}
			wp_send_json( $data );
		}
	}

	/**
	 * Update common data license and editor type data.
	 *
	 * @return void wp_send_json_success.
	 */
	public static function update_license_validity() {
		$valid = filter_input( INPUT_POST, 'valid', FILTER_VALIDATE_BOOLEAN );
		$data = self::get_common_data(true);

		if ( isset( $data['license_key'] ) ) {
			$data['license_key']['valid'] = $valid;
			
			update_option( DROIP_WP_ADMIN_COMMON_DATA, $data, false );
			wp_send_json_success( true );
		}
	}
	
	/**
	 * manage trial info
	 *
	 * @return void wp_send_json_success.
	 */
	public static function update_trial_info() {
    $type = HelperFunctions::sanitize_text(isset($_POST['type']) ? $_POST['type'] : null);
		$trial_info = self::get_trial_info(true);
    if ($type) {
        // Retrieve trial info
        if ($type === 'start' && !$trial_info['started']) {
            $current_datetime = date('Y-m-d H:i:s');
            $end_datetime = date('Y-m-d H:i:s', strtotime('+90 days', strtotime($current_datetime)));
            $trial_info = [
                's' => base64_encode($current_datetime),
                'e'    => base64_encode($end_datetime), // Store encoded end date for security
            ];
            // Save trial data
            HelperFunctions::update_global_data_using_key('droip_trial_info', $trial_info);
        }
    }
    wp_send_json_success(['trial_info' => self::get_trial_info(true)]);
	}

	public static function get_trial_info($internal = true) {
    $trial_info = HelperFunctions::get_global_data_using_key('droip_trial_info');
    if (!$trial_info) {
        // Create a new trial if not found
        $current_datetime = date('Y-m-d H:i:s');
        $end_datetime = date('Y-m-d H:i:s', strtotime('+90 days', strtotime($current_datetime)));

        $trial_info = [
            's'         => base64_encode($current_datetime),
            'e'         => base64_encode($end_datetime),
						'end_date'  => $end_datetime,
            'expire_in' => 90, // Default 90 days
						'started' => false,
            'status'    => 'active',
        ];
    } else {
        // Decode stored dates
        $start_date = base64_decode($trial_info['s']);
        $end_date = base64_decode($trial_info['e']);

        // Validate date format
        if (!$start_date || !$end_date || strtotime($end_date) === false) {
            $status = 'expired';
            $expire_in = 0;
        } else {
            // Check if trial is expired
            $current_time = date('Y-m-d H:i:s');
            $time_diff = strtotime($end_date) - strtotime($current_time);
            $expire_in = max(0, floor($time_diff / (60 * 60 * 24))); // Convert seconds to days
            $status = ($time_diff > 0) ? 'active' : 'expired';
        }

        // Update trial info with status and remaining days
        $trial_info['status'] = $status;
        $trial_info['expire_in'] = $expire_in;
        $trial_info['started'] = true;
        $trial_info['end_date'] = $end_date;

				HelperFunctions::update_global_data_using_key('droip_trial_info', $trial_info);
    }

		if($internal){
			return $trial_info;
		}else{
			wp_send_json_success(['trial_info' => $trial_info]);
		}
	}
}