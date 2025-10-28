<?php

/**
 * FormValidator class
 * 
 * This class is responsible for validating form data
 *
 * @package droip
 */

namespace Droip\ExportImport;

use Droip\Ajax\Media;
use Droip\Ajax\Page;
use Droip\Ajax\UserData;
use Droip\API\ContentManager\ContentManagerHelper;
use Droip\HelperFunctions;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

//TODO: need to check lottie element

class TemplateImport
{
  private $zip_file_path = null;
  private $batch_id = null;
  private $staging = false;
  private $prefix = null;
  private $variable_id_tracker = []; //this value is need to change the variable for every page or symbol
  private $post_id_tracker = [];
  private $delete_zip_after_import = true;
  private $droip_data_link_tracker = [];
  private $droip_cm_ref_field_post_tracker = [];

  
  private $asset_upload_tracker = [];  // [ 'old_attachment_id':{'new_attachment_id': , 'url': 'new_url', old_url:'old_url'}]
  // private $new_asset_urls = [];
  private $asset_upload_batch_size = 5;
  private $content_manager_batch_size = 300; // Batch size for content manager

  public function __construct()
  {
    
  }

  private function count($array) {
    if(isset($array) && is_array($array)){
      return count($array);
    }
    return 0;
  }

  public function import($zip_file_path,  $delete_zip_after_import = true, $selectedMode='default'){
    $this->delete_zip_after_import = $delete_zip_after_import;

    $temp_folder_path = HelperFunctions::get_temp_folder_path();
    HelperFunctions::delete_directory($temp_folder_path);
    $this->save_environment_data(false);

    if(HelperFunctions::is_remote_url($zip_file_path)){
      $file_name_new = uniqid('', true) . '.zip'; // 'random.ext'
      $zip_file_path = HelperFunctions::download_zip_from_remote($zip_file_path, $file_name_new);
      if($zip_file_path){
        $this->zip_file_path = $zip_file_path;
      }else{
        wp_send_json_error('Failed to download zip file');
      }
    }else{
      $this->zip_file_path = $zip_file_path;
    }

    $zip = new \ZipArchive;
    $res = $zip->open($this->zip_file_path);

    if ($res === TRUE) {

      $filtered_zip_path = HelperFunctions::filterZipFile($zip, $this->zip_file_path);
      if(!$filtered_zip_path){
        return false;
      }
      $zip->close();
  
      // Reopen the filtered ZIP file for extraction
      $res = $zip->open($filtered_zip_path);
      if ($res === TRUE) {
        $temp_folder_path = HelperFunctions::get_temp_folder_path();

        $zip->extractTo($temp_folder_path);
        $zip->close();

        if ($this->delete_zip_after_import) {
            unlink($this->zip_file_path);
        }

        $json_data = [];

        // site-settings.json
        $site_settings_file = $temp_folder_path . '/site-settings.json';
        $site_settings_json = file_get_contents($site_settings_file);
        $site_settings_data = json_decode($site_settings_json, true);

        $json_data['exportId'] = $site_settings_data['exportId'];
        $json_data['variable_prefix'] = $site_settings_data['variable_prefix'];
        $json_data['batch_id'] = $site_settings_data['batch_id'];
        $json_data['staging'] = isset($site_settings_data['staging']) ? $site_settings_data['staging'] : false;
        $json_data['siteInfo'] = $site_settings_data['siteInfo'];
        $json_data['prefix'] = isset($site_settings_data['prefix']) ? $site_settings_data['prefix'] : '';


        // assets.json
        $assets_file = $temp_folder_path . '/assets.json';
        $assets_json = file_get_contents($assets_file);
        $assets_data = json_decode($assets_json, true);
        $json_data['assetUrls'] = isset($assets_data['assetUrls']) ? $assets_data['assetUrls'] : [];

        // pages.json
        $pages_file = $temp_folder_path . '/pages.json';
        $pages_json = file_get_contents($pages_file);
        $pages_data = json_decode($pages_json, true);

        $json_data['pages'] = $pages_data['pages'];
        $json_data['templates'] = $pages_data['templates'];
        $json_data['utility_pages'] = $pages_data['utility_pages'];


        // popups.json
        $popups_file = $temp_folder_path . '/popups.json';
        $popups_json = file_get_contents($popups_file);
        $popups_data = json_decode($popups_json, true);
        $json_data['popups'] = $popups_data['popups'];

        // content-manager.json
        $content_manager_file = $temp_folder_path . '/content-manager.json';
        $content_manager_json = file_get_contents($content_manager_file);
        $content_manager_data = json_decode($content_manager_json, true);
        $json_data['contentManager'] = $content_manager_data['contentManager'];

        // content-manager-ref-fields.json
        $content_manager_ref_fields_file = $temp_folder_path . '/content-manager-ref-fields.json';
        $content_manager_ref_fields_json = file_get_contents($content_manager_ref_fields_file);
        $content_manager_ref_fields_data = json_decode($content_manager_ref_fields_json, true);
        $json_data['contentManagerRefFields'] = $content_manager_ref_fields_data['contentManagerRefFields'];

        // symbols.json
        $symbols_file = $temp_folder_path . '/symbols.json';
        $symbols_json = file_get_contents($symbols_file);
        $symbols_data = json_decode($symbols_json, true);
        $json_data['symbols'] = $symbols_data['symbols'];


        // styles.json
        $styles_file = $temp_folder_path . '/styles.json';
        $styles_json = file_get_contents($styles_file);
        $styles_data = json_decode($styles_json, true);
        $json_data['viewPorts'] = $styles_data['viewPorts'];
        $json_data['customFonts'] = $styles_data['customFonts'];
        $json_data['globalStyleBlocks'] = $styles_data['globalStyleBlocks'];
        $json_data['variables'] = $styles_data['variables'];
        $json_data['variables']['defaultMode'] = $selectedMode;

        if (empty($json_data) || !isset($json_data['pages'], $json_data['assetUrls'], $json_data['variables'], $json_data['globalStyleBlocks'], $json_data['customFonts'], $json_data['viewPorts'], $json_data['contentManager'], $json_data['templates'])) {
            wp_send_json_error('Failed to import template, Upload Droip Exported Zip file');
        }

        $environment = [
            'temp_folder_path' => $temp_folder_path,
            'json_data' => $json_data,
            'variable_id_tracker' => $this->variable_id_tracker,
            'post_id_tracker' => $this->post_id_tracker,
            'asset_upload_tracker' => $this->asset_upload_tracker,
            'droip_data_link_tracker' => $this->droip_data_link_tracker,
            'droip_cm_ref_field_post_tracker' => $this->droip_cm_ref_field_post_tracker,
        ];

        $template_info = [
          'symbols' => $this->count($json_data['symbols']),
          'popups' => $this->count($json_data['popups']),
          'pages' => $this->count($json_data['pages']),
          'templates' => $this->count($json_data['templates']),
          'utilityPages' => $this->count($json_data['utility_pages']),
          'variables' => $this->count($json_data['variables']['data']),
          'images' => $this->count($json_data['assetUrls']),
          'fonts' => $this->count($json_data['customFonts']),
          'contentManager' => $this->count($json_data['contentManager']),
          'contentManagerRefFields' => $this->count($json_data['contentManagerRefFields']),
          'overwrite_pages' =>  array_map(fn($page) => $page["post_title"], $json_data['pages']),
          'contentManager_list' => array_map(fn($item) => $item["post_title"] . " (" . count($item["children"]) . ")", $json_data['contentManager'])
        ];

        $environment['queue'] = [];

        if($template_info['images'] > 0){
          $environment['queue'][] = 'assetUrls';
        }
        if($template_info['variables'] > 0){
          $environment['queue'][] = 'variables';
        }
        $environment['queue'][] = 'viewPorts_customFonts_globalStyleBlocks';
        if($template_info['contentManager'] > 0){
          $environment['queue'][] = 'contentManager';
        }

        if ($template_info['contentManagerRefFields'] > 0) {
          $environment['queue'][] = 'contentManagerRefFields';
        }

        if($template_info['symbols'] > 0){
          $environment['queue'][] = 'symbols';
        }
        if($template_info['popups'] > 0){
          $environment['queue'][] = 'popups';
        }
        if($template_info['pages'] > 0){
          $environment['queue'][] = 'pages';
        }
        if($template_info['templates'] > 0){
          $environment['queue'][] = 'templates';
        }
        if($template_info['utilityPages'] > 0){
          $environment['queue'][] = 'utility_pages';
        }
        $environment['queue'][] = 'siteInfo';
        
        $batch_id =  $json_data['batch_id'];
        $args= [
          'post_type'      =>  ['page', DROIP_APP_PREFIX . '_template', DROIP_APP_PREFIX . '_utility'],
          'post_status' => ['draft', 'publish', 'future'],
          'meta_key'       => 'droip_imported_batch_id',
          'meta_value'     => $batch_id,
          'posts_per_page' => -1, // Fetch all matching posts
        ];

        $posts = get_posts($args);

        $response = ['status'=> true, 'queue' => $environment['queue'], 'template_info' => $template_info];

        if(!empty($posts)){
          $response['is_template_exit'] = true;
          $environment['is_template_exit'] = true;
        }
  
        $this->save_environment_data($environment);

        // Cleanup temporary filtered ZIP
        unlink($filtered_zip_path);

        return $response;
      }
    }
    return false;
  }

