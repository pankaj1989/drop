<?php
/**
 * Preview script helper class for handle exceptional element
 *
 * @package droip
 */

namespace Droip\Frontend\Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax\Symbol;
use Droip\Ajax\Users;
use Droip\HelperFunctions;
use Droip\Ajax\WordpressData;

/**
 * ExceptionalElements Class
 */
class ExceptionalElements {

	/**
	 * Get this exceptional element
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	public function get_this_exceptional_element( $this_data, $attributes, $options ) {
		switch ( $this_data['name'] ) {
			case 'custom-code': {
				return $this->custom_code( $this_data, $attributes );
			}
			case 'form': {
				return $this->form_element($this_data, $attributes, $options);
			}
			case 'map': {
				return $this->map_element( $this_data, $attributes );
			}
			case 'svg':
			case 'svg-icon':
			{
				return $this->svg_and_icon_element($this_data, $attributes, $options);
			}
			case 'textarea': {
				return '<textarea ' . $attributes . '></textarea>';
			}
			case 'select': {
				return $this->select_element($this_data, $attributes, $options);
			}
			case 'section': {
					return $this->section_element($this_data, $attributes, $options);
				}
			case 'video': {
				return $this->video_element( $this_data, $attributes, $options );
			}
			case 'radio-group': {
				return $this->radio_group( $this_data, $attributes );
			}
			case 'checkbox-element': {
				return $this->checkbox_element( $this_data, $attributes, $options );
			}
			case 'radio-button': {
				return '<input type="radio" ' . $attributes . ' />';
			}
			case 'image': {
				return $this->image_element( $this_data, $attributes, $options );
			}
			case 'nav_menu': {
				return $this->nav_menu_element( $this_data, $attributes );
			}
			case 'link-block': {
					return $this->link_block_element( $this_data, $attributes, $options );
			}

			case 'file-upload-inner': {
					return $this->file_input_element($this_data, $attributes, $options);
			}

			case 'file-upload-threshold-text': {
					return $this->file_upload_threshold_text($this_data, $attributes, $options);
			}

			case 'file-upload': {
					return $this->file_upload_element($this_data, $attributes, $options);
			}

			case 'collection': {
				$properties = $this_data['properties'];
				$dynamic_content = $properties['dynamicContent'];

				$children = array();

				if (empty($dynamic_content['collectionType'])) {
					$dynamic_content['collectionType'] = 'posts'; // default value
				}
				$children = $this->construct_items_collection_markup($properties, $this_data, $options);
				return $this->construct_collection_markup( $children, $this_data, $attributes );
			}
				// collection loading element
			case 'loading': {
					return $this->collection_loading_element($this_data, $attributes, $options);
				}
			case 'terms': //TODO: migrate and remove
			case 'users': //TODO: migrate and remove
			case 'collection-wrapper': //TODO: migrate and remove
			case 'items': {
					$tag                = isset($this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'div';
					$collection         = isset($options['collection']) ? $options['collection'] : array();
					$itemType         = isset($options['itemType']) ? $options['itemType'] : 'post';
					$collection_item_id = $this_data['children'][0];

					$children = array();

					if (!$options) $options = [];

					foreach ($collection as $key => $collection_item) {

						$item_index = HelperFunctions::get_current_item_index($key + 1, $options);
						$options = array_merge(
							$options,
							array('itemType' => $itemType, $itemType => $collection_item, 'item_index' => $item_index)
						);

						$children[] = HelperFunctions::rec_update_data_id_then_return_new_html($this->data, $this->style_blocks, $collection_item_id, $options, ($key === 0));
					};

					return $this->get_template(
						'items',
						array(
							'attributes' => $attributes,
							'children'   => $children,
							'tag'        => $tag,
						)
					);
				}

			case 'pagination': {
				$tag             = isset( $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'div';
				$markup          = '';
				$show_pagination = isset( $options['pagination'] ) ? $options['pagination'] : true;
				$pagination_type = isset( $options['pagination_type'] ) ? $options['pagination_type'] : 'numeric';

				if ( $show_pagination) {
					$collection_count   = 0;

					if (isset($options['itemType'], $options['collection_count'])) {
						$collection_count = $options['collection_count'];
					}

					$current_page       = isset( $options['page_no'] ) ? $options['page_no'] : 1;
					$items_per_page     = isset( $options['items_per_page'] ) ? $options['items_per_page'] : 3;
					$total_pages        = $collection_count ? (int) ceil( $collection_count / $items_per_page ):0;
						// $pagination_item_id = $this_data['children'][0];
						$pagination_item_id = isset($this_data['children'][0]) ? $this_data['children'][0] : '';

					$children = array();

					// pagination type: numeric or infinite_scroll
					if($pagination_type === 'numeric'){
						$start = 1; 
						$end = $total_pages;

						/**
						 * custom for large pagination start
						 */
						// Determine the range to display
						$range = 10;
						$half_range = floor($range / 2);

