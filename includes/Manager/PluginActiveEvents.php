<?php
/**
 * Plugin active events handler
 *
 * @package droip
 */

namespace Droip\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Droip\HelperFunctions;


/**
 * Do some task during plugin activation
 */
class PluginActiveEvents {


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct() {
		$installed = get_option( DROIP_APP_PREFIX . '_installed' );
		if ( ! $installed ) {
			update_option( DROIP_APP_PREFIX . '_installed', time() );
		}

		HelperFunctions::set_droip_version_in_db();

		$this->update_rbac();
		self::create_custom_tables();
		$this->store_log();
	}

	/**
	 * Create custom tables
	 *
	 * @return void
	 */
	public static function create_custom_tables() {
		self::create_forms_table();
		self::create_collaboration_table();
		self::create_cm_reference_table();
		self::create_comments_table();
	}

	/**
	 * Update role manager data
	 *
	 * @return void
	 */
	private function update_rbac() {
		global $wp_roles;
		$role_names = $wp_roles->role_names;

		if ( is_array( $role_names ) && count( $role_names ) ) {
			foreach ( $role_names as $key => $value ) {
				$exist = get_option( DROIP_APP_PREFIX . '_' . $key );
				if ( ! $exist ) {
					if ( in_array( $key, DROIP_USERS_DEFAULT_FULL_ACCESS, true ) ) {
						add_option( DROIP_APP_PREFIX . '_' . $key, DROIP_ACCESS_LEVELS['FULL_ACCESS'] );
					} else {
						add_option( DROIP_APP_PREFIX . '_' . $key, DROIP_ACCESS_LEVELS['NO_ACCESS'] );
					}
				}
			}
		}
	}

	/**
	 * Create forms table
	 *
	 * @return void
	 */
	private static function create_forms_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$form_table = $wpdb->prefix . DROIP_FORM_TABLE;
		$form_table_query = "CREATE TABLE IF NOT EXISTS $form_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			form_ele_id VARCHAR(255) NOT NULL,
			name VARCHAR(255) DEFAULT 'My Droip Form',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$form_data_table = $wpdb->prefix . DROIP_FORM_DATA_TABLE;
		$form_data_table_query = "CREATE TABLE IF NOT EXISTS $form_data_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			session_id VARCHAR(255) NOT NULL,
			timestamp BIGINT NOT NULL,
			input_key VARCHAR(255) NOT NULL,
			input_value VARCHAR(2000) NOT NULL,
			input_type VARCHAR(100) NOT NULL DEFAULT 'text',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
			-- FOREIGN KEY constraints are not handled by dbDelta reliably
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($form_table_query);
		dbDelta($form_data_table_query);
	}

	/**
	 * Create forms table
	 *
	 * @return void
	 */
	private static function create_collaboration_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_name = $wpdb->prefix . DROIP_COLLABORATION_TABLE;
		$sql1       = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      user_id bigint(20) NOT NULL,
      session_id varchar(50) NOT NULL,
      parent varchar(255) NOT NULL COMMENT 'table name',
      parent_id bigint(20) COMMENT 'table row id', 
      data longtext COMMENT 'json data', 
      status int(1) NOT NULL COMMENT '1=active, 2=sent, 0=expire', 
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

		$table_name = $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected';
		$sql2       = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id bigint(20) NOT NULL,
		post_id bigint(20) NOT NULL,
		session_id varchar(50) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) $charset_collate;";

		$table_name = $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_sent';
		$sql3       = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		collaboration_id bigint(20) NOT NULL,
		session_id varchar(50) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public static function add_post_field_in_collaboaration_connected_table(){
		global $wpdb;
		$table_name = $wpdb->prefix . DROIP_COLLABORATION_TABLE . '_connected';
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE 'post_id'" );

		if ( empty( $columns ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE $table_name ADD COLUMN post_id bigint(20) NOT NULL AFTER user_id"
			);
		}
	}

	/**
	 * Droip reference table
	 *
	 * @return void
	 */
	private static function create_cm_reference_table()
	{
		/**
		 * table name -> wp_droip_cm_reference
		 * table key -> post_id (foreign key) -> wp_posts -> ID (cascade)
		 * table key -> field_meta_key (foreign key) -> wp_postmeta -> meta_key (cascade)
		 * table key -> ref_post_id (foreign key) -> wp_posts -> ID (cascade)
		 */
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_name = $wpdb->prefix . DROIP_CM_REFERENCE_TABLE;
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
							id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
							post_id BIGINT(20) UNSIGNED NOT NULL,
							field_meta_key VARCHAR(255) NOT NULL,
							ref_post_id BIGINT(20) UNSIGNED NOT NULL,
							PRIMARY KEY (id),
							INDEX (post_id),
							INDEX (ref_post_id),
							FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE,
							FOREIGN KEY (ref_post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE
			) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
	
	private static function create_comments_table()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_suffix = DROIP_COMMENTS_TABLE;
		$table_name = $wpdb->prefix . $table_suffix;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			parent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			comment TEXT NOT NULL,
			meta_data VARCHAR(255) NOT NULL,
			status INT(1) NOT NULL COMMENT '1=active, 2=resolved, 0=deleted',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE
		) ENGINE=InnoDB $charset_collate;";

		$seen_table_name = $wpdb->prefix . $table_suffix . '_seen';
		$sql2 = "CREATE TABLE IF NOT EXISTS $seen_table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			comment_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_comment_unique (user_id, comment_id),
			FOREIGN KEY (comment_id) REFERENCES $table_name(id) ON DELETE CASCADE
		) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		dbDelta($sql2);
	}

	/**
	 * Stores the log.
	 *
	 * @return void
	 */
	private function store_log() {
		$site_url = get_site_url();

		HelperFunctions::http_get( DROIP_CORE_PLUGIN_URL . '?log_data=activity&event=activate&version=' . DROIP_VERSION . '&domain=' . $site_url);
	}


}