<?php
/**
 * Manage WordPress global data
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WordpressData API Class
 */
class WordpressData {

	/**
	 * Find Menu object by id
	 *
	 * @param array $menus wp menus.
	 * @param int   $id wp menu id.
	 * @return array
	 */
	private static function find_object_by_id( $menus, $id ) {
		return array_values( ( array_filter( $menus, fn ( $menu) => strval( $id ) === $menu->menu_item_parent ) ) );
	}

	/**
	 * Find parent submenu from all menus
	 *
	 * @param array $menus wp menus.
	 * @return array
	 */
	private static function find_parent_submenus( $menus ) {
		return array_values( ( array_filter( $menus, fn ( $menu) =>  '0' === $menu->menu_item_parent ) ) );
	}

	/**
	 * Get all submenus
	 *
	 * @param array $menu wp menu.
	 * @return array
	 */
	private static function get_submenus( $menu ) {
		$submenus = wp_get_nav_menu_items( $menu->term_id, array( 'order' => 'DESC' ) );
		if ( $submenus ) {
			$count = count( $submenus );
			for ( $i = 0; $i < $count; $i++ ) {
				$submenus[ $i ]->submenus = self::find_object_by_id( $submenus, $submenus[ $i ]->ID);
			}
		}

		$menu->submenus = self::find_parent_submenus( $submenus );

		return $menu;
	}

	/**
	 * Get WordPress menus data api
	 *
	 * @return void wp_send_json
	 */
	public static function get_wordpress_menus_data() {
		$menus = wp_get_nav_menus();
		$count = count( $menus );
		for ( $i = 0; $i < $count; $i++ ) {
			$menus[ $i ] = self::get_submenus( $menus[ $i ] );
		}

		wp_send_json( $menus );
	}

	/**
	 * Get WordPress single menu data api
	 *
	 * @param int     $menu_term_id single menu id.
	 * @param boolean $internal that means this method call from internally or not.
	 * @return void|object wp_send_json
	 */
	public static function get_wordpress_single_menu_data( $menu_term_id, $internal = false ) {
		$menu = wp_get_nav_menu_object( $menu_term_id );

		if ( $menu ) {
			$menu = self::get_submenus( $menu );
		}
		
		if ( $internal ) {
			return $menu;
		}

		wp_send_json( $menu );
	}

	/**
	 * Get WordPress author list
	 *
	 * @return void wp_send_json
	 */
	public static function get_author_list() {
		$authors = get_users(
			array(
				'capability__in' => array(
					'publish_posts',
				),
				'fields'         => array(
					'ID',
					'display_name',
					'user_nicename',
				),
			)
		);

		wp_send_json( $authors );
	}

	/**
	 * Get WordPress user list
	 *
	 * @return void wp_send_json
	 */
	public static function get_user_list()
	{
		$authors = get_users(
			array(
				'fields'         => array(
					'ID',
					'display_name',
					'user_nicename',
				),
			)
		);

		wp_send_json($authors);
	}

	/**
	 * Get WordPress category list
	 *
	 * @return void wp_send_json
	 */
	public static function get_category_list() {
		$categories = get_categories();
		wp_send_json( $categories );
	}

	/**
	 * Get WordPress post types data
	 *
	 * @return void wp_send_json
	 */
	public static function get_wordpress_post_types_data() {
		$args = array(
			'public'   => true
	 	);
		$post_types = get_post_types( $args, 'objects' );
		$types_arr = array();

		$excluded_post_types = array('attachment');

		$filtered_post_types = array_filter($post_types, function($post_type) use ($excluded_post_types) {
				return !in_array($post_type->name, $excluded_post_types);
		});

		foreach ( $filtered_post_types as $value ) {
			$types_arr[] = array(
				'title' => $value->label,
				'value' => $value->name,
			);
		}

		$types_arr = apply_filters( 'droip_post_types', $types_arr );

		wp_send_json( $types_arr );
	}
	
	/**
	 * Get WordPress post types data
	 *
	 * @return void wp_send_json
	 */
	public static function get_wordpress_comment_types_data() {
		global $wpdb;

		$comment_types = $wpdb->get_col( "SELECT DISTINCT(comment_type) FROM $wpdb->comments" );

		$types_arr = array();

		if (is_array($comment_types)) {
			foreach ( $comment_types as $type ) {
				$types_arr[] = array(
					'title' => ucfirst( preg_replace( '/[_ -]+/', ' ', $type ) ),
					'value' => $type,
				);
			}
		}

		wp_send_json( $types_arr );
	}

	/**
	 * Get WordPress role list
	 * 
	 * @return void wp_send_json
	 */

	public static function get_role_list() {
		global $wp_roles;

    // Make sure wp_roles is instantiated
    if ( ! isset( $wp_roles ) ) {
        $wp_roles = new WP_Roles();
    }

    // Get all roles
    $all_roles = $wp_roles->get_names();

    // Prepare the array of objects
    $roles_array = array();

    foreach ( $all_roles as $role_slug => $role_name ) {
        $roles_array[] = array(
            'value' => $role_slug,
            'title' => $role_name
        );
    }

		wp_send_json($roles_array);
	}

}