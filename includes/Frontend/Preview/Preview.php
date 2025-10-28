<?php
/**
 * Preview script for html markup generator
 *
 * @package droip
 */

namespace Droip\Frontend\Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Droip\Ajax\Symbol;
use Droip\Frontend\Preview\ExceptionalElements;
use Droip\Ajax\UserData;
use Droip\Ajax\WpAdmin;
use Droip\API\ContentManager\ContentManagerHelper;
use Droip\HelperFunctions;

/**
 * Preview class
 */
class Preview extends ExceptionalElements {

	/**
	 * $root for entry id or parent element id.
	 */
	protected $root = null;
	/**
	 * $data is for all element data array.
	 */
	protected $data = array();
	/**
	 * $symbol_id if preview is generate for symbol then it has symbol_id for maintain class-prefix.
	 */
	protected $symbol_id = null;
	protected $prefix = false;
	/**
	 * $style_blocks for all style blocks merged array. like=> global, migrated, symbols. etc.
	 */
	protected $style_blocks = array();
	/**
	 * $only_used_style_blocks only used style blocks.
	 */
	private $only_used_style_blocks = array();
	/**
	 * $only_used_popup_id_array only used popup post ids.
	 */
	public static $only_used_popup_id_array = array();
	/**
	 * $printed_font_family_tracker for tracking already printed font family markup.
	 */
	private static $printed_font_family_tracker = array();
	private static $printed_variable_tracker = array();
	private static $section_id_tracker_for_href = array(); // ['section_id' => 'Section-2', 'section_id_1' => 'section-2-1' ]
	/**
	 * $view_ports user all viewports data.
	 */
	private $view_ports = array();

	/**
	 * Initialize some variables for element related data. Like: custom codes, lightbox, map, slider etc.
	 * START
	 */
	/**
	 * $sliders for collect slider elements data.
	 */
	private $sliders = array();
	/**
	 * $navigation for collect navigation elements data.
	 */
	private $navigations = array();
	/**
	 * $inputs for collect inputs elements data.
	 */
	private $inputs = array();
	/**
	 * $maps for collect map elements data.
	 */
	private $maps = array();
	/**
	 * $lotties for collect lottie elements data.
	 */
	private $lotties = array();
	/**
	 * $popups for collect popup elements data.
	 */
	private $popups = array();
	/**
	 * $tabs for collect tab elements data.
	 */
	private $tabs = array();
	/**
	 * $lightboxes for collect lightbox elements data.
	 */
	private $lightboxes = array();
	/**
	 * $re_captchas for collect reCaptcha elements data.
	 */
	private $re_captchas = array();
	/**
	 * $videos for collect video elements data.
	 */
	private $videos = array();
	/**
	 * $interactions for collect interaction elements data.
	 */
	private $interactions = array();
	/**
	 * $collections for collect collection elements data.
	 */
	private $collections = array();
	/**
	 * $forms for collect form elements data.
	 */
	private $forms = array();
	
	/**
	 * $liquid_glass for liquid glass effect
	 */
	private $liquid_glass = array();

	/**
	 * $custom_codes for collect customCode elements data.
	 */
	public $custom_codes = '';

	/**
	 * $dropdown for collect dropdown elements data.
	 */
	private $dropdown = array();
	/**
	 * Initialize some variables for element related data. Like: custom codes, lightbox, map, slider etc.
	 * END
	 */
	
	 /**
	 * elements wise variable modes array.
	 */
	private $ele_variable_modes = array();


	/**
	 * interaction ['scroll-into-ele'] & ['scroll-out-ele'] tracker
	 */
	private $interaction_preset_and_text_animation_tracker = array();

	private $scroll_into_custom_interaction_tracker = '';

	private $track_animation_for_elements_with_this_class = '';

	private $track_animation_for_children_with_this_class = '';

	private $track_animation_for_sibling_with_this_class = '';

	private $track_animation_for_trigger_sibling_with_this_class = '';

	private $track_animation_for_trigger_with_this_class = '';

	/**
	 * END
	 */


	/**
	 * List of exceptional elements.
	 */
	private $exceptional_elements = array(
		'custom-code',
		'map',
		'svg',
		'svg-icon',
		'textarea',
		'select',
		'video',
		'radio-group',
		'checkbox-element',
		'radio-button',
		'image',
		'nav_menu',
		'collection',
		'loading', // collection loading element
		'items',
		'collection-wrapper',
		'comments',
		'users',
		'comment-item',
		'pagination',
		'pagination-item',
		'pagination-number',
		'terms',
		'menus',
		'symbol',
		'link-block',
		'form',
		'button',
		'file-upload-inner',
		'file-upload-threshold-text',
		'file-upload',
		'popup-body',
		'navigation',
		'navigation-items',
		'section',
	);
	
	/**
	 * List of dynamic content element.
	 */
	private $exceptional_elements_contains_dyn_content = array( 'nav_menu', 'collection' );
	/**
	 * List of inline elements.
	 */
	private $inline_elements = array( 'button', 'link-block', 'link-text' );

	/**
	 * List of anchor elements.
	 */
	private $prevent_anchor_elements = array( 'heading', 'image', 'paragraph' );
	/**
	 * List anchor attrs.
	 */
	private $anchor_attrs = array( 'href', 'target', 'rel' );


	/**
	 * Preview script initilizer
	 *
	 * @param array  $data elments data array.
	 * @param array  $style_blocks elments style_blocks array.
	 * @param string $root root element id.
	 * @param string $symbol_id if preview script is initiate for symbol element generation.
	 *
	 * @return void
	 */
	public function __construct( $data, $style_blocks, $root = null, $symbol_id = null, $prefix=false ) {
		if ( $root ) {
			$this->root = $root;
		} else {
			$this->root = 'root';
		}
		$this->data         = $data;
		$this->style_blocks = $style_blocks;
		$this->view_ports   = UserData::get_view_port_list();
		if ( $symbol_id ) {
			$this->symbol_id = $symbol_id;
		}
		$this->prefix = $prefix;
	}

	/**
	 * Get the html string
	 *
	 * @return string
	 */
	public function getHTML( $options = [] ) {
		//TODO: need to fix this code
		// if(!isset($options['user']) && get_current_user_id() > 0){
		// 	$options['user'] = Users::get_user_by_id(get_current_user_id());
		// }
		return $this->recGenHTML( $this->root, $options );
	}

	/**
	 * Get the style blocks that are used in the html
	 * this method is called after getHtml() method call and from the same instance
	 *
	 * @return string
	 */
	public function get_only_used_style_blocks() {
		return $this->only_used_style_blocks;
	}

	public function add_to_only_used_style_blocks( $style_block ) {
		$this->only_used_style_blocks[ $style_block['id'] ] = $style_block;
		if( isset($style_block['liquid-glass']) ){
			$this->liquid_glass[ $style_block['id'] ] = array(
				'id' => $style_block['id'],
				'name' => $style_block['name'],
				'liquid-glass' => $style_block['liquid-glass'],
				'type' => $style_block['type'],
				'prefix' => $this->prefix
			);
		}
	}


	private function updateTranslateCSSString( $end) {
    $transforms = [];

    if (!empty($end['x']['unit']) && $end['x']['unit'] !== 'auto') {
        $transforms[] = "translateX(" . $this->addUnit($end['x']) . ")";
    }
    if (!empty($end['y']['unit']) && $end['y']['unit'] !== 'auto') {
        $transforms[] = "translateY(" . $this->addUnit($end['y']) . ")";
    }
    if (!empty($end['z']['unit']) && $end['z']['unit'] !== 'auto') {
        $transforms[] = "translateZ(" . $this->addUnit($end['z']) . ")";
    }

    return implode(" ", $transforms); // Return only the transform part
}

	
private function updateRotateCSSString( $end) {
	$rotateProperties = [];

	if ($end['x']['unit'] !== 'auto') {
			$rotateProperties[] = "rotateX(" . $this->addUnit($end['x']) . ")";
	}
	if ($end['y']['unit'] !== 'auto') {
			$rotateProperties[] = "rotateY(" . $this->addUnit($end['y']) . ")";
	}
	if ($end['z']['unit'] !== 'auto') {
			$rotateProperties[] = "rotateZ(" . $this->addUnit($end['z']) . ")";
	}

	return implode(" ", $rotateProperties);
}

	
private function updateSkewCSSString( $end) {
	$skewProperties = [];

	if ($end['x']['unit'] !== 'auto') {
			$skewProperties[] = "skewX(" . $this->addUnit($end['x']) . ")";
	}
	if ($end['y']['unit'] !== 'auto') {
			$skewProperties[] = "skewY(" . $this->addUnit($end['y']) . ")";
	}

	return implode(" ", $skewProperties);
}

	
private function updateScaleCSSString( $end) {
	$scaleProperties = [];

	if ($end['x']['unit'] !== 'none') {
			$scaleProperties[] = "scaleX(" . $end['x']['value'] . ")";
	}
	if ($end['y']['unit'] !== 'none') {
			$scaleProperties[] = "scaleY(" . $end['y']['value'] . ")";
	}

	return implode(" ", $scaleProperties);
}

	
	
	private function addUnit($valueObj) {
			$specialValues = ['auto', 'fit-content', 'min-content', 'max-content', 'none', 'scrollHeight', 'scrollWidth'];
	
			// Check if the unit is in specialValues, if so, don't append it
			$unit = in_array($valueObj['unit'], $specialValues) ? '' : $valueObj['unit'];
	
			return $valueObj['value'] . $unit;
	}
	
