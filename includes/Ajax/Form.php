<?php
/**
 * Manage dynamic form data api calls
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\DbQueryUtils;
use Droip\HelperFunctions;
use Exception;

define(
	'DROIP_CONDITIONS_TEXT_TYPE',
	array(
		'contains'          => 'contains',
		'does_not_contain'  => 'does_not_contain',
		'start_with'        => 'start_with',
		'end_with'          => 'end_with',
		'is'                => 'is',
		'is_not'            => 'is_not',
		'cell_is_not_empty' => 'cell_is_not_empty',
		'cell_is_empty'     => 'cell_is_empty',
	)
);

define(
	'DROIP_CONDITIONS_DATE_TYPE',
	array(
		'today'      => 'today',
		'this_week'  => 'this_week',
		'last_month' => 'last_month',
		'last_year'  => 'last_year',
		'between'    => 'between',
		'before'     => 'before',
		'after'      => 'after',
		'is_not'     => 'is_not',
	)
);

define(
	'DROIP_FORM_TABLE_SORT_OPTIONS',
	array(
		'new_to_old' => 'new_to_old',
		'old_to_new' => 'old_to_new',
		'a_z'        => 'a_z',
		'z_a'        => 'z_a',
	)
);

/**
 * Form API Class
 */
class Form {

