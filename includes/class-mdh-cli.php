<?php
/**
 * WP-CLI Commands
 *
 * Migracja i przegląd danych z wiersza poleceń. Import z Yoasta to operacja jednorazowa
 * na całej bazie — lepiej mieć ją jako polecenie z trybem próbnym niż jako przycisk
 * w panelu, który albo zdąży, albo padnie na limicie czasu PHP.
 *
 * @package MetaDescriptionHandler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class MDH_CLI {

    /**
     * Copies meta titles, descriptions, robots flags and OG images from Yoast SEO.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change without writing anything.
     *
     * [--overwrite]
     * : Overwrite MDH fields that already have a value. Off by default.
     *
     * [--post-types=<list>]
     * : Comma-separated post types. Defaults to the types enabled in MDH settings.
     *
     * [--skip-terms]
     * : Do not import taxonomy meta.
     *
     * [--verbose]
     * : Print every changed item, not just the summary.
     *
     * ## EXAMPLES
     *
     *     wp mdh import-yoast --dry-run --verbose
     *     wp mdh import-yoast
     *     wp mdh import-yoast --post-types=post,page,oferta-pracy --overwrite
     *
     * @subcommand import-yoast
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Flags.
     */
    public function import_yoast($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $verbose = isset($assoc_args['verbose']);

        $post_types = null;
        if (!empty($assoc_args['post-types'])) {
            $post_types = array_filter(array_map('trim', explode(',', $assoc_args['post-types'])));
        }

        $report = MDH_Import::run(array(
            'dry_run' => $dry_run,
            'overwrite' => isset($assoc_args['overwrite']),
            'post_types' => $post_types,
            'terms' => !isset($assoc_args['skip-terms']),
        ));

        if ($verbose) {
            foreach ($report['changes'] as $change) {
                WP_CLI::log(sprintf(
                    '[%s #%d] %s',
                    $change['type'],
                    $change['id'],
                    $change['label']
                ));
                foreach ($change['fields'] as $key => $value) {
                    WP_CLI::log('    ' . $key . ' = ' . $value);
                }
                if (!empty($change['unknown'])) {
                    WP_CLI::log('    ⚠ wycięte zmienne Yoasta: ' . implode(', ', $change['unknown']));
                }
            }
        }

        WP_CLI::log('');
        WP_CLI::log(sprintf('Wpisy przejrzane:   %d', $report['posts_scanned']));
        WP_CLI::log(sprintf('Wpisy zmienione:    %d', $report['posts_changed']));
        WP_CLI::log(sprintf('Terminy przejrzane: %d', $report['terms_scanned']));
        WP_CLI::log(sprintf('Terminy zmienione:  %d', $report['terms_changed']));
        WP_CLI::log(sprintf('Pominięte (pole już wypełnione): %d', $report['skipped_existing']));

        if (!empty($report['unknown_tokens'])) {
            WP_CLI::warning(sprintf(
                'Zmienne Yoasta bez odpowiednika w MDH — wycięte z treści, sprawdź te wpisy ręcznie: %s',
                implode(', ', $report['unknown_tokens'])
            ));
        }

        if ($dry_run) {
            WP_CLI::success('Próba (--dry-run) zakończona — nic nie zapisano.');
            return;
        }

        WP_CLI::success('Import z Yoasta zakończony.');
    }

    /**
     * Prints the resolved meta title and description for every published item.
     *
     * Przydatne do porównania „przed / po" migracji: wynik w formacie JSON można zestawić
     * z wcześniejszym zrzutem (np. crawlem starej strony) i zobaczyć, co się rozjechało.
     *
     * ## OPTIONS
     *
     * [--post-types=<list>]
     * : Comma-separated post types. Defaults to the types enabled in MDH settings.
     *
     * [--format=<format>]
     * : Output format: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp mdh list --format=json > mdh-po-migracji.json
     *
     * @subcommand list
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Flags.
     */
    public function list_meta($args, $assoc_args) {
        $settings = MDH_Helpers::get_settings();
        $post_types = !empty($assoc_args['post-types'])
            ? array_filter(array_map('trim', explode(',', $assoc_args['post-types'])))
            : (isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array('post', 'page'));

        $query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ));

        $rows = array();
        foreach ($query->posts as $post_id) {
            $rows[] = array(
                'url' => wp_make_link_relative(get_permalink($post_id)),
                'title' => MDH_Resolver::post_title($post_id),
                'description' => MDH_Resolver::post_description($post_id),
            );
        }

        wp_reset_postdata();

        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        WP_CLI\Utils\format_items($format, $rows, array('url', 'title', 'description'));
    }
}

WP_CLI::add_command('mdh', 'MDH_CLI');
