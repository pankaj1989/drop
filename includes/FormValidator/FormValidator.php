<?php

/**
 * FormValidator class
 * 
 * This class is responsible for validating form data
 *
 * @package droip
 */

namespace Droip\FormValidator;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class FormValidator
{
  private $rulesets = array('type', 'maxLength', 'minLength', 'max', 'min');
  private $fields_validation_results = array();

  private $has_error = false;
  private $error_message = '';

  private function __construct()
  {
  }

  public static function validate($data, $rules)
  {
    $validator = new FormValidator();

    $validation_result = $validator->validate_data($data, $rules);

    return $validation_result;
  }

  private function validate_data($data, $fields)
  {
    foreach ($fields as $field_name => $rules) {
      // check if the rules['type'] is file then continue
      if (isset($rules['type']) && $rules['type'] === 'file') {
        continue;
      }

      $input = '';
      // in form data, the field name can be in camel case or snake case
      if (isset($data[$field_name]) || isset($data[str_replace(' ', '_', $field_name)])) {
        $input = isset($data[$field_name]) ? $data[$field_name] : $data[str_replace(' ', '_', $field_name)];
      }

      if (empty($input)) {
        $this->check_required($field_name, $rules);
      } else {
        $this->validate_field($field_name, $input, $rules);
      }
    }

    $this->validate_files($fields);

    // total errors count
    $total_errors = array_sum(array_map("count", $this->fields_validation_results)) - 1;

    // set error message
    $message = $this->has_error ?  "$this->error_message." .
      ($total_errors > 0 ? " (and $total_errors more errors)" : "") : 'No errors found.';

    return array(
      'has_error' => $this->has_error,
      'message' => $message,
      'errors' => $this->fields_validation_results
    );
  }

  private function validate_files($fields)
  {
    if (count($_FILES) > 0) {
      foreach ($_FILES as $field_name => $file) {
        if (isset($fields[$field_name]) && isset($file['error'])) {
          $rules = $fields[$field_name];

          switch ($file['error']) {
            case UPLOAD_ERR_OK:
              // check if the file type is supported
              $validated_file_type = Validate::accepted_file_type($file['type'], $rules['accept']);

              if (false === $validated_file_type) {
                $this->has_error = true;
                $this->set_error_message($field_name, 'accept', $rules['accept']);
              }

              // check if the file size is supported
              $validated_file_size = Validate::max_file_size($file['size'], $rules['max-file-size']);

              if (false === $validated_file_size) {
                $this->has_error = true;
                $this->set_error_message($field_name, 'max-file-size', $rules['max-file-size']);
              }
              break;

            case UPLOAD_ERR_NO_FILE:
              $this->check_required($field_name, $rules);
              break;

            case UPLOAD_ERR_INI_SIZE:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_INI_SIZE', '');
              break;

            case UPLOAD_ERR_FORM_SIZE:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_FORM_SIZE', '');
              break;

            case UPLOAD_ERR_PARTIAL:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_PARTIAL', '');
              break;

            case UPLOAD_ERR_NO_TMP_DIR:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_NO_TMP_DIR', '');
              break;

            case UPLOAD_ERR_CANT_WRITE:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_CANT_WRITE', '');
              break;

            case UPLOAD_ERR_EXTENSION:
              $this->has_error = true;
              $this->set_error_message($field_name, 'UPLOAD_ERR_EXTENSION', '');
              break;
          }
        }
      }
    }
  }

  private function check_required($field_name, $rules)
  {
    if (isset($rules['required']) && true === $rules['required']) {
      $this->has_error = true;

      // check if there is a custom error message
      $message = '';
      if (isset($rules['data-error-msg']) && !empty($rules['data-error-msg'])) {
        $message = $rules['data-error-msg'];
      } else {
        $message = sprintf(__('%s field is required', 'droip'), $field_name);
      }

      if (
        isset($this->fields_validation_results[$field_name]) &&
        is_array($this->fields_validation_results[$field_name])
      ) {
        array_push($this->fields_validation_results[$field_name], $message);
      } else {
        $this->fields_validation_results[$field_name] = array($message);
        $this->error_message = empty($this->error_message) ? $message : $this->error_message;
      }
    }
  }

  private function validate_field($field_name, $input, $rules)
  {
    foreach ($rules as $rule_key => $rule_value) {
      if (!in_array($rule_key, $this->rulesets, true)) {
        continue;
      }

      $validated = $this->validate_rule($input, $rule_key, $rule_value);

      if (false === $validated) {
        $this->has_error = true;
        $this->set_error_message($field_name, $rule_key, $rule_value);
      }
    }
  }

  private function validate_rule($input, $rule_key, $rule_value)
  {
    switch ($rule_key) {
      case 'type':
        return $this->validate_type($input, $rule_value);
      case 'maxLength':
        return Validate::max_char($input, $rule_value);
      case 'minLength':
        return Validate::min_char($input, $rule_value);
    }

    return false;
  }

  private function set_error_message($field_name, $rule_key, $rule_value)
  {
    $message = '';

    switch ($rule_key) {
      case 'type':
        $message = sprintf(__("Invalid type! %s should be a valid %s", 'droip'), $field_name, $rule_value);
        break;
      case 'maxLength':
        $message = sprintf(__("%s should be less than %s charecters", 'droip'), $field_name, $rule_value);
        break;
      case 'minLength':
        $message = sprintf(__("%s should be more than %s charecters", 'droip'), $field_name, $rule_value);
        break;
      case 'accept':
        $message = __('Unsupported file type!', 'droip');
        break;
      case 'max-file-size':
        $message = sprintf(__("File size should be less than %s MB", 'droip'), $rule_value);
        break;
      case 'UPLOAD_ERR_CANT_WRITE':
        $message = __('Failed to write file to disk', 'droip');
        break;
      case 'UPLOAD_ERR_INI_SIZE':
        $message = __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'droip');
        break;
      case 'UPLOAD_ERR_FORM_SIZE':
        $message = __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'droip');
        break;
      case 'UPLOAD_ERR_PARTIAL':
        $message = __('The uploaded file was only partially uploaded', 'droip');
        break;
      case 'UPLOAD_ERR_NO_TMP_DIR':
        $message = __('Missing a temporary folder', 'droip');
        break;
      case 'UPLOAD_ERR_EXTENSION':
        $message = __('File upload stopped by extension', 'droip');
        break;
    }

    if (
      isset($this->fields_validation_results[$field_name]) &&
      is_array($this->fields_validation_results[$field_name])
    ) {
      array_push($this->fields_validation_results[$field_name], $message);
    } else {
      $this->error_message = empty($this->error_message) ? $message : $this->error_message;
      $this->fields_validation_results[$field_name] = array($message);
    }
  }

  private function validate_type($input, $rule_value)
  {
    switch ($rule_value) {
      case 'email':
        return Validate::email($input);
      case 'number':
        return Validate::number($input);
      case 'tel':
        return Validate::tel($input);
      case 'date':
        return Validate::date($input);
      case 'datetime-local':
        return Validate::datetime($input);
    }
  }
}