	private	function updateCssStringForAnimation($property, $end) {
			$css = "";
			$transformProperties = []; // Store all transform values

	
			switch ($property) {
					case 'move':
							$transformProperties[] = $this->updateTranslateCSSString( $end);
							break;
					case 'rotate':
							$transformProperties[] = $this->updateRotateCSSString( $end);
							break;
					case 'scale':
							$transformProperties[] = $this->updateScaleCSSString( $end);
							break;
					case 'size':
						$css .= "width: " . ($end['width']['value'] === 'scrollWidth' ? 'auto': $this->addUnit($end['width'])) . ";";
						$css .= "height: " . ($end['height']['value'] === 'scrollHeight' ? 'auto': $this->addUnit($end['height'])) . ";";
						break;					
					case 'skew':
							$transformProperties[]= $this->updateSkewCSSString( $end);
							break;
					case 'fade':
							$css .= "opacity: " . $end['value'] . ";";
							break;
					case 'color':
							$css .= "color: " . $end['value'] . "; fill: " . $end['value'] . ";";
							break;
					case 'border-color':
							$css .= "border-color: " . $end['value'] . ";";
							break;
					case 'border-radius':
							$css .= "border-top-left-radius: " . $this->addUnit($end['border-top-left-radius']) . ";";
							$css .= "border-top-right-radius: " . $this->addUnit($end['border-top-right-radius']) . ";";
							$css .= "border-bottom-right-radius: " . $this->addUnit($end['border-bottom-right-radius']) . ";";
							$css .= "border-bottom-left-radius: " . $this->addUnit($end['border-bottom-left-radius']) . ";";
							break;
					case 'background-color':
							$css .= "background: " . $end['value'] . ";";
							break;
					case 'background-size-position':
							$css .= "background-size: " . $this->addUnit($end['sizeWidth']) . " " . $this->addUnit($end['sizeHeight']) . ";";
							$css .= "background-position-x: " . $this->addUnit($end['positionX']) . ";";
							$css .= "background-position-y: " . $this->addUnit($end['positionY']) . ";";
							break;
					case 'background-size':
							$css .= "background-size: " . $this->addUnit($end['sizeWidth']) . " " . $this->addUnit($end['sizeHeight']) . ";";
							break;
					case 'background-position':
							$css .= "background-position-x: " . $this->addUnit($end['positionX']) . ";";
							$css .= "background-position-y: " . $this->addUnit($end['positionY']) . ";";
							break;
					case 'filter':
							$filters = [];
							foreach ($end as $key => $value) {
									$filters[] = "$key(" . $this->addUnit($value) . ")";
							}
							$css .= "filter: " . implode(" ", $filters) . ";";
							break;
					
					default:
							break;
			}

			if (!empty($transformProperties)) {
			$css .= implode(" ", array_unique($transformProperties));
        $transformProperties = []; // Reset for the next element
    }
	
			return $css;
	}
	

	/**
	 * Get the style tag string
	 *
	 * @param  array|bool $blocks all styleblocks.
	 * @return string
	 */
	public function getStyleTag( $blocks = false ) {
    if ( ! $blocks ) {
        $blocks = $this->style_blocks;
    }

    $s = '';

    if(count($this->ele_variable_modes) > 0){
        $s .= $this->getElementWiseVariableCssCodes();
    }

    if ( $blocks ) {
        foreach ( $this->view_ports as $key => $vp ) {
            $css = '';
            foreach ( $blocks as $style_block ) {
                $css .= $this->getRawCssDeviceWise( $style_block, $vp );
            }
            if($css){
                $s .= "<style data='" . DROIP_APP_PREFIX . '-element-styles-' . $key . "'>";
                $s .= $css;
                $s .= '</style>';
            }
        }
    }
    return $s;
}

public function get_interaction_set_as_initial_css(){
	$s = '';
	if (count($this->interaction_preset_and_text_animation_tracker) > 0) {
			$animation_css = '';
			foreach ($this->interaction_preset_and_text_animation_tracker as $ele_id => $bool) {
					$animation_css .= "[data-droip='" . $ele_id . "'] { visibility: hidden; }";
			}        

			if( $animation_css ){
					$s .= "<style data='" . DROIP_APP_PREFIX . "-element-animation-visibility'>";
					$s .= $animation_css;
					$s .= '</style>';
			}
	}

	if($this->scroll_into_custom_interaction_tracker){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-scroll-into-custom-animation'>";
		$s .= $this->scroll_into_custom_interaction_tracker;
		$s .= '</style>';
	}

	if($this->track_animation_for_elements_with_this_class){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-custom-animation-for-all-elements-with-same-class'>";
		$s .= $this->track_animation_for_elements_with_this_class;    
		$s .= '</style>';
	}

	if($this->track_animation_for_children_with_this_class){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-custom-animation-for-all-children-with-same-class'>";
		$s .= $this->track_animation_for_children_with_this_class;    
		$s .= '</style>';
	}

	if($this->track_animation_for_sibling_with_this_class){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-custom-animation-for-all-sibling-with-same-class'>";
		$s .= $this->track_animation_for_sibling_with_this_class;    
		$s .= '</style>';
	}

	if($this->track_animation_for_trigger_sibling_with_this_class){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-custom-animation-for-trigger-sibling-with-same-class'>";
		$s .= $this->track_animation_for_trigger_sibling_with_this_class;    
		$s .= '</style>';
	}

	if($this->track_animation_for_trigger_with_this_class){
		$s .= "<style data='" . DROIP_APP_PREFIX . "-custom-animation-for-trigger-with-same-class'>";
		$s .= $this->track_animation_for_trigger_with_this_class;    
		$s .= '</style>';
	}

	return $s;
}

private function setInitialAnimation($ele_id, $animations,  $single_res_value) {
	$elementStyleValues = [];
	$hasClassName = false;
	$css = '';
	$mediaQueries = '';

	foreach ($animations as $ani_key => $animation) {
			if (!empty($animation['setAsInitial']) && $animation['setAsInitial']) {
				
				foreach ($this->processElementStyles($ele_id, $animation) as $key => $value) {
					if (!isset($elementStyleValues[$key])) {
							$elementStyleValues[$key] = [
									'transform' => $value['transform'] ?? [],
									'other' => $value['other'] ?? [],
							];
					} else {
							$elementStyleValues[$key]['transform'] = array_merge(
									$elementStyleValues[$key]['transform'] ?? [], 
									$value['transform'] ?? []
							);
							$elementStyleValues[$key]['other'] = array_merge(
									$elementStyleValues[$key]['other'] ?? [], 
									$value['other'] ?? []
							);
					}
				}	
		}
	}

	if (!empty($elementStyleValues)) {
			$css .= $this->setAnimationStyles($elementStyleValues, $hasClassName);
			$mediaQueries = $this->getMediaQuery($css, $single_res_value);
			$this->scroll_into_custom_interaction_tracker .= !empty($mediaQueries) ? $mediaQueries : $css;
	}
}



	

private function setAnimationStyles($elementStyleValues, $hasClassName) {
	$s = '';
	foreach ($elementStyleValues as $ele_id => $styles) {
    if (empty($styles['transform']) && empty($styles['other'])) continue;

    $css = $hasClassName ? '' : "[data-droip='$ele_id'] { ";
    $css .= !empty($styles['transform']) ? "transform: " . implode(" ", $styles['transform']) . "; " : "";
    $css .= !empty($styles['other']) ? implode(" ", $styles['other']) : "";
    $css .= $hasClassName ? '' : " }";

    $s .= $css;
	}
	return $s;
}

private function processElementStyles($ele_id, $animation) {
    $elementStyles = []; // Ensure the array is initialized properly

    if (!isset($elementStyles[$ele_id])) {
        $elementStyles[$ele_id] = [
            'transform' => [],
            'other' => []
        ];
    }
		
		if (!isset($animation['property']) || !isset($animation['end'])) {
				return []; // Skip invalid animations
		}
		
		$cssValue = $this->updateCssStringForAnimation($animation['property'], $animation['end']);
		if (!empty($cssValue)) {

				switch ($animation['property']) {
						case 'move':
						case 'rotate':
						case 'scale':
						case 'skew':
								$elementStyles[$ele_id]['transform'][] = $cssValue;
								break;
						default:
								$elementStyles[$ele_id]['other'][] = $cssValue;
								break;
				}
		}
    return $elementStyles;            
}


	/**
	 * Get the html string
	 *
	 * @param array $block single style block.
	 * @param array $vp viewport.
	 *
	 * @return string
	 */
	private function getRawCssDeviceWise( $block, $vp ) {
		$css_string = '';
		$selector   = $this->getSelectorFromBlock( $block );
		$variants   = $block['variant'];

		foreach ( $variants as $key => $value ) {
			$variant = explode( '_', $key );
			if ( $variant[0] === $vp['id'] ) {
				$css_string .= $this->createMediaQueryString( $selector, $key, $value, $vp );
			}
		}
		return $css_string;
	}

