<?php

/**
 * Template Name: Full-width page layout
 * Template Post Type: page, post
 *
 * @package droip
 */

use Droip\Frontend\Preview\Preview;
use Droip\HelperFunctions;

if (!defined('ABSPATH')) {
	exit;
}

$custom_data = get_query_var(DROIP_APP_PREFIX . '_custom_data');
$the_content = false;
$meta_tags = false;

$template_data = HelperFunctions::get_template_data_if_current_page_is_droip_template();
if ($template_data) {
	$the_content = $template_data['content'];
} else {
	//this is for Droip utility page
	$custom_post_data = HelperFunctions::get_custom_data_if_current_page_is_droip_custom_post();
	if ($custom_post_data) {
		$the_content = $custom_post_data['content'];
		$custom_post_id = $custom_post_data['post_id'];

		$meta_tags = Preview::getSeoMetaTags($custom_post_id);
	}
}

global $droip_custom_header, $droip_custom_footer; // this will set from TemplateRedirection.php class
$theme_dir = get_template_directory();
?>

<?php if (!$droip_custom_header && locate_template( 'header.php' )) {
	get_header();
} else { ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
  <?php if ($meta_tags) echo $meta_tags; ?>
</head>

<body>
  <?php } ?>
  <?php do_action('wp_body_open'); ?>
  <?php
	if ($the_content) {
		echo $the_content;
	} else {
		the_content();
	}
	?>
  <?php
	if (!$droip_custom_footer && locate_template( 'footer.php' )) {
		get_footer();
	} else {
		wp_footer();
	?>
</body>
<?php } ?>