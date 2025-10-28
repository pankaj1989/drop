<?php
/**
 * Collaboration controller
 *
 * @package droip
 */

namespace Droip\Ajax\Collaboration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;

/**
 * Collaboration class for running and getting the collaboration process.
 */
class Collaboration {

	/**
	 * This method will trigger from builder ajax actions.
	 * This method will save all type and action related data inside data column.
	 *
	 * @return void wp_send_json
	 */
	public static function save_actions() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		$data = json_decode( stripslashes( $data ), true );
		$session_id = HelperFunctions::sanitize_text(isset( $_POST['session_id'] ) ? $_POST['session_id'] : '' );

		$parent    = $data['parent'];
		$parent_id = $data['parent_id'];
		$action    = $data['action'];
		$status    = 1;

		$d = self::save_action_to_db( $parent, $parent_id, $action, $status, $session_id );
		wp_send_json_success(
			$d
		);
	}

	/**
	 * Save action to db function will save a event if more than one people connected.
	 *
	 * @param string $parent post | styleblock for now.
	 * @param string $parent_id post id or 0 if global data.
	 * @param array  $data total event data.
	 * @param int    $status event status.
	 *
	 * @return bool,array if success.
	 */
	public static function save_action_to_db( $parent, $parent_id, $data, $status = 1, $session_id = '', $cleanup = true ) {
		if ( count( self::get_all_connected_rows($cleanup) ) > 1 ) {
			$user_id    = get_current_user_id();

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . DROIP_COLLABORATION_TABLE,
				array(
					'user_id'    => (int) $user_id,
					'session_id' => $session_id,
					'parent'     => $parent,
					'parent_id'  => (int) $parent_id,
					'data'       => wp_json_encode( $data ),
					'status'     => (int) $status,
				),
				array(
					'%d',
					'%s',
					'%s',
					'%d',
					'%s',
					'%d',
				)
			);
			return array(
				'id'         => $wpdb->insert_id,
				'session_id' => $session_id,
			);
		}

		return false;
	}

	/**
	 * Send action is The main eventsouce function.
	 * this method will start the eventsouce mechanism.
	 *
	 * @return void
	 */
	public static function send_actions() {
		self::save_connection_data();
		$sender = new Sender();
		$sender->start();
		self::clean_disconnected_rows();
		exit();
	}

	/**
	 * Save connection data
	 */
	private static function save_connection_data() {
		$session_id = HelperFunctions::sanitize_text($_GET['session_id']);
		$post_id = HelperFunctions::sanitize_text($_GET['post_id']);
		$user_id    = get_current_user_id();

		if ( ! self::get_connection( $session_id ) ) {
			self::add_connection( $user_id, $session_id, $post_id );
		}

	}

	/**
	 * Add connection if a user is give eventsource request.
	 *
	 * @param int    $user_id wp user id.
	 * @param string $session_id current user session id.
	 */
	private static function add_connection( $user_id, $session_id, $post_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected',
			array(
				'user_id'    => (int) $user_id,
				'post_id'    => (int) $post_id,
				'session_id' => $session_id,
			),
			array(
				'%d',
				'%d',
				'%s',
			)
		);
		if ( $wpdb->insert_id ) {
			// TODO: send connection id to all connected users for add
			$this_connection = self::get_connection( $session_id );
			
			$data = array(
				'type'    => 'COLLABORATION_ADD_CONNECTION',
				'payload' => array('data' => $this_connection),
			);
			self::save_action_to_db('post', $post_id, $data, 1, $session_id);
			
			return $this_connection;
		}
		return false;
	}

	/**
	 * Get single connection.
	 *
	 * @param string $session_id current user session id.
	 *
	 * @return bool,array
	 */
	public static function get_connection( $session_id ) {
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM %1s WHERE session_id = %s',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected',
			$session_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$this_connection = $wpdb->get_row( $query );
		if ( $this_connection ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected',
				array(
					'updated_at' => $date,
				),
				array(
					'session_id' => $session_id,
				),
				array(
					'%s',
				),
				array(
					'%s',
				)
			);
			return self::format_connection_data($this_connection);
		}
		return false;
	}

	/**
	 * Get all connected rows.
	 * first clean disconnected rows then get only recent connected rows.
	 *
	 * @return array
	 */
	public static function get_all_connected_rows($cleanup = true) {
		if($cleanup){
			self::clean_disconnected_rows();
		}
		global $wpdb;
		$query2 = $wpdb->prepare(
			'SELECT * FROM %1s',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$res = $wpdb->get_results( $query2 );
		return $res;
	}

	public static function get_connected_collaboration_users_list($post_id){
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM %1s WHERE post_id = %d',
			$wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected',
			$post_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$res = $wpdb->get_results( $query );

		foreach ($res as $key => $connection) {
			$res[$key] = self::format_connection_data($connection);
		}
		return $res;
	}

private static function format_connection_data($connection){
	$connection->user_name = get_the_author_meta( 'display_name', $connection->user_id );
	return $connection;
}

	/**
	 * Clean disconnected rows if inactive less then 50 seconds.
	 *
	 * @return void
	 */
	public static function clean_disconnected_rows() {
		global $wpdb;

		$fifty_seconds_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-20 seconds' ) );
		$table_name        = $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected';

		// Get all expired connections (session_id + post_id in one query)
		$query = $wpdb->prepare(
			"SELECT session_id, post_id FROM $table_name WHERE updated_at <= %s",
			$fifty_seconds_ago
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$connections = $wpdb->get_results( $query );

		if ( empty( $connections ) ) {
			return;
		}

		// Step 1: Broadcast removal for each connection
		foreach ( $connections as $connection ) {
			$data = array(
				'type'    => 'COLLABORATION_REMOVE_CONNECTION',
				'payload' => array( 'session_id' => $connection->session_id ),
			);
			self::save_action_to_db( 'post', $connection->post_id, $data, 1, $connection->session_id, false );
		}

		// Step 2: Bulk delete all expired sessions in one query
		$session_ids = wp_list_pluck( $connections, 'session_id' );
		$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );

		$delete_sql = $wpdb->prepare(
			"DELETE FROM $table_name WHERE session_id IN ($placeholders)",
			$session_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $delete_sql );
	}

	/**
	 * Delete connection if a user gives event close request.
	 *
	 * @param string $session_id current user session id.
	 * @param bool   $send       whether to broadcast removal.
	 */
	public static function delete_connection( $session_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected';

		$query = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE session_id = %s",
				$session_id
			);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$this_connection = $wpdb->get_row( $query );

		if ( $this_connection ) {
			$post_id = $this_connection->post_id;
			$data    = array(
				'type'    => 'COLLABORATION_REMOVE_CONNECTION',
				'payload' => array( 'session_id' => $session_id ),
			);

			self::save_action_to_db( 'post', $post_id, $data, 1, $session_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table_name, array( 'session_id' => $session_id ) );
	}

}