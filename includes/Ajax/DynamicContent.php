<?php

/**
 * DynamicContent manager API
 *
 * @package droip
 */

namespace Droip\Ajax;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Droip\API\ContentManager\ContentManagerHelper;
use Droip\HelperFunctions;

use function PHPSTORM_META\map;

/**
 * DynamicContent API Class
 */
class DynamicContent
{

	/**
	 * Get Dynamic element data
	 *
	 * @return void wpjson response
	 */
	public static function get_dynamic_element_data()
	{
		//phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// $content_type = HelperFunctions::sanitize_text( isset( $_GET['content_type'] ) ? $_GET['content_type'] : null );
		// //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// $content_value = HelperFunctions::sanitize_text( isset( $_GET['content_value'] ) ? $_GET['content_value'] : null );
		// //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// $meta_name = HelperFunctions::sanitize_text( isset( $_GET['meta_name'] ) ? $_GET['meta_name'] : null );
		// //phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// $post_id = (int) HelperFunctions::sanitize_text( isset( $_GET['post_id'] ) ? $_GET['post_id'] : null );
		// $settings = HelperFunctions::sanitize_text( isset( $_GET['settings'] ) ? $_GET['settings'] : null );

		$contentInfo = HelperFunctions::sanitize_text(isset($_GET['contentInfo']) ? $_GET['contentInfo'] : null);

		$contentInfo = json_decode(stripslashes($contentInfo), true);


		$content = apply_filters('droip_dynamic_content', false, $contentInfo);
		if ($content !== false) {
			wp_send_json($content);
		}

		$dynamicContent = isset($contentInfo['dynamicContent']) ?  $contentInfo['dynamicContent'] : array();
		$content_type = isset($dynamicContent['type']) ? $dynamicContent['type'] : 'post';
		$content_value = isset($dynamicContent['value']) ? $dynamicContent['value'] : 'post_title';
		$content_value = isset($dynamicContent['value']) ? $dynamicContent['value'] : 'post_title';
		$meta_name = isset($dynamicContent['meta']) ? $dynamicContent['meta'] : '';
		$post_id = (int) HelperFunctions::sanitize_text(isset($contentInfo['post_id']) ? $contentInfo['post_id'] : null);

		// Post excerpt length for droip editor
		if (!empty($content_value) && $content_value === 'post_excerpt' && isset($dynamicContent['postExcerptLength'])) {
			$GLOBALS['droip_post_excerpt_length'] = (int) $dynamicContent['postExcerptLength'];
		}

		switch ($content_type) {
			case 'post': {
					$post = null;

					if (!empty($post_id)) {
						$post = get_post($post_id);
					} else if (isset($contentInfo['collectionItem'], $contentInfo['collectionItem']['ID'])) {
						$post = get_post($contentInfo['collectionItem']['ID']);
					}

					$content = HelperFunctions::get_post_dynamic_content($content_value, $post, $meta_name);

					wp_send_json($content);
					break;
				}

			case 'author': {
					$post = null;

					if (!empty($post_id)) {
						$post = get_post($post_id);
					} else if (isset($contentInfo['collectionItem'], $contentInfo['collectionItem']['ID'])) {
						$post = get_post($contentInfo['collectionItem']['ID']);
					}
					$content = HelperFunctions::get_post_dynamic_content($content_value, $post);
					wp_send_json($content);
					break;
				}

			case 'user': {
					$user_id = get_current_user_id();
					if(isset($contentInfo['collectionItem'], $contentInfo['collectionItem']['ID'])){
						$user_id = $contentInfo['collectionItem']['ID'];
					}
					$content = HelperFunctions::get_user_dynamic_content($content_value, $user_id, $meta_name);
					wp_send_json($content);
					break;
				}

			case 'site': {
					$content = HelperFunctions::get_post_dynamic_content($content_value);
					wp_send_json($content);
					break;
				}

			case 'term': {
					$term_id = $post_id; // here post_id mean term_id based on content_type, here set term_id;
					$content = HelperFunctions::get_term_dynamic_content($content_value, $term_id, $meta_name);
					wp_send_json($content);
					break;
				}

			default: {
					wp_send_json(false);
					break;
				}
		}
	}