  public function check_existing_template_data($data) {
    $all_pages = array_merge($data['pages'], $data['templates'], $data['utility_pages']);

    // Initialize arrays for existing items
    $existing_pages = [];
    $existing_templates = [];
    $existing_utility_pages = [];

    // Fetch all pages, templates, and utility pages from the database
    $total_pages = get_posts([
        'post_type'      => ['page', DROIP_APP_PREFIX . '_template', DROIP_APP_PREFIX . '_utility'],
        'posts_per_page' => -1,
        'fields'         => 'ids' // Fetch only IDs for performance
    ]);

    // Create a lookup array with post_name
    $existing_pages_data = [];
    foreach ($total_pages as $id) {
        $post_name = get_post_field('post_name', $id);
        $post_title = get_post_field('post_title', $id);
        $post_type = get_post_field('post_type', $id);

        $existing_pages_data[$post_name] = [
            'post_title' => $post_title,
            'post_type'  => $post_type
        ];
    }

    // Check for existing pages, templates, and utility pages
    foreach ($all_pages as $page) {
        $post_name = $page['post_name'];

        if (isset($existing_pages_data[$post_name])) {
            $post_title = $existing_pages_data[$post_name]['post_title'];
            $post_type = $existing_pages_data[$post_name]['post_type'];

            $matched_data = [
                'post_title' => $post_title,
                'post_name'  => $post_name
            ];

            if ($post_type === 'page') {
                $existing_pages[] = $matched_data;
            } elseif ($post_type === DROIP_APP_PREFIX . '_template') {
                $existing_templates[] = $matched_data;
            } elseif ($post_type === DROIP_APP_PREFIX . '_utility') {
                $existing_utility_pages[] = $matched_data;
            }
        }
    }

    // Return categorized existing data with post_title and post_name
    $result =  array_merge( $existing_pages, $existing_templates, $existing_utility_pages);
    wp_send_json([
        'success' => true,
        'data' => $result
    ]);
  }

  private function save_environment_data($environment){
      if(!empty($environment) && $environment !== false){
        $all_droip_pages = $environment['json_data']['pages'];
        $all_templates_pages = $environment['json_data']['templates'];
        $all_utility_pages = $environment['json_data']['utility_pages'];

        // remove all pages, templates, and utility pages from the environment data
        unset($environment['json_data']['pages']);
        unset($environment['json_data']['templates']);
        unset($environment['json_data']['utility_pages']);
        
        HelperFunctions::update_global_data_using_key('droip_project_import', $environment);


        HelperFunctions::update_global_data_using_key('droip_project_import_pages', $all_droip_pages);
        HelperFunctions::update_global_data_using_key('droip_project_import_templates', $all_templates_pages);
        HelperFunctions::update_global_data_using_key('droip_project_import_utility', $all_utility_pages);
      }else{
        HelperFunctions::update_global_data_using_key('droip_project_import', false);
        HelperFunctions::update_global_data_using_key('droip_project_import_pages', false);
        HelperFunctions::update_global_data_using_key('droip_project_import_templates', false);
        HelperFunctions::update_global_data_using_key('droip_project_import_utility', false);
      }
   }

  private function get_environment_data(){
      $environment = HelperFunctions::get_global_data_using_key( 'droip_project_import' );
      $all_droip_pages = HelperFunctions::get_global_data_using_key( 'droip_project_import_pages' );
      $all_templates_pages = HelperFunctions::get_global_data_using_key( 'droip_project_import_templates' );
      $all_utility_pages = HelperFunctions::get_global_data_using_key( 'droip_project_import_utility' );

      if(!empty($environment) && $environment !== false){
        $environment['json_data']['pages'] = $all_droip_pages;
        $environment['json_data']['templates'] = $all_templates_pages;
        $environment['json_data']['utility_pages'] = $all_utility_pages;
      }

      return $environment;
  }


