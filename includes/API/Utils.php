<?php
/**
 * Media Utils
 *
 * @package droip
 */

namespace Droip\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Media Utils class
 */
class Utils {


	/**
	 * Convert to bytes
	 *
	 * @param   int    $value      The value to converted to bytes.
	 * @param   string $type       The type from which the value will be converted.
	 *
	 * @return  int     The converted bytes equivalent value.
	 */
	public static function to_bytes( int $value, $type = 'MB' ) {
		$multipliers = array(
			'gb' => 1073741824, // 1024 * 1024 * 1024
			'mb' => 1048576,    // 1024 * 1024
			'kb' => 1024,       // 1024
		);

		$type = strtolower( $type );

		return $value * $multipliers[ $type ];
	}

	/**
	 * Recursively search a file
	 *
	 * @param   string $directory_path   The path of file directory.
	 * @param   array  $icon_libraries       The icon_libraries to search.
	 * @return  string     The generated directory.
	 */
	public static function search_file( string $directory_path, array $icon_libraries ): array {
		$it = new \RecursiveDirectoryIterator( $directory_path );

		foreach ( $icon_libraries as $icon_name => $icon_data ) {
			$required_files = $icon_data['requiredFiles'];
			$css_file       = $icon_data['cssFile'];
			$css_file_path  = '';
			$files_found    = array();

			foreach ( new \RecursiveIteratorIterator( $it ) as $file ) {
				$arr      = explode( '/', $file );
				$filename = array_pop( $arr );

				if ( in_array( strtolower( $filename ), $required_files, true ) ) {
					$files_found[] = strtolower( $filename );

					if ( strtolower( $filename ) === $css_file ) {
						$css_file_path = $file;
					}
				}
			}

			if ( count( array_unique( $files_found ) ) === count( $required_files ) ) {
				return array(
					'icon_name'     => $icon_name,
					'css_file_path' => $css_file_path,
				);
			}
		}

		return array(
			'icon_name'     => '',
			'css_file_path' => '',
		);
	}

	/**
	 * Parse css classes
	 *
	 * @param    string $css    The css string.
	 * @param    array  $library    The css string.
	 * @return   array     The parsed css classes array.
	 */
	public static function parse_css_classes( $css, $library ) {
		$classes              = array();
		$math_all_css_pattern = '/([^\{\}]+)\{([^\}]*)\}|([\/\*])/ims';
		preg_match_all( $math_all_css_pattern, $css, $match_css );

		foreach ( $match_css[0] as $key => $value ) {
			$library_prefix  = $library['prefix'];
			$library_postfix = 'before{content';
			$css_match_regex = '/^(.' . $library_prefix . '-)[^.]+[(:)]+(' . $library_postfix . ').*$/m';
			$selector        = trim( $match_css[0][ $key ] );

			if ( preg_match( $css_match_regex, $selector ) ) {
				$trimmed_cls = '';

				$last_pattern  = '/[(:)]+(' . $library_postfix . ').*$/m';
				$first_pattern = '/^.*\./';
				$trimmed_cls   = preg_replace( $last_pattern, '', $selector );
				$trimmed_cls   = preg_replace( $first_pattern, '', $trimmed_cls );
				$trimmed_cls   = $library['leadClass'] ? "{$library['leadClass']} {$trimmed_cls}" : $trimmed_cls;

				if ( $trimmed_cls ) {
					$classes[] = $trimmed_cls;
				}
			}
		}

		return $classes;
	}

	/**
	 * Minify css
	 *
	 * @param string $content css content.
	 *
	 * @return string minify css.
	 */
	public static function minify_css( $content ) {
		$content = preg_replace( '/\/\*(?:(?!\*\/)[\s\S])*\*\/|[\r\n\t]+/', '', $content );
		$content = preg_replace( '/ {2,}/', ' ', $content );
		$content = preg_replace( '/ ([{:}]) /', '$1', $content );
		$content = preg_replace( '/([;,]) /', '$1', $content );
		$content = preg_replace( '/ !/', '!', $content );

		return $content;
	}

	/**
	 * Extract zip file
	 *
	 * @param  string $path  zip file path.
	 *
	 * @return void
	 */
	public static function extract_zip_file( $path ) {
	}
}
