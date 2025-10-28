<?php
/**
 * Droip Configurations
 *
 * @package droip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DROIP_VERSION', '2.4.0' );
define( 'DROIP_CORE_PLUGIN_URL', 'https://droip.com' );
define( 'DROIP_PUBLIC_ASSETS_DOMAIN', 'droip.s3.amazonaws.com' );
define( 'DROIP_PUBLIC_ASSETS_URL', 'https://' . DROIP_PUBLIC_ASSETS_DOMAIN . '/dist' );
define( 'DROIP_APP_NAME', 'Droip' );
define( 'DROIP_APP_PREFIX', 'droip' );
define( 'DROIP_CLASS_PREFIX', 'droip' );
define( 'DROIP_TEXT_DOMAIN', 'droip' );
define( 'DROIP_DEFAULT_CACHE_GROUP', 'droip' );
define( 'DROIP_APP_IFRAME_ID', 'droip-canvas' );

define( 'DROIP_META_NAME_FOR_POST_EDITOR_MODE', 'droip_editor_mode' );
define( 'DROIP_META_NAME_FOR_POST_DATA', 'droip' );
define( 'DROIP_META_NAME_FOR_USED_STYLE_BLOCK_IDS', 'droip_used_style_block_ids' );
define( 'DROIP_META_NAME_FOR_USED_FONT_LIST', 'droip_used_font_list' );
define( 'DROIP_META_NAME_FOR_STAGED_VERSIONS', 'droip_stage_versions');
define( 'DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS', 'droip_disabled_page_symbols');


// droip apps 
define('IS_DEVELOPING_DROIP_APPS', false);
if (IS_DEVELOPING_DROIP_APPS) define('DROIP_APPS_BASE_URL', content_url() . '/droip-apps-dev/build');
else define('DROIP_APPS_BASE_URL', DROIP_PUBLIC_ASSETS_URL . '/droip-apps');
define('DROIP_DEVELOPING_APPS_INCLUDES', plugin_dir_path(__FILE__) . '../../droip-apps-dev/index.php');


/* all types of file paths start */
define( 'DROIP_PLUGIN_REL_URL', dirname( plugin_basename( __FILE__ ) ) );
define( 'DROIP_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'DROIP_ASSETS_URL', DROIP_ROOT_URL . 'assets/' );
define( 'DROIP_ROOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DROIP_FULL_CANVAS_TEMPLATE_PATH', str_replace('\\', '/', DROIP_ROOT_PATH .'includes' . DIRECTORY_SEPARATOR .'Frontend' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'page.php') );
define( 'DROIP_SLUG', plugin_basename(DROIP_ROOT_PATH . 'droip.php') );
/* all types of file paths end */

/* all types of post types start */
define( 'DROIP_GLOBAL_DATA_POST_TYPE_NAME', DROIP_APP_PREFIX . '_global_data' );
define( 'DROIP_POST_TYPE', DROIP_APP_PREFIX . '_post' );
define( 'DROIP_SYMBOL_TYPE', DROIP_APP_PREFIX . '_symbol' );
define( 'DROIP_CONTENT_MANAGER_PREFIX', DROIP_APP_PREFIX .'_cm' );
/* all types of post types end */

/* all types of option meta types start */
define( 'DROIP_USER_CONTROLLER_META_KEY', DROIP_APP_PREFIX . '_user_controller' );
define( 'DROIP_GLOBAL_STYLE_BLOCK_META_KEY', DROIP_APP_PREFIX . '_global_style_block' );
define( 'DROIP_USER_SAVED_DATA_META_KEY', DROIP_APP_PREFIX . '_user_saved_data' );
define( 'DROIP_USER_CUSTOM_FONTS_META_KEY', DROIP_APP_PREFIX . '_user_custom_fonts' );
define( 'DROIP_PAGE_SEO_SETTINGS_META_KEY', DROIP_APP_PREFIX . '_page_seo_settings' );
define( 'DROIP_PAGE_CUSTOM_CODE', DROIP_APP_PREFIX . '_page_custom_code' );

define( 'DROIP_WP_ADMIN_COMMON_DATA', DROIP_APP_PREFIX . '_wp_admin_common_data' );
/* all types of option meta types end */

/* all types of user meta types start */
define( 'DROIP_USER_WALKTHROUGH_SHOWN_META_KEY', DROIP_APP_PREFIX . '_user_walkthrough_shown_state' );
/* all types of user meta types end */

define( 'DROIP_EDITOR_ACTION', DROIP_APP_PREFIX );

define(
	'DROIP_ACCESS_LEVELS',
	array(
		'NO_ACCESS'      => 'no',
		'FULL_ACCESS'    => 'full',
		'CONTENT_ACCESS' => 'content',
		'VIEW_ACCESS'    => 'view',
	)
);
define( 'DROIP_USERS_DEFAULT_FULL_ACCESS', array( 'administrator', 'editor' ) );

/* Custom DB table names */
define( 'DROIP_FORM_TABLE', DROIP_APP_PREFIX . '_forms' );
define( 'DROIP_FORM_DATA_TABLE', DROIP_APP_PREFIX . '_forms_data' );
define( 'DROIP_COLLABORATION_TABLE', DROIP_APP_PREFIX . '_collaborations' );
define( 'DROIP_CM_REFERENCE_TABLE', DROIP_APP_PREFIX . '_cm_reference' );
define( 'DROIP_COMMENTS_TABLE', DROIP_APP_PREFIX . '_comments' );
/* Custom DB table names */

define(
	'DROIP_SUPPORTED_MEDIA_TYPES',
	array(
		'image'  => array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' ),
		'video'  => array( 'video/mp4', 'video/ogg', 'video/quicktime' ),
		'svg'    => array( 'image/svg+xml' ),
		'audio'  => array( 'audio/mpeg', 'audio/ogg' ),
		'json'   => array( 'application/json' ),
		'lottie' => array( 'text/plain' ),
		'pdf'    => array( 'application/pdf'),
	)
);


define(
	'DROIP_SUPPORTED_MEDIA_TYPES_FOR_FILE_INPUT',
	array(
		'default'  => array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/ogg', 'video/quicktime', 'application/pdf', 'application/msword', 'text/plain'),
		'.doc, .pdf, .txt'  => array('application/pdf', 'application/msword', 'text/plain'),
		'.mp4, .mov'  => array('video/mp4', 'video/ogg', 'video/quicktime'),
		'.jpg, .jpeg, .png, .gif'    => array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'),
	)
);

define(
	'DROIP_WORDPRESS_SORT_BY_OPTIONS',
	['none','ID', 'author', 'title', 'name','type', 'date', 'modified', 'parent', 'comment_count', 'menu_order']
);

define('DROIP_PRESERVED_CLASS_LIST', [
	DROIP_CLASS_PREFIX . '-current-tab',
	DROIP_CLASS_PREFIX . '-tab-active',
	DROIP_CLASS_PREFIX . '-slide-active',
	DROIP_CLASS_PREFIX . '-navigation-item-active',
	DROIP_CLASS_PREFIX . '-active',
	DROIP_CLASS_PREFIX . '-pagination-item-active',
	DROIP_CLASS_PREFIX . '-active-link',
]);

define('DROIP_PLUGIN_SETTINGS', [
	'INPUT_TEXT' => ['type' => 'inputtext', 'placeholder' => 'Placeholder text'],
	'INPUT' => ['type' => 'input', 'placeholder' => 'Placeholder text'],
	'INPUT_NUMBER' => ['type' => 'inputnumber', 'placeholder' => 'Placeholder text'],
	'DYNAMIC_INPUT' => ['type' => 'dynamicinput', 'placeholder' => 'Placeholder text'],
	'CHECKBOX' => ['type' => 'checkbox'],
	'TOGGLER' => ['type' => 'toggler'],
	'COLOR_PICKER' => ['type' => 'colorpicker', 'title' => 'Heading Color'],
	'SELECT' => [
			'type' => 'select',
			'options' => [
					[
							'value' => 'value1',
							'title' => 'Value One',
					],
					[
							'value' => 'value2',
							'title' => 'Value Two',
					],
			],
	],
	'DIVIDER_FULL' => ['type' => 'divider', 'style' => 'full'],
	'DIVIDER_HALF' => ['type' => 'divider', 'style' => 'half'],
	'DIVIDER_TRANSPARENT' => ['type' => 'divider_tansparent'],
	'TAB' => ['type' => 'tab', 'tabs' => []],
	'DATEPICKER' => ['type' => 'datepicker'],
	'SELECT_WITH_WP_POST_SUGGESTION' => ['postType' => DROIP_APP_PREFIX . '_popup', 'type'=> 'select_with_wp_post_suggestion']
]);