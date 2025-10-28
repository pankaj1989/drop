<?php
/**
 * This class act like controller for Editor, Iframe, Frontend
 *
 * @package droip
 */

namespace Droip;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Frontend\Editor;
use Droip\Frontend\Iframe;
use Droip\Frontend\TheFrontend;
use Droip\Manager\TemplateRedirection;

/**
 * Frontend handler class
 */
class Frontend {

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		new TemplateRedirection();
		$this->plugin_editor_page_or_iframe_or_the_content();
	}


	/**
	 * This action trigger when post data loaded done
	 *
	 * @param object $post WordPress post object.
	 * @return void
	 */
	public function post_loaded( $post ) {
		if ( ! HelperFunctions::user_has_editor_access() ) {
      //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'droip' ) );
		}
	}

	/**
	 * Render the plugin editor page
	 *
	 * @return void
	 */
	public function plugin_editor_page_or_iframe_or_the_content() {
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action = HelperFunctions::sanitize_text( isset( $_GET['action'] ) ? $_GET['action'] : '' );
		$staging_version = isset( $_GET['staging_version'] ) ? intval(HelperFunctions::sanitize_text($_GET['staging_version'])) : false;
		$nonce = HelperFunctions::sanitize_text( isset( $_GET['nonce'] ) ? $_GET['nonce'] : 'false' );
		$preview_staging_version = ($staging_version && wp_verify_nonce( $nonce, 'droip_preview_staging_nonce' )) ? $staging_version : false;

		if ( $action === DROIP_EDITOR_ACTION ) {
			add_action( 'the_post', array( $this, 'post_loaded' ) );

      //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$load_for = HelperFunctions::sanitize_text( isset( $_GET['load_for'] ) ? $_GET['load_for'] : '' );
			if ( $load_for === 'droip-iframe' ) {
				new Iframe( $staging_version );
			} elseif ( $load_for === 'migration' ) {
				new TheFrontend( 'migration' );
			} else {
				new Editor();
			}
		} else {
			new TheFrontend(null, $preview_staging_version);
		}
	}
}