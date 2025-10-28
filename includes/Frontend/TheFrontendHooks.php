<?php
/**
 * Front end post hooks and content controller
 *
 * @package droip
 */

namespace Droip\Frontend;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;

/**
 * TheFrontendHooks class
 */
class TheFrontendHooks
{
    /**
     * Flag for where from call this instance
     */
    private $call_from = 'front-end';

    public function __construct($call_from)
    {
        $this->call_from = $call_from;
        add_action('wp_enqueue_scripts', [$this, 'load_assets']);
        add_action('wp_body_open', [$this, 'load_custom_header'], 1, 1); // call if template has get_header method.
        add_action('wp_footer', [$this, 'load_custom_footer'], 1, 1);    // call if template has get_header method.
        add_action('wp_footer', [$this, 'add_before_body_tag_end']);
        add_action('template_redirect', [$this, 'may_be_change_header_footer']);
    }

    public function may_be_change_header_footer()
    {
        ob_start([$this, 'check_and_change_header_and_footer']);
    }
    public function check_and_change_header_and_footer($html)
    {
        global $droip_custom_header, $droip_custom_footer;
        if ($droip_custom_header) {
          $html = preg_replace('#<header(?![^>]*\sdata-droip=["\']).*?>.*?</header>#is', '
<!-- droip-custom-header will
  be loaded -->', $html);
}
if ($droip_custom_footer) {
$html = preg_replace('#<footer(?![^>]*\sdata-droip=["\']).*?>.*?</footer>#is', '
  <!-- droip-custom-footer will be loaded
      -->', $html);
  }
  return $html;
  }

  public function load_assets()
  {
  wp_enqueue_script(DROIP_APP_PREFIX, DROIP_ASSETS_URL . 'js/droip.min.js', ['wp-i18n'], DROIP_VERSION, true);
  wp_enqueue_style(DROIP_APP_PREFIX, DROIP_ASSETS_URL . 'css/droip.min.css', null, DROIP_VERSION);
  }

  /**
  * Load custom header
  *
  * @param string $header header text.
  * @return void
  */
  public function load_custom_header($header)
  {
  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  echo HelperFunctions::get_page_custom_section('header');
  }

  /**
  * Load custom footer
  *
  * @param string $footer footer text.
  * @return void
  */
  public function load_custom_footer($footer)
  {
  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  echo HelperFunctions::get_page_custom_section('footer');
  }

  public function add_before_body_tag_end()
  {
  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  $s = '';

  $template_data = HelperFunctions::get_template_data_if_current_page_is_droip_template();
  $context = HelperFunctions::get_current_page_context();
  $url_arr = HelperFunctions::get_post_url_arr_from_post_id(HelperFunctions::get_post_id_if_possible_from_url());
  $s .= HelperFunctions::get_view_port_lists();
  $s .= HelperFunctions::get_droip_css_variables_data();
  $s .= '<script id="' . DROIP_APP_PREFIX . '-api-and-nonce">
  window.wp_droip = {
    ajaxUrl: "' . esc_url($url_arr['ajax_url']) . '",
    restUrl: "' . esc_url($url_arr['rest_url']) . '",
    siteUrl: "' . esc_url($url_arr['site_url']) . '",
    apiVersion: "v1",
    postId: "' . esc_attr($url_arr['post_id']) . '",
    nonce: "' . esc_attr($url_arr['nonce']) . '",
    call_from: "' . esc_attr($this->call_from) . '",
    templateId: "' . esc_attr($template_data ? $template_data['template_id'] : false) . '",
    context: ' . json_encode($context) . '
  };
  </script>';
  // smooth scroll
  $s .= HelperFunctions::get_smooth_scroll_script();

  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  echo $s;
  }

  }