<?php
/**
 * Manage Symbol
 *
 * @package droip
 */

namespace Droip\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;


/**
 * Symbol API Class
 */
class Symbol {

	/**
	 * Create/save a symbol
	 *
	 * @return void wp_send_json.
	 */
	public static function save() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_symbol_data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		if ( ! empty( $post_symbol_data ) ) {
			$wp_post = array(
				'post_type' => DROIP_SYMBOL_TYPE,
			);

			$post_id = wp_insert_post( $wp_post );
			if ( isset( $post_id ) ) {

				$symbol_data = json_decode( stripslashes( $post_symbol_data ), true );
				$symbol_data['data'][ $symbol_data['root'] ]['properties']['symbolId'] = $post_id;

				add_post_meta( $post_id, DROIP_APP_PREFIX, $symbol_data );
				add_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, DROIP_APP_PREFIX );

				$data = array(
					'id'         => $post_id,
					'symbolData' => $symbol_data,
					'type'       => isset( $symbol_data['category'] ) ? $symbol_data['category'] : 'other',
				);
				$data['html'] = self::get_symbol_html_preview($data);
				wp_send_json( $data );
			}
		};

		die();
	}

	public static function save_to_db($data) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_symbol_data = $data ? $data : null;



		if ( ! empty( $post_symbol_data ) ) {
			unset($post_symbol_data['id']);
			unset($post_symbol_data['elementId']);

			$post_symbol_data = $post_symbol_data['symbolData'];

			$wp_post = array(
				'post_type' => DROIP_SYMBOL_TYPE,
			);

			$post_id = wp_insert_post( $wp_post );

	
			if ( isset( $post_id ) ) {

				$symbol_data =  $post_symbol_data;
				$symbol_data['data'][ $symbol_data['root'] ]['properties']['symbolId'] = $post_id;
	
				add_post_meta( $post_id, DROIP_APP_PREFIX, $symbol_data );
				add_post_meta( $post_id, DROIP_META_NAME_FOR_POST_EDITOR_MODE, DROIP_APP_PREFIX );

				$data = array(
					'id'         => $post_id,
					'symbolData' => $symbol_data,
					'type'       => isset( $symbol_data['category'] ) ? $symbol_data['category'] : 'other',
				);
				$data['html'] = self::get_symbol_html_preview($data);

				return $data;
			}
		};

		return null;

}

	/**
	 * Fetch symbol list
	 * if $internal is true then it will return array
	 * otherwise it will return json for api call
	 *
	 * @param boolean $internal if function call from internally.
	 * @param boolean $html if need html string.
	 * @return array|void wp_send_json.
	 */
	public static function fetch_list( $internal = false , $html=false) {
		$posts = get_posts(
			array(
				'post_type'   => DROIP_SYMBOL_TYPE,
				'post_status' => 'draft',
				'numberposts' => -1,
			)
		);

		$symbols = array();

		if ( ! empty( $posts ) ) {
			$symbols = array_map(
				function( $post ) use ($html, $internal){
					 $single_symbol = self::get_single_symbol( $post->ID, true, $html );
					 if(!$internal){
						unset($single_symbol["symbolData"]["data"]);
						unset($single_symbol["symbolData"]["styleBlocks"]);
					 }
					 return $single_symbol;
				},
				$posts
			);
		}

		if ( $internal ) {
			return $symbols;
		}
		wp_send_json( $symbols );
	}
	/**
	 * Fetch symbol
	 *
	 * @return void wp_send_json.
	 */
	public static function fetch_symbol() {
		$symbol_id = HelperFunctions::sanitize_text( isset( $_POST['id'] ) ? $_POST['id'] : null );
		$symbolElementProp = isset( $_POST['symbolElementProp'] ) ? $_POST['symbolElementProp'] : null;
		$collectionItem = isset( $_POST['collectionItem'] ) ? $_POST['collectionItem'] : null;
		$variableCSS = isset( $_POST['variableCSS'] ) ? $_POST['variableCSS'] : true;
		$options = $collectionItem ? json_decode( stripslashes( $collectionItem ), true ) : array();
		foreach ($options as $key => $value) {
			if (is_array($value) && $key !== 'user') $options[$key] = json_decode(json_encode($value));
		}
		Symbol::get_single_symbol($symbol_id, false, true, $symbolElementProp, $options, $variableCSS);
	}


	/**
	 * get single symbol
	 * if $internal is true then it will return array
	 * otherwise it will return json for api call
	 *
	 * @param int     $symbol_id symbol id.
	 * @param boolean $internal if the function call from internally.
	 * @param boolean $html if need html preview string.
	 * @return array|void
	 */
	public static function get_single_symbol( $symbol_id = null, $internal = false, $html=false, $symbolElementProp = false, $options = array(), $variableCSS = true ) {
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$symbol_id = $symbol_id ? $symbol_id : HelperFunctions::sanitize_text( isset( $_GET['symbol_id'] ) ? $_GET['symbol_id'] : null );
		$post      = get_post( $symbol_id );
		$symbol    = null;
		if ( $post && $post->post_type == DROIP_SYMBOL_TYPE ) {
			$symbol               = array();
			$symbol_data          = get_post_meta( $post->ID, DROIP_APP_PREFIX, true );
			$symbol['id']         = $post->ID;
			$symbol['symbolData'] = $symbol_data;
			$symbol['type']       = isset( $symbol_data['category'] ) ? $symbol_data['category'] : 'other';
			$symbol['setAs']       = isset( $symbol_data['setAs'] ) ? $symbol_data['setAs'] : '';

			if(!$internal) $symbolElementProp = json_decode( stripslashes( $symbolElementProp ), true );
			if($symbolElementProp !== false && is_array($symbolElementProp)){
				foreach($symbolElementProp as $key => $value){
					foreach($symbol['symbolData']['data'] as $key2 => $value2){
						if(isset($value2['properties']['symbolElPropId']) && $value2['properties']['symbolElPropId'] == $key){
							if(isset($value['contents']))
								$symbol['symbolData']['data'][$key2]['properties']['contents'] = $value['contents'];
							if (isset($value['contentElement'])) {
									foreach ($value['contentElement'] as $newItem) {
											if (isset($newItem['id'])) {
													$symbol['symbolData']['data'][$newItem['id']] = $newItem;
											}
									}
							}
							if(isset($value['attributes']))
								$symbol['symbolData']['data'][$key2]['properties']['attributes'] = $value['attributes'];
							if(isset($value['wp_attachment_id']))
								$symbol['symbolData']['data'][$key2]['properties']['wp_attachment_id'] = $value['wp_attachment_id'];
							if(isset($value['lottie']))
								$symbol['symbolData']['data'][$key2]['properties']['lottie'] = $value['lottie'];
							if(isset($value['svgOuterHtml']))
								$symbol['symbolData']['data'][$key2]['properties']['svgOuterHtml'] = $value['svgOuterHtml'];
							if(isset($value['tag']))
								$symbol['symbolData']['data'][$key2]['properties']['tag'] = $value['tag'];
							if(isset($value['type']))
								$symbol['symbolData']['data'][$key2]['properties']['type'] = $value['type'];
							if(isset($value['isActive']))
								$symbol['symbolData']['data'][$key2]['properties']['isActive'] = $value['isActive'];
							if(isset($value['preload']))
								$symbol['symbolData']['data'][$key2]['properties']['preload'] = $value['preload'];
							if(isset($value['dynamicContent']))
								$symbol['symbolData']['data'][$key2]['properties']['dynamicContent'] = $value['dynamicContent'];
							if (
								isset($symbol['symbolData']['data'][$key2]['properties']['dynamicContent']) &&
								$symbol['symbolData']['data'][$key2]['properties']['dynamicContent']['type'] === 'manual'
							) {
								unset($symbol['symbolData']['data'][$key2]['properties']['dynamicContent']);
							}

						}
					}
				}

			}
			
			if($html === true){
				$symbol['html'] = self::get_symbol_html_preview($symbol, $options, $variableCSS);
			}
		}

		if ( $internal ) {
			return $symbol;
		}
		wp_send_json( $symbol );
	}

	private static function get_symbol_html_preview($symbol, $options = array(), $variableCSS = true) {
		$symbol_data = $symbol['symbolData'];

		$params = array( 
			'blocks' =>$symbol_data['data'],
			'style_blocks' => $symbol_data['styleBlocks'],
			'root' => $symbol_data['root'],
			'post_id' => $symbol['id'],
			'options' => $options,
			'get_style' => true,
			'get_variable' => HelperFunctions::isTruthy($variableCSS),
			'should_take_app_script' => false,
			'prefix' => 'droip-s' . $symbol['id']
		 );

		$s = HelperFunctions::get_html_using_preview_script( $params );
		return $s;
	}

	/**
	 * Update Symbol data
	 *
	 * @return void wp_send_json.
	 */
	public static function update() {
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$symbol_id = HelperFunctions::sanitize_text( isset( $_POST['symbol_id'] ) ? $_POST['symbol_id'] : null );
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post_symbol_data = isset( $_POST['data'] ) ? $_POST['data'] : null;
		if ( $symbol_id && ! empty( $post_symbol_data ) ) {
			$data        = json_decode( stripslashes( $post_symbol_data ), true );
			$symbol_data = get_post_meta( $symbol_id, DROIP_APP_PREFIX, true );

			if ( $symbol_data ) {
				if ( isset( $data['data'] ) ) {
					$symbol_data['data'] = $data['data'];
				}
				if ( isset( $data['styleBlocks'] ) ) {
					$symbol_data['styleBlocks'] = $data['styleBlocks'];
				}
				if ( isset( $data['customFonts'] ) ) {
					$symbol_data['customFonts'] = $data['customFonts'];
				}
				if ( isset( $data['name'] ) ) {
					$symbol_data['name'] = $data['name'];
				}
				if ( isset( $data['category'] ) ) {
					$symbol_data['category'] = $data['category'];
				}
				if ( isset( $data['root'] ) ) {
					$symbol_data['root'] = $data['root'];
				}
				// if ( isset( $data['conditions'] ) ) {
				// 	$symbol_data['conditions'] = $data['conditions'];
				// }

				$setAs_toggle_success = true;
				if ( isset( $data['setAs'] ) ) {
					$symbol_data['setAs'] = $data['setAs'];
					
					if($data['setAs'] != '') {
						$all_symbol = Symbol::fetch_list(true);
						foreach ($all_symbol as $key => $value) {
							if($value['id'] != $symbol_id && $value['symbolData']['setAs'] == $data['setAs']) {
								$all_symbol[$key]['symbolData']['setAs'] = '';
								$setAs_toggle_success = update_post_meta( $value['id'], DROIP_APP_PREFIX, $all_symbol[$key]['symbolData'] );
							}
						}
					}
				}

				if($setAs_toggle_success !== true){
					wp_send_json( false );
				}

				$updated = update_post_meta( $symbol_id, DROIP_APP_PREFIX, $symbol_data );

				$updated === true && $setAs_toggle_success === true ? wp_send_json( self::get_single_symbol($symbol_id, true, true) ) : wp_send_json( false );
			} else {
				wp_send_json( false );
			}
		}
		die();
	}

	/**
	 * Delete a symbol
	 *
	 * @return void
	 */
	public static function delete() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$symbol_id = HelperFunctions::sanitize_text( isset( $_POST['symbol_id'] ) ? $_POST['symbol_id'] : null );
		if ( $symbol_id ) {
			$post = wp_delete_post( $symbol_id );
			isset( $post ) ? wp_send_json( true ) : wp_send_json( false );
		}
		die();
	}

	public static function get_pre_built_html_using_url(){
		$url =  isset( $_GET['elementUrl'] ) ? $_GET['elementUrl'] : null;
		$new_url = sanitize_url( $url );

		$data = HelperFunctions::http_get( $new_url, array('sslverify' => false));

		$data = json_decode($data, true);

		$params = array( 
			'blocks' => $data['blocks'],
			'style_blocks' => $data['styles'],
			'root' => $data['root'],
			'post_id' => false,
			'options' => [],
			'get_style' => true,
			'get_variable' => false,
			'should_take_app_script' => false,
		 );

		$html = HelperFunctions::get_html_using_preview_script( $params );

		wp_send_json_success( $html );
	}
}