<?php
/**
 * Helper Functions Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Helpers {

    private static $settings_cache = null;

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        if (null === self::$settings_cache) {
            self::$settings_cache = get_option('mdh_settings', array());
        }
        return self::$settings_cache;
    }
    
    /**
     * Get single setting
     */
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update settings
     */
    public static function update_settings($settings) {
        self::$settings_cache = $settings;
        return update_option('mdh_settings', $settings);
    }
    
    /**
     * Get title separator
     */
    public static function get_separator() {
        return self::get_setting('title_separator', '|');
    }
    
    /**
     * Parse title/description template
     */
    public static function parse_template($template, $context = array()) {
        $replacements = array(
            '%site_title%' => get_bloginfo('name'),
            '%site_description%' => get_bloginfo('description'),
            '%separator%' => self::get_separator(),
            '%current_date%' => date_i18n(get_option('date_format')),
            '%current_year%' => date('Y'),
        );
        
        // Context-specific replacements
        if (isset($context['post_title'])) {
            $replacements['%post_title%'] = $context['post_title'];
        }
        if (isset($context['archive_title'])) {
            $replacements['%archive_title%'] = $context['archive_title'];
        }
        if (isset($context['term_title'])) {
            $replacements['%term_title%'] = $context['term_title'];
        }
        if (isset($context['term_description'])) {
            $replacements['%term_description%'] = $context['term_description'];
        }
        if (isset($context['search_query'])) {
            $replacements['%search_query%'] = $context['search_query'];
        }
        if (isset($context['page_number'])) {
            $replacements['%page_number%'] = $context['page_number'];
        }
        if (isset($context['author_name'])) {
            $replacements['%author_name%'] = $context['author_name'];
        }
        if (isset($context['post_excerpt'])) {
            $replacements['%post_excerpt%'] = $context['post_excerpt'];
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Truncate text to specified length
     */
    public static function truncate($text, $length = 160, $append = '...') {
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        $text = mb_substr($text, 0, $length - mb_strlen($append, 'UTF-8'), 'UTF-8');
        $last_space = mb_strrpos($text, ' ', 0, 'UTF-8');
        if ($last_space !== false) {
            $text = mb_substr($text, 0, $last_space, 'UTF-8');
        }

        return $text . $append;
    }
    
    /**
     * Get all public post types
     */
    public static function get_public_post_types() {
        $post_types = get_post_types(array(
            'public' => true,
        ), 'objects');
        
        // Remove attachments
        unset($post_types['attachment']);
        
        return $post_types;
    }
    
    /**
     * Get all public taxonomies
     */
    public static function get_public_taxonomies() {
        return get_taxonomies(array(
            'public' => true,
        ), 'objects');
    }
    
    /**
     * Detect active SEO plugins that may conflict with MDH
     *
     * Uses constants and classes instead of is_plugin_active() to work on frontend.
     *
     * @return array Array of detected plugin names (empty if none found)
     */
    public static function detect_seo_conflicts() {
        $conflicts = array();

        // Yoast SEO
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
            $conflicts[] = 'Yoast SEO';
        }

        // Rank Math
        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            $conflicts[] = 'Rank Math';
        }

        // All in One SEO Pack
        if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
            $conflicts[] = 'All in One SEO';
        }

        // SEOPress
        if ( defined( 'SEOPRESS_VERSION' ) ) {
            $conflicts[] = 'SEOPress';
        }

        // The SEO Framework
        if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
            $conflicts[] = 'The SEO Framework';
        }

        // Squirrly SEO
        if ( defined( 'SQ_VERSION' ) ) {
            $conflicts[] = 'Squirrly SEO';
        }

        return $conflicts;
    }

    /**
     * Check if frontend output should be disabled due to SEO plugin conflicts
     *
     * @return bool True if frontend output should be disabled
     */
    public static function is_frontend_disabled() {
        $conflicts = self::detect_seo_conflicts();

        if ( empty( $conflicts ) ) {
            return false;
        }

        return ! self::get_setting( 'force_frontend_output', false );
    }

    /**
     * Sanitize meta title
     */
    public static function sanitize_title($title) {
        $title = sanitize_text_field($title);
        $title = wp_strip_all_tags($title);
        return $title;
    }
    
    /**
     * Sanitize meta description
     */
    public static function sanitize_description($description) {
        $description = sanitize_textarea_field($description);
        $description = wp_strip_all_tags($description);
        $description = preg_replace('/\s+/', ' ', $description);
        return trim($description);
    }
    
    /**
     * Calculate approximate pixel width of text
     * Based on Arial font character widths (calibrated to match ToTheWeb)
     * 
     * @param string $text Text to measure
     * @param string $type 'title' (20px) or 'description' (13px)
     * @return int Pixel width
     */
    public static function calculate_pixel_width($text, $type = 'description') {
        if (empty($text)) {
            return 0;
        }
        
        // Average character widths in Arial (approximation)
        // Title uses 20px font, description uses 13px font
        $font_size = ($type === 'title') ? 20 : 13;
        
        // Character width ratios relative to font size (Arial)
        $char_widths = array(
            // Narrow characters
            'i' => 0.278, 'l' => 0.278, 'I' => 0.278, '1' => 0.556, 
            'j' => 0.278, 't' => 0.333, 'f' => 0.333, 'r' => 0.389,
            '!' => 0.333, '.' => 0.278, ',' => 0.278, ':' => 0.333,
            ';' => 0.333, '|' => 0.278, "'" => 0.222, '"' => 0.400,
            
            // Medium characters
            'a' => 0.556, 'b' => 0.611, 'c' => 0.556, 'd' => 0.611,
            'e' => 0.556, 'g' => 0.611, 'h' => 0.611, 'k' => 0.556,
            'n' => 0.611, 'o' => 0.611, 'p' => 0.611, 'q' => 0.611,
            's' => 0.500, 'u' => 0.611, 'v' => 0.556, 'x' => 0.556,
            'y' => 0.556, 'z' => 0.500,
            
            // Wide characters
            'm' => 0.889, 'w' => 0.778, 'M' => 0.833, 'W' => 1.000,
            
            // Uppercase (wider)
            'A' => 0.722, 'B' => 0.722, 'C' => 0.722, 'D' => 0.778,
            'E' => 0.667, 'F' => 0.611, 'G' => 0.778, 'H' => 0.778,
            'J' => 0.556, 'K' => 0.722, 'L' => 0.611, 'N' => 0.778,
            'O' => 0.778, 'P' => 0.667, 'Q' => 0.778, 'R' => 0.722,
            'S' => 0.667, 'T' => 0.611, 'U' => 0.778, 'V' => 0.722,
            'X' => 0.667, 'Y' => 0.667, 'Z' => 0.611,
            
            // Numbers
            '0' => 0.556, '2' => 0.556, '3' => 0.556, '4' => 0.556,
            '5' => 0.556, '6' => 0.556, '7' => 0.556, '8' => 0.556,
            '9' => 0.556,
            
            // Space and common punctuation
            ' ' => 0.278, '-' => 0.333, '_' => 0.556, '/' => 0.278,
            '(' => 0.389, ')' => 0.389, '[' => 0.333, ']' => 0.333,
            '@' => 0.975, '#' => 0.556, '$' => 0.556, '%' => 0.889,
            '&' => 0.722, '*' => 0.389, '+' => 0.584, '=' => 0.584,
            '?' => 0.611, '<' => 0.584, '>' => 0.584,
        );
        
        // Default width for unknown characters
        $default_width = 0.556;
        
        $total_width = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            $ratio = isset($char_widths[$char]) ? $char_widths[$char] : $default_width;
            $total_width += $ratio * $font_size;
        }
        
        // Floor for description (to match ToTheWeb), round for title
        return ($type === 'title') ? round($total_width) : floor($total_width);
    }
    
    /**
     * Get pixel count class for styling
     * 
     * @param int $pixels Pixel width
     * @param string $type 'title' or 'description'
     * @return string CSS class
     */
    public static function get_pixel_count_class($pixels, $type = 'description') {
        if ($type === 'title') {
            if ($pixels === 0) return 'mdh-empty';
            if ($pixels < 400) return 'mdh-short';
            if ($pixels <= 580) return 'mdh-optimal';
            return 'mdh-long';
        } else {
            if ($pixels === 0) return 'mdh-empty';
            if ($pixels < 400) return 'mdh-short';
            if ($pixels <= 920) return 'mdh-optimal';
            return 'mdh-long';
        }
    }
    
    /**
     * Get character count info (legacy - kept for backward compatibility)
     */
    public static function get_char_count_class($length, $type = 'description') {
        if ($type === 'title') {
            if ($length === 0) return 'mdh-empty';
            if ($length < 30) return 'mdh-short';
            if ($length <= 60) return 'mdh-optimal';
            return 'mdh-long';
        } else {
            if ($length === 0) return 'mdh-empty';
            if ($length < 70) return 'mdh-short';
            if ($length <= 160) return 'mdh-optimal';
            return 'mdh-long';
        }
    }
}