	/**
	 * Create Media Query String
	 *
	 * @param string $selector calss/tag selector like => .class-name.
	 * @param string $device_name media query key like => md.
	 * @param string $css_text css text like => color:red.
	 * @param object $vp current viewport object.
	 *
	 * @return string
	 */
	private function createMediaQueryString( $selector, $device_name, $css_text, $vp ) {
		if ( $css_text === '' ) {
			return '';
		}
		$css_string = '';
		$variant    = explode( '_', $device_name );
		$type       = $vp['type'];
		if ( count( $variant ) === 1 ) {
			// default class or sub class for devices.
			if ( $variant[0] === 'md' ) {
				$css_string .= $selector . '{' . $css_text . '}';
			} elseif ( isset( $this->view_ports[ $variant[0] ] ) ) {
				$css_string .= '@media only screen and (' . $type . '-width: ' . $this->view_ports[ $variant[0] ][ $type . 'Width' ] . 'px) {' . $selector . '{' . $css_text . '}}';
			}
		} elseif ( count( $variant ) === 2 ) {
			// active, hover, focus or pseudo class.
			$psuedo_class = $this->checkIfHasPsuedoClassValue( $variant[1] );
			// $class_prefix = DROIP_CLASS_PREFIX;
			if ( $variant[0] === 'md' ) {
				$css_string .= $selector . $psuedo_class . '{' . $css_text . '}';
				// $css_string .= $selector . '.' . $class_prefix . '-' . $variant[1] . '{' . $css_text . '}';
			} elseif ( isset( $this->view_ports[ $variant[0] ] ) ) {
				$css_string .= '@media only screen and (' . $type . '-width: ' . $this->view_ports[ $variant[0] ][ $type . 'Width' ] . 'px) {';
				$css_string .= $selector . $psuedo_class . '{' . $css_text . '}';
				// $css_string .= $selector . '.' . $class_prefix . '-' . $variant[1] . '{' . $css_text . '}';
				$css_string .= '}';
			}
		} elseif ( count( $variant ) === 3 ) {
			// active, hover, focus + pseudo class.
			$psuedo_class1 = $this->checkIfHasPsuedoClassValue( $variant[1] );
			$psuedo_class2 = $this->checkIfHasPsuedoClassValue( $variant[2] );
			// $class_prefix  = DROIP_CLASS_PREFIX;

			if ( $variant[0] === 'md' ) {
				$css_string .= $selector . $psuedo_class1 . $psuedo_class2 . '{' . $css_text . '}';
				// $css_string .= "$selector.$class_prefix" . '-' . $variant[1] . '-' . $variant[2] . "{$css_text}";
			} elseif ( isset( $this->view_ports[ $variant[0] ] ) ) {
				$css_string .= '@media only screen and (' . $type . '-width: ' . $this->view_ports[ $variant[0] ][ $type . 'Width' ] . 'px) {';
				$css_string .= $selector . $psuedo_class1 . $psuedo_class2 . '{' . $css_text . '}';
				// $css_string .= "$selector.$class_prefix" . '-' . $variant[1] . '-' . $variant[2] . "{$css_text}";
				$css_string .= '}';
			}
		}
		return $css_string;
	}

	/**
	 * Get the custom fonts links
	 *
	 * @return string
	 */
	public function getCustomFontsLinks() {
		$post_id = $this->symbol_id ? $this->symbol_id : HelperFunctions::get_post_id_if_possible_from_url();
		$post    = get_post( $post_id );

		if(!$post){
			return '';
		}

		$s = '';
		if ( 'droip_symbol' === $post->post_type ) {
			$symbol = Symbol::get_single_symbol( $post_id, true );
			if ( isset( $symbol['symbolData'], $symbol['symbolData']['customFonts'] ) ) {
				foreach ( $symbol['symbolData']['customFonts'] as $key => $f ) {
					$s .= $this->getFontsHTMLMarkup( $f );
				}
			}
		} else {
			$custom_fonts = HelperFunctions::get_global_data_using_key( DROIP_USER_CUSTOM_FONTS_META_KEY );
			if($custom_fonts){
				foreach ($custom_fonts as $key => $fonts_data) {
					$s .= $this->getFontsHTMLMarkup( $fonts_data );
				}
				return $s;
			}
		}
		return $s;
	}

	/**
	 * Get fonts html markup
	 * If HTML markup already created for a single font => thats means font link markup already created. then it will return empty string.
	 * Like, If same symbol used multiple times....
	 *
	 * @param object $fonts_data Single Fonts data.
	 * @return string <link markup
	 */
	public function getFontsHTMLMarkup( $fonts_data ) {
		$font_family = str_replace(' ', '+', $fonts_data['family']);
		if ( isset($fonts_data['fontUrl']) &&  ! in_array( $font_family, self::$printed_font_family_tracker, true ) ) {
			self::$printed_font_family_tracker[] = $font_family;

			$font_url = isset($fonts_data['localUrl']) ? $fonts_data['localUrl'] : $fonts_data['fontUrl'];

			//phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			return '<link class="' . DROIP_CLASS_PREFIX . '-custom-fonts-link" href="' . $font_url . '" rel="stylesheet">';
		}
		return '';
	}

	/**
	 * Get the script tag string
	 *
	 * @return string
	 */
	public function getScriptTag($should_take_app_script = true) {
		$s = '';
		$empty_vars = $this->getVariableString( 'Sliders', $this->sliders );
		$empty_vars .= $this->getVariableString( 'Maps', $this->maps );
		$empty_vars .= $this->getVariableString( 'Lotties', $this->lotties );
		$empty_vars .= $this->getVariableString( 'Popups', $this->popups );
		$empty_vars .= $this->getVariableString( 'Lightboxes', $this->lightboxes );
		$empty_vars .= $this->getVariableString( 'ReCaptchas', $this->re_captchas );
		$empty_vars .= $this->getVariableString( 'Videos', $this->videos );
		$empty_vars .= $this->getVariableString( 'Tabs', $this->tabs );
		$empty_vars .= $this->getVariableString( 'Interactions', $this->interactions );
		$empty_vars .= $this->getVariableString( 'Collections', $this->collections );
		$empty_vars .= $this->getVariableString( 'Forms', $this->forms );
		$empty_vars .= $this->getVariableString( 'Dropdown', $this->dropdown );
		$empty_vars .= $this->getVariableString( 'Navigations', $this->navigations );
		$empty_vars .= $this->getVariableString( 'Inputs', $this->inputs );
		$empty_vars .= $this->getVariableString( 'LiquidGlass', $this->liquid_glass);
		
		
		if($empty_vars){
			$s .= "<script data='" . DROIP_APP_PREFIX . "-elements-property-vars'>";
			$s .= $empty_vars;
			$s .= '</script>';
		}

		if ( $this->custom_codes ) {
			$s .= "<script data='" . DROIP_APP_PREFIX . "-elements-property-dev-mode'>";
			$s .= $this->custom_codes;
			$s .= '</script>';
		}

		$updatedScriptTags = false;
		if($should_take_app_script)
			$updatedScriptTags = apply_filters(DROIP_APP_PREFIX . '_add_script_tags', $s);

		if($updatedScriptTags){
			$s = $updatedScriptTags;
		}

		return $s;
	}

	private function getVariableString( $name, $value ) {
		if(count($value) === 0)return '';
		$prefix = DROIP_APP_PREFIX;
		$value  = wp_json_encode( $value );
		$s      = "var $prefix$name = window.$prefix$name === undefined? $value : {...$prefix$name, ...$value};";
		return $s;
	}

	/**
	 * Returns the JS code with `<script>` tag user put into `Page settings`
	 *
	 * @return string
	 */
	public static function getBodyCustomCode() {
		$post_id     = HelperFunctions::get_post_id_if_possible_from_url();
		$custom_code = get_post_meta( $post_id, DROIP_PAGE_CUSTOM_CODE, true );
		$code        = '<div data-' . DROIP_APP_PREFIX . "-code='custom-code'>";

		if ( isset( $custom_code['body'], $custom_code['body']['value'] ) ) {
			$code .= $custom_code['body']['value'];
		}
		$code .= '</div>';
		return $code;
	}

	/**
	 * Returns the css code with `<style>` tag user put into `Page settings`
	 *
	 * @return string
	 */
	public static function getHeadCustomCode() {
		$post_id     = HelperFunctions::get_post_id_if_possible_from_url();
		$custom_code = get_post_meta( $post_id, DROIP_PAGE_CUSTOM_CODE, true );
		$code = '';
		if ( isset( $custom_code['head'], $custom_code['head']['value'] ) ) {
			$code .= '<meta data-' . DROIP_APP_PREFIX . "-code='start' />";
			$code .= $custom_code['head']['value'];
			$code .= '<meta data-' . DROIP_APP_PREFIX . "-code='end' />";
		}
		return $code;
	}

	public function getElementWiseVariableCssCodes() {
		$s = '';
		foreach ($this->ele_variable_modes as $ele_id => $mode) {
			if ($mode === 'inherit')
				continue;
			$s .= self::getVariableCssCode( $ele_id, '[data-droip="'.$ele_id.'"]', $mode );
		}
		return $s;
	}
	
	public static function getVariableCssCode( $key = 'global', $selector = ':root', $mode=false ) {
		$k = "$key-$selector-$mode";
		if(isset(self::$printed_variable_tracker[$k]) && self::$printed_variable_tracker[$k]){
			return '';
		}
		$variables = UserData::get_droip_variable_data();

		if($mode === 'inherit'){
			$mode = false;
		}
		if(!$mode){
			if(isset($variables['defaultMode'])){
				$mode = $variables['defaultMode'];
			}else{
				$mode = 'default';
			}
		}


		$s = "<style id='" . DROIP_APP_PREFIX . "-variables-" . $key . "'>".$selector."{";
		foreach ($variables['data'] as $key2 => $group) {
			foreach ( $group['variables'] as $key3 => $variable) {
				if(isset($variable['value'][$mode])){
					$name = '--'. $variable['id'];
					if($variable['type'] === 'size'){
						$value = $variable['value'][$mode]['value'] . $variable['value'][$mode]['unit'];
					}else if($variable['type'] === 'font-family'){
						$value = "'" . $variable['value'][$mode] . "'";
					}else {
						$value = $variable['value'][$mode];
					}
					$s .= "$name:$value;";
				}
			}
		}
		$s .= "}</style>";
		self::$printed_variable_tracker[$k] = true;
		return $s;
	}

	/**
	 * Returns `<meta>` tag with provided content
	 *
	 * @param string $name The meta name.
	 * @param string $content The meta description.
	 * @return string The `<meta />` tag.
	 */
	private static function getMeta($name, $content)
	{
		// Replace &nbsp; and \u00a0 with a space
		$content = preg_replace('/(?:&nbsp;|\x{00a0})/u', ' ', $content);

		// Escape double quotes in the content to avoid breaking the HTML attribute
		$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

		return "<meta name=\"{$name}\" content=\"{$content}\" />";
	}


