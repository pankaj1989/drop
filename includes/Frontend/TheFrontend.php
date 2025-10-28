<?php

/**
 * Front end post hooks and content controller
 *
 * @package droip
 */

namespace Droip\Frontend;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\Frontend\Preview\Preview;
use Droip\HelperFunctions;
use Droip\Admin\EditWithButton;

/**
 * TheFrontend class
 */
class TheFrontend
{
	/**
	 * Flag for where from call this instance
	 */
	private $call_from = 'front-end';
	private $staging_version = false;

	/**
	 * Collect droip type data header, footer, content, popups
	 */
	private $droip_type_html_data = array(
		'content' => '',
		'popups'  => '',
	);
	/**
	 * Initialize the class
	 *
	 * @param string $call_from where from call this instance.
	 * @return void
	 */
	public function __construct($call_from = 'front-end', $staging_version = false)
	{
		if ($call_from === 'iframe') {
			remove_all_filters('the_content');
		}
		$this->staging_version = $staging_version;

		add_action('wp', array($this, 'collect_all_droip_type_data'));
		add_action('wp_head', array($this, 'add_inside_head_tag'));
		add_filter('the_content', array($this, 'replace_content'), PHP_INT_MAX);
		add_action('wp_footer', array($this, 'add_before_body_tag_end'));

		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action = HelperFunctions::sanitize_text(isset($_GET['action']) ? $_GET['action'] : null);
		if ('front-end' === $call_from && DROIP_APP_PREFIX === $action) {
			$call_from = 'editor';
		}
		if ('front-end' === $call_from) {
			new EditWithButton();
		}

		new TheFrontendHooks($call_from);

		$this->call_from = $call_from;
	}
	/**
	 * Collect droip related custom sections html string
	 *
	 * @return void
	 */
	public function collect_all_droip_type_data()
	{
		$this->get_current_post_related_dependency();
	}

	/**
	 * Get Post preview related data.
	 * this function is called for enqueueing the icon assets and get only used style blocks
	 * and also get the html string of the preview
	 *
	 * @return void
	 */
	public function get_current_post_related_dependency()
	{
		$d = HelperFunctions::is_droip_type_data( null, $this->staging_version );
		if ($d) {
			$params = array(
				'blocks' => $d['blocks'],
				'style_blocks' => $d['styles'],
				'root' => 'root',
			);

			$this->droip_type_html_data['content'] = HelperFunctions::get_html_using_preview_script($params);
		}
	}


	/**
	 * Add elements style to the header
	 *
	 * @return void
	 */
	public function add_inside_head_tag()
	{
		$s = '';
		$s .= Preview::getSeoMetaTags();
		$s .= Preview::getHeadCustomCode();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $s;
	}

	/**
	 * Replace the content with our droip data
	 *
	 * @param string $content wp content.
	 *
	 * @return the_contnet
	 */
	public function replace_content($content)
	{
		// $flag = false;
		if ('iframe' === $this->call_from) {
			return '<div id="' . DROIP_CLASS_PREFIX . '-builder"></div>';
		}

		if ($this->droip_type_html_data['content']) {
			$content = $this->droip_type_html_data['content'];
		}
		// Decode HTML entities. Example: [gravityform id=&quot;1&quot;] → [gravityform id="1"]
		$content = html_entity_decode($content, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');

		// Run shortcode manually
		$content = do_shortcode($content);

		if ('migration' === $this->call_from) {
			return '<div id="' . DROIP_CLASS_PREFIX . '-builder-migration">' . $content . '</div>';
		}
		return $content;
	}

	/**
	 * Add script tag to the footer
	 *
	 * @return void
	 */
	public function add_before_body_tag_end()
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$s = "";
		if ('iframe' !== $this->call_from) {
			$s = HelperFunctions::get_page_popups_html();
		}

		if ($this->droip_type_html_data['content']) {
			$s .= Preview::getBodyCustomCode();
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $s;
	}
}