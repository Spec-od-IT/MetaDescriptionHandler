<?php
/**
 * Frontend Output Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Frontend {

    private static $instance = null;

    /**
     * Whether frontend output is active (not disabled by SEO conflict detection)
     */
    private $is_active = true;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check for SEO plugin conflicts before registering any frontend hooks
        if ( MDH_Helpers::is_frontend_disabled() ) {
            $this->is_active = false;
            return;
        }

        // Output meta tags in head
        add_action('wp_head', array($this, 'output_meta_tags'), 1);

        // Filter document title
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 15);
        add_filter('document_title_parts', array($this, 'filter_document_title_parts'), 15);

        // Remove default WordPress meta description if exists
        remove_action('wp_head', 'wp_output_meta_tags');

        // Remove core canonical - we handle it for all page types
        remove_action('wp_head', 'rel_canonical');
    }

    /**
     * Check if frontend output is currently active
     *
     * @return bool
     */
    public function is_active() {
        return $this->is_active;
    }
    
    /**
     * Output meta tags in head
     */
    public function output_meta_tags() {
        $description = $this->get_meta_description();
        $robots = $this->get_robots_meta();
        $canonical = $this->get_canonical_url();

        // Output canonical URL
        if (!empty($canonical)) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }

        // Output meta description
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        // Output robots meta
        if (!empty($robots)) {
            echo '<meta name="robots" content="' . esc_attr($robots) . '" />' . "\n";
        }
        
        // Output Open Graph tags
        $this->output_og_tags();
    }
    
    /**
     * Get meta description for current page
     */
    public function get_meta_description() {
        $description = '';
        $settings = MDH_Helpers::get_settings();
        
        if (is_front_page() || is_home()) {
            // Homepage
            $description = $settings['homepage_description'] ?? '';
            if (empty($description)) {
                $description = get_bloginfo('description');
            }
        } elseif (is_singular()) {
            // Single post/page/custom post type
            global $post;
            $description = get_post_meta($post->ID, '_mdh_meta_description', true);
            
            if (empty($description)) {
                // Get post type settings
                $post_type = get_post_type();
                $pt_settings = $settings['post_type_settings'][$post_type] ?? array();
                
                if (!empty($pt_settings['default_description'])) {
                    $description = MDH_Helpers::parse_template($pt_settings['default_description'], array(
                        'post_title' => get_the_title(),
                        'post_excerpt' => get_the_excerpt(),
                    ));
                } else {
                    // Auto-generate from excerpt or content
                    if (has_excerpt()) {
                        $description = get_the_excerpt();
                    } else {
                        $description = wp_strip_all_tags($post->post_content);
                    }
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            // Taxonomy archive
            $term = get_queried_object();
            $description = get_term_meta($term->term_id, '_mdh_meta_description', true);
            
            if (empty($description)) {
                // Get taxonomy settings
                $tax_settings = $settings['taxonomy_settings'][$term->taxonomy] ?? array();
                
                if (!empty($tax_settings['default_description'])) {
                    $description = MDH_Helpers::parse_template($tax_settings['default_description'], array(
                        'term_title' => $term->name,
                        'term_description' => $term->description,
                    ));
                } else {
                    $description = $term->description;
                }
            }
        } elseif (is_post_type_archive()) {
            // Post type archive
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            
            $archive_settings = $settings['archive_settings'][$post_type] ?? array();
            $description = $archive_settings['description'] ?? '';
        } elseif (is_author()) {
            // Author archive
            $archive_settings = $settings['archive_settings']['author'] ?? array();
            $author = get_queried_object();
            
            $description = $archive_settings['description'] ?? '';
            if (!empty($description)) {
                $description = str_replace('%author_name%', $author->display_name, $description);
            } else {
                $description = get_the_author_meta('description', $author->ID);
            }
        } elseif (is_date()) {
            // Date archive
            $archive_type = 'yearly';
            $archive_title = get_the_date('Y');
            if (is_month()) {
                $archive_type = 'monthly';
                $archive_title = get_the_date('F Y');
            } elseif (is_day()) {
                $archive_type = 'daily';
                $archive_title = get_the_date();
            }

            $archive_settings = $settings['archive_settings'][$archive_type] ?? array();
            $description = $archive_settings['description'] ?? '';
            if (!empty($description)) {
                $description = MDH_Helpers::parse_template($description, array(
                    'archive_title' => $archive_title,
                ));
            }
        } elseif (is_search()) {
            // Search results - no description typically needed
            $description = '';
        } elseif (is_404()) {
            // 404 page
            $description = $settings['default_404_description'] ?? '';
        }
        
        // Truncate and clean description
        if (!empty($description)) {
            $description = MDH_Helpers::truncate($description, 160, '');
        }
        
        return apply_filters('mdh_meta_description', $description);
    }
    
    /**
     * Get robots meta content
     */
    public function get_robots_meta() {
        $robots = array();
        
        if (is_singular()) {
            global $post;
            $noindex = get_post_meta($post->ID, '_mdh_robots_noindex', true);
            $nofollow = get_post_meta($post->ID, '_mdh_robots_nofollow', true);
            
            if ($noindex) {
                $robots[] = 'noindex';
            }
            if ($nofollow) {
                $robots[] = 'nofollow';
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            $noindex = get_term_meta($term->term_id, '_mdh_robots_noindex', true);
            
            if ($noindex) {
                $robots[] = 'noindex';
            }
        }
        
        // Default to index, follow if nothing specified
        if (empty($robots)) {
            return '';
        }
        
        return implode(', ', $robots);
    }
    
    /**
     * Filter document title
     */
    public function filter_document_title($title) {
        $custom_title = $this->get_custom_title();
        
        if (!empty($custom_title)) {
            return $custom_title;
        }
        
        return $title;
    }
    
    /**
     * Filter document title parts
     */
    public function filter_document_title_parts($title_parts) {
        $custom_title = $this->get_custom_title();
        
        if (!empty($custom_title)) {
            // Return only the custom title, removing other parts
            return array('title' => $custom_title);
        }
        
        return $title_parts;
    }
    
    /**
     * Get custom title for current page
     */
    public function get_custom_title() {
        $title = '';
        $settings = MDH_Helpers::get_settings();
        
        if (is_front_page() || is_home()) {
            // Homepage
            $title = $settings['homepage_title'] ?? '';
            if (empty($title)) {
                $title = get_bloginfo('name');
                $tagline = get_bloginfo('description');
                if (!empty($tagline)) {
                    $title .= ' ' . MDH_Helpers::get_separator() . ' ' . $tagline;
                }
            }
        } elseif (is_singular()) {
            // Single post/page/custom post type
            global $post;
            $title = get_post_meta($post->ID, '_mdh_meta_title', true);
            
            if (empty($title)) {
                // Get post type settings
                $post_type = get_post_type();
                $pt_settings = $settings['post_type_settings'][$post_type] ?? array();
                
                $format = $pt_settings['title_format'] ?? '%post_title% %separator% %site_title%';
                $title = MDH_Helpers::parse_template($format, array(
                    'post_title' => get_the_title(),
                    'post_excerpt' => get_the_excerpt(),
                ));
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            // Taxonomy archive
            $term = get_queried_object();
            $title = get_term_meta($term->term_id, '_mdh_meta_title', true);
            
            if (empty($title)) {
                // Get taxonomy settings
                $tax_settings = $settings['taxonomy_settings'][$term->taxonomy] ?? array();
                
                $format = $tax_settings['title_format'] ?? '%term_title% %separator% %site_title%';
                $title = MDH_Helpers::parse_template($format, array(
                    'term_title' => $term->name,
                    'term_description' => $term->description,
                ));
            }
            
            // Add pagination
            $paged = get_query_var('paged');
            if ($paged > 1) {
                $title .= ' ' . MDH_Helpers::get_separator() . ' ' . sprintf(__('Strona %d', 'meta-description-handler'), $paged);
            }
        } elseif (is_post_type_archive()) {
            // Post type archive
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            
            $archive_settings = $settings['archive_settings'][$post_type] ?? array();
            $title = $archive_settings['title'] ?? '';
            
            if (empty($title)) {
                $post_type_obj = get_post_type_object($post_type);
                $format = $settings['default_archive_title_format'] ?? '%archive_title% %separator% %site_title%';
                $title = MDH_Helpers::parse_template($format, array(
                    'archive_title' => $post_type_obj->labels->name,
                ));
            }
            
            // Add pagination
            $paged = get_query_var('paged');
            if ($paged > 1) {
                $title .= ' ' . MDH_Helpers::get_separator() . ' ' . sprintf(__('Strona %d', 'meta-description-handler'), $paged);
            }
        } elseif (is_author()) {
            // Author archive
            $archive_settings = $settings['archive_settings']['author'] ?? array();
            $author = get_queried_object();
            
            $format = $archive_settings['title_format'] ?? __('Wpisy autora %author_name% %separator% %site_title%', 'meta-description-handler');
            $title = MDH_Helpers::parse_template($format, array(
                'author_name' => $author->display_name,
            ));
        } elseif (is_year()) {
            // Yearly archive
            $archive_settings = $settings['archive_settings']['yearly'] ?? array();
            $format = $archive_settings['title_format'] ?? '%archive_title% %separator% %site_title%';
            $title = MDH_Helpers::parse_template($format, array(
                'archive_title' => get_the_date('Y'),
            ));
        } elseif (is_month()) {
            // Monthly archive
            $archive_settings = $settings['archive_settings']['monthly'] ?? array();
            $format = $archive_settings['title_format'] ?? '%archive_title% %separator% %site_title%';
            $title = MDH_Helpers::parse_template($format, array(
                'archive_title' => get_the_date('F Y'),
            ));
        } elseif (is_day()) {
            // Daily archive
            $archive_settings = $settings['archive_settings']['daily'] ?? array();
            $format = $archive_settings['title_format'] ?? '%archive_title% %separator% %site_title%';
            $title = MDH_Helpers::parse_template($format, array(
                'archive_title' => get_the_date(),
            ));
        } elseif (is_search()) {
            // Search results
            $format = $settings['default_search_title_format'] ?? __('Wyniki wyszukiwania dla "%search_query%" %separator% %site_title%', 'meta-description-handler');
            $title = MDH_Helpers::parse_template($format, array(
                'search_query' => get_search_query(),
            ));
        } elseif (is_404()) {
            // 404 page
            $format = $settings['default_404_title'] ?? __('Strona nie została znaleziona %separator% %site_title%', 'meta-description-handler');
            $title = MDH_Helpers::parse_template($format);
        }
        
        return apply_filters('mdh_document_title', $title);
    }
    
    /**
     * Output Open Graph tags
     */
    public function output_og_tags() {
        $title = $this->get_custom_title();
        $description = $this->get_meta_description();
        $url = $this->get_canonical_url();
        $image = $this->get_og_image();
        
        echo "\n<!-- Meta Description Handler - Open Graph Tags -->\n";
        
        if (!empty($title)) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        }
        
        if (!empty($description)) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        if (!empty($url)) {
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        }
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        // OG Type
        if (is_singular()) {
            echo '<meta property="og:type" content="article" />' . "\n";
        } else {
            echo '<meta property="og:type" content="website" />' . "\n";
        }
        
        // OG Image
        if (!empty($image)) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        }
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";

        $settings = MDH_Helpers::get_settings();
        $twitter_site = $settings['twitter_site'] ?? '';
        if (!empty($twitter_site)) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '" />' . "\n";
        }
        
        if (!empty($title)) {
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        }
        
        if (!empty($description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        if (!empty($image)) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
        
        echo "<!-- End Meta Description Handler -->\n\n";
    }
    
    /**
     * Get canonical URL for current page
     */
    public function get_canonical_url() {
        $url = '';

        if (is_front_page() || is_home()) {
            $url = home_url('/');
        } elseif (is_singular()) {
            $url = wp_get_canonical_url();
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $url = get_term_link($term);
                if (is_wp_error($url)) {
                    $url = '';
                }
            }
        } elseif (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            $url = get_post_type_archive_link($post_type);
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $url = get_author_posts_url($author->ID);
            }
        } elseif (is_year()) {
            $url = get_year_link(get_query_var('year'));
        } elseif (is_month()) {
            $url = get_month_link(get_query_var('year'), get_query_var('monthnum'));
        } elseif (is_day()) {
            $url = get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
        }

        // No canonical for search and 404
        if (is_search() || is_404()) {
            return '';
        }

        if (!empty($url)) {
            $url = trailingslashit($url);
        }

        // Handle archive pagination (non-singular)
        if (!empty($url) && !is_singular()) {
            $paged = get_query_var('paged');
            if ($paged > 1) {
                global $wp_rewrite;
                if ($wp_rewrite->using_permalinks()) {
                    $url = $url . 'page/' . $paged . '/';
                } else {
                    $url = add_query_arg('paged', $paged, $url);
                }
            }
        }

        return apply_filters('mdh_canonical_url', $url);
    }
    
    /**
     * Get Open Graph image
     */
    public function get_og_image() {
        $image = '';
        
        if (is_singular()) {
            // Try to get featured image
            if (has_post_thumbnail()) {
                $image = get_the_post_thumbnail_url(null, 'large');
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            // Try to get term image if available (requires additional meta)
            $term = get_queried_object();
            $term_image = get_term_meta($term->term_id, '_mdh_og_image', true);
            if (!empty($term_image)) {
                $image = $term_image;
            }
        }
        
        // Fallback to default OG image from settings
        if (empty($image)) {
            $settings = MDH_Helpers::get_settings();
            $default_og = $settings['default_og_image'] ?? '';
            if (!empty($default_og)) {
                $image = $default_og;
            }
        }

        // Fallback to site logo
        if (empty($image)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $image = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }
        
        return apply_filters('mdh_og_image', $image);
    }
}
