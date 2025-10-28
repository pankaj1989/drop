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

use Droip\Admin\AdminMenu;
use Droip\Admin\PostActions;
use Droip\Admin\EditWithButton;

/**
 * Droip Admin
 */
class Admin {
	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		// add_filter( 'plugin_action_links_' . DROIP_SLUG, array( $this, 'plugin_action_links' ) );

		new AdminMenu();
		new PostActions();
		new EditWithButton();
	}

	/**
	 * Plugin activation link
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions action list.
	 * @return array
	 */
	// public function plugin_action_links( $actions ) {
	// 	if ( ! HelperFunctions::is_pro_user() ) {
	// 		$actions['droip_pro_link'] =
	// 			'<a href="https://droip.com/pricing/?utm_source=droip_dashboard&utm_medium=wp_dashboard&utm_campaign=upgrade_pro&referrer=wordpress_dashboard" target="_blank">
	// 				<span style="color: #7338d6; font-weight: bold;">Upgrade Pro</span>
	// 			</a>';
	// 	}

	// 	return $actions;
	// }
}
