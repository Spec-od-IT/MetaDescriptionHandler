<?php
/**
 * Plugin Name: Meta Description Handler
 * Plugin URI: https://github.com/Spec-od-IT/MetaDescriptionHandler
 * Description: A comprehensive plugin for managing meta titles and meta descriptions for all pages, posts, custom post types, taxonomies, and archives.
 * Version: 1.1.0
 * Author: Spec od IT
 * Author URI: https://specodit.pl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: meta-description-handler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MDH_VERSION', '1.1.0');
define('MDH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MDH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class MetaDescriptionHandler {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-helpers.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-resolver.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-admin.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-post-meta.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-taxonomy-meta.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-frontend.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-bulk-editor.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-schema.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-sitemap.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-updater.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-headless.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-import.php';
        require_once MDH_PLUGIN_DIR . 'includes/class-mdh-cli.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'meta-description-handler',
            false,
            dirname(MDH_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Admin
        if (is_admin()) {
            MDH_Admin::get_instance();
            MDH_Post_Meta::get_instance();
            MDH_Taxonomy_Meta::get_instance();
            MDH_Bulk_Editor::get_instance();
            MDH_Updater::get_instance();
        }
        
        // Frontend
        MDH_Frontend::get_instance();

        // Schema.org structured data (frontend only)
        if (!is_admin()) {
            MDH_Schema::get_instance();
        }

        // Sitemap control (always active — filters WP built-in sitemap)
        MDH_Sitemap::get_instance();

        // Headless: WPGraphQL + REST (rejestruje się tylko, gdy dane API jest dostępne)
        MDH_Headless::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options with Polish language defaults
        $default_options = array(
            'title_separator' => '|',
            'homepage_title' => '',
            'homepage_description' => '',
            'default_post_title_format' => '%post_title% %separator% %site_title%',
            'default_page_title_format' => '%post_title% %separator% %site_title%',
            'default_archive_title_format' => '%archive_title% %separator% %site_title%',
            'default_taxonomy_title_format' => '%term_title% %separator% %site_title%',
            'default_search_title_format' => 'Wyniki wyszukiwania dla "%search_query%" %separator% %site_title%',
            'default_404_title' => 'Strona nie została znaleziona %separator% %site_title%',
            'default_404_description' => 'Przepraszamy, strona której szukasz nie istnieje lub została przeniesiona.',
            'archive_settings' => array(
                'yearly' => array(
                    'title_format' => 'Archiwum: %archive_title% %separator% %site_title%',
                    'description' => 'Przeglądaj wszystkie wpisy z roku %archive_title%.',
                ),
                'monthly' => array(
                    'title_format' => 'Archiwum: %archive_title% %separator% %site_title%',
                    'description' => 'Przeglądaj wszystkie wpisy z miesiąca %archive_title%.',
                ),
                'daily' => array(
                    'title_format' => 'Archiwum: %archive_title% %separator% %site_title%',
                    'description' => 'Przeglądaj wszystkie wpisy z dnia %archive_title%.',
                ),
                'author' => array(
                    'title_format' => 'Wpisy autora %author_name% %separator% %site_title%',
                    'description' => 'Wszystkie artykuły napisane przez %author_name%.',
                ),
            ),
            'enabled_post_types' => array('post', 'page'),
            'enabled_taxonomies' => array('category', 'post_tag'),
            'force_frontend_output' => false,
            'sitemap_enabled' => true,
            'default_og_image' => '',
            'default_og_image_id' => 0,
            'twitter_site' => '',
            'post_type_settings' => array(
                'post' => array(
                    'title_format' => '%post_title% %separator% %site_title%',
                    'default_description' => '',
                ),
                'page' => array(
                    'title_format' => '%post_title% %separator% %site_title%',
                    'default_description' => '',
                ),
            ),
            'taxonomy_settings' => array(
                'category' => array(
                    'title_format' => 'Kategoria: %term_title% %separator% %site_title%',
                    'default_description' => 'Przeglądaj wszystkie wpisy z kategorii %term_title%.',
                ),
                'post_tag' => array(
                    'title_format' => 'Tag: %term_title% %separator% %site_title%',
                    'default_description' => 'Przeglądaj wszystkie wpisy oznaczone tagiem %term_title%.',
                ),
            ),
        );
        
        if (!get_option('mdh_settings')) {
            add_option('mdh_settings', $default_options);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        MDH_Updater::clear_cache();
    }
}

/**
 * Initialize the plugin
 */
function mdh_init() {
    return MetaDescriptionHandler::get_instance();
}

// Start the plugin
mdh_init();
