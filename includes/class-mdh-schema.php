<?php
/**
 * Schema.org JSON-LD Structured Data Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Schema {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Skip schema output if another SEO plugin handles it
        if ( MDH_Helpers::is_frontend_disabled() ) {
            return;
        }

        add_action('wp_head', array($this, 'output_schema'), 2);
    }

    /**
     * Output JSON-LD structured data based on current page type
     */
    public function output_schema() {
        $schemas = array();

        if (is_front_page() || is_home()) {
            $schemas[] = $this->get_website_schema();
            $schemas[] = $this->get_organization_schema();
        } elseif (is_singular('post')) {
            $schemas[] = $this->get_article_schema();
            $breadcrumb = $this->get_breadcrumb_schema();
            if ($breadcrumb) {
                $schemas[] = $breadcrumb;
            }
        } elseif (is_singular()) {
            $schemas[] = $this->get_webpage_schema();
            $breadcrumb = $this->get_breadcrumb_schema();
            if ($breadcrumb) {
                $schemas[] = $breadcrumb;
            }
        } elseif (is_category() || is_tag() || is_tax() || is_post_type_archive() || is_author() || is_date()) {
            $breadcrumb = $this->get_breadcrumb_schema();
            if ($breadcrumb) {
                $schemas[] = $breadcrumb;
            }
        } elseif (is_search() || is_404()) {
            $breadcrumb = $this->get_breadcrumb_schema();
            if ($breadcrumb) {
                $schemas[] = $breadcrumb;
            }
        }

        if (empty($schemas)) {
            return;
        }

        echo "\n<!-- Meta Description Handler - Schema.org JSON-LD -->\n";
        foreach ($schemas as $schema) {
            if (!empty($schema)) {
                echo '<script type="application/ld+json">' . "\n";
                echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                echo "\n" . '</script>' . "\n";
            }
        }
        echo "<!-- End Meta Description Handler - Schema.org -->\n\n";
    }

    /**
     * WebSite schema with SearchAction (sitelinks search box)
     */
    private function get_website_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => get_bloginfo('name'),
            'url'      => home_url('/'),
        );

        $description = get_bloginfo('description');
        if (!empty($description)) {
            $schema['description'] = $description;
        }

        $schema['potentialAction'] = array(
            '@type'       => 'SearchAction',
            'target'      => array(
                '@type'        => 'EntryPoint',
                'urlTemplate'  => home_url('/?s={search_term_string}'),
            ),
            'query-input' => 'required name=search_term_string',
        );

        return apply_filters('mdh_schema_website', $schema);
    }

    /**
     * Organization schema with logo from Customizer
     */
    private function get_organization_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => get_bloginfo('name'),
            'url'      => home_url('/'),
        );

        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $schema['logo'] = $logo_url;
            }
        }

        return apply_filters('mdh_schema_organization', $schema);
    }

    /**
     * BlogPosting schema for single posts
     */
    private function get_article_schema() {
        global $post;

        $frontend = MDH_Frontend::get_instance();

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => get_the_title(),
            'url'           => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified'  => get_the_modified_date('c'),
        );

        // Author
        $author_name = get_the_author();
        if (!empty($author_name)) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => get_author_posts_url(get_the_author_meta('ID')),
            );
        }

        // Image
        $image = $frontend->get_og_image();
        if (!empty($image)) {
            $schema['image'] = $image;
        }

        // Publisher
        $publisher = array(
            '@type' => 'Organization',
            'name'  => get_bloginfo('name'),
            'url'   => home_url('/'),
        );
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $publisher['logo'] = array(
                    '@type' => 'ImageObject',
                    'url'   => $logo_url,
                );
            }
        }
        $schema['publisher'] = $publisher;

        // Word count
        $content = get_post_field('post_content', $post->ID);
        if (!empty($content)) {
            $words = preg_split('/\s+/u', wp_strip_all_tags($content), -1, PREG_SPLIT_NO_EMPTY);
            $schema['wordCount'] = count($words);
        }

        // Description
        $description = $frontend->get_meta_description();
        if (!empty($description)) {
            $schema['description'] = $description;
        }

        return apply_filters('mdh_schema_article', $schema);
    }

    /**
     * WebPage schema for pages and custom post types
     */
    private function get_webpage_schema() {
        $frontend = MDH_Frontend::get_instance();

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'WebPage',
            'name'          => get_the_title(),
            'url'           => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified'  => get_the_modified_date('c'),
        );

        $description = $frontend->get_meta_description();
        if (!empty($description)) {
            $schema['description'] = $description;
        }

        $schema['isPartOf'] = array(
            '@type' => 'WebSite',
            'name'  => get_bloginfo('name'),
            'url'   => home_url('/'),
        );

        return apply_filters('mdh_schema_webpage', $schema);
    }

    /**
     * BreadcrumbList schema
     */
    private function get_breadcrumb_schema() {
        $items = $this->get_breadcrumb_items();

        if (count($items) < 2) {
            return null;
        }

        $list_items = array();
        foreach ($items as $position => $item) {
            $list_item = array(
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'],
            );
            if (!empty($item['url'])) {
                $list_item['item'] = $item['url'];
            }
            $list_items[] = $list_item;
        }

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list_items,
        );

        return apply_filters('mdh_schema_breadcrumb', $schema);
    }

    /**
     * Build breadcrumb items array based on current page type
     *
     * @return array Array of ['name' => ..., 'url' => ...]
     */
    private function get_breadcrumb_items() {
        $items = array();

        // Home is always first
        $items[] = array(
            'name' => get_bloginfo('name'),
            'url'  => home_url('/'),
        );

        if (is_singular('post')) {
            // Post: Home → Category → Post
            $categories = get_the_category();
            if (!empty($categories)) {
                // Pick primary category (first one)
                $cat = $categories[0];
                // Include parent categories
                $parents = $this->get_term_parents($cat);
                $items = array_merge($items, $parents);
                $items[] = array(
                    'name' => $cat->name,
                    'url'  => get_term_link($cat),
                );
            }
            $items[] = array(
                'name' => get_the_title(),
                'url'  => get_permalink(),
            );
        } elseif (is_singular()) {
            // Page/CPT: Home → Parents → Current
            global $post;
            $post_type = get_post_type();

            // For CPT, add archive link
            if (!in_array($post_type, array('post', 'page'), true)) {
                $pt_object = get_post_type_object($post_type);
                if ($pt_object && $pt_object->has_archive) {
                    $archive_url = get_post_type_archive_link($post_type);
                    if ($archive_url) {
                        $items[] = array(
                            'name' => $pt_object->labels->name,
                            'url'  => $archive_url,
                        );
                    }
                }
            }

            // Page ancestors
            if (is_page() && $post->post_parent) {
                $ancestors = array_reverse(get_post_ancestors($post->ID));
                foreach ($ancestors as $ancestor_id) {
                    $items[] = array(
                        'name' => get_the_title($ancestor_id),
                        'url'  => get_permalink($ancestor_id),
                    );
                }
            }

            $items[] = array(
                'name' => get_the_title(),
                'url'  => get_permalink(),
            );
        } elseif (is_category() || is_tag() || is_tax()) {
            // Taxonomy: Home → Parent terms → Term
            $term = get_queried_object();

            // For custom taxonomies, try to add CPT archive
            if (!in_array($term->taxonomy, array('category', 'post_tag'), true)) {
                $tax_object = get_taxonomy($term->taxonomy);
                if ($tax_object && !empty($tax_object->object_type)) {
                    $cpt = $tax_object->object_type[0];
                    $pt_object = get_post_type_object($cpt);
                    if ($pt_object && $pt_object->has_archive) {
                        $archive_url = get_post_type_archive_link($cpt);
                        if ($archive_url) {
                            $items[] = array(
                                'name' => $pt_object->labels->name,
                                'url'  => $archive_url,
                            );
                        }
                    }
                }
            }

            // Parent terms
            $parents = $this->get_term_parents($term);
            $items = array_merge($items, $parents);

            $items[] = array(
                'name' => $term->name,
                'url'  => get_term_link($term),
            );
        } elseif (is_post_type_archive()) {
            // CPT archive: Home → Archive
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            $pt_object = get_post_type_object($post_type);
            if ($pt_object) {
                $items[] = array(
                    'name' => $pt_object->labels->name,
                    'url'  => get_post_type_archive_link($post_type),
                );
            }
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $items[] = array(
                    'name' => $author->display_name,
                    'url'  => get_author_posts_url($author->ID),
                );
            }
        } elseif (is_day()) {
            // Date: Home → Year → Month → Day
            $items[] = array(
                'name' => get_the_date('Y'),
                'url'  => get_year_link(get_query_var('year')),
            );
            $items[] = array(
                'name' => get_the_date('F Y'),
                'url'  => get_month_link(get_query_var('year'), get_query_var('monthnum')),
            );
            $items[] = array(
                'name' => get_the_date(),
                'url'  => get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day')),
            );
        } elseif (is_month()) {
            // Date: Home → Year → Month
            $items[] = array(
                'name' => get_the_date('Y'),
                'url'  => get_year_link(get_query_var('year')),
            );
            $items[] = array(
                'name' => get_the_date('F Y'),
                'url'  => get_month_link(get_query_var('year'), get_query_var('monthnum')),
            );
        } elseif (is_year()) {
            // Date: Home → Year
            $items[] = array(
                'name' => get_the_date('Y'),
                'url'  => get_year_link(get_query_var('year')),
            );
        } elseif (is_search()) {
            $items[] = array(
                'name' => sprintf(__('Wyniki wyszukiwania: „%s"', 'meta-description-handler'), get_search_query()),
                'url'  => '',
            );
        } elseif (is_404()) {
            $items[] = array(
                'name' => '404',
                'url'  => '',
            );
        }

        return $items;
    }

    /**
     * Get parent terms for a given term (from top-most parent down)
     *
     * @param WP_Term $term
     * @return array
     */
    private function get_term_parents($term) {
        $parents = array();

        if ($term->parent === 0) {
            return $parents;
        }

        $ancestor_ids = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');
        $ancestor_ids = array_reverse($ancestor_ids);

        foreach ($ancestor_ids as $ancestor_id) {
            $ancestor = get_term($ancestor_id, $term->taxonomy);
            if ($ancestor && !is_wp_error($ancestor)) {
                $link = get_term_link($ancestor);
                $parents[] = array(
                    'name' => $ancestor->name,
                    'url'  => is_wp_error($link) ? '' : $link,
                );
            }
        }

        return $parents;
    }
}
