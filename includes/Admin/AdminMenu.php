<?php
/**
 * AdminMenu for wp admin menu and icon management
 *
 * @package droip
 */

namespace Droip\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;


/**
 * AdminMenu Class
 */
class AdminMenu {


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct() {
		if ( HelperFunctions::user_is( 'administrator' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'add_react_js_file_for_settings_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_script_text_domain' ), 100 );
		}
		add_action( 'admin_menu', array( $this, 'remove_custom_menu_from_sidebar' ) );
		add_action( 'admin_head', array( $this, 'add_droip_admin_styles' ) );
	}

	/**
	 * Droip Logo for droip menu
	 *
	 * @return void
	 */
	public static function add_droip_admin_styles() {
		echo wp_kses( ' <style> .dashicons-droip { background-image: url("' . DROIP_ASSETS_URL . 'images/droip-20X20.svg"); background-repeat: no-repeat; background-position: center;background-size: 20px 20px; }
		[href="admin.php?page=droip-get-pro"] {
			background: linear-gradient(93.07deg, rgba(78, 94, 218, 0.7) -4.71%, rgba(253, 98, 96, 0.7) 107.25%) !important;
			color: #fff !important;
			font-weight: bold !important;
			width: 125px;
		}
			#toplevel_page_droip .wp-first-item{
				display: none;
			}
		.wp-menu-open [href="admin.php?page=droip-get-pro"] {
			width: unset;
		}
		[href="admin.php?page=droip-get-pro"]:hover {
			background: linear-gradient(93.07deg, #4e5eda -4.71%, #fd6260 107.25%) !important;
			box-shadow: none !important;
		} </style> ', array( 'style' => array() ) );

		if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'droip') {
			global $submenu_file; // WordPress uses this to mark the active submenu
			$submenu_file = DROIP_APP_PREFIX . '-settings'; // Set active menu to "droip-settings"
		}
	}


	/**
	 * Remove custom menu from sidebar
	 *
	 * @return void
	 */
	public function remove_custom_menu_from_sidebar() {
		remove_menu_page( 'edit.php?post_type=droip_page' );
	}

	/**
	 * Load scritp text domain
	 *
	 * @return void
	 */
	public function load_script_text_domain() {
		HelperFunctions::load_script_text_domain( DROIP_APP_PREFIX . '-admin' );
	}

	/**
	 * Enqueue a script in the WordPress admin on edit.php.
	 *
	 * @param int $hook Hook suffix for the current admin page.
	 * @return void
	 */
	public function add_react_js_file_for_settings_page( $hook ) {
		if (
		'toplevel_page_' . DROIP_APP_PREFIX === $hook ||
		'droip_page_' . DROIP_APP_PREFIX . '-settings' === $hook ||
		'droip_page_' . DROIP_APP_PREFIX . '-form-data' === $hook 
		) {
			$version = DROIP_VERSION;
			wp_enqueue_script( DROIP_APP_PREFIX . '-admin', DROIP_ASSETS_URL . 'js/droip-admin.min.js', array( 'wp-i18n' ), $version, true );
			wp_enqueue_style( DROIP_APP_PREFIX . '-admin', DROIP_ASSETS_URL . 'css/droip-admin.min.css', null, $version );
		}
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_menu_page( DROIP_APP_NAME . ' - Settings', DROIP_APP_NAME, 'edit_posts', DROIP_APP_PREFIX, array( $this, 'plugin_page' ), 'dashicons-droip', 25 );

		add_submenu_page( DROIP_APP_PREFIX, DROIP_APP_NAME . ' - Settings', __( 'Settings', 'droip' ), 'edit_posts', DROIP_APP_PREFIX . '-settings', array( $this, 'plugin_page' ) );
		add_submenu_page( DROIP_APP_PREFIX, DROIP_APP_NAME . ' - Form data',  __( 'Form data', 'droip' ), 'edit_posts', DROIP_APP_PREFIX . '-form-data', array( $this, 'plugin_page' ) );
		// if ( ! HelperFunctions::is_pro_user() ) {
		// 	add_submenu_page( DROIP_APP_PREFIX, 'Upgrade Pro', '<span class="droip-get-pro-text">Upgrade Pro</span>', 'edit_posts', DROIP_APP_PREFIX . '-get-pro', array( $this, 'droip_get_pro_page' ) );
		// }
	}

	/**
	 * Render the menu page
	 *
	 * @return void
	 */
	public function plugin_page() {
		include_once __DIR__ . '/views/dashboard.php';
	}

	/**
	 * Get pro page
	 *
	 * @return void
	 */
	public function droip_get_pro_page() {
		include_once __DIR__ . '/views/get-pro.php';
	}
}