  public function process(){
    $environment = $this->get_environment_data();
    if(!$environment){
      return ['status'=> false, 'message' =>'Nothing found to import'];
    }
    $queue = $environment['queue'];
    $temp_folder_path = $environment['temp_folder_path'];
    $is_template_exit = isset($environment['is_template_exit']) ? $environment['is_template_exit'] : false;
    $json_data = $environment['json_data'];
    $this->batch_id = $json_data['batch_id'];
    $this->staging = $json_data['staging'];
    $this->prefix = isset($json_data['prefix']) ? $json_data['prefix'] : '';

    if($is_template_exit){
      // delete all posts
      $args= [
        'post_type'      => ['page', DROIP_APP_PREFIX . '_template', DROIP_APP_PREFIX . '_utility', DROIP_APP_PREFIX . '_cm', DROIP_APP_PREFIX . '_symbol'],
        'post_status'    => ['draft', 'publish', 'future'],
        'meta_key'       => 'droip_imported_batch_id',
        'meta_value'     => $this->batch_id,
        'posts_per_page' => -1, // Fetch all matching posts
      ];

      $posts = get_posts($args);

      if(!empty($posts)){
        foreach ($posts as $post) {
         wp_delete_post($post->ID, true);
        }
      }

      // delete all other variables, content manager, etc.
      $args= [
        'post_type'      => 'any',
        'meta_key'       => 'droip_imported_batch_id',
        'post_status'    => ['draft', 'publish', 'future'],
        'meta_value'     => $this->batch_id,
        'posts_per_page' => -1, // Fetch all matching posts
      ];

      $posts = get_posts($args);

      // delete all other 
      if(!empty($posts)){
        foreach ($posts as $post) {
         wp_delete_post($post->ID, true);
        }
      }

      // delete all attachment
      $args= [
        'post_type'      => ['attachment'], 
        'post_status'    => ['inherit', 'publish'], 
        'meta_key'       => 'droip_imported_batch_id',
        'meta_value'     => $this->batch_id,
        'posts_per_page' => -1, // Fetch all matching posts
      ];
  
      $posts = get_posts($args);
      if(!empty($posts)){
        foreach ($posts as $post) {
          wp_delete_post($post->ID, true);
        }
      }

      $environment['is_template_exit'] = false;
      // save updated environment metadata
      $this->save_environment_data($environment);
    }

    if ( count($queue) > 0 ) {
      $this->variable_id_tracker = $environment['variable_id_tracker'];
      $this->post_id_tracker = $environment['post_id_tracker'];
      $this->asset_upload_tracker = $environment['asset_upload_tracker'];
      $this->droip_data_link_tracker = $environment['droip_data_link_tracker'];
      $this->droip_cm_ref_field_post_tracker = $environment['droip_cm_ref_field_post_tracker'];
    
      $current_task = array_shift($queue);

      switch ( $current_task ) {
        case 'assetUrls':{
          $this_batch_assets = array();
          $left_assets = array();
          $count = 0;
          if (count($json_data['assetUrls']) > $this->asset_upload_batch_size) {
            foreach ($json_data['assetUrls'] as $key => $a) {
              if($count < $this->asset_upload_batch_size){
                $this_batch_assets[$key] = $a;
              }else{
                $left_assets[$key] = $a;
              }
              $count++;
            }

            array_unshift($queue, $current_task);
          }else{
            $this_batch_assets = $json_data['assetUrls'];
          }
          $this->import_assets($this_batch_assets);
          $json_data['assetUrls'] = $left_assets;
          break;
        }
        // viewPorts
        case 'viewPorts_customFonts_globalStyleBlocks':{
          $this->import_view_ports($json_data['viewPorts']);  
          $this->import_custom_fonts($json_data['customFonts']);
          $this->import_global_style_blocks($json_data['globalStyleBlocks']);
          break;
        }

        case 'variables': {
          $this->import_variables($json_data['variables'], $json_data['variable_prefix'], $json_data['variables']['defaultMode']);
          break;
        }

        case 'contentManager':{
          $this_batch_content = [];
          $left_content = [];
          $count = 0;

          if (count($json_data['contentManager']) > $this->content_manager_batch_size) {
              foreach ($json_data['contentManager'] as $key => $c) {
                  if ($count < $this->content_manager_batch_size) {
                      $this_batch_content[] = $c;
                  } else {
                      $left_content[] = $c;
                  }
                  $count++;
              }
              array_unshift($queue, $current_task);
          } else {
              $this_batch_content = $json_data['contentManager'];
          }

          $this->import_content_manager($this_batch_content);
          $json_data['contentManager'] = $left_content;
          break;
        }

        case 'contentManagerRefFields': {
            $this_batch_content = [];
            $left_content = [];
            $count = 0;

            if (count($json_data['contentManagerRefFields']) > $this->content_manager_batch_size) {
              foreach ($json_data['contentManagerRefFields'] as $key => $c) {
                if ($count < $this->content_manager_batch_size) {
                  $this_batch_content[] = $c;
                } else {
                  $left_content[] = $c;
                }
                $count++;
              }
              array_unshift($queue, $current_task);
            } else {
              $this_batch_content = $json_data['contentManagerRefFields'];
            }

            $this->import_content_manager_ref_fields($this_batch_content);
            $json_data['contentManagerRefFields'] = $left_content;
            break;
          }

        case 'symbols': {
          $this->import_pages($json_data['symbols']);
          break;
        }
        case 'popups':{
          $this->import_pages($json_data['popups']);
          break;
        }
        case 'pages':{
          $this->import_pages($json_data['pages']);
          break;
        }
        // templates
        case 'templates':{
          $this->import_pages($json_data['templates']);
          break;
        }
        case 'utility_pages':{
          $this->import_pages($json_data['utility_pages']);
          break;
        }
        // siteInfo
        case 'siteInfo':{
          $this->import_site_info($json_data['siteInfo']);
          $this->import_extra_info();
          break;
        }
        default:
          # code...
          break;
      }

      $environment['queue'] = $queue;
      $environment['json_data'] = $json_data;
      $environment['variable_id_tracker'] = $this->variable_id_tracker;
      $environment['post_id_tracker'] = $this->post_id_tracker;
      $environment['asset_upload_tracker'] = $this->asset_upload_tracker;
      $environment['droip_data_link_tracker'] = $this->droip_data_link_tracker;
      $environment['droip_cm_ref_field_post_tracker'] = $this->droip_cm_ref_field_post_tracker;
      $this->save_environment_data($environment);
      return ['status'=>'importing', 'queue' => $queue, 'done' => $current_task];
    }else{
      HelperFunctions::delete_directory($temp_folder_path);
      flush_rewrite_rules(true);
      $this->save_environment_data(false);
      return ['status'=>'done', 'queue' => $queue];
    }
  }

