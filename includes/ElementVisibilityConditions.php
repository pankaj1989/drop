<?php

/**
 * This class act like controller for Editor, Iframe, Frontend
 *
 * @package droip
 */

namespace Droip;

use DateTime;
use Droip\API\ContentManager\ContentManagerHelper;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Frontend handler class
 */
class ElementVisibilityConditions
{

  /**
   * Initialize the class
   *
   * @return void
   */
  public function __construct()
  {
    add_filter('droip_visibility_condition_fields', [$this, 'droip_visibility_condition_fields'], 10, 2);
    add_filter('droip_visibility_condition_check_droip', [$this, 'droip_visibility_condition_check'], 10, 3);
  }

  public function droip_visibility_condition_check($default_value, $condition, $options)
  {
    $condition_result = $default_value;

    $field = $condition['field']['value'] ?? '';
    $group = (string) ($condition['field']['group'] ?? '');
    $operator = $condition['operator']['value'] ?? '';
    $operand = '';
    if ($operator === "date-in_between") {
      $operand =  $condition['operand']['value'] ?? '';
    } else {
      $operand = (string) ($condition['operand']['value'] ?? '');
    }

    // Default field value
    $fieldValue = '';
    if ($group === 'post') {
      if (isset($options['post'])) {
        $post = $options['post'];
        if (isset($post->$field)) {
          $fieldValue = (string) $post->$field;
        } else if (str_contains($post->post_type, DROIP_CONTENT_MANAGER_PREFIX)) {
          $cmsFields = ContentManagerHelper::format_single_child_post($post);
          if (isset($cmsFields['fields'][$field])) $fieldValue = $cmsFields['fields'][$field];
        }
      } else {
        return false;
      }
    } else if ($group === 'user') {
      $user = wp_get_current_user();
      if ($field === 'role') {
        if (is_user_logged_in()) {
          $fieldValue =  $user->roles;
          $fieldValue[] = 'logged_in';
        } else {
          $fieldValue = ['guest'];
        }
      } else if (is_user_logged_in()) {
        $fieldValue = $user->data->$field;
      }
    }

    // Evaluate condition
    switch ($operator) {
      case 'is_equal':
        $condition_result = ($fieldValue === $operand);
        break;
      case 'not_equal':
        $condition_result = ($fieldValue !== $operand);
        break;
      case 'contains':
        $condition_result = is_array($fieldValue) ? in_array($operand, $fieldValue) : (stripos($fieldValue, $operand) !== false);
        break;
      case 'not_contains':
        $condition_result = is_array($fieldValue) ? !in_array($operand, $fieldValue) : (stripos($fieldValue, $operand) === false);
        break;
      case 'starts_with':
        $condition_result = (stripos($fieldValue, $operand) === 0);
        break;
      case 'ends_with':
        $condition_result = str_ends_with(strtolower($fieldValue), strtolower($operand));
        break;
      case 'empty':
        $condition_result = empty($fieldValue);
        break;
      case 'not_empty':
        $condition_result = !empty($fieldValue);
        break;
      case 'in_between':
        $condition_result = $fieldValue >= $operand[0] && $fieldValue <= $operand[1];
        break;
      case 'less_than':
        $condition_result = $fieldValue < $operand;
        break;
      case 'greater_than':
        $condition_result = $fieldValue > $operand;
        break;
      case 'less_than_or_equal':
        $condition_result = $fieldValue <= $operand;
        break;
      case 'greater_than_or_equal':
        $condition_result = $fieldValue >= $operand;
        break;
      case 'true':
        $condition_result = !!$fieldValue;
      case 'false':
        $condition_result = !$fieldValue;
        break;
      case 'date-is_equal':
        $operand = new DateTime($operand);
        $fieldValue = new DateTime($fieldValue);
        $condition_result = $operand == $fieldValue;
        break;
      case 'date-not_equal':
        $operand = new DateTime($operand);
        $fieldValue = new DateTime($fieldValue);
        $condition_result = $operand != $fieldValue;
        break;
      case 'date-in_between':
        if(!isset($operand['from']) || !isset($operand['to'])) {
          return $default_value;
        }
        $from = new DateTime($operand['from']);
        $to = new DateTime($operand['to']);
        $fieldValue = new DateTime($fieldValue);
        $condition_result = $fieldValue >= $from && $fieldValue <= $to;
        return $condition_result;
        $condition_result = str_ends_with(strtolower($fieldValue), strtolower($operand));
        break;
      case 'date-before':
        $operand = new DateTime($operand);
        $fieldValue = new DateTime($fieldValue);
        $condition_result = $fieldValue < $operand;
        break;
      case 'date-after':
        $operand = new DateTime($operand);
        $fieldValue = new DateTime($fieldValue);
        $condition_result = $fieldValue > $operand;
        break;
    }

    return $condition_result;
  }

  public function droip_visibility_condition_fields($conditions, $collection_data)
  {
    if ($collection_data['collectionType'] === 'posts' && str_contains($collection_data['type'], DROIP_CONTENT_MANAGER_PREFIX)) {
      $post_parent = str_replace(DROIP_CONTENT_MANAGER_PREFIX . '_', '', $collection_data['type']);
      $post = ContentManagerHelper::get_post_type($post_parent, true);

      $fields = $this->get_conditions_from_post_fields($post);
      foreach ($fields as $key => $field) {
        $conditions['post']['fields'][] = $field;
      }
    }
    return $conditions;
  }

  private function get_conditions_from_post_fields($post)
  {
    $conditions = [];

    if (!empty($post['fields']) && is_array($post['fields'])) {
      foreach ($post['fields'] as $field) {
        if (empty($field['id']) || empty($field['title'])) {
          continue;
        }

        $id = $field['id'];
        $title = $field['title'];
        $type = $field['type'];

        // Determine operators and values based on field type
        switch ($type) {
          case 'text':
          case 'rich-text':
          case 'email':
          case 'url':
          case 'phone':
            $operator_type = 'text_operators';
            break;

          case 'option':
            $operator_type = 'common_operator';
            break;

          case 'image':
          case 'file':
            $operator_type = 'media_operators';
            break;

          case 'switch':
            $operator_type = 'boolean_operators';
            break;
          case 'reference':
            $operator_type = ['is', 'is_not'];
            break;

          default:
            $operator_type = 'text_operators';
            break;
        }

        $conditions[] = [
          'title' => $title,
          'value' => $id,
          'parent_post_id' => $post['ID'],
          'operator_type' => $operator_type,
          'filed_type' => DROIP_CONTENT_MANAGER_PREFIX
        ];
      }
    }

    return $conditions;
  }
}