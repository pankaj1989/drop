<?php
/**
 * Helper class for droip project
 *
 * @package droip
 */

namespace Droip;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DateTime;
use Droip\Ajax\Collaboration\Collaboration;
use Droip\Ajax\Page;
use Droip\Staging;
use Droip\Ajax\Symbol;
use Droip\Ajax\UserData;
use Droip\Ajax\Users;
use Droip\Ajax\WpAdmin;
use Droip\API\ContentManager\ContentManagerHelper;
use Droip\Frontend\Preview\DataHelper;
use Droip\Frontend\Preview\Preview;
use WP_Post;
use WP_Query;
use WP_Term;
use WP_User;

/**
 * HelperFunctions Class
 */
class HelperFunctions {

	public static $custom_sections = [];
	/**
	 * Load assets for Editor
	 *
	 * @param string $for TheFrontend | null.
	 * @return false || array
	 */
	public static function is_droip_type_data( $post_id = false, $staging_version = false ) {
		if(!$post_id){
			$post_id = self::get_post_id_if_possible_from_url();
			if(!$post_id)return false;
		}

		if ( ! self::is_editor_mode_is_droip( $post_id ) ) {
			return false;
		}
		if($staging_version) {
			return Staging::get_page_staging_data($post_id, $staging_version);
		}

		$droip_data = get_post_meta( $post_id, DROIP_META_NAME_FOR_POST_DATA, true );
		if ( ! $droip_data ) {
			$droip_data           = array();
			$droip_data['blocks'] = null;
		}

		$styles = self::get_page_styleblocks( $post_id, $staging_version );

		$droip_data['styles'] = isset( $styles ) ? $styles : '';

		return $droip_data;
	}