  private function import_site_info($new_site_info)
  {
    if(!empty($new_site_info['page_on_front']) && isset($this->post_id_tracker[$new_site_info['page_on_front']])){
      update_option('page_on_front', $this->post_id_tracker[$new_site_info['page_on_front']]);
    }
    if(!empty($new_site_info['show_on_front'])){
      update_option('show_on_front', $new_site_info['show_on_front']);
    }
  }
  
  private function import_extra_info()
  {
    foreach ($this->droip_data_link_tracker as $post_id => $block_ids) {
      $post = get_post($post_id);
      $droip_data = get_post_meta($post_id, 'droip', true);
      if(is_serialized($droip_data)){
        $droip_data = maybe_unserialize($droip_data);
      }
      foreach ($block_ids as $key => $block_id) {
        if($post->post_type === 'droip_symbol'){
          if(isset($droip_data['data'][$block_id]['properties']['attributes']['href'])) {
            $old_href = $droip_data['data'][$block_id]['properties']['attributes']['href'];
            if(isset($this->post_id_tracker[$old_href])) {
              $droip_data['data'][$block_id]['properties']['attributes']['href'] = $this->post_id_tracker[$old_href];
            }
          }
        }else if($post->post_type === 'droip_popup'){
          if(isset($droip_data[$block_id]['properties']['attributes']['href'])) {
            $old_href = $droip_data[$block_id]['properties']['attributes']['href'];
            if(isset($this->post_id_tracker[$old_href])) {
              $droip_data[$block_id]['properties']['attributes']['href'] = $this->post_id_tracker[$old_href];
            }
          }
        }else{
          if(isset($droip_data['blocks'][$block_id]['properties']['attributes']['href'])) {
            $old_href = $droip_data['blocks'][$block_id]['properties']['attributes']['href'];
            if(isset($this->post_id_tracker[$old_href])) {
              $droip_data['blocks'][$block_id]['properties']['attributes']['href'] = $this->post_id_tracker[$old_href];
            }
          }
        }
      }
      update_post_meta( $post_id, 'droip', $droip_data );
    }

    // now loop through the droip_cm_ref_field_post_tracker and update the post_id and ref_post_id
    foreach ($this->droip_cm_ref_field_post_tracker as $post_id_key => $tracker_post_id) {
      $new_post_id = $tracker_post_id;
      // Now update the post id and ref post id in the meta table
      $droip_cm_fields = get_post_meta($new_post_id, DROIP_CONTENT_MANAGER_PREFIX . '_fields', true);

      if (is_serialized($droip_cm_fields)) {
        $droip_cm_fields = maybe_unserialize($droip_cm_fields);
      }

      foreach ($droip_cm_fields as $field_key => $field) {
        if (isset($field['ref_collection']) && !empty($field['ref_collection'])) {
          $new_ref_post_id = $this->post_id_tracker[$field['ref_collection']];

          if ($new_post_id && $new_ref_post_id) {
            // update the post id and ref post id
            $droip_cm_fields[$field_key]['ref_collection'] = $new_ref_post_id;
          }
        }
      }

      // update the post meta
      update_post_meta($new_post_id, DROIP_CONTENT_MANAGER_PREFIX . '_fields', $droip_cm_fields);
    }
  }

  private function import_view_ports($new_view_ports){
    $control = HelperFunctions::get_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY );
    if(!$control){
      $control = array();
    }

