<?php
/**
 * Taxonomy Meta Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Taxonomy_Meta {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_taxonomy_fields'));
    }
    
    /**
     * Register taxonomy meta fields
     */
    public function register_taxonomy_fields() {
        $settings = MDH_Helpers::get_settings();
        $enabled_taxonomies = $settings['enabled_taxonomies'] ?? array('category', 'post_tag');
        
        foreach ($enabled_taxonomies as $taxonomy) {
            // Add fields to add term form
            add_action("{$taxonomy}_add_form_fields", array($this, 'add_term_fields'));
            
            // Add fields to edit term form
            add_action("{$taxonomy}_edit_form_fields", array($this, 'edit_term_fields'), 10, 2);
            
            // Save term meta
            add_action("created_{$taxonomy}", array($this, 'save_term_meta'));
            add_action("edited_{$taxonomy}", array($this, 'save_term_meta'));
            
            // Add columns
            add_filter("manage_edit-{$taxonomy}_columns", array($this, 'add_columns'));
            add_filter("manage_{$taxonomy}_custom_column", array($this, 'render_columns'), 10, 3);
        }
    }
    
    /**
     * Add fields to term add form
     */
    public function add_term_fields($taxonomy) {
        wp_nonce_field('mdh_save_term_meta', 'mdh_term_meta_nonce');
        ?>
        <div class="form-field mdh-term-field">
            <label for="mdh_term_meta_title">Meta Tytuł</label>
            <input type="text" name="mdh_term_meta_title" id="mdh_term_meta_title" class="mdh-title-input" value="">
            <p class="description">Własny meta tytuł dla tego terminu. Pozostaw puste, aby użyć nazwy terminu.</p>
            <div class="mdh-char-counter" data-type="title">
                <span class="mdh-char-count">0</span>/580px
            </div>
        </div>
        
        <div class="form-field mdh-term-field">
            <label for="mdh_term_meta_description">Meta Opis</label>
            <textarea name="mdh_term_meta_description" id="mdh_term_meta_description" rows="3" class="mdh-description-input"></textarea>
            <p class="description">Własny meta opis dla archiwum tego terminu.</p>
            <div class="mdh-char-counter" data-type="description">
                <span class="mdh-char-count">0</span>/920px
            </div>
        </div>
        
        <div class="form-field mdh-term-field">
            <label>Widoczność w wyszukiwarkach</label>
            <label>
                <input type="checkbox" name="mdh_term_robots_noindex" value="1">
                Nie indeksuj - Zniechęć wyszukiwarki do indeksowania archiwum tego terminu
            </label>
        </div>
        <?php
    }
    
    /**
     * Add fields to term edit form
     */
    public function edit_term_fields($term, $taxonomy) {
        $meta_title = get_term_meta($term->term_id, '_mdh_meta_title', true);
        $meta_description = get_term_meta($term->term_id, '_mdh_meta_description', true);
        $robots_noindex = get_term_meta($term->term_id, '_mdh_robots_noindex', true);
        
        wp_nonce_field('mdh_save_term_meta', 'mdh_term_meta_nonce');
        ?>
        <tr class="form-field mdh-term-field-row">
            <th scope="row">
                <label for="mdh_term_meta_title">Meta Tytuł</label>
            </th>
            <td>
                <input type="text" name="mdh_term_meta_title" id="mdh_term_meta_title" 
                       class="mdh-title-input" value="<?php echo esc_attr($meta_title); ?>">
                <p class="description">Własny meta tytuł dla tego terminu. Pozostaw puste, aby użyć nazwy terminu.</p>
                <div class="mdh-char-counter" data-type="title">
                    <span class="mdh-char-count">0</span>/580px
                </div>
            </td>
        </tr>
        
        <tr class="form-field mdh-term-field-row">
            <th scope="row">
                <label for="mdh_term_meta_description">Meta Opis</label>
            </th>
            <td>
                <textarea name="mdh_term_meta_description" id="mdh_term_meta_description" 
                          rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($meta_description); ?></textarea>
                <p class="description">Własny meta opis dla archiwum tego terminu.</p>
                <div class="mdh-char-counter" data-type="description">
                    <span class="mdh-char-count">0</span>/920px
                </div>
            </td>
        </tr>
        
        <tr class="form-field mdh-term-field-row">
            <th scope="row">Widoczność w wyszukiwarkach</th>
            <td>
                <label>
                    <input type="checkbox" name="mdh_term_robots_noindex" value="1" <?php checked($robots_noindex, '1'); ?>>
                    Nie indeksuj - Zniechęć wyszukiwarki do indeksowania archiwum tego terminu
                </label>
            </td>
        </tr>
        
        <!-- SERP Preview -->
        <tr class="form-field mdh-term-field-row">
            <th scope="row">Podgląd w wyszukiwarce</th>
            <td>
                <div class="mdh-serp-preview">
                    <div class="mdh-serp-title" id="mdh-preview-title">
                        <?php echo esc_html(!empty($meta_title) ? $meta_title : $term->name); ?>
                    </div>
                    <div class="mdh-serp-url"><?php echo esc_url(get_term_link($term)); ?></div>
                    <div class="mdh-serp-description" id="mdh-preview-description">
                        <?php echo esc_html(!empty($meta_description) ? $meta_description : $term->description); ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save term meta
     */
    public function save_term_meta($term_id) {
        // Check nonce
        if (!isset($_POST['mdh_term_meta_nonce']) || !wp_verify_nonce($_POST['mdh_term_meta_nonce'], 'mdh_save_term_meta')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_categories')) {
            return;
        }
        
        // Save meta title
        if (isset($_POST['mdh_term_meta_title'])) {
            $meta_title = MDH_Helpers::sanitize_title($_POST['mdh_term_meta_title']);
            update_term_meta($term_id, '_mdh_meta_title', $meta_title);
        }
        
        // Save meta description
        if (isset($_POST['mdh_term_meta_description'])) {
            $meta_description = MDH_Helpers::sanitize_description($_POST['mdh_term_meta_description']);
            update_term_meta($term_id, '_mdh_meta_description', $meta_description);
        }
        
        // Save robots settings
        $noindex = isset($_POST['mdh_term_robots_noindex']) ? '1' : '';
        update_term_meta($term_id, '_mdh_robots_noindex', $noindex);
    }
    
    /**
     * Add columns to taxonomy list
     */
    public function add_columns($columns) {
        $columns['mdh_meta_title'] = 'Meta Tytuł';
        $columns['mdh_meta_description'] = 'Meta Opis';
        return $columns;
    }
    
    /**
     * Render column content
     */
    public function render_columns($content, $column_name, $term_id) {
        switch ($column_name) {
            case 'mdh_meta_title':
                $meta_title = get_term_meta($term_id, '_mdh_meta_title', true);
                if (!empty($meta_title)) {
                    $pixels = MDH_Helpers::calculate_pixel_width($meta_title, 'title');
                    $class = MDH_Helpers::get_pixel_count_class($pixels, 'title');
                    return '<span class="mdh-column-meta ' . esc_attr($class) . '">' . esc_html(MDH_Helpers::truncate($meta_title, 30)) . '</span> <span class="mdh-column-count">(' . $pixels . 'px)</span>';
                }
                return '<span class="mdh-column-empty">—</span>';
                
            case 'mdh_meta_description':
                $meta_description = get_term_meta($term_id, '_mdh_meta_description', true);
                if (!empty($meta_description)) {
                    $pixels = MDH_Helpers::calculate_pixel_width($meta_description, 'description');
                    $class = MDH_Helpers::get_pixel_count_class($pixels, 'description');
                    return '<span class="mdh-column-meta ' . esc_attr($class) . '">' . esc_html(MDH_Helpers::truncate($meta_description, 40)) . '</span> <span class="mdh-column-count">(' . $pixels . 'px)</span>';
                }
                return '<span class="mdh-column-empty">—</span>';
        }
        
        return $content;
    }
}
