<?php
/**
 * Collaboration server
 *
 * @package droip
 */

namespace Droip\Ajax\Collaboration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;


/**
 * Sender class will send and manage collaboration data, also tracking send info.
 */
class  Sender {

	/**
	 * Sender instance which will send event-strem as content type
	 *
	 * @return void
	 */
	public function __construct() {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
	}

	/**
	 * Start method will start sending to single client.
	 *
	 * @return void
	 */
	public function start() {
		$this->fetch_and_send_events();
	}

	/**
	 * This method will collect active events and send events to a perticular user.
	 * This mathod also seperate event using parent and parent_id.
	 *
	 * @return void
	 */
	private function fetch_and_send_events() {

		// Fetch events from your data source.
		$events = $this->get_custom_events(); // Implement this function to retrieve your custom events.

		foreach ( $events as $event ) {
			$event_data = $event['data'];

			$parent    = $event['parent'];
			$parent_id = $event['parent_id'];
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "event: collaboration-$parent-$parent_id\n"; // Specify the event name.
			// this data is a json data. No need to Escape.
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "data: $event_data\n\n";

			// Flush the output buffer to send the event immediately.
			ob_flush();
			flush();
		}
	}

	/**
	 * Get custom events.
	 * Remove same user and same session.
	 */
	private static function get_custom_events() {
		self::clean_expired_rows();
		// Get the current user's session ID.
		$session_id = HelperFunctions::sanitize_text($_GET['session_id']);
		$events     = array();
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM %1s WHERE session_id != %s AND status = %d ORDER BY created_at ASC LIMIT %d OFFSET %d',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE,
			$session_id,
			1,
			10,
			0
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query, ARRAY_A );
		foreach ( $rows as &$row ) {
			if ( ! self::is_already_sent( $row['id'], $session_id ) ) {
				$events[] = $row;
				self::add_to_sent_tracking_table( $row['id'], $session_id );
			}
			self::delete_single_collaboration( $row );
		}

		return $events;
	}

	/**
	 * Add events send status data inside sent table for tracking.
	 *
	 * @param int    $collaboration_id collbaration table id.
	 * @param string $session_id user current session id.
	 * @return void
	 */
	private static function add_to_sent_tracking_table( $collaboration_id, $session_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_sent',
			array(
				'collaboration_id' => (int) $collaboration_id,
				'session_id'       => $session_id,
			),
			array(
				'%d',
				'%s',
			)
		);
	}

	/**
	 * Check event is already sent or not.
	 *
	 * @param int    $collaboration_id collbaration table id.
	 * @param string $session_id user current session id.
	 * @return bool
	 */
	private static function is_already_sent( $collaboration_id, $session_id ) {
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM %1s WHERE collaboration_id = %d AND session_id = %s',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_sent',
			$collaboration_id,
			$session_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$d = $wpdb->get_row( $query );
		if ( $d ) {
			return true;
		}
		return false;
	}

	/**
	 * Check event is already sent or not.
	 *
	 * @param array $collaboration collbaration table single row.
	 * @return void
	 */
	private static function delete_single_collaboration( $collaboration ) {
		global $wpdb;
		$collaboration_id   = $collaboration['id'];
		$my_session_id      = $collaboration['session_id'];
		$all_connected_rows = Collaboration::get_all_connected_rows();
		$flag               = true;

		if ( $all_connected_rows ) {
			foreach ( $all_connected_rows as $key => $row ) {
				if ( ! self::is_already_sent( $collaboration_id, $row->session_id ) && $row->session_id !== $my_session_id ) {
					$flag = false;
					break;
				}
			}
		}

		if ( $flag ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->prefix . DROIP_COLLABORATION_TABLE, array( 'id' => $collaboration_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_sent', array( 'collaboration_id' => $collaboration_id ) );
		}
	}

	/**
	 * Clean expired row.
	 * expired if collboration data sent with in 60sec.
	 *
	 * @return void
	 */
	private static function clean_expired_rows() {
		global $wpdb;
		$sixty_seconds_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-20 seconds' ) );
		$query1            = $wpdb->prepare(
			'DELETE FROM %1s WHERE updated_at <= %s',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE,
			$sixty_seconds_ago
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $query1 );

		$query2 = $wpdb->prepare(
			'DELETE FROM %1s WHERE updated_at <= %s',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_sent',
			$sixty_seconds_ago
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $query2 );
	}

}