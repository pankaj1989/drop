<?php
/**
 * Preview scripts data and styles helper
 *
 * @package droip
 */

namespace Droip\Frontend\Preview;

use Droip\HelperFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class DataHelper
 */
class DataHelper {

	/**
	 * Temporary IDs store
	 *
	 * @var array
	 */
	public $temp_ids = [];

	/**
	 * Temporary Data store
	 *
	 * @var array
	 */
	public $temp_data = [];
	public $temp_styles = [];

	/**
	 * Generate or retrieve a unique new ID for a given original ID.
	 *
	 * @param int    $id     Original ID.
	 * @param string $prefix ID prefix.
	 *
	 * @return string Unique new ID.
	 */
	private function get_unique_new_id( $id, $prefix = 'droip-s' ) {
		if ( isset( $this->temp_ids[ $id ] ) ) {
			return $this->temp_ids[ $id ];
		}

		$new_id                = $prefix . '-' . uniqid();
		$this->temp_ids[ $id ] = $new_id;

		return $new_id;
	}

	/**
	 * Recursively update IDs and build a new data structure.
	 *
	 * @param array  $data      Source data array.
	 * @param string $root      Root element ID.
	 * @param string $symbol_id Optional symbol ID for class prefixing.
	 */
	public function rec_update_data_id_to_new_id( $data, $styles, $root, $symbol_id = null ) {
		if ( ! isset( $data[ $root ] ) ) {
			return;
		}

		$prefix = $symbol_id ? 'droip-s' . $symbol_id : null;
		$new_id = $this->get_unique_new_id( $root );
		$element = $data[ $root ];
		$element['id'] = $new_id;

		if(isset($element['styleIds']) && !empty($element['styleIds'])) {
			foreach ($element['styleIds'] as $style_id) {
				if ( isset( $styles[$style_id] ) ) {
					$this->temp_styles[$style_id] = $styles[$style_id];
				}
			}
		}
		// Update parent ID
		if ( $element['parentId'] !== null ) {
			$element['parentId'] = $this->get_unique_new_id( $element['parentId'] );
		}

		// Update children
		if ( ! empty( $element['children'] ) ) {
			$children = [];
			foreach ( $element['children'] as $child_id ) {
				$child_new_id = $this->get_unique_new_id( $child_id );
				$children[] = $child_new_id;
				$this->rec_update_data_id_to_new_id( $data, $styles,$child_id, $symbol_id );
			}
			$element['children'] = $children;
		}

		// Update content elements
		if ( isset( $element['properties']['contents'] ) && is_array( $element['properties']['contents'] ) ) {
			$contents = [];
			foreach ( $element['properties']['contents'] as $content ) {
				if ( is_array( $content ) && isset( $content['id'] ) ) {
					$content_id = $this->get_unique_new_id( $content['id'] );
					$contents[] = [ 'id' => $content_id ];
					$this->rec_update_data_id_to_new_id( $data, $styles, $content['id'], $symbol_id );
				} else {
					$contents[] = $content;
				}
			}
			$element['properties']['contents'] = $contents;
		}

		// Update interaction class names and element IDs
		if ( isset( $element['properties']['interactions'] ) ) {
			$this->update_interactions( $element['properties']['interactions'], $prefix, $styles );
		}

		$this->temp_data[ $element['id'] ] = $element;
	}

	/**
	 * Helper function to update interaction class names and element IDs.
	 *
	 * @param array  &$interactions Interaction properties reference.
	 * @param string $prefix        Optional prefix for class names.
	 */
	private function update_interactions( &$interactions, $prefix, $styles ) {
		// TODO: we need to set $this->styles if available in interaction panel.
		foreach ( $interactions as $key => &$value ) {
			if ( $key === 'deviceAndClassList' && isset( $value['classList'] ) && $prefix ) {
				$value['classList'] = HelperFunctions::add_prefix_to_class_name( $prefix, $value['classList'] );
			} elseif ( $key === 'elementAsTrigger' ) {
				foreach ( $value as &$event ) {
					foreach ( $event as &$preset_or_custom ) {
						foreach ( $preset_or_custom as &$action ) {
							$new_data = [];

							foreach ( $action['data'] as $ele_id => $animation ) {
								if ( strpos( $ele_id, '____info' ) !== false ) {
									continue;
								}

								$new_id              = $this->get_unique_new_id( $ele_id );
								$new_data[ $new_id ] = $animation;

								// Copy associated info and update classList if needed
								$info_key = $ele_id . '____info';
								if ( isset( $action['data'][ $info_key ] ) ) {
									$info = $action['data'][ $info_key ];
									if ( isset( $info['classList'] ) && $prefix ) {
										$info['classList'] = HelperFunctions::add_prefix_to_class_name( $prefix, $info['classList'] );
									}
									$new_data[ $new_id . '____info' ] = $info;
								}
							}

							$action['data'] = $new_data;
						}
					}
				}
			}
		}
	}

	/**
	 * Recursively collect data and styles starting from a root block.
	 *
	 * @param string $root_id        Root block ID.
	 * @param array  &$data_n_styles Output container for blocks and styles.
	 * @param array  $data           Full block data set.
	 * @param array  $styles         Full styles data set.
	 */
	public static function get_data_and_styles_from_root( $root_id, &$data_n_styles, $data = [], $styles = [] ) {
		if ( ! isset( $data[ $root_id ] ) ) {
			return;
		}

		$block = $data[ $root_id ];
		$data_n_styles['blocks'][ $root_id ] = $block;

		// Add styles for this block
		if ( ! empty( $block['styleIds'] ) ) {
			foreach ( $block['styleIds'] as $style_id ) {
				if ( isset( $styles[ $style_id ] ) ) {
					$data_n_styles['styles'][ $style_id ] = $styles[ $style_id ];
				}
			}
		}

		// Traverse contents for text-based blocks
		if ( isset( $block['name'] ) && in_array( $block['name'], [ 'paragraph', 'heading', 'text' ], true ) ) {
			if ( ! empty( $block['properties']['contents'] ) && is_array( $block['properties']['contents'] ) ) {
				foreach ( $block['properties']['contents'] as $content ) {
					if ( is_array( $content ) && isset( $content['id'] ) ) {
						self::get_data_and_styles_from_root( $content['id'], $data_n_styles, $data, $styles );
					}
				}
			}
		}

		// Traverse children
		if ( ! empty( $block['children'] ) ) {
			foreach ( $block['children'] as $child_id ) {
				self::get_data_and_styles_from_root( $child_id, $data_n_styles, $data, $styles );
			}
		}
	}
}