    if(!isset($new_view_ports['list'])){
      $new_view_ports['list'] = array();
    }
    if( !isset( $control['viewport'], $control['viewport']['list'] ) ){
      $control['viewport'] = $new_view_ports;
    }else{
      $control['viewport']['list'] = (object) array_merge((array) $control['viewport']['list'], (array) $new_view_ports['list']);
    }
    HelperFunctions::update_global_data_using_key( DROIP_USER_CONTROLLER_META_KEY, $control );
    return true;
  }
  
  private function import_custom_fonts($new_fonts){
    $custom_fonts = HelperFunctions::get_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY );
    if(!$custom_fonts){
      $custom_fonts = array();
    }
    $custom_fonts =  array_merge((array) $custom_fonts, (array) $new_fonts);

    HelperFunctions::update_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY, $custom_fonts );
    return true;
  }
  private function import_global_style_blocks($new_blocks){
    $new_styles = $this->add_prefix_to_style_blocks($new_blocks); 
    $global_styles = HelperFunctions::get_global_data_using_key(DROIP_GLOBAL_STYLE_BLOCK_META_KEY);
    if(!$global_styles){
      $global_styles = array();
    }
    $global_styles = array_merge((array) $global_styles, (array) $new_styles);
    HelperFunctions::save_global_style_blocks($global_styles);
    return true;
  }

  private function add_prefix_to_style_block_class_names($name){
    if(is_array($name)){
      foreach ($name as $key => $c) {
        $name[$key] = in_array($c, DROIP_PRESERVED_CLASS_LIST) ? $c : $this->add_prefix($c);
      }
    }else{
      $name = in_array($name, DROIP_PRESERVED_CLASS_LIST) ? $name : $this->add_prefix($name);
    }
    return $name;
  }

  private function add_prefix_to_style_blocks($style_blocks){
    $new_styles = [];
    foreach ($style_blocks as $key => $block) {
      $obj = $block;
      $obj['id'] = $this->add_prefix($obj['id']);
      $obj['name'] = $this->add_prefix_to_style_block_class_names($obj['name']);
      if(isset($obj['isDefault'])){
        //remove isDefault from object
        if($obj['isDefault'] === true){
          $obj['isGlobal'] = true;
        }
        unset($obj['isDefault']);
      }
      $new_styles[$this->add_prefix($key)] = $obj;
    }

    //STRING RELATED TASK START HERE
    $style_string = json_encode($new_styles);
    $style_string = $this->update_variable_data_from_css_string( $style_string );
    $style_string = $this->update_assets_url_from_css_string( $style_string );
    
    $new_styles = json_decode($style_string, true);
    //STRING RELATED TASK END HERE
    return $new_styles;
  }

  private function update_assets_url_from_css_string($string){
    return $string; //TODO: need to change assets url
    $string = stripslashes($string);
    
    foreach ($this->asset_upload_tracker as $key => $asset) {
      // Define the pattern to match 'http://jshossen.com'
      $pattern = '/' . preg_quote($asset['old_url'], '/') . '/';
      // Define the replacement URL
      $replacement = $asset['url'];
      // Replace the old URL with the new one
      $string = preg_replace($pattern, $replacement, $string);
    }

    return $string;
  }
  
  private function update_variable_data_from_css_string($string){
    foreach ($this->variable_id_tracker as $old => $new) {
      $pattern = '/var\(--' . preg_quote($old, '/') . '\)/';
      $replacement = "var(--$new)";
      $string = preg_replace($pattern, $replacement, $string);
    }
    return $string;
  }

  private function filterVariablesByPrefix(array $data, string $prefix): array {
    $filteredData = array_map(function (array $group) use ($prefix) {
        if (!isset($group['variables']) || !is_array($group['variables'])) {
            $group['variables'] = [];
        }

        $group['variables'] = array_values(array_filter($group['variables'], function ($variable) use ($prefix) {
            return isset($variable['id']) && strpos($variable['id'], $prefix) !== 0;
        }));

        return $group;
    }, $data);

    // Remove groups that have no variables left
    return array_values(array_filter($filteredData, function ($group) {
        return !empty($group['variables']);
    }));
  }
  
  private function import_variables( $new_variables, $variable_prefix, $mode = 'default'){
    $variables = UserData::get_droip_variable_data();
    $filter_variable = [];

    if(isset($variables['data']) && !empty($variables['data'])){
      $filter_variable = $this->filterVariablesByPrefix($variables['data'], $this->batch_id);
    }

    $new_data = $new_variables['data']; // variables
    foreach ($new_data as $key => $g) {
      $prefix = '';
      if (isset($variable_prefix) && !empty($variable_prefix)) {
          $prefix = trim($variable_prefix) . ' ';
      } else {
          $prefix = 'Template ';
      }

      $new_data[$key]['title'] = $prefix . trim($new_data[$key]['title']);

      foreach ($new_data[$key]['variables'] as $key2 => $v) {
        $new_id = $this->add_prefix($v['id']);
        $this->variable_id_tracker[$v['id']] = $new_id;
        $v['id'] = $new_id;
        $mode_value = isset($v['value'][$mode]) ? $v['value'][$mode] : $v['value']['default'];

        if(isset($v['value'][$mode]) && is_string($v['value'][$mode]) && preg_match('/var\(--([a-zA-Z0-9\-]+)\)/', $v['value'][$mode], $matches) ){
           $id = $matches[1];
           $new_variable_name = $this->add_prefix($id);
           $mode_value = 'var(--' . $new_variable_name . ')';
        }else if (isset($v['value']['default']) && is_string($v['value']['default']) && preg_match('/var\(--([a-zA-Z0-9\-]+)\)/', $v['value']['default'], $matches) ){
          $id = $matches[1];
          $new_variable_name = $this->add_prefix($id);
          $mode_value = 'var(--' . $new_variable_name . ')';
        }

        $v['value'] = array('default' => $mode_value);
        $new_data[$key]['variables'][$key2] = $v;
      }
      $new_data[$key]['modes'] = [['title' => 'Default', 'key' => 'default']];
    }
    $variables['data'] = array_merge((array) $filter_variable, (array) $new_data);
    $saved_data = HelperFunctions::get_global_data_using_key( DROIP_USER_SAVED_DATA_META_KEY );
    if(!$saved_data){
      $saved_data = array();
    }
    $saved_data['variableData'] = $variables;

    HelperFunctions::update_global_data_using_key( DROIP_USER_SAVED_DATA_META_KEY, $saved_data );
    return true;
  }

  private function import_content_manager($content_manager_posts){
    foreach ($content_manager_posts as $key => $parent_post) {
      $this->insert_single_post($parent_post);
    }
  }

  private function import_content_manager_ref_fields($content_manager_ref_fields)
  {

    $updated_ref_fields = [];
    foreach ($content_manager_ref_fields as $key => $field) {
      // Check field -> post_id, field_meta_key, ref_post_id match with $this->post_id_tracker[field -> post_id] field. If match then update the $new_post['ID'] with the new post id
      $post_id = $field['post_id'];
      $field_meta_key = $field['field_meta_key'];
      $ref_post_id = $field['ref_post_id'];

      if (isset($this->post_id_tracker[$post_id])) {
        $new_post_id = $this->post_id_tracker[$post_id];
        $field['post_id'] = $new_post_id;
      }

      if (isset($this->post_id_tracker[$ref_post_id])) {
        $new_ref_post_id = $this->post_id_tracker[$ref_post_id];
        $field['ref_post_id'] = $new_ref_post_id;
      }

      // Check if the field_meta_key is in the $this->post_id_tracker array. If match then update the $new_post['ID'] with the new post id
      // Get post ID from the field_meta_key like "droip_cm_field_123_abc" => 123
      $parent_post_id = '';
      $parent_post_id_matches = [];
      if (preg_match('/droip_cm_field_(\d+)_/', $field_meta_key, $parent_post_id_matches)) {
        $parent_post_id = $parent_post_id_matches[1];
      }

      // get the field id from the field_meta_key like "droip_cm_field_123_abc" => abc
      $field_id = '';
      $field_id_matches = [];
      if (preg_match('/droip_cm_field_(\d+)_([a-zA-Z0-9]+)/', $field_meta_key, $field_id_matches)) {
        $field_id = $field_id_matches[2];
      }

      // check if the parent_post_id is in the $this->post_id_tracker array. If match then update the $new_post['ID'] with the new post id
      if (!empty($parent_post_id) && isset($this->post_id_tracker[$parent_post_id])) {
        $new_parent_post_id = $this->post_id_tracker[$parent_post_id];

        $field['field_meta_key'] = DROIP_CONTENT_MANAGER_PREFIX . '_field_' . $new_parent_post_id . '_' . $field_id;
      }

      // Add the updated field to the array
      $updated_ref_fields[] = $field;
    }

    // Now insert the updated ref fields to the database table name wp_droip_cm_reference
    global $wpdb;
    foreach ($updated_ref_fields as $key => $field) {
      $wpdb->insert(
        $wpdb->prefix . 'droip_cm_reference',
        array(
          'post_id' => $field['post_id'],
          'field_meta_key' => $field['field_meta_key'],
          'ref_post_id' => $field['ref_post_id']
        ),
        array('%d', '%s', '%d')
      );
    }
  }

  private function import_pages($new_pages){
    foreach ($new_pages as $key => $new_page) {
      $this->insert_single_post($new_page);
    }
  }

  private function add_post_all_meta($post){
    $meta = $post['meta'];
    $post_id = $post['ID'];

    foreach ($meta as $key => $value) {
      $v = $value[0];
      $updated_key_value = $this->handle_droip_related_meta($key, $v, $post);
      update_post_meta( $post_id, $updated_key_value[0], $updated_key_value[1] );
    }
  }


  private function update_element_properties_symbol_id($data, $post_id) {
    foreach ($data as $key => $block) {
      if (isset($block['properties'], $block['properties']['symbolId'])) {
          $block['properties']['symbolId'] = $post_id; // Update symbolId with post_id
          $data[$key] = $block;
          break;
      }
    }
    return $data;
  }

  private function handle_droip_related_meta($key, $v, $post){
    // '_wp_page_template', 'droip_used_style_block_ids', 'droip', 'droip_global_style_block_random', 'droip_used_style_block_ids_random', 'droip_page_seo_settings', 'droip_variable_mode', 'droip_template_conditions','droip_cm_fields', 'droip_cm_basic_fields'
    $post_type = $post['post_type'];
    $post_parent = $post['post_parent'];
    $post_id = $post['ID'];

    if(is_serialized($v)){
      $v = maybe_unserialize($v);
    }
    switch ($key) {
      case 'droip':
        if($post_type === 'droip_symbol'){
          $v['data'] = $this->add_prefix_to_droip_blocks($v['data'], $post_id);
          $v['data'] = $this->update_element_properties_symbol_id($v['data'], $post_id);
          $v['styleBlocks'] = $this->add_prefix_to_style_blocks($v['styleBlocks']);
          $v['conditions'] = $this->check_droip_conditions($v['conditions']);
        }else if($post_type === 'droip_popup'){
          $v = $this->add_prefix_to_droip_blocks($v, $post_id);
          foreach ($v as $key2 => $v2) {
            if($v2['name'] === 'popup' && isset($v2['properties'], $v2['properties']['popup'], $v2['properties']['popup'], $v2['properties']['popup']['visibilityConditions'])){
              $v2['properties']['popup']['visibilityConditions'] = $this->check_droip_conditions($v2['properties']['popup']['visibilityConditions']);
            }
            $v[$key2] = $v2;
          }
        }else{
          //template, pages
          $v['blocks'] = $this->add_prefix_to_droip_blocks($v['blocks'], $post_id);
        }
        return [$key, $v];
      case '_wp_page_template':
        return [$key, DROIP_FULL_CANVAS_TEMPLATE_PATH];
        
      case 'droip_variable_mode':
        return [$key, ''];//TODO: need to get template mode or selected mode by user
        return [$key, $this->add_prefix($v)];

      case 'droip_page_seo_settings':
        //TODO: need to update seo settings for droip_template
        return [$key, $v];

      case 'droip_used_style_block_ids':
        foreach ($v as $key2 => $value) {
          $v[$key2] = $this->add_prefix($value);
        }
        return [$key, $v];
        
      case 'droip_used_style_block_ids_random':
        foreach ($v as $key2 => $value) {
          $v[$key2] = $this->add_prefix($value);
        }
        return [$key, $v];

      case 'droip_global_style_block_random':
        $updated_blocks = $this->add_prefix_to_style_blocks($v);
        return [$key, $updated_blocks];
        
      case 'droip_template_conditions':
        $v = $this->check_droip_conditions($v);
        return [$key, $v];

      case 'droip_cm_fields':
        // check if the parent $v array field has any field['ref_collection'] if yes then add the post to the $this->droip_cm_ref_field_post_tracker array
        if (is_array($v)) {
          foreach ($v as $field_key => $field) {
            if (isset($field['ref_collection']) && !empty($field['ref_collection'])) {
              $this->droip_cm_ref_field_post_tracker[] = $post_id;
              break;
            }
          }
        }

        //TODO: need to update cm fields default assets data
        return [$key, $v];

      case 'droip_cm_basic_fields':
        return [$key, $v];

      default: {
          if (str_contains($key, 'droip_cm_field_')) {
            //thats means this is content manager item field post_meta key
            // Use preg_replace to replace the first instance of the numbers after 'droip_cm_field_' with $post_parent
            $key = preg_replace('/(droip_cm_field_)\d+/', '${1}' . $post_parent, $key);

            // get parent post fields
            $parent_post_fields = get_post_meta($post_parent, 'droip_cm_fields', true);

            // get current field id
            $field_id = '';
            $field_id_matches = [];
            if (preg_match('/droip_cm_field_(\d+)_([a-zA-Z0-9]+)/', $key, $field_id_matches)) {
              $field_id = $field_id_matches[2];
            }


            if (is_serialized($parent_post_fields)) {
              $parent_post_fields = maybe_unserialize($parent_post_fields);
            }

            $field_type = $this->get_parent_post_field_type($parent_post_fields, $field_id);

            if (isset($field_type) && $field_type === 'gallery') {
              foreach ($v as $key2 => $value) {
                $v[$key2] = $this->format_uploaded_asset($value);
              }
            } elseif (is_array($v)) {
              $v = $this->format_uploaded_asset($v);
            }
          }

          return [$key, $v];
        }
    }
  }

  private function format_uploaded_asset($value)
  {
    if (isset($value['id'], $value['url'], $value['file_extension'])) {
      $attachment_id = $this->asset_upload_tracker[$value['id']]['attachment_id'] ?? null;

      if ($attachment_id) {
        $attachment_post = get_post($attachment_id);
        if ($attachment_post) {
          $m = new Media();
          return $m->format_media_data($attachment_post);
        }
      }
    }

    // return original if no change
    return $value;
  }

  private function get_parent_post_field_type($parent_post_fields, $field_id)
  {
    foreach ($parent_post_fields as $key => $field) {
      if (isset($field['id']) && $field['id'] === $field_id) {
        return $field['type'];
      }
    }
    return '';
  }

  private function check_droip_conditions($conditions){
    foreach ($conditions as $key => $condition) {
      if(isset($condition['category']) && str_contains($condition['category'], 'droip_cm_')){
        $post_parent = str_replace('droip_cm_', '', $condition['category']);
        if(isset($this->post_id_tracker[$post_parent])){
          $post_parent = $this->post_id_tracker[$post_parent];
        }
        $condition['category'] = ContentManagerHelper::get_child_post_post_type_value($post_parent);
        if(isset($condition['apply'], $condition['apply']['to'])){
          $condition['apply']['to'] = $this->post_id_tracker[$condition['apply']['to']];
        }
      }else if(isset($condition['post_type']) && str_contains($condition['post_type'], 'droip_cm_')){
        $post_parent = str_replace('droip_cm_', '', $condition['post_type']);
        if(isset($this->post_id_tracker[$post_parent])){
          $post_parent = $this->post_id_tracker[$post_parent];
          $condition['post_type'] = ContentManagerHelper::get_child_post_post_type_value($post_parent);
        }
      }
      $conditions[$key] = $condition;
    }
    return $conditions;
  } 

  private function add_prefix_to_droip_blocks($blocks, $post_id){
    $new_data = [];
    foreach ($blocks as $key => $block) {
      $obj = $block;
      if(isset($obj['styleIds']) && count($obj['styleIds']) > 0){
        foreach ($obj['styleIds'] as $key2 => $style_id) {
          $obj['styleIds'][$key2] = $this->add_prefix($style_id);
        }
      }
      //update assets url for droip block
      $obj = $this->update_asset_url_for_droip_block($obj);

      //update symbol id for droip block
      $obj = $this->update_symbol_id_for_droip_block($obj);

      //update interaction data for droip block
      $obj = $this->update_interaction_data_for_droip_block($obj);

      //update collection post type for droip content manager
      $obj = $this->update_collection_settings_for_droip_block($obj);
      //update link dynamic url. like post id 
      $obj = $this->update_link_wp_post_id_for_droip_block($obj, $post_id);

      //TODO: need to update collection element filter data for cm (may be not need to do anything.)
      
      //
      $new_data[$key] = $obj;
    }
    return $new_data;
  }

  private function insert_single_post($new_post){
    $new_post_id = wp_insert_post(
      array(
        'post_title' => $new_post['post_title'],
        'post_content' => $new_post['post_content'],
        'post_status' => $this->staging ? 'draft': $new_post['post_status'],
        'post_name' => $new_post['post_name'],
        'post_parent' => $new_post['post_parent'],
        'menu_order' => $new_post['menu_order'],
        'post_type' => $new_post['post_type'],
      )
    );

    // assign meta tag
    if($new_post_id){
      update_post_meta($new_post_id, 'droip_imported_batch_id', $this->batch_id);
      $this->post_id_tracker[$new_post['ID']] = $new_post_id;
      $new_post['ID'] = $new_post_id;
      $this->add_post_all_meta($new_post);

      if(isset($new_post['children']) && count($new_post['children']) > 0){
        foreach ($new_post['children'] as $key => $child) {
          $child['post_parent'] = $new_post_id;
          if(str_contains($child['post_type'], 'droip_cm_')){
            $child['post_type'] = ContentManagerHelper::get_child_post_post_type_value($new_post_id);
          }
          $this->insert_single_post($child);
        }
      }
    }

    return $new_post_id;
  }

  private function import_assets($asset_urls){

    foreach ($asset_urls as $key => $asset_item) {
      $attachment_id = $asset_item['attachment_id'];

      if(empty($this->asset_upload_tracker[$attachment_id]['url'])){
        // new asset
        $new_asset = $this->upload_file($asset_item);

        if ($new_asset) {
          $this->asset_upload_tracker[$attachment_id]['attachment_id'] = $new_asset['attachment_id'];
          $this->asset_upload_tracker[$attachment_id]['url'] = $new_asset['url'];
          $this->asset_upload_tracker[$attachment_id]['old_url'] = $asset_item['url'];
        }
      }
    }
  }

  private function upload_file($asset_item)
  {
    $temp_folder_path = HelperFunctions::get_temp_folder_path();
    $asset_name = basename($asset_item['url']);
    $source_file_path = $temp_folder_path . '/assets/' . $asset_name;

    if (file_exists($source_file_path)) {
      $file_name = basename($source_file_path);

      // Upload the file
      $file_array = array(
        'name'     => $file_name,
        'tmp_name' => $source_file_path,
      );

      $_FILES['file'] = $file_array;

      $attachment_id = media_handle_upload('file', 0, array(), array('test_form' => false, 'action' => 'upload-attachment'));

      // Check if the upload was successful
      if (!is_wp_error($attachment_id)) {
        $post = get_post($attachment_id);
        update_post_meta($post->ID, 'droip_imported_batch_id', $this->batch_id);
        $new_asset = [
          'url' =>  $post->guid,
          'attachment_id' => $attachment_id,
        ];

        return  $new_asset;
      }
    }

    return null;
  }

  private function update_symbol_id_for_droip_block($block){
    if($block['name'] === 'symbol'){
      $block['properties']['symbolId'] = $this->post_id_tracker[$block['properties']['symbolId']];
    }
    return $block;
  }

  private function update_collection_settings_for_droip_block($block)
  {
    if(!$block || !isset($block['properties'], $block['properties']['dynamicContent'])) return $block;
    $dc = $block['properties']['dynamicContent'];

    if ($block['name'] === 'collection') {
      if (isset($dc['collectionType'], $dc['type']) && $dc['collectionType'] === 'posts' && str_contains($dc['type'], 'droip_cm_')) {
        $post_parent = str_replace('droip_cm_', '', $dc['type']);
        $dc['type'] = ContentManagerHelper::get_child_post_post_type_value($this->post_id_tracker[$post_parent]);

        $block['properties']['dynamicContent'] = $dc;
      } else if (isset($dc['collectionType'], $dc['type']) && $dc['collectionType'] === DROIP_CONTENT_MANAGER_PREFIX . '_multi_reference') {
        $post_parent = isset($dc['cm_ref_collection_id']) ? $dc['cm_ref_collection_id'] : '';
        if (isset($this->post_id_tracker[$post_parent])) {
          $dc['cm_ref_collection_id'] = $this->post_id_tracker[$post_parent];
        }
        $block['properties']['dynamicContent'] = $dc;
      }
    } else if (isset($dc['type']) && $dc['type'] === 'reference') {
      $post_parent = isset($dc['cm_post_id']) ? $dc['cm_post_id'] : '';

      if (isset($this->post_id_tracker[$post_parent])) {
        $dc['cm_post_id'] = $this->post_id_tracker[$post_parent];
      }

      $block['properties']['dynamicContent'] = $dc;
    }

    return $block;
  }
  
  private function update_link_wp_post_id_for_droip_block($block, $post_id){
    if( isset($block['properties'], $block['properties']['type'], $block['properties']['attributes'], $block['properties']['attributes']['href']) ){
        $href = $block['properties']['attributes']['href'];
        if (is_numeric($href) && intval($href) == $href) {
            if( isset($this->post_id_tracker[$href]) ){
                $block['properties']['attributes']['href'] = $this->post_id_tracker[$href];
            } else {
                if(isset($this->droip_data_link_tracker[$post_id])){  // Fix: Use $this->droip_data_link_tracker instead
                    $this->droip_data_link_tracker[$post_id][] = $block['id'];
                } else {
                    $this->droip_data_link_tracker[$post_id] = array($block['id']);
                }
            }
        }

        if(isset($block['properties'],$block['properties']['attributes'],$block['properties']['attributes']['popup']) ){
          $popup = $block['properties']['attributes']['popup'];
          if(isset($popup) && is_numeric($popup) && intval($popup) == $popup){
              if( isset($this->post_id_tracker[$popup]) ){
                  $block['properties']['attributes']['popup'] = $this->post_id_tracker[$popup];
              } else {
                  if(isset($this->droip_data_link_tracker[$post_id])){ 
                      $this->droip_data_link_tracker[$post_id][] = $block['id'];
                  } else {
                      $this->droip_data_link_tracker[$post_id] = array($block['id']);
                  }
              }
          }
        }
        
    }
    return $block;
  }
  
  private function update_interaction_data_for_droip_block($block){
    if( isset($block['properties']['interactions']) ){
      $block['properties']['interactions']['deviceAndClassList'] = $this->update_interaction_device_and_class_list($block['properties']['interactions']['deviceAndClassList']);

      $elementAsTrigger = $block['properties']['interactions']['elementAsTrigger'];
      foreach ($elementAsTrigger as $key => $trigger) {

        foreach ($trigger as $key2 => $singleTrigger) {

          foreach ($singleTrigger as $key3 => $customOrPreset) {

            if(isset($customOrPreset['deviceAndClassList'])){
              $customOrPreset['deviceAndClassList'] = $this->update_interaction_device_and_class_list($customOrPreset['deviceAndClassList']);
            }
            foreach ($customOrPreset['data'] as $ele_id => $singleRes) {
              $obj = $singleRes;
              if ( str_contains( $ele_id, '____info' ) ) {
                if (isset($obj['applyToClass'], $obj['styleBlockId']) && $obj['applyToClass']) {
                  $obj['styleBlockId'] = $this->add_prefix($obj['styleBlockId']);
                }
              }
              foreach ($obj as $ani_key => $animation) {
                if(isset($animation['property']) && $animation['property'] === 'class-change'){
                  if(isset($animation['end'], $animation['end']['className'], $animation['end']['className']['id'])){
                    $obj[$ani_key]['end']['className']['id'] = $this->add_prefix($animation['end']['className']['id']);
                  }
                }

                if(isset($animation['property']) && ( $animation['property'] === 'background-color' || $animation['property'] === 'color' || $animation['property'] === 'border-color')){
                  if(isset($animation['start'], $animation['start']['value'])){
                    if(str_contains($animation['start']['value'], 'var(--')){
                      // Use regex to extract the value inside var(--...)
                      preg_match('/var\(--(.*?)\)/', $animation['start']['value'], $matches);
                        $animation['start']['value'] = 'var(--'.$this->variable_id_tracker[$matches[1]].')';
                    } 
                  }

                  if(isset($animation['end'], $animation['end']['value'])){
                    if(str_contains($animation['end']['value'], 'var(--')){
                      // Use regex to extract the value inside var(--...)
                      preg_match('/var\(--(.*?)\)/', $animation['end']['value'], $matches);
                      $animation['end']['value'] = 'var(--'.$this->variable_id_tracker[$matches[1]].')';
                    }
                  }
                }
                $obj[$ani_key] = $animation;
              }

              $customOrPreset['data'][$ele_id] = $obj;
            }

            $singleTrigger[$key3] = $customOrPreset;
          }
          $trigger[$key2] = $singleTrigger;
        }

        $elementAsTrigger[$key] = $trigger;
      }
      $block['properties']['interactions']['elementAsTrigger'] = $elementAsTrigger;
    }
    return $block;
  }

  private function update_interaction_device_and_class_list($deviceAndClassList){
    if($deviceAndClassList && $deviceAndClassList['applyToClass'] && !empty($deviceAndClassList['styleBlockId'])){
      $deviceAndClassList['styleBlockId'] = $this->add_prefix($deviceAndClassList['styleBlockId']);
      $new_class_list = $this->add_prefix_to_style_block_class_names($deviceAndClassList['classList']);
      $deviceAndClassList['classList'] = $new_class_list;
    }

    return $deviceAndClassList;
  }

  private function update_asset_url_for_droip_block($block){
    // image
    if($block['name'] === 'image' && isset($block['properties']['wp_attachment_id'])){
      $block_attachment_id = $block['properties']['wp_attachment_id'];

      if(isset($this->asset_upload_tracker[$block_attachment_id])){
        $block['properties']['wp_attachment_id'] = $this->asset_upload_tracker[$block_attachment_id]['attachment_id'];
        $block['properties']['attributes']['src'] = $this->asset_upload_tracker[$block_attachment_id]['url'];
      };
    }else if($block['name'] === 'video'){
      foreach ($this->asset_upload_tracker as $key => $asset_item) {
        if($block['properties']['attributes']['src'] === $asset_item['old_url'] ){
          $block['properties']['attributes']['src'] = $asset_item['url'];
          $block['properties']['wp_attachment_id'] = $asset_item['attachment_id'];
        }

        if($block['properties']['thumbnail']['url'] === $asset_item['old_url'] ){
          $block['properties']['thumbnail']['url'] = $asset_item['url'];
          $block['properties']['thumbnail']['wp_attachment_id'] = $asset_item['attachment_id'];
        }
      }
    }else if($block['name'] === 'lottie'){

      foreach ($this->asset_upload_tracker as $key => $asset_item) {
        if($block['properties']['lottie']['src'] === $asset_item['old_url'] ){
          $block['properties']['lottie']['src'] = $asset_item['url'];
          $block['properties']['wp_attachment_id'] = $asset_item['attachment_id'];
        }
      }
    }

    else if($block['name'] === 'lightbox'){
      foreach ($this->asset_upload_tracker as $key => $asset_item) {
        if($block['properties']['lightbox']['thumbnail']['src'] === $asset_item['old_url'] ){
          $block['properties']['lightbox']['thumbnail']['src'] = $asset_item['url'];
          $block['properties']['wp_attachment_id'] = $asset_item['attachment_id'];
        }
      }

      if(!empty($block['properties']['lightbox']['media'])){
        $media = $block['properties']['lightbox']['media'];

        foreach ($media as $key => $media_item) {
          if(!empty($media_item['id']) && isset($this->asset_upload_tracker[$media_item['id']])){
            $media[$key]['id'] = $this->asset_upload_tracker[$media_item['id']]['attachment_id'];
            $media[$key]['sources']['original'] = $this->asset_upload_tracker[$media_item['id']]['url'];
          }
        }
        $block['properties']['lightbox']['media'] = $media;
      }

    }

    return $block;
  }
  
  private function add_prefix($string) {
    $prefix = $this->prefix ? $this->prefix . '_' : '';

    // If there's no prefix set, return the original string
    if (!$prefix) {
        return $string;
    }

    // Check if the string already has the correct prefix
    if (strpos($string, $prefix) === 0) {
        return $string; // Return unchanged
    }

    // If no prefix exists, simply prepend the new prefix
    return $prefix . $string;
  }

}