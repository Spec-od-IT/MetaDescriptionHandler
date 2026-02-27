<?php
/**
 * Sitemap Control Class
 *
 * Filters WordPress 5.5+ built-in sitemap to exclude noindex content
 * and respect plugin's enabled post types/taxonomies settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Sitemap {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $settings = MDH_Helpers::get_settings();

        // Allow completely disabling the WP sitemap
        if (empty($settings['sitemap_enabled'])) {
            add_filter('wp_sitemaps_enabled', '__return_false');
            return;
        }

        // Filter post types shown in sitemap
        add_filter('wp_sitemaps_post_types', array($this, 'filter_post_types'));

        // Filter taxonomies shown in sitemap
        add_filter('wp_sitemaps_taxonomies', array($this, 'filter_taxonomies'));

        // Exclude noindex posts from sitemap
        add_filter('wp_sitemaps_posts_query_args', array($this, 'exclude_noindex_posts'), 10, 2);

        // Exclude noindex terms from sitemap
        add_filter('wp_sitemaps_taxonomies_query_args', array($this, 'exclude_noindex_terms'), 10, 2);
    }

    /**
     * Only show enabled post types in sitemap
     */
    public function filter_post_types($post_types) {
        $settings = MDH_Helpers::get_settings();
        $enabled = $settings['enabled_post_types'] ?? array('post', 'page');

        return array_intersect_key($post_types, array_flip($enabled));
    }

    /**
     * Only show enabled taxonomies in sitemap
     */
    public function filter_taxonomies($taxonomies) {
        $settings = MDH_Helpers::get_settings();
        $enabled = $settings['enabled_taxonomies'] ?? array('category', 'post_tag');

        return array_intersect_key($taxonomies, array_flip($enabled));
    }

    /**
     * Exclude posts marked as noindex from sitemap
     */
    public function exclude_noindex_posts($args, $post_type) {
        $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => '_mdh_robots_noindex',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_mdh_robots_noindex',
                'value'   => '1',
                'compare' => '!=',
            ),
        );

        return $args;
    }

    /**
     * Exclude terms marked as noindex from sitemap
     */
    public function exclude_noindex_terms($args, $taxonomy) {
        $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => '_mdh_robots_noindex',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_mdh_robots_noindex',
                'value'   => '1',
                'compare' => '!=',
            ),
        );

        return $args;
    }
}
