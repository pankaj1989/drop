<?php

/**
 * Plugin update events handler
 *
 * @package droip
 */

namespace Droip\Manager;

use Droip\Ajax\Page;
use Droip\Ajax\Taxonomy;
use Droip\Ajax\Users;
use Droip\HelperFunctions;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Do some task on WordPress template redirection
 */
class TemplateRedirection
{

	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct()
	{
		/* update the the content with our droip data if page is droip */
		add_filter('template_include', array($this, 'load_page_template_to_check_staging'), PHP_INT_MAX);
		
		add_action('login_form_login', [$this, 'redirect_to_custom_login_if_has_droip_utility_page']);
		add_action('login_form_register', [$this, 'redirect_to_custom_register_if_has_droip_utility_page']);
		add_action('login_form_lostpassword', [$this, 'redirect_to_custom_lostpassword_if_has_droip_utility_page']);
		add_action('login_form_rp', [$this, 'redirect_to_custom_rp_if_has_droip_utility_page']);
		add_action('login_form_resetpass', [$this, 'redirect_to_custom_resetpass_if_has_droip_utility_page']);

		add_filter('post_link', [$this, 'custom_utility_page_link'], 10, 2);

		add_action('init', [$this, 'droip_utility_pages_rewrite_rules']);
		add_filter('query_vars', [$this, 'droip_utility_pages_query_vars']);
	}

	public function custom_utility_page_link($permalink, $post) {
		if($post && $post->post_type == DROIP_APP_PREFIX . '_utility'){
			return home_url('/'.$post->post_name);
		}
		return $permalink;
	}
	public function redirect_to_custom_login_if_has_droip_utility_page() {
      $this->check_and_redirect_to_custom_utility_page('login');
  }
	public function redirect_to_custom_register_if_has_droip_utility_page() {
			$this->check_and_redirect_to_custom_utility_page('sign_up');
  }
	public function redirect_to_custom_lostpassword_if_has_droip_utility_page() {
			$this->check_and_redirect_to_custom_utility_page('forgot_password');
  }
	public function redirect_to_custom_rp_if_has_droip_utility_page() {
			$this->check_and_redirect_to_custom_utility_page('reset_password');
  }
	public function redirect_to_custom_resetpass_if_has_droip_utility_page() {
			$this->check_and_redirect_to_custom_utility_page('forgot_password');
  }

	private function check_and_redirect_to_custom_utility_page($type){
			$utility_pages = Page::fetch_list(DROIP_APP_PREFIX . '_utility', true,  array('publish'));
			foreach ($utility_pages as $key => $page) {
				$utility_page_type = $page['utility_page_type'];
				if ($utility_page_type == $type) {
            // Get current GET parameters
            $query_args = $_GET;

            // Base redirect URL
            $url = site_url('/' . $page['slug']);

            // Append existing GET params if any
            if (!empty($query_args)) {
                $url = add_query_arg($query_args, $url);
            }

            wp_redirect($url);
            exit; // Always call exit after wp_redirect to stop execution
        }
			}
	}


	public function load_page_template_to_check_staging($original) {
		$staging_version = isset( $_GET['staging_version'] ) ? intval(HelperFunctions::sanitize_text($_GET['staging_version'])) : false;
		$nonce = HelperFunctions::sanitize_text( isset( $_GET['nonce'] ) ? $_GET['nonce'] : 'false' );
		$preview_staging_version = ($staging_version && wp_verify_nonce( $nonce, 'droip_preview_staging_nonce' )) ? $staging_version : false;
		return $this->load_page_template($original, $preview_staging_version);
	}

	public function droip_utility_pages_query_vars($vars) {
    $vars[] = 'droip_utility_page_type';
    $vars[] = 'droip_utility_page_id';
    return $vars;
	}
	public function droip_utility_pages_rewrite_rules() {
		$utility_pages = Page::fetch_list(DROIP_APP_PREFIX . '_utility', true,  array('publish'));
		foreach ($utility_pages as $key => $page) {
			$utility_page_type = $page['utility_page_type'];
			$id = $page['id'];
			$slug = $page['slug'];
			if( $utility_page_type !== '404' ){
				// Add custom rewrite rule for login
				add_rewrite_rule("^$slug$", "index.php?droip_utility_page_type=$utility_page_type&droip_utility_page_id=$id", 'top');
			}
		}
		flush_rewrite_rules(true);
	}