	public static function get_default_condition_data($post)
	{
		$post_fields = [
			['value' => 'post_id', 'title' => 'Post ID', 'operand' => ['type' => 'search', 'item' => 'post'], 'operator_type' => 'dropdown_operators'],
			['value' => 'post_title', 'title' => 'Post Title', 'operand' => null, 'operator_type' => 'text_operators'],
			['value' => 'post_author', 'title' => 'Post Author', 'operand' => ['type' => 'search', 'item' => 'user'], 'operator_type' => 'dropdown_operators'],
			['value' => 'post_date', 'title' => 'Post Date', 'operand' => ['type' => 'date'], 'operator_type' => 'date_operators'],
			['value' => 'post_time', 'title' => 'Post Time', 'operand' => ['type' => 'time'], 'operator_type' => 'text_operators'],
		];

		$user_fields = [
			['value' => 'display_name', 'title' => 'Display Name', 'operand' => null, 'operator_type' => 'text_operators'],
			['value' => 'user_email', 'title' => 'User Email', 'operand' => null, 'operator_type' => 'text_operators'],
			['value' => 'user_nicename', 'title' => 'User Nice Name', 'operand' => null, 'operator_type' => 'text_operators'],
			['value' => 'registered_date', 'title' => 'Registered Date', 'operand' => ['type' => 'date'], 'operator_type' => 'date_operators'],
			['value' => 'registered_time', 'title' => 'Registered Time', 'operand' => ['type' => 'time'], 'operator_type' => 'text_operators'],
		];

		// Add post fields
		foreach ($post_fields as $field) {
			$post['post'][$field['value']] = [
				'title'     => $field['title'],
				'operator_type' => $field['operator_type'],
				'operand' => $field['operand']
			];
		}

		// Add user fields
		foreach ($user_fields as $field) {
			$post['user'][$field['value']] = [
				'title'     => $field['title'],
				'operator_type' => $field['operator_type'],
				'operand' => $field['operand']
			];
		}

		return $post;
	}


	public static function get_visibility_condition_fields()
	{
		$conditions = [];
		$collection_data = HelperFunctions::sanitize_text(isset($_GET['collectionData']) ? $_GET['collectionData'] : false);
		if ($collection_data) $collection_data = json_decode($collection_data, true);


		$type = $collection_data['type'];
		$collectionType = $collection_data['collectionType'];

		$roles = array_map(
			fn($key, $value) => ['value' => $key, 'title' => $value],
			array_keys(wp_roles()->role_names),
			wp_roles()->role_names
		);
		$roles[] = ['value' => 'logged_in', 'title' => 'Logged In'];
		$roles[] = ['value' => 'guest', 'title' => 'Guest'];

		$conditions['user'] = ['title' => 'User', 'fields' => [
			['value' => 'display_name', 'title' => 'Display Name', 'operator_type' => 'text_operators'],
			['value' => 'user_login', 'title' => 'Username', 'operator_type' => 'text_operators'],
			['value' => 'user_nicename', 'title' => 'Nice Name', 'operator_type' => 'text_operators'],
			['value' => 'user_email', 'title' => 'Email', 'operator_type' => 'text_operators'],
			[
				'value' => 'user_registered',
				'title' => 'Registered Date',
				'operator_type' => 'date_operators',
				'operand_type' => DROIP_PLUGIN_SETTINGS["DATEPICKER"]
			],
			[
				'value' => 'role',
				'title' => 'Role',
				'operator_type' => 'list_operators',
				'operand_type' => array_merge(
					DROIP_PLUGIN_SETTINGS['SELECT'],
					[
						'options' => $roles
					]
				)
			],
		]];

		if ($collectionType === 'posts') {
			$conditions['post'] = ['title' => 'Post', 'fields' => []];
			$conditions['post']['fields'] = [
				['value' => 'post_id', 'title' => 'ID', 'operator_type' => 'dropdown_operators'],
				['value' => 'post_title', 'title' => 'Title', 'operator_type' => 'text_operators'],
				[
					'value' => 'post_author',
					'title' => 'Author',
					'operator_type' => 'dropdown_operators',
					'operand_type' => array_merge(
						DROIP_PLUGIN_SETTINGS['SELECT'],
						[
							'options' => array_map(fn($a) => ['value' => $a->data->ID, 'title' => $a->data->display_name], get_users())
						]
					)
				],
				[
					'value' => 'post_date',
					'title' => 'Date',
					'operator_type' => 'date_operators',
					'operand_type' => DROIP_PLUGIN_SETTINGS["DATEPICKER"]
				]
			];
		}
		$conditions = apply_filters('droip_visibility_condition_fields', $conditions, $collection_data);

		wp_send_json($conditions);
	}