						// Calculate new $start and $end
						$start = max(1, $current_page - $half_range);
						$end = min($total_pages, $current_page + $half_range);

						// Adjust $start and $end if at the boundaries
						if ($end - $start < $range - 1) {
								if ($start == 1) {
										$end = min($start + $range - 1, $total_pages);
								} else {
										$start = max($end - $range + 1, 1);
								}
						}
						/**
						 * custom for large pagination end
						 */

						if($end > 1) {
							for ( $i = $start; $i <= $end; $i++ ) {
								$options = array(
										'page_number'  => $i,
										'current_page' => $current_page === $i,
								);
								$children[] = HelperFunctions::rec_update_data_id_then_return_new_html( $this->data, $this->style_blocks, $pagination_item_id, $options,  ($i === $start) );
							}
						}

						$markup = $this->get_template(
							'pagination',
							array(
								'attributes' => $attributes,
								'children'   => $children,
								'tag'        => $tag,
							)
						);
					}else if($pagination_type === 'infinite_scroll'){
						$markup = $this->get_template(
							'pagination',
							array(
								'attributes' => $attributes . ' data-total-pages="' . $total_pages . '" data-current-page="' . $current_page . '"' . ' data-pagination-type="' . $pagination_type . '"'. 'style="height:1px "',
								'children'   => $children,
								'tag'        => $tag,
							)
						);
					}
					
				}

				return $markup;
			}

			case 'pagination-item': {
				$tag          = isset( $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'div';
				$current_page = isset( $options['current_page'] ) ? $options['current_page'] : false;
				$page_number  = isset( $options['page_number'] ) ? $options['page_number'] : false;

				$children = array();

				foreach ( $this_data['children'] as $child ) {
						$children[] = $this->recGenHTML(
							$child,
							array(
								'page_number' => $page_number,
							)
						);
				}

				return $this->get_template(
					'pagination-item',
					array(
						'attributes'   => $attributes,
						'children'     => $children,
						'current_page' => $current_page,
						'page_number'  => $page_number,
						'tag'          => $tag,
					)
				);
			}

			case 'pagination-number': {
				$tag         = isset( $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'span';
				$page_number = isset( $options['page_number'] ) ? $options['page_number'] : 1;

				return $this->get_template(
					'pagination-number',
					array(
						'attributes'  => $attributes,
						'page_number' => $page_number,
						'tag'         => $tag,
					)
				);
			}

			case 'symbol': {
				return $this->generate_symbol_html( $this_data, $attributes, $options );
			}
			case 'popup-body': {
				return $this->popup_element( $this_data, $attributes, $options );
			}

			case 'button': {
				return $this->button_element( $this_data, $attributes, $options );
			}

			case 'navigation': {
					return $this->navigation_element($this_data, $attributes, $options);
				}

			case 'navigation-items': {
					return $this->navigation_items_element($this_data, $attributes, $options);
				}
		}
	}

	/**
	 * Generate collection loading element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function collection_loading_element($this_data, $attributes, $options)
	{
		$id = isset($this_data['id']) ? $this_data['id'] : '';
		if ($id) {
			$id = $this->get_unique_section_id_from_title($id);
		}

		$tag = isset($this_data['properties'], $this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'div';

		$children_markup = $this->construct_children_markup(isset($this_data['children']) ? $this_data['children'] : array(), $options);

		return <<<EOF
			<$tag $attributes id="$id" droip-collection="loading" data-element_hide="true">
				$children_markup
			</$tag>
		EOF;
	}

	/**
	 * Generate section element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function section_element($this_data, $attributes, $options)
	{
		$id = isset($this_data['id']) ? $this_data['id'] : '';
		if($id) {
			$id = $this->get_unique_section_id_from_title($id);
		}

		$tag = isset($this_data['properties'], $this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'a';

		$children_markup = $this->construct_children_markup(isset($this_data['children']) ? $this_data['children'] : array(), $options);

		return <<<EOF
			<$tag $attributes id="$id">
				$children_markup
			</$tag>
		EOF;
	}

	private function navigation_element($this_data, $attributes, $options)
	{
		$tag = isset($this_data['properties'], $this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'a';
		$hamburger = isset($this_data['properties']['navigation']['hamburger']) ? $this_data['properties']['navigation']['hamburger'] : false;

		if(!is_array($options)){
			$options = [];
		}

		if(!$options)$options = [];
		$options = array_merge(
			$options,
			array(
				'navigation' => array(
					'id' => $this_data['id'],
					'hamburger' => $hamburger,
				)
			)
		);

		$children_markup = $this->construct_children_markup(isset($this_data['children']) ? $this_data['children'] : array(), $options);

		return <<<EOF
			<$tag $attributes>
				$children_markup
			</$tag>
		EOF;
	}

	private function navigation_items_element($this_data, $attributes, $options)
	{

		$tag = isset($this_data['properties'], $this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'a';

		$extra_attr = isset($options['inside_navigation']) && $options['inside_navigation'] ? 'droip-navigation-hide="true"' : '';

		if(!$options)$options = [];
		$options = array_merge(
			$options,
			array(
				'inside_navigation' => true
			)
		);

		$children_markup = $this->construct_children_markup(isset($this_data['children']) ? $this_data['children'] : array(), $options);

		return <<<EOF
			<$tag $attributes $extra_attr>
				$children_markup
			</$tag>
		EOF;
	}

	private function form_element($this_data, $attributes, $options){
		$properties = $this_data['properties'];
		$post_id   = HelperFunctions::get_post_id_if_possible_from_url();
		$form_id   = $this_data['id'];
		$post_data = $form_id . '|' . $post_id;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$form_id_base64       = base64_encode( base64_encode( $post_data ) );

		$form_nonce_field     = wp_nonce_field( 'wp_rest', '_wpnonce', true, false );

		if(!$options){
			$options = [];
		}

		$options = array_merge(
			$options,
			array(
				'form' => array(
					'id' => $form_id
				)
			)
		);
		
		$form_children_markup = $this->construct_children_markup( isset( $this_data['children'] ) ? $this_data['children'] : array(), $options );

		if (isset($properties['form']['type']) && $properties['form']['type'] !== 'external') {
			$attributes = $this->getAllAttributes(
				$this_data,
				array(
					'action' => false,
					'method' => false,
					'rest' => true,
				),
			);
		}

		return <<<EOF
			<form $attributes>
				<input type="hidden" name="_droip_form" value="$form_id_base64" />
				$form_nonce_field
				$form_children_markup
			</form>
		EOF;
	}
	
	private function select_element($this_data, $attributes, $options){
		$properties = $this_data['properties'];
		$select_options = isset( $properties['options']['list'] ) ? $properties['options']['list'] : array();
				$selected       = isset( $properties['options']['selectedOption'] ) ? $properties['options']['selectedOption'] : '';
				$html           = '<select ' . $attributes . '>';
				foreach ( $select_options as $option ) {
					$html .= "<option value='";
					$html .= $option['value'] . "'";
					$html .= $selected === $option['value'] ? ' selected' : '';
					$html .= '>';
					$html .= $option['contents'];
					$html .= '</option>';
				}
				$html .= '</select>';

				return $html;
	}

	/**
	 * Generate checkbox element markup
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function checkbox_element($this_data, $attributes, $options)
	{
		$relation_type = isset($options['relation_type']) ? $options['relation_type'] : 'posts';
		if(!isset($options['form'])){
			// Start: Filter functionality
			if ($relation_type === 'posts') {
				$checked_ids = isset($options['selected_post_ids']) ? $options['selected_post_ids'] : array();
				$checked = in_array((string) $options['post']->ID, $checked_ids, true);

				if ($checked) {
					$attributes = "checked " . $attributes;
				}

				$attributes = "name='cm_post' value='" . $options['post']->ID . "' " . $attributes;
			} else if ($relation_type === 'terms') {
				$taxonomy = $options['term']['taxonomy'];
				$term_id = $options['term']['term_id'];

				$checked_ids = isset($options['selected_taxonomies']) ? $options['selected_taxonomies'] : array();
				$checked = in_array((string) $term_id, $checked_ids, true);

				if ($checked) {
					$attributes = "checked " . $attributes;
				}

				$attributes = "name='$taxonomy' value='$term_id' " . $attributes;
			}
		}
		// End: Filter functionality

		return '<input type="checkbox" ' . $attributes . ' />';
	}

	private function link_block_element($this_data, $attributes, $options){
		$href = $this->get_href_value($this_data, $options);

		$preload_link = '';

		if ($href) {
			$attributes = preg_replace('/href="([^"]+")/i', '', $attributes);

			// check if this link is active
			$attributes = $this->add_link_active_class($href, $attributes, $this_data, $options);

			$preload_link = $this->get_preload_link($href, $this_data);
		}

		return '<a ' . $attributes . ' href="' . $href . '" >' . $this->construct_children_markup(isset($this_data['children']) ? $this_data['children'] : array(), $options) . '</a>' . $preload_link;
	}
	
	private function button_element($this_data, $attributes, $options){
		$href = $this->get_href_value($this_data, $options);
		$preload_link = '';

		if ( $href ) {
			$attributes = preg_replace( '/href="([^"]+")/i', '', $attributes );

			// check if this link is active
			$attributes = $this->add_link_active_class($href, $attributes, $this_data, $options);

			$preload_link = $this->get_preload_link($href, $this_data);
		}

		$children_markup = $this->construct_children_markup( isset( $this_data['children'] ) ? $this_data['children'] : array(), $options );
		$tag = isset( $this_data['properties'], $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'button';
		if($href){
			$tag = 'a';
		}

		return <<<EOF
			<$tag $attributes href="$href">
				$children_markup
			</$tag>
			$preload_link
		EOF;
	}
	
	private function svg_and_icon_element($this_data, $attributes, $options){
		$properties = $this_data['properties'];
		$svg = isset($properties['svgOuterHtml']) ? $properties['svgOuterHtml'] : '';
		return str_replace('<svg', "<svg $attributes", $svg);
	}

	/**
	 * Construct children markup
	 *
	 * @param array $children single element all childrens.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function construct_children_markup( $children, $options ) {
		$markup = '';
		if ( isset( $children ) && is_array( $children ) ) {
			foreach ( $children as $child ) {
				$markup .= $this->recGenHTML( $child, $options );
			}
		}
		return $markup;
	}
	private function construct_items_collection_markup($properties, $this_data, $options) {
		$dynamic_content = $properties['dynamicContent'];

		$collection_type = isset($dynamic_content['collectionType']) ? $dynamic_content['collectionType'] : 'posts';
		$show_pagination = isset( $dynamic_content['pagination'] ) ? $dynamic_content['pagination'] : true;
		$pagination_type = isset( $dynamic_content['pagination_type'] ) ? $dynamic_content['pagination_type'] : 'numeric';
		
		$items_data = Utils::get_items_data_from_dynamic_contents($dynamic_content, $options);
		
		$data = $items_data['data'];
		$pagination = $items_data['pagination'];
		$itemType = $items_data['itemType'];

		$additional_options = [];

		// START: Filter functionality
		if ($collection_type === 'posts' && isset($dynamic_content['post_type']) && $dynamic_content['post_type']) {
			if (isset($_GET['post'][$dynamic_content['post_type']]) && is_array($_GET['post'][$dynamic_content['post_type']])) {
				$additional_options['selected_post_ids'] =  $_GET['post'][$dynamic_content['post_type']];
			}
		} else if ($collection_type === 'terms') {
			if (isset($_GET['taxonomy'][$dynamic_content['taxonomy']]) && is_array($_GET['taxonomy'][$dynamic_content['taxonomy']])) {
				$additional_options['selected_taxonomies'] =  $_GET['taxonomy'][$dynamic_content['taxonomy']];
			}
		}

		if(!$options){
			$options = array();
		}

		$additional_options['relation_type'] = $collection_type;
		// END: Filter functionality

		$options = array_merge(
			$options,
			$additional_options
		);

		$children = array();
		foreach ( $this_data['children'] as $child ) {

			if(count($data) > 0){
				if( isset($this->data[ $child ]) && $this->data[ $child ]['name'] === 'empty' ){
					continue;
				}
			}else if(count($data) === 0){
				if( isset($this->data[ $child ]) && ($this->data[ $child ]['name'] === 'pagination'||$this->data[ $child ]['name'] === 'items') ){
					continue;
				}
			}
			
			$children[] = $this->recGenHTML(
				$child,
				array_merge(
					$options,
					array(
						'collection'       => $data,
						'collection_count' => $pagination['total_count']??0,
						'items_per_page'   => $pagination['per_page']??0,
						'page_no'          => $pagination['current_page']??1,
						'pagination'       => $show_pagination,
						'itemType'	=>  $itemType,
						'pagination_type'	=> $pagination_type,
					)
				)
			);
		}

		return $children;
	}
	/**
	 * Construct collection markup
	 *
	 * @param array $collection single element collection data.
	 * @param array $this_data single element.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function construct_collection_markup( $children, $this_data, $attributes ) {
		$tag             = isset( $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'div';
		
		$data_n_styles = array(
			'blocks' => array(),
			'styles' => array(),
		);

		DataHelper::get_data_and_styles_from_root($this_data['id'], $data_n_styles, $this->data, $this->style_blocks);

		if (isset($this_data['properties']['dynamicContent']['collectionRandomId'])) {
			$attributes .= ' droip_collection_random_id="' . $this_data['properties']['dynamicContent']['collectionRandomId'] . '"';
		}

		if ( is_array( $children ) ) {
			return $this->get_template(
				'collection',
				array(
					'data' => $data_n_styles,
					'attributes' => $attributes,
					'children'   => $children,
					'tag'        => $tag,
				)
			);
		} else {
			return '';
		}
	}


	/**
	 * Generate custom code markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function custom_code( $this_data, $attributes ) {
		$properties = $this_data['properties'];
		if ( isset( $properties['content'] ) ) {
			if ( isset( $properties['data-type'] ) && $properties['data-type'] === 'url' ) {
				return '<div ' . $attributes . '>
                  <iframe
                    src="' . $properties['content'] . '"
                    title="' . $this_data['id'] . '"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    style="width:100%;height:100%;"
                  ></iframe>
                </div>';
			} else {
				return '<div ' . $attributes . '>' . $properties['content'] . '</div>';
			}
		}
		return '';
	}

	/**
	 * Generate Map element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function map_element( $this_data, $attributes ) {
		$properties = isset( $this_data['properties']['map'] ) ? $this_data['properties']['map'] : false;
		if ( ! $properties ) {
			return '';
		}
		return '<div ' . $attributes . '></div>';
	}

	/**
	 * Generate Video element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function video_element( $this_data, $attributes, $options ) {
		$properties       = $this_data['properties'];
		$name             = $properties['attributes']['name'];
		$src              = $properties['attributes']['src'];
		$scale						= isset($properties['scale']) ? $properties['scale']: 'cover';
		$aspectRatio						= isset($properties['aspectRatio']) ? $properties['aspectRatio']: '16 / 9';
		$type             = $properties['attributes']['type'];
		$controls         = $properties['attributes']['controls'];
		$play_inline      = isset( $properties['attributes']['playInline'] ) ? $properties['attributes']['playInline'] : true;
		$duration         = isset( $properties['attributes']['duration'] ) ? $properties['attributes']['duration'] : 0;
		$start_time       = $properties['attributes']['startTime'];
		$end_time         = isset( $properties['attributes']['endTime'] ) ? $properties['attributes']['endTime'] : $duration;
		$span_attr_string = $this->getAllAttributes(
			$this_data,
			array(
				'class'      => true,
				'data-droip' => true,
				'rest'       => false,
			),
		);
		$dynamic_content = isset( $properties['dynamicContent'] ) ? $properties['dynamicContent'] : false;

		if ( isset( $this_data['properties']['attributes']['muted'] ) && $this_data['properties']['attributes']['muted'] === false ) {
			unset( $this_data['properties']['attributes']['muted'] );
		}

		$video_attr_string = $this->getAllAttributes(
			$this_data,
			array(
				'class'                 => false,
				'data-droip'            => false,
				'src'                   => false,
				'autoPlay'              => false,
				'controls'              => false,
				'dataVideoPlayListener' => false,
				'rest'                  => true,
			),
		);

		if ( $play_inline ) {
			$video_attr_string .= ' playsinline';
		}

		$poster = '';
		if ( isset( $properties['thumbnail'], $properties['thumbnail']['status'] ) && $properties['thumbnail']['status'] ) {
			$poster = isset( $properties['thumbnail'], $properties['thumbnail']['url'] ) ? $properties['thumbnail']['url'] : null;
		}

		$video_src = '';
		if ($dynamic_content) {
			//TODO: Need to get dynamic course video or image url for tutor lms using hook.
			if (isset($options['itemType'])) {
				if ($options['itemType'] === 'post') {
					if ($options['post']->{$dynamic_content['value']}) {
						$video_obj = $options['post']->{$dynamic_content['value']};

						if (isset($video_obj['url'])) {
							$video_src = $video_obj['url'];
						}
					} else {
						$video_obj = HelperFunctions::get_post_dynamic_content( $dynamic_content['value'], isset( $options['post'] ) ? $options['post'] : null );

						if (isset($video_obj['url'])) {
							$video_src = $video_obj['url'];
						}
					}
				}
			} else {
				$video_obj = HelperFunctions::get_post_dynamic_content( $dynamic_content['value'], isset( $options['post'] ) ? $options['post'] : null );

				if (isset($video_obj['url'])) {
					$video_src = $video_obj['url'];
				}
			}
		}

		//if no dynamic video src then check original source for fallback video url
		if(!$video_src && !$src){
			return '';
		}else{
			$src = $video_src ? $video_src: $src;
		}

		$video_url_type = $this->video_type( $src );

		if ( $video_url_type === 'youtube' ) {
			$video_src = $this->get_youtube_embed_url( $src ) . '?controls=' . $controls . '&amp;start=' . $start_time . '&end=' . $end_time;
		} else {
			$video_src = $src . '#t=' . $start_time . ',' . $duration;
		}

		$html = '<span ' . $span_attr_string . '>';
		if ( $video_url_type === 'youtube' || $video_url_type === 'vimeo' || $video_url_type === 'tiktok' ) {
			$html .= '<iframe height="100%" width="100%" title="' . $name . '" src="' . $video_src . '" style="aspect-ratio: '.$aspectRatio.';" ></iframe>';
		} else {
			if ( isset( $properties['attributes']['lazy'] ) && $properties['attributes']['lazy'] ) {
				// `data-src` is used in `source` tag to lazy load the video using JS. Check JS preview script for videos.
				$html .= '<video' . $video_attr_string . ' style="object-fit: ' . $scale . '; aspect-ratio: '.$aspectRatio.';" poster="' . $poster . '">
				<source data-src="' . $video_src . '" type="' . $type . '"></source></video>';
			} else {
				$html .= '<video' . $video_attr_string . ' style="object-fit: ' . $scale . '; aspect-ratio: '.$aspectRatio.';" poster="' . $poster . '">
				<source src="' . $video_src . '" type="' . $type . '"></source></video>';
			}
		}
		$html .= '</span>';
		return $html;
	}

	/**
	 * Generate Video element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function radio_group( $this_data, $attributes ) {
		$properties = $this_data['properties'];
		$data       = $properties['data']['child'];
		$name       = $properties['attributes']['name'];

		$str = '<div ' . $attributes . '>';

		foreach ( $data as $key => $child ) {
			$attrs  = "type='radio' name='{$name}' value='{$child['value']}'";
			$attrs .= $child['checked'] ? ' checked' : '';

			$str .= '<label>
                  <input ' . $attrs . '/>
                  <span>' . $child['content'] . '</span>
                </label>';
		}
		$str .= '</div>';
		return $str;
	}

	/**
	 * Generate Nav menu element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function nav_menu_element( $this_data, $attributes ) {
		$menu_id = $this_data['properties']['dynamicContent']['value'];
		$menu    = WordpressData::get_wordpress_single_menu_data( $menu_id, true );
		if ( ! $menu ) {
			return '';
		}
		return $this->rec_gen_menu_items( $menu->submenus, 0 );
	}
	
	/**
	 * Generate popup-body element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function popup_element( $this_data, $attributes, $options ) {
		$children_markup = $this->construct_children_markup( isset( $this_data['children'] ) ? $this_data['children'] : array(), $options );
		return '<div class="droip-popup-body-wrapper"><div '.$attributes.'>' . $children_markup . '</div></div>';
	}

	/**
	 * Recursively Generate menu items element markup
	 *
	 * @param array $submenus all submenus.
	 * @param int   $depth menu nesting info.
	 * @return string HTML markup.
	 */
	private function rec_gen_menu_items( $submenus, $depth ) {
		if ( count( $submenus ) === 0 ) {
			return '';
		}
		if ( $depth === 0 ) {
			$str = '<ul class="' . DROIP_CLASS_PREFIX . '-nav-menu">';
		} else {
			$str = '<ul class="' . DROIP_CLASS_PREFIX . '-element-submenu">';
		}
		$depth++;
		foreach ( $submenus as $key => $menu ) {
			$str .= '<li class="' . DROIP_CLASS_PREFIX . '-element-nav-item"><a class="' . DROIP_CLASS_PREFIX . '-link-element" href="' . $menu->url . '" target="' . $menu->target . '">' . $menu->title . '</a>' . $this->rec_gen_menu_items( $menu->submenus, $depth ) . '</li>';
		}
		$str .= '</ul>';
		return $str;
	}

	/**
	 * This method will add css value and unit
	 *
	 * @param array $value css value and unit pair.
	 * @return string 5px | auto.
	 */
	private function add_unit( $value ) {
		if ( isset( $value['unit'] ) && $value['unit'] !== 'auto' ) {
			return $value['value'] . $value['unit'];
		}
		return 'auto';
	}

	/**
	 * Generate Image element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function image_element( $this_data, $attributes, $options ) {
		$properties      = $this_data['properties'];
		$svg_outer_html  = isset( $properties['svgOuterHtml'] ) ? $properties['svgOuterHtml'] : false;
		$dynamic_content = isset( $properties['dynamicContent'] ) ? $properties['dynamicContent'] : false;

		$user_initials = false;

		$src = '';


		if ($dynamic_content) {
			$image_data = array();

			if ($dynamic_content['type'] === 'reference' && isset($options['post'], $options['post']->ID)) {
				$content_info = array(
					'collectionItem' => array(
						'ID' => $options['post']->ID,
					),
					'dynamicContent' => $dynamic_content,
				);

				$content = apply_filters('droip_dynamic_content', false, $content_info);
				$image_data = $content;
			} else if ($dynamic_content['type'] === 'gallery') {
				$content_info = array(
					'collectionItem' => array(
						'id' => $options['gallery']['id'],
						'url' => $options['gallery']['url'],

					),
					'dynamicContent' => $dynamic_content,
				);

				$content = apply_filters('droip_dynamic_content', false, $content_info);

				$image_data = $content;
			} else if (isset($options['itemType'])) {
				if ($options['itemType'] === 'post') {
					if (isset($options['post']->{$dynamic_content['value']}) && $options['post']->{$dynamic_content['value']}) {
						$image_data = $options['post']->{$dynamic_content['value']};
					} else {
						$image_data = HelperFunctions::get_post_dynamic_content( $dynamic_content['value'], isset( $options['post'] ) ? $options['post'] : null );
					}
				} else if ($options['itemType'] === 'comment') {
					if (isset($options['comment']->{$dynamic_content['value']}) && $options['comment']->{$dynamic_content['value']}) {
						$image_data = $options['comment']->{$dynamic_content['value']};
					}
				}else if ($options['itemType'] === 'user') {
					if ($options['user'][$dynamic_content['value']]) {
						$image_data = array(
							'src' => $options['user'][$dynamic_content['value']]
						);
					}
				}
			} else {
				if ($dynamic_content['type'] === 'user') {
					$$src =	HelperFunctions::get_user_dynamic_content($dynamic_content['value'], $options['user']['ID'] ?? null, $dynamic_content['meta'] ?? '');
					$image_data = array(
						'src' => $src
					);
				} else {
					$post = isset( $options['post'] ) ? $options['post'] : get_post(HelperFunctions::get_post_id_if_possible_from_url());
					$image_data = HelperFunctions::get_post_dynamic_content( $dynamic_content['value'], $post, isset($dynamic_content['meta'])?$dynamic_content['meta']:null );
					if(is_string($image_data)) {
						$image_data = array(
							'src' => $image_data
						);
					}
				}
			}

			if (isset($image_data['src'])) {
				$src = $image_data['src'];
			}

			if (isset($image_data['wp_attachment_id'])) {
				$properties['wp_attachment_id'] = $image_data['wp_attachment_id'];
			}
		}

		if ( $dynamic_content && $src) {
			// replace src and alt.
			$attributes = preg_replace( array( '/src="([^"]+")/i', '/alt="([^"]+")/i' ), array( '', 'alt=""' ), $attributes );
		} else {
			$src = isset( $properties['attributes']['src'] ) ? $properties['attributes']['src'] : '';
		}

		$srcset = null;
		if ( !$dynamic_content && isset( $properties['wp_attachment_id'] ) && $properties['wp_attachment_id'] ) {
			$srcset      = wp_get_attachment_image_srcset( $properties['wp_attachment_id'] );
			$attributes .= $srcset ? ' srcset="' . $srcset . '"' : '';

			$sizes       = '';
			$a_meta_data = wp_get_attachment_metadata( $properties['wp_attachment_id'] );
			if ( $a_meta_data && isset($a_meta_data['width']) ) {
				$width       = $a_meta_data['width'];
				$sizes       = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
				$attributes .= $sizes ? ' sizes="' . $sizes . '"' : '';
			}
		}

		if ( ( isset( $properties['load'] ) && $properties['load'] !== 'auto' ) || ! isset( $properties['load'] ) ) {
			$attributes .= isset( $properties['load'] ) ? ' loading="' . $properties['load'] . '"' : ' loading="lazy"';
		}

		if ( isset( $properties['width'] ) ) {
			$attributes .= ' width="' . $this->add_unit( $properties['width'] ) . '"';

		}
		if ( isset( $properties['height'] ) ) {
			$attributes .= ' height="' . $this->add_unit( $properties['height'] ) . '"';
		}

		if(!$src)return '';

		$str = '';

		if ($svg_outer_html) {
			$str .= '<img ' . $attributes . ' src="' . $src . '"/>
               <span>' . $svg_outer_html . '</span>';
		} else {
			$str .= '<img ' . $attributes . ' src="' . $src . '"/>';
		}

		return $str;
	}

	/**
	 * Get video type from url
	 *
	 * @param string $url single element block.
	 * @return string|bool video url type.
	 */
	private function video_type( $url ) {
		if ( strpos( $url, 'youtube' ) > 0 ) {
			return 'youtube';
		} elseif ( strpos( $url, 'vimeo' ) > 0 ) {
			return 'vimeo';
		} elseif ( strpos( $url, 'tiktok' ) > 0 ) {
			return 'tiktok';
		} else {
			return false;
		}
	}

	/**
	 * Get video type from url
	 *
	 * @param string $url single element block.
	 * @return string youtube url type.
	 */
	private function get_youtube_embed_url( $url ) {
		preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match );
		return 'https://www.youtube.com/embed/' . $match[1];
	}

	/**
	 * Generate symbol markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @return string HTML markup.
	 */
	private function generate_symbol_html( $this_data, $attributes, $options = array() ) {
		$properties = $this_data['properties'];
		$symbol_id  = $properties['symbolId'];
		$symbolElementProp 		= isset($properties['symbolElProps']) ? $properties['symbolElProps'] : false;
		$symbol     = Symbol::get_single_symbol( $symbol_id, true, false, $symbolElementProp );

		if ( ! $symbol ) {
			return '';
		}

		$symbol_data = $symbol['symbolData'];
		if (!$symbol_data || !isset($symbol_data['data'])) {
			return '';
		}
		$s = HelperFunctions::rec_update_data_id_then_return_new_html($symbol_data['data'], $symbol_data['styleBlocks'], $symbol_data['root'], $options);
		$fonts_links = '';
		if ( isset( $symbol_data['customFonts'] ) ) {
			foreach ( $symbol_data['customFonts'] as $key => $f ) {
				if ( isset( $f['fontUrl'] ) ) {
					$fonts_links .= $this->getFontsHTMLMarkup( $f );
				}
			}
		}
		return '<div ' . $attributes . '>' . $fonts_links . $s . '</div>';
	}

	/**
	 * Get html template.
	 *
	 * @param string $template template file name.
	 * @param array  $vars if extra variable needs inside template file.
	 * @return string $message
	 */
	private function get_template( $template, $vars ) {
		ob_start();
		include __DIR__ . "/templates/$template.view.php";
		$message = ob_get_contents();
		ob_end_clean();
		return $message;
	}

	/**
	 * Generate File upload element markup
	 *
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function file_upload_element($this_data, $attributes, $options)
	{
		$tag  = isset($this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'div';
		$data_attr = isset($this_data['properties']['attributes']) ? $this_data['properties']['attributes'] : array();
		$options = array_merge(
			$options,
			array(
				'file_upload' => array(
					'name' => isset($data_attr['name']) ? $data_attr['name'] : '',
					'accept' => isset($data_attr['accept']) ? $data_attr['accept'] : '',
					'required' => isset($data_attr['required']) ? $data_attr['required'] : '',
					'maxFileSize' => isset($this_data['properties']['maxFileSize']) ? $this_data['properties']['maxFileSize'] : 2,
				)
			)
		);

		$attributes = $this->getAllAttributes(
			$this_data,
			array(
				'name' => false,
				'accept' => false,
				'required' => false,
				'rest' => true,
			),
		);

		$children_markup = $this->construct_children_markup( isset( $this_data['children'] ) ? $this_data['children'] : array(), $options );
		return <<<EOF
						<$tag $attributes>
							$children_markup
						</$tag>
				EOF;
	}

	/**
	 * Generate File input threshold text element markup
	 * 
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */

	private function file_upload_threshold_text($this_data, $attributes, $options)
	{
		$tag  = isset($this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'span';
		$max_file_size = isset($options['file_upload']['maxFileSize']) ? $options['file_upload']['maxFileSize'] : 2;

		return "<$tag $attributes>Max file size " . $max_file_size . "MB.</$tag>";
	}

	/**
	 * Generate File input element markup
	 * 
	 * @param array $this_data single element block.
	 * @param array $attributes single element all attributes.
	 * @param array $options single element all options.
	 * @return string HTML markup.
	 */
	private function file_input_element($this_data, $attributes, $options)
	{
		$tag  = isset($this_data['properties']['tag']) ? $this_data['properties']['tag'] : 'div';
		$children = $this_data['children'] ?? array();
		$markup = '';

		$name = isset($options['file_upload']['name']) ? $options['file_upload']['name'] : '';
		$accept = isset($options['file_upload']['accept']) ? $options['file_upload']['accept'] : '';
		$required = isset($options['file_upload']['required']) ? $options['file_upload']['required'] : '';
		$max_file_size = isset($options['file_upload']['maxFileSize']) ? $options['file_upload']['maxFileSize'] : 2;

		foreach ($children as $child) {
			$markup .= $this->recGenHTML($child, $options);
		}

		return <<<EOF
						<$tag $attributes>
							$markup
							<input
								type="file"
								readonly
								style="display: none;"
								name="$name"
								accept="$accept"
								required="$required"
								droip-max_file_size=$max_file_size
								/>
							</$tag>
					EOF;
	}

}