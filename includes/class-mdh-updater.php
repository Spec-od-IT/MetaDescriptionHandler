<?php
/**
 * GitHub Update Checker
 *
 * Checks for plugin updates from GitHub Releases and integrates
 * with the WordPress plugin update system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Updater {

    private static $instance = null;

    private $github_repo = 'Spec-od-IT/MetaDescriptionHandler';
    private $plugin_slug = 'meta-description-handler';
    private $cache_key = 'mdh_github_release';
    private $cache_ttl = 3600; // 1 hour

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
        add_filter('auto_update_plugin', array($this, 'enable_auto_update'), 10, 2);
    }

    /**
     * Enable automatic updates for this plugin.
     */
    public function enable_auto_update($update, $item) {
        if (isset($item->slug) && $this->plugin_slug === $item->slug) {
            return true;
        }
        return $update;
    }

    /**
     * Fetch latest release info from GitHub API (cached).
     */
    private function get_release_info() {
        $release = get_transient($this->cache_key);

        if (false !== $release) {
            return $release;
        }

        $url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['tag_name'])) {
            return false;
        }

        // Find the plugin ZIP in release assets
        $download_url = '';
        if (!empty($body['assets'])) {
            foreach ($body['assets'] as $asset) {
                if (
                    isset($asset['content_type']) &&
                    'application/zip' === $asset['content_type'] &&
                    preg_match('/\.zip$/', $asset['name'])
                ) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback to zipball if no asset found
        if (empty($download_url) && !empty($body['zipball_url'])) {
            $download_url = $body['zipball_url'];
        }

        if (empty($download_url)) {
            return false;
        }

        $release = array(
            'version'      => ltrim($body['tag_name'], 'vV'),
            'download_url' => $download_url,
            'changelog'    => isset($body['body']) ? $body['body'] : '',
            'published_at' => isset($body['published_at']) ? $body['published_at'] : '',
            'html_url'     => isset($body['html_url']) ? $body['html_url'] : '',
        );

        set_transient($this->cache_key, $release, $this->cache_ttl);

        return $release;
    }

    /**
     * Inject update info into WordPress update transient.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_release_info();

        if (false === $release) {
            return $transient;
        }

        if (version_compare(MDH_VERSION, $release['version'], '<')) {
            $plugin_data = array(
                'slug'        => $this->plugin_slug,
                'plugin'      => MDH_PLUGIN_BASENAME,
                'new_version' => $release['version'],
                'url'         => 'https://github.com/' . $this->github_repo,
                'package'     => $release['download_url'],
                'icons'       => array(),
                'banners'     => array(),
                'tested'      => '',
                'requires'    => '5.0',
                'requires_php' => '7.2',
            );

            $transient->response[MDH_PLUGIN_BASENAME] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Provide plugin details for the "View Details" modal.
     */
    public function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (!isset($args->slug) || $this->plugin_slug !== $args->slug) {
            return $result;
        }

        $release = $this->get_release_info();

        if (false === $release) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = 'Meta Description Handler';
        $info->slug          = $this->plugin_slug;
        $info->version       = $release['version'];
        $info->author        = '<a href="https://specodit.pl">Spec od IT</a>';
        $info->homepage      = 'https://github.com/' . $this->github_repo;
        $info->requires      = '5.0';
        $info->requires_php  = '7.2';
        $info->downloaded    = 0;
        $info->last_updated  = $release['published_at'];
        $info->download_link = $release['download_url'];

        $info->sections = array(
            'description' => 'A comprehensive plugin for managing meta titles and meta descriptions for all pages, posts, custom post types, taxonomies, and archives.',
            'changelog'   => $this->parse_changelog($release['changelog']),
        );

        return $info;
    }

    /**
     * After install, move the plugin to the correct directory.
     *
     * GitHub release ZIPs extract to a folder that already matches
     * the plugin slug, but this ensures it works in edge cases.
     */
    public function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || MDH_PLUGIN_BASENAME !== $hook_extra['plugin']) {
            return $result;
        }

        global $wp_filesystem;

        if (!$wp_filesystem) {
            return $result;
        }

        $proper_destination = WP_PLUGIN_DIR . '/' . dirname(MDH_PLUGIN_BASENAME);
        $install_directory  = $result['destination'];

        // If already in the right place, nothing to do
        if (trailingslashit($install_directory) === trailingslashit($proper_destination)) {
            return $result;
        }

        $moved = $wp_filesystem->move($install_directory, $proper_destination);

        if (!$moved) {
            return $result;
        }

        $result['destination'] = $proper_destination;

        // Re-activate the plugin
        activate_plugin(MDH_PLUGIN_BASENAME);

        return $result;
    }

    /**
     * Convert markdown changelog to basic HTML.
     */
    private function parse_changelog($markdown) {
        if (empty($markdown)) {
            return '<p>No changelog available.</p>';
        }

        $html = '';
        $in_list = false;

        foreach (explode("\n", $markdown) as $line) {
            $line = trim($line);

            if (empty($line)) {
                if ($in_list) { $html .= '</ul>'; $in_list = false; }
                continue;
            }

            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
                if ($in_list) { $html .= '</ul>'; $in_list = false; }
                $html .= '<h4>' . esc_html($m[1]) . '</h4>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                if (!$in_list) { $html .= '<ul>'; $in_list = true; }
                $html .= '<li>' . esc_html($m[1]) . '</li>';
                continue;
            }

            if ($in_list) { $html .= '</ul>'; $in_list = false; }
            $html .= '<p>' . esc_html($line) . '</p>';
        }

        if ($in_list) { $html .= '</ul>'; }

        return $html;
    }

    /**
     * Clear cached release data.
     */
    public static function clear_cache() {
        delete_transient('mdh_github_release');
    }
}