	/**
	 * All meta tags
	 *
	 * @return string
	 */
	public static function getSeoMetaTags($post_id = null) {
		$post_id      = $post_id ? $post_id :HelperFunctions::get_post_id_if_possible_from_url();
		$seo_settings = false;

		//get set settings from template if current page is droip template
		$template_data = HelperFunctions::get_template_data_if_current_page_is_droip_template();
		if($template_data){
			$seo_settings = get_post_meta( $template_data['template_id'], DROIP_PAGE_SEO_SETTINGS_META_KEY, true );
		}else{
			$seo_settings = get_post_meta( $post_id, DROIP_PAGE_SEO_SETTINGS_META_KEY, true );
		}

		// $title = esc_html(get_the_title());
		$meta_tags    = '';

		// if ( $seo_settings && $seo_settings['seoSettings'] && $seo_settings['seoSettings']['seoTitleTag'] && $seo_settings['seoSettings']['seoTitleTag']['value'] ) {
		// 	$seo_title = self::getSeoValue($post_id, $seo_settings['seoSettings']['seoTitleTag']['value']);
		// 	// $title = $seo_title;
		// 	$meta_tags .= self::getMeta('title', $seo_title);
		// }

		// $meta_tags .= '<title>' . $title . '</title>';

		if ( $seo_settings && $seo_settings['seoSettings'] && $seo_settings['seoSettings']['seoMetaDesc'] && $seo_settings['seoSettings']['seoMetaDesc']['value'] ) {
			$seo_meta_desc = self::getSeoValue($post_id, $seo_settings['seoSettings']['seoMetaDesc']['value']);
			$meta_tags .= self::getMeta('description', $seo_meta_desc);
		}

		if ( $seo_settings && $seo_settings['openGraph'] && $seo_settings['openGraph']['openGraphImage'] && $seo_settings['openGraph']['openGraphImage']['value'] ) {
			$seo_open_graph_image = self::getSeoValue($post_id, $seo_settings['openGraph']['openGraphImage']['value']);

			$meta_tags .= self::getMeta('og:image', $seo_open_graph_image);
			$meta_tags .= self::getMeta('twitter:image', $seo_open_graph_image);
		}

		if ( $seo_settings && $seo_settings['openGraph'] && $seo_settings['openGraph']['openGraphTitle'] ) {
			// If og:title is sameAsSeoTitle.
			if (
			$seo_settings['openGraph']['openGraphTitle']['sameAsSeoTitle'] &&
			$seo_settings && $seo_settings['seoSettings'] && $seo_settings['seoSettings']['seoTitleTag'] && $seo_settings['seoSettings']['seoTitleTag']['value']
			) {
				$seo_title = self::getSeoValue($post_id, $seo_settings['seoSettings']['seoTitleTag']['value']);

				$meta_tags .= self::getMeta('og:title', $seo_title);
				$meta_tags .= self::getMeta('twitter:title', $seo_title);
			} elseif ( $seo_settings['openGraph']['openGraphTitle']['value'] ) {

				$og_title = self::getSeoValue($post_id, $seo_settings['openGraph']['openGraphTitle']['value']);

				$meta_tags .= self::getMeta('og:title', $og_title);
				$meta_tags .= self::getMeta('twitter:title', $og_title);
			}
		}

		if ( $seo_settings && $seo_settings['openGraph'] && $seo_settings['openGraph']['openGraphDesc'] ) {
			// If og:description is sameAsSeoMeta.
			if (
			$seo_settings['openGraph']['openGraphDesc']['sameAsSeoMeta'] &&
			$seo_settings && $seo_settings['seoSettings'] && $seo_settings['seoSettings']['seoMetaDesc'] && $seo_settings['seoSettings']['seoMetaDesc']['value']
			) {
				$seo_meta_desc = self::getSeoValue($post_id, $seo_settings['seoSettings']['seoMetaDesc']['value']);

				$meta_tags .= self::getMeta('og:description', $seo_meta_desc);
				$meta_tags .= self::getMeta('twitter:description', $seo_meta_desc);
			} elseif ( $seo_settings['openGraph']['openGraphDesc']['value'] ) {

				$og_meta_des = self::getSeoValue($post_id, $seo_settings['openGraph']['openGraphDesc']['value']);

				$meta_tags .= self::getMeta('og:description', $og_meta_des);
				$meta_tags .= self::getMeta('twitter:description', $og_meta_des);
			}
		}

		$meta_tags .= self::getMeta( 'og:url', get_permalink( $post_id ) );
		return $meta_tags;
	}

	/**
	 * Get the seo title
	 * 
	 * @param int $post_id post id.
	 * @param string|array $value seo title tag value.
	 * 
	 * @return string
	 */

	public static function getSeoValue($post_id, $value)
	{
		$seo_value = '';

		if (is_array($value)) {
			$post = get_post($post_id);
			$post_parent_id = null;
			//if template preview open
			if ($post->post_type && str_contains($post->post_type, DROIP_APP_PREFIX . '_template')) {
				// get post condition
				$post_conditions = get_post_meta($post->ID, DROIP_APP_PREFIX . '_template_conditions', true); // droip_cm_parentId
				$condition = $post_conditions[0];
				if (
					isset($condition['post_type']) &&
					!empty($condition['post_type'])
				) {
					if (strpos($condition['post_type'], DROIP_CONTENT_MANAGER_PREFIX) !== false) {
						//content manager related post
						$post_parent_id = str_replace(DROIP_CONTENT_MANAGER_PREFIX . '_', '', $condition['post_type']);

						$args = [
							'post_parent' => $post_parent_id,
							'page' => 1,
							'posts_per_page' => 1,
						];

						$res = ContentManagerHelper::get_all_child_items($args);

						if ($res && $res[0]) {
							$post = (object) $res[0];
						}
					}
				}
			}

			foreach ($value as $key => $val) {
				$option_type = $val['type'];
				$option_value = $val['value'];

				if ($option_type === 'text') {
					$seo_value .= $option_value;
				} else if ($option_type === 'post') {
					// post author
					if (isset($post->$option_value) && $option_value === 'post_author') {
						$seo_value .= get_the_author_meta('display_name', $post->post_author);
					} else if ($option_value === 'post_id') {
						$seo_value .= isset($post->ID) ? $post->ID : '';
					} else {
						$seo_value .= isset($post->$option_value) ? $post->$option_value : '';
					}
				} else if ($option_type === DROIP_CONTENT_MANAGER_PREFIX . '_field') {
					$meta_key = ContentManagerHelper::get_child_post_meta_key_using_field_id($post->post_parent, $option_value);

					$post_meta_value = get_post_meta($post->ID, $meta_key, true);

					// for image field
					if ($post_meta_value && isset($post_meta_value['url'])) {
						$post_meta_value = $post_meta_value['url'];
					}

					$seo_value .= strip_tags($post_meta_value);
				} else if ($option_type === 'featured_image') {
					$seo_value .= get_the_post_thumbnail_url( $post->ID );
				} else if ($option_type === 'user') {
					$user_id = HelperFunctions::get_user_id_if_possible_from_url();

					if ($option_value === 'user_id') {
						$seo_value .= $user_id;
					} else if ($option_value === 'user_name') {
						$seo_value .= get_the_author_meta('display_name', $user_id);
					} else {
						$seo_value .= get_the_author_meta($option_value, $user_id);
					}
				} else if ($option_type === 'term') {
					$term_id = HelperFunctions::get_term_id_if_possible_from_url();
					if ($term_id) {
						$term = get_term($term_id);

						if ($option_value === 'term_id') {
							$seo_value .= $term->term_id;
						} else if ($option_value === 'term_name') {
							$seo_value .= $term->name;
						} else if ($option_value === 'term_slug') {
							$seo_value .= $term->slug;
						}
					}
				}
			}
		} else {
			$seo_value = $value;
		}

		return $seo_value;
	}

	/**
	 * Check if has pseudo class value
	 *
	 * @param string $s pseudo class name like => active, hover, focus, child(5th).
	 *
	 * @return string
	 */
	private function checkIfHasPsuedoClassValue( $s ) {
		if ( strpos( $s, '-----' ) !== false ) {
			$s_arr = explode( '-----', $s );
			$s     = $s_arr[0] . '(' . $s_arr[1] . ')';
		}
		return str_contains( $s, 'before' ) || str_contains( $s, 'after' ) || str_contains( $s, 'placeholder' ) ? '::' . $s : ':' . $s;
	}


	/**
	 * Get the selector from block
	 *
	 * @param array $block single style block.
	 *
	 * @return string
	 */
	private function getSelectorFromBlock( $block ) {
		$selector = '';
		if (isset($block['type']) && $block['type'] === 'class') {
			$block['name'] = HelperFunctions::add_prefix_to_class_name($this->prefix, $block['name']);
			if ( is_array( $block['name'] ) ) {
				$selector      = '.' . str_replace( ' ', '.', $this->makeClassStringFromArray( $block['name'] ) );
			} else {
				$selector = '.' . $this->makeClassStringFromArray( array( $block['name'] ) );
			}
		} elseif (isset($block['type']) && $block['type'] === 'tag') {
			$selector = isset( $block['tag'] ) ? $block['tag'] : ( isset( $block['name'] ) ? $block['name'] : '' );
		}
		return $selector;
	}

