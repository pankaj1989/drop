<?php

namespace Droip\Ajax;
use Droip\HelperFunctions;

class Users{
  /**
   * Get users data based on the query
   * 
   * @return void wp_send_json($users);
   */

   public static function get_users_of_collection() {
    $inherit = HelperFunctions::sanitize_text($_GET['inherit'] ?? null);
    $post_parent = HelperFunctions::sanitize_text($_GET['post_parent'] ?? null);

    $sorting_param = HelperFunctions::sanitize_text($_GET['sorting'] ?? null);
    $filter_param = HelperFunctions::sanitize_text($_GET['filters'] ?? null);

    $item_per_page = HelperFunctions::sanitize_text( $_GET['items'] ?? 3 );
		$current_page = HelperFunctions::sanitize_text( $_GET['current_page'] ?? 1 );
		$offset = HelperFunctions::sanitize_text( $_GET['offset'] ?? 0 );
    $query = HelperFunctions::sanitize_text( isset( $_GET['q'] ) ? $_GET['q'] : '' );

    $sorting = null;
    $filters = null;

    if (isset($sorting_param)) {
      $sorting = json_decode(stripslashes($sorting_param), true);
    }

    if (isset($filter_param)) {
      $filters = json_decode(stripslashes($filter_param), true);
    }

    $users = self::get_users(
      array(
        'inherit' => $inherit,
        'post_parent' => $post_parent,
        'sorting' => $sorting,
        'filters' => $filters,
        'item_per_page' => $item_per_page,
        'current_page' => $current_page,
        'offset' => $offset,
        'q' => $query,
      )
    );

    wp_send_json($users);
   }

   public static function get_users($params) {
    $inherit = (bool)($params['inherit'] ?? false);
    $post_parent = (int)($params['post_parent'] ?? 0);

    $sorting = isset($params['sorting']) ? $params['sorting'] : null;
    $filters = isset($params['filters']) ? $params['filters'] : null;
    $item_per_page = isset($params['item_per_page']) ? $params['item_per_page'] : 3;
    $current_page = isset($params['current_page']) ? $params['current_page'] : 1;
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $query = isset($params['q']) ? $params['q'] : '';

    // Calculate the offset
    $offset_cal = ($current_page - 1) * $item_per_page + $offset;

    if(!$query){
			$query = HelperFunctions::sanitize_text(isset($_REQUEST['q']) ?$_REQUEST['q']:'' );
		}


    $args = array(
      'number' => $item_per_page,
      'paged' => $current_page,
      'offset' => $offset_cal,
      'search'         => '*' . $query . '*',  // The dynamic search query
      'search_columns' => array('user_nicename', 'user_email', 'display_name' ),  // Fields to search in
    );

    if ( isset( $filters ) && is_array( $filters ) ) {
			foreach ( $filters as $filter_item ) {
				$field_name = isset( $filter_item['id'] ) ? $filter_item['id'] : '';

				if(!$field_name){
					continue;
				}

				switch($field_name) {
          case 'date_query': {
            /**
             * $filter_item['items'] max contain one array. 
             * in array may contain start-date, end-date
             * Like: [{"start-date": "2020-01-01","end-date": "2020-01-02"}]
             */
            
            if(isset($filter_item['items'], $filter_item['items'][0])) {
              $items = $filter_item['items'];
              $item = $items[0]; // Get first item in array.

              $date_query = array();
              $date_query['inclusive'] = true;

              if (!empty($item['start-date'])) {
                $date_query['after'] = $item['start-date'];
              }

              if (!empty($item['end-date'])) {
                $date_query['before'] = $item['end-date'];
              }

              $args['date_query'] = $date_query;
            }

            break;
          }

          case 'role': {
            /**
             * $filter_item['items'] must not contain more than 2 array of conditions. 
             * 1 array may contain 'in' conditions and another for 'not-in' conditions
             * And values of 'in' and 'not-in' conditions should not collide
             * Like: the condition should not be author 'in' [1, 2, 3] and 'not-in' [2, 4, 5]
             */
            $items = $filter_item['items'];

            foreach($items as $item) {
              if ( isset( $item['condition'], $item['values'] ) && is_array( $item['values'] ) ) {
                if ( $item['condition'] === 'in' ) {
                  $args['role__in'] = $item['values'];
                }

                if ( $item['condition'] === 'not-in' ) {
                  $args['role__not_in'] = $item['values'];
                }
              }
            }

            break;
          }
        }
			}
		}

    if($sorting){
      if(isset($sorting['order'])){
        $args['order'] = $sorting['order'];
      }
      if(isset($sorting['orderby'])){
        $args['orderby'] = $sorting['orderby'];
      }
    }

    $users = get_users($args);

    // To get total users based on filters, exclude pagination from $args
    $args_for_count = array_merge($args, array('number' => '', 'paged' => '', 'count_total' => true));

    // Fetch total users count
    $total_users = count(get_users($args_for_count));
    $total_users = $total_users - $offset;

    // Calculate pagination
    $total_pages = ceil($total_users / $item_per_page);
    $previous_page = ($current_page > 1) ? $current_page - 1 : null;
    $next_page = ($current_page < $total_pages) ? $current_page + 1 : null;

    // Prepare pagination array
    $pagination = array(
        'per_page'     => $item_per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'previous'     => $previous_page,
        'next'         => $next_page,
        'total_count' => $total_users,
    );

    // Return the users and pagination info
    return array(
        'data'       =>  self::get_formatted_users_data($users),
        'pagination' => $pagination
    );
   }

   public static function get_formatted_users_data( $users ) {
    // Initialize an empty array to store formatted user data
    $formatted_users = array();

    // Loop through each user and format the data
    foreach ( $users as $user ) {
        // Add user data to the array
        $formatted_users[] = self::get_format_single_user_data( $user );
    }

    return $formatted_users;
  }

  /**
   * The function `get_format_single_user_data` retrieves and formats specific user data including ID,
   * display name, email, profile URL, and avatar URL.
   */
  public static function get_format_single_user_data( $user ) {
    // Get the user's avatar (profile image) URL
    $avatar_url = get_avatar_url( $user->user_email );

    // Get the user's profile page URL
    $profile_url = get_author_posts_url( $user->ID );
    
    return array(
      'ID'             => $user->ID,
      'display_name'   => $user->display_name,
      'user_email'     => $user->user_email,
      'user_nicename'  => $user->user_nicename,
      'user_registered'=> $user->user_registered,
      'user_status'    => $user->user_status,
      'user_url'       => $profile_url,
      'profile_image'  => $avatar_url,  // Only the main profile image
      'roles' => $user->roles,
    );
  }

  public static function get_user_by_id($user_id) {
    $user = get_user_by('id', $user_id);

		$format_user = Users::get_format_single_user_data($user);
		return $format_user;
  }
}


