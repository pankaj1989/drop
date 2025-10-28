<?php

/**
 * Media api controller
 *
 * @package droip
 */

namespace Droip\Ajax;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\HelperFunctions;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;


/**
 * Media API Class
 */
class Media
{

	/**
	 * Format media data
	 *
	 * @param object $post wp post.
	 * @return object formatted_data.
	 */
	public function format_media_data($post)
	{
		$media_categories = array(
			'image'  => DROIP_SUPPORTED_MEDIA_TYPES['image'],
			'video'  => DROIP_SUPPORTED_MEDIA_TYPES['video'],
			'svg'    => DROIP_SUPPORTED_MEDIA_TYPES['svg'],
			'audio'  => DROIP_SUPPORTED_MEDIA_TYPES['audio'],
			'lottie' => DROIP_SUPPORTED_MEDIA_TYPES['lottie'],
			'pdf'    => DROIP_SUPPORTED_MEDIA_TYPES['pdf'],
		);

		$formatted_data = array();

		foreach ($post as $key => $value) {
			if ('ID' === $key) {
				$formatted_data['id'] = $value;
			} elseif ('post_mime_type' === $key) {
				foreach ($media_categories as $category => $mime_types) {
					if (in_array($value, $mime_types, true)) {
						$formatted_data['category'] = 'svg' === $category ? 'image' : $category;
						$formatted_data['type']     = $value;
					}
				}
			} elseif ('guid' === $key) {
				$formatted_data['url'] = $value;
			} elseif ('post_name' === $key) {
				$formatted_data['name'] = $value;
				$formatted_data['alt']  = $value;
			}
		}

		// media file size converting to human readable format
		$formatted_data['file_size'] = filesize(get_attached_file($post->ID));
		$formatted_data['file_size'] = size_format($formatted_data['file_size']);

		// media file extension
		$file_path = get_attached_file($post->ID);
		$formatted_data['file_extension'] = pathinfo($file_path, PATHINFO_EXTENSION);

		$formatted_data['trash'] = false;

		$formatted_data['thumbnail'] = wp_get_attachment_image_url($post->ID);

		return $formatted_data;
	}

