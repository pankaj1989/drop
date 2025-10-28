<?php

/**
 * Manage dynamic form data api calls
 *
 * @package droip
 */

namespace Droip\Ajax;

use Droip\ExportImport\TemplateExport;
use Droip\ExportImport\TemplateImport;
use Droip\HelperFunctions;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}
class TemplateExportImport
{

  public static function export(){
    $is_assets_with =  HelperFunctions::sanitize_text( isset( $_POST['is_assets_with'] ) ? $_POST['is_assets_with'] : false );
    $is_assets_with = $is_assets_with === 'true' ? true : false;

    $only_published_pages =  HelperFunctions::sanitize_text( isset( $_POST['only_published_pages'] ) ? $_POST['only_published_pages'] : true );
    $only_published_pages = $only_published_pages === 'false' ? false : true;

    $te = new TemplateExport();
    $d = $te->export($is_assets_with, $only_published_pages);
    wp_send_json_success($d);
  }

  public static function import()
  {
    set_time_limit(300);
    $file = $_FILES['file']; // zip file
    $upload_dir = wp_upload_dir();

    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_error = $file['error'];

    $file_ext = explode('.', $file_name); // ['file', 'ext']
    $file_ext = strtolower(end($file_ext)); // 'ext'

    $allowed = ['zip'];

    if (in_array($file_ext, $allowed)) {
      if ($file_error === 0) {

        $file_name_new = uniqid('', true) . '.' . $file_ext; // 'random.ext'
        $file_destination = $upload_dir['basedir'] . '/' . $file_name_new;

        if (move_uploaded_file($file_tmp, $file_destination)) {

          $ti = new TemplateImport();
          $status = $ti->import($file_destination, true);
          if($status && $status['status']){
            wp_send_json_success(['queue' => $status['queue']]);
          }else{
            wp_send_json_error('Failed to import template');
          }
        } else {
          wp_send_json_error('Something went wrong');
        }
      } else {
        wp_send_json_error('Zip File upload Failed');
      }
    } else {
      wp_send_json_error('File type not allowed, Upload Droip Exported Zip file');
    }
  }
  
  
  public static function import_using_url()
  {
    set_time_limit(300);
    $file_url = HelperFunctions::sanitize_text( isset( $_POST['file_url'] ) ? $_POST['file_url'] : '' );
    $selectedMode = HelperFunctions::sanitize_text( isset( $_POST['selectedMode'] ) ? $_POST['selectedMode'] : 'default' );
    // Validate the URL to ensure it's a properly formatted and secure URL
    if (filter_var($file_url, FILTER_VALIDATE_URL) === false) {
      wp_send_json_error('Invalid file URL', 400);
    }
    
    $ti = new TemplateImport();
    $status = $ti->import($file_url, true, $selectedMode);
    if($status && $status['status']){
      wp_send_json_success([
        'queue' => $status['queue'], 
        'is_template_exit' => isset($status['is_template_exit']) ? $status['is_template_exit']: false, 
        'template_info' => $status['template_info']
      ]);
    }else{
      wp_send_json_error('Failed to import template');
    }
  }

  public static function check_existing_template_data(){
    $data = HelperFunctions::sanitize_text( isset( $_POST['data'] ) ? $_POST['data'] : '' );
    $data = json_decode($data, true);

    $ti = new TemplateImport();
    $status = $ti->check_existing_template_data($data);
    wp_send_json_success($status);
  }

  public static function processImport(){
    $t = new TemplateImport();
    $res = $t->process();
    wp_send_json_success($res);
  }
  
  public static function processExport(){
    $t = new TemplateExport();
    $res = $t->process();
    wp_send_json_success($res);
  }
}