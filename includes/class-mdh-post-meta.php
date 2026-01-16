<?php
/**
 * Post Meta Box Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Post_Meta {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        add_action('admin_footer', array($this, 'add_column_styles'));
        
        // Add columns to post list
        add_action('admin_init', array($this, 'add_post_columns'));
    }
    
    /**
     * Add meta boxes to enabled post types
     */
    public function add_meta_boxes() {
        $settings = MDH_Helpers::get_settings();
        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
        
        foreach ($enabled_post_types as $post_type) {
            add_meta_box(
                'mdh_meta_box',
                'Meta Tytuł i Opis',
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('mdh_save_meta_box', 'mdh_meta_box_nonce');
        
        $meta_title = get_post_meta($post->ID, '_mdh_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_mdh_meta_description', true);
        $robots_noindex = get_post_meta($post->ID, '_mdh_robots_noindex', true);
        $robots_nofollow = get_post_meta($post->ID, '_mdh_robots_nofollow', true);
        
        // Get preview data
        $preview_title = !empty($meta_title) ? $meta_title : $post->post_title;
        $preview_description = !empty($meta_description) ? $meta_description : wp_trim_words($post->post_content, 25, '...');
        $preview_url = get_permalink($post->ID);
        ?>
        <div class="mdh-meta-box">
            <!-- Preview Section -->
            <div class="mdh-preview-section">
                <h4>Podgląd w wyszukiwarce</h4>
                <div class="mdh-serp-preview">
                    <div class="mdh-serp-title" id="mdh-preview-title"><?php echo esc_html($preview_title); ?></div>
                    <div class="mdh-serp-url"><?php echo esc_url($preview_url); ?></div>
                    <div class="mdh-serp-description" id="mdh-preview-description"><?php echo esc_html(MDH_Helpers::truncate($preview_description, 160)); ?></div>
                </div>
            </div>
            
            <!-- Meta Title -->
            <div class="mdh-field-group">
                <label for="mdh_meta_title">
                    <strong>Meta Tytuł</strong>
                </label>
                <input type="text" id="mdh_meta_title" name="mdh_meta_title" 
                       value="<?php echo esc_attr($meta_title); ?>" 
                       class="widefat mdh-title-input"
                       placeholder="<?php echo esc_attr($post->post_title); ?>">
                <div class="mdh-char-counter" data-type="title">
                    <span class="mdh-char-count">0</span>/580px
                    <span class="mdh-char-status"></span>
                </div>
                <p class="description">Pozostaw puste, aby użyć tytułu wpisu z domyślnym formatem.</p>
            </div>
            
            <!-- Meta Description -->
            <div class="mdh-field-group">
                <label for="mdh_meta_description">
                    <strong>Meta Opis</strong>
                </label>
                <textarea id="mdh_meta_description" name="mdh_meta_description" 
                          rows="3" class="widefat mdh-description-input"
                          placeholder="Wpisz przekonujący opis..."><?php echo esc_textarea($meta_description); ?></textarea>
                <div class="mdh-char-counter" data-type="description">
                    <span class="mdh-char-count">0</span>/920px
                    <span class="mdh-char-status"></span>
                </div>
                <p class="description">Pozostaw puste, aby automatycznie wygenerować z treści wpisu.</p>
            </div>
            
            <!-- Robots Settings -->
            <div class="mdh-field-group mdh-robots-section">
                <strong>Widoczność w wyszukiwarkach</strong>
                <div class="mdh-checkbox-group">
                    <label>
                        <input type="checkbox" name="mdh_robots_noindex" value="1" <?php checked($robots_noindex, '1'); ?>>
                        Nie indeksuj (noindex)
                        <span class="description">(Zniechęć wyszukiwarki do indeksowania tej strony)</span>
                    </label>
                    <label>
                        <input type="checkbox" name="mdh_robots_nofollow" value="1" <?php checked($robots_nofollow, '1'); ?>>
                        Nie podążaj (nofollow)
                        <span class="description">(Zniechęć wyszukiwarki do podążania za linkami na tej stronie)</span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['mdh_meta_box_nonce']) || !wp_verify_nonce($_POST['mdh_meta_box_nonce'], 'mdh_save_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if post type is enabled
        $settings = MDH_Helpers::get_settings();
        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
        
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Save meta title
        if (isset($_POST['mdh_meta_title'])) {
            $meta_title = MDH_Helpers::sanitize_title($_POST['mdh_meta_title']);
            update_post_meta($post_id, '_mdh_meta_title', $meta_title);
        }
        
        // Save meta description
        if (isset($_POST['mdh_meta_description'])) {
            $meta_description = MDH_Helpers::sanitize_description($_POST['mdh_meta_description']);
            update_post_meta($post_id, '_mdh_meta_description', $meta_description);
        }
        
        // Save robots settings
        $noindex = isset($_POST['mdh_robots_noindex']) ? '1' : '';
        $nofollow = isset($_POST['mdh_robots_nofollow']) ? '1' : '';
        
        update_post_meta($post_id, '_mdh_robots_noindex', $noindex);
        update_post_meta($post_id, '_mdh_robots_nofollow', $nofollow);
    }
    
    /**
     * Add columns to post list tables
     */
    public function add_post_columns() {
        $settings = MDH_Helpers::get_settings();
        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
        
        foreach ($enabled_post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_columns'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'render_columns'), 10, 2);
        }
    }
    
    /**
     * Add meta columns
     */
    public function add_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['mdh_meta_title'] = 'Meta Tytuł';
                $new_columns['mdh_meta_description'] = 'Meta Opis';
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render column content
     */
    public function render_columns($column, $post_id) {
        switch ($column) {
            case 'mdh_meta_title':
                $meta_title = get_post_meta($post_id, '_mdh_meta_title', true);
                if (!empty($meta_title)) {
                    $pixels = MDH_Helpers::calculate_pixel_width($meta_title, 'title');
                    $class = MDH_Helpers::get_pixel_count_class($pixels, 'title');
                    echo '<span class="mdh-column-meta ' . esc_attr($class) . '">' . esc_html(MDH_Helpers::truncate($meta_title, 40)) . '</span>';
                    echo '<span class="mdh-column-count">(' . $pixels . 'px)</span>';
                } else {
                    echo '<span class="mdh-column-empty">—</span>';
                }
                break;
                
            case 'mdh_meta_description':
                $meta_description = get_post_meta($post_id, '_mdh_meta_description', true);
                if (!empty($meta_description)) {
                    $pixels = MDH_Helpers::calculate_pixel_width($meta_description, 'description');
                    $class = MDH_Helpers::get_pixel_count_class($pixels, 'description');
                    echo '<span class="mdh-column-meta ' . esc_attr($class) . '">' . esc_html(MDH_Helpers::truncate($meta_description, 50)) . '</span>';
                    echo '<span class="mdh-column-count">(' . $pixels . 'px)</span>';
                } else {
                    echo '<span class="mdh-column-empty">—</span>';
                }
                break;
        }
    }
    
    /**
     * Add column styles
     */
    public function add_column_styles() {
        global $pagenow;
        
        if ($pagenow !== 'edit.php') {
            return;
        }
        ?>
        <style>
            .column-mdh_meta_title,
            .column-mdh_meta_description {
                width: 15%;
            }
            .mdh-column-meta {
                display: block;
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .mdh-column-count {
                color: #999;
                font-size: 11px;
            }
            .mdh-column-empty {
                color: #999;
            }
            .mdh-optimal { color: #46b450; }
            .mdh-short { color: #ffb900; }
            .mdh-long { color: #dc3232; }
        </style>
        <?php
    }
}
