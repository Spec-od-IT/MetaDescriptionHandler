<?php
/**
 * Context-Free Meta Resolver
 *
 * Rozwiązuje tytuł i opis dla konkretnego wpisu lub terminu BEZ udziału głównego zapytania
 * WordPressa. Klasa MDH_Frontend potrafi to robić tylko dla „bieżącej" strony (is_singular(),
 * global $post), więc jest bezużyteczna poza pętlą — a tego właśnie potrzebują GraphQL, REST
 * i WP-CLI. Ta klasa jest jedynym źródłem prawdy: MDH_Frontend też z niej korzysta.
 *
 * @package MetaDescriptionHandler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Resolver {

    /**
     * Meta title for a single post/page/CPT.
     *
     * Kolejność: własne pole redaktora → szablon z ustawień typu treści → domyślny szablon.
     * Wynik nie przechodzi przez filtry — te zakłada strona wywołująca, żeby nie zadziałały dwa razy.
     *
     * @param int $post_id Post ID.
     * @return string Resolved title (may be empty when the post does not exist).
     */
    public static function post_title($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        $title = get_post_meta($post->ID, '_mdh_meta_title', true);
        if (!empty($title)) {
            return $title;
        }

        $settings = MDH_Helpers::get_settings();
        $pt_settings = isset($settings['post_type_settings'][$post->post_type])
            ? $settings['post_type_settings'][$post->post_type]
            : array();

        $format = !empty($pt_settings['title_format'])
            ? $pt_settings['title_format']
            : '%post_title% %separator% %site_title%';

        return MDH_Helpers::parse_template($format, array(
            'post_title' => get_the_title($post),
            'post_excerpt' => get_the_excerpt($post),
        ));
    }

    /**
     * Meta description for a single post/page/CPT.
     *
     * Kolejność: własne pole redaktora → szablon z ustawień typu treści → zajawka → treść.
     * Zawsze przycięte do 160 znaków (tak samo jak wyjście frontowe).
     *
     * @param int $post_id Post ID.
     * @return string Resolved description (may be empty).
     */
    public static function post_description($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        $description = get_post_meta($post->ID, '_mdh_meta_description', true);

        if (empty($description)) {
            $settings = MDH_Helpers::get_settings();
            $pt_settings = isset($settings['post_type_settings'][$post->post_type])
                ? $settings['post_type_settings'][$post->post_type]
                : array();

            if (!empty($pt_settings['default_description'])) {
                $description = MDH_Helpers::parse_template($pt_settings['default_description'], array(
                    'post_title' => get_the_title($post),
                    'post_excerpt' => get_the_excerpt($post),
                ));
            } elseif (self::autogenerate_enabled()) {
                // Zajawka albo początek treści. Świadomie wyłączalne: pusty opis bywa lepszy
                // niż sklejony z pierwszego akapitu — wyszukiwarka dobierze wtedy własny
                // fragment pasujący do zapytania. Tak samo zachowuje się Yoast z pustym
                // szablonem opisu, więc migracja z niego niczego nie psuje.
                $description = has_excerpt($post)
                    ? get_the_excerpt($post)
                    : self::plain_text($post->post_content);
            }
        }

        if (empty($description)) {
            return '';
        }

        return MDH_Helpers::truncate($description, 160, '');
    }

    /**
     * Whether descriptions may be generated from the excerpt or content.
     *
     * @return bool
     */
    private static function autogenerate_enabled() {
        $settings = MDH_Helpers::get_settings();

        // Domyślnie włączone — brak klucza oznacza instalację sprzed tej opcji.
        return !isset($settings['autogenerate_description']) || (bool) $settings['autogenerate_description'];
    }

    /**
     * Turn post content into something usable as a meta description.
     *
     * Same `wp_strip_all_tags()` nie wystarcza: w treści zostają shortcode'y, encje HTML
     * (`&nbsp;`) i adresy plików wstawione przez Elementora — wszystko to trafiłoby
     * dosłownie do znacznika meta.
     *
     * @param string $content Raw post content.
     * @return string
     */
    private static function plain_text($content) {
        $content = strip_shortcodes((string) $content);
        $content = wp_strip_all_tags($content);
        $content = wp_specialchars_decode($content, ENT_QUOTES);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = preg_replace('#https?://\S+#u', '', $content);

        return trim(preg_replace('/\s+/u', ' ', $content));
    }

    /**
     * Robots directives stored on a post.
     *
     * @param int $post_id Post ID.
     * @return array{noindex: bool, nofollow: bool}
     */
    public static function post_robots($post_id) {
        return array(
            'noindex' => (bool) get_post_meta($post_id, '_mdh_robots_noindex', true),
            'nofollow' => (bool) get_post_meta($post_id, '_mdh_robots_nofollow', true),
        );
    }

    /**
     * Open Graph image chosen for a post (own field, then featured image).
     *
     * @param int $post_id Post ID.
     * @return string Image URL or empty string.
     */
    public static function post_og_image($post_id) {
        $image = get_post_meta($post_id, '_mdh_og_image', true);
        if (!empty($image)) {
            return $image;
        }

        if (has_post_thumbnail($post_id)) {
            $thumbnail = get_the_post_thumbnail_url($post_id, 'full');
            if ($thumbnail) {
                return $thumbnail;
            }
        }

        return '';
    }

    /**
     * Meta title for a taxonomy term.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug (resolved from the term when omitted).
     * @return string Resolved title (may be empty when the term does not exist).
     */
    public static function term_title($term_id, $taxonomy = '') {
        $term = self::get_term($term_id, $taxonomy);
        if (!$term) {
            return '';
        }

        $title = get_term_meta($term->term_id, '_mdh_meta_title', true);
        if (!empty($title)) {
            return $title;
        }

        $settings = MDH_Helpers::get_settings();
        $tax_settings = isset($settings['taxonomy_settings'][$term->taxonomy])
            ? $settings['taxonomy_settings'][$term->taxonomy]
            : array();

        $format = !empty($tax_settings['title_format'])
            ? $tax_settings['title_format']
            : '%term_title% %separator% %site_title%';

        return MDH_Helpers::parse_template($format, array(
            'term_title' => $term->name,
            'term_description' => $term->description,
        ));
    }

    /**
     * Meta description for a taxonomy term.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug (resolved from the term when omitted).
     * @return string Resolved description (may be empty).
     */
    public static function term_description($term_id, $taxonomy = '') {
        $term = self::get_term($term_id, $taxonomy);
        if (!$term) {
            return '';
        }

        $description = get_term_meta($term->term_id, '_mdh_meta_description', true);

        if (empty($description)) {
            $settings = MDH_Helpers::get_settings();
            $tax_settings = isset($settings['taxonomy_settings'][$term->taxonomy])
                ? $settings['taxonomy_settings'][$term->taxonomy]
                : array();

            if (!empty($tax_settings['default_description'])) {
                $description = MDH_Helpers::parse_template($tax_settings['default_description'], array(
                    'term_title' => $term->name,
                    'term_description' => $term->description,
                ));
            } else {
                $description = $term->description;
            }
        }

        if (empty($description)) {
            return '';
        }

        return MDH_Helpers::truncate($description, 160, '');
    }

    /**
     * Everything a headless front end needs about one post, in a single call.
     *
     * @param int $post_id Post ID.
     * @return array{title: string, description: string, noindex: bool, nofollow: bool, ogImage: string}
     */
    public static function post_payload($post_id) {
        $robots = self::post_robots($post_id);

        return array(
            'title' => self::post_title($post_id),
            'description' => self::post_description($post_id),
            'noindex' => $robots['noindex'],
            'nofollow' => $robots['nofollow'],
            'ogImage' => self::post_og_image($post_id),
        );
    }

    /**
     * Fetch a term by ID, tolerating a missing taxonomy argument.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug or empty string.
     * @return WP_Term|null
     */
    private static function get_term($term_id, $taxonomy = '') {
        $term = '' === $taxonomy ? get_term($term_id) : get_term($term_id, $taxonomy);

        if (!$term || is_wp_error($term)) {
            return null;
        }

        return $term;
    }
}
