<?php
/**
 * DbQueryUtils for form data management
 *
 * @package droip
 */

namespace Droip;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * DbQueryUtils Class
 */
class DbQueryUtils {

	/**
	 * Find query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function contains( $column, $str ) {
		global $wpdb;
		$wild    = '%';
		$esc_str = $wpdb->esc_like( $str );
		$like    = $wild . $esc_str . $wild;
		return $wpdb->prepare( '%1s LIKE %s', array( $column, $like ) );
	}

	/**
	 * Does_not_contain query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function does_not_contain( $column, $str ) {
		global $wpdb;
		$wild    = '%';
		$esc_str = $wpdb->esc_like( $str );
		$like    = $wild . $esc_str . $wild;
		return $wpdb->prepare( '%1s NOT LIKE %s', array( $column, $like ) );
	}

	/**
	 * Start_with query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function start_with( $column, $str ) {
		global $wpdb;
		$wild    = '%';
		$esc_str = $wpdb->esc_like( $str );
		$like    = $esc_str . $wild;
		return $wpdb->prepare( '%1s LIKE %s', array( $column, $like ) );
	}

	/**
	 * End_with query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function end_with( $column, $str ) {
		global $wpdb;
		$wild    = '%';
		$esc_str = $wpdb->esc_like( $str );
		$like    = $wild . $esc_str;
		return $wpdb->prepare( '%1s LIKE %s', array( $column, $like ) );
	}

	/**
	 * Is query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function is( $column, $str ) {
		global $wpdb;
		return $wpdb->prepare( '%1s=%s', array( $column, $str ) );
	}

	/**
	 * Is_not query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $str which string needs to find.
	 * @return string
	 */
	public static function is_not( $column, $str ) {
		global $wpdb;
		return $wpdb->prepare( '%1s <> %s', array( $column, $str ) );
	}

	/**
	 * Cell_is_not_empty query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function cell_is_not_empty( $column ) {
		global $wpdb;
		return $wpdb->prepare( "%1s <> '' OR %1s <> NULL", array( $column, $column ) );
	}

	/**
	 * Cell_is_empty query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function cell_is_empty( $column ) {
		global $wpdb;
		return $wpdb->prepare( "%1s = '' OR %1s = NULL", array( $column, $column ) );
	}

	/**
	 * Today query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function today( $column ) {
		global $wpdb;
		//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->prepare( 'DATE(%1s) = ' . date( 'Y-m-d' ), array( $column ) );
	}

	/**
	 * This_week query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function this_week( $column ) {
		global $wpdb;
		//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->prepare( 'WEEK(%1s) = ' . date( 'W' ) . ' AND YEAR(%1s) = ' . date( 'Y' ), array( $column, $column ) );
	}

	/**
	 * Last_month query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function last_month( $column ) {
		global $wpdb;
		return $wpdb->prepare( 'YEAR(%1s) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(%1s) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)', array( $column, $column ) );
	}

	/**
	 * Last_year query builder
	 *
	 * @param string $column The name of the column.
	 * @return string
	 */
	public static function last_year( $column ) {
		global $wpdb;
		//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->prepare( 'YEAR(%1s) - 1 = ' . ( date( 'Y' ) - 1 ), array( $column ) );
	}

	/**
	 * Between query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $start start time.
	 * @param string $end end time.
	 * @return string
	 */
	public static function between( $column, $start, $end ) {
		global $wpdb;
		return $wpdb->prepare( '%1s BETWEEN %s AND %s', array( $column, $start, $end ) );
	}

	/**
	 * Before query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $date date.
	 * @return string
	 */
	public static function before( $column, $date ) {
		global $wpdb;
		return $wpdb->prepare( 'DATE(%1s) < %s', array( $column, $date ) );
	}

	/**
	 * Start_with query builder
	 *
	 * @param string $column The name of the column.
	 * @param string $date date.
	 * @return string
	 */
	public static function after( $column, $date ) {
		global $wpdb;
		return $wpdb->prepare( '%1s > %s', array( $column, $date ) );
	}
}
