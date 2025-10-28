<?php

/**
 * Manage dynamic form data api calls
 *
 * @package droip
 */

namespace Droip\Ajax;

use Droip\HelperFunctions;
use Droip\Ajax\Symbol;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class ExportImport
{

  public static function export()
  {
    $filename = HelperFunctions::sanitize_text(isset($_POST['filename']) ? $_POST['filename'] : "");
    $filename = basename($filename);
    $data = isset($_POST['data']) ? $_POST['data'] : "{}";
    $data = json_decode(stripslashes($data), true);
    $blocks = $data['blocks'];
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];

    $asset_urls = [];
    $symbol_ids = [];
    $symbols = [];

    foreach ($blocks as $key => $block) {
      if (isset($block['properties']['symbolId'])) {
        $symbol_id = [
          'id' => $block['properties']['symbolId'],
          'elementId' => $block['id'],
        ];

        array_push($symbol_ids, $symbol_id);
      }
      self::add_asset($asset_urls, $key, $block);
    }

    // filter asset_urls using base_url if base_url not included remove item
    $asset_urls = array_filter($asset_urls, function ($asset_item) use ($base_url) {
      return strpos($asset_item['url'], $base_url) !== false;
    });

    // get symbols data
    foreach ($symbol_ids as $symbol_id) {
      $symbol = Symbol::get_single_symbol($symbol_id['id'], true);
  
      if ($symbol) {
        $symbol['elementId'] = $symbol_id['elementId'];
        array_push($symbols, $symbol);
      }
    }
    // iterate through symbols and add assets
    foreach ($symbols as $symbol) {
      foreach ($symbol['symbolData']['data'] as $key => $block) {
        self::add_asset($asset_urls, $key, $block);
      }
    }

    $data['asset_urls'] = $asset_urls;
    $data['symbols'] = $symbols;

    self::get_assets_make_zip($filename, $asset_urls, $data);
  }

  public static function add_asset(&$asset_urls, $key, $block)
  {
    if ($block['name'] === 'image' && $block['properties']['attributes']['src']) {
      $image_item = [
        'id' => $key,
        'name' => $block['name'],
        'url' => $block['properties']['attributes']['src'],
      ];
      array_push($asset_urls, $image_item);
    }

    if ($block['name'] === 'video' && $block['properties']['attributes']['src']) {
      $video_item = [
        'id' => $key,
        'name' => $block['name'],
        'url' => $block['properties']['attributes']['src'],
      ];
      array_push($asset_urls, $video_item);

      if ($block['properties']['thumbnail']['url']) {
        $video_thumbnail = [
          'id' => $key,
          'name' => $block['name'],
          'url' => $block['properties']['thumbnail']['url'],
          'thumbnail' => true,
        ];
        array_push($asset_urls, $video_thumbnail);
      }
    }

    if ($block['name'] === 'lottie' && $block['properties']['lottie']['src']) {
      $lottie = [
        'id' => $key,
        'name' => $block['name'],
        'url' => $block['properties']['lottie']['src'],
      ];
      array_push($asset_urls, $lottie);
    }
    if ($block['name'] === 'lightbox' && $block['properties']['lightbox']['thumbnail']['src']) {
      $lightbox_thumbnail = [
        'id' => $key,
        'name' => $block['name'],
        'url' => $block['properties']['lightbox']['thumbnail']['src'],
        'thumbnail' => true,
      ];
      array_push($asset_urls, $lightbox_thumbnail);

      $lightbox_media = $block['properties']['lightbox']['media'];

      foreach ($lightbox_media as $key => $media_item) {
        if ($media_item['sources']['original']) {
          $lightbox_media_item = [
            'id' => $key,
            'name' => $block['name'],
            'url' => $media_item['sources']['original'],
            'index' => $key,
          ];
          array_push($asset_urls, $lightbox_media_item);
        }
      }
    }
  }

  public static function get_assets_make_zip($filename, $asset_urls, $data)
  {
    try {
      if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
      }

      global $wp_filesystem;

      $zip = new \ZipArchive();
      $upload_dir = wp_upload_dir();

      $data['asset_urls'] = $asset_urls;

      // Step 1: Name of the zip file to be created
      $zipFileName = $upload_dir['basedir'] .  "/$filename.zip";
      $droip_json_file = $upload_dir['basedir'] . '/droip-data.json';

      // Step 2: Convert the array to JSON
      $json_data = json_encode($data, JSON_PRETTY_PRINT);

      $is_file_written = $wp_filesystem->put_contents(
        $droip_json_file,
        $json_data,
        FS_CHMOD_FILE // predefined mode settings for WP files
      );

      if (false === $is_file_written) {
        throw new \Exception('Failed to write droip-data.json file');
      }

      if (true !== $zip->open($zipFileName, \ZipArchive::CREATE)) {
        throw new \Exception('Failed to create zip file');
      }

      // Step 3: Add file to the zip file
      foreach ($asset_urls as $key => $asset_item) {
        $url = $asset_item['url'];
        $file_name = basename($url);

        $subdir_with_filename = explode("/uploads", $url)[1];  // /2021/05/1.jpg
        $file_path = $upload_dir['basedir'] . $subdir_with_filename; // /var/www/html/wp-content/uploads/2021/05/1.jpg

        if (file_exists($file_path)) {
          $zip->addFile($file_path, $file_name);
        }
      }

      if (false === $zip->addFile($droip_json_file, 'droip-data.json')) {
        throw new \Exception('Failed to add droip-data.json file to zip');
      }

      $zip->close();

      // remove droip-data.json
      unlink($droip_json_file);

      // Step 4: Download the created zip file
      wp_send_json(home_url("/?page-export=true&file-name=$filename.zip"));
    } catch (\Exception $e) {
      wp_send_json_error($e->getMessage(), 401);
    }
  }

  public static function import()
  {
    set_time_limit(300);

    $is_include_media = HelperFunctions::sanitize_text(isset($_POST['is_include_media']) ? $_POST['is_include_media'] : false);
    $file = $_FILES['file']; // zip file


    self::handle_zip_file_upload($file, $is_include_media);
  }

  public static function  download_and_save_zip_file($url, $destination)
  {
    // Execute the cURL session
    $file_content = HelperFunctions::http_get(
      $url,
      array(
        'timeout' => 300 // Seconds
      )
    );

    if (empty($wp_filesystem)) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
    }

    global $wp_filesystem;

    // Save the file to the destination
    if ($wp_filesystem->put_contents($destination, $file_content, FS_CHMOD_FILE)) {
      return true;
    } else {
      return false;
    }
  }

  public static function template_import()
  {
    $upload_dir = wp_upload_dir();

    $is_include_media = HelperFunctions::sanitize_text(isset($_POST['is_include_media']) ? $_POST['is_include_media'] : false);

    // get template file from url 
    $file_url = HelperFunctions::sanitize_text(isset($_POST['file_url']) ? $_POST['file_url'] : false);

    $destination_path = $upload_dir['basedir'] . '/droip-template.zip';

    if (self::download_and_save_zip_file($file_url, $destination_path)) {
      $file = [
        'name' => 'droip-template.zip',
        'tmp_name' => $destination_path,
        'error' => 0,
      ];

      self::handle_zip_file_upload($file, $is_include_media);
    } else {
      // Error occurred while downloading or saving the file
      wp_send_json_error('Zip File upload Failed');
    }
  }


  public static function handle_zip_file_upload($file, $is_include_media)
  {
    $upload_dir = wp_upload_dir();

    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_error = $file['error'];

    $file_ext = explode('.', $file_name); // ['file', 'ext']
    $file_ext = strtolower(end($file_ext)); // 'ext'

    $allowed = ['zip'];

    if (!in_array($file_ext, $allowed, true)) {
      wp_send_json_error('File type not allowed, please upload zip file');
    }

    if ($file_error !== 0) {
      wp_send_json_error('File upload Failed');
    }

    $file_name_new = uniqid('', true) . '.' . $file_ext; // 'random.ext'
    $file_destination = $upload_dir['basedir'] . '/' . $file_name_new;

    if (!(move_uploaded_file($file_tmp, $file_destination) || rename($file_tmp, $file_destination))) {
      wp_send_json_error('Something went wrong, please try again');
    }

    $zip = new \ZipArchive;
    $res = $zip->open($file_destination);

    if ($res !== TRUE) {
      wp_send_json_error('Failed to extract zip file');
    }

    $filtered_zip_path = HelperFunctions::filterZipFile($zip, $file_destination);

    if(!$filtered_zip_path){
      return false;
    }

    $zip->close();
    unlink($file_destination);


    $temp_folder = 'droip_temp';
    $temp_folder_path = HelperFunctions::get_temp_folder_path();

    // Reopen the filtered ZIP file for extraction
    $res = $zip->open($filtered_zip_path);

    $zip->extractTo($temp_folder_path);
    $zip->close();


    if (empty($wp_filesystem)) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
    }

    global $wp_filesystem;

    $droip_json_file = $temp_folder_path . '/droip-data.json';
    $droip_json_data = $wp_filesystem->get_contents($droip_json_file);
    $droip_json_data = json_decode($droip_json_data, true);

    $blocks = $droip_json_data['blocks'];
    $symbols = $droip_json_data['symbols'];

    if ($is_include_media === 'true') {
      $asset_urls = $droip_json_data['asset_urls'];

      $pivot_table = [];
      $assets_urls_map = [];

      foreach ($asset_urls as $key => $asset_item) {
        $url = $asset_item['url'];

        if (empty($pivot_table[$url]['url'])) {
          $new_asset = self::upload_file($asset_item);

          if ($new_asset) {
            $pivot_table[$url]['url'] = $new_asset['url'];
            $pivot_table[$url]['attachment_id'] = $new_asset['attachment_id'];

            $asset_urls[$key]['url'] = $new_asset['url'];
            $asset_urls[$key]['attachment_id'] = $new_asset['attachment_id'];
            $asset_urls[$key]['index'] = isset($asset_item['index']) ? $asset_item['index'] : null;
            $asset_urls[$key]['thumbnail'] = isset($asset_item['thumbnail']) ? $asset_item['thumbnail'] : false;
          }
        } else {
          $asset_urls[$key]['url'] = $pivot_table[$url]['url'];
          $asset_urls[$key]['attachment_id'] = $pivot_table[$url]['attachment_id'];

          $asset_urls[$key]['index'] = isset($asset_item['index']) ? $asset_item['index'] : null;
          $asset_urls[$key]['thumbnail'] = isset($asset_item['thumbnail']) ? $asset_item['thumbnail'] : false;
        }
      }

      //  update blocks with new asset urls
      foreach ($asset_urls as $key => $new_asset) {
        $assets_urls_map[$new_asset['id']] = $new_asset;

        if (isset($blocks[$new_asset['id']]['name'], $new_asset['name']) && $blocks[$new_asset['id']]['name'] === $new_asset['name']) {
          if ($new_asset['name'] === 'image') {
            $blocks[$new_asset['id']]['properties']['attributes']['src'] = $new_asset['url'];
            $blocks[$new_asset['id']]['properties']['wp_attachment_id'] = $new_asset['attachment_id'];
          } else if ($new_asset['name'] === 'video') {
            if ($new_asset['thumbnail']) {
              $blocks[$new_asset['id']]['properties']['thumbnail']['url'] = $new_asset['url'];
            } else {
              $blocks[$new_asset['id']]['properties']['attributes']['src'] = $new_asset['url'];
            }
          } else if ($new_asset['name'] === 'lottie') {
            $blocks[$new_asset['id']]['properties']['lottie']['src'] = $new_asset['url'];
          } else if ($new_asset['name'] === 'lightbox') {
            if ($new_asset['thumbnail']) {
              $blocks[$new_asset['id']]['properties']['lightbox']['thumbnail']['src'] = $new_asset['url'];
            } else {
              $blocks[$new_asset['id']]['properties']['lightbox']['media'][$new_asset['index']]['sources']['original'] = $new_asset['url'];
            }
          }
        }
      }

      // update symbols with new asset urls
      foreach ($symbols as  $sym_key => $symbol) {
        foreach ($symbol['symbolData']['data'] as $key => $block) {
          if (isset($assets_urls_map[$block['id']])) {

            if ($assets_urls_map[$block['id']]['name'] === 'image') {
              $symbol['symbolData']['data'][$key]['attributes']['src'] = $assets_urls_map[$block['id']]['url'];
              $symbol['symbolData']['data'][$key]['wp_attachment_id'] = $assets_urls_map[$block['id']]['attachment_id'];
            } else if ($assets_urls_map[$block['id']]['name'] === 'video') {
              if ($assets_urls_map[$block['id']]['thumbnail']) {
                $symbol['symbolData']['data'][$key]['thumbnail']['url'] = $assets_urls_map[$block['id']]['url'];
              } else {
                $symbol['symbolData']['data'][$key]['attributes']['src'] = $assets_urls_map[$block['id']]['url'];
              }
            } else if ($assets_urls_map[$block['id']]['name'] === 'lottie') {
              $symbol['symbolData']['data'][$key]['lottie']['src'] = $assets_urls_map[$block['id']]['url'];
            } else if ($assets_urls_map[$block['id']]['name'] === 'lightbox') {
              if ($assets_urls_map[$block['id']]['thumbnail']) {
                $symbol['symbolData']['data'][$key]['lightbox']['thumbnail']['src'] = $assets_urls_map[$block['id']]['url'];
              } else {
                $symbol['symbolData']['data'][$key]['lightbox']['media'][$assets_urls_map[$block['id']]['index']]['sources']['original'] = $assets_urls_map[$block['id']]['url'];
              }
            }
          }
        }

        $symbols[$sym_key]['symbolData']['data'] = $symbol['symbolData']['data'];
      }
    }

    // Tracker for saved symbols
    $saved_symbols_tracker = [];

    if (is_array($symbols) && count($symbols) > 0) {
        foreach ($symbols as $symbol) {
            $symbol_id = $symbol['id'];

            // Check if the symbol is already saved
            if (!isset($saved_symbols_tracker[$symbol_id])) {
                // Save the symbol to the database
                $saved_symbol = Symbol::save_to_db($symbol);

                // Add the saved symbol to the tracker
                if ($saved_symbol) {
                    $saved_symbols_tracker[$symbol_id] = $saved_symbol;
                }
            }

            // Update all relevant elements with the symbol's ID
            if (isset($blocks[$symbol['elementId']]) && $blocks[$symbol['elementId']]['name'] === 'symbol') {
                $blocks[$symbol['elementId']]['properties']['symbolId'] = $saved_symbols_tracker[$symbol_id]['id'];
            }
        }
    }

    $droip_json_data['blocks'] = $blocks;
    $droip_json_data['symbols'] = $symbols;

    // remove asset_urls
    unset($droip_json_data['asset_urls']);

    // delete zip file
    $wp_filesystem->delete($file_destination);

    // delete temp folder
    self::delete_directory($temp_folder_path);

    wp_send_json_success($droip_json_data);
  }

  /**
   * Process droip template zip
   * @param string $droip_template_zip_path
   * @param boolean $is_include_media
   * @return boolean || string
   */
  public static function process_droip_template_zip( $droip_template_zip_path, $is_include_media = false, $post_id = false ){
    $zip = new \ZipArchive;
    $res = $zip->open($droip_template_zip_path);

    if ($res === TRUE) {
      $temp_folder_path = HelperFunctions::get_temp_folder_path();

      $zip->extractTo($temp_folder_path);
      $zip->close();

      $droip_json_file = $temp_folder_path . '/droip-data.json';
      $droip_json_data = file_get_contents($droip_json_file);
      $droip_json_data = json_decode($droip_json_data, true);

      if ($is_include_media === 'true') {
        $asset_urls = $droip_json_data['asset_urls'];
        $blocks = $droip_json_data['blocks'];

        $pivot_table = [];

        foreach ($asset_urls as $key => $asset_item) {
          $url = $asset_item['url'];

          if (empty($pivot_table[$url]['url'])) {
            $new_asset = self::upload_file($asset_item);

            if ($new_asset) {
              $pivot_table[$url]['url'] = $new_asset['url'];
              $pivot_table[$url]['attachment_id'] = $new_asset['attachment_id'];

              $asset_urls[$key]['url'] = $new_asset['url'];
              $asset_urls[$key]['attachment_id'] = $new_asset['attachment_id'];
              $asset_urls[$key]['index'] = isset($asset_item['index']) ? $asset_item['index'] : null;
              $asset_urls[$key]['thumbnail'] = isset($asset_item['thumbnail']) ? $asset_item['thumbnail'] : false;
            }
          } else {
            $asset_urls[$key]['url'] = $pivot_table[$url]['url'];
            $asset_urls[$key]['attachment_id'] = $pivot_table[$url]['attachment_id'];

            $asset_urls[$key]['index'] = isset($asset_item['index']) ? $asset_item['index'] : null;
            $asset_urls[$key]['thumbnail'] = isset($asset_item['thumbnail']) ? $asset_item['thumbnail'] : false;
          }
        }

        //  update blocks with new asset urls
        foreach ($asset_urls as $key => $new_asset) {
          if (isset($blocks[$new_asset['id']]['name'], $new_asset['name']) && $blocks[$new_asset['id']]['name'] === $new_asset['name']) {
            if ($new_asset['name'] === 'image') {
              $blocks[$new_asset['id']]['properties']['attributes']['src'] = $new_asset['url'];
              $blocks[$new_asset['id']]['properties']['wp_attachment_id'] = $new_asset['attachment_id'];
            } else if ($new_asset['name'] === 'video') {
              if ($new_asset['thumbnail']) {
                $blocks[$new_asset['id']]['properties']['thumbnail']['url'] = $new_asset['url'];
              } else {
                $blocks[$new_asset['id']]['properties']['attributes']['src'] = $new_asset['url'];
              }
            } else if ($new_asset['name'] === 'lottie') {
              $blocks[$new_asset['id']]['properties']['lottie']['src'] = $new_asset['url'];
            } else if ($new_asset['name'] === 'lightbox') {
              if ($new_asset['thumbnail']) {
                $blocks[$new_asset['id']]['properties']['lightbox']['thumbnail']['src'] = $new_asset['url'];
              } else {
                $blocks[$new_asset['id']]['properties']['lightbox']['media'][$new_asset['index']]['sources']['original'] = $new_asset['url'];
              }
            }
          }
        }

        $droip_json_data['blocks'] = $blocks;
      }

      // remove asset_urls
      unset($droip_json_data['asset_urls']);

      if($post_id){
        $root = array(
          'accept'=>"*",
          'children' =>        ["body"],
          'id'=>        "root",
          'name'=>        "root",
          'styleIds' =>        [],
          'title' => "Root"
        );
        $droip_json_data['blocks']['root'] = $root;
        if($post_id){
          foreach ($droip_json_data['styles'] as $key => $style) {
            $style['name'] = HelperFunctions::add_prefix_to_class_name('post-'.$post_id, $style['name']);
            unset($style['isGlobal']);
            unset($style['isDefault']);
            $droip_json_data['styles'][$key] = $style;
          }
        }
        HelperFunctions::save_droip_data_to_db($post_id, $droip_json_data);
      }

      // delete temp folder
      HelperFunctions::delete_directory($temp_folder_path);
      return $droip_json_data;
    }

    return false;
  }

  private static function upload_file($asset_item)
  {
    $asset_name = basename($asset_item['url']);
    $temp_folder_path = HelperFunctions::get_temp_folder_path();
    $source_file_path = $temp_folder_path . '/' . $asset_name; //

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

        $new_asset = [
          'id' => $asset_item['id'],
          'name' => $asset_item['name'],
          'url' =>  $post->guid,
          'attachment_id' => $attachment_id,
        ];

        return  $new_asset;
      }
    }

    return null;
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
}