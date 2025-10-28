<?php

/**
 * FormValidator class
 * 
 * This class is responsible for validating form data
 *
 * @package droip
 */

namespace Droip\ExportImport;

use Droip\Ajax\UserData;
use Droip\API\ContentManager\ContentManagerHelper;
use Droip\HelperFunctions;
use Droip\Staging;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class TemplateExport
{

  private $discarded_meta = ['_edit_lock', '_edit_last'];
  public $asset_urls = [];
  private $asset_urls_tracker = [];
  private $only_published_pages = true;

  public function __construct()
  {

  }

  public function export($is_assets_with, $only_published_pages = true){
    $environment = [
      'asset_urls_tracker' => $this->asset_urls_tracker,
      'asset_urls' => $this->asset_urls,
      'template_data' => ['exportId' => uniqid('', true)],
      'only_published_pages' => $only_published_pages,
      'queue' => [
        'pages_symbols_popups_templates_utility_pages',
        'viewPorts_customFonts',
        'globalStyleBlocks_variables',
        'contentManager',
        'contentManagerRefFields',
        'siteInfo',
      ],
    ];

    if($is_assets_with){
      $environment['queue'][] = 'assetUrls';
    }

    HelperFunctions::update_global_data_using_key( 'droip_project_export', $environment );
    return ['status'=>'init', 'queue' =>  $environment['queue']];
  }
  
  public function process(){
    $environment = HelperFunctions::get_global_data_using_key( 'droip_project_export' );
    if(!$environment){
      return ['status'=> false, 'message' =>'Nothing found to export'];
    }
    $site_name = get_bloginfo('name');
    $template_data = $environment['template_data'];
    $template_data['variable_prefix'] = $site_name;
    $this->only_published_pages = $environment['only_published_pages'];

    $formattedString = preg_replace('/[^a-zA-Z ]/', '', $site_name); // Remove numbers & special symbols
    $formattedString = str_replace(' ', '_', strtolower($formattedString));
    $template_data['batch_id'] = $formattedString;
    $template_data['prefix'] = $formattedString;

    $queue = $environment['queue'];
    if ( count($queue) > 0 ) {
      $this->asset_urls_tracker = $environment['asset_urls_tracker'];
      $this->asset_urls = $environment['asset_urls'];
      
      $current_task = array_shift($queue);
      switch ( $current_task ) {
        case 'pages_symbols_popups_templates_utility_pages':{
          $template_data['pages'] = $this->get_all_pages();
          $template_data['symbols'] = $this->get_all_symbols();
          $template_data['popups'] = $this->get_all_popups();
          $template_data['templates'] = $this->get_all_templates();
          $template_data['utility_pages'] = $this->get_all_utility_pages();
          break;
        }
        case 'viewPorts_customFonts':{
          $template_data['viewPorts'] = $this->get_view_ports();
          $template_data['customFonts'] = $this->get_custom_fonts();
          break;
        }
        case 'globalStyleBlocks_variables':{
          $template_data['globalStyleBlocks'] = $this->get_global_style_blocks();
          $template_data['variables'] = UserData::get_droip_variable_data();
          break;
        }
        case 'contentManager': {
            $template_data['contentManager'] = $this->get_all_content_manager_data();
            break;
          }
        case 'contentManagerRefFields': {
            $template_data['contentManagerRefFields'] = $this->get_all_content_manager_ref_fields();
            break;
          }
        case 'assetUrls':{
          $this->extract_asset_url_from_template_data($template_data);
          $template_data['assetUrls'] = $this->asset_urls_tracker;
          break;
        }

        case 'siteInfo':{
          $template_data['siteInfo'] = array(
            'siteName' => get_bloginfo('name'),
            'siteUrl' => get_bloginfo('url'),
            'siteDescription' => get_bloginfo('description'),
            'siteLogo' => get_site_icon_url(),
            'page_on_front' => get_option('page_on_front'),
            'show_on_front' => get_option('show_on_front'),
          );
          break;
        }
        default:
          # code...
          break;
      }

      $environment['queue'] = $queue;
      $environment['asset_urls_tracker'] = $this->asset_urls_tracker;
      $environment['asset_urls'] = $this->asset_urls;
      $environment['template_data'] = $template_data;
      HelperFunctions::update_global_data_using_key( 'droip_project_export', $environment );
      return ['status'=>'exporting', 'queue' => $queue, 'done' => $current_task];
    }else{
      HelperFunctions::update_global_data_using_key( 'droip_project_export', false );
      $zip_file_url = $this->export_and_get_url($template_data);
      return ['status'=>'done', 'queue' => $queue, 'zip_file_url'=> $zip_file_url];
    }
  }

  private function export_and_get_url($template_data)
  {
    // creating a zip file name for template export.
    $site_name = get_bloginfo('name');
    // Check if $site_name is empty or not found
    if (empty($site_name)) {
      $site_name = 'droip-template'; // Fallback name
    }
    // Convert the site name to a slug format
    $zip_filename = strtolower(str_replace(' ', '-', $site_name));
    $zip_file_url = $this->make_zip_and_get_url($zip_filename, $template_data);

    return $zip_file_url;
  }
  private function extract_asset_url_from_template_data($template_data)
  {
    $this->extract_asset_url_from_content_manager($template_data['contentManager']);
    $this->extract_asset_url_from_pages($template_data['pages']);
    $this->extract_asset_url_from_template($template_data['templates']);
    $this->extract_asset_url_from_popups($template_data['popups']);
    $this->extract_asset_url_from_symbols($template_data['symbols']);
    $this->extract_asset_url_from_string(json_encode($template_data['globalStyleBlocks']));
  }

  private function extract_asset_url_from_string($string){
    if (!$string)
      return;
    $string = stripslashes($string);
    // Define a regular expression pattern to match any image URLs
    $pattern = '/https?:\/\/[^\s]+\/[^\s]+\.(jpg|jpeg|png|gif|svg|webp)/i';
    // Find all image URLs matching the pattern in the string
    preg_match_all($pattern, $string, $matches);
    // Output the found image URLs
    $image_urls = $matches[0];
    foreach ($image_urls as $key => $url) {
      $this->push_to_asset_urls_tracker(false, $url);
    }
  }

  private function extract_asset_url_from_content_manager($contentManager)
  {
    $types = ['image', 'video', 'gallery'];

    foreach ($contentManager as $key => $collection) {
      $asset_field_ids = []; // [{type: 'image', id: 'droip_cm_field_952_dp23nqfx'}]
      $meta = $collection->meta;

      if (isset($meta['droip_cm_fields']) && !empty($meta['droip_cm_fields'])) {
        $fields = maybe_unserialize($meta['droip_cm_fields'][0]);

        //droip_cm_field_952_dp23nqfx
        if (is_array($fields) && !empty($fields)) {
          foreach ($fields as $field) {
            if (isset($field['type']) && in_array($field['type'], $types)) {
              $asset_field_ids[] = array(
                'type' => $field['type'],
                'id' => 'droip_cm_field_' . $collection->ID . '_' . $field['id']
              );
            }
          }
        }
      }

      if (count($collection->children) > 0) {
        foreach ($collection->children as $collection_data) {
          $meta = $collection_data->meta;

          foreach ($asset_field_ids as $asset_field) {
            if (isset($meta[$asset_field['id']]) && !empty($meta[$asset_field['id']])) {
              $asset = maybe_unserialize($meta[$asset_field['id']][0]);
              $this->track_asset_and_push_to_tracker($asset, $asset_field['type']);
            }
          }
        }
      }
    }
  }

  private function track_asset_and_push_to_tracker($asset, $type = 'image')
  {
    if ($type === 'gallery' && is_array($asset)) {
      foreach ($asset as $item) {
        if (!empty($item) && isset($item['id'], $item['url'])) {
          $this->push_to_asset_urls_tracker($item['id'], $item['url']);
        }
      }
    } elseif (!empty($asset) && isset($asset['id'], $asset['url'])) {
      $this->push_to_asset_urls_tracker($asset['id'], $asset['url']);
    }
  }

  private function extract_asset_url_from_pages($pages)
  {
    foreach ($pages as $page) {
      $meta = $page->meta;
      if (isset($meta['droip']) && !empty($meta['droip'])) {
        $droip_data = maybe_unserialize($meta['droip'][0]);

        if (is_array($droip_data) && !empty($droip_data)) {
          $this->exact_asset_url_from_blocks($droip_data['blocks'], 'pages');
        }
      }
      if(!empty($meta['droip_global_style_block_random'])){
        $droip_global_style_block_random = maybe_unserialize($meta['droip_global_style_block_random'][0]);
        $this->extract_asset_url_from_string(json_encode($droip_global_style_block_random));
      }
    }
  }

  private function extract_asset_url_from_template($templates)
  {
    foreach ($templates as $template) {
      $meta = $template->meta;
      if (isset($meta['droip']) && !empty($meta['droip'])) {
        $droip_data = maybe_unserialize($meta['droip'][0]);

        if (is_array($droip_data) && !empty($droip_data)) {
          $this->exact_asset_url_from_blocks($droip_data['blocks'], 'templates');
        }
      }
      if(!empty($meta['droip_global_style_block_random'])){
        $droip_global_style_block_random = maybe_unserialize($meta['droip_global_style_block_random'][0]);
        $this->extract_asset_url_from_string(json_encode($droip_global_style_block_random));
      }
    }
  }

  private function extract_asset_url_from_popups($popups)
  {
    foreach ($popups as $popup) {
      $meta = $popup->meta;

      if (isset($meta['droip']) && !empty($meta['droip'])) {
        $droip_data = maybe_unserialize($meta['droip'][0]);
        if (is_array($droip_data) && !empty($droip_data)) {
          $this->exact_asset_url_from_blocks($droip_data, 'popups');
        }
      }
    }
  }

  private function extract_asset_url_from_symbols($symbols)
  {
    foreach ($symbols as $symbol) {

      $meta = $symbol->meta;
      if (isset($meta['droip']) && !empty($meta['droip'])) {
        $droip_data = maybe_unserialize($meta['droip'][0]);

        if (is_array($droip_data) && !empty($droip_data)) {
          $this->exact_asset_url_from_blocks($droip_data['data'], 'symbols');
          $this->extract_asset_url_from_string(json_encode($droip_data['styleBlocks']));
        }
      }
    }
  }



  private function exact_asset_url_from_blocks($blocks, $context)
  {
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];

    // $asset_urls = [];

    foreach ($blocks as $key => $block) {
      if ($block['name'] === 'image' && $block['properties']['attributes']['src']) {
        $this->push_to_asset_urls_tracker($block['properties']['wp_attachment_id'], $block['properties']['attributes']['src']);
      }

      if ($block['name'] === 'video' && $block['properties']['attributes']['src']) {
        $this->push_to_asset_urls_tracker(false, $block['properties']['attributes']['src']);
        if ($block['properties']['thumbnail']['url']) {
          $this->push_to_asset_urls_tracker($block['properties']['thumbnail']['wp_attachment_id'], $block['properties']['thumbnail']['url']);
        }
      }

      if ($block['name'] === 'lottie' && $block['properties']['lottie']['src']) {
        $this->push_to_asset_urls_tracker(false, $block['properties']['lottie']['src']);
      }
      if ($block['name'] === 'lightbox' && $block['properties']['lightbox']['thumbnail']['src']) {
        $this->push_to_asset_urls_tracker(false, $block['properties']['lightbox']['thumbnail']['src']);

        $lightbox_media = $block['properties']['lightbox']['media'];

        foreach ($lightbox_media as $key => $media_item) {
          if ($media_item['sources']['original']) {
            $this->push_to_asset_urls_tracker(false, $media_item['sources']['original']);
          }
        }
      }
    }
  }

  private function count($array) {
    if(isset($array) && is_array($array)){
      return count($array);
    }
    return 0;
  }

  private function make_zip_and_get_url($zip_filename, $template_data)
  {
    try {
      if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
      }

      global $wp_filesystem;
      $zip = new \ZipArchive();

      $upload_dir = wp_upload_dir();

      // Step 1: create zip file path and json file path
      $zip_file_path = $upload_dir['basedir'] . "/$zip_filename.zip";

      // site-settings.json
      $site_settings_filename = 'site-settings.json';
      $site_settings_file_path = $upload_dir['basedir'] . '/' . $site_settings_filename;
      $site_settings_data = [];
      $site_settings_data['exportId'] = $template_data['exportId'];
      $site_settings_data['variable_prefix'] = $template_data['variable_prefix'];
      $site_settings_data['batch_id'] = $template_data['batch_id'];
      $site_settings_data['prefix'] = $template_data['prefix'];
      $site_settings_data['siteInfo'] = $template_data['siteInfo'];
      $site_settings_file_content = json_encode($site_settings_data, JSON_PRETTY_PRINT);
      $site_settings_file_write = $wp_filesystem->put_contents(
        $site_settings_file_path,
        $site_settings_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // assets.json
      $assets_filename = 'assets.json';
      $assets_file_path = $upload_dir['basedir'] . '/' . $assets_filename;
      $assets_data = [];
      $assets_data['assetUrls'] = $template_data['assetUrls'];
      $assets_file_content = json_encode($assets_data, JSON_PRETTY_PRINT);
      $assets_file_write = $wp_filesystem->put_contents(
        $assets_file_path,
        $assets_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // pages.json
      $pages_filename = 'pages.json';
      $pages_file_path = $upload_dir['basedir'] . '/' . $pages_filename;
      $pages_data = [];
      $pages_data['pages'] = $template_data['pages'];
      $pages_data['templates'] = $template_data['templates'];
      $pages_data['utility_pages'] = $template_data['utility_pages'];
      $pages_file_content = json_encode($pages_data, JSON_PRETTY_PRINT);
      $pages_file_write = $wp_filesystem->put_contents(
        $pages_file_path,
        $pages_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // popups.json
      $popups_filename = 'popups.json';
      $popups_file_path = $upload_dir['basedir'] . '/' . $popups_filename;
      $popups_data = [];
      $popups_data['popups'] = $template_data['popups'];
      $popups_file_content = json_encode($popups_data, JSON_PRETTY_PRINT);
      $popups_file_write = $wp_filesystem->put_contents(
        $popups_file_path,
        $popups_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // content-manager.json
      $content_manager_filename = 'content-manager.json';
      $content_manager_file_path = $upload_dir['basedir'] . '/' . $content_manager_filename;
      $content_manager_data = [];
      $content_manager_data['contentManager'] = $template_data['contentManager'];
      $content_manager_file_content = json_encode($content_manager_data, JSON_PRETTY_PRINT);
      $content_manager_file_write = $wp_filesystem->put_contents(
        $content_manager_file_path,
        $content_manager_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // content-manager-ref-fields.json
      $content_manager_ref_fields_filename = 'content-manager-ref-fields.json';
      $content_manager_ref_fields_file_path = $upload_dir['basedir'] . '/' . $content_manager_ref_fields_filename;
      $content_manager_ref_fields_data = [];
      $content_manager_ref_fields_data['contentManagerRefFields'] = $template_data['contentManagerRefFields'];
      $content_manager_ref_fields_file_content = json_encode($content_manager_ref_fields_data, JSON_PRETTY_PRINT);
      $content_manager_ref_fields_file_write = $wp_filesystem->put_contents(
        $content_manager_ref_fields_file_path,
        $content_manager_ref_fields_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );


      // symbols.json
      $symbols_filename = 'symbols.json';
      $symbols_file_path = $upload_dir['basedir'] . '/' . $symbols_filename;
      $symbols_data = [];
      $symbols_data['symbols'] = $template_data['symbols'];
      $symbols_file_content = json_encode($symbols_data, JSON_PRETTY_PRINT);
      $symbols_file_write = $wp_filesystem ->put_contents(
          $symbols_file_path,
          $symbols_file_content,
          FS_CHMOD_FILE // predefined mode settings for WP files
      );

      // styles.json
      $styles_filename = 'styles.json';
      $styles_file_path = $upload_dir['basedir'] . '/' . $styles_filename;
      $styles_data = [];
      $styles_data['viewPorts'] = $template_data['viewPorts'];
      $styles_data['customFonts'] = $template_data['customFonts'];
      $styles_data['globalStyleBlocks'] = $template_data['globalStyleBlocks'];
      $styles_data['variables'] = $template_data['variables'];
      $styles_file_content = json_encode($styles_data, JSON_PRETTY_PRINT);
      $styles_file_write = $wp_filesystem->put_contents(
        $styles_file_path,
        $styles_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );



      // single.json
      $single_filename = 'single.json';
      $single_file_path = $upload_dir['basedir'] . '/' . $single_filename;
      $single_data = [];
      $single_data['exportId'] = $template_data['exportId'];
      $single_data['batch_id'] = $template_data['batch_id'];
      
      $template_info = [
        'symbols' => $this->count($template_data['symbols']),
        'popups' => $this->count($template_data['popups']),
        'pages' => $this->count($template_data['pages']),
        'templates' => $this->count($template_data['templates']),
        'utilityPages' => $this->count($template_data['utility_pages']),
        'variables' => $this->count($template_data['variables']['data']),
        'images' => $this->count($template_data['assetUrls']),
        'fonts' => $this->count($template_data['customFonts']),
        'contentManager' => $this->count($template_data['contentManager']),
        'contentManager' => array_reduce($template_data['contentManager'], function($sum, $item) {
            return $sum + (isset($item->children) ? count($item->children) : 0);
        }, 0),
        'contentManagerRefFields' => $this->count($template_data['contentManagerRefFields']),
        'contentManager_list' => array_map(function($item) {
          return $item->post_title . " (" . (isset($item->children) ? count($item->children) : 0) . ")";
        }, $template_data['contentManager']),
      ];

      $single_data['template_info'] = $template_info;

      $single_data['pages'] = $this->extractPageData($template_data['pages']);
      $single_data['templates'] = $this->extractPageData($template_data['templates']);
      $single_data['utility_pages'] = $this->extractPageData($template_data['utility_pages']);
      $single_file_content = json_encode($single_data, JSON_PRETTY_PRINT);
      $single_file_write = $wp_filesystem->put_contents(
        $single_file_path,
        $single_file_content,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );


      if (!$site_settings_file_write || !$assets_file_write || !$pages_file_write || !$popups_file_write || !$content_manager_file_write || !$symbols_file_write || !$styles_file_write || !$single_file_write || !$content_manager_ref_fields_file_write) {
        // throw new \Exception('Failed to write template file');
         wp_send_json_error('Failed to write template file');
      }

      // Step 3: Open the zip file
      if (true !== $zip->open($zip_file_path, \ZipArchive::CREATE)) {
        // throw new \Exception('Failed to create zip file');
        wp_send_json_error('Failed to write template file');
      }

      // Step 4: Add file to the zip file
      foreach ($template_data['assetUrls'] as $key => $asset_item) {
        $url = $asset_item['url'];
        $file_name = basename($url); // Extract only the filename (e.g., "image.jpg")

        $file_path = $upload_dir['basedir'] . str_replace($upload_dir['baseurl'], '', $url);
        $zip_path = "assets/" . $file_name; // Place all files inside "media/" without subdirectories

        // Check if file exists
        if (!file_exists($file_path)) {
            // error_log("File not found: " . $file_path);
            continue; // Skip to the next file
        }

        // Try adding the file to the ZIP
        if (!$zip->addFile($file_path, $zip_path)) {
            // error_log("Failed to add file to ZIP: " . $file_path);
        }
      }


      if(false === $zip->addFile($site_settings_file_path, $site_settings_filename)) {
        // throw new \Exception('Failed to add assets.json file to zip');
        wp_send_json_error('Failed to write site-settings.json file');
      }

      if(isset($template_data['assetUrls']) && !empty($template_data['assetUrls'])) {
        if(false === $zip->addFile($assets_file_path, $assets_filename)) {
          // throw new \Exception('Failed to add assets.json file to zip');
          wp_send_json_error('Failed to write assets.json file');
        }
      }

      if(false === $zip->addFile($pages_file_path, $pages_filename)) {
        // throw new \Exception('Failed to add pages.json file to zip');
        wp_send_json_error('Failed to write pages.json file');
      }

      if(false === $zip->addFile($popups_file_path, $popups_filename)) {
        // throw new \Exception('Failed to add popups.json file to zip');
        wp_send_json_error('Failed to write popups.json file');
      }

      if(false === $zip->addFile($content_manager_file_path, $content_manager_filename)) {
        // throw new \Exception('Failed to add content-manager.json file to zip');
        wp_send_json_error('Failed to write content-manager.json file');
      }

      if (false === $zip->addFile($content_manager_ref_fields_file_path, $content_manager_ref_fields_filename)) {
        // throw new \Exception('Failed to add content-manager-ref-fields.json file to zip');
        wp_send_json_error('Failed to write content-manager-ref-fields.json file');
      }

      if(false === $zip->addFile($symbols_file_path, $symbols_filename)) {
        // throw new \Exception('Failed to add symbols.json file to zip');
        wp_send_json_error('Failed to write symbols.json file');

      }
      if(false === $zip->addFile($styles_file_path, $styles_filename)) {
        // throw new \Exception('Failed to add styles.json file to zip');
        wp_send_json_error('Failed to write styles.json file');
      }
      if(false === $zip->addFile($single_file_path, $single_filename)) {
        // throw new \Exception('Failed to add styles.json file to zip');
        wp_send_json_error('Failed to write styles.json file');
      }

    
      $zip->close();

      // Step 6: remove the template file after zip file is created
      unlink($site_settings_file_path);
      unlink($assets_file_path);
      unlink($pages_file_path);
      unlink($popups_file_path);
      unlink($content_manager_file_path);
      unlink($content_manager_ref_fields_file_path);
      unlink($symbols_file_path);
      unlink($styles_file_path);
      unlink($single_file_path);



      // Step 7: Download the created zip file
      return home_url("/?page-export=true&file-name=$zip_filename.zip");
    } catch (\Exception $e) {
      // return false;
      wp_send_json_error('Failed to write template file');
    }
  }

  function extractPageData($pages) {
    $result = [];
    foreach ($pages as $page) {
        if (isset($page->post_title) && isset($page->post_name)) {
            $result[] = [
                'post_title' => $page->post_title,
                'post_name'  => $page->post_name
            ];
        }
    }
    return $result; // Returns an array of page data
  }


  private function get_global_style_blocks()
  {
    return HelperFunctions::get_global_data_using_key(DROIP_GLOBAL_STYLE_BLOCK_META_KEY);
  }

  private function get_all_pages()
  {
    return $this->get_posts_with_meta('page', ['publish']);
  }

  private function get_all_popups()
  {
    return $this->get_posts_with_meta(DROIP_APP_PREFIX . '_popup', ['publish', 'draft']);
  }

  private function get_all_templates()
  {
    return $this->get_posts_with_meta(DROIP_APP_PREFIX . '_template', ['publish']);
  }
  
  private function get_all_utility_pages()
  {
    return $this->get_posts_with_meta(DROIP_APP_PREFIX . '_utility', ['publish']);
  }

  private function get_all_symbols()
  {
    return $this->get_posts_with_meta(DROIP_APP_PREFIX . '_symbol', ['draft']);
  }

  private function get_all_content_manager_data()
  {
    $parent_posts =  $this->get_posts_with_meta(DROIP_APP_PREFIX . '_cm', ['publish']);

    foreach ($parent_posts as $key => $parent_post) {
      $parent_posts[$key]->children = $this->get_posts_with_meta(DROIP_APP_PREFIX . '_cm_' . $parent_post->ID, ['publish']);
    }

    return $parent_posts;
  }

  private function get_view_ports(){
    $control = HelperFunctions::get_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY );

    if(!$control){
      $control = [];
    }
    if(!isset($control['viewport'])){
      $control['viewport'] = HelperFunctions::get_initial_view_ports();
    }
    return $control['viewport'];
  }

  private function get_custom_fonts()
  {
    $custom_fonts = HelperFunctions::get_global_data_using_key(DROIP_USER_CUSTOM_FONTS_META_KEY);
    return $custom_fonts ? $custom_fonts : [];
  }

  private function get_posts_with_meta($post_type, $post_status = ['publish'])
  {
    $all_posts = get_posts(array(
      'post_type' => $post_type,
      'posts_per_page' => -1,
      'post_status' => $post_status
    ));
    foreach ($all_posts as $key => $post) {
      $post_meta = get_post_meta($post->ID);
      foreach ($this->discarded_meta as $discarded_meta_key) {
        unset($post_meta[$discarded_meta_key]);
      }
      if($this->only_published_pages) {
        $stage_metas = Staging::get_all_stage_related_meta_names($post->ID);
        foreach ($stage_metas as $discarded_meta_key) {
          unset($post_meta[$discarded_meta_key]);
        }
      }
      $post->meta = $post_meta;

      // Get the post's content manager fields if available
      // $cm_ref_fields = ContentManagerHelper::get_post_cm_ref_fields($post->ID);
      // if (isset($cm_ref_fields) && !empty($cm_ref_fields)) {
      //   $post->cm_ref_fields = $cm_ref_fields;
      // }

      $all_posts[$key] = $post;
    }

    return $all_posts;
  }

  private function push_to_asset_urls_tracker($attachment_id, $url){
    if(!$attachment_id){
      $attachment_id = attachment_url_to_postid($url);
    }
    if($attachment_id){
      $this->asset_urls_tracker[$attachment_id] = [
        'attachment_id' => $attachment_id,
        'url' => $url
      ];
    }
  }

  private function get_all_content_manager_ref_fields()
  {
    $cm_ref_fields = ContentManagerHelper::get_post_cm_ref_fields();

    return $cm_ref_fields;
  }
}