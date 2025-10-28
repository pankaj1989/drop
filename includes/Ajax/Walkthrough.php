<?php
/**
 * Walkthrough or onboarding
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;


/**
 * Walkthrough API Class
 */
class Walkthrough {

	/**
	 * Get walkthrough state
	 *
	 * @return void wp_send_json.
	 */
	public static function get_walkthrough_state() {
			$user_id   = get_current_user_id();
			$user_meta = get_user_meta( $user_id, DROIP_USER_WALKTHROUGH_SHOWN_META_KEY, true );

		if ( $user_meta ) {
			wp_send_json( true );
		} else {
			wp_send_json( false );
		}

			die();
	}

	/**
	 * Set walkthrough state
	 *
	 * @return void wp_send_json.
	 */
	public static function set_walkthrough_state() {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$walkthrough_state = HelperFunctions::sanitize_text( isset( $_POST['walkthrough-shown-state'] ) ? $_POST['walkthrough-shown-state'] : null );
			$user_id           = get_current_user_id();
			update_user_meta( $user_id, DROIP_USER_WALKTHROUGH_SHOWN_META_KEY, $walkthrough_state );

			wp_send_json( true );

			die();
	}
}
