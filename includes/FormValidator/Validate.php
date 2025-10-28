<?php

/**
 * Validate
 *
 * @package droip
 */

namespace Droip\FormValidator;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Validate
{
  public static function email($email = '')
  {
    return is_email($email);
  }

  public static function number($input)
  {
    return !empty($input) && is_numeric($input);
  }

  public static function tel($input)
  {
    $tel_regex = '/^[0-9]{10}+$/';

    return preg_match($tel_regex, $input);
  }

  public static function date($input)
  {
    $date_regex = '/^\d{4}-\d{2}-\d{2}$/';

    return preg_match($date_regex, $input);
  }

  public static function datetime($input)
  {
    $datetime_regex = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    return preg_match($datetime_regex, $input);
  }

  public static function max_char($input, $max_length)
  {
    return strlen((string) $input) <= (int) $max_length;
  }

  public static function min_char($input, $min_length)
  {
    return strlen((string) $input) >= (int) $min_length;
  }

  public static function accepted_file_type($file_type, $accepted_file_types)
  {
    // droip supported media types for file input
    $droip_supported_media_types = DROIP_SUPPORTED_MEDIA_TYPES_FOR_FILE_INPUT[$accepted_file_types];

    // check if file type is in droip supported media types
    if (!empty($droip_supported_media_types) && in_array($file_type, $droip_supported_media_types, true)) {
      return true;
    }

    return false;
  }

  public static function max_file_size($file_size, $max_file_size)
  {
    // convert file size to MB
    $file_size = !empty($file_size) ? (float) number_format($file_size / 1048576, 2) : 2;

    return $file_size <= (int) $max_file_size;
  }
}