	/**
	 * Insert element related data if needed
	 *
	 * @param array $element single element data.
	 * @return void
	 */
	private function insertElementRelatedConfig( $element, $options = array() ) {
		$id = $element['id'];
		if ( isset( $element['properties'] ) ) {
			$properties = $element['properties'];

			if ( isset( $properties['interactions'] ) && (!isset($element['stylePanels']) || (isset($element['stylePanels'],  $element['stylePanels']['interaction']) && $element['stylePanels']['interaction']) ) ) {
				$this->interactions[ $id ] = $this->updateClassListForInteractionFromStyleBlockId($properties['interactions'], $element);
			}

			if ( isset( $properties['code'], $properties['code']['javascript'] ) ) {
				$this->custom_codes .= str_replace( 'DROIP_TARGET_ELEMENT_ID', $id, $properties['code']['javascript'] );
			}
			
			if ( isset( $properties['variableMode']) ) {
				$this->ele_variable_modes[$id] = $properties['variableMode'];
			}

			// Store slider properties.
			if ( $element['name'] === 'slider' ) {
				$this->sliders[ $id ] = $properties['slider'];
			}

			if ( $element['name'] === 'popup' ) {
				$this->popups[ $id ] = $properties['popup'];
			}

			if ( $element['name'] === 'map' ) {
				$this->maps[ $id ] = $properties['map'];
			}

			if ( $element['name'] === 'video' ) {
				$this->videos[ $id ] = $properties['attributes'];
			}
			// Store Lottie properties.
			if ( $element['name'] === 'lottie' ) {
				$this->lotties[ $id ] = $properties['lottie'];
			}
			// Store Lottie properties.
			if ( $element['name'] === 'dropdown' ) {
				$this->dropdown[ $id ] = $properties['dropdown'];
			}

			if ( $element['name'] === 'tabs' ) {
				$this->tabs[ $id ]['active_tab']    = $properties['active_tab'];
				$this->tabs[ $id ]['animationName'] = $properties['animationName'];
				$this->tabs[ $id ]['easing']        = isset( $properties['easing'] ) ? $properties['easing'] : 'ease';
				$this->tabs[ $id ]['duration']      = isset( $properties['duration'] ) ? $properties['duration'] : 100;
			}

			if ( $element['name'] === 'lightbox' ) {
				$this->lightboxes[ $id ] = $properties['lightbox'];
			}

			if ( $element['name'] === 'collection' ) {
				$this->collections[ $id ] = $properties['dynamicContent'];
			}

			// navigation properties.
			if ( $element['name'] === 'navigation' ) {
				$this->navigations[ $id ] = $properties['navigation'];
			}

			// input properties.
			if ($element['name'] === 'input') {
				$this->inputs[$id] = $properties;
			}


			if (
				$element['name'] === 'input' ||
				$element['name'] === 'textarea' ||
				$element['name'] === 'select' ||
				$element['name'] === 'checkbox-element' ||
				$element['name'] === 'radio-group' ||
				$element['name'] === 'file-upload'
			) {
				$parent_form_id = $options['form']['id'] ?? '';
				$session_data = HelperFunctions::get_session_data($parent_form_id);

				$type = $element['properties']['attributes']['type'] ?? '';
				$others_attributes = array();

				if ('file-upload' === $element['name']) {
					$type = 'file';
					$others_attributes['max-file-size'] = $element['properties']['maxFileSize'] ?? 2;
				}

				if ($session_data && isset($element['properties']['attributes']['name'])) {
					if (!isset($session_data['fields'])) {
						$session_data['fields'] = array(
							$element['properties']['attributes']['name'] => array_merge(
								array(
									'type' => $type,
									'required' => $element['properties']['attributes']['required'] ?? false
								),
								$element['properties']['attributes'],
								$others_attributes,
							)
						);
					} else {
						$session_data['fields'][$element['properties']['attributes']['name']] = array_merge(
							array(
								'type' => $type,
								'required' => $element['properties']['attributes']['required'] ?? false,
							),
							$element['properties']['attributes'],
							$others_attributes,
						);
					}

					HelperFunctions::set_session_data($parent_form_id, $session_data);
				}
			}
			if ( $element['name'] === 'form' ) {
				$this->forms[ $id ] = array_merge( $properties['form'], $properties['attributes'] );
				$this->check_popup_inside_form( $properties['form'] );
				HelperFunctions::set_session_data($id, array_merge( array( 'id' => $id ), $this->forms[ $id ] ));
			}

			if ( $element['name'] === 'button' ) {
				$this->check_popup_inside_button( $properties );
			}
			
			if ( $element['name'] === 'button' || $element['name'] === 'link-text' || $element['name'] === 'link-block' ) {
				$this->check_popup_inside_button( $properties );
			}

			if ( $element['name'] === 'recaptcha' ) {
				$common_data = WpAdmin::get_common_data( true );
				if ( ! isset( $common_data['recaptcha'], $common_data['recaptcha']['GRC_version'] ) ) {
					return;
				}
				$version   = $common_data['recaptcha']['GRC_version'];
				$recaptcha = $common_data['recaptcha'][ $version ];

				$this->re_captchas[ $id ]['data-version'] = $version;
				$this->re_captchas[ $id ]['data-sitekey'] = $recaptcha['GRC_site_key'];
			}
		}
	}

	private function getClassNameAndAnimationStyle($animations, $styleBlockId) {
    if (!empty($this->style_blocks[$styleBlockId])) {
        $className = $this->style_blocks[$styleBlockId]['name'];

        if ($className) {
            // If className is an array, join all values with a dot separator
            $classSelector = HelperFunctions::get_selector_from_sb_name($className);
						$innerCSS = '';
            foreach ($animations as $animation) {
                if (!empty($animation['setAsInitial']) && $animation['setAsInitial']) {
                    $temp = $this->processElementStyles($classSelector, $animation);
                    $innerCSS .= $this->setAnimationStyles($temp, $hasClassName = true);
                }
            }
						if($innerCSS){
							return "$classSelector { $innerCSS }";
						}
        }
    }

    return ''; // Return an empty string if no className is found
	}


private function setMediaQuery($cssValue, $deviceList) {
    $mediaQueries = '';
    $viewPorts = $this->view_ports;

    // Sort viewports by value ascending
    uasort($viewPorts, function($a, $b) {
        return $a['value'] <=> $b['value'];
    });

    // Get sorted keys
    $orderedKeys = array_keys($viewPorts);
		$ranges = [];
		$prev_viewport = null;
		$count = 0;

		foreach ($viewPorts as $key => $vp) {
			if(in_array($key, $deviceList)){
				if($count ===0 && $vp['value'] < 1400){
					$ranges[] = ['min' => null, 'max' => $vp['value']];
				}else if( $vp['value'] < 1400){
					$ranges[] = ['min' => $prev_viewport['value'] + 1, 'max' => $vp['value']];
				} else if( $count === count($viewPorts)-1 &&  $vp['value'] > 1400){
					$ranges[] = ['min' => $vp['value'], 'max' => null];
				}else if( $vp['value'] > 1400){
					$ranges[] = ['min' => $prev_viewport['value'] + 1, 'max' => $vp['value']];
				}else{
					//this is md device
					if($count === count($viewPorts) - 1){
						//thats means: no md upper device like: 1600
						$ranges[] = ['min' => $prev_viewport['value'] + 1, 'max' => null];
					}else{
						// next viewport
						$next_viewport = $viewPorts[$orderedKeys[$count + 1]];
						$ranges[] = ['min' => $prev_viewport['value'] + 1, 'max' => $next_viewport['value']-1];
					}
				}
			}
			$count++;
			$prev_viewport = $vp;
		}
		foreach ($ranges as $key => $value) {
			$min = $value['min'];
			$max = $value['max'];
			if ($min !== null && $max !== null) {
            $mediaQueries .= "@media (min-width: {$min}px) and (max-width: {$max}px) { $cssValue } ";
        } elseif ($min !== null) {
            $mediaQueries .= "@media (min-width: {$min}px) { $cssValue } ";
        } elseif ($max !== null) {
            $mediaQueries .= "@media (max-width: {$max}px) { $cssValue } ";
        }
		}

    return $mediaQueries;
}



	private function getMediaQuery($cssValue, $single_res_value) {
		return isset($single_res_value['deviceAndClassList']['devices']) && count($single_res_value['deviceAndClassList']['devices']) > 0
				? $this->setMediaQuery($cssValue, $single_res_value['deviceAndClassList']['devices'])
				: '';
	}

	private function setInteractionCssValueToTracker($classApplyTo, $element, $ele_id, $animations, $styleBlockId , $single_res_value ){
		$parent_selector = false;
		if(isset($single_res_value['deviceAndClassList'], $single_res_value['deviceAndClassList']['applyToClass'],  $single_res_value['deviceAndClassList']['styleBlockId']) && $single_res_value['deviceAndClassList']['applyToClass']){
			if(isset($this->style_blocks[ $single_res_value['deviceAndClassList']['styleBlockId']])){
				$sb = $this->style_blocks[ $single_res_value['deviceAndClassList']['styleBlockId']];
				$parent_selector = HelperFunctions::get_selector_from_sb_name($sb['name']);
			}
		}

		$cssValue = $this->getClassNameAndAnimationStyle($animations, $styleBlockId);

		if(!$cssValue){
			return;
		}

		switch ($classApplyTo) {
			
			case "childrens":
				
					$parentID = $element['id'];
				
						if(!$parent_selector){
							$parent_selector = "[data-droip='" . $parentID . "']";
						}
						$animation_css = $parent_selector ." " . $cssValue;	
						$mediaQueries = $this->getMediaQuery($animation_css, $single_res_value);
						
						$finalCss = !empty($mediaQueries) ? $mediaQueries : $animation_css;
						$this->track_animation_for_children_with_this_class .= $finalCss;
					
					break;

			case "siblings":
					
					if($cssValue){
						$child_sb = $this->style_blocks[ $styleBlockId ];
						$child_selector = HelperFunctions::get_selector_from_sb_name($child_sb['name']);
						if($parent_selector){
							$animation_css = "$parent_selector $child_selector ~ $cssValue";
						}else{
							// $animation_css = "$child_selector ~ $cssValue";
							$animation_css = "[data-droip='" . $ele_id . "'] ~" ." " . $cssValue;
						}	

						$mediaQueries = $this->getMediaQuery($animation_css, $single_res_value);
            $finalCss = !empty($mediaQueries) ? $mediaQueries : $animation_css;
            $this->track_animation_for_sibling_with_this_class .= $finalCss;
					}
					break;

			case "trigger-siblings":
					$parentID = $element['parentId']; 
						
						if(!$parent_selector){
							$parent_selector = "[data-droip='" . $parentID . "']";
						}
						$animation_css = "$parent_selector > " .$cssValue;	
						$mediaQueries = $this->getMediaQuery($animation_css, $single_res_value);
            $finalCss = !empty($mediaQueries) ? $mediaQueries : $animation_css;
            $this->track_animation_for_trigger_sibling_with_this_class .= $finalCss;
					
					break;

			case "trigger":
						$mediaQueries = $this->getMediaQuery($cssValue, $single_res_value);
            $finalCss = !empty($mediaQueries) ? $mediaQueries : $cssValue;
            $this->track_animation_for_trigger_with_this_class .= $finalCss;
					
					break;

			default:
						$mediaQueries = $this->getMediaQuery($cssValue, $single_res_value);
						$finalCss = !empty($mediaQueries) ? $mediaQueries : $cssValue;
						$this->track_animation_for_elements_with_this_class .= $finalCss;
					break;
					
		}
	}


