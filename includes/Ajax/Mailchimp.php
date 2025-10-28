<?php
/**
 * Register routes for Media and Frontend
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Exception;


/**
 * Mailchimp API Class
 */
class Mailchimp {

	/**
	 * Mailchimp variable.
	 */
	private $mailchimp = null;

	/**
	 * Initialize the class
	 *
	 * @return object mailchimp
	 */
	private function __construct() {
		$this->mailchimp = new \MailchimpMarketing\ApiClient();

		$data = WpAdmin::get_common_data( true );

		if ( isset( $data['mailchimp_status'] ) ) {
			$mailchimp_api_key = $data['mailchimp_api_key'] ?? null;
			$mailchimp_dc      = $data['mailchimp_dc'] ?? null;

			if ( $mailchimp_api_key && $mailchimp_dc ) {
				$this->mailchimp->setConfig(
					array(
						'apiKey' => $mailchimp_api_key,
						'server' => $mailchimp_dc,
					)
				);
			}
		}

		return $this;
	}

	/**
	 * Get mailchimp instance
	 *
	 * @return object mailchimp object|client.
	 */
	public static function get_instance() {
		static $instance = null;
		if ( ! $instance ) {
			$instance = new Mailchimp();
		}
		return $instance;
	}

	/**
	 * Create a function get api key and dc from user and validate it using
	 *
	 * @param string $api_key mailchimp api_key.
	 * @param string $server mailchimp server.
	 * @return void mailchimp response.
	 */
	public static function validate_api_key( $api_key, $server ) {
		$mc = new \MailchimpMarketing\ApiClient();
		$mc->setConfig(
			array(
				'apiKey' => $api_key,
				'server' => $server,
			)
		);

		try {
			$response = $mc->ping->get();
			wp_send_json( $response );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage(), 400 );
		}
	}

	/**
	 * Get Mailchimp list
	 *
	 * @return object mailchimp response | null.
	 */
	public function get_data() {
		if ( $this->mailchimp ) {
			try {
				$response = $this->mailchimp->lists->getAllLists();
				return $response;
			} catch ( Exception $e ) {
			}
		}
		return null;
	}

	/**
	 * Add Member to mailchimp mailr list
	 *
	 * @param string $list_id mailchimp mailr list id.
	 * @param string $email user email.
	 * @param array  $merge_fields meta data.
	 * @return void mailchimp response.
	 */
	public function add_member( $list_id, $email = '', $merge_fields = array() ) {
		if ( $this->mailchimp && $list_id && $email ) {
			try {
				$this->mailchimp->lists->addListMember(
					$list_id,
					array(
						'email_address' => $email,
						'status'        => 'subscribed',
						'merge_fields'  => $merge_fields,
					),
					array(
						'skip_merge_validation' => true,
					)
				);
			} catch ( Exception $e ) {
			}
		}
	}
}
