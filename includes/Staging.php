<?php

/**
 * This class act like controller for Editor, Iframe, Frontend
 *
 * @package droip
 */

namespace Droip;

use Droip\Ajax\Collaboration\Collaboration;
use Droip\HelperFunctions;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Frontend handler class
 */
class Staging
{

  public static function get_most_recent_stage_version($post_id, $stage_must = true, $restoring = false, $old_version = false)
  {
    $staged_versions = self::get_all_staged_versions($post_id, true);
    $recent_stage_version = false;
    $version_number = 0;
    $being_restored = false;

    foreach ($staged_versions as $version) {
      if (is_array($version) && intval($version['version']) > $version_number) {
        $version_number = $version['version'];
        $recent_stage_version = $version;
      }

      if ($restoring && intval($old_version) === intval($version['version'])) {
        $being_restored = $version;
      }
    }

    if ($version_number === 0 || ($stage_must && isset($recent_stage_version['publish']) && $recent_stage_version['publish']) || $restoring) {
      $version_number = self::add_stage_version($post_id, $version_number + 1, $staged_versions, $being_restored);
    }

    return $version_number;
  }

  public static function get_all_staged_versions($post_id, $internal = false, $withname = false)
  {
    // delete_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS);
    $staged_versions = get_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, true);
    if (!is_array($staged_versions) || count($staged_versions) < 1) {
      $staged_versions = self::create_first_stage_version($post_id);
    }
    if ($withname) {
      $staged_versions = self::add_editor_names_into_stage_versions($staged_versions);
    }
    if ($internal) return $staged_versions;
    wp_send_json($staged_versions);
  }

  public static function publish_stage_version($internal = false, $post_id = false)
  {
    if(!$internal)
      $post_id = HelperFunctions::sanitize_text(isset($_POST['post_id']) ? $_POST['post_id'] : null);

    if (!$post_id) {
      wp_send_json(false, 400);
    }

    $version_id = self::get_most_recent_stage_version($post_id, false);
    $staged_versions = self::get_all_staged_versions($post_id, true, true);
    $staged_versions = array_map(function ($item) use ($version_id) {
      return (is_array($item) && isset($item['version']) && intval($item['version']) === intval($version_id))
        ? array_merge($item, ['publish' => true])
        : array_merge($item, ['publish' => false]);
    }, $staged_versions);
    update_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, $staged_versions);
    if($internal) return $staged_versions;
    wp_send_json($staged_versions);
  }


  public static function get_published_stage_version($post_id) {
    $staged_versions = self::get_all_staged_versions($post_id, true, true);
    $published_version = array_find($staged_versions, fn($item)=> $item['publish']);
    return isset($published_version['version']) ? $published_version['version'] : false;
  }

  public static function get_page_staging_data($post_id, $version_id)
  {
    $droip_data = array();
    $droip_data['blocks'] = null;

    $meta_name = self::get_staged_meta_name(DROIP_META_NAME_FOR_POST_DATA, $post_id, $version_id);
    $staging_post_meta = get_post_meta($post_id, $meta_name, true);
    if ($staging_post_meta) $droip_data =  $staging_post_meta;

    $styles = HelperFunctions::get_page_styleblocks($post_id, $version_id);
    $droip_data['styles'] = isset($styles) ? $styles : '';
    return $droip_data;
  }

  // This function receives the page data (blocks, styleblocks...) and saves in stage version whichever required in stage
  // returns the data which needs to be saved in Publish version
  public static function save_page_staging_data_to_db($post_id, $page_data)
  {
    $staging_version = self::get_most_recent_stage_version($post_id);
    self::update_last_edited_datetime_of_stage_version($post_id);
    if (isset($page_data['styles'])) {
      $new_random_styles = $page_data['styles'];
      $new_global_styles = array(); // global Styleblocks which won't be saved in publish but stage 
      $add_to_publish_global = array(); // global Styleblocks which won't be saved in stage but publish 

      foreach ($new_random_styles as $key => $style) {
        if ((isset($style['isDefault']) && $style['isDefault'] === true) || (isset($style['isGlobal']) && $style['isGlobal'] === true)) {
          if(isset($style['fromStage']) && $style['fromStage']) $new_global_styles[$key] = $style;
          else {
            // Adding fromStage true because publish version saves only styleblocks having fromStage
            $style['fromStage'] = true;
            $add_to_publish_global[$key] = $style;
          }
          unset($new_random_styles[$key]);
        }
      }

      $meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $staging_version);
      update_post_meta($post_id, $meta_key, $new_random_styles);

      // $data = array(
      //   'type'    => 'COLLABORATION_UPDATE_GLOBAL_STYLE',
      //   'payload' => array( 'styleBlock' => $new_random_styles ),
      // );
      //Collaboration::save_action_to_db( 'post', $post_id, $data, 1 );

      $global_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $staging_version);
      update_post_meta($post_id, $global_meta_key, $new_global_styles);

      // $data = array(
      //   'type'    => 'COLLABORATION_UPDATE_GLOBAL_STYLE',
      //   'payload' => array( 'styleBlock' => $new_global_styles ),
      // );
      //Collaboration::save_action_to_db( 'global', 0, $data, 1 );

      if(count($add_to_publish_global)) $page_data['styles'] = $add_to_publish_global;
      else unset($page_data['styles']); // Unset if no global style blocks for publish version
    }

    if (isset($page_data['usedStyles'])) {
      $meta_key = DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS;
      $meta_key = self::get_staged_meta_name($meta_key, $post_id, $staging_version);
      update_post_meta($post_id, $meta_key, $page_data['usedStyles']);
      unset($page_data['usedStyles']);
    }

    if (isset($page_data['usedStyleIdsRandom'])) {
      $meta_key = DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random';
      $meta_key = self::get_staged_meta_name($meta_key, $post_id, $staging_version);
      update_post_meta($post_id, $meta_key, $page_data['usedStyleIdsRandom']);
      unset($page_data['usedStyleIdsRandom']);
    }

    if (isset($page_data['usedFonts'])) {
      $meta_key = DROIP_META_NAME_FOR_USED_FONT_LIST;
      $meta_key = self::get_staged_meta_name($meta_key, $post_id, $staging_version);
      update_post_meta($post_id, $meta_key, $page_data['usedFonts']);
      unset($page_data['usedFonts']);
    }

    if (isset($page_data['blocks'])) {
      $meta_key = DROIP_APP_PREFIX;
      $meta_key = self::get_staged_meta_name($meta_key, $post_id, $staging_version);
      update_post_meta($post_id, $meta_key, array('blocks' => $page_data['blocks']));
      // $data = array(
      //   'type'    => 'COLLABORATION_PAGE_DATA',
      //   'payload' => array('data' => $page_data['blocks']),
      // );
      unset($page_data['blocks']);
      //Collaboration::save_action_to_db('post', $post_id, $data, 1);
    }

    return array('page_data' => $page_data, 'version' => $staging_version);
  }

  public static function restore_stage_version()
  {

    $post_id = HelperFunctions::sanitize_text(isset($_POST['post_id']) ? $_POST['post_id'] : null);
    $old_version_id = HelperFunctions::sanitize_text(isset($_POST['stage_version']) ? $_POST['stage_version'] : null);

    $new_version = self::get_most_recent_stage_version($post_id, true, true, $old_version_id);

    $old_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_FONT_LIST, $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_FONT_LIST, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = self::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $old_version_id);
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    wp_send_json(array_merge(['versions' => self::get_all_staged_versions($post_id, true, true)], ['new_version' => $new_version]));
  }

  public static function rename_stage_version()
  {
    $post_id = HelperFunctions::sanitize_text(isset($_POST['post_id']) ? $_POST['post_id'] : null);
    $version_id = HelperFunctions::sanitize_text(isset($_POST['version_id']) ? $_POST['version_id'] : null);
    $name = HelperFunctions::sanitize_text(isset($_POST['name']) ? $_POST['name'] : null);

    if (!$post_id || !$version_id || !$name) {
      wp_send_json(false, 400);
    }

    $staged_versions = self::get_all_staged_versions($post_id, true, true);
    $staged_versions = array_map(function ($item) use ($version_id, $name) {
      return (is_array($item) && isset($item['version']) && intval($item['version']) === intval($version_id))
        ? array_merge($item, ['name' => $name])
        : $item;
    }, $staged_versions);

    update_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, $staged_versions);
    wp_send_json($staged_versions);
  }

  public static function delete_stage_version()
  {
    $post_id = HelperFunctions::sanitize_text(isset($_POST['post_id']) ? $_POST['post_id'] : null);
    $version_id = HelperFunctions::sanitize_text(isset($_POST['version_id']) ? $_POST['version_id'] : null);

    if (!$post_id || !$version_id) {
      wp_send_json(false, 400);
    }
    // Delete stage meta data
    $meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    $meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_FONT_LIST, $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    $meta_key = self::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $version_id, true);
    delete_post_meta($post_id, $meta_key);

    // delete the version from stage version list
    $staged_versions = self::get_all_staged_versions($post_id, true, true);
    $staged_versions = array_values(array_filter($staged_versions, function ($item) use ($version_id) {
      return !(is_array($item) && !$item['publish'] && intval($item['version']) === intval($version_id));
    }));
    update_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, $staged_versions);

    wp_send_json($staged_versions);
  }

  public static function get_staged_meta_name($meta_name, $post_id, $stage_version = false, $stage_only = false)
  {
    $version = $stage_version;
    if (!$version) $version = self::get_most_recent_stage_version($post_id, false);
    else if(!$stage_only) {
      $published_version = self::get_published_stage_version($post_id);
      if($published_version && $version == $published_version) return $meta_name;
    }
    return 'staged_' .  $version . '_' . $meta_name;
  }

  public static function get_all_stage_related_meta_names($post_id)
  {
    $versions = get_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, true);
    if(!$versions) return [];
    $meta_names = [DROIP_META_NAME_FOR_STAGED_VERSIONS];
    foreach ($versions as $version) {
      $version_id = $version['version'];
      $meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $version_id, true);
      $meta_names[] = $meta_key;
      $meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $version_id, true);
      $meta_names[] = $meta_key;
      $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $post_id, $version_id, true);
      $meta_names[] = $meta_key;
      $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $post_id, $version_id, true);
      $meta_names[] = $meta_key;
      $meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_FONT_LIST, $post_id, $version_id, true);
      $meta_names[] = $meta_key;
      $meta_key = self::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $version_id, true);
      $meta_names[] = $meta_key;
    }
    return $meta_names;
  }

  private static function add_editor_names_into_stage_versions($versions)
  {
    $versions = array_map(function ($item) {
      $user = get_user_by('id', $item['edited_by']);
      if (!$user) return $item;
      return array_merge($item, ['edited_by' => $user->data->display_name]);
    }, $versions);
    return $versions;
  }

  private static function create_first_stage_version($post_id)
  {
    $new_version = self::add_stage_version($post_id, 1);

    $old_meta_key = DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random';
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY . '_random', $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $new_meta_key = self::get_staged_meta_name(DROIP_GLOBAL_STYLE_BLOCK_META_KEY, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, []);

    $old_meta_key = DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS;
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random';
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS . '_random', $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = DROIP_META_NAME_FOR_USED_FONT_LIST;
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_META_NAME_FOR_USED_FONT_LIST, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    $old_meta_key = DROIP_APP_PREFIX;
    $old_data = get_post_meta($post_id, $old_meta_key, true);
    $new_meta_key = self::get_staged_meta_name(DROIP_APP_PREFIX, $post_id, $new_version);
    update_post_meta($post_id, $new_meta_key, $old_data);

    return self::publish_stage_version(true, $post_id);
  }

  private static function update_last_edited_datetime_of_stage_version($post_id)
  {
    $staged_versions = self::get_all_staged_versions($post_id, true, true);
    if (count($staged_versions) === 0) return;

    $datetime = new \DateTime('now', wp_timezone());
    $datetime = $datetime->format('Y-m-d H:i:s');
    $staged_versions[count($staged_versions) - 1] = array_merge($staged_versions[count($staged_versions) - 1], ['last_updated' => $datetime]);
    update_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, $staged_versions);
    return $staged_versions;
  }

  private static function add_stage_version($post_id, $version_number, $prev_versions = array(), $being_restored = false)
  {
    $format = 'F j g:i A';
    $datetime = new \DateTime('now', wp_timezone());
    $date = $datetime->format($format);
    $datetime = $datetime->format('Y-m-d H:i:s');

    $new_version = [
      'version' => $version_number,
      'edited_by' => get_current_user_id(),
      'created_on' => $datetime,
      'last_updated' => $datetime,
      'name' => $being_restored ? '[Restored] ' . $being_restored['name'] : $date,
      'publish' => false
    ];
    $prev_versions[] = $new_version;
    update_post_meta($post_id, DROIP_META_NAME_FOR_STAGED_VERSIONS, $prev_versions);
    return $version_number;
  }
}