	private function updateClassListForInteractionFromStyleBlockId($interactionData,  $element){
		$id = $element['id'];
		foreach ( $interactionData as $el_as_target_key => $el_as_target_value ) {
			if ( $el_as_target_key === 'deviceAndClassList' ) {
				if(isset($interactionData['deviceAndClassList']['styleBlockId'])){
					$this_block = isset($this->style_blocks[$interactionData['deviceAndClassList']['styleBlockId']])?$this->style_blocks[$interactionData['deviceAndClassList']['styleBlockId']]:false;
					if($this_block){
						$class_names = [];
						if(is_array($this_block['name'])){
							$class_names = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
						}else{
							$class_names[] = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
						}
						$interactionData['deviceAndClassList']['classList'] = $class_names;
						$this->add_to_only_used_style_blocks( $this_block );
					}
				}
			} elseif ( $el_as_target_key === 'elementAsTrigger' ) {
				foreach ( $el_as_target_value as $event_key => $event_value ) {
					foreach ( $event_value as $custom_or_preset_key => $custom_or_preset_value ) {
						foreach ( $custom_or_preset_value as $single_res_key => $single_res_value ) {
							$new_data = array();
							foreach ( $single_res_value['data'] as $ele_id => $animations ) {
								if ( strpos( $ele_id, '____info' ) !== false ) {
									continue;
								}

								if ( ( $custom_or_preset_key === 'preset' || $custom_or_preset_key === 'textAnimation' ) && !empty($animations) && count($animations) > 0) {
									if ($event_key === 'scroll-into-ele' || $event_key === 'scroll-out-ele') {
										$this->interaction_preset_and_text_animation_tracker[$ele_id] = true;
									}
								}
								// set initial value for custom interaction
								$this->setInitialAnimation($ele_id, $animations , $single_res_value);

								foreach ($animations as $ani_key => $animation) {
									
									if(isset($animation['property']) && $animation['property'] === 'class-change'){
										if(isset($animation['end'], $animation['end']['className'], $animation['end']['className']['id'])){
											$this_block = isset($this->style_blocks[$animation['end']['className']['id']])?$this->style_blocks[$animation['end']['className']['id']]:false;
											if($this_block){

												$class_names = [];
												if(is_array($this_block['name'])){
													$class_names = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
												}else{
													$class_names[] = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
												}

												$animation['end']['className']['name'] = $class_names;
												$this->add_to_only_used_style_blocks( $this_block );
											}
										}
									}
									$animations[$ani_key] = $animation;
								}
								$new_data[$ele_id] = $animations;
								if ( isset( $single_res_value['data'][ $ele_id . '____info' ] ) ) {

									if(isset($single_res_value['data'][ $ele_id . '____info' ]['styleBlockId'] )){
										$classApplyTo = isset($single_res_value['data'][$ele_id . '____info']['classApplyOnly']) ? $single_res_value['data'][$ele_id . '____info']['classApplyOnly'] : '*';
										$applyToClass = isset($single_res_value['data'][$ele_id . '____info']['applyToClass']) ? $single_res_value['data'][$ele_id . '____info']['applyToClass'] : false;
										$styleBlockId = $single_res_value['data'][$ele_id . '____info']['styleBlockId'];
										
										if($applyToClass ){
											$this->setInteractionCssValueToTracker($classApplyTo, $element, $ele_id, $animations, $styleBlockId, $single_res_value );
										}

										$this_block = isset($this->style_blocks[$single_res_value['data'][ $ele_id . '____info' ]['styleBlockId']])?$this->style_blocks[$single_res_value['data'][ $ele_id . '____info' ]['styleBlockId']]:false;
										if($this_block){

											$class_names = [];
											if(is_array($this_block['name'])){
												$class_names = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
											}else{
												$class_names[] = HelperFunctions::add_prefix_to_class_name($this->prefix, $this_block['name']);
											}
											
											$single_res_value['data'][ $ele_id . '____info' ]['classList'] = $class_names;

											$new_data[$ele_id . '____info'] = $single_res_value['data'][ $ele_id . '____info' ];

											$this->add_to_only_used_style_blocks( $this_block );
										}
									}else{
										//legecy code support
										$new_data[$ele_id . '____info'] = $single_res_value['data'][ $ele_id . '____info' ];
									}

								}
							}
							$interactionData[ $el_as_target_key ][ $event_key ][ $custom_or_preset_key ][ $single_res_key ]['data'] = $new_data;
						}
					}
				}
			}
		}
		return $interactionData;
	}

	/**
	 * Get element related config
	 *
	 * @return array
	 */
	public function get_element_related_config() {
		return array(
			'interactions' => $this->interactions,
			'sliders'      => $this->sliders,
			'popups'       => $this->popups,
			'maps'         => $this->maps,
			'lotties'      => $this->lotties,
			'tabs'         => $this->tabs,
			'lightboxes'   => $this->lightboxes,
			'collections'  => $this->collections,
			'forms'        => $this->forms,
			'reCaptchas'   => $this->re_captchas,
			'dropdown'     => $this->dropdown,
		);
	}

	/**
	 * Merge element related config.
	 *
	 * Set/merge all element releted cofig.
	 *
	 * @param array $configs element congiguration.
	 *
	 * @return void
	 */
	public function merge_element_related_config( $configs ) {
		$this->interactions = array_merge( $this->interactions, $configs['interactions'] );
		$this->sliders      = array_merge( $this->sliders, $configs['sliders'] );
		$this->popups       = array_merge( $this->popups, $configs['popups'] );
		$this->maps         = array_merge( $this->maps, $configs['maps'] );
		$this->lotties      = array_merge( $this->lotties, $configs['lotties'] );
		$this->tabs         = array_merge( $this->tabs, $configs['tabs'] );
		$this->lightboxes   = array_merge( $this->lightboxes, $configs['lightboxes'] );
		$this->collections  = array_merge( $this->collections, $configs['collections'] );
		$this->forms        = array_merge( $this->forms, $configs['forms'] );
		$this->re_captchas  = array_merge( $this->re_captchas, $configs['reCaptchas'] );
		$this->dropdown     = array_merge( $this->dropdown, $configs['dropdown'] );
	}

	/**
	 * Check popup inside button element. Insert the popup inside data.
	 *
	 * @param array $button_properties button properties.
	 *
	 * @return void
	 */
	private function check_popup_inside_button( $button_properties ) {
		// Search for popups.
		$type = isset( $button_properties['type'] ) ? $button_properties['type'] : '';

		if ( $type === 'popup' ) {
			$popup_id = (int) $button_properties['attributes']['popup'];
			if ( is_numeric( $popup_id ) ) {
				$this->insert_popup_into_data( $popup_id );
			}
		}
	}

	/**
	 * Check popup inside form element. then insert the popup data.
	 *
	 * @param array $form_properties form properties.
	 * @return void
	 */
	private function check_popup_inside_form( $form_properties ) {
		// Search for popups.
		$action_on_submit = isset( $form_properties['onSubmit']['type'] ) ? $form_properties['onSubmit']['type'] : '';

		$action_on_submit_success = isset( $form_properties['onSubmit']['value']['success'] ) ? $form_properties['onSubmit']['value']['success'] : '';

		$action_on_submit_fail = isset( $form_properties['onSubmit']['value']['fail'] ) ? $form_properties['onSubmit']['value']['fail'] : '';

		if ( $action_on_submit === 'popup' ) {
			if ( is_numeric( $action_on_submit_success ) ) {
				$this->insert_popup_into_data( $action_on_submit_success );
			}

			if ( is_numeric( $action_on_submit_fail ) ) {
				$this->insert_popup_into_data( $action_on_submit_fail );
			}
		}
	}

	/**
	 * Insert popup into data
	 *
	 * @param int $popup_id popup post id.
	 *
	 * @return void
	 */
	private function insert_popup_into_data( $popup_id ) {
		if ( in_array( $popup_id, self::$only_used_popup_id_array, true ) ) {
			return;
		}
		self::$only_used_popup_id_array[] = $popup_id;
	}

	/**
	 * Recursive function to generate html string
	 *
	 * @param int   $id root id.
	 * @param array $options elements option.
	 *
	 * @return string
	 */
	public function recGenHTML( $id, $options = array() ) {
		if ( ! isset( $this->data[ $id ] ) ) {
			return '';
		}
		$this_data = $this->data[ $id ];
		if ( isset( $this_data['hide'] ) && $this_data['hide'] === true ) {
			return '';
		}

		if ( isset( $this_data['properties'], $this_data['properties']['access'] ) ) {
			if(!(isset($options['check_access']) && $options['check_access'] === false) && !HelperFunctions::is_element_accessible($this_data['properties']['access'])){
				return '';
			}
		}

		if ( isset( $this_data['properties'], $this_data['properties']['visibilityConditions'] ) ) {
			if(!HelperFunctions::checkVisibilityConditions($this_data, $options)){
				return "";
			}
		}

		$this->insertElementRelatedConfig( $this_data, $options );

		if ( isset( $this_data['source'] ) && $this_data['source'] !== DROIP_APP_PREFIX ) {
			// if souce is not equal droip then the element came from external plugin
			return $this->external_element_apply_filter_hook( $this_data['source'], $this_data, $options );
		}else{
			$html = $this->external_element_apply_filter_hook( $this_data['name'], $this_data, $options );
			if($html) return $html;
		}
		return $this->generateSingleElement($this_data, $options);
	}

