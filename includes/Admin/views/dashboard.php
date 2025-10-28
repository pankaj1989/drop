<?php
/**
 * WP Droip Dashboard React root renderer
 *
 * @package droip
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;

echo '<div id="' . esc_html( DROIP_CLASS_PREFIX ) . '-app"></div>';

$url_arr = HelperFunctions::get_post_url_arr_from_post_id( get_the_ID() );

$last_edited_droip_editor_page = HelperFunctions::get_last_edited_droip_editor_type_page();
// var_dump($last_edited_droip_editor_page);die();
$last_edited_droip_editor_page_url_arr = ['editor_url'=>false];
if($last_edited_droip_editor_page){
	$last_edited_droip_editor_page_url_arr = HelperFunctions::get_post_url_arr_from_post_id( $last_edited_droip_editor_page->ID );
}
?>

<script>
window.wp_droip = {
	ajaxUrl: "<?php echo esc_url( $url_arr['ajax_url'] ); ?>",
	restUrl: "<?php echo esc_url( $url_arr['rest_url'] ); ?>",
	hasValidLicense: "<?php echo HelperFunctions::is_pro_user(); ?>",
	nonce: "<?php echo esc_html( $url_arr['nonce'] ); ?>", 
	version: "<?php echo DROIP_VERSION; ?>", 
	adminUrl: "<?php echo esc_html( $url_arr['admin_url'] ); ?>", 
	droipWPDashboard: "<?php echo esc_html ( $url_arr['site_url'] );?>/wp-admin",
	last_edited_droip_editor_page_url: "<?php echo esc_url_raw( $last_edited_droip_editor_page_url_arr['editor_url'] ); ?>",
};
</script>
