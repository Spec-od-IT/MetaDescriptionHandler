<?php
/**
 * Headless API Exposure (WPGraphQL + REST)
 *
 * Bez tej klasy wtyczka jest bezużyteczna w architekturze headless: redaktor wypełnia pola,
 * ale front (Astro, Next, cokolwiek) nie ma skąd ich przeczytać — MDH wypisuje meta wyłącznie
 * przez wp_head, którego taki front nigdy nie renderuje.
 *
 * Rejestrujemy tylko wtedy, gdy odpowiednie API jest dostępne: WPGraphQL wykrywamy po akcji
 * `graphql_register_types` (nie odpali się, gdy wtyczki nie ma), REST po `rest_api_init`.
 *
 * @package MetaDescriptionHandler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Headless {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Nazwa typu obiektowego w GraphQL-u.
     */
    const GRAPHQL_TYPE = 'MdhSeo';

    /**
     * Nazwa pola w GraphQL-u i w REST.
     */
    const GRAPHQL_FIELD = 'mdhSeo';
    const REST_FIELD = 'mdh_seo';

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
        add_action('graphql_register_types', array($this, 'register_graphql'));
        add_action('rest_api_init', array($this, 'register_rest'));
    }

    /**
     * Typy treści, dla których redaktor ma pola MDH.
     *
     * @return array
     */
    private function enabled_post_types() {
        $settings = MDH_Helpers::get_settings();
        $enabled = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array('post', 'page');

        return is_array($enabled) ? $enabled : array();
    }

    /**
     * Taksonomie, dla których redaktor ma pola MDH.
     *
     * @return array
     */
    private function enabled_taxonomies() {
        $settings = MDH_Helpers::get_settings();
        $enabled = isset($settings['enabled_taxonomies']) ? $settings['enabled_taxonomies'] : array('category', 'post_tag');

        return is_array($enabled) ? $enabled : array();
    }

    /**
     * Pusty komplet danych — GraphQL woli komplet nulli niż brak obiektu.
     *
     * @return array
     */
    private function empty_payload() {
        return array(
            'title' => '',
            'description' => '',
            'noindex' => false,
            'nofollow' => false,
            'ogImage' => '',
        );
    }

    /**
     * Register the GraphQL object type and attach it to every enabled post type / taxonomy.
     *
     * Rejestrujemy per typ, a nie na interfejsie ContentNode — dzięki temu pole pojawia się
     * dokładnie tam, gdzie redaktor faktycznie ma co wypełnić, i nie zależymy od tego, jak
     * dana wersja WPGraphQL-a propaguje pola z interfejsów.
     */
    public function register_graphql() {
        if (!function_exists('register_graphql_object_type') || !function_exists('register_graphql_field')) {
            return;
        }

        register_graphql_object_type(self::GRAPHQL_TYPE, array(
            'description' => __('Meta title and description resolved by Meta Description Handler.', 'meta-description-handler'),
            'fields' => array(
                'title' => array(
                    'type' => 'String',
                    'description' => __('Ready-to-use document title (own field or post type template).', 'meta-description-handler'),
                ),
                'description' => array(
                    'type' => 'String',
                    'description' => __('Ready-to-use meta description, truncated to 160 characters.', 'meta-description-handler'),
                ),
                'noindex' => array(
                    'type' => 'Boolean',
                    'description' => __('Whether this content is marked as noindex.', 'meta-description-handler'),
                ),
                'nofollow' => array(
                    'type' => 'Boolean',
                    'description' => __('Whether this content is marked as nofollow.', 'meta-description-handler'),
                ),
                'ogImage' => array(
                    'type' => 'String',
                    'description' => __('Open Graph image URL (own field or featured image).', 'meta-description-handler'),
                ),
            ),
        ));

        foreach ($this->enabled_post_types() as $post_type) {
            $type_name = $this->graphql_type_name(get_post_type_object($post_type));
            if ('' === $type_name) {
                continue;
            }

            register_graphql_field($type_name, self::GRAPHQL_FIELD, array(
                'type' => self::GRAPHQL_TYPE,
                'description' => __('Meta title and description from Meta Description Handler.', 'meta-description-handler'),
                'resolve' => array($this, 'resolve_post_field'),
            ));
        }

        foreach ($this->enabled_taxonomies() as $taxonomy) {
            $type_name = $this->graphql_type_name(get_taxonomy($taxonomy));
            if ('' === $type_name) {
                continue;
            }

            register_graphql_field($type_name, self::GRAPHQL_FIELD, array(
                'type' => self::GRAPHQL_TYPE,
                'description' => __('Meta title and description from Meta Description Handler.', 'meta-description-handler'),
                'resolve' => array($this, 'resolve_term_field'),
            ));
        }
    }

    /**
     * GraphQL type name for a post type / taxonomy object, or '' when it is not exposed.
     *
     * @param object|null $object Post type or taxonomy object.
     * @return string
     */
    private function graphql_type_name($object) {
        if (!$object || empty($object->show_in_graphql) || empty($object->graphql_single_name)) {
            return '';
        }

        return ucfirst($object->graphql_single_name);
    }

    /**
     * Resolver for post-like GraphQL types.
     *
     * @param mixed $source WPGraphQL post model.
     * @return array
     */
    public function resolve_post_field($source) {
        $post_id = isset($source->databaseId) ? (int) $source->databaseId : 0;
        if (!$post_id) {
            return $this->empty_payload();
        }

        return MDH_Resolver::post_payload($post_id);
    }

    /**
     * Resolver for term GraphQL types.
     *
     * @param mixed $source WPGraphQL term model.
     * @return array
     */
    public function resolve_term_field($source) {
        $term_id = isset($source->databaseId) ? (int) $source->databaseId : 0;
        if (!$term_id) {
            return $this->empty_payload();
        }

        $taxonomy = isset($source->taxonomyName) ? $source->taxonomyName : '';

        return array(
            'title' => MDH_Resolver::term_title($term_id, $taxonomy),
            'description' => MDH_Resolver::term_description($term_id, $taxonomy),
            'noindex' => (bool) get_term_meta($term_id, '_mdh_robots_noindex', true),
            'nofollow' => false,
            'ogImage' => '',
        );
    }

    /**
     * Expose the same payload through the REST API (`mdh_seo` on each enabled post type).
     */
    public function register_rest() {
        if (!function_exists('register_rest_field')) {
            return;
        }

        foreach ($this->enabled_post_types() as $post_type) {
            $post_type_object = get_post_type_object($post_type);
            if (!$post_type_object || empty($post_type_object->show_in_rest)) {
                continue;
            }

            register_rest_field($post_type, self::REST_FIELD, array(
                'get_callback' => array($this, 'resolve_rest_field'),
                'schema' => array(
                    'description' => __('Meta title and description from Meta Description Handler.', 'meta-description-handler'),
                    'type' => 'object',
                    'context' => array('view', 'edit'),
                ),
            ));
        }
    }

    /**
     * REST callback.
     *
     * @param array $post Prepared post array.
     * @return array
     */
    public function resolve_rest_field($post) {
        $post_id = isset($post['id']) ? (int) $post['id'] : 0;
        if (!$post_id) {
            return $this->empty_payload();
        }

        return MDH_Resolver::post_payload($post_id);
    }
}
