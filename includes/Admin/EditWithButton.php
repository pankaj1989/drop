<?php
/**
 * EditWithButton show on post or custom post type edit page in wp admin page
 *
 * @package droip
 */

namespace Droip\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;
use Droip\Admin\AdminMenu;


/**
 * EditWithButton Class
 */
class EditWithButton {


	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public function __construct() {
		if ( HelperFunctions::user_has_editor_access() ) {
			add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'add_custom_button_click_functionality' ), 10, 1 );
			add_action( 'admin_bar_menu', array($this, 'add_edit_with_droip_button'), 100);
		}
	}
	
	function add_edit_with_droip_button($wp_admin_bar)
	{
		global $post;

		// Ensure we are on the front end and there's a valid post
		if (is_admin_bar_showing() && is_singular() && isset($post->ID)) {

			// Check if the editor mode is set to "Droip" and if the user has edit access
			$editor_mode = HelperFunctions::is_editor_mode_is_droip($post->ID);
			if ($editor_mode && HelperFunctions::user_has_post_edit_access($post->ID)) {

				// Fetch the post URLs using your existing helper functions
				$post_url_arr = HelperFunctions::get_post_url_arr_from_post_id($post->ID);

				// Add the custom button to the admin bar
				$wp_admin_bar->add_node(array(
					'id'   							  => 'edit_with_droip', // Unique ID for the button
					'title'							  => '<span class="dashicons-droip" style="padding: 10px;"></span>&nbsp Edit with ' . DROIP_APP_NAME, // Button content
					'href'  							=>  $post_url_arr['editor_url'], // URL to the XYZ editor
					'ajaxUrl' 						=> $post_url_arr['ajax_url'],
					'action'              => DROIP_EDITOR_ACTION,
					'isEditorModeIsDroip' => $editor_mode,
					'postId'              => get_the_ID(),
					'nonce'           		=> wp_create_nonce('wp_rest'),
				));
			}
		}
		AdminMenu::add_droip_admin_styles();
	}



	/**
	 * Filter Post Row Actions.
	 *
	 * Let the Document to filter the array of row action links on the Posts list table.
	 *
	 * @param array    $actions wp actions.
	 * @param \WP_Post $post wp post.
	 *
	 * @return array
	 */
	public function filter_post_row_actions( $actions, $post ) {
		$editor_mode = HelperFunctions::is_editor_mode_is_droip( $post->ID );
		if ( $editor_mode && HelperFunctions::user_has_post_edit_access( $post->ID ) ) {
			$editor_url                                 = HelperFunctions::get_post_url_arr_from_post_id( $post->ID )['editor_url'];
			$actions[ 'edit_with_' . DROIP_APP_PREFIX ] = sprintf(
				'<a href="%1$s" style="color:#9353FF;">%2$s</a>',
				$editor_url,
				'Edit with ' . DROIP_APP_NAME
			);
		}
		return $actions;
	}

	/**
	 * Add custom link in toolbar and click functionality
	 *
	 * @param string $hook wp hook or page name.
	 *
	 * @return void
	 */
	public function add_custom_button_click_functionality( $hook ) {
		global $post;

		if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
			$version      = DROIP_VERSION;
			$editor_mode  = HelperFunctions::is_editor_mode_is_droip( get_the_ID() );
			$post_url_arr = HelperFunctions::get_post_url_arr_from_post_id( get_the_ID() );
			wp_enqueue_script( 'custom_link', DROIP_ASSETS_URL . 'js/admin/custom-link-in-toolbar.js', array(), $version, true );
			wp_localize_script(
				'custom_link',
				DROIP_APP_PREFIX . '_admin',
				array(
					'postEditURL'         => $post_url_arr['editor_url'],
					'ajaxUrl'             => $post_url_arr['ajax_url'],
					'action'              => DROIP_EDITOR_ACTION,
					'isEditorModeIsDroip' => $editor_mode,
					'postId'              => get_the_ID(),
					'nonce'           		=> wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}
}
