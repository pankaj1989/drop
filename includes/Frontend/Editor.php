<?php
/**
 * Editor controller
 *
 * @package droip
 */

namespace Droip\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax\WpAdmin;
use Droip\HelperFunctions;
use Droip\Staging;

/**
 * Editor class for controlling editor page template and hooks
 */
class Editor {


	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		$this->render_editor();
	}

	/**
	 * Render the editor page
	 *
	 * @return void
	 */
	public function render_editor() {
		if ( ! $this->has_editor_access() ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.NonSingularStringLiteralDomain
			wp_die( __( 'Sorry you are not allowed to access this page', 'droip' ), 403 );
		}
		add_filter( 'template_include', array( $this, 'load_my_editor_view' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_assets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_script_text_domain' ), 1 );

		// add_action( 'wp_enqueue_scripts', array( new HelperFunctions(), 'remove_theme_style' ), 100 );
		add_action('wp_enqueue_scripts', [new HelperFunctions(), 'dequeue_all_except_my_plugin'], 100); // Low priority to run late

		add_action( 'wp_footer', array( $this, 'add_before_body_tag_end' ) );

		if ( false === get_transient( 'droip_license_checked' ) ) {
			HelperFunctions::check_my_license();
			set_transient( 'droip_license_checked', true, DAY_IN_SECONDS );
		}
	}

	/**
	 * Load script text domain
	 *
	 * @return void
	 */
	public function load_script_text_domain() {
		HelperFunctions::load_script_text_domain( DROIP_APP_PREFIX . '-editor' );
	}

	/**
	 * Render Editor View from droip custom page template.
	 *
	 * @param string $template template name.
	 * @return string template location.
	 */
	public function load_my_editor_view($template)
	{
		$template = dirname(__FILE__) . '/views/editor.php';
		return $template;
	}

	/**
	 * Load Assets
	 *
	 * @return void
	 */
	public function load_assets() {
		HelperFunctions::remove_wp_assets();
		$version     = DROIP_VERSION;

		wp_enqueue_script( DROIP_APP_PREFIX . '-editor', DROIP_ASSETS_URL . 'js/droip-editor.min.js', array( 'wp-i18n' ), $version, true );
		wp_enqueue_script(DROIP_APP_PREFIX, DROIP_ASSETS_URL . 'js/droip.min.js', array('wp-i18n'), $version, true);

		$post_id = HelperFunctions::get_post_id_if_possible_from_url();
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$post_type = $post->post_type;

		$staging_version = isset( $_GET['staging_version'] ) ? intval(HelperFunctions::sanitize_text($_GET['staging_version'])) : false;
		if(!$staging_version) $staging_version = Staging::get_most_recent_stage_version($post_id, false);

		$post_url_arr = HelperFunctions::get_post_url_arr_from_post_id( $post_id );

		$staging_nonce = false;
		if($staging_version) {
			$staging_nonce = wp_create_nonce( 'droip_preview_staging_nonce' );
		}



		$wp_droip     = array(
			'ajaxUrl'          => $post_url_arr['ajax_url'],
			'restUrl'          => $post_url_arr['rest_url'],
			'nonce'            => $post_url_arr['nonce'],
			'siteUrl'          => $post_url_arr['site_url'],
			'adminUrl'         => $post_url_arr['admin_url'],
			'postId'           => $post_url_arr['post_id'],
			'corePluginUrl'    => $post_url_arr['core_plugin_url'],
			'postPreviewUrl'   => $post_url_arr['preview_url'],
			'editor_preview_token'   => $post_url_arr['editor_preview_token'],
			'uploadBaseUrl'    => wp_upload_dir()['baseurl'],
			'postTitle'        => get_the_title($post_id),
			'postType'         => $post_type,
			'droipWPDashboard' => $post_url_arr['site_url'] . '/wp-admin',
			'postWpEditUrl'    => get_edit_post_link( $post_url_arr['post_id'] ),
			'version'          => DROIP_VERSION,
			'defaultMenuID' => $this->get_defalut_menu_id(),//TODO: need to check this menu related code
			'current_user_id' => get_current_user_id(),
			'current_user_avatar_url' => get_avatar_url( get_current_user_id() ),
			'current_user_name' => get_the_author_meta( 'display_name', get_current_user_id() ), 
			'staging_version' => $staging_version,
			'staging_nonce' => $staging_nonce
		);
		wp_localize_script( DROIP_APP_PREFIX . '-editor', 'wp_droip', $wp_droip );
		wp_enqueue_style( DROIP_APP_PREFIX . '-droip', DROIP_ASSETS_URL . 'css/droip.min.css', null, $version );
		wp_enqueue_style( DROIP_APP_PREFIX . '-editor', DROIP_ASSETS_URL . 'css/droip-editor.min.css', null, $version );


		// Store the handles of your plugin's scripts and styles
    $editor_assets = array(
			'scripts' => array(DROIP_APP_PREFIX, DROIP_APP_PREFIX . '-editor'),//here we add droip.min.js DROIP_APP_PREFIX too. cause some theme prevent this script from loading.
			'styles' => array(DROIP_APP_PREFIX . '-droip', DROIP_APP_PREFIX . '-editor')
		);

		if(defined('TDE_APP_PREFIX')){
			//tutor support
			$editor_assets['scripts'][] = TDE_APP_PREFIX . '-tutor-droip-elements';
			$editor_assets['styles'][] = TDE_APP_PREFIX . '-tutor-droip-elements';
		}

		// Make the handles accessible globally
		global $droip_editor_assets;
		$droip_editor_assets = $editor_assets;
	}

	public function add_before_body_tag_end() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$s = '';
		$s      .= HelperFunctions::get_view_port_lists();
		echo $s;
	}
	private function get_defalut_menu_id(){
		$menus = wp_get_nav_menus();
		$first_menu_id = count($menus) > 0 ? $menus[0]->term_id : 0;
		$nav_locations = get_nav_menu_locations();
		$primary_menu_id = $nav_locations && isset($nav_locations['primary']) ? $nav_locations['primary'] : 0;
		$defaultMenuID =  $primary_menu_id == 0 ? $first_menu_id : $primary_menu_id;
		return $defaultMenuID;
	}

	/**
	 * Check the editor access
	 *
	 * @return boolean
	 */
	public function has_editor_access() {
		return HelperFunctions::user_has_editor_access();
	}
}