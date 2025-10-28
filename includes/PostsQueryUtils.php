<?php
/**
 * PostsQueryUtils class for droip project
 *
 * @package droip
 */

namespace Droip;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class PostsQueryUtils {
  public static function post_table_text_query($column_name, $condition, $value) {
    if (empty($condition)) {
      return '';
    }

    switch($condition) {
      case 'starts-with': {
        if (isset($value)) {
          return DbQueryUtils::start_with(
            $column_name,
            $value
          );
        }
      }
      
      case 'ends-with': {
        if (isset($value)) {
          return DbQueryUtils::end_with(
            $column_name,
            $value
          );
        }
      }

      case 'contains': {
        if (isset($value)) {
          return DbQueryUtils::contains(
            $column_name,
            $value
          );
        }
      }

      case 'not-empty': {
        return DbQueryUtils::cell_is_not_empty($column_name);
      }

      case 'empty': {
        return DbQueryUtils::cell_is_empty($column_name);
      }

      default: {
        return '';
      }
    }

    return '';
  }
  
  public static function post_table_date_query($column_name, $start, $end) {

    return '';
  }


  /**** Meta Data Query Start */
  public static function post_meta_table_text_query($key, $condition, $value) {
    switch($condition) {
      case 'starts-with': {
        return array(
          'key' => $key,
          'value' => '^' . $value,
          'compare' => 'REGEXP',
        );
      }
      
      case 'ends-with': {
        return array(
          'key' => $key,
          'value' => $value . '^',
          'compare' => 'REGEXP',
        );
      }

      case 'contains': {
        return array(
          'key' => $key,
          'value' => $value,
          'compare' => 'LIKE',
        );
      }

      case 'empty': {
        return array(
          'key' => $key,
          'value' => '',
          'compare' => '=',
        );
      }

    case 'not-empty':
      return array(
        'key' => $key,
        'value' => '',
        'compare' => '!=',
      );
    }

    return array();
  }
  
  public static function post_meta_table_date_query($key, $values) {
    // Check if both 'start-date' and 'end-date' are provided
    $is_start_date_provided = isset($values['start-date']) && $values['start-date'] !== '';
    $is_end_date_provided = isset($values['end-date']) && $values['end-date'] !== '';

    if ($is_start_date_provided &&   $is_end_date_provided) {
        return array(
            'key' => $key,
            'value' => array($values['start-date'], $values['end-date']),
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        );
    }

    // If only 'start-date' is provided
    if ($is_start_date_provided) {
        return array(
            'key' => $key,
            'value' => $values['start-date'],
            'compare' => '>=',
            'type' => 'DATE'
        );
    }

    // If only 'end-date' is provided
    if ($is_end_date_provided) {
        return array(
            'key' => $key,
            'value' => $values['end-date'],
            'compare' => '<=',
            'type' => 'DATE'
        );
    }

    // Return an empty array if no valid date values are provided
    return array();
  }

  public static function post_meta_table_number_query($key, $condition, $values) {
    switch($condition) {
        case 'greater-than': {
            return array(
                'key' => $key,
                'value' => $values,
                'compare' => '>',
                'type' => 'NUMERIC'
            );
        }
        
        case 'smaller-than': {
            return array(
                'key' => $key,
                'value' => $values,
                'compare' => '<',
                'type' => 'NUMERIC'
            );
        }
        
        case 'equal': {
            return array(
                'key' => $key,
                'value' => $values,
                'compare' => '=',
                'type' => 'NUMERIC'
            );
        }
        
        case 'empty': {
            return array(
                'relation' => 'OR',
                array(
                    'key' => $key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => $key,
                    'value' => '',
                    'compare' => '='
                )
            );
        }
        
        case 'not-empty': {
            return array(
                'relation' => 'AND',
                array(
                    'key' => $key,
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => $key,
                    'value' => '',
                    'compare' => '!='
                )
            );
        }
        
        default: {
            return array();
        }
    }
  }

  
  public static function post_meta_table_options_query($key, $condition, $values) {
    // Check if values array is empty
    if (empty($values)) {
        return array();
    }

    switch($condition) {
        case 'in': {
            return array(
                'key' => $key,
                'value' => $values,
                'compare' => 'IN',
            );
        }
        
        case 'not-in': {
            return array(
                'key' => $key,
                'value' => $values,
                'compare' => 'NOT IN',
            );
        }
        
        default: {
            return array();
        }
    }
  }

  
  public static function post_meta_table_switch_query($key, $condition) {
    switch($condition) {
        case 'on': {
            return array(
                'key' => $key,
                'value' => 'on', 
                'compare' => '=',
            );
        }
        
        case 'off': {
            return array(
                'key' => $key,
                'value' => 'off', 
                'compare' => '=',
            );
        }
        
        default: {
            return array();
        }
    }
  }

}