	public static function get_post_id_if_possible_from_url() {
		if ( isset( $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_child_post'] ) ) {
			return $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_child_post'];
		}else if(isset( $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_parent_post'] ) && false){//disable content manager archive page logic
			return $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_parent_post'];
		}

		$post_id = get_the_ID();
		$post_id = self::sanitize_text( isset( $_GET['post_id'] ) ? $_GET['post_id'] : $post_id );
		$post_id = self::sanitize_text( isset( $_POST['post_id'] ) ? $_POST['post_id'] : $post_id );
		$post_id = self::sanitize_text( isset( $_GET['p'] ) ? $_GET['p'] : $post_id );

		return (int) $post_id;
	}

	/**
	 * Get author/user id if possible from url
	 */
	public static function get_user_id_if_possible_from_url()
	{
		if (isset($GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_user'])) {
			return $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_user'];
		}

		$user_id = '';
		if (isset($GLOBALS['wp']->query_vars['author_name'])) {
			$user = get_user_by('slug', $GLOBALS['wp']->query_vars['author_name']);
			if ($user instanceof WP_User) {
				$user_id = $user->ID;
			}
		} else {
			$user_id = get_current_user_id();
		}

		return (int) $user_id;
	}

	/**
	 * Get term or tag id if possible from url
	 */
	public static function get_term_id_if_possible_from_url()
	{
		if (isset($GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_term'])) {
			return $GLOBALS['wp']->query_vars[DROIP_CONTENT_MANAGER_PREFIX . '_term'];
		}

		// http://droip.test/tag/wp-tag-2/
		$term_id = get_queried_object_id();
		$term = null;

		if ($term_id) {
			$term = get_term($term_id);
		} else if (isset($GLOBALS['wp']->query_vars['tag'])) {
			$term = get_term_by('slug', $GLOBALS['wp']->query_vars['tag'], 'post_tag');
		}

		if ($term instanceof WP_Term) {
			$term_id = $term->term_id;
		}

		if (!$term_id) {
			// get all terms and return the first one
			$term = get_terms(array(
				'taxonomy' => 'category',
				'hide_empty' => false,
				'number' => 1,
			));

			$term_id = isset($term[0], $term[0]->term_id) ? $term[0]->term_id : 1;
		}

		return (int) $term_id;
	}

	public static function save_droip_data_to_db($post_id, $page_data, $is_staging = false){
		$version_where_saved = false;
		if($is_staging) {
			$data = Staging::save_page_staging_data_to_db($post_id, $page_data);
			$page_data = $data['page_data'];
			$version_where_saved = $data['version'];
		}
		
		if ( isset( $page_data['styles'] ) ) {
			foreach ( $page_data['styles'] as $key => $sb ) {
				if ( ( isset( $sb['isDefault'] ) && $sb['isDefault'] === true ) || ( isset( $sb['isGlobal'] ) && $sb['isGlobal'] === true ) ) {
					if(isset($sb['fromStage'])) unset($page_data['styles'][$key]['fromStage']);
					else unset($page_data['styles'][$key]);
				}
			}

			self::update_page_styleblocks( $post_id, $page_data['styles'] );
			unset( $page_data['styles'] );
		}

		if ( isset( $page_data['usedStyles'] ) ) {
			update_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $page_data['usedStyles'] );
			unset( $page_data['usedStyles'] );
		}

		if ( isset( $page_data['usedStyleIdsRandom'] ) ) {
			update_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $page_data['usedStyleIdsRandom'] );
			unset( $page_data['usedStyleIdsRandom'] );
		}

		if ( isset( $page_data['usedFonts'] ) ) {
			update_post_meta( $post_id, DROIP_META_NAME_FOR_USED_FONT_LIST, $page_data['usedFonts'] );
			unset( $page_data['usedFonts'] );
		}

		if ( isset( $page_data['customFonts'] ) ) {
			//save others data if isset. this is for template import
			$custom_fonts = self::get_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY );
			foreach ($page_data['customFonts'] as $key => $cf) {
				$custom_fonts[$key] = $cf;
			}
			self::update_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY, $custom_fonts );
			unset( $page_data['customFonts'] );
		}
		
		if ( isset( $page_data['viewportList'] ) ) {
			//save others data if isset. this is for template import
			$controller_data = self::get_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY );
			if ( ! $controller_data ) {
				$init = array( 
					'active' =>'md',
					'defaults'=> ["md", "tablet", "mobileLandscape", "mobile"],
					'list' => $page_data['viewportList'],
					'mdWidth'=> 1400,
					"scale"=>1,
					"width"=>2484,
					"zoom"=>1
				);
				$controller_data = array('viewport'=> $init);
			}else if(isset($controller_data['viewport'], $controller_data['viewport']['list'])){
				$controller_data['viewport']['list'] = $page_data['viewportList'];
			}

			HelperFunctions::update_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY, $controller_data );
			unset( $page_data['viewportList'] );
		}

		if ( isset( $page_data['blocks'] ) ) {
			update_post_meta( $post_id, DROIP_APP_PREFIX, array('blocks'=>$page_data['blocks']) );
			// $data = array(
			// 	'type'    => 'COLLABORATION_PAGE_DATA',
			// 	'payload' => array( 'data' => $page_data['blocks'] ),
			// );
			//Collaboration::save_action_to_db( 'post', $post_id, $data, 1 );
		}

		

		update_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, DROIP_APP_PREFIX );
		return $version_where_saved;
	}

	/**
	 * This function will return page style blocks from option meta and post meta
	 * post meta for migration and option meta for global style block
	 *
	 * @param int $post_id post id.
	 * @return object
	 */
	public static function get_page_styleblocks( $post_id, $stage_version = false ) {
		$random_style_blocks = get_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', true );
		$global_style_blocks = self::get_global_data_using_key( DROIP_GLOBAL_STYLE_BLOCK_META_KEY );

		$random_style_blocks = self::fix_duplicate_class_name_from_random_sbs($random_style_blocks, $global_style_blocks);

		$merged_style_blocks = array();
		if ( $random_style_blocks ) {
			$merged_style_blocks = array_merge( $merged_style_blocks, $random_style_blocks );
		}
		if ( $global_style_blocks ) {
			$merged_style_blocks = array_merge( $merged_style_blocks, $global_style_blocks );
		}

		$published_version = Staging::get_published_stage_version($post_id);
		if($published_version && $stage_version !== $published_version) {
			$staging_style_blocks = array();
			$meta_key = Staging::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $stage_version);
			$stage_style = get_post_meta( $post_id, $meta_key, true );
			if($stage_style)
				$staging_style_blocks = array_merge( $staging_style_blocks, $stage_style );

			$meta_key = $meta_key . '_random';
			$stage_style = get_post_meta( $post_id, $meta_key, true );
			if($stage_style)
				$staging_style_blocks = array_merge( $staging_style_blocks, $stage_style );

			if($stage_version)
				$merged_style_blocks = self::merge_style_blocks( $merged_style_blocks, $staging_style_blocks );
			else 
				$merged_style_blocks = self::merge_style_blocks( $staging_style_blocks, $merged_style_blocks );
		}

		return $merged_style_blocks;
	}

	public static function merge_style_blocks($a, $b)
	{
		$names_in_a = [];
    foreach ($a as $val) {
			if ( isset($val['name']) &&  is_string($val['name'])) {
				$names_in_a[strtolower($val['name'])] = true;
			}
    }
    $names_in_b = [];
    foreach ($b as $val) {
			if (isset($val['name']) && is_string($val['name'])) {
				$names_in_b[strtolower($val['name'])] = true;
			}
    }

		foreach ($b as $id_b => $value_b) {
			if (isset($a[$id_b])) {
				unset($a[$id_b]);
				if(is_string($value_b['name']) && isset($names_in_a[strtolower($value_b['name'])])) unset($names_in_a[strtolower($value_b['name'])]);
			}

			if(!is_string($value_b['name'])) continue;
			$b_name = strtolower($value_b['name']);

			foreach ($a as $id_a => $value_a) {
				if(isset($value_a['name']) && !is_string($value_a['name'])) continue;
				$a_name = strtolower($value_a['name']);
				if ($a_name === $b_name && $id_a !== $id_b) {
					$i = 1;
					while (isset($names_in_a[$b_name . '_' . $i]) || isset($names_in_b[$b_name . '_' . $i])) {
						$i++;
					}
					$b[$id_b]['name'] = $value_b['name'] . '_' . $i;
					unset($names_in_b[$b_name]);
					$names_in_b[strtolower($b[$id_b]['name'])] = true;

					foreach($b as $id => $value) {
						if(isset($value['name']) && is_array($value['name']) && in_array($value_b['name'], $value['name'])) {
							$b[$id]['name'] = array_map(fn($item) => $item === $value_b['name'] ? $b[$id_b]['name'] : $item, $value['name']);
						}
					}
					break;
				}
			}
		}
		return array_merge($a, $b);
	}


	private static function fix_duplicate_class_name_from_random_sbs( $random_style_blocks, $global_style_blocks ){
		$global_class_names = [];
		$random_class_names = [];
		if($global_style_blocks){
			foreach ($global_style_blocks as $key => $value) {
				if (isset($value['name']) && is_string($value['name'])) {
					$global_class_names[self::get_class_name_from_string($value['name'])] = true;
				}
			}
		}
		if($random_style_blocks){
			foreach ($random_style_blocks as $key => $value) {
				if (isset($value['name']) && is_string($value['name'])) {
					$random_class_names[self::get_class_name_from_string($value['name'])] = true;
				}
			}
		}

		$class_match = [];
		foreach ($random_class_names as $key => $value) {
			if( isset($global_class_names[$key]) ){
				$class_match[$key] = true;
			}
		}

		$class_match = self::check_or_generate_new_class_names( $class_match, $global_class_names, $random_class_names );

		if ( count($class_match) > 0) {
			foreach ($random_style_blocks as $key => $value) {
				if (isset($value['name']) && is_string($value['name']) ) {
					$class_name = self::get_class_name_from_string( $value['name'] );
					if (isset($class_match[$class_name])){
						$random_style_blocks[$key]['name'] = $class_match[$class_name];
					}
				} else if(isset($value['name']) && is_array($value['name'])){
					//array
					foreach ($value['name'] as $key2 => $v2) {
						$class_name = self::get_class_name_from_string( $v2 );
						if (isset($class_match[$class_name])){
							$random_style_blocks[$key]['name'][$key2] = $class_match[$class_name];
						}
					}
				}
			}
		}
		return $random_style_blocks;
	}

	private static function check_or_generate_new_class_names($class_match, $global_class_names, $random_class_names){
		foreach ($class_match as $key => $value) {
			$temp_class = $key;
			$found = true;
			while($found){
				if ( isset($global_class_names[$temp_class]) || isset($random_class_names[$temp_class]) ) {
					$temp_class = $temp_class . '-copy';
				} else {
					$found = false;
				}
			}
			$class_match[$key] = $temp_class;
		}
		return $class_match;
	}

	public static function get_class_name_from_string($s){
		$s = strtolower( str_replace( ' ', '-', $s ) );
		return $s;
	}

	public static function get_selector_from_sb_name($name){
		if(!$name) return '';
		$class_name = '';
		if (is_string($name) ) {
			$class_name = '.'.self::get_class_name_from_string( $name );
		} else {
			foreach ($name as $key2 => $cn) {
				$class_name .= '.'.self::get_class_name_from_string( $cn );
			}
		}
		return $class_name;
	}

	/**
	 * This function will update page style blocks into option meta and post meta
	 * post meta for migration and option meta for global style block
	 *
	 * @param int    $post_id post id.
	 * @param object $style_blocks styleblocks.
	 */
	public static function update_page_styleblocks( $post_id, $style_blocks ) {
		$prev_style_blocks = self::get_page_styleblocks( $post_id );
		$style_blocks      = array_merge( $prev_style_blocks, $style_blocks );

		$global_style_blocks = array();
		foreach ( $style_blocks as $key => $sb ) {
			if ( ( isset( $sb['isDefault'] ) && $sb['isDefault'] === true ) || ( isset( $sb['isGlobal'] ) && $sb['isGlobal'] === true ) ) {
				$global_style_blocks[ $sb['id'] ] = $sb;
				unset( $style_blocks[ $key ] );
			}
		}

		self::save_global_style_blocks( $global_style_blocks );
		self::save_random_style_blocks( $post_id, $style_blocks );
	}

	/**
	 * Save global style blocks in option table. Also save collaboration data.
	 *
	 * @param array $style //take styleblocs if isDefault and isGlobal key is true.
	 * @return void
	 */
	public static function save_global_style_blocks( $style ) {
		self::update_global_data_using_key( DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $style );
		// $data = array(
		// 	'type'    => 'COLLABORATION_UPDATE_GLOBAL_STYLE',
		// 	'payload' => array( 'styleBlock' => $style ),
		// );
		//Collaboration::save_action_to_db( 'global', 0, $data, 1 );
	}

	/**
	 * Save post related random style blocks in option table. Also save collaboration data.
	 *
	 * @param array $post_id //current post id.
	 * @param array $style //take styleblocs if not isDefault and isGlobal key is true.
	 * @return void
	 */
	public static function save_random_style_blocks( $post_id, $style ) {
		update_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $style );
		// $data = array(
		// 	'type'    => 'COLLABORATION_UPDATE_GLOBAL_STYLE',
		// 	'payload' => array( 'styleBlock' => $style ),
		// );
		//Collaboration::save_action_to_db( 'post', $post_id, $data, 1 );
	}

	/**
	 * No module import this method right now
	 *
	 * @return array
	 */
	public static function get_custom_code_block_element() {
		return array(
			'name'       => 'custom-code',
			'title'      => 'Code',
			'visibility' => true,
			'properties' => array(
				'tag'       => 'div',
				'content'   => '',
				'data-type' => 'code',
			),
			'styleIds'   => array(),
			'className'  => '',
			'id'         => DROIP_CLASS_PREFIX . uniqid(),
			'parentId'   => 'body',
		);
	}
	/**
	 * Get current editor mode is droip or others
	 *
	 * @param int $post_id post id.
	 * @return bool true if droip.
	 */
	public static function is_editor_mode_is_droip( $post_id ) {
		$editor_mode = get_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, true );
		if ( DROIP_APP_PREFIX === $editor_mode ) {
			return true;
		}
		return false;
	}

	/**
	 * Get post url arr from post id
	 * preview_url, iframe_url, post_url
	 *
	 * @param int $post_id post id.
	 * @return array
	 */
	public static function get_post_url_arr_from_post_id( $post_id ) {
		$post_perma_link = self::get_page_perma_url($post_id);
		$protocol = strpos(home_url(), 'https://') !== false ? 'https' : 'http';
		
		$iframe_url_params = array(
			'action'   => DROIP_EDITOR_ACTION,
			'load_for' => 'droip-iframe',
			'post_id'  => $post_id,
		);
		if(isset($_GET['editor-preview-token'])){
			$iframe_url_params['editor-preview-token'] = self::sanitize_text( $_GET['editor-preview-token']);
		}

		$preview_url = self::get_page_preview_url($post_id);

		$obj = array(
			'preview_url'     => $preview_url,
			'editor_url'      => add_query_arg(
				array(
					'action'  => DROIP_EDITOR_ACTION,
					// 'post_id' => $post_id,
				),
				$post_perma_link
			),
			'iframe_url'      => add_query_arg(
				$iframe_url_params,
				$post_perma_link
			),
			'post_url'        => $post_perma_link,
			'ajax_url'        => admin_url( 'admin-ajax.php', $protocol ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'editor_preview_token'           => isset($_GET['editor-preview-token']) ? self::sanitize_text( $_GET['editor-preview-token']):false,
			'site_url'        => get_site_url(),
			'admin_url'       => get_admin_url(),
			'core_plugin_url' => DROIP_CORE_PLUGIN_URL,
			'post_id'         => $post_id,
		);

		try {
			$rest_url = rest_url();
			$obj['rest_url'] = $rest_url;
		} catch (\Throwable $th) {
			$obj['rest_url'] = '';
		}

		if(HelperFunctions::is_api_call_from_editor_preview() && HelperFunctions::is_api_header_post_editor_preview_token_valid()){
			$headers = getallheaders();
			$obj['editor_url'] = add_query_arg(
				array(
				'editor-preview-token'  => isset($headers['Editor-Preview-Token']) ? $headers['Editor-Preview-Token'] : null,
				),
				$obj['editor_url']
			);
		}
		
		return $obj;
		/*
		NB: These are sent debugging purpose, please don't remove them without being sure
		'home_url' => home_url(),
		'origin' => get_http_origin(),
		'site_url' => site_url(),
		'host' => $_SERVER['SERVER_NAME'],
		'http_host' => $domainLink,
		*/
	}

	private static function get_page_preview_url($post_id){
		$post = get_post($post_id);
		if($post && $post->post_type === 'droip_template'){
			$conditions = get_post_meta( $post->ID, DROIP_APP_PREFIX.'_template_conditions', true );

			$d = self::get_collection_items_from_conditions($conditions);
			if( $d['type'] === 'post' && count($d['data']) > 0 ){
				return self::get_page_perma_url( $d['data'][0]['ID'] );
			}
			if( $d['type'] === 'user' && count($d['data']) > 0 ){
				return get_author_posts_url( $d['data'][0]['ID'] );
			}
			if( $d['type'] === 'term' && count($d['data']) > 0 ){
				return get_term_link( $d['data'][0]['ID'] );
			}
		}
		return self::get_page_perma_url($post_id);
	}
	private static function get_page_perma_url($post_id){
		$post_perma_link = get_permalink( $post_id );
		$protocol = strpos(home_url(), 'https://') !== false ? 'https' : 'http';
		if ( $protocol === 'https' ) {
			$post_perma_link = str_replace( 'http://', 'https://', $post_perma_link );
		} else {
			$post_perma_link = str_replace( 'https://', 'http://', $post_perma_link );
		}

		return $post_perma_link;
	}

	public static function get_collection_items_from_conditions($conditions, $query=''){
		$data = [];
		$type = 'post';
		$post_type = 'post';
		$role = '';

		if(is_array($conditions) && count($conditions) > 0) {
			if(isset($conditions[0]['type'])){
				$type = $conditions[0]['type'];
				
				if($type === 'post'){
					$post_type = $conditions[0]['post_type'];
				
					// if the conditions has from key then it is term. then it will be term type.
					if(isset($conditions[0]['from']) && $conditions[0]['from'] === 'term'){
						$type = 'term';
					}
				} 
				if($type === 'user'){
					$role = $conditions[0]['to'];
				}
			}else{
				//legacy support
				$post_type = $conditions[0]['category'];
			}
		}


		$numberposts = 20;
		$post_status = array('publish', 'draft', 'future');

		if ($type === 'post' && HelperFunctions::user_has_page_edit_access()) { // type will be wordpress post type
			$arg = array(
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'numberposts' => $numberposts,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			);
			if ($query) {
				$arg['s'] = $query;
			}
			$posts = get_posts($arg);
			foreach ($posts as $key => $post) {
				$data[] = array(
					'ID' => $post->ID,
					'title' => $post->post_title,
				);
			}
		}

		if($type === 'user' && HelperFunctions::user_has_page_edit_access()){
			// get all users
			$arg = array(
				'role' => $role === '*' ? '' : $role,
				'number' => $numberposts,
				'orderby' => 'ID',
				'order' => 'DESC',
			);

			if ($query) {
				$arg['search'] = '*' . $query . '*';
			}

			$users = get_users($arg);
			foreach ($users as $key => $user) {
				$data[] = array(
					'ID' => $user->ID,
					'title' => $user->display_name,
				);
			}
		}

		if($type === 'term' && HelperFunctions::user_has_page_edit_access()){
			// conditions 
			$taxonomy = [];

			foreach ($conditions as $key => $condition) {
				if($condition['from'] === 'term'){
					$taxonomy[] = $condition['where'];
				}
			}

			$arg = array(
				'taxonomy' => $taxonomy,
				'number' => $numberposts,
				'orderby' => 'ID',
				'order' => 'DESC',
			);

			if ($query) {
				$arg['search'] = $query;
			}

			$terms = get_terms($arg);

			foreach ($terms as $key => $term) {
				$data[] = array(
					'ID' => $term->term_id,
					'title' => $term->name,
				);
			}
		}

		return ['data'=> $data, 'type' => $type];
	}
	/**
	 * Get post content from content value. like => id, title, description, content
	 *
	 * @param string $content_value for post dynamic content.
	 * @param object $post post object.
	 *
	 * @return string
	 */
	public static function get_post_dynamic_content($content_value, $post = null, $meta_name = '')
	{
		if ( isset( $post ) && ! empty( $post ) ) {
			$post_id = $post->ID;
		}

		if ( empty( $post_id ) ) {
			$post_id = self::get_post_id_if_possible_from_url();
		}

		$content = null;

		switch ( $content_value ) {
			case 'post_id': {
					$content = $post_id;
					break;
			}

			case 'post_title': {
					$content = get_the_title( $post_id );
					break;
			}

			case 'post_excerpt': {

					// Excerpt length global variable is only for droip editor but not for the frontend.
					if (isset($GLOBALS['droip_post_excerpt_length']) && $GLOBALS['droip_post_excerpt_length'] > 0) {
						$content = wp_trim_words(get_the_excerpt($post_id), $GLOBALS['droip_post_excerpt_length'], '[...]');
					} else {
						$content = get_the_excerpt($post_id);
					}
					break;
				}

			case 'post_author': {
					$post    = get_post( $post_id );
					if(!$post)return "";
					$content = get_the_author_meta( 'display_name', $post->post_author );
					break;
			}

			case 'post_date': {
					$content = get_the_date('', $post_id);
					break;
			}

			case 'post_time': {
					$content = get_the_time( '', $post_id );
					break;
			}

			case 'post_content': {
				$content = self::retrieve_post_content($post_id);
				break;
			}

			case 'post_status': {
					$content = get_post_status( $post_id );
					break;
			}

			case 'featured_image': {
					$content = array(
						'wp_attachment_id' => get_post_thumbnail_id( $post_id ),
						'src' => get_the_post_thumbnail_url( $post_id )
					);
					break;
			}

			case 'site_name': {
					$content = get_bloginfo( 'name' );
					break;
			}
			case 'site_description': {
					$content = get_bloginfo( 'description' );
					break;
			}
			case 'site_url': {
					$content = get_site_url();
					break;
			}
			
			case 'site_logo': {
				$content = '';
				
				// Try site icon first
				$site_icon_id = get_option( 'site_icon' );
				if ( $site_icon_id ) {
						$content = wp_get_attachment_url( $site_icon_id );
				}
				
				// If no site icon, try get_site_icon_url()
				if ( ! $content ) {
						$content = get_site_icon_url( 512 );
				}
				
				// If still nothing, try custom logo
				if ( ! $content ) {
						$custom_logo_id = get_theme_mod( 'custom_logo' );
						if ( $custom_logo_id ) {
								$imgs = wp_get_attachment_image_src( $custom_logo_id, 'full' );
								if ( $imgs && isset( $imgs[0] ) ) {
										$content = $imgs[0];
								}
						}
				}
				
				break;
			}

			case 'author_profile_picture': {
					$post = get_post( $post_id );

				if ( ! empty( $post ) ) {
					$post_author = $post->post_author;
					$content     = get_avatar_url( $post_author );
				} else {
					$content = 'Something wrong!';
				}

				break;
			}

			case 'user_profile_picture': {
					$content = get_avatar_url( get_current_user_id() );
					break;
			}

			case 'post_page_link': {
					$url     = \get_permalink( $post_id );
					$content = ! empty( $url ) ? $url : '';
					break;
			}

			case 'author_posts_page_link': {
					$post    = \get_post( $post_id );
					$url     = \get_author_posts_url( $post->post_author );
					$content = ! empty( $url ) ? $url : '';
					break;
			}

			case 'post_meta': {
				if ( ! empty( $meta_name ) ) {
					$meta = get_post_meta( $post_id, $meta_name, true );

					// For now, only string is supported!
					if ( is_string( $meta ) ) {
						$content = $meta;
					}
				}

				break;
			}

			default: {
				$post    = get_post( $post_id );
				if (isset($post) && 0 !== strpos(DROIP_CONTENT_MANAGER_PREFIX, $post->post_type)) {
					$meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post->post_parent, $content_value);
					$content = get_post_meta($post->ID, $meta_key, true);
					$fields = ContentManagerHelper::get_post_type_custom_field_keys($post->post_parent);

					if(!$content && isset($fields[$content_value], $fields[$content_value]['default_value'])){
						$content = $fields[$content_value]['default_value'];
					}

					if(isset($fields[$content_value]) && $fields[$content_value]['type'] === 'date'){
						$content = self::format_date($content, $fields[$content_value]['default_format']);
					}
					if ( isset($fields[$content_value]['type']) && $fields[$content_value]['type'] === 'image' ) {
						$content = array(
							'wp_attachment_id' =>  $content['id'] ?? '',
							'src' => $content['url'] ?? '',
						);
					}
					if(isset($fields[$content_value]['type']) && $fields[$content_value]['type'] === 'time') {
						$time = "";

						if(isset($fields[$content_value], $fields[$content_value]['default_value']) && $fields[$content_value]['default_value']){
							$default_time = $fields[$content_value]['default_value'];
							$time = $default_time['value'] . ' '. $default_time['unit'];
						}

						$content = $content ? $content['value'] . ' '. $content['unit'] : $time;
					}

				} else {
					$content = 'Not Implemented';
				}

				break;
			}
		}

		return $content ? $content : '';
	}

	/**
	 * The function `get_user_dynamic_content` retrieves specific dynamic content related to a user based
	 * on the provided content value.
	 */
	
	 public static function get_user_dynamic_content($content_value, $user_id = null, $meta_name = '') {
    $content = '';

		 // Get the user by ID
		 $user = get_user_by('id', $user_id);

		 // fall back to the current user
		 if(empty($user)) {
			$user = get_user_by('id', get_current_user_id());
		 }

		
		if(empty($user)) {
			return $content;
		}
    
    // Switch statement to handle dynamic content based on the content_value
    switch ($content_value) {
        case 'display_name':
            return $user->display_name;
        
        case 'user_email':
            return $user->user_email;
        
        case 'user_nicename':
            return $user->user_nicename;
        
				case 'registered_date': {
					$date = new DateTime($user->user_registered);
					return $date->format('F j, Y');
				}

				case 'registered_time': {
					$date = new DateTime($user->user_registered);
					return $date->format('H:i:a');
				}
        
        case 'user_url':
					return get_author_posts_url($user->ID);
        
        case 'profile_image':
            return get_avatar_url($user->ID);

				case 'user_meta': {
					if ( ! empty( $meta_name ) ) {
						$meta = get_user_meta( $user->ID, $meta_name, true );
					
						if(is_string($meta)) {
							return $meta;
						}
						return '';
					}
				}

			case 'initials': {
					$first_name = isset($user->first_name) ? $user->first_name : '';
					$last_name = isset($user->last_name) ? $user->last_name : '';

					$initials = '';
					if ($first_name) {
						$initials .= strtoupper(substr($first_name, 0, 1));
					}

					if ($last_name) {
						$initials .= strtoupper(substr($last_name, 0, 1));
					}

					// if initials are lenght 1 or empty, then the first two letters of the username
					if (empty($initials) || strlen($initials) < 2) {
						$username = isset($user->user_login) ? $user->user_login : '';
						$initials = strtoupper(substr($username, 0, 2));
					}

					return $initials;
				}

			default:
            return '';
    }
	}

	public static function get_term_dynamic_content($content_value, $term_id = null, $meta_name = '') {
		$content = '';
		$term = get_term($term_id);

		if(empty($term)) {
			return $content;
		}

		switch ($content_value) {
			case 'name':
				return $term->name;
			
			case 'description':
				return $term->description;
			
			case 'slug':
				return $term->slug;
			
			case 'count':
				return $term->count;

			case 'meta': {
				if ( ! empty( $meta_name ) ) {
					$meta = get_term_meta( $term->term_id, $meta_name, true );
				
					if(is_string($meta)) {
						return $meta;
					}
					return '';
				}
			}
			
			default:
				return '';
		}
	}

	public static function retrieve_post_content($post_id) {
		$content = apply_filters( 'the_content', get_the_content(null, false, $post_id ) );

		$load_for = HelperFunctions::sanitize_text( isset( $_GET['load_for'] ) ? $_GET['load_for'] : '' );

		if ($load_for !== 'droip-iframe' && $droip_data = HelperFunctions::is_droip_type_data($post_id)) {
			$params = array( 
				'blocks' => $droip_data['blocks'],
				'style_blocks' => $droip_data['styles'],
				'root' => 'root',
				'post_id' => $post_id,
			 );
			$content =  apply_filters( 'the_content', HelperFunctions::get_html_using_preview_script( $params ));
		}

		return $content;
	}

	/**
	 * This methos is for if Theme enque some style and css then it will remove those styel and css codes.
	 *
	 * @return void
	 */
	public static function remove_theme_style() {
		$theme        = wp_get_theme();
		$parent_style = $theme->stylesheet . '-style';
		wp_dequeue_style( $parent_style );
		wp_deregister_style( $parent_style );
		wp_dequeue_style( $parent_style . '-css' );
		wp_deregister_style( $parent_style . '-css' );
	}

	public static function dequeue_all_except_my_plugin() {
    global $wp_scripts, $wp_styles, $droip_editor_assets;
    // Dequeue all scripts
    foreach ($wp_scripts->queue as $handle) {
        wp_dequeue_script($handle);
    }

    // Dequeue all styles
    foreach ($wp_styles->queue as $handle) {
        wp_dequeue_style($handle);
    }

    // Re-enqueue your plugin's scripts
    foreach ($droip_editor_assets['scripts'] as $script_handle) {
        wp_enqueue_script($script_handle);
    }

    // Re-enqueue your plugin's styles
		if(isset($droip_editor_assets['styles']) && $droip_editor_assets['styles']){
			foreach ($droip_editor_assets['styles'] as $style_handle) {
					wp_enqueue_style($style_handle);
			}
		}
	}

	/**
	 * Generate html using Preview script.
	 *
	 * @param array  $data blocks.
	 * @param array  $style_blocks style_blocks.
	 * @param string $root data root.
	 * @param int    $id if need prefix like symbol or popup post_id.
	 * @return string  the html string.
	 */
	public static function get_html_using_preview_script( array $params = [] ) {

		$blocks = $params['blocks'] ?? null;
    $style_blocks = $params['style_blocks'] ?? null;
    $root = $params['root'] ?? null;
    $options = $params['options'] ?? [];
    $post_id = $params['post_id'] ?? null;
    $get_style = $params['get_style'] ?? true;
    $get_variable = $params['get_variable'] ?? true;
    $get_fonts = $params['get_fonts'] ?? true;
    $should_take_app_script = $params['should_take_app_script'] ?? true;
    $prefix = $params['prefix'] ?? false;
    $get_all_style_forcefully_if_get_style_true = $params['get_all_style_forcefully_if_get_style_true'] ?? false;

		//set initial context data start
		if(!isset($options['post'])){
			$post = get_post(get_the_ID());
			if($post){
				$options['post'] = $post;
				$options['itemType'] = 'post';
			}
		}
		// if(!isset($options['user'])){
		// 	$user = get_user_by('id', get_current_user_id());
		// 	if($user){
		// 		$options['user'] = Users::get_format_single_user_data( $user );
		// 	}
		// }
		//set initial context data end
	
		$preview                = new Preview( $blocks, $style_blocks, $root, $post_id, $prefix );
		$html                   = $preview->getHtml( $options );// this method need to call first cause after that only used style block array construct.
		$only_used_style_blocks = $get_all_style_forcefully_if_get_style_true ? $style_blocks : $preview->get_only_used_style_blocks();
		$s = '';
		if($get_fonts ){
			$s                      .= $preview->getCustomFontsLinks();
		}

		if($get_variable){
			$variable_post_id = $post_id ? $post_id : HelperFunctions::get_post_id_if_possible_from_url();
			$variable_mode = Page::get_variable_mode($variable_post_id);
			$s 									 .= Preview::getVariableCssCode('global', ':root', $variable_mode);
		}
		if($get_style){
			//style will be false when it calls from collection single item. only first item will generate style. others item will be same.
			$s                   .= $preview->getStyleTag( $only_used_style_blocks );
		}

		$s .= $preview->get_interaction_set_as_initial_css();

		

		$s                     .= $html;
		$s                     .= $preview->getScriptTag($should_take_app_script);
		return $s;
	}

	/**
	 * Generate new id and html string for: symbol, collection etc.
	 *
	 * @param array  $data blocks.
	 * @param array  $style_blocks style_blocks.
	 * @param string $root data root.
	 */
	public static function rec_update_data_id_then_return_new_html( $data, $style_blocks, $root = 'body', $options = [], $get_style=true ) {
		$data_helper = new DataHelper();
		$data_helper->rec_update_data_id_to_new_id( $data, $style_blocks, $root, null );
		$data = $data_helper->temp_data;
		$style_blocks = $data_helper->temp_styles;
		$root = $data_helper->temp_ids[ $root ];

		$params = array( 
			'blocks' => $data,
			'style_blocks' => $style_blocks,
			'root' => $root,
			'post_id' => null,
			'options' => $options,
			'get_style' => $get_style,
			'get_all_style_forcefully_if_get_style_true' => true,//this will generate collection first item all style 
		 );

		return self::get_html_using_preview_script( $params );
	}

	// hook
	public static function droip_html_generator( $s, $post_id, $staging_version = false ) {
		$d = self::is_droip_type_data( $post_id, $staging_version );
		if($d){
			$params = array( 
				'blocks' => $d['blocks'],
				'style_blocks' => $d['styles'],
				'root' => 'root',
				'post_id' => $post_id,
			 );
			return self::get_html_using_preview_script( $params );
		}
		return false;
	}

	/**
	 * Find symbol for post id using condition
	 * it will find and return selected symbols html and css;
	 *
	 * @param string $type : post | user
	 * @param array $context : post object or user object
	 * @return symbol || bool(false)
	 */
	public static function find_template_for_this_context($type = 'post', $context=null)
	{
		$templates = Page::fetch_list(DROIP_APP_PREFIX . '_template', true,  array('publish'));
		if (!$templates || !$context) return false;
		foreach ($templates as $key => $template) {
			if (isset($template['conditions'])) {
				$conditions = $template['conditions'];
				if ($type === 'user') {
					if (self::check_all_conditions_for_this_user($context, $conditions)) {
						return $template;
					}
				} else if ($type === 'post') {
					if (self::check_all_conditions_for_this_post($context, $conditions)) {
						return $template;
					}
				}else if ($type === 'term'){					
					if (self::check_all_conditions_for_this_term($context, $conditions)) {
						return $template;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Generate popup html
	 *
	 * @return strint
	 */
	public static function get_page_popups_html() {
		global $post;
		if ( $post ) {
			$popups = self::get_page_popups();
			if ( count( $popups ) > 0 ) {
				$s = '';
				foreach ( $popups as $key => $popup ) {
					$params = array( 
						'blocks' => $popup['blocks'],
						'style_blocks' => $popup['styleBlocks'],
						'root' => $popup['root'],
						'post_id' => $popup['id'],
					);
					$s .= self::get_html_using_preview_script( $params );
				}
				return $s;
			}
		}
		return '';
	}

	/**
	 * Get Custom popups
	 * it will find and return selected popups;
	 *
	 * @return array
	 */
	public static function get_page_popups() {
		global $post;
		if ( $post ) {
			$popups = Page::fetch_list( DROIP_APP_PREFIX . '_popup', true, array( 'publish' ) );
			return self::find_popups_for_this_post( $popups, $post );
		}
		return array();
	}

	/**
	 * Find popups for post id using condition
	 * it will find and return selected popups arr;
	 *
	 * @param object $popups popup block object.
	 * @param object $post post object.
	 * @return popups || [];
	 */
	public static function find_popups_for_this_post( $popups, $post ) {
		$arr     = array();
		$post_id = $post->ID;
		foreach ( $popups as $key => $popup ) {
			$popup = self::format_single_popup_data_for_html_print( $popup );
			if ( self::check_popup_pagearr_logic( $popup, $post_id ) ) {
				$arr[] = $popup;
			}
		}
		return $arr;
	}

	private static function format_single_popup_data_for_html_print( $popup ) {
		$root = false;
		foreach ( $popup['blocks'] as $key2 => &$b ) {
			if ( 'root' === $b['parentId'] ) {
				$root = $b['id'];

				if ( isset( $b['properties']['attributes'] ) ) {
					$b['properties']['attributes']['popup-id'] = $popup['id'];
				} else {
					$b['properties']['attributes'] = array(
						'popup-id' => $popup['id'],
					);
				}
				$popup['root'] = $root;
			}
		}

		return $popup;
	}

	/**
	 * Check popup is active apply for this post or not.
	 *
	 * @param array $popup popup from Page::fetch_list.
	 * @param int   $post_id wp post id.
	 * @return bool
	 */
	private static function check_popup_pagearr_logic( $popup, $post_id ) {
		$root = $popup['root'];
		if ( $root && isset( $popup['blocks'][ $root ]['properties']['popup']['visibilityConditions'] ) ) {
			$conditions = $popup['blocks'][ $root ]['properties']['popup']['visibilityConditions'];
			$post       = get_post( $post_id );
			if ( self::check_all_conditions_for_this_post( $post, $conditions ) || in_array( $popup['id'], Preview::$only_used_popup_id_array, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check all conditon for this post
	 * This method will match all the condition and return true or false
	 * category = * || post_type
	 * taxonomy = 1=single post || * = all post || taxonomy slug
	 * apply[to] = * = all || id = post id || term id
	 * visibility = show || hide
	 *
	 * @param object $post post object.
	 * @param object $conditions symbol visibility condiotions object.
	 *
	 * @return Boolean
	 */
	public static function check_all_conditions_for_this_post($post, $conditions)
	{
		$show_flag = false;
		$hide_flag = false;

		foreach ($conditions as $key => $condition) {
			if(!isset($condition['from'])){
				$condition['from'] = 'post';
			}
			if($condition['from'] === 'term'){
				return false;
			}
			if (isset($condition['category'])) {
				//legacy
				if ($condition['category'] === '*') {
					$condition['type'] = '*';
				} else {
					$condition['post_type'] = $condition['category'];
					$condition['type'] = 'post';
				}
			}
			
			if(!isset($condition['visibility'])){
				$condition['visibility'] = 'show';
			}

			if ($condition['type'] === '*') {
				// Entire site
				$show_flag = $condition['visibility'] === 'show';
			} elseif ($condition['type'] === 'post') {
				if ($condition['post_type'] === $post->post_type) {
					// Post type related
					if ($condition['where'] == '*') {
						// All posts
						$show_flag = $condition['visibility'] === 'show';
					} elseif ($condition['where'] == 'single') {
						// Single post
						if ($condition['to'] === $post->ID) {
							$show_flag = $condition['visibility'] === 'show';
							$hide_flag = $condition['visibility'] === 'hide';
						}
					} else {
						// Taxonomy
						$taxonomy = $condition['where'];
						$term = $condition['to'];
						if ($term === '*') {
							// All terms
							$show_flag = $condition['visibility'] === 'show';
						} else {
							if (has_term($term, $taxonomy, $post->ID)) {
								$show_flag = $condition['visibility'] === 'show';
								$hide_flag = $condition['visibility'] === 'hide';
							}
						}
					}
				}
			}

			// If hide flag is set, stop and return false
			if ($hide_flag) {
				return false;
			}
		}
		return $show_flag;
	}
	
	
	public static function check_all_conditions_for_this_term($term, $conditions)
	{
		$show_flag = false;
		$hide_flag = false;
		foreach ($conditions as $key => $condition) {
			if(!isset($condition['visibility'])){
				$condition['visibility'] = 'show';
			}
			if(!isset($condition['from'])){
				$condition['from'] = 'post';
			}

			if($condition['from'] !== 'term'){
					return false;
			}

			if ($condition['type'] === '*') {
				// Entire site
				$show_flag = $condition['visibility'] === 'show';
			} elseif ($condition['type'] === 'post') {

				if ($condition['post_type'] === $term['post_type']) {
					// Post type related
					if ($condition['where'] == '*') {
						// All posts
						$show_flag = $condition['visibility'] === 'show';
					}  else {
						// Taxonomy
						$taxonomy = $condition['where'];
						$con_term = $condition['to'];
						if ($con_term === '*') {
							// All terms
							$show_flag = $condition['visibility'] === 'show';
						} else {
							if($term['taxonomy'] == $con_term){
								$show_flag = $condition['visibility'] === 'show';
								$hide_flag = $condition['visibility'] === 'hide';
							}
						}
					}
				}
			}

			// If hide flag is set, stop and return false
			if ($hide_flag) {
				return false;
			}
		}
		return $show_flag;
	}

	public static function check_all_conditions_for_this_user($user, $conditions)
	{
		$show_flag = false;
		$hide_flag = false;
		foreach ($conditions as $key => $condition) {

			if (isset($condition['category'])) {
				//legacy
				if ($condition['category'] === '*') {
					$condition['type'] = '*';
				} else {
					$condition['post_type'] = $condition['category'];
					$condition['type'] = 'post';
				}
			}
			
			if(!isset($condition['visibility'])){
				$condition['visibility'] = 'show';
			}

			if ($condition['type'] === '*') {
				// Entire site
				$show_flag = $condition['visibility'] === 'show';
			} elseif ($condition['type'] === 'user') {
				if ($condition['where'] === '*') {
					$show_flag = $condition['visibility'] === 'show';
				} elseif ($condition['where'] === 'role') {
					$user_role = $condition['to'];

					if($user_role === '*'){
						$show_flag = $condition['visibility'] === 'show';
					}else{
						$curr_user = $user;
						if (in_array($user_role, $curr_user['roles'])) {
							$show_flag = $condition['visibility'] === 'show';
						}
					}
				}
			}

			if ($hide_flag) {
				return false;
			}
		}

		return $show_flag;
	}


	private static function attribute_in_post_table($attr = '') {
		$post_table_attributes = array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'post_name',
			'post_type',
			'post_category',
			'term'
		);

		return in_array($attr, $post_table_attributes, true);
	}

	private static function sort_filters_by_relation($filter_items = array()) {
		$sorted_array = array();

		if (is_array($filter_items)) {
			array_walk($filter_items, function($item) use (&$sorted_array) {
				$relation = isset($item['relation']) ? $item['relation'] : 'OR';

				if (!isset($sorted_array[$relation])) {
					$sorted_array[$relation] = array();
				}

				unset($item['relation']);
		
				$sorted_array[$relation][] = $item;
    	});
		}

		return $sorted_array;
	}

	/**
	 * text, number, date/time, options, switch
	 */
	private static function post_table_filter_query($filter_item, $data_type) {
		$field_name = $filter_item['id'];
		$sorted_array = self::sort_filters_by_relation($filter_item['items']);

		$where_sql = '';

		array_walk($sorted_array, function($sorted_array_item, $condition) use ($data_type, $field_name, &$where_sql) {
			global $wpdb;

			$conditions = array();
			$column_name = "$wpdb->posts.$field_name";

			array_walk($sorted_array_item, function($filter_condition_item) use ($data_type, $column_name, &$conditions) {
				switch($data_type) {
					case 'text': {
						$conditions[] = PostsQueryUtils::post_table_text_query($column_name, $filter_condition_item['condition'], $filter_condition_item['value']);
						break;
					}

					// case 'date': {
					// 	$conditions[] = PostsQueryUtils::post_table_text_query($column_name, $filter_condition_item['condition'], $filter_condition_item['value']);
					// 	break;
					// }

					// case 'number': {
					// 	$conditions[] = PostsQueryUtils::post_table_number_query($column_name, $filter_condition_item['condition'], $filter_condition_item['value']);
					// 	break;
					// }

					// case 'option': {
					// 	$conditions[] = PostsQueryUtils::post_table_options_query($column_name, $filter_condition_item['condition'], $filter_condition_item['value']);
					// 	break;
					// }

					// case 'switch': {
					// 	$conditions[] = PostsQueryUtils::post_table_switch_query($column_name, $filter_condition_item['condition'], $filter_condition_item['value']);
					// 	break;
					// }

					default: {
						break;
					}
				}
			});
			
			$condition_sql = implode(" $condition ", $conditions);

			if('OR' === $condition) {
				$condition_sql = "($condition_sql)";
			}

			/**
			 * A query to 
			 * [x start with 'JoomShaper' or 'IcoFont' or 'Droip'
			 * and contains 'website' and ends with 'Ollyo']
			 * will be like below:
			 * 
			 * AND (x LIKE 'JoomShaper%' OR x LIKE 'IcoFont%' OR x LIKE 'Droip%') AND x LIKE '%website%' AND x LIKE '%Ollyo'
			 */
			$where_sql .= " AND $condition_sql";
		});

		add_filter('posts_where', function($where) use ($where_sql) {
			$where .= $where_sql;

			return $where;
		});
	}

	/**
	 * text, number, date/time, options, switch
	 * help: https://wordpress.stackexchange.com/questions/159426/meta-query-with-string-starting-like-pattern
	 */

	private static function post_meta_table_filter_query($filter_item, $key, $data_type) {
		$sorted_array = self::sort_filters_by_relation($filter_item['items']);
		$meta_query = array();

		array_walk($sorted_array, function($sorted_array_item, $condition) use (&$meta_query, $key, $data_type) {
			if (count($sorted_array_item) > 0) {
				$condition_arr = array('relation' => $condition);

				array_walk($sorted_array_item, function($filter_condition_item) use (&$condition_arr, $key, $data_type) {

					switch($data_type) {
						case 'text': {
							$condition_arr[] = PostsQueryUtils::post_meta_table_text_query($key, $filter_condition_item['condition'], $filter_condition_item['value']);
							break;
						}
	
						case 'date': {
							$condition_arr[] = PostsQueryUtils::post_meta_table_date_query($key, $filter_condition_item); // ['start-date' => '', 'end-date' => '']);
							break;
						}
	
						case 'number': {
							$condition_arr[] = PostsQueryUtils::post_meta_table_number_query($key, $filter_condition_item['condition'], $filter_condition_item['value']);
							break;
						}
	
						case 'option': {
							$condition_arr[] = PostsQueryUtils::post_meta_table_options_query($key, $filter_condition_item['condition'], $filter_condition_item['values']);
							break;
						}
	
						case 'switch': {
							$condition_arr[] = PostsQueryUtils::post_meta_table_switch_query($key, $filter_condition_item['condition']);
							break;
						}
	
						default: {
							break;
						}
					}
				});
				
				$meta_query[] = $condition_arr;
			}
		});

		return $meta_query;
	}

	/**
	 * filter query for reference table
	 * 
	 * @param object $filter_item filter item.
	 * @param string $key field meta key.
	 * @param array  $args args.
	 *
	 * @return array args
	 */

	private static function cm_reference_table_filter_query($filter_item, $key, $args)
	{
		global $wpdb;

		$sorted_array = self::sort_filters_by_relation($filter_item['items']);

		$post_ids_in = [];
		$post_ids_not_in = [];

		$has_in_condition = false;
		$has_not_in_condition = false;

		foreach ($sorted_array as $relation_str => $filters_by_relation) {
			foreach ($filters_by_relation as $filter_condition_item) {
				$condition = $filter_condition_item['condition'];
				$value = isset($filter_condition_item['value']) ? (int)$filter_condition_item['value'] : null;

				if (!$value) continue;

				$results = $wpdb->get_col($wpdb->prepare(
					"SELECT post_id FROM {$wpdb->prefix}droip_cm_reference WHERE field_meta_key = %s AND ref_post_id = %d",
					$key,
					$value
				));

				$results = array_map('intval', $results);

				if ($condition === 'in') {
					$has_in_condition = true;
					if ($relation_str === 'AND') {
						$post_ids_in[] = $results;
					} else {
						$post_ids_in = array_merge($post_ids_in, $results);
					}
				} elseif ($condition === 'not-in') {
					$has_not_in_condition = true;
					if ($relation_str === 'AND') {
						$post_ids_not_in[] = $results;
					} else {
						$post_ids_not_in = array_merge($post_ids_not_in, $results);
					}
				}
			}
		}

		// Handle post__in only if there was an 'in' condition
		if ($has_in_condition) {
			if (!empty($post_ids_in)) {
				$post_ids_in = is_array(reset($post_ids_in))
					? array_reduce($post_ids_in, 'array_intersect', array_shift($post_ids_in)) // ids-> [[1,2], [2,3]] -> array_reduce($ids, 'array_intersect', [1,2])
					: $post_ids_in;

				$args['post__in'] = isset($args['post__in'])
					? array_intersect($args['post__in'], $post_ids_in)
					: $post_ids_in;

				if (empty($args['post__in'])) {
					$args['post__in'] = [0];
				}
			} else {
				// 'in' condition was given, but returned nothing
				$args['post__in'] = [0];
			}
		}

		// Handle post__not_in normally
		if ($has_not_in_condition && !empty($post_ids_not_in)) {
			$post_ids_not_in = is_array(reset($post_ids_not_in))
				? array_merge(...$post_ids_not_in)
				: $post_ids_not_in;

			$args['post__not_in'] = isset($args['post__not_in'])
				? array_merge($args['post__not_in'], $post_ids_not_in)
				: $post_ids_not_in;
		}

		return $args;
	}

	/**
	 * handle legacy filter data
	 * 
	 * @param object $params filter array.
	 *
	 * @return array filter array
	 */
	public static function  handle_legacy_filter_to_new_filter($filters){
		$new_filters = array();

		if(is_array($filters)){
			foreach($filters as $key => $item){
				if(!isset($item['id']) && isset($item['type']) && $item['type']){
					switch($item['type']){
						case 'date': {
							$new_filters[] = array(
								'type' => 'post_date',
								'id' => 'post_date',
								'title' => 'Post Date',
								'items' => [array(
										'start-date' => isset($item['start-date']) ? $item['start-date'] : '',
										'end-date' => isset($item['end-date']) ? $item['end-date'] : '',
										'relation' => 'OR',
								)],
							);
	
							break;
						}
	
						case 'author': {
							$new_filters[] = array(
								'type' => 'post_author',
								'id' => 'post_author',
								'title' => 'Author',
								'items' => [array(
										'condition' => 'in',
										'values' => $item['values'],
										'relation' => 'OR',
								)],
							);
	
							break; 
						}
	
						case 'category': {
							$new_filters[] = array(
								'type' => 'post_category',
								'id' => 'post_category',
								'title' => 'Category',
								'items' => [array(
										'condition' => 'in',
										'values' => $item['values'],
										'relation' => 'OR',
								)],
							);
							break;
						}
	
						default: {
							break;
						}
					}
				}else{
					$new_filters[] = $item;
				}
			}
		}
		return $new_filters;
	 }

	/**
	 * Get dynamic collection data
	 *
	 * @param object $params query object.
	 *
	 * @return array post array
	 */
	public static function get_posts( $params ) {
		$name        = isset( $params['name'] ) ? $params['name'] : null;
		$sorting     = isset( $params['sorting'] ) ? $params['sorting'] : null;
		$filters     = isset( $params['filters'] ) ? $params['filters'] : null;
		$inherit     = (bool)($params['inherit'] ?? false);
		$related     = (bool)($params['related'] ?? false);
		$post_parent = (int)($params['post_parent'] ?? 0);
		$post_status = isset( $params['post_status'] ) ? $params['post_status'] : 'publish';
		$query = isset( $params['q'] ) ? $params['q'] : '';
		$IDs = isset( $params['IDs'] ) ? $params['IDs'] : [];
		$related_post_parent = isset($params['related_post_parent']) ? $params['related_post_parent'] : self::get_post_id_if_possible_from_url();

		if(!$query){
			$query = HelperFunctions::sanitize_text(isset($_REQUEST['q']) ?$_REQUEST['q']:'' );
		}

		// add new 
		$current_page = isset( $params['current_page'] ) ? $params['current_page'] : 1;
		$item_per_page = isset( $params['item_per_page'] ) ? $params['item_per_page'] : 3;
		$offset = isset( $params['offset'] ) ? $params['offset'] : 0;
		$context = isset($params['context']) ? $params['context'] : null;
		$tax_query = [
			'relation' => 'AND',
		];

		// Calculate the offset
		$offset = ($current_page - 1) * $item_per_page + $offset;

		$args        = array(
			'posts_per_page' => $item_per_page,
			'paged' => $current_page,
			'offset' => $offset,
			'post_type'   => $name,
			'suppress_filters' => false,
			'post_status' => $post_status,
			's' => $query,
		);
		
		if(count($IDs) > 0){
			$args['post__in'] = $IDs;
			unset($args['post_parent']);
			$args['post_type'] = 'any';
			$inherit = false;
			$post_parent = false;
		}	

		$filters  = self::handle_legacy_filter_to_new_filter($filters);

		if ( isset( $filters ) && is_array( $filters ) ) {
			foreach ( $filters as $filter_item ) {
				if(isset($filter_item['parent']) && $filter_item['parent']){
					$filter_item['id'] = 'term';
				}
				$field_name = isset( $filter_item['id'] ) ? $filter_item['id'] : '';

				if(!$field_name){
					continue;
				}

				if (self::attribute_in_post_table($filter_item['id']) && is_array($filter_item['items'])) {
			
					switch($field_name) {
						case 'post_excerpt':
						case 'post_content':
						case 'post_title': {
							self::post_table_filter_query($filter_item, 'text');
							break;
						}

						case 'post_date':
						case 'post_date_gmt': {
							/**
							 * $filter_item['items'] max contain one array. 
							 * in array may contain start-date, end-date
							 * Like: [{"start-date": "2020-01-01","end-date": "2020-01-02"}]
							 */
							
							if(isset($filter_item['items'], $filter_item['items'][0])) {
								$items = $filter_item['items'];
								$item = $items[0]; // Get first item in array.

								$date_query = array('column' => $field_name);
								$date_query['inclusive'] = true;

								if (!empty($item['start-date'])) {
									$date_query['after'] = $item['start-date'];
								}

								if (!empty($item['end-date'])) {
									$date_query['before'] = $item['end-date'];
								}
	
								$args['date_query'] = $date_query;
							}

							break;
						}

						case 'post_author': {
					
							/**
							 * $filter_item['items'] must not contain more than 2 array of conditions. 
							 * 1 array may contain 'in' conditions and another for 'not-in' conditions
							 * And values of 'in' and 'not-in' conditions should not collide
							 * Like: the condition should not be author 'in' [1, 2, 3] and 'not-in' [2, 4, 5]
							 */
							$items = $filter_item['items'];

							foreach($items as $item) {
								if ( isset( $item['condition'], $item['values'] ) && is_array( $item['values'] ) ) {
									if ( $item['condition'] === 'in' ) {
										$args['author__in'] = $item['values'];
									}
	
									if ( $item['condition'] === 'not-in' ) {
										$args['author__not_in'] = $item['values'];
									}
								}
							}

							break;
						}

						case 'term': {
							$items = $filter_item['items'];
					

							foreach($items as $item) {
								if ( isset( $item['condition'], $item['values'] ) && is_array( $item['values'] ) ) {

									if ( $item['condition'] === 'in' && !empty($item['values']) ) {
										array_push($tax_query, [
											'taxonomy' => $filter_item['type'],
											'field'    => 'term_id',
											'terms'    => $item['values'],
											'operator' => 'IN',
										]);
									}
	
									if ($item['condition'] === 'not-in' && !empty($item['values'])) {
											array_push($tax_query, [
											'taxonomy' => $filter_item['type'],
											'field'    => 'term_id',
											'terms'    => $item['values'],
											'operator' => 'NOT IN',
										]);
									}
								}
							}
							break;
						}
					}
				} else {
					$key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post_parent, $field_name);
					$data_type = $filter_item['type'] ?? 'text';

					if (isset($args['meta_query']) && !is_array($args['meta_query'])) {
						$args['meta_query'] = array();
					}

					switch($data_type) {
						default:
						case 'rich_text':
						case 'text':
						case 'phone':
						case 'url':
						case 'email': {
							$args['meta_query'][] = self::post_meta_table_filter_query($filter_item, $key, 'text');
							break;
						}

						case 'date': {
							$args['meta_query'][] = self::post_meta_table_filter_query($filter_item, $key, 'date');
							break;
						}

						case 'number': {
							$args['meta_query'][] = self::post_meta_table_filter_query($filter_item, $key, 'number');
							break;
						}

						case 'option': {
							$args['meta_query'][] = self::post_meta_table_filter_query($filter_item, $key, 'option');
							break;
						}

						case 'switch': {
							$args['meta_query'][] = self::post_meta_table_filter_query($filter_item, $key, 'switch');
							break;
						}

						case 'taxonomy': {
								if (empty($args['tax_query'])) {
									$tax_query = array(
										'relation' => 'AND'
									);
								}

								if (
									isset($filter_item['taxonomy'], $filter_item['terms']) &&
									is_array($filter_item['terms'])
								) {
									$operators = array('NOT IN', 'IN');

									$operator = 'IN';

									if (isset($filter_item['operator']) && in_array($filter_item['operator'], $operators, true)) {
										$operator = $filter_item['operator'];
									}

									array_push($tax_query, [
										'taxonomy' => $filter_item['taxonomy'],
										'field'    => 'term_id', // So far this is fixed
										'terms'    => $filter_item['terms'],
										'operator' => $operator,
									]);
								}
								break;
							}

						case 'author': {
							if ( isset( $filter_item['condition'], $filter_item['values'] ) && is_array( $filter_item['values'] ) ) {
								if ( $filter_item['condition'] === 'is-equal' ) {
									$args['author__in'] = $filter_item['values'];
								}

								if ( $filter_item['condition'] === 'is-not-equal' ) {
									$args['author__not_in'] = $filter_item['values'];
								}
							}
							break;
						}

						case 'multi-reference':
						case 'reference': {
								$args = self::cm_reference_table_filter_query($filter_item, $key, $args);
								break;
							}
					}
				}
			}
		}

		$args['tax_query'] = $tax_query;
		
		if ( isset( $sorting ) ) {
			// Set the sort order (ASC/DESC)
			if ( isset( $sorting['order'] ) ) {
				$args['order'] = $sorting['order'];
			}
	
			// Check if the name is set and contains 'droip_cm' && not include 'droip_cm_post_meta'
			if ( isset($name) && str_contains($name, DROIP_CONTENT_MANAGER_PREFIX) && !in_array($sorting['orderby'], DROIP_WORDPRESS_SORT_BY_OPTIONS)) {
				$args['orderby'] = 'meta_value'; // Use 'meta_value' or 'meta_value_num' as needed
				if ( isset($sorting['orderby']) && !empty($sorting['orderby']) ) {
					$args['meta_key'] = ContentManagerHelper::get_child_post_meta_key_using_field_id($post_parent,$sorting['orderby']);
				}
			} else {
				// For other cases, set the orderby based on the sorting parameter
				if ( isset( $sorting['orderby'] ) && !empty($sorting['orderby']) ) {
					$args['orderby'] = $sorting['orderby'];
				}
			}
		}

		if ( $inherit || $post_parent ) {
			//TODO: if terms page then show terms post only. like tag, category.
			$args['post_parent'] = $post_parent;
		}

		if(!empty($context) && $inherit){
			if($context['collectionType'] == 'user'){
				$args['author'] = $context['id'];
				unset($args['post_parent']);
			}
			if($context['collectionType'] == 'term'){
				$args['tax_query'] = array(
					array(
							'taxonomy' => $context['taxonomy'], // Replace 'category' with your taxonomy
							'field'    => 'term_id', // Use 'slug' if you want to query by slug
							'terms'    => $context['id'],       // Replace 123 with your term ID
					)
				);
				unset($args['post_parent']);
			}
		}

		if($related){
			$post = get_post($related_post_parent);

			if($post){
				$args['post_type'] = $post->post_type;
				if(str_contains($post->post_type, 'droip_cm_')){
					// filter related posts for content manager post
					$referenced_post_ids = self::get_referenced_post_ids($post_parent, $post);
					$args['post__in'] = array_map('intval', $referenced_post_ids);
				}else{
					$args['tax_query'] = self::buildTaxonomyForRelatedPosts($post);
					$args['post__not_in'] = [$post->ID];
				}
				
			}
		}
		// Run the WP_Query
		$query = new WP_Query( $args );
		$posts = $query->posts;
		

		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$image          = wp_get_attachment_image_src( $custom_logo_id, 'full' );

		$droip_content_manager_post_type_fields = array();
		
		if (isset($args['post_type']) && DROIP_CONTENT_MANAGER_PREFIX === $args['post_type']) {
			$post_parent = $args['post_parent'];
			$droip_content_manager_post_type_fields = ContentManagerHelper::get_post_type_custom_field_keys($post_parent);
		}

		foreach ( $posts as  &$post ) {
			if (
				DROIP_CONTENT_MANAGER_PREFIX === $post->post_type && is_array($droip_content_manager_post_type_fields)
				) {
					foreach($droip_content_manager_post_type_fields as $field_key) {
						$meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post->post_parent, $field_key['id']);
						$post->{$field_key['id']} = get_post_meta($post->ID, $meta_key, true);

						if (
							isset($field_key['type']) &&
							$field_key['type'] === 'image' &&
							$post->{$field_key['id']}
						) {
							$post->{$field_key['id']} = array(
								'wp_attachment_id' => $post->{$field_key['id']}['id'],
								'src' => $post->{$field_key['id']}['url'],
							);
						}
				}
			}


			$post->post_id = $post->ID;
			$post->author_profile_picture = array(
				'src' => get_avatar_url( $post->post_author )
			); 
			$post->post_author = get_the_author_meta( 'display_name', $post->post_author );
			$post->post_time = get_the_time( '', $post->ID );
			$post->featured_image = array(
				'wp_attachment_id' => get_post_thumbnail_id( $post->ID ),
				'src' => get_the_post_thumbnail_url( $post->ID )
			);
			$post->site_logo = isset( $image[0] ) ? $image[0] : '';
			$post->post_page_link = \get_permalink( $post->ID );
			$post->author_posts_page_link = \get_author_posts_url( $post->post_author );

			unset($post->post_excerpt);
		};

		 // Get total posts and total pages for pagination
		 $total_posts = $query->found_posts;
		 $total_pages = $query->max_num_pages;
 
		 // Calculate previous and next page numbers
		 $prev_page = ( $current_page > 1 ) ? $current_page - 1 : null;
		 $next_page = ( $current_page < $total_pages ) ? $current_page + 1 : null;
 
		 // Return the query and pagination info
		 return array(
				 'data'       => $posts,  
				 'pagination' => array(
						 'per_page'     => $item_per_page,
						 'current_page' => $current_page,
						 'total_pages'  => $total_pages,
						 'total_count'  => $total_posts,
						 'previous'     => $prev_page,
						 'next'         => $next_page,
				 ),
		 );
	}

	public static function get_referenced_post_ids($post_parent, $post) {
    global $wpdb;

    $allData   = ContentManagerHelper::get_post_type_custom_field_keys($post_parent);
    $post_ids  = [];

    foreach ($allData as $data) {
        if ($data && in_array($data['type'], ['reference', 'multi-reference'], true)) {
            $meta_key = 'droip_cm_field_' . $post_parent . '_' . $data['id'];

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ref_post_id FROM {$wpdb->prefix}droip_cm_reference WHERE field_meta_key = %s AND post_id = %d",
                    $meta_key,
                    $post->ID
                ),
                ARRAY_A
            );

            foreach ($results as $id) {
                $related_posts = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->prefix}droip_cm_reference WHERE field_meta_key = %s AND ref_post_id = %d",
                        $meta_key,
                        (int) $id['ref_post_id']
                    ),
                    ARRAY_A
                );

								foreach ($related_posts as $related) {
									$related_id = (int) $related['post_id'];

									if ($related_id !== (int) $post->ID) {
											$post_ids[] = $related_id;
									}
							}

                
            }
        }
    }
		$post_ids = array_values(array_unique($post_ids));

    return !empty($post_ids) ? $post_ids : [0];
}

	public static function get_terms($params)
	{
		$terms_array = [];
		$current_page = isset($params['current_page']) ? (int)$params['current_page'] : 1;
		$item_per_page = isset($params['item_per_page']) ? (int)$params['item_per_page'] : 3;
		$offset = isset($params['offset']) ? $params['offset'] : 0;

		if (isset($params['inherit']) && $params['inherit']) {
			$terms_array = get_the_terms($params['post_parent'], $params['taxonomy']);
			if (is_array($terms_array)) {
				// Convert objects to arrays
        $terms_array = array_map(function($term) {
            return $term->to_array();
        }, $terms_array);

        $calculated_offset = (($current_page - 1) * $item_per_page) + $offset;
        // Slice the array to simulate get_terms pagination
        $terms_array = array_slice($terms_array, $calculated_offset, $item_per_page);
			} else {
				$terms_array = array();
			}
		} else {
			$params['offset'] = (($current_page - 1) * $item_per_page) + $offset;
			$params['number'] = $item_per_page;

			$terms_array = get_terms($params);

			foreach ($terms_array as $key => &$item) {
				$item = $item->to_array();
			}
		}

		$total_terms = 0;

		if(isset($params['inherit']) && $params['inherit']){
			$t = get_the_terms($params['post_parent'], $params['taxonomy']);
			if($t){
				$total_terms = count($t);
			}else{
				$total_terms = wp_count_terms($params['taxonomy'], array_merge($params, ['offset' => 0, 'number' => 0]));
			}
		}

		$total_pages = ceil($total_terms / $item_per_page);
		$prev_page = ($current_page > 1) ? $current_page - 1 : null;
		$next_page = ($current_page < $total_pages) ? $current_page + 1 : null;

		return [
			'data' => $terms_array,
			'pagination' => [
				'per_page' => $item_per_page,
				'current_page' => $current_page,
				'total_pages' => $total_pages,
				'total_count' => $total_terms,
				'previous' => $prev_page,
				'next' => $next_page,
			],
		];
	}

	public static function buildTaxonomyForRelatedPosts(\WP_Post $post)
	{
		$taxonomies = get_object_taxonomies( $post->post_type );
			$taxQuery = [
					'relation' => 'OR',
			];

			foreach ($taxonomies as $taxonomy) {
					$taxQuery[] = [
							'taxonomy' => $taxonomy,
							'field'    => 'slug',
							'terms'    => array_filter(wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'slugs']), function ($termSlug) {
									return strtolower($termSlug) !== 'uncategorized';
							}),
					];
			}


			return $taxQuery;
	}


	/**
	 * Get dynamic collectiond data
	 *
	 * @param object $params query object.
	 *
	 * @return array post array
	 */
	public static function get_comments( $params ) {
		$parent = (int)($params['parent'] ?? 0);
		$post_id = (int)($params['post_id'] ?? 0);
		$type = ($params['type'] ?? 'comment');
		$sorting     = isset( $params['sorting'] ) ? $params['sorting'] : null;
		$filters      = isset( $params['filters'] ) ? $params['filters'] : null;
		// add new 
		$current_page = isset( $params['current_page'] ) ? $params['current_page'] : 1;
		$item_per_page = isset( $params['item_per_page'] ) ? $params['item_per_page'] : 3;
		$offset = isset( $params['offset'] ) ? $params['offset'] : 0;

		// Calculate the offset
		$offset_cal = ($current_page - 1) * $item_per_page + $offset;

		$args        = array(
			'parent' => $parent,
			'post_id'   => $post_id,
			'type'   => $type,
			'number' => $item_per_page,
			'paged' => $current_page,
			'offset' => $offset_cal,
			'count' => false
		);
		
		if ( isset( $filters ) && is_array( $filters ) ) {
			foreach ( $filters as $filter_item ) {
				$field_name = isset( $filter_item['id'] ) ? $filter_item['id'] : '';

				if(!$field_name){
					continue;
				}
				switch($field_name) {
					case 'comment_date':
					case 'comment_date_gmt':{
						/**
						 * $filter_item['items'] max contain one array. 
						 * in array may contain start-date, end-date
						 * Like: [{"start-date": "2020-01-01","end-date": "2020-01-02"}]
						 */
						if(isset($filter_item['items'], $filter_item['items'][0])) {
							$items = $filter_item['items'];
							$item = $items[0]; // Get first item in array.

							$date_query = array('column' => $field_name);
							$date_query['inclusive'] = true;

							if (!empty($item['start-date'])) {
								$date_query['after'] = $item['start-date'];
							}

							if (!empty($item['end-date'])) {
								$date_query['before'] = $item['end-date'];
							}

							$args['date_query'] = $date_query;
						}

						break;
					}

					case 'comment_author': {
						$items = $filter_item['items']; // $items['items'] max contain one array. 

						foreach($items as $item) {
							if ( isset( $item['condition'], $item['values'] ) && is_array( $item['values'] ) ) {
								if ( $item['condition'] === 'in' ) {
									$args['author__in'] = $item['values'];
								}

								if ( $item['condition'] === 'not-in' ) {
									$args['author__not_in'] = $item['values'];
								}
							}
						}

						break;
					}

					case 'comment_approved': {
						$items = $filter_item['items']; //  $items['items'] max contain one array. 

						foreach($items as $item) {
							if ( isset( $item['condition'], $item['values'] ) && is_array( $item['values'] ) ) {
								if ( $item['condition'] === 'in' ) {
									$args['status'] = $item['values'];
								}
							}
						}
					}
				}
			}
		}

		if(isset($sorting)){
			// Set the sort order (ASC/DESC)
			if ( isset( $sorting['order'] ) ) {
				$args['order'] = $sorting['order'];
			}

			if ( isset( $sorting['orderby'] ) && !empty($sorting['orderby']) ) {
				$args['orderby'] = $sorting['orderby'];
			}
		}
		
		$comments = get_comments($args);
		unset($args['number']);
		unset($args['paged']);

		if (is_array($comments)) {
			foreach($comments as &$comment) {
				$comment = (object)(array)$comment;

				$author_posts_page_link = $comment->comment_author_url;

				if (!$author_posts_page_link) {
					$author_posts_page_link = \get_author_posts_url($comment->user_id);
				}

				$comment->author_profile_picture = array(
					'src' => get_avatar_url( $comment->user_id )
				);
				$comment->author_posts_page_link = $author_posts_page_link;
			}
		}

		// Get total comments count
    $total_comments = get_comments( array_merge( $args, array( 'count' => true ) ) ) ;
		$total_comments = $total_comments - $offset;
		
		// Calculate total pages
		$total_pages = ceil( $total_comments / $item_per_page );

		// Calculate previous and next pages
    $prev_page = ( $current_page > 1 ) ? $current_page - 1 : null;
    $next_page = ( $current_page < $total_pages ) ? $current_page + 1 : null;

		// return $comments;
		return array(
			'data'       => $comments,  // Raw comments data
			'pagination' => array(
					'per_page'     => $item_per_page,
					'current_page' => $current_page,
					'total_pages'  => $total_pages,
					'total_count' => $total_comments,
					'previous'     => $prev_page,
					'next'         => $next_page,
			),
		);
	}


	/**
	 * Remove all default assets
	 *
	 * @return void
	 */
	public static function remove_wp_assets() {
		/*
		// Remove all WordPress actions
		// remove_all_actions('wp_head');
		// remove_all_actions('wp_print_styles');
		// remove_all_actions('wp_print_head_scripts');
		// remove_all_actions('wp_footer');

		// // Handle `wp_head`
		// add_action('wp_head', 'wp_enqueue_scripts', 1);
		// add_action('wp_head', 'wp_print_styles', 8);
		// add_action('wp_head', 'wp_print_head_scripts', 9);
		// add_action('wp_head', 'wp_site_icon');

		// // Handle `wp_footer`
		// add_action('wp_footer', 'wp_print_footer_scripts', 20);

		// // Handle `wp_enqueue_scripts`
		// remove_all_actions('wp_enqueue_scripts');

		// // Also remove all scripts hooked into after_wp_tiny_mce.
		// remove_all_actions('after_wp_tiny_mce');
		*/
		// remove admin-bar.
		add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
	}

	/**
	 * Get server protocol
	 * currently not in use
	 *
	 * @return string protocol name.
	 */
	public static function get_protocol() {
		$protocol = isset( $_SERVER['HTTPS'] ) ? 'https' : 'http';
		return $protocol;
	}

	/**
	 * Check if the current user has specific role ($role)
	 *
	 * @param string $role The role to check.
	 * @return boolean
	 */
	public static function user_is( $role ) {
		$user  = wp_get_current_user();
		$roles = $user->roles;

		return is_array( $roles ) && count( $roles ) && in_array( $role, $roles, true ) ? true : false;
	}

	/**
	 * Check if the user has access to edit/create specific/all post
	 *
	 * @param int $post_id post id.
	 * @return boolean
	 */
	public static function user_has_post_edit_access( $post_id = '' ) {
		return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
	}

	/**
	 * Check if the user has access to edit/create specific/all page
	 *
	 * @param int $page_id post id.
	 * @return boolean
	 */
	public static function user_has_page_edit_access( $page_id = '' ) {
		return $page_id ? current_user_can( 'edit_page', $page_id ) : current_user_can( 'edit_pages' );
	}

	/**
	 * Check if the user has access to editor
	 *
	 * @return boolean
	 */
	public static function user_has_editor_access() {
		if(isset($_GET['editor-preview-token'])){
			//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$editor_preview_token            = self::sanitize_text( isset( $_GET['editor-preview-token'] ) ? $_GET['editor-preview-token'] : '' );
			return self::is_post_editor_preview_token_valid($editor_preview_token);
		}
		return self::has_access(
			array(
				DROIP_ACCESS_LEVELS['FULL_ACCESS'],
				DROIP_ACCESS_LEVELS['CONTENT_ACCESS'],
				DROIP_ACCESS_LEVELS['VIEW_ACCESS'],
			)
		);
	}

	public static function is_api_call_from_editor_preview(){
		// Check the Editor-Preview-Token header
    $headers = getallheaders();
    $editor_preview_token = isset($headers['Editor-Preview-Token']) ? $headers['Editor-Preview-Token'] : null;
		if($editor_preview_token){
			return true;
		}
		return false;
	}

	public static function is_api_header_post_editor_preview_token_valid(){
		// Check the Editor-Preview-Token header
		$headers = getallheaders();
		$editor_preview_token = isset($headers['Editor-Preview-Token']) ? $headers['Editor-Preview-Token'] : null;
		if(HelperFunctions::is_post_editor_preview_token_valid( $editor_preview_token)){
			return true;
		}
		return false;
	}

	

	public static function is_post_editor_preview_token_valid($token){
		$status = HelperFunctions::get_global_data_using_key('droip_editor_read_only_access_status');
		if($status){
			$droip_editor_read_only_access_token = HelperFunctions::get_global_data_using_key('droip_editor_read_only_access_token');
			if($droip_editor_read_only_access_token && $droip_editor_read_only_access_token === $token){
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if the current user has specific access
	 *
	 * @param string|string[] $access_level The access level to check access.
	 */
	public static function has_access( $access_level ) {
		$user       = wp_get_current_user();
		$roles      = $user->roles;
		$has_access = false;

		if ( is_array( $access_level ) ) {
			foreach ( $roles as $role ) {
				$access = get_option( DROIP_APP_PREFIX . '_' . $role );
				if ( ! empty( $access ) && in_array( $access, $access_level, true ) ) {
					$has_access = true;
					break;
				}
			}
		} elseif ( is_string( $access_level ) ) {
			foreach ( $roles as $role ) {
				$access = get_option( DROIP_APP_PREFIX . '_' . $role );
				if ( ! empty( $access ) && $access === $access_level ) {
					$has_access = true;
					break;
				}
			}
		}

		return $has_access;
	}

	/**
	 * This method will collect license info from droip.com
	 *
	 * @param string $license_key user license key.
	 * @return array license info.
	 */
	public static function get_my_license_info( $license_key ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$info = self::http_get( DROIP_CORE_PLUGIN_URL . '/?license_key=' . $license_key . '&host=' . rawurlencode( self::sanitize_text( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null ) ) );
		$info = json_decode( $info, true );
		if ( $info && isset( $info['success'] ) ) {
			return $info['data'];
		} else {
			return array( 'key' => $license_key );
		}
	}

	/**
	 * HTTP get
	 *
	 * @param string $url api endpoint url.
	 * @return string|bool response.
	 */
	public static function http_get( $url, $args = array() ) {
		try {
			$response = wp_remote_get( $url, $args );

			if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
				$responseBody = $response['body'];

				return $responseBody;
			}

			return false;
		} catch( \Exception $ex ) {
			return false;
		}
	}

	/**
	 * HTTP post
	 *
	 * @param string $url api endpoint url.
	 * @param array  $options options.
	 * @return array|WP_Error response.
	 */
	public static function http_post( $url, $options ) {
		$res = wp_remote_post( $url, $options );
		return $res;
	}

	/**
	 * Text domain load hooks
	 *
	 * @param string $handle droip handle.
	 * @return void
	 */
	public static function load_script_text_domain( $handle ) {
		wp_set_script_translations( $handle, DROIP_TEXT_DOMAIN, DROIP_ROOT_PATH . 'languages/' );
	}

	/**
	 * Check and update user license
	 *
	 * @return array full license
	 */
	public static function check_my_license() {
		$data = WpAdmin::get_common_data(true);
		if ( $data && isset( $data['license_key'], $data['license_key']['key'] ) ) {
			$license_info = self::get_my_license_info( $data['license_key']['key'] );
		} else {
			$license_info = ['key' => '', 'valid' => false];
		}
		if ( $data ) {
			$data['license_key'] = $license_info;
		} else {
			$data = array( 'license_key' => $license_info );
		}
		update_option( DROIP_WP_ADMIN_COMMON_DATA, $data, false );
		return $data;
	}

	/**
	 * Delete droip related meta if a post is deleted.
	 *
	 * @param int $post_id post id.
	 * @return void
	 */
	public static function delete_post_with_meta_key( $post_id ) {
		delete_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS );
		delete_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random' );
		delete_post_meta( $post_id, DROIP_APP_PREFIX );
		delete_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE );
		delete_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY );
		delete_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random' );
	}
	/**
	 * Get the query string for the media type
	 *
	 * @param string $type media type.
	 * @return string The query string.
	 * @example  HelperFunctions::get_media_type_query_string('image') => 'image/jpeg, image/png, image/gif'
	 */
	public static function get_media_type_query_string( $type ) {
		return implode(
			', ',
			array_map(
				function ( $v ) {
					return "'" . $v . "'";
				},
				DROIP_SUPPORTED_MEDIA_TYPES[ $type ]
			)
		);
	}

	/**
	 * This is for component configuration/object javascript variable.
	 *
	 * @return string script tag
	 */
	public static function get_empty_variables() {
		$s  = "<script id='" . DROIP_APP_PREFIX . "-elements-property-empty-vars'>";
		$s .= 'var ' . DROIP_APP_PREFIX . 'Sliders = [], ' . DROIP_APP_PREFIX . 'Maps = [], ' . DROIP_APP_PREFIX . 'Lotties = [], ' . DROIP_APP_PREFIX . 'Popups = [], ' . DROIP_APP_PREFIX . 'Lightboxes = [], ' . DROIP_APP_PREFIX . 'ReCaptchas = [], ' . DROIP_APP_PREFIX . 'Videos = [], ' . DROIP_APP_PREFIX . 'Tabs = [], ' . DROIP_APP_PREFIX . 'Interactions = [], ' . DROIP_APP_PREFIX . 'Collections = [], ' . DROIP_APP_PREFIX . 'Dropdown = [], ' . DROIP_APP_PREFIX . 'Forms = [];';
		$s .= '</script>';
		return $s;
	}

	/**
	 * Check droip and droip pro is active or not
	 * 'droip/droip.php'
	 *
	 * @param string $plugin_main_file plugin main PHP file name.
	 * @return boolean
	 */
	public static function is_plugin_activate( $plugin_main_file ) {
		if ( in_array( $plugin_main_file, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			// plugin is activated.
			return true;
		}
		return false;
	}

	/**
	 * This function will verify nonce
	 * ACT like API calls auth middleware
	 *
	 * @param string $action ajax action name.
	 *
	 * @return void
	 */
	public static function verify_nonce( $action = -1 ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = self::sanitize_text( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : null );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( 'Not authorized' );
			exit;
		}
	}

	/**
	 * Unslash and sanitize text
	 *
	 * @param string $v text.
	 * @return string sanitized text.
	 */
	public static function sanitize_text( $v ) {
		return sanitize_text_field( wp_unslash( $v ) );
	}

	/**
	 * Get current WordPress session ID.
	 * This method generates a unique session ID if none exists.
	 *
	 * @return string Session ID.
	 */
	public static function get_session_id() {
		// Check if a session ID exists in a cookie.
		if (isset($_COOKIE['droip_session_id'])) {
			return sanitize_text_field($_COOKIE['droip_session_id']);
		}

		// Generate a new session ID.
		$session_id = wp_generate_uuid4();

		// Set the session ID in a cookie.
		setcookie('droip_session_id', $session_id, time() + (DAY_IN_SECONDS * 7), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

		return $session_id;
	}

	/**
	 * Get session data by key using WordPress transients.
	 *
	 * @param string $key The key of the session data to retrieve.
	 * @return mixed|null The session data if found, null otherwise.
	 */
	public static function get_session_data($key) {
		// Get the current session ID.
		$session_id = self::get_session_id();

		// Retrieve the session data.
		$session_data = get_transient('droip_session_' . $session_id);

		if (isset($session_data[$key])) {
			return $session_data[$key];
		}

		return null;
	}

	/**
	 * Add or update session data using WordPress transients.
	 *
	 * @param string $key The key of the session data.
	 * @param mixed $value The value of the session data.
	 * @return void
	 */
	public static function set_session_data($key, $value) {
		// Get the current session ID.
		$session_id = self::get_session_id();

		// Retrieve existing session data.
		$session_data = get_transient('droip_session_' . $session_id) ?: array();

		// Update the session data.
		$session_data[$key] = $value;

		// Save the updated session data with a 24-hour expiration time.
		set_transient('droip_session_' . $session_id, $session_data, DAY_IN_SECONDS);
	}

	/**
	 * Delete session data by key using WordPress transients.
	 *
	 * @param string $key The key of the session data to delete.
	 * @return void
	 */
	public static function delete_session_data($key) {
		// Get the current session ID.
		$session_id = self::get_session_id();

		// Retrieve existing session data.
		$session_data = get_transient('droip_session_' . $session_id);

		if (isset($session_data[$key])) {
			unset($session_data[$key]);

			// Save the updated session data or delete the transient if empty.
			if (!empty($session_data)) {
				set_transient('droip_session_' . $session_id, $session_data, DAY_IN_SECONDS);
			} else {
				delete_transient('droip_session_' . $session_id);
			}
		}
	}


	/**
	 * Is Pro user checking function.
	 *
	 * @return bool
	 */
	public static function is_pro_user() {
		$common_data = WpAdmin::get_common_data( true );
		
		$bool= isset( $common_data['license_key']['valid'] ) && boolval( $common_data['license_key']['valid'] ) === true;

		if(!$bool){
			if($common_data['trial_info']['status'] === 'active'){
				$bool = true;
			}
		}
		return $bool;
	}

	/**
	 * Store Error log to droip server
	 *
	 * @param string $error_text the error text created in PHP.
	 *
	 * @return void
	 */
	public static function store_error_log( $error_text ) {
		$droip_version = DROIP_VERSION;

		self::http_get(
			DROIP_CORE_PLUGIN_URL . "?log_data=error&version=$droip_version&error_type=php&error_text=$error_text"
		);
	}

	/**
	 * Get all view port lists
	 *
	 * @return string viewports list variable in script markup.
	 */
	public static function get_view_port_lists() {
		$s       = '';
		$list = UserData::get_view_port_list();
		if ( $list ) {
			$s .= "<script id='" . DROIP_APP_PREFIX . "-viewport-lists'>";
			$s .= 'var ' . DROIP_APP_PREFIX . 'Viewports = ' . wp_json_encode( $list ) . ';';
			$s .= '</script>';
		}
		return $s;
	}

	/**
	 * Get all css variables
	 *
	 * @return string variables in script markup.
	 */
	public static function get_droip_css_variables_data() {
		$s       = '';
		$variableData = UserData::get_droip_variable_data();
		if ( $variableData ) {
			$s .= "<script id='" . DROIP_APP_PREFIX . "-variable-lists'>";
			$s .= 'var ' . DROIP_APP_PREFIX . 'CSSVariable = ' . wp_json_encode( $variableData ) . ';';
			$s .= '</script>';
		}
		return $s;
	}

	/**
	 * Get smooth scroll script
	 *
	 * @return string script markup.
	 */
	public static function get_smooth_scroll_script()
	{
		$common_data = WpAdmin::get_common_data(true);
		$smooth_scroll_enabled = isset($common_data['smooth_scroll'], $common_data['smooth_scroll']['enabled']) ? $common_data['smooth_scroll']['enabled'] : false;

		$smooth_scroll_value = isset($common_data['smooth_scroll'], $common_data['smooth_scroll']['value']) ? $common_data['smooth_scroll']['value'] : 1;

		$min_click_duration = 100;
		$max_click_duration = 1000;
		$normal_smooth = ($smooth_scroll_value - 1) / 199;
		$click_duration = $min_click_duration + $normal_smooth * ($max_click_duration - $min_click_duration);
		$max_wheel_speed = 50;
		$min_wheel_speed = 1;
		$wheel_speed = ((200 - $smooth_scroll_value) / 199) * ($max_wheel_speed - $min_wheel_speed) + $min_wheel_speed;

		$s = '';


		if ($smooth_scroll_enabled) {
			$s .= "<script id='" . DROIP_APP_PREFIX . "-smooth-scroll'>";
			$s .=
				"function init() {
			  SmoothOnSectionClick(document, $click_duration);
  			SmoothOnScroll(document, $wheel_speed, 12);
		 }
		 
			function SmoothOnSectionClick(target = document, duration = 1000) {
				const easeInOutCubic = (t) =>
					t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

				const smoothScrollOnSectionClick = (targetElement, duration) => {
					const targetPosition =
						targetElement.getBoundingClientRect().top + window.scrollY;
					const startPosition = window.scrollY;
					const distance = targetPosition - startPosition;
					const startTime = performance.now();

					const animateScroll = (currentTime) => {
						const elapsedTime = currentTime - startTime;
						const progress = Math.min(elapsedTime / duration, 1);

						window.scrollTo({
							top: startPosition + distance * easeInOutCubic(progress),
							behavior: \"instant\",
						});
						// The pos variable value need to be changed to the new scroll position

						if (elapsedTime < duration) {
							requestAnimationFrame(animateScroll);
						}
					};

					requestAnimationFrame(animateScroll);
				};
				target.querySelectorAll('a[href^=\"#\"]').forEach((anchor) => {
					anchor.addEventListener(\"click\", (e) => {
						e.preventDefault();

						const targetElement = target.querySelector(anchor.getAttribute(\"href\"));
						if (targetElement) smoothScrollOnSectionClick(targetElement, duration);
					});
				});
			}
		 
			function SmoothOnScroll(target = document, speed = 50, smooth = 12) {
				if (target === document) {
					target =
						document.scrollingElement ||
						document.documentElement ||
						document.body.parentNode ||
						document.body;
				}
				let moving = false;
				let pos = target.scrollTop; // THE pos Variable

				const frame =
					target === document.body && document.documentElement
						? document.documentElement
						: target;

				const scrolled = (e) => {
					if(!moving) pos = target.scrollTop;
					if (!moving && e.ctrlKey && !(e.key === \"ArrowDown\" || e.key === \"ArrowUp\"))
						return;

					e.preventDefault();

					const delta = normalizeWheelDelta(e);
					pos += -delta * speed;
					pos = Math.max(0, Math.min(pos, target.scrollHeight - frame.clientHeight));

					if (!moving) update();
				};

				const normalizeWheelDelta = (e) => {
					const { detail, wheelDelta } = e;
					if (detail) {
						if (wheelDelta) {
							return (wheelDelta / detail / 40) * (detail > 0 ? 1 : -1);
						}
						return -detail / 3;
					}
					return wheelDelta / 120;
				};

				const update = () => {
					moving = true;
					const delta = (pos - target.scrollTop) / smooth;
					target.scrollTop += delta > 0 ? Math.ceil(delta) : Math.floor(delta);

					if (Math.abs(delta) > 0.5) {
						requestAnimationFrame(update);
					} else {
						moving = false;
					}
				};

				const updatePostionOnKeyPressScroll = (e) => {
					if (e.key === \"ArrowDown\" || e.key === \"ArrowUp\") {
						if (moving) e.preventDefault();
						else if (e.metaKey) {
							pos = e.key === \"ArrowDown\" ? target.offsetHeight : 0;
						} else {
							pos += e.key === \"ArrowDown\" ? 40 : -40;
						}
					}
				};

				target.addEventListener(\"mousewheel\", scrolled, { passive: false });
				target.addEventListener(\"DOMMouseScroll\", scrolled, { passive: false });
				document.addEventListener(\"keydown\", updatePostionOnKeyPressScroll);
			}
		 
		 window.addEventListener(\"DOMContentLoaded\", () => {
			 init();
		 });";
			$s .= '</script>';
		}

		return $s;
	}

	/**
	 * Format the date with date format
	 *
	 * @return string
	 */
public static function format_date($date, $format)
	{
		if ($date && $format) {
			$date_formats_arr = [
				'DD/MM/YYYY' => 'd/m/Y',
				'DD-MM-YYYY' => 'd-m-Y',
				'DD.MM.YYYY' => 'd.m.Y',
				'MM/DD/YYYY' => 'm/d/Y',
				'MM-DD-YYYY' => 'm-d-Y',
				'MM.DD.YYYY' => 'm.d.Y',
				'MMMM DD, YYYY' => 'F j, Y',
				'MMM DD, YYYY' => 'M j, Y',
				'YYYY-MM-DD' => 'Y-m-d',
				'YYYY/MM/DD' => 'Y/m/d',
				'YY.MM.DD' => 'y.m.d',
				'YY/MM/DD' => 'y/m/d',
				'YY-MM-DD' => 'y-m-d',
			];

			$date = new \DateTime($date);
			$date = $date->format($date_formats_arr[$format]);

			return $date;
		}

		return $date;
	}

	/**
	 * Get single post if has a droip type post
	 *
	 * @return object|bool
	 */
	public static function get_last_edited_droip_editor_type_page(){
		$args = array(
			'post_type'      => 'page', // Change to 'post' if you want to search for posts
			'post_status'    => ['publish', 'draft'],
			'numberposts'    => 1,      // Number of results to retrieve (change as needed)
			'meta_key'       => 'droip_editor_mode',
			'meta_value'     => 'droip',
			'orderby'        => 'modified', // Order by post date
    	'order'          => 'DESC',  // Sort in descending order
		);
		
		$pages = get_posts($args);
		if(count($pages) > 0){
			return $pages[0];
		}
		return false;
	}

	public static function get_droip_version_from_db() {
		$version = wp_cache_get(DROIP_APP_PREFIX . '_version', DROIP_DEFAULT_CACHE_GROUP);

		if (false === $version) {
			$version = get_option(DROIP_APP_PREFIX . '_version', '');
			
			if ( !empty($version) ) {
				wp_cache_set(DROIP_APP_PREFIX . '_version', $version, DROIP_DEFAULT_CACHE_GROUP);
			}
		}

		return $version;
	}

	public static function set_droip_version_in_db() {
		$version = self::get_droip_version_from_db();

		if($version && version_compare($version, DROIP_VERSION, '==')) {
			// No need to update the version if it's already equal to the current version.
			return;
		}

		update_option(DROIP_APP_PREFIX . '_version', DROIP_VERSION, false);
		wp_cache_set(DROIP_APP_PREFIX . '_version', DROIP_VERSION, DROIP_DEFAULT_CACHE_GROUP);
	}

	public static function accepted_file_types_by_plugin($accepted_media_types = DROIP_SUPPORTED_MEDIA_TYPES) {
    $result = array();

    foreach ($accepted_media_types as $value) {
			if (is_array($value)) {
				$result = array_merge($result, self::accepted_file_types_by_plugin($value));
			} else {
				$result[] = $value;
			}
    }

    return $result;
	}

	public static function content_manager_link_filter($dynamic_content = array(), $href="#") {
		$current_post = get_post(self::get_post_id_if_possible_from_url());

		if ($current_post->post_type === DROIP_CONTENT_MANAGER_PREFIX) {
			$fields = ContentManagerHelper::get_post_type_custom_field_keys($current_post->post_parent);

			if (isset($fields[$dynamic_content['value']]) && is_array($fields[$dynamic_content['value']])) {
				if ('email' === $fields[$dynamic_content['value']]['type']) {
					$href = "mailto:$href";
				} else if ('phone' === $fields[$dynamic_content['value']]['type']) {
					$href = "tel:$href";
				}
			}
		}

		return $href;
	}

	public static function check_string_has_this_tags($string, $tag) {
    // Check if the string contains either a <p> tag or an <h1> tag
    return preg_match("/<".$tag."[^>]*>/i", $string) === 1;
	}
	private static function get_global_data_post_id(){
		$post_id = get_option('DROIP_GLOBAL_DATA_POST_TYPE_ID', false);
		if($post_id){
			return $post_id;
		}else{
			//this block will run only once
			$posts = get_posts(array(
				'post_type' => DROIP_GLOBAL_DATA_POST_TYPE_NAME,
				'numberposts' => 1,
			));
			if($posts){
				$post_id = $posts[0]->ID;
			}else{
				//create new post
				$post = array(
					'post_title' => DROIP_GLOBAL_DATA_POST_TYPE_NAME,
					'post_type' => DROIP_GLOBAL_DATA_POST_TYPE_NAME,
					'post_status' => 'draft'
				);		
				$post_id = wp_insert_post($post);
			}
			update_option('DROIP_GLOBAL_DATA_POST_TYPE_ID', $post_id, true);
		}

		return $post_id;
	}

	/**
	 * Get global data using key
	 * 
	 */
	public static function get_global_data_using_key($key){
		//first get post using DROIP_GLOBAL_DATA_POST_TYPE_NAME post_type name. if not found then create new one.
		$post_id = self::get_global_data_post_id();
		$value = get_post_meta($post_id, $key, true);
		if(!$value){
			//this block will run only once for a option key
			$value = get_option($key);
			update_post_meta($post_id, $key, $value);
			delete_option($key);
		}
		return $value;
	}
	
	/**
	 * Get global data using key
	 * 
	 */
	public static function update_global_data_using_key($key, $value){
		$post_id = self::get_global_data_post_id();
		update_post_meta($post_id, $key, $value);
	}

	public static function get_template_data_if_current_page_is_droip_template(){
		$custom_data = get_query_var(DROIP_APP_PREFIX . '_custom_data');
		$data = false;
		$builder_div = '';
		if ($custom_data && isset($custom_data[DROIP_APP_PREFIX . '_template_content'])) {
			$action = HelperFunctions::sanitize_text(isset($_GET['action']) ? $_GET['action'] : null);
			$load_for = HelperFunctions::sanitize_text( isset( $_GET['load_for'] ) ? $_GET['load_for'] : '' );

			if ($action === DROIP_EDITOR_ACTION && $load_for === DROIP_APP_PREFIX . '-iframe' && !str_contains($custom_data[DROIP_APP_PREFIX . '_template_content'], DROIP_APP_PREFIX . '-builder')){
				$template_edit_url = HelperFunctions::get_post_url_arr_from_post_id( $custom_data[DROIP_APP_PREFIX . '_template_id'] )['editor_url'];
				$builder_div = '<div id="' . DROIP_CLASS_PREFIX . '-builder' . '" template-error="' . $template_edit_url . '"></div>';
			}

			$data = array(
				'content' => $custom_data[DROIP_APP_PREFIX . '_template_content'] . $builder_div,
				'template_id' => $custom_data[DROIP_APP_PREFIX . '_template_id']
			);
		}
		return $data;
	}
	
	public static function get_custom_data_if_current_page_is_droip_custom_post(){
		$custom_data = get_query_var(DROIP_APP_PREFIX . '_custom_data');
		$data = false;
		$builder_div = '';
		if ($custom_data && isset($custom_data[DROIP_APP_PREFIX . '_custom_post_content'])) {
			$action = HelperFunctions::sanitize_text(isset($_GET['action']) ? $_GET['action'] : null);
			$load_for = HelperFunctions::sanitize_text( isset( $_GET['load_for'] ) ? $_GET['load_for'] : '' );

			if ($action === DROIP_EDITOR_ACTION && $load_for === DROIP_APP_PREFIX . '-iframe' && !str_contains($custom_data[DROIP_APP_PREFIX . '_custom_post_content'], DROIP_APP_PREFIX . '-builder')){
				$template_edit_url = HelperFunctions::get_post_url_arr_from_post_id( $custom_data[DROIP_APP_PREFIX . '_custom_post_id'] )['editor_url'];
				$builder_div = '<div id="' . DROIP_CLASS_PREFIX . '-builder' . '" template-error="' . $template_edit_url . '"></div>';
			}

			$data = array(
				'content' => $custom_data[DROIP_APP_PREFIX . '_custom_post_content'] . $builder_div,
				'post_id' => $custom_data[DROIP_APP_PREFIX . '_custom_post_id']
			);
		}
		return $data;
	}

	public static function validate_slug($post_id, $post_type, $post_name){
    global $wpdb;
    // Prepare the SQL query to check if the slug exists for the given post type
    $query = $wpdb->prepare("
        SELECT ID 
        FROM $wpdb->posts 
        WHERE post_name = %s 
          AND post_type = %s
          AND ID != %d
    ", $post_name, $post_type, $post_id ? $post_id : 0);

    // Execute the query
    $result = $wpdb->get_var($query);

    // If a post with the same slug exists, return false
    if ($result) {
        return false;
    }

    // If no post with the same slug exists, return true
    return true; 
  }

	/**
	 * Summary of find_utility_page_for_this_context
	 * @param mixed $type
	 * @param mixed $get_by id | type.
	 * @return mixed
	 */
	public static function find_utility_page_for_this_context( $value = '404', $get_by = 'type' ) {
		$utility_pages = Page::fetch_list(DROIP_APP_PREFIX . '_utility', true,  array('publish'));
		if(count($utility_pages) > 0) {
			foreach ($utility_pages as $key => $page) {
				if($get_by === 'type'){
					if($page['utility_page_type'] === $value){
						return $page;
					}
				}else if($get_by === 'id'){
					if($page['id'] === (int) $value){
						return $page;
					}
				}
			}
		}
		return false;
	}
	public static function get_current_page_context(){
		$context = array(); // {id, type}

		$obj = get_queried_object();

		if(is_404()){
			$context['type'] = '404';
		} else if ($obj instanceof WP_Post) {
			$context['id'] = $obj->ID;
			$context['type'] = 'post';
		} else if ($obj instanceof WP_User) {
			$context['id'] = $obj->ID;
			$context['type'] = 'user';
		}
		elseif ($obj instanceof WP_Term) {
			$context['id'] = $obj->term_id;
			$context['type'] = 'term';
		} 
		// elseif ($obj instanceof WP_Post_Type) {
		// 		echo 'This is a WP_Post_Type object';
		// } elseif (is_null($obj)) {
		// 		echo 'No queried object (null)';
		// }
		
		else {
			$droip_utility_page_type = get_query_var('droip_utility_page_type');
			$droip_utility_page_id = get_query_var('droip_utility_page_id');
			if(!empty($droip_utility_page_type)){
				if(!self::check_utility_page_visibility_condition($droip_utility_page_type)){
					$context['type'] = '404';
				}else{
					$context['type'] = 'droip_utility';
					$context['droip_utility_page_type'] = $droip_utility_page_type;
					$context['droip_utility_page_id'] = $droip_utility_page_id;
				}
			}
		}
		return $context;
	}

	
	/**
	 * Get utility page slug using type.
	 * { type: 'login', title: 'Login' },
	 * { type: 'sign_up', title: 'Registration' },
	 * { type: 'forgot_password', title: 'Forgot Password' },
	 * { type: 'reset_password', title: 'Reset Password' },
	 * { type: 'retrive_username', title: 'Retrive Username' },
	 * { type: '404', title: '404' },
	 * 
	 * @param string $type //utility page type.
	 * @return string||bool //string or false.
	 */
	public static function get_utility_page_url($type){
		$utility_pages = Page::fetch_list(DROIP_APP_PREFIX . '_utility', true,  array('publish'));
		foreach ($utility_pages as $key => $page) {
			$utility_page_type = $page['utility_page_type'];

			$slug = $page['slug'];
			if( $utility_page_type === $type ){
				return home_url('/'.$slug);
			}
		}
		return false;
	}
	public static function check_utility_page_visibility_condition($type){
		if ($type === 'login' || $type === 'sign_up' || $type === 'forgot_password'|| $type === 'reset_password'|| $type === 'retrive_username') {
			// Check if the user is already logged in
			if (is_user_logged_in()) {
					return false; // User is logged in, so the page should not be visible
			}
			// Add other conditions based on the type if needed
			if ($type === 'signup') {
					// Example: Check if registrations are enabled in WordPress
					if (!get_option('users_can_register')) {
							return false; // Registration is disabled
					}
			}
			// If the user is not logged in and other conditions pass, allow the page to be visible
			return true;
		}
		// If the type is not 'login' or 'signup', return false by default
		return true;
	}

	public static function delete_directory($dirname)
  {
    if (is_dir($dirname))
      $dir_handle = opendir($dirname);
    if (!$dir_handle)
      return false;
    while ($file = readdir($dir_handle)) {
      if ($file != "." && $file != "..") {
        if (!is_dir($dirname . "/" . $file))
          unlink($dirname . "/" . $file);
        else
          self::delete_directory($dirname . '/' . $file);
      }
    }
    closedir($dir_handle);
    rmdir($dirname);
    return true;
  }

	public static function get_temp_folder_path(){
		$upload_dir = wp_upload_dir();
		$temp_folder = 'droip_temp';
		$temp_folder_path = $upload_dir['basedir'] . '/' . $temp_folder;

		return $temp_folder_path;
	}

	public static function get_initial_view_ports(){
		return json_decode('{
   "active":"md",
   "scale":1,
   "zoom":1,
   "width":1400,
   "mdWidth":"",
   "defaults":[
      "md",
      "tablet",
      "mobileLandscape",
      "mobile"
   ],
   "list":{
      "md":{
         "value":1400,
         "scale":1,
         "minWidth":1400,
         "maxWidth":1400,
         "title":"Desktop(Default)",
         "icon":"desktop",
         "activeIcon":"desktop-hover"
      },
      "tablet":{
         "value":991,
         "scale":1,
         "minWidth":991,
         "maxWidth":991,
         "title":"Tablet",
         "icon":"tablet-default",
         "activeIcon":"tablet-hover"
      },
      "mobileLandscape":{
         "value":767,
         "scale":1,
         "minWidth":767,
         "maxWidth":767,
         "title":"Mobile Landscape",
         "icon":"phone-hr-default",
         "activeIcon":"phone-hr-hover"
      },
      "mobile":{
         "value":575,
         "scale":1,
         "minWidth":575,
         "maxWidth":575,
         "title":"Mobile",
         "icon":"phone-vr-default",
         "activeIcon":"phone-vr-hover"
      }
   }
}', true);
	}

	public static function download_zip_from_remote($remote_file, $new_name)
	{
		$file_ext = explode('.', $remote_file); // ['file', 'ext']
    $file_ext = strtolower(end($file_ext)); // 'ext'
    $allowed = ['zip'];
		if (!in_array($file_ext, $allowed)) {
			return false;
		}

		try {
			// error_reporting(E_ALL);
			// ini_set('display_errors', 1);
			// Download the file from the remote server.
			// Create a stream context to disable SSL verification
			$options = [
				"ssl" => [
						"verify_peer" => false,      // Disable verification of the peer's certificate
						"verify_peer_name" => false // Disable verification of the peer's name
				]
			];
			$context = stream_context_create($options);
			$file_contents = file_get_contents($remote_file, false, $context);

			// Save the file locally.
			if ($file_contents !== false) {
				// Local path to save the downloaded file.
				$local_file = wp_upload_dir()['basedir'] . '/' . $new_name;
				file_put_contents($local_file, $file_contents);
				return $local_file;
			}
		} catch (\Throwable $th) {
			// throw $th;
		}
		return false;
	}

	public static function filterZipFile($zip, $zip_file_path) {
    // Temporary filtered ZIP path
    $filtered_zip_path = sys_get_temp_dir() . '/filtered.zip';
    $filtered_zip = new \ZipArchive;

    if ($filtered_zip->open($filtered_zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
        // Loop through all files in the archive
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $file_path = 'zip://' . $zip_file_path . '#' . $filename;

				// Get MIME type based on file extensioncm_f
				$file_mime = self::getMimeTypeByExtension($filename);

            // Additional JSON validation
            if ($file_mime === 'application/json' && !self::isJsonFile($file_path)) {
                $file_mime = 'text/plain'; // Fallback if not valid JSON
            }

            // Check if the file MIME type matches supported types
            $is_supported = false;
            foreach (DROIP_SUPPORTED_MEDIA_TYPES as $types) {
                if (in_array($file_mime, $types)) {
                    $is_supported = true;
                    break;
                }
            }

            // Add the file to the filtered archive if supported
            if ($is_supported) {
                $file_contents = $zip->getFromIndex($i);
                $filtered_zip->addFromString($filename, $file_contents);
            }
        }

        $filtered_zip->close();
        return $filtered_zip_path;
    } else {
        return false;
    }
 }

	// Helper function to get MIME type by file extension
	private static function getMimeTypeByExtension($filename) {
			$extension_to_mime = [
					'json' => 'application/json',
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'gif'  => 'image/gif',
					'webp' => 'image/webp',
					'svg'  => 'image/svg+xml',
					'pdf'  => 'application/pdf',
					'mp4'  => 'video/mp4',
					'ogg'  => 'audio/ogg',
					'lottie' => 'text/plain',
					'mov'  => 'video/quicktime',
					'mp3'  => 'audio/mpeg',
					'wav'  => 'audio/wav',

					// Add more extensions as needed
			];

			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			return $extension_to_mime[strtolower($ext)] ?? 'application/octet-stream';
	}

	// Helper function to validate JSON file content
	private static function isJsonFile($file_path) {
			$file_contents = @file_get_contents($file_path);
			$trimmed = trim($file_contents);
			return $trimmed[0] === '{' || $trimmed[0] === '[';
	}

	public static function is_remote_url($url) {
    // Parse the URL to get components
    $parsed_url = parse_url($url);
		return isset($parsed_url['scheme']);
	}

	public static function is_element_accessible($access) {

    switch ($access) {
        case 'all':
            return true; // Accessible to everyone
						
				case 'guest':
            return !is_user_logged_in();

        case 'logged-in':
            return is_user_logged_in(); // Accessible to logged-in users
						
        case 'admin':
            return current_user_can('administrator'); // Accessible to administrators

        case 'editor':
            return current_user_can('editor'); // Accessible to editors

        case 'author':
            return current_user_can('author'); // Accessible to authors

        case 'subscriber':
            return current_user_can('subscriber'); // Accessible to subscribers

        default:
            return false; // Default to not accessible if the value is unrecognized
    }
	}

	/**
	 * Find symbol for post id using condition
	 * it will find and return selected symbols html and css;
	 *
	 * @param string $type : symbol type.
	 * @param string $post : post object.
	 * @return symbol || bool(false)
	 */
	public static function find_symbol_for_this_page( $type ) {
		$all_symbols = Symbol::fetch_list( true, false );
		foreach ( $all_symbols as $key => $symbol ) {
			if(isset($symbol['setAs']) && $symbol['setAs'] === $type){
				return $symbol;
			}
		}
		return false;
	}
	/**
	 * Get Custom Header
	 * it will find and return selected symbols html and css;
	 *
	 * @param string $type stymbol type header|footer.
	 * @param string $html if true the function will return html otherwise return symbol object.
	 * @return string|object custom section html or stymbol object.
	 */
	public static function get_page_custom_section( $type, $html = true ) {
		$symbol = self::find_symbol_for_this_page( $type );
		if ( ! $html ) {
			return $symbol;
		}

		if(isset(self::$custom_sections[$type])){
			return self::$custom_sections[$type];
		}

		$s = '';

		if ( $symbol ) {
			$action = HelperFunctions::sanitize_text( isset( $_GET['action'] ) ? $_GET['action'] : '' );
			$symbol_data = $symbol['symbolData'];
			$set_as = isset($symbol['setAs']) ? $symbol['setAs'] : '';

			$post_id = self::get_post_id_if_possible_from_url();
			$template_data = self::get_template_data_if_current_page_is_droip_template();
			if($template_data) $post_id = $template_data['template_id'];


			$custom_page_data = self::get_custom_data_if_current_page_is_droip_custom_post();
			if($custom_page_data) $post_id = $custom_page_data['post_id'];

			$is_page_symbol_disabled = get_post_meta($post_id, DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS, true);
			if (isset($is_page_symbol_disabled) && is_array($is_page_symbol_disabled) && isset($is_page_symbol_disabled[$type])) {
				$is_page_symbol_disabled = $is_page_symbol_disabled[$type];
			} else {
				$is_page_symbol_disabled = false;
			}

			$params = array( 
				'blocks' => $symbol_data['data'],
				'style_blocks' => $symbol_data['styleBlocks'],
				'root' => $symbol_data['root'],
				'post_id' => $symbol['id'],
				'options' => [],
				'get_style' => true,
				'get_variable' => false,
				'should_take_app_script' => false,
				'prefix' => 'droip-s' . $symbol['id']
			);
			if($action === DROIP_EDITOR_ACTION){
				$extra_attr_for_hf_symbol = '';
				if($is_page_symbol_disabled) $extra_attr_for_hf_symbol = ' style="display:none;"';
				$s = '<' . $type . $extra_attr_for_hf_symbol . ' data-droip-symbol_set_as="' . $set_as . '" data-droip-symbol="' . $symbol['id'] . '" data-droip="' . $type . '">' . self::get_html_using_preview_script( $params ) . '</' . $type .'>'; //added data-droip="$type" => We removed theme header and footer using preg_replace in TheFrontendHooks.php file
			}else if(!$is_page_symbol_disabled){	
				$params['should_take_app_script'] = true;
				$params['get_variable'] = true;
				$s = self::get_html_using_preview_script( $params );
			}else if($is_page_symbol_disabled){
				$s = '<!-- ' . $type . ' is disabled -->';
			}
		}
		self::$custom_sections[$type] = $s;
		return $s;
	}

	/**
	 * Check if a value is considered true or false.
	 *
	 * @param mixed $value The value to check.
	 * @return bool Returns true if the value is considered "truthy", otherwise false.
	 */
	public static function isTruthy($value): bool {
		return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
	}

	/**
	 * Get the upload directory path has upload or write permission.
	 */
	public static function get_upload_dir_has_write_permission()
	{
		if (! function_exists('request_filesystem_credentials')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (WP_Filesystem()) {
			global $wp_filesystem;
			$upload_dir = wp_upload_dir();
			return $wp_filesystem->is_writable($upload_dir['basedir']);
		}

		return false;
	}

	/**
	 * $name = [] or string
	 */
	public static function add_prefix_to_class_name($prefix, $name) {
    if (is_array($name)) {
        foreach ($name as $key => $c) {
            $c = strtolower($c);
            if (in_array($c, DROIP_PRESERVED_CLASS_LIST)) {
                $name[$key] = $c;
            } else {
                $name[$key] = $prefix ? strtolower($prefix) . '-' . $c : $c;
            }
        }
    } else {
        $name = strtolower($name);
        if (!in_array($name, DROIP_PRESERVED_CLASS_LIST)) {
            $name = $prefix ? strtolower($prefix) . '-' . $name : $name;
        }
    }
    return $name;
}

	public static function checkVisibilityConditions($element, $options) {
		$conditions = $element['properties']['visibilityConditions'] ?? [];
		if ( !count($conditions) ) return true;
    foreach ($conditions as $and_group) {
        $and_result = true;
        foreach ($and_group as $condition) {
					$source = (string) ($condition['source'] ?? DROIP_APP_PREFIX);
					$condition_result = apply_filters('droip_visibility_condition_check_' . $source, false, $condition, $options);
					if (!$condition_result) {
							$and_result = false;
							break; // If any condition fails in AND group
					}
        }
        if ($and_result) {
            return true; // If any OR group passes
        }
    }
    return false; // No group passed
	}

		private static function update_slider_style_blocks($blocks, $styles)
	{

		$slider_mask_styleIds = [];
		$slider_item_styleIds = [];


		// slider_item
		foreach ($blocks as $id => $block) {
			// Check if the block has a 'name' key
			if (isset($block['name'])) {
				if (isset($block['name']) && $block['name'] === 'slider_mask') {
					// Loop through each item in $block['styleIds']
					foreach ($block['styleIds'] as $styleId) {
						// Check if the styleId is NOT already in the $slider_mask_styleIds array
						if (!in_array($styleId, $slider_mask_styleIds)) {
							// If not present, push it into the array
							$slider_mask_styleIds[] = $styleId;
						}
					}
				}

				if (isset($block['name']) && $block['name'] === 'slider_item') {
					// Loop through each item in $block['styleIds']
					foreach ($block['styleIds'] as $styleId) {
						// Check if the styleId is NOT already in the $slider_item_styleIds array
						if (!in_array($styleId, $slider_item_styleIds)) {
							// If not present, push it into the array
							$slider_item_styleIds[] = $styleId;
						}
					}
				}
			}
		}

		if (count($slider_mask_styleIds) > 0) {
			foreach ($slider_mask_styleIds as $styleId) {
				$style = $styles[$styleId];
				$style_variants = $style['variant'];

				$styleVariants = [];

				if (count($style_variants) > 0) {
					foreach ($style_variants as $key => $css) {
						$css = preg_replace('/overflow\s*:\s*hidden;?/', '', $css);
						$css = preg_replace('/pointer-events\s*:\s*none;?/', '', $css);

						$css = trim($css);
						$styleVariants[$key] = $css;
					}
				}
				$styles[$styleId]['variant'] = $styleVariants;
			}
		}


		if (count($slider_item_styleIds) > 0) {
			foreach ($slider_item_styleIds as $styleId) {
				$style = $styles[$styleId];
				$style_variants = $style['variant'];

				$styleVariants = [];

				if (count($style_variants) > 0) {
					foreach ($style_variants as $key => $css) {
						$css = preg_replace('/position\s*:\s*absolute;?/', '', $css);
						$css = preg_replace('/display\s*:\s*none;?/', '', $css);

						$css = trim($css);
						$styleVariants[$key] = $css;
					}

					$styles[$styleId]['variant'] = $styleVariants;
				}
			}
		}

		return $styles;
	}

	public static function handle_legacy_slider_class()
	{
		$data = Page::get_all_data_by_droip_meta_key();

		foreach ($data as $key => $value) {
			$post_id = $value['post_id'];       // ID of the post
			$meta_key = $value['meta_key'];     // Meta key name
			$meta_value = $value['meta_value']; // Serialized meta value

			$meta_value = unserialize($meta_value); // Convert serialized data to array

			// If the meta value has a 'blocks' key, handle it as a full page data
			if (isset($meta_value['blocks'])) {
				$blocks = $meta_value['blocks']; // Get the blocks array
				$styles = self::get_page_styleblocks($post_id);
				$updated_styles = self::update_slider_style_blocks($blocks, $styles); // Update block names

				self::update_page_styleblocks($post_id, $updated_styles);
			} else {
				// If no 'blocks' key, treat entire value as blocks array
				$blocks = $meta_value;
				$post = get_post($post_id);


				if (isset($post->post_type) && $post->post_type === 'droip_symbol') {
					$styles = $blocks['styleBlocks'];
					$updated_styles = self::update_slider_style_blocks($blocks['data'], $styles);
					$blocks['styleBlocks'] = $updated_styles;

					update_post_meta($post_id, $meta_key, $blocks);
				}
			}
		}
	}

	public static function handle_legacy_slider_default_class(){
		$styles = self::get_global_data_using_key(DROIP_GLOBAL_STYLE_BLOCK_META_KEY);
		if(!$styles){
			$styles = array();
		}
		if(count($styles) > 0){
			if(isset($styles['droip_slider_slide'])){
				// Handle droip_slider_mask
				if(isset($styles['droip_slider_mask'])){
					$variants = $styles['droip_slider_mask']['variant'];
					if(count($variants) > 0){
						$styleVariants = [];
						foreach ($variants as $key => $css) {
							$css = preg_replace('/overflow\s*:\s*hidden;?/', '', $css);
							$css = preg_replace('/pointer-events\s*:\s*none;?/', '', $css);
							$css = trim($css);
							$styleVariants[$key] = $css;
						}
						$styles['droip_slider_mask']['variant'] = $styleVariants;
					}
				}
				
				// Handle droip_slider_slide
				if(isset($styles['droip_slider_slide'])){
					$variants = $styles['droip_slider_slide']['variant'];
					if(count($variants) > 0){
						$styleVariants = [];
						foreach ($variants as $key => $css) {
							$css = preg_replace('/position\s*:\s*absolute;?/', '', $css);
							$css = preg_replace('/display\s*:\s*none;?/', '', $css);
							$css = trim($css);
							$styleVariants[$key] = $css;
						}
						$styles['droip_slider_slide']['variant'] = $styleVariants;
					}
				}
			}
		}
		
	}

	public static function get_current_item_index($index, $options)
	{
		$pagination = isset($options['pagination']) ? $options['pagination'] : false;
		$items_per_page = isset($options['items_per_page']) ? $options['items_per_page'] : 3;
		$page_no = isset($options['page_no']) ? $options['page_no'] : 1;

		if ($pagination && $items_per_page > 0 && $page_no > 0) {
			// Calculate the start index based on the current page and items per page
			$index = (($page_no - 1) * $items_per_page) + $index;
		}

		return $index;
	}
}