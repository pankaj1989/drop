<?php
/**
 * Single post or page droip settings
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\API\ContentManager\ContentManagerHelper;
use Droip\HelperFunctions;


/**
 * PageSettings API Class
 */
class PageSettings {

	/**
	 * Save page settings data
	 *
	 * @return void wp_send_json
	 */
	public static function save_page_setting_data() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id = (int) HelperFunctions::sanitize_text( isset( $_POST['id'] ) ? $_POST['id'] : '' );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page_name = HelperFunctions::sanitize_text( isset( $_POST['page_name'] ) ? $_POST['page_name'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slug = HelperFunctions::sanitize_text( isset( $_POST['page_url'] ) ? $_POST['page_url'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_status = HelperFunctions::sanitize_text( isset( $_POST['post_status'] ) ? $_POST['post_status'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page_desc = HelperFunctions::sanitize_text( isset( $_POST['page_desc'] ) ? $_POST['page_desc'] : '' );
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$seo_settings                            = json_decode(stripslashes($_POST['seo_settings']), true);
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$custom_code = json_decode( stripslashes( $_POST['custom_code'] ), true );

		$the_post = array(
			'ID'           => $id,
			'post_title'   => $page_name,
			'post_name'    => $slug,
			'post_excerpt' => $page_desc,
		);

		if($post_status){
			$the_post['post_status'] = $post_status;
		}

		$post_id = wp_update_post( $the_post );

		update_post_meta( $id, DROIP_PAGE_SEO_SETTINGS_META_KEY, $seo_settings );
		update_post_meta( $id, DROIP_PAGE_CUSTOM_CODE, $custom_code );

		$the_post       = get_post( $post_id );
		$the_post_perma = get_permalink( $id );

		wp_send_json(
			array(
				'page_name'               => $the_post->post_title,
				'page_url'                => $the_post->post_name,
				'page_desc'               => $the_post->post_excerpt,
				'post_status'               => $the_post->post_status,
				'post_url'                => str_replace( get_http_origin(), '', $the_post_perma ),
				// NB: These are sent debugging purpose, please don't remove them without being sure.
				'page_full_url'           => $the_post_perma,
				'home_url'                => home_url(),
				'origin'                  => get_http_origin(),
				'site_url'                => site_url(),
			)
		);

		die();
	}

	/**
	 * Get page settings data
	 *
	 * @return void wp_send_json
	 */
	public static function get_page_settings_data() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_id    = (int) HelperFunctions::sanitize_text(isset($_GET['id']) ? $_GET['id'] : '');

		$post       = get_post( $post_id );
		$post_title = $post->post_title;
		$slug       = $post->post_name;
		$og_image   = '';
		$page_desc  = $post->post_excerpt;

		$seo_post_data = '';
		$seo_settings_post_meta = get_post_meta( $post_id, DROIP_PAGE_SEO_SETTINGS_META_KEY, true );
		$custom_code_post_meta  = get_post_meta( $post_id, DROIP_PAGE_CUSTOM_CODE, true );

		if ( isset( $seo_settings_post_meta['openGraph']['openGraphImage']['value'] ) ) {
			$og_image = $seo_settings_post_meta['openGraph']['openGraphImage']['value'];
		} else {
			$featured_img_url = get_the_post_thumbnail_url( $post_id );
			if ( $featured_img_url ) {
				$og_image = $featured_img_url;
			}
		}

		$result = array(
			'page_name'               => ! empty( $post_title ) ? $post_title : '',
			'page_url'                => ! empty( $slug ) ? $slug : '',
			'og_image'                => ! empty( $og_image ) ? $og_image : '',
			'page_desc'               => ! empty( $page_desc ) ? $page_desc : '',
			'post_status'               => $post->post_status,
			'seo_settings'            => $seo_settings_post_meta,
			'custom_code'             => $custom_code_post_meta,
		);

		$seo_post_data = self::get_seo_post_data($post);
		$result['seo_post_data'] = $seo_post_data;

		wp_send_json($result);

		die();
	}

	/**
	 * Get custom code
	 *
	 * @return void wp_send_json
	 */
	public static function get_custom_code() {
		$post_id = HelperFunctions::get_post_id_if_possible_from_url();

		$custom_code_post_meta = get_post_meta( $post_id, DROIP_PAGE_CUSTOM_CODE, true );

		$result = $custom_code_post_meta;

		wp_send_json( $result );

		die();
	}

	/**
	 * Save custom code
	 *
	 * @return void wp_send_json
	 */
	public static function save_custom_code() {
		$post_id = HelperFunctions::get_post_id_if_possible_from_url();
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$custom_code = json_decode( stripslashes( $_POST['custom_code'] ), true );

		update_post_meta( $post_id, DROIP_PAGE_CUSTOM_CODE, $custom_code );

		wp_send_json( true );

		die();
	}

	/**
	 * Get SEO data
	 *
	 * @param object $post post object.
	 * 
	 * @return void wp_send_json
	 */
	private static function get_seo_post_data($post)
	{
		$post_conditions = get_post_meta($post->ID, DROIP_APP_PREFIX . '_template_conditions', true);
		$condition = $post_conditions[0];
		$res = array();

		$condition_type = isset($condition['type']) ? $condition['type'] : ''; // post, user, term;
		if (isset($condition['from']) && $condition['from'] === 'term') {
			$condition_type = 'term';
		}

		// check if the type is post
		if ($condition_type == 'post') {
			$res = self::get_post_type_seo_response($post, $condition);
		} elseif ($condition_type == 'user') {
			$res = self::get_user_type_seo_response();
		} elseif ($condition_type == 'term') {
			$res = self::get_term_type_seo_response();
		}

		return $res;
	}

	/**
	 * Get SEO data for post type
	 *
	 * @param object $post post object.
	 * @param array  $condition condition array.
	 *
	 * @return array
	 */
	private static function get_post_type_seo_response($post, $condition)
	{
		$curr_seo_post = $post;

		if (isset($condition['post_type']) && strpos($condition['post_type'], DROIP_CONTENT_MANAGER_PREFIX) !== false) {
			// content manager related post
			$post_parent = str_replace(DROIP_CONTENT_MANAGER_PREFIX . '_', '', $condition['post_type']);

				$args = array(
					'post_parent' => $post_parent,
					'page' => 1,
					'posts_per_page' => 1,
				);

				$res = ContentManagerHelper::get_all_child_items($args);

				if ($res && $res[0]) {
				$curr_seo_post = (object) $res[0];
			}
		}

		$res = array(
			'post_id' => isset($curr_seo_post->ID) ? $curr_seo_post->ID : '',
			'post_title' => isset($curr_seo_post->post_title) ? $curr_seo_post->post_title : '',
			'post_author' => get_the_author_meta('display_name', $curr_seo_post->post_author),
			'post_date' => get_the_date('', $curr_seo_post->ID),
			'post_time' => get_the_time('', $curr_seo_post->ID),
			'post_excerpt' => isset($curr_seo_post->post_excerpt) ? $curr_seo_post->post_excerpt : '',
			'post_meta' => isset($curr_seo_post->post_meta) ? $curr_seo_post->post_meta : '',
			'featured_image' => array(
				'url' => get_the_post_thumbnail_url($curr_seo_post->ID),
			),
		);

		// check if post has fields
		if (isset($curr_seo_post->fields)) {
			foreach ($curr_seo_post->fields as $key => $field) {
				$res[$key] = $field;
			}
		}

		return $res;
	}

	/**
	 * Get SEO data for user type
	 *
	 * @param object $post post object.
	 *
	 * @return array
	 */
	private static function get_user_type_seo_response()
	{
		// get user id from post context
		$user_id = HelperFunctions::get_user_id_if_possible_from_url();

		$user = get_user_by('ID', $user_id);

		$res = array(
			'user_id' => isset($user->ID) ? $user->ID : '',
			'user_name' => isset($user->display_name) ? $user->display_name : '',
			'user_nicename' => isset($user->user_nicename) ? $user->user_nicename : '',
			'user_login' => isset($user->user_login) ? $user->user_login : '',
			'user_email' => isset($user->user_email) ? $user->user_email : '',
			'user_registered' => isset($user->user_registered) ? $user->user_registered : '',
			'user_url' => isset($user->user_url) ? $user->user_url : '',
			'featured_image' => array(
				'url' => get_avatar_url($user->ID),
			),
		);

		return $res;
	}

	/**
	 * Get SEO data for term type
	 *
	 * @param object $post post object.
	 *
	 * @return array
	 */
	private static function get_term_type_seo_response()
	{
		// get term id from post context
		$term_id = HelperFunctions::get_term_id_if_possible_from_url();

		$term = get_term($term_id);

		$res = array(
			'term_id' => isset($term->term_id) ? $term->term_id : '',
			'term_name' => isset($term->name) ? $term->name : '',
			'term_slug' => isset($term->slug) ? $term->slug : '',
			'featured_image' => array(
				'url' => get_term_meta($term->term_id, 'thumbnail_id', true),
			),
		);

		return $res;
	}
}