	public static function getCustomFields()
	{
		return [
			['type' => 'text', 'dynamicContentType' => 'content'],
			['type' => 'rich-text', 'dynamicContentType' => 'content'],
			['type' => 'image', 'dynamicContentType' => 'image'],
			['type' => 'video', 'dynamicContentType' => 'video'],
			['type' => 'email', 'dynamicContentType' => ['content', 'anchor']],
			['type' => 'phone', 'dynamicContentType' => ['content', 'anchor']],
			['type' => 'number', 'dynamicContentType' => 'content'],
			['type' => 'date', 'dynamicContentType' => 'content'],
			['type' => 'time', 'dynamicContentType' => 'content'],
			['type' => 'switch'], // no dynamicContentType
			['type' => 'option', 'dynamicContentType' => ['content', 'anchor']],
			['type' => 'url', 'dynamicContentType' => ['content', 'anchor']],
			['type' => 'file', 'dynamicContentType' => ['anchor']],
			['type' => 'reference'], // no dynamicContentType
			['type' => 'multi-reference'], // no dynamicContentType
			['type' => 'gallery', 'dynamicContentType' => 'gallery'],
		];
	}

	public static function sortContentManagerCustomFieldsByDynamicContentType()
	{
		$customFields = self::getCustomFields();
		$sortedData = [
			'content' => [],
			'image' => [],
			'video' => [],
			'anchor' => [],
			'gallery' => [],
		];

		foreach ($customFields as $field) {
			if (!isset($field['dynamicContentType'])) {
				continue;
			}

			if (is_array($field['dynamicContentType'])) {
				foreach ($field['dynamicContentType'] as $type) {
					if (array_key_exists($type, $sortedData)) {
						$sortedData[$type][] = $field['type'];
					}
				}
			} elseif (is_string($field['dynamicContentType'])) {
				$type = $field['dynamicContentType'];
				if (array_key_exists($type, $sortedData)) {
					$sortedData[$type][] = $field['type'];
				}
			}
		}

		return $sortedData;
	}



	public static function convertDroipCMSFieldsToSelectOptions($type, $fields)
	{
		if (!is_array($fields)) {
			return [];
		}

		$sortedCMSFields = self::sortContentManagerCustomFieldsByDynamicContentType();

		if (!isset($sortedCMSFields[$type])) {
			return [];
		}

		$currentTypeCMSFields = $sortedCMSFields[$type];

		$options = [];

		foreach ($fields as $field) {
			if (in_array($field['type'], $currentTypeCMSFields)) {
				$options[] = [
					'title' => isset($field['title']) ? $field['title'] : '',
					'value' => isset($field['id']) ? $field['id'] : '',
				];
			}
		}

		return $options;
	}