	/**
	 * Upload Media api
	 *
	 * @return void wp_send_json
	 */
	public static function upload_media()
	{
		$data = array();
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files = isset($_FILES['files']) ? wp_unslash($_FILES['files']) : null;
		if (!$files) {
			wp_send_json(
				array(
					'status'  => 'fail',
					'message' => 'Invalid files',
				)
			);
			die();
		}
		foreach ($files['name'] as $key => $value) {
			if (isset($files['name'][$key])) {
				$tmp_name = wp_unslash($files['tmp_name'][$key]);
				$type     = wp_unslash($files['type'][$key]);
				if ('image/svg+xml' === $type && !(new self())->validate_svg($tmp_name)) {
					wp_send_json(
						array(
							'status'  => 'fail',
							'message' => 'Invalid SVG file',
						)
					);
					die();
				}
				$file = array(
					'name'     => wp_unslash($files['name'][$key]),
					'type'     => $type,
					'tmp_name' => $tmp_name,
					'error'    => wp_unslash($files['error'][$key]),
					'size'     => wp_unslash($files['size'][$key]),
				);

				$attachment_id = self::upload_single_media($file);

				if (is_wp_error($attachment_id)) {
					$message = 'Some error occurred, please try again';
					if (isset($attachment_id->errors['upload_error'][0])) {
						$message = $attachment_id->errors['upload_error'][0];
					}
					wp_send_json(
						array(
							'status'  => 'fail',
							'message' => $message,
						)
					);
				} else {
					$post           = get_post($attachment_id);
					$formatted_data = (new self())->format_media_data($post);
					$data[]         = $formatted_data;
				}
			}
		}

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => $data,
			)
		);
	}

	public static function upload_single_media($file)
	{
		$file = self::droip_handle_upload_prefilter($file);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$_FILES        = array('upload_file' => $file);

		// $attachment_id = media_handle_upload('upload_file', 0);
		$attachment_id = media_handle_upload('upload_file', 0, array(), array('test_form' => false, 'action' => 'upload-attachment'));

		return $attachment_id;
	}


	/**
	 * Upload Media Pre filter.
	 * This method will call from 'wp_handle_upload_prefilter'this hook. which is imeplemented inside plugin init events file.
	 *
	 * @param array $file Original file.
	 * @return array $file Converted file. If image optimization is enabled from droip dashboard menu. then any image related file will converted to webp and return as file.
	 */
	public static function droip_handle_upload_prefilter($file)
	{
		$common_data = WpAdmin::get_common_data(true);
		if (!isset($common_data['image_optimization']) || !$common_data['image_optimization']) {
			return $file;
		}
		$filetype = wp_check_filetype($file['name']);

		if ('image/jpeg' === $filetype['type'] || 'image/png' === $filetype['type']) {
			if (!extension_loaded('gd')) {
				return $file;
			}

			$image           = ('image/jpeg' === $filetype['type']) ? imagecreatefromjpeg($file['tmp_name']) : imagecreatefrompng($file['tmp_name']);
			$webp_image_path = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file['tmp_name']) . '.webp';

			imagewebp($image, $webp_image_path, 80);
			imagedestroy($image);

			// copy the webp image back to the original tmp location.
			copy($webp_image_path, $file['tmp_name']);

			$file['name'] = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file['name']) . '.webp';
			$file['type'] = 'image/webp';
		}

		return $file;
	}

	/**
	 * Convert images generated sizes to webp format.
	 * This method will call from 'wp_generate_attachment_metadata' this filter hook. which is imeplemented inside plugin init events file.
	 *
	 * @param array $metadata uploaded files attachment meta data.
	 * @return array $metadata If image optimization is enabled from droip dashboard menu then it will convert all images to webp otherwise return default metadata.
	 */
	public static function droip_convert_sizes_to_webp($metadata)
	{
		$common_data = WpAdmin::get_common_data(true);
		if (
			!isset($common_data['image_optimization']) || 
			!$common_data['image_optimization'] || 
			!isset($metadata['sizes']) || 
			!isset($metadata['image_meta'])
		) {
			return $metadata;
		}
		if (!isset($metadata['file'])) {
			return $metadata;
		}
		$upload_dir         = wp_upload_dir();
		$original_image_dir = trailingslashit($upload_dir['basedir']) . dirname($metadata['file']);
		$image_files        = array_merge(array($metadata['file']), array_column($metadata['sizes'], 'file'));

		foreach ($image_files as $image_file) {
			$original_image_path = trailingslashit($original_image_dir) . $image_file;
			$webp_image_path     = preg_replace('/\\.[^.\\s]{3,4}$/', '', $original_image_path) . '.webp';

			if (!file_exists($webp_image_path)) {
				if (!extension_loaded('gd')) {
					continue;
				}

				$image_info = getimagesize($original_image_path);

				switch ($image_info[2]) {
					case IMAGETYPE_JPEG:
						$image = imagecreatefromjpeg($original_image_path);
						break;
					case IMAGETYPE_PNG:
						$image = imagecreatefrompng($original_image_path);
						break;
					case IMAGETYPE_GIF:
						$image = imagecreatefromgif($original_image_path);
						break;
					default:
						return $metadata;
				}

				imagewebp($image, $webp_image_path, 80);
				imagedestroy($image);
			}

			// Delete the original image.
			if (file_exists($original_image_path)) {
				unlink($original_image_path);
			}

			$metadata = self::droip_replace_file_in_metadata($metadata, $image_file, basename($webp_image_path));
		}

		return $metadata;
	}

	/**
	 * Replace file to meta data.
	 *
	 * @param array  $metadata files attachment metadata.
	 * @param string $old_file old file original path. (jpg, png, gif).
	 * @param string $new_file new file original path.
	 *
	 * @return array $metadata files updated attachment metadata.
	 */
	private static function droip_replace_file_in_metadata($metadata, $old_file, $new_file)
	{
		if ($old_file === $metadata['file']) {
			$metadata['file'] = str_replace($old_file, $new_file, $metadata['file']);
		}

		foreach ($metadata['sizes'] as $size => $info) {
			if ($old_file === $info['file']) {
				$metadata['sizes'][$size]['file'] = $new_file;
			}
		}

		return $metadata;
	}

	/**
	 * Upload font zip
	 *
	 * @return void wp_send_json.
	 */
	public static function upload_font_zip()
	{

		require_once ABSPATH . 'wp-admin/includes/image.php';
		if (!is_object($wp_filesystem)) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		global $wp_filesystem;

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = isset($_FILES['file']) ? wp_unslash($_FILES['file']) : null;

		if (!$file) {
			wp_send_json(
				array(
					'status'  => 'fail',
					'message' => 'Invalid file',
				)
			);
			die();
		}

		$filename    = sanitize_file_name($file['name']);
		$source      = $file['tmp_name'];
		$folder_name = str_replace('.zip', '', $filename);
		$path        = dirname(__FILE__) . '/';
		$targetzip   = $path . $filename;
		$upload_dir  = wp_upload_dir()['basedir'] . '/' . DROIP_APP_PREFIX . '-fonts/' . $folder_name;

		$file_extension      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if ($file_extension !== 'zip') {
			wp_send_json([
				'status'  => 'fail',
				'message' => 'Only .zip files are allowed',
			]);
			die();
		}

		if (!file_exists($upload_dir)) {
			mkdir($upload_dir, 0777);
		}

		// use wp_upload_bits() to move the uploaded file .
		$wp_upload_result = wp_upload_bits($filename, null, $wp_filesystem->get_contents($source));

		if (false === $wp_upload_result['error']) {
			$upload_file_path = $wp_upload_result['file'];
			$base_url         = wp_get_upload_dir()['baseurl'];

			$zip = new ZipArchive();
			$zip->open($upload_file_path);
			$zip->extractTo($upload_dir);
			$zip->close();
			unlink($upload_file_path);

			if (!file_exists($upload_dir . '/stylesheet.css')) {
				self::delete_dir($upload_dir);
				wp_send_json(
					array(
						'status'  => 'fail',
						'message' => 'Invalid data.',
					)
				);
			}
			$style_sheet_string = $wp_filesystem->get_contents($upload_dir . '/stylesheet.css');
			$font_family_name = self::get_common_family_name($style_sheet_string);

			$result = self::get_rewritten_style_and_variants($style_sheet_string, $font_family_name); 
			$rewritten_css = $result['css']; 
			$variants = $result['variants'];

			$wp_filesystem->put_contents($upload_dir . '/stylesheet.css', $rewritten_css);

			if (!$font_family_name) {
				self::delete_dir($upload_dir);
				wp_send_json(
					array(
						'status'  => 'fail',
						'message' => 'Font family name not found',
					)
				);
			} else {
				$renamed_folder_name = str_replace(' ', '', $font_family_name);

				$old_folder_name = $upload_dir;
				$new_folder_name = wp_upload_dir()['basedir'] . '/' . DROIP_APP_PREFIX . '-fonts/' . $renamed_folder_name;

				if (is_dir($new_folder_name)) {
					self::delete_dir($new_folder_name);
				}

				rename($old_folder_name, $new_folder_name);
				$font = array(
					'fontUrl'  => $base_url . '/' . DROIP_APP_PREFIX . '-fonts/' . $renamed_folder_name . '/stylesheet.css',
					'family'   => $font_family_name,
					'variants' => $variants,
					'subsets'  => array('latin'),
					'uploaded' => true,
					'version'  => 'v1',
				);

				self::remove_extra_files_from_font_folder($new_folder_name);
				wp_send_json(
					array(
						'status' => 'success',
						'data'   => $font,
					)
				);
			}
		} else {
			wp_send_json(
				array(
					'status'  => 'fail',
					'message' => 'Zip file upload fail',
				)
			);
		}
	}

	public static function get_rewritten_style_and_variants($css_string, $common_family_name){
				// Define weight keywords 
				$weightKeywords = [
						'extrablack' => '900',
						'black'      => '900',
						'heavy'      => '900',
						'extrabold'  => '800',
						'ultrabold'  => '800',
						'semibold'   => '600',
						'demibold'   => '600',
						'bold'       => '700',
						'medium'     => '500',
						'extralight' => '200',
						'ultralight' => '200',
						'light'      => '300',
						'book'       => '400',
						'regular'    => '400',
						'normal'     => '400',
						'thin'       => '100',
					];

					// Extract all @font-face blocks
					preg_match_all('/@font-face\s*{[^}]*}/is', $css_string, $blocks);
					
					$rewritten_css = '';
					$variants = [];

					foreach ($blocks[0] as $block) {
						$weight = null; 
						$style  = 'normal';

						// get src
						preg_match('/src:[^;]+;/i', $block, $srcMatch);
						$src = isset($srcMatch[0]) ? $srcMatch[0] : '';

						// get font-family
						preg_match('/font-family:\s*[\'"]?([^;\'"]+)[\'"]?;/i', $block, $familyMatch);
						$family = isset($familyMatch[1]) ? $familyMatch[1] : '';

						// Detect weight from keywords in src or font-family only
						foreach ($weightKeywords as $keyword => $w) {
								if (stripos($src, $keyword) !== false || stripos($family, $keyword) !== false) {
										$weight = $w;
										break;
								}
						}

						// Fallback to declared CSS font-weight if not found
						if ($weight === null) {
								if (preg_match('/font-weight:\s*([0-9]+)/i', $block, $match)) {
										$weight = $match[1];
								} else {
										$weight = '400';
								}
						}

						if (stripos($block, 'italic') !== false) {
								$style = 'italic';
						}

						// rewritten @font-face block
						$rewritten_css .= "@font-face {
								font-family: '{$common_family_name}';
								{$src}
								font-weight: {$weight};
								font-style: {$style};
						}\n\n";

						// if weight is 400, change -> regular, if italic 400italic -> italic, 200italic,700italic etc
						if ($weight === '400') {
								$variant = ($style === 'italic') ? 'italic' : 'regular';
						} else {
								$variant = ($style === 'italic') ? $weight.'italic' : $weight;
						}

						$variants[] = $variant;
				}

				// remove duplicate variants
				$variants = array_values(array_unique($variants));

				return [
						'css'      => $rewritten_css,
						'variants' => $variants
				];
	}

	public static function get_common_family_name($css_string) {
		// get all font family names
    $pattern = '/font-family\s*:\s*([^;]+);/i';
    preg_match_all($pattern, $css_string, $matches);

    if (empty($matches[1])) {
        return null; 
    }

    $base_names = [];

    foreach ($matches[1] as $match) {
        $parts = explode(',', $match);
        foreach ($parts as $p) {
            $f = trim($p, " \t\n\r\0\x0B'\""); 
            if (!$f) continue;

            // normalize font family name
            $base = preg_replace('/[-_\s]?(thin|extra|light|regular|medium|bold|black|italic|\d+)/i', '', $f);

            if ($base) {
                $base_names[] = strtolower($base); 
            }
        }
    }

    if (empty($base_names)) {
        return null;
    }

		// find most common 
    $counts = array_count_values($base_names);
    arsort($counts); // most frequent first
    $common_name = array_key_first($counts);
		$common_name = str_replace(['_', '-'], ' ', $common_name);
    $common_name = ucwords($common_name);

    return $common_name;
	}

	/**
	 * Remove extra files from font folder.
	 * we need only stylesheet.css, .woff and .woff2 file.
	 * others files will be removed.
	 *
	 * @param string $folder_path //raw path of uploaded folder.
	 * @return void
	 */
	private static function remove_extra_files_from_font_folder($folder_path)
	{
		if (is_dir($folder_path)) {
			$files = scandir($folder_path);

			foreach ($files as $file) {
				if ('.' !== $file && '..' !== $file) {
					$file_path = $folder_path . '/' . $file;

					if (is_file($file_path)) {
						$ext = pathinfo($file_path, PATHINFO_EXTENSION);

						if ('css' !== $ext && 'woff' !== $ext && 'woff2' !== $ext) {
							unlink($file_path);
						}
					}
				}
			}
		}
	}


	/**
	 * Remove custom font folder from server
	 *
	 * @return void wp_send_json.
	 */
	public static function remove_custom_font_folder_from_server()
	{
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = HelperFunctions::sanitize_text(isset($_POST['data']) ? $_POST['data'] : null);
		if (!$data) {
			wp_send_json(
				array(
					'status'  => 'fail',
					'message' => 'Invalid post data',
				)
			);
			die();
		}
		$fonts = json_decode(stripslashes($data), true);

		foreach ($fonts as $key => $value) {
			$upload_root_dir = wp_upload_dir()['basedir'];
			$upload_dir      = $upload_root_dir . '/' . DROIP_APP_PREFIX . '-fonts/' . $value['family'];
			if (is_dir($upload_dir)) {
				self::delete_dir($upload_dir);
			}

			// Remove font from local.
			$font_family_slug = sanitize_title_with_dashes($value['family']);
		  $font_local_dir = WP_CONTENT_DIR . "/uploads/droip-fonts/{$font_family_slug}";

			if (is_dir($font_local_dir)) {
				HelperFunctions::delete_directory($font_local_dir);
			}

		}
		wp_send_json(
			array(
				'status'  => 'success',
				'message' => 'Font folder deleted success',
				'url'     => $upload_dir,
			)
		);
	}

	/**
	 * Delete Dir
	 *
	 * @param string $dir_path directory path string.
	 * @throws InvalidArgumentException If the $dir_path is not a directory.
	 * @return void
	 */
	public static function delete_dir($dir_path)
	{
		if (!is_dir($dir_path)) {
			throw new InvalidArgumentException("$dir_path must be a directory");
		}
		if (substr($dir_path, strlen($dir_path) - 1, 1) !== '/') {
			$dir_path .= '/';
		}
		$files = glob($dir_path . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				self::delete_dir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dir_path);
	}

	/**
	 * Get Font Family name using regex
	 *
	 * @param string $css_string css string.
	 * @return string font family name.
	 */
	public static function get_font_family_name_using_regex($css_string)
	{
		$pattern = '/font-family:.*?;/';
		preg_match($pattern, $css_string, $matches);
		$font_family = $matches[0];
		$font_family = str_replace('font-family:', '', $font_family);
		$font_family = str_replace(';', '', $font_family);
		$font_family = str_replace("'", '', $font_family);
		$font_family = trim($font_family);
		return $font_family;
	}

	/**
	 * Upload base64 image
	 *
	 * @return void wp send json
	 */
	public static function upload_base64_img()
	{
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$source = HelperFunctions::sanitize_text(isset($_POST['source']) ? $_POST['source'] : null);
		$imageName = HelperFunctions::sanitize_text(isset($_POST['imageName']) ? $_POST['imageName'] : null);
	
		// Upload dir.
		$upload_dir = wp_upload_dir();

		$img = str_replace('data:image/png;base64,', '', $source);
		$img = str_replace(' ', '+', $img);
		//phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded         = base64_decode($img);
		// $filename        = 'bas64-image-' . gmdate('Y-m-d') . '.png';
		$filename = $imageName ? sanitize_file_name($imageName) . '.png' : 'bas64-image-' . gmdate('Y-m-d') . '.png';
		
		$file_type       = 'image/png';
		// $hashed_filename = md5($filename . microtime()) . '.png';

		// Save the image using wp_upload_bits.
		$upload = wp_upload_bits($filename, null, $decoded);

		if (!$upload['error']) {
			$file = array(
				'name'     => basename($upload['file']),
				'type'     => mime_content_type($upload['file']),
				'tmp_name' => $upload['file'],
				'error'    => 0,
				'size'     => filesize($upload['file']),
			);

			
			$attachment_id = self::upload_single_media($file);
			$img           = wp_get_attachment_image_src($attachment_id, "full");

			wp_send_json(
				array(
					'status' => 'success',
					'src'    => $img[0],
					'id'     => $attachment_id,
				)
			);
		} else {
			wp_send_json(
				array(
					'status'  => 'fail',
					'message' => 'Error uploading base64 image',
				)
			);
		}
	}

	/**
	 * Validate a svg file
	 *
	 * @param string $svg_file svg file path.
	 * @return bool
	 */
	private function validate_svg($svg_file)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress parsing errors for better handling.

    // Attempt to load the SVG file.
    if ($dom->load($svg_file)) {
        // Check if the root element is <svg>.
        $root_element = $dom->documentElement->nodeName;
        if ($root_element !== 'svg') {
            return false; // Not a valid SVG root element.
        }

        // Check for <script> elements.
        $script_elements = $dom->getElementsByTagName('script');
        if ($script_elements->length > 0) {
            return false; // Invalid SVG: Contains <script> elements.
        }

        // Check for potentially harmful attributes.
        $suspicious_attributes = ['onload', 'onclick', 'onmouseover', 'onfocus', 'onerror', 'onmousedown'];
        $xpath = new DOMXPath($dom);

        foreach ($suspicious_attributes as $attribute) {
            $nodes_with_attribute = $xpath->query('//@' . $attribute);
            if ($nodes_with_attribute->length > 0) {
                return false; // Invalid SVG: Contains dangerous attributes.
            }
        }

        return true; // Valid and safe SVG file.
    }

    return false; // Could not load the SVG file.
	}

}