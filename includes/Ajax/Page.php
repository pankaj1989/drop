<?php
/**
 * Page or post data manager
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\Ajax\Collaboration\Collaboration;
use Droip\HelperFunctions;
use Droip\Staging;

/**
 * Page API Class
 */
class Page {
	/**
	 * Save page data
	 *
	 * @return void wp_send_json.
	 */
	public static function save_page_data() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page_data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$page_data = json_decode( stripslashes( $page_data ), true );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_POST['id'] ) ? $_POST['id'] : '' );
		$is_staging = isset( $_POST['is_staging'] ) ? HelperFunctions::sanitize_text($_POST['is_staging']) : false ;

		if ( ! empty( $page_data ) && ! empty( $post_id ) ) {

			$version_where_saved = HelperFunctions::save_droip_data_to_db($post_id, $page_data, $is_staging);

			$post = get_post($post_id);
			$arr = ['ID'=>$post->ID, 'type'=> $post->post_type];
			$post_id = wp_update_post( $arr );
			wp_send_json( array( 'status' => 'Page data saved.', 'staging_version'=> $version_where_saved  ) );
		} else {
			wp_send_json( array( 'status' => 'Page data save failed!' ) );
		}
		die();
	}

	/**
	 * Delete page
	 *
	 * @return void wp_send_json.
	 */
	public static function delete_page() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id = (int) HelperFunctions::sanitize_text( isset( $_POST['id'] ) ? $_POST['id'] : '' );
		wp_delete_post( $id );
		wp_send_json( array( 'status' => 'Page deleted' ) );
	}

	/**
	 * Add new page
	 *
	 * @return void wp_send_json.
	 */
	public static function add_new_page() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$options = isset( $_POST['options'] ) ? $_POST['options'] : null;
		$options = json_decode( stripslashes( $options ), true );

		if ( HelperFunctions::user_has_page_edit_access() ) {
			$post_id = wp_insert_post(
				array(
					'post_title' => $options['post_title'],
					'post_name'  => $options['post_title'],
					// check if type = droip_page then change it to wp page type. cause we only set template if page type is droip_page.
					'post_type'  => $options['post_type'] === 'droip_page' ? 'page' : $options['post_type'],
				)
			);

			if ( isset( $options['blocks'] ) ) {
				// this is for popup creation. cause popup has predefined blocks.
				update_post_meta( $post_id, DROIP_APP_PREFIX, $options['blocks'] );
			}
			
			if (isset($options['conditions'])) {
				// this is for template creation. cause popup has predefined conditions.
				update_post_meta($post_id, DROIP_APP_PREFIX . '_template_conditions', $options['conditions']);
			}

			//TODO: need to remove this code. after checking collection_type used or not
			if (isset($options['collection_type']) && !empty($options['collection_type'])) {
				update_post_meta($post_id, DROIP_APP_PREFIX . '_template_collection_type', $options['collection_type']);
			}
			
			if (isset($options['utility_page_type']) && !empty($options['utility_page_type'])) {
				update_post_meta($post_id, DROIP_APP_PREFIX . '_utility_page_type', $options['utility_page_type']);
			}

			update_post_meta($post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, DROIP_APP_PREFIX);
			if ($options['post_type'] === 'page' || $options['post_type'] === 'droip_page') {
				// check if type = droip_page then change it to wp page type. cause we only set template if page type is droip_page.
				update_post_meta($post_id, '_wp_page_template', DROIP_FULL_CANVAS_TEMPLATE_PATH);
			}

			if($options['post_type'] === DROIP_APP_PREFIX . '_utility') {
				self::initialize_predefine_template_data($post_id, $options['utility_page_type']);
			}

			if(isset($options['custom_template']) && !empty($options['custom_template']) &&
			   isset($options['custom_template']['url']) && !empty($options['custom_template']['url'])){
				self::add_custom_template_to_page($post_id, $options['custom_template']['url']);
			}

			wp_send_json((new self())->format_single_post($post_id));
		} else {
			wp_send_json_error('Limited permission', 403);
		}
	}

	public static function initialize_predefine_template_data($post_id, $type) {
		if($type === '404' || $type === 'login' || $type === 'sign_up' || $type === 'forgot_password' || $type === 'reset_password' || $type === 'retrive_username' ) {
			self::fetch_template_data($post_id, $type);
		}
	}

	public static function add_custom_template_to_page ($post_id, $template_url) {
			self::fetch_template_data($post_id, $template_url, true);
	}

	public static function fetch_template_data($post_id, $type, $custom = false) {
		$zip_file_path = DROIP_PUBLIC_ASSETS_URL . '/pre-built-pages/basic/' . $type . '.zip';
		if($custom){
			$zip_file_path = $type;
		}
		$file_name_new = uniqid('', true) . '.zip'; // 'random.ext'
		$zip_file_path = HelperFunctions::download_zip_from_remote($zip_file_path, $file_name_new);
		if($zip_file_path){
			$d = ExportImport::process_droip_template_zip($zip_file_path, false, $post_id);
			if($d){
				// delete zip file
				unlink($zip_file_path);
				return true;
			}else{
				wp_send_json_error('Failed to extract zip file');
			}
		}else{
			wp_send_json_error('Failed to download zip file');
		}
	}

	/**
	 * Update current page data
	 *
	 * @return void wp_send_json.
	 */
	public static function update_page_data() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$options = isset( $_POST['options'] ) ? $_POST['options'] : null;
		$options = json_decode( stripslashes( $options ), true );
		$arr     = array();
		if ( isset( $options['ID'] ) ) {
			$arr['ID'] = $options['ID'];
			$post_id = $arr['ID'];
			if ( isset( $options['post_title'] ) ) {
				$arr['post_title'] = $options['post_title'];
			}
			if ( isset( $options['post_name'] ) ) {
				$arr['post_name'] =  $options['post_name'] ;
			}
			if ( isset( $options['post_status'] ) ) {
				$arr['post_status'] = $options['post_status'];
			}
			wp_update_post( $arr );
	
			if ( isset( $options['blocks'] ) ) {
				update_post_meta( $post_id, DROIP_APP_PREFIX, $options['blocks'] );
			}
			if ( isset( $options['styleBlocks'] ) ) {
				update_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $options['styleBlocks'] );
			}
			if ( isset( $options['usedFonts'] ) ) {
				update_post_meta( $post_id, DROIP_META_NAME_FOR_USED_FONT_LIST, $options['usedFonts'] );
			}
			if ( isset( $options['conditions'] ) ) {
				update_post_meta( $post_id, DROIP_APP_PREFIX.'_template_conditions', $options['conditions'] );
			}
			if ( isset( $options['variableMode'] ) ) {
				update_post_meta( $post_id, 'droip_variable_mode', $options['variableMode'] );
			}
			wp_send_json( ( new self() )->format_single_post( $post_id ) );
			die();
		}else{
			wp_send_json( array( 'status' => 'Page data update failed' ) );
			die();
		}
	}

	/**
	 * Duplicate page data
	 *
	 * @return void wp_send_json.
	 */
	public static function duplicate_page() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id     = (int) HelperFunctions::sanitize_text( isset( $_POST['id'] ) ? $_POST['id'] : '' );
		$post        = get_post( $post_id );
		$new_post_id = wp_insert_post(
			array(
				'post_title'   => $post->post_title . ' (copy)',
				'post_content' => $post->post_content,
				'post_name'    => $post->post_name,
				'post_type'    => $post->post_type,
				'post_status'  => $post->post_status,
			)
		);

		$page_data = get_post_meta( $post_id, DROIP_APP_PREFIX, true );
		if ( $page_data ) {
			update_post_meta( $new_post_id, DROIP_APP_PREFIX, $page_data );
			update_post_meta( $new_post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, DROIP_APP_PREFIX );
		}

		/**
		 * Also duplicate _wp_page_template meta if exists
		 */
		$page_template = get_post_meta( $post_id, '_wp_page_template', true );
		if ( $page_template ) {
			update_post_meta( $new_post_id, '_wp_page_template', $page_template );
		}

		/**
		 * Also duplicate this page style blocks if exists
		 */
		$post_styles = get_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', true );
		if ( $post_styles ) {
			update_post_meta( $new_post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_styles );
		}

		$used_fonts = get_post_meta( $post_id, DROIP_META_NAME_FOR_USED_FONT_LIST, true );
		if ( $used_fonts ) {
			update_post_meta( $new_post_id, DROIP_META_NAME_FOR_USED_FONT_LIST, $used_fonts );
		}

		wp_send_json( ( new self() )->format_single_post( $new_post_id ) );
	}

	/**
	 * Back to droip editor
	 *
	 * @return void wp_send_json.
	 */
	public static function back_to_droip_editor() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_POST['postId'] ) ? $_POST['postId'] : '' );

		$post = get_post( $post_id );

		if ( $post->post_status === 'auto-draft' ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_title = HelperFunctions::sanitize_text( isset( $_POST['title'] ) ? $_POST['title'] : null );

			if ( ! isset( $post_title ) ) {
				$post_title = DROIP_APP_NAME . ' - ' . $post_id;
			}

			$data = array(
				'ID'          => $post_id,
				'post_title'  => $post_title,
				// 'post_name' => $post_title,
				'post_status' => 'draft',
			);
			wp_update_post( $data );
		}
		wp_send_json( array( 'status' => true ) );
	}


	/**
	 * Back to WordPress editor
	 *
	 * @return void wp_send_json.
	 */
	public static function back_to_wordpress_editor() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_POST['postId'] ) ? $_POST['postId'] : '' );

		delete_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE );
		wp_send_json( array( 'status' => true ) );
	}

	/**
	 * This function is called from EDITOR panel
	 *
	 * @return void wp_send_json.
	 */
	public static function get_page_blocks_and_styles() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_GET['id'] ) ? $_GET['id'] : '' );
		$stage_version = HelperFunctions::sanitize_text( isset( $_GET['stage_version'] ) ? intval($_GET['stage_version']) : false );
		if(!$stage_version) $stage_version = Staging::get_most_recent_stage_version($post_id, false);

		if ( ! empty( $post_id ) ) {
			$post_meta = get_post_meta( $post_id, DROIP_APP_PREFIX, true );
			if ( ! $post_meta ) {
				$post_meta           = array();
				$post_meta['blocks'] = null;
			}

			if($stage_version) {
				$meta_name = Staging::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $stage_version);
				$staging_post_meta = get_post_meta( $post_id, $meta_name, true );
				if ($staging_post_meta ) $post_meta =  $staging_post_meta;
			}

			$styles              = HelperFunctions::get_page_styleblocks( $post_id, $stage_version );
			$post_meta['styles'] = $styles;

			$post_meta['preview_url']          = HelperFunctions::get_post_url_arr_from_post_id( $post_id )['preview_url'];

			$post_meta['is_droip_editor_mode'] = HelperFunctions::is_editor_mode_is_droip( $post_id );

			$content = get_the_content(null, false, $post_id);
			$post_meta['content_length'] = strlen($content);
			wp_send_json( $post_meta );
		}
		die();
	}

	public static function get_page_html(){
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_GET['id'] ) ? $_GET['id'] : '' );
		if ( ! empty( $post_id ) ) {
			$post_meta = get_post_meta( $post_id, DROIP_APP_PREFIX, true );
			if ( ! $post_meta ) {
				$post_meta           = array();
				$post_meta['blocks'] = null;
			}

			$params = array( 
				'blocks' => $post_meta['blocks'],
				'style_blocks' => null,
				'root' => 'root',
				'post_id' => false,
				'options' => array('check_access'=>false),
				'get_style' => false,
				'get_variable' => false,
				'should_take_app_script' => false,
			 );

			$html = HelperFunctions::get_html_using_preview_script( $params );
			wp_send_json( $html );
		}
		die();
	}

	/**
	 * Format single post data
	 *
	 * @param int $post_id post id.
	 * @return object|null post with custom data.
	 */
	public function format_single_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$page = array();
			if ( DROIP_APP_PREFIX . '_popup' === $post->post_type ) {
				$page['blocks']      = get_post_meta( $post->ID, DROIP_APP_PREFIX, true );
				$page['styleBlocks'] = get_post_meta( $post->ID, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', true );
				$page['usedFonts']   = get_post_meta( $post->ID, DROIP_META_NAME_FOR_USED_FONT_LIST, true );
			}

			if ( DROIP_APP_PREFIX . '_template' === $post->post_type ) {
				$conditions = get_post_meta( $post->ID, DROIP_APP_PREFIX.'_template_conditions', true );
				$page['conditions']  = $conditions ? $conditions : [];
				$collection_type = get_post_meta($post->ID, DROIP_APP_PREFIX . '_template_collection_type', true);
				$page['collection_type']  = $collection_type ? $collection_type : '';
			}
			
			if(DROIP_APP_PREFIX . '_utility' === $post->post_type){
				$utility_type_page = get_post_meta($post->ID, DROIP_APP_PREFIX . '_utility_page_type', true);
				$page['utility_page_type']  = $utility_type_page ? $utility_type_page : '';
			}
			
			$page['id']          = $post->ID;
			$page['title']       = $post->post_title;
			$page['status']      = $post->post_status;
			$page['post_type']   = $post->post_type;
			$page['post_parent']   = $post->post_parent;
			$page['preview_url'] = HelperFunctions::get_post_url_arr_from_post_id( $post->ID )['preview_url'];
			$page['editor_url']  = HelperFunctions::get_post_url_arr_from_post_id( $post->ID )['editor_url'];
			$page['slug']  = $post->post_name;
			$page['variableMode'] = self::get_variable_mode($post->ID);

			$disabled_page_symbols = get_post_meta($post_id, DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS, true);
			if (!isset($disabled_page_symbols) || !is_array($disabled_page_symbols)) {
					$disabled_page_symbols = array();
			}
			$page['disabled_page_symbols'] = $disabled_page_symbols;

			return $page;
		}
		return null;
	}

	public static function get_variable_mode($post_id){
		$variableMode = get_post_meta( $post_id, 'droip_variable_mode', true );
		return $variableMode ? $variableMode : 'inherit';
	}

	/**
	 * Fetch page list
	 *
	 * @return void wp_send_json.
	 */
	public static function fetch_list_api() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_types = HelperFunctions::sanitize_text(isset($_GET['post_types']) ? $_GET['post_types'] : '[]');
		$post_types = json_decode($post_types, true);
		$exclude_post_ids = json_decode(HelperFunctions::sanitize_text(isset($_GET['exclude_post_ids']) ? $_GET['exclude_post_ids'] : '[]'), true);

		$query     = HelperFunctions::sanitize_text(isset($_GET['query']) ? $_GET['query'] : null);
		$numberposts = HelperFunctions::sanitize_text(isset($_GET['numberposts']) ? $_GET['numberposts'] : 20);

		$page_list = array();
		if ( HelperFunctions::user_has_page_edit_access() ) {
			$page_list = static::fetch_list($post_types, true, array('publish', 'draft'), $query, $numberposts, $exclude_post_ids);
		}

		wp_send_json( $page_list );
	}
	
	public static function get_pages_for_pages_panel() {
    // Sanitize and validate inputs
    $query = HelperFunctions::sanitize_text(isset($_GET['query']) ? $_GET['query'] : null);
    $page = HelperFunctions::sanitize_text(isset($_GET['page']) ? $_GET['page'] : 1);
    $numberposts = intval(HelperFunctions::sanitize_text(isset($_GET['numberposts']) ? $_GET['numberposts'] : 20));
    $post_types = HelperFunctions::sanitize_text(isset($_GET['post_types']) ? $_GET['post_types'] : '[]');
    $post_types = json_decode($post_types, true);

    // Set a default post type if not provided
    if (empty($post_types)) {
        $post_types = ['page']; // Default to pages if no post types are provided
    }
		$page_list = [];
		$page_list = static::fetch_list($post_types, true, array('publish', 'draft'), $query, $numberposts, $page);
		// if ( HelperFunctions::user_has_page_edit_access() ) {
		// }
    // Return the data as a JSON response
    wp_send_json_success($page_list);
}

	/**
	 * Fetch post list for search
	 *
	 * @return void wp_send_json.
	 */
	public static function get_data_list_for_template_edit_search_flyout()
	{
		$query     = HelperFunctions::sanitize_text(isset($_GET['query']) ? $_GET['query'] : '');
		$conditions     = HelperFunctions::sanitize_text(isset($_GET['conditions']) ? $_GET['conditions'] : []);
		$conditions     = json_decode($conditions, true);
		$data = HelperFunctions::get_collection_items_from_conditions($conditions, $query);
		wp_send_json($data['data']);
	}

	/**
	 * Get all post types and found the all post types that are not discarded post types
	 *
	 * @return void wp_send_json
	 */
	public static function fetch_post_list_data_post_type_wise() {

		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$search_query = HelperFunctions::sanitize_text( isset( $_GET['search'] ) ? $_GET['search'] : '' );

		$post_types           = get_post_types();
		$discarded_post_types = array('attachment', 'custom_css', 'customize_changeset', 'wp_global_styles', 'revision', 'nav_menu_item', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
		
		$post_types['droip_template'] = 'droip_template';
		$post_types['droip_utility'] = 'droip_utility';
		$post_types['droip_popup'] = 'droip_popup';

		$post_types           = array_diff_key( $post_types, array_flip( $discarded_post_types ) );

		$args = array(
			'post_type'      => $post_types,
			's'              => $search_query,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);

		$all_posts = get_posts( $args );
		$data      = array();
		
		// Post types to be grouped under "page"
		$group_under_page = array( 'droip_template', 'droip_utility', 'droip_page' );
		
		foreach ( $all_posts as $p ) {
			$single_post = array(
				'id'         => $p->ID,
				'title'      => $p->post_title,
				'editor_url' => HelperFunctions::get_post_url_arr_from_post_id( $p->ID )['editor_url'],
			);
			
			// Normalize post_type
			$type_key = in_array( $p->post_type, $group_under_page, true ) ? 'page' : $p->post_type;
			$data[ $type_key ][] = $single_post;
		}

		wp_send_json( $data );
	}

	/**
	 * Fetch page list
	 * if $internal is true then it will return array
	 * otherwise it will return json for api call
	 *
	 * @param string  $type post type.
	 * @param boolean $internal if this method call from internal response.
	 * @param string  $post_status post status.
	 *
	 * @return void|array
	 */
	public static function fetch_list($type = 'page', $internal = true, $post_status = array('publish', 'draft'), $query = null, $numberposts = 20, $current_page=1, $exclude_post_ids=[])
	{
		$pages = array();

		$arg = array(
			'post_type'   => $type,
			'post_status' => $post_status,
			// 'numberposts' => $numberposts,
			'orderby'     => 'ID',
			'order'       => 'DESC',
			'posts_per_page' => $numberposts,
			'paged' => $current_page,
			'post__not_in' => $exclude_post_ids,
		);
		if ($query) {
			$arg['s'] = $query;
		}

		$posts = get_posts($arg);

		if (!empty($posts)) {
			foreach ($posts as $post) {

				/**
				 * If page template type is droip full page then check if GET['type'] is set to droip_page otherwise send any page type data
				 */
				$pages[] = (new self())->format_single_post($post->ID);
			}
		}

		if($internal){
			return $pages;
		}
		wp_send_json( $pages );
	}


	/**
	 * Get current page data
	 *
	 * @return void wp_send_json.
	 */
	public static function get_current_page_data() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id        = (int) HelperFunctions::sanitize_text( isset( $_GET['id'] ) ? $_GET['id'] : '' );
		$post_formatted = null;
		if ( $post_id ) {
			$post_formatted = ( new self() )->format_single_post( $post_id );
		}
		wp_send_json( $post_formatted );
	}

	/**
	 * Remove all unused style blocks from option meta
	 * it will collect all posts unused style block id
	 * then it will remove all unused style blocks from option meta
	 *
	 * @return void wp_send_json.
	 */
	public static function remove_unused_style_block_from_db() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_POST['post_id'] ) ? $_POST['post_id'] : '' );

		$global_unused_key_array = self::get_unused_global_style_ids();
		$global_styles           = HelperFunctions::get_global_data_using_key( DROIP_GLOBAL_STYLE_BLOCK_META_KEY );
		foreach ( $global_unused_key_array as $key => $value ) {
			unset( $global_styles[ $value ] );
		}
		HelperFunctions::save_global_style_blocks( $global_styles );

		$post_used_ids = get_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', true );
		$post_used_ids = $post_used_ids ? $post_used_ids : array();
		$this_styles   = get_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', true );
		foreach ( $this_styles as $key => $sb ) {
			if ( ! in_array( $key, $post_used_ids, true ) ) {
				unset( $this_styles[ $key ] );
			}
		}

		HelperFunctions::save_random_style_blocks( $post_id, $this_styles );

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => HelperFunctions::get_page_styleblocks( $post_id ),
			)
		);
	}

	/**
	 * Get unused class info from db
	 *
	 * @param boolean $internal if this method call from internally.
	 * @return void|array wp_send_json.
	 */
	public static function get_unused_class_info_from_db( $internal = false ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id            = (int) HelperFunctions::sanitize_text( isset( $_GET['post_id'] ) ? $_GET['post_id'] : '' );
		$global_unused_keys = self::get_unused_global_style_ids();
		$post_unused_keys   = self::get_unused_post_style_ids( $post_id );

		$merged_style_ids = array_merge( $global_unused_keys, $post_unused_keys );

		if ( $internal ) {
			return $merged_style_ids;
		}

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => $merged_style_ids,
			)
		);

	}
	
	public static function validate_wp_post_slug( ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id            = (int) HelperFunctions::sanitize_text( isset( $_GET['post_id'] ) ? $_GET['post_id'] : '' );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_type          = HelperFunctions::sanitize_text( isset( $_GET['post_type'] ) ? $_GET['post_type'] : '' );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_name          = HelperFunctions::sanitize_text( isset( $_GET['post_name'] ) ? $_GET['post_name'] : '' );
		
		wp_send_json(
			array(
				'status' => 'success',
				'data'   => HelperFunctions::validate_slug($post_id, $post_type, $post_name)
			)
		);
	}
	
	public static function get_editor_read_only_access_data( ) {
		wp_send_json(
			array(
				'status' => 'success',
				'data'   => self::format_editor_access_data()
			)
		);
	}

	private static function format_editor_access_data(){
		$status = HelperFunctions::get_global_data_using_key('droip_editor_read_only_access_status');
		$arr = array(
			'status' => $status ? $status : false,
			'url' => self::get_post_read_only_access_url()
		);
		return $arr;
	}
	
	public static function save_editor_read_only_access_data( ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$data = json_decode( stripslashes( $data ), true );
		
		if($data['type'] === 'status'){
			HelperFunctions::update_global_data_using_key( 'droip_editor_read_only_access_status', HelperFunctions::sanitize_text($data['status']));
		}

		if($data['type'] === 'regenerate'){
			self::generate_read_only_access_token();
		}

		wp_send_json(
			array(
				'status' => 'success',
				'data' => self::format_editor_access_data()
			)
		);
	}

	private static function get_post_read_only_access_url() {
    $token = HelperFunctions::get_global_data_using_key('droip_editor_read_only_access_token');
    if (!$token) {
        $token = self::generate_read_only_access_token();
    }

    // Try to get the home page ID first
    $home_page_id = get_option('page_on_front'); // Retrieves the ID of the homepage if set

    if ($home_page_id) {
        $home_page_url_arr = HelperFunctions::get_post_url_arr_from_post_id($home_page_id);
        return esc_url($home_page_url_arr['editor_url']) . '&editor-preview-token=' . $token;
    }

    // If no homepage is set, fallback to the last edited droip editor page
    $last_edited_droip_editor_page = HelperFunctions::get_last_edited_droip_editor_type_page();
    if ($last_edited_droip_editor_page) {
        $last_edited_droip_editor_page_url_arr = HelperFunctions::get_post_url_arr_from_post_id($last_edited_droip_editor_page->ID);
        return esc_url($last_edited_droip_editor_page_url_arr['editor_url']) . '&editor-preview-token=' . $token;
    }

    // If neither the home page nor the last edited page is found, default to home URL
    return home_url('?action=droip&editor-preview-token=' . $token);
}

	private static function generate_read_only_access_token() {
    $token = wp_generate_password(32, false, false); // Generate a secure random token
		HelperFunctions::update_global_data_using_key( 'droip_editor_read_only_access_token', $token);
    return $token;
	}
	/**
	 * Get all global used style block ids
	 * first get all posts used global style ids
	 * then merge and returns.
	 *
	 * @return array style ids.
	 */
	private static function get_all_global_used_style_block_ids() {
		global $wpdb;
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$used_global_ids = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '" . DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . "'", OBJECT );

		$all_used_block_ids = array();
		foreach ( $used_global_ids as $key => $p_meta ) {
			$this_used_global   = get_post_meta( $p_meta->post_id, $p_meta->meta_key, true );
			$all_used_block_ids = array_merge( $all_used_block_ids, $this_used_global );
		}
		$all_used_block_ids_global = array_unique( $all_used_block_ids );
		return $all_used_block_ids_global;
	}

	public static function get_all_data_by_droip_meta_key(){
		global $wpdb;
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		// $all_page_meta_data = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '" . DROIP_APP_PREFIX . "'", ARRAY_A );
		$all_page_meta_data = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '" . DROIP_APP_PREFIX . "' ORDER BY meta_id DESC", ARRAY_A );

		return $all_page_meta_data;
	}

	/**
	 * Get all global unused style block ids
	 * first get all posts used global style ids using get_all_global_used_style_block_ids()
	 * then collect and return.
	 *
	 * @return array style ids.
	 */
	private static function get_unused_global_style_ids() {
		$all_used_ids = self::get_all_global_used_style_block_ids();
		$styles       = HelperFunctions::get_global_data_using_key( DROIP_GLOBAL_STYLE_BLOCK_META_KEY );
		$unused_keys  = array();

		foreach ( $styles as $key => $sb ) {

			//we will also remove default style block too. if it is not used in any post. and added latest style block from front end.
			if ( (true || ( ( isset( $sb['isDefault'] ) && ! $sb['isDefault'] ) || ! isset( $sb['isDefault'] ) ) ) && ! in_array( $key, $all_used_ids, true ) ) {
				$unused_keys[] = $key;
			}
		}
		return $unused_keys;
	}

	/**
	 * Get all post unused style block ids
	 * first get all posts used post style ids using get_unused_post_style_ids()
	 * then collect and return.
	 *
	 * @param int $post_id wp post id.
	 * @return array style ids.
	 */
	private static function get_unused_post_style_ids( $post_id ) {
		$all_used_ids = get_post_meta( $post_id, DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', true );
		$styles       = get_post_meta( $post_id, DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', true );

		if ( ! $styles ) {
			return array();
		}
		$unused_keys = array();

		foreach ( $styles as $key => $sb ) {
			if ( ! $all_used_ids || ! in_array( $key, $all_used_ids, true ) ) {
				$unused_keys[] = $key;
			}
		}
		return $unused_keys;
	}

	public static function toggle_disabled_page_symbols() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id = (int) HelperFunctions::sanitize_text( isset( $_POST['post_id'] ) ? $_POST['post_id'] : get_the_ID() );
		$symbol_type = HelperFunctions::sanitize_text( isset( $_POST['symbol_type'] ) ? $_POST['symbol_type'] : null );
		$disable = HelperFunctions::sanitize_text( isset( $_POST['disable'] ) ? $_POST['disable'] : null );

		if($post_id && $symbol_type && $disable) {
			$prev_status = get_post_meta($post_id, DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS, true);
			if (!isset($prev_status) || !is_array($prev_status)) {
					$prev_status = array();
			}
			$disable = filter_var($disable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
			$current_status = array_merge($prev_status, array($symbol_type => $disable));
			update_post_meta($post_id, DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS, $current_status);
			wp_send_json( array( 'status' => 'Page data saved.' ) );
		}
		wp_send_json( array( 'status' => 'Page data save failed!' ) );
	}
}