	public static function get_default_dynamic_content_fields()
	{
		return 		[
			'type' => [
				'content' => [
					'id' => 'content',
				],
				'image' => [
					'id' => 'image',
				],
				'video' => [
					'id' => 'video',
				],
				'anchor' => [
					'id' => 'anchor',
				],
			],
			'typeValues' => [
				'content' => [
					[
						'value' => 'manual',
						'title' => 'Manual',
					],
					[
						'value' => 'post',
						'title' => 'Post',
					],
					[
						'value' => 'term',
						'title' => 'Term',
					],
					[
						'value' => 'comment',
						'title' => 'Comment',
					],
					[
						'value' => 'user',
						'title' => 'User',
					],
					[
						'value' => 'site',
						'title' => 'Site',
					],
					[
						'value' => 'others',
						'title' => 'Others',
					],
				],
				'image' => [
					[
						'value' => 'manual',
						'title' => 'Manual',
					],
					[
						'value' => 'post',
						'title' => 'Post',
					],
					[
						'value' => 'site',
						'title' => 'Site',
					],
					[
						'value' => 'author',
						'title' => 'Author',
					],
					[
						'value' => 'user',
						'title' => 'User',
					],
				],
				'video' => [
					[
						'value' => 'manual',
						'title' => 'Manual',
					],
					[
						'value' => 'post',
						'title' => 'Post',
					],
				],
				'anchor' => [
					[
						'value' => 'manual',
						'title' => 'Manual',
					],
					[
						'value' => 'post',
						'title' => 'Post',
					],
					[
						'value' => 'term',
						'title' => 'Term',
					],
					[
						'value' => 'author',
						'title' => 'Author',
					],
					[
						'value' => 'user',
						'title' => 'User',
					],
				],
			],
			'typeValuesAttr' => [
				'content' => [
					'manual' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
					'post' => [
						[
							'value' => 'post_id',
							'title' => 'ID',
						],
						[
							'value' => 'post_title',
							'title' => 'Title',
						],
						[
							'value' => 'post_author',
							'title' => 'Author',
						],
						[
							'value' => 'post_date',
							'title' => 'Date',
						],
						[
							'value' => 'post_time',
							'title' => 'Time',
						],
						[
							'value' => 'post_content',
							'title' => 'Content',
						],
						[
							'value' => 'post_excerpt',
							'title' => 'Excerpt',
						],
						[
							'value' => 'post_meta',
							'title' => 'Meta',
						],
					],
					'comment' => [
						[
							'value' => 'comment_id',
							'title' => 'ID',
						],
						[
							'value' => 'parent_id',
							'title' => 'Parent ID',
						],
						[
							'value' => 'post_id',
							'title' => 'Post ID',
						],
						[
							'value' => 'comment_author',
							'title' => 'Author',
						],
						[
							'value' => 'comment_author_email',
							'title' => 'Author email',
						],
						[
							'value' => 'comment_date',
							'title' => 'Date',
						],
						[
							'value' => 'comment_time',
							'title' => 'Time',
						],
						[
							'value' => 'comment_content',
							'title' => 'Content',
						],
						[
							'value' => 'comment_karma',
							'title' => 'Karma',
						],
					],
					'term' => [
						[
							'value' => 'name',
							'title' => 'Name',
						],
						[
							'value' => 'slug',
							'title' => 'Slug',
						],
						[
							'value' => 'description',
							'title' => 'Description',
						],
						[
							'value' => 'count',
							'title' => 'Count',
						],
					],
					'page' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
					'user' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'display_name',
							'title' => 'Display Name',
						],
						[
							'value' => 'user_email',
							'title' => 'Email',
						],
						[
							'value' => 'user_nicename',
							'title' => 'Nice Name',
						],
						[
							'value' => 'registered_date',
							'title' => 'Registered Date',
						],
						[
							'value' => 'registered_time',
							'title' => 'Registered Time',
						],
						[
							'value' => 'initials',
							'title' => 'Initials',
						],
						[
							'value' => 'user_meta',
							'title' => 'Meta',
						],
					],
					'site' => [
						[
							'value' => 'site_name',
							'title' => 'Site Name',
						],
						[
							'value' => 'site_description',
							'title' => 'Site Description',
						],
						[
							'value' => 'site_url',
							'title' => 'Site URL',
						],
					],
					'others' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'item_index',
							'title' => 'Item Index',
						],
					],
				],
				'image' => [
					'manual' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
					'post' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'featured_image',
							'title' => 'Featured Image',
						],
						[
							'value' => 'post_meta',
							'title' => 'Post Meta',
						],
					],
					'site' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'site_logo',
							'title' => 'Site Logo',
						],
					],
					'author' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'author_profile_picture',
							'title' => 'Author Profile Picture',
						],
					],
					'user' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'profile_image',
							'title' => 'Profile Image',
						],
					],
				],
				'video' => [
					'manual' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
					'post' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
				],
				'anchor' => [
					'manual' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
					],
					'post' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'post_page_link',
							'title' => 'Post link',
						],
					],
					'term' => [
						[
							'value' => 'link',
							'title' => 'Link',
						],
					],
					'author' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'author_posts_page_link',
							'title' => 'Author Posts',
						],
					],
					'user' => [
						[
							'value' => 'none',
							'title' => 'None',
						],
						[
							'value' => 'user_url',
							'title' => 'User URL',
						],
					],
				],
			],
		];
	}

	public static function get_dynamic_content_fields()
	{
		$data = self::get_default_dynamic_content_fields();

		$collection_data = HelperFunctions::sanitize_text(isset($_GET['collectionData']) ? $_GET['collectionData'] : false);
		if ($collection_data) $collection_data = json_decode($collection_data, true);
		$type = $collection_data['type'];
		$collectionType = $collection_data['collectionType'];
		$elementContentType = $collection_data['elementContentType'] ?? '';

		$cleanData = [];
		$cleanData['type'][$elementContentType] = $data['type'][$elementContentType];
		$cleanData['typeValues'][$elementContentType] = $data['typeValues'][$elementContentType];
		$cleanData['typeValuesAttr'][$elementContentType] = $data['typeValuesAttr'][$elementContentType];
		$data = $cleanData;

		if ($collectionType === 'posts' && str_contains($type, DROIP_CONTENT_MANAGER_PREFIX)) {
			$post_parent = str_replace(DROIP_CONTENT_MANAGER_PREFIX . '_', '', $collection_data['type']);
			$post = ContentManagerHelper::get_post_type($post_parent, true);

			if (!empty(array_filter($post['fields'], fn($obj) => $obj['type'] === 'reference'))) {
				$data['typeValues'][$elementContentType] = array_merge($data['typeValues'][$elementContentType], [
					[
						'value' => 'reference',
						'title' => 'Reference',
					],
				]);

				$data['availableRefNames'] = array_reduce(
					array_filter($post['fields'], fn($obj) => $obj['type'] === 'reference'),
					function ($carry, $obj) {
						$carry[] = [
							'value' => $obj['id'],
							'title' => $obj['title'],
							'post_id' => $obj['ref_collection']
						];
						return $carry;
					},
					[]
				);

				$data['typeValuesAttr'][$elementContentType]['reference'] = array_reduce(
					array_filter($post['fields'], fn($obj) => $obj['type'] === 'reference'),
					function ($carry, $obj) use ($data, $elementContentType) {
						$carry[$obj['id']] = array_merge(
							$data['typeValuesAttr'][$elementContentType]['post'],
							self::convertDroipCMSFieldsToSelectOptions($elementContentType, $obj['fields'][0]['fields'])
						);
						return $carry;
					},
					[]
				);
			}

			$data['typeValues'][$elementContentType] = array_map(
				fn($obj) => $obj['value'] === 'post' ? [
					'value' => $obj['value'],
					'title' => $post['post_title'],
				] : [
					'value' => $obj['value'],
					'title' => $obj['title'],
				],
				$data['typeValues'][$elementContentType]
			);

			$data['typeValuesAttr'][$elementContentType]['post'] = array_merge(
				$data['typeValuesAttr'][$elementContentType]['post'],
				self::convertDroipCMSFieldsToSelectOptions($elementContentType, $post['fields'])
			);
		}

		$data = apply_filters('droip_dynamic_content_fields', $data, $collection_data);
		wp_send_json($data);
	}
}