	/**
	 * Load droip page template
	 * it will include the template file insted of original template file
	 * $loadForIframe = true if load for iframe
	 *
	 * @param string $original wp action for template file load.
	 * @return string template name.
	 */
	public function load_page_template($original, $staging_version = false)
	{
		$post_id = HelperFunctions::get_post_id_if_possible_from_url();
		$post = null;
		if($post_id){
			//this is for template and utility preview
			$post = get_post($post_id);
			if ($post && $post->post_type === DROIP_APP_PREFIX . '_template') {
				//this is for template preview.(droip_template post type)
				$template_content = apply_filters('the_content', get_the_content(null, false, $post_id));
				
				$custom_data = array(
					DROIP_APP_PREFIX . '_template_content' => $template_content, // Example: Get the current post ID
					DROIP_APP_PREFIX . '_template_id' => $post->ID,
				);
				// Set a global variable with custom data to make it available in the template
				set_query_var(DROIP_APP_PREFIX . '_custom_data', $custom_data);
				
				if (file_exists(DROIP_FULL_CANVAS_TEMPLATE_PATH)) {
					$original = DROIP_FULL_CANVAS_TEMPLATE_PATH;
				}
			}else if ($post && $post->post_type === DROIP_APP_PREFIX . '_utility') {
				//this is for template preview.(droip_template post type)
				$custom_post_content = apply_filters('the_content', get_the_content(null, false, $post_id));
				
				$custom_data = array(
					DROIP_APP_PREFIX . '_custom_post_content' => $custom_post_content, // Example: Get the current post ID
					DROIP_APP_PREFIX . '_custom_post_id' => $post->ID,
				);
				// Set a global variable with custom data to make it available in the template
				set_query_var(DROIP_APP_PREFIX . '_custom_data', $custom_data);
				
				if (file_exists(DROIP_FULL_CANVAS_TEMPLATE_PATH)) {
					$original = DROIP_FULL_CANVAS_TEMPLATE_PATH;
				}
			}
		}

		if($original !== DROIP_FULL_CANVAS_TEMPLATE_PATH){
			$context = HelperFunctions::get_current_page_context(); //{id, type}
			if(!empty($context)){
				switch ($context['type']) {
					case 'user':{
						$user = Users::get_user_by_id($context['id']);
						$template = HelperFunctions::find_template_for_this_context('user', $user);
						if ($template) {
							$options = [];
							$options['user'] = $user;
							$original = $this->set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original, $staging_version);
						}
						break;
					}
					case 'post':{
						$template = HelperFunctions::find_template_for_this_context('post', $post);
						if($template){
							$options = [];	
							$original = $this->set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original, $staging_version);
						}else{
							if (is_page()) {
								$template_name = get_post_meta($post_id, '_wp_page_template', true);
								if (! empty($template_name) && $template_name !== $original && DROIP_FULL_CANVAS_TEMPLATE_PATH === $template_name) {
									add_action('wp_enqueue_scripts', array(new HelperFunctions(), 'remove_theme_style')); //we can remove it. need to rethink. dequeue_all_except_my_plugin
									$original = $template_name;
								}
							}
						}
						break;
					}
					case 'term': {
						$term = Taxonomy::get_term_by_id($context['id']);
						
						$template = HelperFunctions::find_template_for_this_context('term', $term);
						
						if($template){
							$options = [];
							$options['term'] = $term;
							$original = $this->set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original, $staging_version);
						}
						break;
					}


					case '404':{
						$template = HelperFunctions::find_utility_page_for_this_context( '404', 'type');
						$options = [];
						$original = $this->set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original, $staging_version);
						break;
					}
					case 'droip_utility':{
						$droip_utility_page_type = $context['droip_utility_page_type'];
						$droip_utility_page_id = $context['droip_utility_page_id'];
						if(HelperFunctions::check_utility_page_visibility_condition($droip_utility_page_type)){
							$template = HelperFunctions::find_utility_page_for_this_context( $droip_utility_page_id, 'id' );
							$options = [];
							$original = $this->set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original, $staging_version);
						}
						break;
					}
					default:{
						break;
					}
				}
			}
		}


		global $droip_custom_header, $droip_custom_footer;
		$droip_custom_header = HelperFunctions::get_page_custom_section('header');
		$droip_custom_footer = HelperFunctions::get_page_custom_section('footer');
		
		if($original !== DROIP_FULL_CANVAS_TEMPLATE_PATH ){
			if($droip_custom_header || $droip_custom_footer){
				// $original = DROIP_FULL_CANVAS_TEMPLATE_PATH;
			}
		}

		return $original;
	}
	private function set_template_or_post_content_for_droip_full_canvas_template($template, $options, $original_template_path, $staging_version = false){
		if(!$template) return $original_template_path;

		$droip_data = HelperFunctions::is_droip_type_data($template['id'], $staging_version);

		if ($droip_data) {

			$params = array( 
				'blocks' => $droip_data['blocks'],
				'style_blocks' => $droip_data['styles'],
				'root' => 'body',
				'post_id' => $template['id'],
				'options' => $options
			 );


			$template_content = HelperFunctions::get_html_using_preview_script($params);
			// $template_content .= HelperFunctions::get_page_popups_html(); //need to check all popup front end render logic
			$custom_data = array();

			if($template['post_type'] === DROIP_APP_PREFIX . '_utility'){
				$custom_data = array(
					DROIP_APP_PREFIX . '_custom_post_content' => $template_content, // Example: Get the current post ID
					DROIP_APP_PREFIX . '_custom_post_id' => $template['id'],
				);
			}else{
				$custom_data = array(
					DROIP_APP_PREFIX . '_template_content' => $template_content, // Example: Get the current post ID
					DROIP_APP_PREFIX . '_template_id' => $template['id'],
				);
			}
			
			// Set a global variable with custom data to make it available in the template
			set_query_var(DROIP_APP_PREFIX . '_custom_data', $custom_data);

			add_filter('droip_assets_should_load', function () { return true;} );
			if (file_exists(DROIP_FULL_CANVAS_TEMPLATE_PATH)) {
				return DROIP_FULL_CANVAS_TEMPLATE_PATH;
			}

		}
		return $original_template_path;
	}
}