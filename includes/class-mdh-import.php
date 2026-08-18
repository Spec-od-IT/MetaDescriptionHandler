<?php
/**
 * Import from Yoast SEO
 *
 * Przenosi tytuły, opisy, ustawienia robots i obrazki OG z Yoasta do pól MDH. Yoast trzyma
 * w tekście własne zmienne (`%%sep%%`, `%%sitename%%`), więc samo przepisanie wartości nie
 * wystarczy — trzeba je przetłumaczyć na składnię MDH (`%separator%`, `%site_title%`).
 * Zmiennych, których MDH nie zna, nie zostawiamy w treści (wypisałyby się dosłownie
 * w tytule) — wycinamy je i raportujemy, żeby było widać, co wymaga ręcznej poprawki.
 *
 * @package MetaDescriptionHandler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Import {

    /**
     * Yoast post meta keys → MDH post meta keys.
     */
    const MAP = array(
        '_yoast_wpseo_title' => '_mdh_meta_title',
        '_yoast_wpseo_metadesc' => '_mdh_meta_description',
        '_yoast_wpseo_opengraph-image' => '_mdh_og_image',
    );

    /**
     * Yoast replacement variables → MDH template placeholders.
     *
     * @return array
     */
    public static function token_map() {
        return array(
            '%%title%%' => '%post_title%',
            '%%sitename%%' => '%site_title%',
            '%%sitedesc%%' => '%site_description%',
            '%%sep%%' => '%separator%',
            '%%excerpt%%' => '%post_excerpt%',
            '%%excerpt_only%%' => '%post_excerpt%',
            '%%currentdate%%' => '%current_date%',
            '%%currentyear%%' => '%current_year%',
            '%%searchphrase%%' => '%search_query%',
            '%%name%%' => '%author_name%',
            '%%term_title%%' => '%term_title%',
            '%%term_description%%' => '%term_description%',
            '%%category_title%%' => '%term_title%',
            '%%archive_title%%' => '%archive_title%',
        );
    }

    /**
     * Yoast variables that are deliberately dropped instead of translated.
     *
     * `%%page%%` i pokrewne Yoast rozwija do pustego łańcucha na stronie pojedynczego wpisu —
     * numer strony ma sens tylko w archiwach, a te w MDH biorą format z ustawień, nie z pola
     * przy wpisie. Przetłumaczenie ich na `%page_number%` zostawiłoby w tytule dosłowny
     * placeholder, bo nikt nie poda dla niego wartości.
     *
     * @return array
     */
    public static function drop_tokens() {
        return array('%%page%%', '%%pagenumber%%', '%%pagetotal%%');
    }

    /**
     * Translate Yoast variables into MDH placeholders.
     *
     * @param string $value    Raw Yoast value.
     * @param array  $unknown  Collects Yoast variables MDH has no equivalent for (by reference).
     * @return string
     */
    public static function convert_tokens($value, &$unknown = array()) {
        if (!is_string($value) || '' === $value) {
            return '';
        }

        $value = str_replace(self::drop_tokens(), '', $value);

        $map = self::token_map();
        $value = str_replace(array_keys($map), array_values($map), $value);

        // Cokolwiek zostało w składni %%…%% jest zmienną Yoasta bez odpowiednika w MDH.
        if (preg_match_all('/%%[^%]+%%/', $value, $matches)) {
            foreach ($matches[0] as $token) {
                $unknown[] = $token;
            }
            $value = str_replace($matches[0], '', $value);
        }

        // Po wycięciu zmiennej zostają podwójne spacje i osierocone separatory.
        $value = preg_replace('/\s{2,}/u', ' ', $value);

        return trim($value);
    }

    /**
     * Run the import.
     *
     * @param array $args {
     *     @type bool  $dry_run    Only report, do not write. Default false.
     *     @type bool  $overwrite  Overwrite MDH fields that already have a value. Default false.
     *     @type array $post_types Post types to process. Default: types enabled in MDH settings.
     *     @type bool  $terms      Also import taxonomy meta. Default true.
     * }
     * @return array Report with counts and per-item changes.
     */
    public static function run($args = array()) {
        $defaults = array(
            'dry_run' => false,
            'overwrite' => false,
            'post_types' => null,
            'terms' => true,
        );
        $args = array_merge($defaults, $args);

        $settings = MDH_Helpers::get_settings();
        if (null === $args['post_types']) {
            $args['post_types'] = isset($settings['enabled_post_types'])
                ? $settings['enabled_post_types']
                : array('post', 'page');
        }

        $report = array(
            'dry_run' => (bool) $args['dry_run'],
            'posts_scanned' => 0,
            'posts_changed' => 0,
            'terms_scanned' => 0,
            'terms_changed' => 0,
            'skipped_existing' => 0,
            'unknown_tokens' => array(),
            'changes' => array(),
        );

        self::import_posts($args, $report);

        if ($args['terms']) {
            self::import_terms($args, $report);
        }

        $report['unknown_tokens'] = array_values(array_unique($report['unknown_tokens']));

        return $report;
    }

    /**
     * Copy Yoast post meta into MDH post meta.
     *
     * @param array $args   Normalised arguments.
     * @param array $report Report (by reference).
     */
    private static function import_posts($args, &$report) {
        $query = new WP_Query(array(
            'post_type' => $args['post_types'],
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
        ));

        foreach ($query->posts as $post_id) {
            $report['posts_scanned']++;
            $changed = array();
            $item_unknown = array();

            foreach (self::MAP as $yoast_key => $mdh_key) {
                $raw = get_post_meta($post_id, $yoast_key, true);
                if (!is_string($raw) || '' === trim($raw)) {
                    continue;
                }

                $existing = get_post_meta($post_id, $mdh_key, true);
                if (!empty($existing) && !$args['overwrite']) {
                    $report['skipped_existing']++;
                    continue;
                }

                $unknown = array();
                $value = '_mdh_og_image' === $mdh_key
                    ? esc_url_raw($raw)
                    : self::convert_tokens($raw, $unknown);

                if ('' === $value || $value === $existing) {
                    continue;
                }

                $report['unknown_tokens'] = array_merge($report['unknown_tokens'], $unknown);
                $item_unknown = array_merge($item_unknown, $unknown);
                $changed[$mdh_key] = $value;

                if (!$args['dry_run']) {
                    update_post_meta($post_id, $mdh_key, $value);
                }
            }

            $robots = self::import_post_robots($post_id, $args);
            $changed = array_merge($changed, $robots);

            if (!empty($changed)) {
                $report['posts_changed']++;
                $report['changes'][] = array(
                    'type' => 'post',
                    'id' => $post_id,
                    'label' => get_the_title($post_id),
                    'fields' => $changed,
                    'unknown' => array_values(array_unique($item_unknown)),
                );
            }
        }

        wp_reset_postdata();
    }

    /**
     * Translate Yoast robots settings into MDH checkboxes.
     *
     * Yoast trzyma noindex jako '1' (noindex) / '2' (index) / '' (domyślne) — przenosimy
     * wyłącznie jawne '1', żeby nie ustawiać niczego tam, gdzie redaktor niczego nie wybrał.
     *
     * @param int   $post_id Post ID.
     * @param array $args    Normalised arguments.
     * @return array Changed fields.
     */
    private static function import_post_robots($post_id, $args) {
        $changed = array();
        $flags = array(
            '_yoast_wpseo_meta-robots-noindex' => '_mdh_robots_noindex',
            '_yoast_wpseo_meta-robots-nofollow' => '_mdh_robots_nofollow',
        );

        foreach ($flags as $yoast_key => $mdh_key) {
            if ('1' !== (string) get_post_meta($post_id, $yoast_key, true)) {
                continue;
            }
            if (get_post_meta($post_id, $mdh_key, true)) {
                continue;
            }

            $changed[$mdh_key] = '1';

            if (!$args['dry_run']) {
                update_post_meta($post_id, $mdh_key, '1');
            }
        }

        return $changed;
    }

    /**
     * Copy Yoast taxonomy meta (single `wpseo_taxonomy_meta` option) into term meta.
     *
     * @param array $args   Normalised arguments.
     * @param array $report Report (by reference).
     */
    private static function import_terms($args, &$report) {
        $yoast_terms = get_option('wpseo_taxonomy_meta', array());
        if (!is_array($yoast_terms) || empty($yoast_terms)) {
            return;
        }

        $fields = array(
            'wpseo_title' => '_mdh_meta_title',
            'wpseo_desc' => '_mdh_meta_description',
        );

        foreach ($yoast_terms as $taxonomy => $terms) {
            if (!is_array($terms)) {
                continue;
            }

            foreach ($terms as $term_id => $meta) {
                if (!is_array($meta)) {
                    continue;
                }

                $term = get_term((int) $term_id, $taxonomy);
                if (!$term || is_wp_error($term)) {
                    continue;
                }

                $report['terms_scanned']++;
                $changed = array();
                $item_unknown = array();

                foreach ($fields as $yoast_key => $mdh_key) {
                    if (empty($meta[$yoast_key])) {
                        continue;
                    }

                    $existing = get_term_meta($term->term_id, $mdh_key, true);
                    if (!empty($existing) && !$args['overwrite']) {
                        $report['skipped_existing']++;
                        continue;
                    }

                    $unknown = array();
                    $value = self::convert_tokens($meta[$yoast_key], $unknown);

                    if ('' === $value || $value === $existing) {
                        continue;
                    }

                    $report['unknown_tokens'] = array_merge($report['unknown_tokens'], $unknown);
                    $item_unknown = array_merge($item_unknown, $unknown);
                    $changed[$mdh_key] = $value;

                    if (!$args['dry_run']) {
                        update_term_meta($term->term_id, $mdh_key, $value);
                    }
                }

                if (isset($meta['wpseo_noindex']) && 'noindex' === $meta['wpseo_noindex']
                    && !get_term_meta($term->term_id, '_mdh_robots_noindex', true)) {
                    $changed['_mdh_robots_noindex'] = '1';

                    if (!$args['dry_run']) {
                        update_term_meta($term->term_id, '_mdh_robots_noindex', '1');
                    }
                }

                if (!empty($changed)) {
                    $report['terms_changed']++;
                    $report['changes'][] = array(
                        'type' => 'term',
                        'id' => $term->term_id,
                        'label' => $term->name,
                        'fields' => $changed,
                        'unknown' => array_values(array_unique($item_unknown)),
                    );
                }
            }
        }
    }
}