	public function generateSingleElement($this_data, $options){
		if ( in_array( $this_data['name'], $this->exceptional_elements, true ) ) {
			if ( ! in_array( $this_data['name'], $this->exceptional_elements_contains_dyn_content, true ) || isset( $this_data['properties']['dynamicContent'] ) ) {
				return $this->get_this_exceptional_element( $this_data, $this->getAllAttributes( $this_data ), $options );
			}
		}
		$html = '';
		if ( isset( $this_data['properties'], $this_data['properties']['noEndTag'] ) ) {
			$html = $this->noEndTagElements( $this_data, $options );
		} else {
			$html = $this->hasEndTagElements( $this_data, $options );
		}
		return $html;
	}

	public function external_element_apply_filter_hook( $source, $this_data, $options ){
		return apply_filters(
			'droip_element_generator_' . $source,
			false,
			array(
				'element'                => $this_data,
				'elements'               => $this->data,
				'style_blocks'           => $this->style_blocks,
				'attributes'             => $this->getAllAttributes( $this_data ),
				'options'                => $options,
				'generate_child_element' => array( $this, 'recGenHTML' ),
				'generate_element' => array( $this, 'generateSingleElement' ),
				'generate_child_element_with_new_id' => array( new HelperFunctions, 'rec_update_data_id_then_return_new_html' ),
				'get_data_and_styles_from_root' => array( new DataHelper, 'get_data_and_styles_from_root' ),
			)
		);
	}


