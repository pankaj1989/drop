<?php
/**
 * Plugin init events handler
 *
 * @package droip
 */

namespace Droip\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax\Media;
use Droip\Ajax\WpAdmin;
use Droip\Frontend\Preview\Preview;
use Droip\HelperFunctions;

/**
 * Do some task during plugin activation
 */
class PluginInitEvents {


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'document_title_parts', [$this, 'change_document_title'] );
		add_action( 'init', array( $this, 'plugins_initial_tasks' ) );
		add_filter( 'upload_mimes', array( $this, 'cc_mime_types' ) );
		add_filter( 'big_image_size_threshold', '__return_false' );
		add_theme_support( 'post-thumbnails' );

		add_filter( 'wp_handle_upload_prefilter', array( new Media(), 'droip_handle_upload_prefilter' ) );
		add_filter( 'wp_generate_attachment_metadata', array( new Media(), 'droip_convert_sizes_to_webp' ) );
		add_filter( 'droip_html_generator', array( $this, 'droip_html_generator_wrap' ), 10, 2 );
		add_filter( 'droip_template_finder', array( new HelperFunctions(), 'find_template_for_this_context' ), 10, 2 );
	}


	public function droip_html_generator_wrap($s, $post_id) {
		$staging_version = isset( $_GET['staging_version'] ) ? intval(HelperFunctions::sanitize_text($_GET['staging_version'])) : false;
		$nonce = HelperFunctions::sanitize_text( isset( $_GET['nonce'] ) ? $_GET['nonce'] : 'false' );
		$preview_staging_version = ($staging_version && wp_verify_nonce( $nonce, 'droip_preview_staging_nonce' )) ? $staging_version : false;
		return HelperFunctions::droip_html_generator($s, $post_id, $preview_staging_version);
	}

	/**
	 * Filters the document title parts based on custom SEO settings or context-specific templates.
	 *
	 * @param array $title_parts_array The original title parts array.
	 * @return array Modified title parts array.
	 */
	public function change_document_title($title_parts_array)
	{
		$post_id      = HelperFunctions::get_post_id_if_possible_from_url();

		$context = HelperFunctions::get_current_page_context();
		if ($context && isset($context['type'])) {
			switch ($context['type']) {
				case '404': {
						$template = HelperFunctions::find_utility_page_for_this_context('404', 'type');
						if ($template && isset($template['id'])) {
							$post_id = $template['id'];
						}
						break;
					}
				case 'droip_utility': {
						$post_id = $context['droip_utility_page_id'];
						break;
					}
				default: {
						break;
					}
			}
		}

		if ($post_id) {

			$template_data = HelperFunctions::get_template_data_if_current_page_is_droip_template();
			if($template_data){
				$seo_settings = get_post_meta( $template_data['template_id'], DROIP_PAGE_SEO_SETTINGS_META_KEY, true );
			}else{
				$seo_settings = get_post_meta($post_id, DROIP_PAGE_SEO_SETTINGS_META_KEY, true);
			}

			if ($seo_settings && isset($seo_settings['seoSettings'], $seo_settings['seoSettings']['seoTitleTag'], $seo_settings['seoSettings']['seoTitleTag']['value'])) {
				$seo_title = Preview::getSeoValue($post_id, $seo_settings['seoSettings']['seoTitleTag']['value']);
				$title_parts_array['title'] = $seo_title;
			}
		}

		return $title_parts_array;
	}

	/**
	 * Register custom post type , mime types and theme page templates
	 *
	 * @return void
	 */
	public function plugins_initial_tasks() {
		// register_post_type( 'droip_page', array( 'public' => true ) );
		add_filter( 'theme_page_templates', array( $this, 'add_page_template_to_dropdown' ) );
		load_plugin_textdomain( DROIP_TEXT_DOMAIN, false, DROIP_PLUGIN_REL_URL . '/languages' );
	}

	/**
	 * Filter hook upload_mimes.
	 *
	 * @param array $mimes wp mimes.
	 * @return array
	 */
	public function cc_mime_types( $mimes ) {
		$data = WpAdmin::get_common_data(true);
		if ( isset( $data['json_upload'] ) && $data['json_upload'] === true ) {
			$mimes['json'] = 'text/plain';
		}
		if ( isset( $data['svg_upload'] ) && $data['svg_upload'] === true ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Add page templates.
	 *
	 * @param  array $templates  The list of page templates.
	 *
	 * @return array  $templates  The modified list of page templates.
	 */
	public function add_page_template_to_dropdown( $templates ) {
		$templates[ DROIP_FULL_CANVAS_TEMPLATE_PATH ] = DROIP_APP_NAME . ' Full Canvas';
		return $templates;
	}
}