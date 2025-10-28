<?php


namespace ComponentLib;

define('DROIP_COMPONENT_LIBRARY_APP_PREFIX', 'DroipComponentLibrary');
define('DROIP_COMPONENT_LIBRARY_ROOT_URL', plugin_dir_url(__FILE__));
define('DROIP_COMPONENT_LIBRARY_ROOT_PATH', plugin_dir_path(__FILE__));

require_once DROIP_COMPONENT_LIBRARY_ROOT_PATH . '/controller/CompLibFormHandler.php';
require_once DROIP_COMPONENT_LIBRARY_ROOT_PATH . '/controller/ShowUserMetadata.php';
require_once DROIP_COMPONENT_LIBRARY_ROOT_PATH . '/controller/ElementGenerator.php';

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class DroipComponentLibrary
{
  private $component_lib_forms = array();

  public function __construct()
  {
    $this->init();
    add_filter('droip_element_generator_' . DROIP_COMPONENT_LIBRARY_APP_PREFIX, array($this, 'element_generator'), 10, 2);
  }

  public function init()
  {
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $action = sanitize_text_field(isset($_GET['action']) ? $_GET['action'] : null);
    if ('droip' === $action) {
      $load_for = sanitize_text_field(isset($_GET['load_for']) ? wp_unslash($_GET['load_for']) : null);
      if ('droip-iframe' !== $load_for) {
        add_action('wp_enqueue_scripts', array($this, 'load_editor_assets'), 1);
      }
    }
  }

  public function load_editor_assets()
  {
    wp_enqueue_script(DROIP_COMPONENT_LIBRARY_APP_PREFIX . '-editor', DROIP_COMPONENT_LIBRARY_ROOT_URL . 'assets/js/' . 'editor.min.js', array(), false, array('in_footer' => true));
    wp_add_inline_script(
      DROIP_COMPONENT_LIBRARY_APP_PREFIX . '-editor',
      'const ' . DROIP_COMPONENT_LIBRARY_APP_PREFIX . ' = ' . json_encode(array(
        'base_url' => DROIP_COMPONENT_LIBRARY_ROOT_URL,
      )),
      'before'
    );
    add_action('wp_enqueue_scripts', function () {
      global $droip_editor_assets;
      $droip_editor_assets['scripts'][] = DROIP_COMPONENT_LIBRARY_APP_PREFIX . '-editor';
    }, 50);
  }

  public function add_component_library_script($script_tags)
  {
    $value = $this->component_lib_forms;
    $val = wp_json_encode($value);
    $script = "var " . DROIP_COMPONENT_LIBRARY_APP_PREFIX . " = window." . DROIP_COMPONENT_LIBRARY_APP_PREFIX . " === undefined? {form: $val, root_url:'" . DROIP_COMPONENT_LIBRARY_ROOT_URL . "'} : {..." . DROIP_COMPONENT_LIBRARY_APP_PREFIX . ", form:{...(" . DROIP_COMPONENT_LIBRARY_APP_PREFIX . ".form || {}), ...$val}};";

    $script_tags .= "<script data='" . DROIP_COMPONENT_LIBRARY_APP_PREFIX . "-elements-property-vars'>$script</script>";

    return $script_tags;
  }

  public function load_element_scripts_and_styles()
  {
    add_filter(DROIP_APP_PREFIX . '_add_script_tags', array($this, 'add_component_library_script'));
    //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $action = sanitize_text_field(isset($_GET['action']) ? $_GET['action'] : null);
    if ('droip' !== $action) {
      add_action('wp_enqueue_scripts', function () {
        wp_enqueue_style(DROIP_COMPONENT_LIBRARY_APP_PREFIX, DROIP_COMPONENT_LIBRARY_ROOT_URL . 'assets/css/' . 'main.min.css');
        wp_enqueue_script(DROIP_COMPONENT_LIBRARY_APP_PREFIX, DROIP_COMPONENT_LIBRARY_ROOT_URL . 'assets/js/' . 'preview.min.js', array(), false, array('in_footer' => true));
      });
    }
  }

  public function element_generator($string, $props)
  {
    $this->load_element_scripts_and_styles();
    $props['component_lib_forms'] = $this->component_lib_forms;
    $hide = false;
    if (
      'droip-login-error' === $props['element']['name'] ||
      'droip-register-error' === $props['element']['name'] ||
      'droip-forgot-password-error' === $props['element']['name'] ||
      'droip-change-password-error' === $props['element']['name'] ||
      'droip-retrieve-username-error' === $props['element']['name']
    ) {
      $hide = true;
    }
    $eg = new ElementGenerator($props);
    $gen = $eg->generate_common_element($hide);
    $this->component_lib_forms = $eg->component_lib_forms;
    return $gen;
  }
}


new DroipComponentLibrary();
