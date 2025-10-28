<?php

/**
 * Plugin Loaded events handler
 *
 * @package droip
 */

namespace Droip\Manager;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\Admin;
use Droip\Ajax\Page;
use Droip\Frontend;
use Droip\HelperFunctions;

/**
 * Do some task during plugin activation
 */
class PluginLoadedEvents
{


	/**
	 * Initilize the class
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action('plugins_loaded', array($this, 'init_plugin'));
		add_action('plugins_loaded', array($this, 'do_migration_related_task'));
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init_plugin()
	{
		if (is_admin()) {
			new Admin();
		} else {
			new Frontend();
		}
	}

	// This function updates the names of specific blocks to standardize them
	public function update_block($blocks)
	{
		// Loop through each block using its ID and content
		foreach ($blocks as $id => $block) {
			// Check if the block has a 'name' key
			if (isset($block['name'])) {
				// If block name is one of the wrapper types, change it to 'items'
				if (
					$block['name'] === 'collection-wrapper' ||
					$block['name'] === 'terms' ||
					$block['name'] === 'users' ||
					$block['name'] === 'comments' ||
					$block['name'] === 'menus'
				) {
					$blocks[$id]['name'] = 'items';
				}

				// If block name is one of the item types, change it to 'item'
				if (
					$block['name'] === 'collection-item' ||
					$block['name'] === 'term-item' ||
					$block['name'] === 'user-item' ||
					$block['name'] === 'comment-item' ||
					$block['name'] === 'menu-item'
				) {
					$blocks[$id]['name'] = 'item';
				}
			}
		}

		// Return the updated blocks
		return $blocks;
	}

	// This function goes through all stored page meta data and updates block names
	public function alter_collection_names()
	{
		// Get all meta data where key is matched (droip meta key)
		$data = Page::get_all_data_by_droip_meta_key();

		foreach ($data as $key => $value) {
			$post_id = $value['post_id'];       // ID of the post
			$meta_key = $value['meta_key'];     // Meta key name
			$meta_value = $value['meta_value']; // Serialized meta value

			$meta_value = unserialize($meta_value); // Convert serialized data to array

			// If the meta value has a 'blocks' key, handle it as a full page data
			if (isset($meta_value['blocks'])) {
				$blocks = $meta_value['blocks']; // Get the blocks array
				$updated_blocks = $this->update_block($blocks); // Update block names

				$meta_value['blocks'] = $updated_blocks; // Set updated blocks back
				update_post_meta($post_id, $meta_key, $meta_value); // Save back to DB
			} else {
				// If no 'blocks' key, treat entire value as blocks array
				$blocks = $meta_value;
				$updated_blocks = $this->update_block($blocks); // Update block names

				$meta_value = $updated_blocks;
				update_post_meta($post_id, $meta_key, $meta_value); // Save updated value
			}
		}
	}


	/**
	 * Check version whether db needs update or not
	 *
	 * @return void
	 */
	public function do_migration_related_task()
	{
		$version_in_db = HelperFunctions::get_droip_version_from_db();

		$db_altered_in3 = '2.1.0';
		if (empty($version_in_db) || version_compare($version_in_db, $db_altered_in3, '<')) {
			$this->alter_collection_names();
		}

		// TODO: Change version name before release
		$db_altered_in4 = '2.2.4';
    if (empty($version_in_db) || version_compare($version_in_db, $db_altered_in4, '<')) {
			$page = HelperFunctions::find_utility_page_for_this_context('404');
			if($page) {
				$post_id = $page['id'];
				update_post_meta($post_id, DROIP_META_NAME_FOR_PAGE_HF_SYMBOL_DISABLE_STATUS, array('header' => true, 'footer' => true));
			}
			HelperFunctions::handle_legacy_slider_class();
		}

		// fix: previous slider default class problem with new slider class issue.
		$db_altered_in5 = '2.2.6';
		if (empty($version_in_db) || version_compare($version_in_db, $db_altered_in5, '<')) {
			HelperFunctions::handle_legacy_slider_default_class();
		}
		
		$db_altered_in6 = '2.3.0';
		if (empty($version_in_db) || version_compare($version_in_db, $db_altered_in6, '<')) {
			PluginActiveEvents::create_custom_tables();
		}

		$db_altered_in6 = '2.4.0';
		if (empty($version_in_db) || version_compare($version_in_db, $db_altered_in6, '<')) {
			PluginActiveEvents::add_post_field_in_collaboaration_connected_table();
		}

		HelperFunctions::set_droip_version_in_db();
	}
}