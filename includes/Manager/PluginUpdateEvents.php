<?php

/**
 * Plugin update events handler
 *
 * @package droip
 */

namespace Droip\Manager;

use Droip\HelperFunctions;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PluginUpdateEvents
{
    /**
     * Holds changelog API response data
     *
     * @var object|null
     */
    private $droip_changelog_response_data = null;

    /**
     * Droip API endpoint
     *
     * @var string
     */
    private $api_end_point;

    /**
     * Cache duration in seconds (8 hours = 28800 seconds)
     *
     * @var int
     */
    private $cache_duration = 28800;

    /**
     * Transient key for caching our API response
     *
     * @var string
     */
    private $cache_key = 'droip_update_check_cache';

    public function __construct()
    {
        $this->api_end_point = DROIP_CORE_PLUGIN_URL . '?action=latest-release&product=droip';

        add_filter('site_transient_update_plugins', array($this, 'check_for_update'));

        // Plugin info popup hook (keeps running on plugin pages)
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
    }

    /**
     * Hook into update_plugins transient
     *
     * @param object $transient Transient data.
     * @return object
     */
    public function check_for_update($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }

        $base_name = 'droip/droip.php';

        // Check our own cache first
        $cached_data = get_transient($this->cache_key);

        if ($cached_data !== false) {
            // Use cached data if available
            $remote = $cached_data;
        } else {
            // Cache expired or doesn't exist, make API call
            $remote = $this->check_for_update_api();

            // Cache the result for 8 hours
            if ($remote) {
                set_transient($this->cache_key, $remote, $this->cache_duration);
            }
        }

        if (empty($remote->new_version)) {
            return $transient;
        }

        $version_in_db = HelperFunctions::get_droip_version_from_db();
        if ( version_compare($version_in_db, $remote->new_version, '<') ) {
            // $update_info = array(
            //     'new_version' => $remote->new_version,
            //     'package'     => $remote->package,
            //     'tested'      => $remote->tested ?? '',
            //     'requires'    => $remote->requires ?? '',
            //     'slug'        => 'droip',
            // );

            $update_info = array(
                'id'          => 'droip', // unique ID
                'slug'        => 'droip', // must match plugin directory
                'plugin'      => $base_name, // droip/droip.php
                'new_version' => $remote->new_version,
                'url'         => 'https://droip.com', // plugin info page
                'package'     => $remote->package, // direct .zip link
                'tested'      => $remote->tested ?? '',
                'requires'    => $remote->requires ?? '',
            );

            $transient->response[$base_name] = (object) $update_info;
        }

        return $transient;
    }

    /**
     * Check for updates through the Droip API
     *
     * @return object|null
     */
    private function check_for_update_api()
    {
        if ($this->droip_changelog_response_data) {
            return $this->droip_changelog_response_data;
        }

        $data = HelperFunctions::http_get($this->api_end_point);
        $data = json_decode($data, true);

        if ($data && $data['success']) {
            $response_data = $data['data'];

            $prepared_data = array(
                'name'         => DROIP_APP_NAME,
                'slug'         => DROIP_APP_PREFIX,
                'new_version'  => $response_data['version'] ?? '',
                'package'      => $response_data['file_path'] ?? '',
                'last_updated' => $response_data['released_on'] ?? '',
                'changelog'    => $response_data['changelog'] ?? '',
            );

            $this->droip_changelog_response_data = (object) $prepared_data;
        }

        return $this->droip_changelog_response_data;
    }

    /**
     * Provide plugin details for plugin info popup
     *
     * @param false|object $res Result.
     * @param string       $action Action type.
     * @param object       $args Args.
     * @return object|false
     */
    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $res;
        }

        // For plugin info popup, we can use cached data or make fresh call
        $cached_data = get_transient($this->cache_key);

        if ($cached_data !== false) {
            $remote = $cached_data;
        } else {
            $remote = $this->check_for_update_api();

            // Cache the result
            if ($remote) {
                set_transient($this->cache_key, $remote, $this->cache_duration);
            }
        }

        if (isset($args->slug) && $args->slug === 'droip' && ! empty($remote->new_version)) {
            $res                 = new \stdClass();
            $res->name           = $remote->name;
            $res->slug           = $remote->slug;
            $res->version        = $remote->new_version;
            $res->author         = '<a href="' . esc_url(DROIP_CORE_PLUGIN_URL) . '">Droip</a>';
            $res->homepage       = DROIP_CORE_PLUGIN_URL;
            $res->last_updated   = $remote->last_updated;
            $res->download_link  = $remote->package;
            $res->sections       = array(
                'changelog' => $remote->changelog,
            );
            return $res;
        }

        return $res;
    }
}