	/**
	 * Elements for has end tag
	 *
	 * @param array $this_data single element data.
	 * @param array $options element options data.
	 *
	 * @return string html markup.
	 */
	private function hasEndTagElements( $this_data, $options ) {
		$tag = isset( $this_data['properties'], $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'div';
		$attributes = $this->getAllAttributes( $this_data );
		$preload_link = '';

		if($this_data['name'] == 'link-text') {
			$href = $this->get_href_value($this_data, $options);

			if ( $href ) {
				// replace href.
				$attributes = preg_replace( '/href="([^"]+")/i', "href='$href'", $attributes );

				// check if the link is active.
				$attributes = $this->add_link_active_class($href, $attributes);

				$preload_link = $this->get_preload_link($href, $this_data);
			}

			if(isset($this_data['properties']['dynamicContent'])){
				unset($this_data['properties']['dynamicContent']);
			}
		}

		$child_content_or_html = $this->get_child_content_or_childrens($this_data, $options);

		$nested_not_allowed_tags = ['a', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
		if(in_array($tag, $nested_not_allowed_tags) && HelperFunctions::check_string_has_this_tags($child_content_or_html, $tag)){
			$tag = 'div';
		}
		// Start Tag <div>.
		$html = '<' . $tag . ' ' . $attributes . '>';
			$html .= $child_content_or_html;
		$html .= '</' . $tag . '>';
		$html .= $preload_link;
		// End Tag </div>.
		return $html;
	}

	private function get_child_content_or_childrens($this_data, $options){
		$html = '';
		if ( ! isset( $this_data['children'] ) ) {
			$html .= $this->print_content( $this_data, $options );
		} else {
			$child_count = count( $this->data[ $this_data['id'] ]['children'] );
			for ( $i = 0; $i < $child_count; $i++ ) {
				$html .= $this->recGenHTML( $this->data[ $this_data['id'] ]['children'][ $i ], $options );
			}
		}
		return $html;
	}

	/**
	 * Print richtext content.
	 *
	 * @param array $this_data single element data.
	 * @param array $options single element options/dynamic data.
	 * @return string html markup.
	 */
	private function print_content( $this_data, $options ) {
		$html            = '';
		$properties      = $this_data['properties'];
		$content         = isset( $properties['contents'] ) ? $properties['contents'] : '';
		$dynamic_content = isset( $properties['dynamicContent'] ) ? $properties['dynamicContent'] : false;
		$tag = isset( $this_data['properties'], $this_data['properties']['tag'] ) ? $this_data['properties']['tag'] : 'div';

		if ($dynamic_content) {
			$html = Utils::getDynamicRichTextValue($dynamic_content, $options);
		} else {
			if ( is_array( $content ) ) {
				$content_count = count( $content );
				for ( $i = 0; $i < $content_count; $i++ ) {
					if ( is_array( $content[ $i ] ) ) {
						$html .= $this->recGenHTML( $content[ $i ]['id'], $options );
					} else {
						$html .= htmlspecialchars( $content[ $i ] );
					}
				}
			} else {
				$html .= htmlspecialchars( $content );
			}
		}

		$href = $this->get_href_value($this_data, $options);
		
		if ( $tag !== 'a' && $href ) {
			$target = isset( $properties['attributes'], $properties['attributes']['target'] ) ? "target={$properties['attributes']['target']}" : '';
			$rel = isset( $properties['attributes'], $properties['attributes']['rel'] ) ? "rel={$properties['attributes']['rel']}" : '';

			if (isset($properties['type'])) {
				$html = "<a href={$href} {$target} {$rel}>{$html}</a>";
			}
		}

		return $html;
	}

	public function get_href_value($this_data, $options = array())
	{
		$name 					 = $this_data['name'];
		$properties      = $this_data['properties'];
		$dynamic_content = isset($properties['dynamicContent']) ? $properties['dynamicContent'] : false;
		$href = isset($properties['attributes']['href']) ? $properties['attributes']['href'] : false;

		
		switch ($name) {
			case 'link-block':
			case 'link-text':
			case 'navigation-item':
			case 'paragraph':
			case 'heading':
			case 'button': {
					if ($dynamic_content) {
						
						$contentInfo = ['dynamicContent'=> $dynamic_content, 'options' => $options];
						if(isset($options['itemType'], $options[$options['itemType']], $options[$options['itemType']]->ID)){
							$contentInfo['collectionItem'] = array(
									'ID' => $options[$options['itemType']]->ID,
							);
						}
						$content = apply_filters('droip_dynamic_content', false, $contentInfo);
						if ($content) {
							return $content;
						}


						if ($dynamic_content['type'] === 'post') {
							if (isset($options['post']) && $options['post']->{$dynamic_content['value']}) {
								$href = $options['post']->{$dynamic_content['value']};
							} else {
								$href = HelperFunctions::get_post_dynamic_content($dynamic_content['value'], isset($options['post']) ? $options['post'] : null);
							}
						} else if ($dynamic_content['type'] === 'term' && isset($options['term'], $options['term']['term_id'])) {
							
							// $href = get_tag_link($options['term']['term_id']);
							

							$term = $options['term'];
							$taxonomy = isset($term['taxonomy']) ? $term['taxonomy'] : 'post_tag';

							$href = get_term_link($term['term_id'], $taxonomy);
							if (is_wp_error($href)) {
									error_log('get_term_link error: ' . $href->get_error_message());
									$href = '';
							}
							// $href = $options['post']->{$dynamic_content['value']};
						} else if ($dynamic_content['type'] === 'menu' && isset($options['menu'], $options['menu']->{$dynamic_content['value']})) {
							$href = $options['menu']->{$dynamic_content['value']};
						} else if ($dynamic_content['type'] === 'user' && isset($options['user'], $options['user'][$dynamic_content['value']])) {
							$href = $options['user'][$dynamic_content['value']];
							// $href = $options['post']->{$dynamic_content['value']};
						} 
						
						// else if ($dynamic_content['type'] === 'reference' && isset($options['post'], $options['post']->ID)) {
						// 	$content_info = array(
						// 		'collectionItem' => array(
						// 			'ID' => $options['post']->ID,
						// 		),
						// 		'dynamicContent' => $dynamic_content,
						// 	);

						// 	$content = apply_filters('droip_dynamic_content', false, $content_info);

						// 	$href = $content;
						// } 
						
						else {
							$itemType = $dynamic_content['type'];
							if(isset($options[$itemType])) {
								$data = $options[$itemType] ?? array();
								$value = isset($data[$dynamic_content['value']]) ? $data[$dynamic_content['value']] : '';
								$href = $value;
							} 
							
							// else {
							// 	$contentInfo = array(
							// 		'collectionItem' => array(
							// 			'ID' => $options['post']->ID,
							// 		),
							// 		'dynamicContent' => $dynamic_content,
							// 	);
							// 	$value = apply_filters('droip_dynamic_content', false, $contentInfo);
							// 	if($value !== false) $href = $value;
							// }
						}
					} else if ($dynamic_content && isset($options['itemType'], $dynamic_content['value']) && $options['itemType'] === 'term') {
						$term = $options['term'];
						$property = $dynamic_content['value'];
						// Check if 'term' and the dynamic property are set
						if (isset($term) && isset($term->$property)) {
							// Use the dynamic property's value as the href
							$href = $term->$property;
						} else {
							// Use the fallback link generated by get_tag_link
							$href = get_tag_link($term['term_id']);
						}
						$href = HelperFunctions::content_manager_link_filter($dynamic_content, $href);
					} else {
						if (isset($properties['type'])) {
							if ($properties['type'] === 'popup') {
								// $href = 'javascript:void(0);';
							} else if ($properties['type'] === 'page') {
								$href = isset($properties['attributes']['href']) ? $properties['attributes']['href'] : false;
								if (is_numeric($href) && intval($href) == $href) {
									//thats means its a page id
									$href = get_permalink($href);
								}
							} else if ($properties['type'] === 'section') {
								$cleaned_id = ltrim($href, '#');
								$href = '#' . self::get_unique_section_id_from_title($cleaned_id);
							} 
						}
					}
					return $href;
			}
			default: {
				return $href;
			}
		}
	}

	public function get_unique_section_id_from_title($current_id) {
    if (isset(self::$section_id_tracker_for_href[$current_id])) {
        return self::$section_id_tracker_for_href[$current_id];
    }

    $section_title = isset($this->data[$current_id]['title']) ? $this->data[$current_id]['title'] : $current_id;
    $base_slug = strtolower(preg_replace('/\s+/', '-', $section_title));
    $href = $base_slug;
    $suffix = 1;

    while (in_array($href, self::$section_id_tracker_for_href)) {
        $href = $base_slug . '-' . $suffix++;
    }

    self::$section_id_tracker_for_href[$current_id] = $href;
    return $href;
}

	/**
	 * Get preload link
	 *
	 * @param string $href value
	 * @param array $this_data single element data.
	 * 
	 * @return string preload link
	 */
	public function get_preload_link($href, $this_data)
	{
		$properties = $this_data['properties'];

		if (isset($properties['preload']) && $properties['preload'] !== 'default') {
			return '<link rel="' . $properties['preload'] . '" href="' . $href . '">';
		}

		return '';
	}

	/**
	 * Get class names
	 *
	 * @param string $href value
	 * @param array $this_data single element data.
	 * @param string $attributes
	 * 
	 * @return string updated attributes
	 */
	public function add_link_active_class($href, $attributes, $this_data = null, $options = null)
	{
		
		if (!$href) {
			return $attributes;
		}

		// Resolve $href to a post ID
		$targetId = null;
		$is_home = false;

		// 1. If it's a plain permalink like ?page_id=437
		if (strpos($href, 'page_id=') !== false) {
			parse_str(parse_url($href, PHP_URL_QUERY), $params);
			if (!empty($params['page_id'])) {
				$targetId = (int) $params['page_id'];
			}
		} else {
			// 2. If it's a relative or full pretty permalink
			if (strpos($href, 'http') !== 0) {
				$href = home_url($href);
			}

			$href = rtrim($href, '/');
			$home_url = rtrim(home_url(), '/');

			if ($href === $home_url) {
				$is_home = true;
			} else {
				$post = get_page_by_path(ltrim((string) parse_url($href, PHP_URL_PATH), '/'));

				if ($post) {
					$targetId = $post->ID;
				}
			}
			
		}

		$currentId = HelperFunctions::get_post_id_if_possible_from_url();
		$home_page_id = get_option('page_on_front');
		$current_term_id = HelperFunctions::get_term_id_if_possible_from_url();
		$current_url = strtok( home_url( add_query_arg( null, null ) ), '?' );
		$current_url = rtrim($current_url, '/');
		$term_id = null;

		if($this_data && $options && isset($this_data['properties']['dynamicContent']) && $this_data['properties']['dynamicContent']['type'] && $this_data['properties']['dynamicContent']['type'] === 'term' && $this_data['properties']['dynamicContent']['value'] === 'link'){
			$term_id = $options['term']['term_id'];
		}
		
		$is_match = (
		($targetId && $currentId && $targetId === $currentId) ||
		($is_home && $currentId && $currentId === intval($home_page_id))||
		($term_id && $current_term_id && $term_id === $current_term_id) ||
		($current_url === $href)
		);

		if ($is_match) {
			if (preg_match('/class="/i', $attributes)) {
				$attributes = preg_replace(
					'/class="([^"]*)"/i',
					'class="$1 ' . DROIP_CLASS_PREFIX . '-active-link"',
					$attributes
				);
			} else {
				$attributes .= ' class="' . DROIP_CLASS_PREFIX . '-active-link"';
			}
		}

			return $attributes;
		}

	/**
	 * Elements for no end tag
	 *
	 * @param array $this_data element single data.
	 * @param array $options element single option data.
	 *
	 * @return string html markup.
	 */
	private function noEndTagElements( $this_data, $options ) {
		$html = '<' . $this_data['properties']['tag'] . ' ' . $this->getAllAttributes( $this_data ) . '/>';
		return $html;
	}

	/**
	 * Get elements all attributes
	 *
	 * @param array $this_element single element data.
	 * @param array $filter_condition Attribute filter condition. Empty array returns all attributes.
	 *
	 * @return string attributes string.
	 */
	public function getAllAttributes( $this_element, $filter_condition = array() ) {
		if ( ! isset( $this_element['properties'] ) ) {
			return '';
		}

		$attr_str    = '';
		$class_names = trim( $this->getClassNames( $this_element ) );
		if ( $class_names ) {
			$attr_str .= 'class="' . $class_names . '"';
		}

		$others_attributes = '';
		if ( isset( $this_element['properties']['attributes'] ) ) {
			$attributes   = $this_element['properties']['attributes'];
			$element_name = $this_element['name'];

			if ( $this_element['name'] === 'image' && isset( $attributes['width'] ) ) {
				unset( $attributes['width'] );
			}

			$others_attributes = array_map(
				function ( $value, $key ) use ( $element_name ) {
					if ( in_array( $element_name, $this->prevent_anchor_elements, true ) && in_array( $key, $this->anchor_attrs, true ) ) {
						return '';
					}

					if ( ! $this->attribute_validation( $key, $value ) ) {
						return '';
					}

					if (is_array($value)) {
						$value = implode(' ', $value);
					}

					return $key . '="' . $value . '"';
				},
				array_values( $attributes ),
				array_keys( $attributes )
			);

			$others_attributes = ' ' . implode( ' ', $others_attributes );
		}

		$custom_attributes = '';
		if ( isset( $this_element['properties']['customAttributes'] ) ) {
			// phpcs:ignore WordPress.PHP.DisallowShortTernary.Found
			$attributes        = $this_element['properties']['customAttributes'] ?: array();
			$custom_attributes = array_map(
				function ( $value, $key ) {
					return $key . '="' . $value . '"';
				},
				array_values( $attributes ),
				array_keys( $attributes )
			);
			$custom_attributes = ' ' . implode( ' ', $custom_attributes );
		}

		if ( count( $filter_condition ) > 0 ) {
			$merged_attributes = array_merge( $this_element['properties']['attributes'], $attributes );
			// Merge attributes from other sources.
			$merged_attributes['class']      = $class_names;
			$merged_attributes['data-droip'] = $this_element['id'];

			if ( isset( $filter_condition['rest'] ) && false === $filter_condition['rest'] ) {
				// `rest => false` will pick only the `true` filter attributes.
				$filtered_attributes = array_filter(
					$merged_attributes,
					function ( $at ) use ( $filter_condition ) {
						return array_key_exists( $at, $filter_condition ) && true === $filter_condition[ $at ];
					},
					ARRAY_FILTER_USE_KEY
				);
			} else {
				// By default `rest` will be avaluted to `true`, so it will only remove the `false` attributes.
				$filtered_attributes = array_filter(
					$merged_attributes,
					function ( $at ) use ( $filter_condition ) {
						return ! ( array_key_exists( $at, $filter_condition ) && false === $filter_condition[ $at ] );
					},
					ARRAY_FILTER_USE_KEY
				);
			}

			$attr_str = array_reduce(
				array_keys( $filtered_attributes ),
				function ( $carry, $key ) use ( $filtered_attributes ) {
					return $carry . ' ' . $key . '=' . wp_json_encode( $filtered_attributes[ $key ] );
				},
				'',
			);

		} else {
			$attr_str .= ' data-' . DROIP_CLASS_PREFIX . '="' . $this_element['id'] . '"' . $others_attributes . $custom_attributes;
		}

		return $attr_str;
	}

	/**
	 * Attribute validation form different element attributes.
	 *
	 * @param string $attr name of the attribute.
	 * @param string $value value of the attribute.
	 *
	 * @return bool
	 */
	private function attribute_validation( $attr, $value ) {
		if ( $attr === 'multiple' && ! $value ) {
			return false;
		} elseif ( $attr === 'required' && ! $value ) {
			return false;
		} elseif ( $attr === 'src' && ! $value ) {
			return false;
		} elseif ( $attr === 'href' && ! $value ) {
			return false;
		} elseif ( $attr === 'checked' && ! $value ) {
			return false;
		}
		return true;
	}

	/**
	 * Get element class names
	 *
	 * @param array $this_element element data.
	 *
	 * @return string
	 */
	private function getClassNames( $this_element ) {
		$class_array = array();

		if ( isset( $this_element['styleIds'] ) ) {
			$style_ids_count = count( $this_element['styleIds'] );
			for ( $i = 0; $i < $style_ids_count; $i++ ) {
				$style_id = $this_element['styleIds'][ $i ];
				$s_block  = isset( $this->style_blocks[ $style_id ] ) ? $this->style_blocks[ $style_id ] : null;
				if ( ! isset( $s_block ) ) {
					continue;
				}
				$this->add_to_only_used_style_blocks( $s_block );
				$c_name                                    = $s_block['name'];
				if ( is_array( $c_name ) ) {
					$c_name      = HelperFunctions::add_prefix_to_class_name($this->prefix, $c_name);
					$class_array = array_merge( $class_array, $c_name );
				} else {
					$class_array[] = HelperFunctions::add_prefix_to_class_name($this->prefix, $c_name);
				}
			}
		}

		$ele_class_names = isset( $this_element['className'] ) ? explode( ' ', $this_element['className'] ) : array();
		$class_array     = array_merge( $ele_class_names, $class_array );

		// Check for disabled styles panels.
		if ( isset( $this_element['stylePanels'] ) && is_array( $this_element['stylePanels'] ) ) {
			foreach ( $this_element['stylePanels'] as $name => $value ) {
				if ( ! $value ) {
					array_push( $class_array, DROIP_APP_PREFIX . '-disabled-' . $name );
				}
			}
		}

		if ( in_array( $this_element['name'], $this->inline_elements, true ) ) {
			array_push( $class_array, DROIP_CLASS_PREFIX . '-inline-element' );
		}

		// Check if DROIP_CLASS_PREFIX . '-active-link' class is already added then remove it
		$class_array = array_filter(
			$class_array,
			function ($class) {
				return $class !== DROIP_CLASS_PREFIX . '-active-link';
			}
		);

		return $this->makeClassStringFromArray( $class_array );
	}

	/**
	 * Make class string from array
	 *
	 * @param array $arr class array.
	 *
	 * @return string
	 */
	private function makeClassStringFromArray( $arr ) {
		$arr = array_unique( $arr );
		$arr = array_map(
			function ( $c ) {
				return HelperFunctions::get_class_name_from_string( $c );
			},
			$arr
		);
		return join( ' ', $arr );
	}
}