	/**
	 * Get forms
	 *
	 * @return void wp send json
	 */
	public static function get_forms() {
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM %1s LIMIT %d OFFSET %d',
			$wpdb->prefix . DROIP_FORM_TABLE,
			10,
			0
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$forms = $wpdb->get_results( $query, ARRAY_A );
		foreach ( $forms as &$form ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result                = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT timestamp) AS total_entries FROM %1s WHERE form_id=%d',
					$wpdb->prefix . DROIP_FORM_DATA_TABLE,
					$form['id']
				),
				ARRAY_A
			);
			$form['total_entries'] = $result[0]['total_entries'];
		}

		wp_send_json( $forms );
	}

	/**
	 * Get forms data
	 *
	 * @return void wp send json
	 */
	public static function get_form_data() {
		global $wpdb;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_id = HelperFunctions::sanitize_text( isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$limit = HelperFunctions::sanitize_text( isset( $_GET['limit'] ) && is_numeric( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 10 );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page   = HelperFunctions::sanitize_text( isset( $_GET['page'] ) && is_numeric( $_GET['page'] ) ? absint( $_GET['page'] ) : 0 );
		$offset = $page * $limit;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$filters = HelperFunctions::sanitize_text( isset( $_GET['filters'] ) ? $_GET['filters'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$sort = HelperFunctions::sanitize_text( isset( $_GET['sort'] ) ? $_GET['sort'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$search_text = HelperFunctions::sanitize_text( isset( $_GET['search_text'] ) ? $_GET['search_text'] : '' );

		$filters = $filters ? json_decode( stripslashes( $filters ), true ) : null;
		$sort    = $sort ? json_decode( stripslashes( $sort ), true ) : null;

		$timestamp_query = self::prepare_timestamp_query(
			$form_id,
			array(
				'filters'     => $filters,
				'sort'        => $sort,
				'search_text' => $search_text,
			)
		);
		$count_query     = 'SELECT COUNT(timestamp) AS total FROM (' . $timestamp_query . ') AS T';
		$limit_query     = $wpdb->prepare( ' LIMIT %d, %d', array( $offset, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$total_count_data = $wpdb->get_results( $count_query, ARRAY_A );
		$total_count      = (int) $total_count_data[0]['total'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$timestamps = $wpdb->get_results( $timestamp_query . $limit_query, ARRAY_A );
		$form_data  = array();
		if ( count( $timestamps ) ) {
			$form_data_query = self::prepare_form_data_query( $timestamps, isset( $sort ) ? true : false );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$form_data = $wpdb->get_results( $form_data_query, ARRAY_A );
		}
		$table_name = $wpdb->prefix . DROIP_FORM_DATA_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$input_keys = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT input_key from %1s WHERE form_id=%d',
				$table_name,
				$form_id
			)
		);

		$res = self::prepare_form_data_response(
			$form_data,
			$input_keys,
			$total_count,
			$total_count - ( $offset + $limit ) <= 0
		);
		wp_send_json( $res );
	}

	/**
	 * Delete a from
	 *
	 * @return void wp send json
	 */
	public static function delete_form()
	{
		global $wpdb;

		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_id = HelperFunctions::sanitize_text(isset($_REQUEST['form_id']) ? absint($_REQUEST['form_id']) : null);

		// check if the form id is not null
		if (is_null($form_id)) {
			wp_send_json(false);
		};

		$form_table = $wpdb->prefix . DROIP_FORM_TABLE;

		// check if the form id exist in the database
		$form_exist = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $form_table WHERE id=%d",
				$form_id
			)
		);

		// if the form id does not exist
		if (empty($form_exist)) {
			wp_send_json(false);
		}

		try {
			$wpdb->query('START TRANSACTION');

			// Firstly, get file ids and delete the attachments
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$file_type_rows_query = $wpdb->prepare(
				"SELECT input_value FROM $wpdb->prefix" . DROIP_FORM_DATA_TABLE . " WHERE form_id=%d AND input_type='file'",
				$form_id
			);

			$file_type_ids = $wpdb->get_col($file_type_rows_query);

			if (count($file_type_ids)) {
				self::delete_attachments($file_type_ids);
			}

			// Secondly, delete all data related to the form id (foreign key) from the form data table first
			$form_data_table_name = $wpdb->prefix . DROIP_FORM_DATA_TABLE;
			$form_data_delete_query = $wpdb->prepare(
				"DELETE FROM $form_data_table_name WHERE form_id=%s",
				$form_id
			);

			// Finally, delete the form
			$form_delete_query = $wpdb->prepare(
				"DELETE FROM $form_table WHERE id=%s",
				$form_id
			);

			// execute the delete queries
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$form_data_delete_res = $wpdb->query($form_data_delete_query);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$form_delete_res = $wpdb->query($form_delete_query);

			if ($form_data_delete_res !== false && $form_delete_res !== false && $form_delete_res > 0) {
				$wpdb->query('COMMIT');
				wp_send_json(true);
			} else {
				$wpdb->query('ROLLBACK');
			}
		} catch(Exception $e) {
			$wpdb->query('ROLLBACK');
		}

		wp_send_json(false);
	}

	/**
	 * Delete from rows
	 *
	 * @return void wp send json
	 */
	public static function delete_form_row() {
		global $wpdb;

		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_id = HelperFunctions::sanitize_text( isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$row_ids     = json_decode(HelperFunctions::sanitize_text($_REQUEST['row_ids']), true);

		$placeholders    = array();
		$placeholder_str = '';

		if ( ! is_null( $form_id ) && ! empty( $row_ids ) ) {
			if ( is_array( $row_ids ) ) {
				$placeholders    = array_fill( 0, count( $row_ids ), '%s' );
				$placeholder_str = implode( ', ', $placeholders );
			}

			$form_data_table_name = $wpdb->prefix . DROIP_FORM_DATA_TABLE;

			// get file ids from as input value from the form data table
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$file_type_rows_query = $wpdb->prepare(
					"SELECT input_value FROM $form_data_table_name WHERE form_id=%s AND input_type='file' AND timestamp IN($placeholder_str)",
					array_merge(array($form_id), $row_ids)
			);

			$file_type_ids = $wpdb->get_col( $file_type_rows_query );

			if (count($file_type_ids)) {
				self::delete_attachments($file_type_ids);
			}

			// delete the rows from the form data table
			$query = $wpdb->prepare( 
				"DELETE FROM $form_data_table_name WHERE form_id=%s AND timestamp IN($placeholder_str)", 
				array_merge( array($form_id), $row_ids ) 
			);


			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$res = $wpdb->query( $query );

			if ( $res !== false ) {
				wp_send_json( true );
			}
		}
		wp_send_json( false );
	}

	/**
	 * Update form single data
	 *
	 * @return void wp send json
	 */
	public static function update_form_row() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_id = HelperFunctions::sanitize_text( isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$row_id = HelperFunctions::sanitize_text( isset( $_REQUEST['row_id'] ) ? absint( $_REQUEST['row_id'] ) : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = HelperFunctions::sanitize_text( isset( $_REQUEST['data'] ) ? $_REQUEST['data'] : null );
		$flag = false;

		$data = $data ? json_decode( stripslashes( $data ), true ) : null;

		if ( is_array( $data ) && count( $data ) ) {
			foreach ( $data as $column => $value ) {
				$res = self::update_form_cell( $form_id, $row_id, $column, $value );
				if ( $res ) {
					$flag = true;
				}
			}
		}
		wp_send_json( $flag );
	}

	/**
	 * Update forn data single cell
	 *
	 * @param int    $form_id form id.
	 * @param int    $row_id row id.
	 * @param string $column column name.
	 * @param int    $value value.
	 *
	 * @return boolean boolean
	 */
	private static function update_form_cell( $form_id, $row_id, $column, $value ) {
		global $wpdb;

		if ( ! empty( $form_id ) && ! empty( $row_id ) && ! empty( $column ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$res = $wpdb->update(
				$wpdb->prefix . DROIP_FORM_DATA_TABLE,
				array(
					'input_value' => $value,
				),
				array(
					'form_id'   => $form_id,
					'timestamp' => $row_id,
					'input_key' => $column,
				),
				array(
					'%s',
				),
				array(
					'%1s',
					'%1s',
					'%s',
				)
			);

			if ( $res !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Download form data
	 *
	 * @return void download data
	 */
	public static function download_form_data() {
		/* PRO_START */
		global $wpdb;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_id = HelperFunctions::sanitize_text( isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$all_selected = isset( $_REQUEST['allSelected'] ) ? json_decode( HelperFunctions::sanitize_text( $_REQUEST['allSelected'] ) ) : false;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$entries = isset( $_REQUEST['entries'] ) ? explode( ',', HelperFunctions::sanitize_text( $_REQUEST['entries'] ) ) : null;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$filter = isset( $_REQUEST['filter'] ) ? json_decode( stripslashes( HelperFunctions::sanitize_text( $_REQUEST['filter'] ) ), true ) : null;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$search_text       = isset( $_REQUEST['searchText'] ) ? HelperFunctions::sanitize_text( $_REQUEST['searchText'] ) : null;
		$columns_to_select = 'id, form_id, timestamp, input_key, input_value, created_at, updated_at';

		$table_name = $wpdb->prefix . DROIP_FORM_DATA_TABLE;

		if ( $all_selected ) {
			if ( ! empty( $filter ) && ! empty( $search_text ) ) {
				$filter_query = self::prepare_filter_query( $form_id, $filter );
				$text_query   = 'SELECT DISTINCT timestamp FROM (' . $filter_query . ') AS T WHERE ' . DbQueryUtils::contains( 'input_value', $search_text );
				$query        = 'SELECT ' . $columns_to_select . ' FROM ' . $wpdb->prefix . DROIP_FORM_DATA_TABLE . ' WHERE form_id=' . $form_id . ' AND timestamp IN(' . $text_query . ')';
			} elseif ( ! empty( $filter ) && empty( $search_text ) ) {
				$filter_query = self::prepare_filter_query( $form_id, $filter );
				$query        = $filter_query;
			} elseif ( ! empty( $search_text ) && empty( $filter ) ) {
				$text_query = 'SELECT DISTINCT timestamp FROM ' . $wpdb->prefix . DROIP_FORM_DATA_TABLE . ' WHERE ' . DbQueryUtils::contains( 'input_value', $search_text );
				$query      = 'SELECT ' . $columns_to_select . ' FROM ' . $wpdb->prefix . DROIP_FORM_DATA_TABLE . ' WHERE timestamp IN(' . $text_query . ')';
			} else {
				$query = 'SELECT ' . $columns_to_select . ' FROM ' . $wpdb->prefix . DROIP_FORM_DATA_TABLE . ' WHERE form_id=' . $form_id;
			}
		} elseif ( isset( $entries ) && count( $entries ) ) {
			$placeholder = array();
			$values      = array();

			foreach ( $entries as $entry ) {
				$placeholder[] = '%s';
				$values[]      = $entry;
			}
			$placeholder_str = implode( ', ', $placeholder );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$query = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT  %1s FROM %1s WHERE form_id=%d AND timestamp IN($placeholder_str)",
				array_merge(
					array(
						$columns_to_select,
						$table_name,
						$form_id,
					),
					$entries
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$form_data = $wpdb->get_results( $query, ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$input_keys = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT input_key from %1s WHERE form_id=%d',
				$table_name,
				$form_id
			)
		);
		$res        = self::prepare_form_data_response(
			$form_data,
			$input_keys,
			0,
			true
		);

		self::array_to_csv_download( $res['data'], $input_keys );
		die();
		/* PRO_END */
	}

	/**
	 * Array to CSV download
	 *
	 * @param array  $array data in array.
	 * @param array  $columns heading names.
	 * @param string $filename filename.
	 * @param string $delimiter delimiter.
	 *
	 * @return void
	 */
	private static function array_to_csv_download( $array, $columns, $filename = 'form-data.csv', $delimiter = ',' ) {
		header( 'Content-Type: application/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '";' );

		// open the "output" stream.
		// see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq.
		$f            = fopen( 'php://output', 'w' );
		$file_content = array();

		foreach ( $array as $line ) {
			$row = array();
			foreach ( $columns as $column ) {
				$row[] = isset( $line[ $column ] ) ? $line[ $column ] : '';
			}
			$file_content[] = $row;
		}

		// Column names.
		fputcsv( $f, $columns, $delimiter );
		// Data.
		foreach ( $file_content as $row ) {
			fputcsv( $f, $row, $delimiter );
		}

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $f );
	}

	/**
	 * Prepare timestamp query
	 *
	 * @param int    $form_id form id.
	 * @param object $filter_conditions filter conditions.
	 * @return string query.
	 */
	private static function prepare_timestamp_query( $form_id, $filter_conditions ) {
		global $wpdb;
		$query             = '';
		$filters           = null;
		$sort              = null;
		$search_text       = $filter_conditions['search_text'];
		$text_search_query = '';

		$table_name = $wpdb->prefix . DROIP_FORM_DATA_TABLE;

		if ( ! empty( $filter_conditions['sort'] ) ) {
			$sort = $filter_conditions['sort'];
		}
		if ( ! empty( $filter_conditions['filters'] ) ) {
			$filters = $filter_conditions['filters'];
		}

		if ( ! empty( $search_text ) ) {
			$text_search_query = DbQueryUtils::contains( 'input_value', $search_text );
		}

		if ( isset( $sort, $filters ) && count( $sort ) && count( $filters ) ) {
			$filter_query = self::prepare_filter_query( $form_id, $filters );
			$sort_query   = self::prepare_sort_query( $sort );
			if ( ! empty( $text_search_query ) ) {
				$filter_query_mod = $filter_query;
				$filter_query     = 'SELECT * FROM (' . $filter_query_mod . ') AS T WHERE ' . $text_search_query;
			}

			$query = 'SELECT DISTINCT(timestamp) FROM (' . $filter_query . ') AS T' . $sort_query;
		} elseif ( isset( $sort ) && count( $sort ) && ! isset( $filters ) ) {
			$additional_search_query = '';
			if ( ! empty( $text_search_query ) ) {
				$additional_search_query = ' AND ' . $text_search_query;
			}

			$query = $wpdb->prepare( 'SELECT DISTINCT timestamp FROM (SELECT * FROM %1s WHERE form_id=%d', $table_name, $form_id ) . $additional_search_query . ') AS T' . self::prepare_sort_query( $sort );
		} elseif ( isset( $filters ) && count( $filters ) && ! isset( $sort ) ) {
			$filter_query = self::prepare_filter_query( $form_id, $filters );
			if ( ! empty( $text_search_query ) ) {
				$filter_query_mod = $filter_query;
				$filter_query     = 'SELECT * FROM (' . $filter_query_mod . ') AS T WHERE ' . $text_search_query;
			}

			$query = 'SELECT DISTINCT(timestamp) FROM (' . $filter_query . ') AS T';
		} else {
			$additional_search_query = '';
			if ( ! empty( $text_search_query ) ) {
				$additional_search_query = ' AND ' . $text_search_query;
			}
			$query = $wpdb->prepare( 'SELECT DISTINCT(timestamp) FROM %1s WHERE form_id=%d', $table_name, $form_id ) . $additional_search_query;
		}

		return $query;
	}

	/**
	 * Prepare sort query
	 *
	 * @param string $sort sort data.
	 * @return string sort query
	 */
	private static function prepare_sort_query( $sort ) {
		global $wpdb;
		$sort_query      = '';
		$sort_clause     = '';
		$constant_fields = array( 'created_at', 'updated_at' );
		$field_name      = $sort['field_name'];
		$sort_option     = $sort['option'];

		switch ( $sort_option ) {
			case 'old_to_new':
			case 'z_a': {
				$sort_clause = 'DESC';
				break;
			}

			case 'new_to_old':
			case 'a_z':
			default:
				break;
		}

		if ( isset( $sort ) ) {
			if ( in_array( $field_name, $constant_fields, true ) ) {
				$sort_query = $wpdb->prepare( ' ORDER BY %1s %1s', array( $field_name, $sort_clause ) );
			} else {
				$sort_column_name = '';

				switch ( $sort_option ) {
					case 'old_to_new':
					//phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.WrongOpenercase
					case 'new_to_old': {
						$sort_column_name = 'created_at';
						break;
					}

					case 'a_z':
					case 'z_a':
					//phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.WrongOpenerdefault
					default: {
						$sort_column_name = 'input_value';
						break;
					}
				}

				// $sort_query = " WHERE input_key='" . $field_name . "' ORDER BY " . $sort_column_name . $sort_clause;

				$sort_query = $wpdb->prepare( ' WHERE input_key=%s ORDER BY %1s %1s', array( $field_name, $sort_column_name, $sort_clause ) );
			}
		}

		return $sort_query;
	}


	/**
	 * Prepare filter query
	 *
	 * @param int    $form_id form id.
	 * @param object $filters filter conditions.
	 * @return string query.
	 */
	private static function prepare_filter_query( $form_id, $filters ) {
		global $wpdb;
		$table_name      = $wpdb->prefix . DROIP_FORM_DATA_TABLE;
		$groups          = array();
		$constant_fields = array( 'created_at', 'updated_at' );
		$from_query      = $table_name;

		if ( is_array( $filters ) ) {
			foreach ( $filters as $filter ) {
				if ( isset( $groups[ $filter['fieldName'] ] ) ) {
					$groups[ $filter['fieldName'] ][] = $filter;
				} else {
					$groups[ $filter['fieldName'] ] = array( $filter );
				}
			}

			foreach ( $groups as $group_name => $group ) {
				$this_query_list = array();

				foreach ( $group as $filter ) {
					$this_query = '(';
					if ( isset( $filter['fieldName'], $filter['condition'] ) ) {
						$condition   = $filter['condition'];
						$column_name = '';

						if ( ! in_array( $group_name, $constant_fields, true ) ) {
							$column_name = 'input_value';
							$this_query .= $wpdb->prepare( 'input_key=%s AND ', array( $group_name ) );
						} else {
							$column_name = $group_name;
						}

						switch ( $condition ) {
							case DROIP_CONDITIONS_TEXT_TYPE['contains']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::contains( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['does_not_contain']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::does_not_contain( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['start_with']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::start_with( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['end_with']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::end_with( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['is']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::is( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['is_not']: {
								$value       = $filter['value'];
								$this_query .= DbQueryUtils::is_not( $column_name, $value );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['cell_is_not_empty']: {
								$this_query .= DbQueryUtils::cell_is_not_empty( $column_name );
								break;
							}

							case DROIP_CONDITIONS_TEXT_TYPE['cell_is_empty']: {
								$this_query .= DbQueryUtils::cell_is_empty( $column_name );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['today']: {
								$this_query .= DbQueryUtils::today( $column_name );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['this_week']: {
								$this_query .= DbQueryUtils::this_week( $column_name );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['last_month']: {
								$this_query .= DbQueryUtils::last_month( $column_name );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['last_year']: {
								$this_query .= DbQueryUtils::last_year( $column_name );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['between']: {
								$start       = $filter['from'];
								$end         = $filter['to'];
								$this_query .= DbQueryUtils::between( $column_name, $start, $end );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['before']: {
								$date        = $filter['value'];
								$this_query .= DbQueryUtils::before( $column_name, $date );
								break;
							}

							case DROIP_CONDITIONS_DATE_TYPE['after']: {
								$date        = $filter['value'];
								$this_query .= DbQueryUtils::after( $column_name, $date );
								break;
							}

							default:
								break;
						}
					}

					$this_query       .= ')';
					$this_query_list[] = $this_query;
				}

				$timestamp_filter = 'SELECT DISTINCT(timestamp) FROM ' . $from_query . $wpdb->prepare( ' WHERE form_id=%s', array( $form_id ) ) . ' AND ' . implode( ' AND ', $this_query_list );

				$full_filter_query  = 'SELECT * FROM ' . $table_name . ' WHERE timestamp IN(';
				$full_filter_query .= $timestamp_filter;
				$full_filter_query .= ')';
				$from_query         = '(' . $full_filter_query . ') AS T';
			}
		}

		return $full_filter_query;
	}

	/**
	 * Prepare form data query
	 *
	 * @param string  $timestamps timestamps.
	 * @param boolean $ordered true|false.
	 * @return string query.
	 */
	private static function prepare_form_data_query( $timestamps, $ordered = false ) {
		global $wpdb;
		$timestamps_values = array();
		foreach ( $timestamps as $timestamp ) {
			$timestamps_values[] = $timestamp['timestamp'];
		}

		$timestamps_str  = implode( ',', $timestamps_values );
		$timestamp_order = $ordered ? ' ORDER BY FIELD(timestamp, ' . $timestamps_str . ')' : '';
		return 'SELECT id, form_id, timestamp, input_key, input_value, input_type, created_at, updated_at from ' . $wpdb->prefix . DROIP_FORM_DATA_TABLE . ' WHERE timestamp IN(' . $timestamps_str . ')' . $timestamp_order;
	}

	/**
	 * Prepare form data response
	 *
	 * @param array   $form_data_unstractured form_data_unstractured.
	 * @param string  $keys keys.
	 * @param int     $total_count total_count.
	 * @param boolean $done done.
	 * @return array
	 */
	private static function prepare_form_data_response( $form_data_unstractured, $keys, $total_count, $done = false ) {
		$data = array();
		array_push( $keys, 'created_at' );
		$types = array();

		if ( count( $form_data_unstractured ) ) {
			foreach ( $form_data_unstractured as $form_data_item ) {
				if ( empty( $data[ $form_data_item['timestamp'] ]['created_at'] ) ) {
					$data[ $form_data_item['timestamp'] ]['created_at'] = $form_data_item['created_at'];
				}

				if ( empty( $data[ $form_data_item['timestamp'] ]['form_id'] ) ) {
					$data[ $form_data_item['timestamp'] ]['form_id'] = $form_data_item['form_id'];
				}

				if ( empty( $data[ $form_data_item['timestamp'] ]['id'] ) ) {
					$data[ $form_data_item['timestamp'] ]['id'] = $form_data_item['timestamp'];
				}

				if ( isset( $data[ $form_data_item['timestamp'] ] ) ) {
					$data[ $form_data_item['timestamp'] ][ $form_data_item['input_key'] ] = $form_data_item['input_value'];
				} else {
					$data[ $form_data_item['timestamp'] ] = array(
						$form_data_item['input_key'] => $form_data_item['input_value'],
					);
				}

				$types[$form_data_item['input_key']] = $form_data_item['input_type'];

				if ( isset($form_data_item['input_type']) && $form_data_item['input_type'] === 'file' ) {
					$file_url = wp_get_attachment_url( $form_data_item['input_value'] );
					$file_url = !empty($file_url) ? esc_url($file_url) : '';
					$data[ $form_data_item['timestamp'] ][ $form_data_item['input_key'] ] = $file_url;
				}
				
			}
		}

		return array(
			'fields'      => array_values( $keys ),
			'data'        => array_values( $data ),
			'types' => $types,
			'total_count' => $total_count,
			'done'        => $done,
		);
	}

	/**
	 * Delete file from post and postmeta table
	 * 
	 * @param array $file_ids file ids
	 *
	 * @return void
	 */

	private static function delete_attachments($file_ids)
	{
		foreach ($file_ids as $file_id) {
			wp_delete_attachment($file_id, true);
		}
	}
}
