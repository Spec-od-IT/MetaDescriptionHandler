<?php
/**
 * Admin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_mdh_save_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Meta Description Handler',
            'Meta Handler',
            'manage_options',
            'meta-description-handler',
            array($this, 'render_admin_page'),
            'dashicons-editor-code',
            80
        );
        
        add_submenu_page(
            'meta-description-handler',
            'Ustawienia ogólne',
            'Ustawienia ogólne',
            'manage_options',
            'meta-description-handler',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'meta-description-handler',
            'Typy wpisów',
            'Typy wpisów',
            'manage_options',
            'mdh-post-types',
            array($this, 'render_post_types_page')
        );
        
        add_submenu_page(
            'meta-description-handler',
            'Taksonomie',
            'Taksonomie',
            'manage_options',
            'mdh-taxonomies',
            array($this, 'render_taxonomies_page')
        );
        
        add_submenu_page(
            'meta-description-handler',
            'Archiwa',
            'Archiwa',
            'manage_options',
            'mdh-archives',
            array($this, 'render_archives_page')
        );
        
        add_submenu_page(
            'meta-description-handler',
            'Strony specjalne',
            'Strony specjalne',
            'manage_options',
            'mdh-special-pages',
            array($this, 'render_special_pages')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mdh_settings_group', 'mdh_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['title_separator'])) {
            $sanitized['title_separator'] = sanitize_text_field($input['title_separator']);
        }
        
        if (isset($input['homepage_title'])) {
            $sanitized['homepage_title'] = MDH_Helpers::sanitize_title($input['homepage_title']);
        }
        
        if (isset($input['homepage_description'])) {
            $sanitized['homepage_description'] = MDH_Helpers::sanitize_description($input['homepage_description']);
        }
        
        // Title formats
        $format_fields = array(
            'default_post_title_format',
            'default_page_title_format',
            'default_archive_title_format',
            'default_taxonomy_title_format',
            'default_search_title_format',
            'default_404_title',
        );
        
        foreach ($format_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        if (isset($input['default_404_description'])) {
            $sanitized['default_404_description'] = MDH_Helpers::sanitize_description($input['default_404_description']);
        }
        
        // Post types and taxonomies
        if (isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = array_map('sanitize_key', $input['enabled_post_types']);
        } else {
            $sanitized['enabled_post_types'] = array();
        }
        
        if (isset($input['enabled_taxonomies']) && is_array($input['enabled_taxonomies'])) {
            $sanitized['enabled_taxonomies'] = array_map('sanitize_key', $input['enabled_taxonomies']);
        } else {
            $sanitized['enabled_taxonomies'] = array();
        }
        
        // Archive settings
        if (isset($input['archive_settings']) && is_array($input['archive_settings'])) {
            $sanitized['archive_settings'] = array();
            foreach ($input['archive_settings'] as $key => $archive) {
                $sanitized['archive_settings'][sanitize_key($key)] = array(
                    'title' => isset($archive['title']) ? MDH_Helpers::sanitize_title($archive['title']) : '',
                    'description' => isset($archive['description']) ? MDH_Helpers::sanitize_description($archive['description']) : '',
                    'title_format' => isset($archive['title_format']) ? sanitize_text_field($archive['title_format']) : '',
                );
            }
        }
        
        // Post type specific settings
        if (isset($input['post_type_settings']) && is_array($input['post_type_settings'])) {
            $sanitized['post_type_settings'] = array();
            foreach ($input['post_type_settings'] as $key => $pt_settings) {
                $sanitized['post_type_settings'][sanitize_key($key)] = array(
                    'title_format' => isset($pt_settings['title_format']) ? sanitize_text_field($pt_settings['title_format']) : '',
                    'default_description' => isset($pt_settings['default_description']) ? MDH_Helpers::sanitize_description($pt_settings['default_description']) : '',
                );
            }
        }
        
        // Taxonomy specific settings
        if (isset($input['taxonomy_settings']) && is_array($input['taxonomy_settings'])) {
            $sanitized['taxonomy_settings'] = array();
            foreach ($input['taxonomy_settings'] as $key => $tax_settings) {
                $sanitized['taxonomy_settings'][sanitize_key($key)] = array(
                    'title_format' => isset($tax_settings['title_format']) ? sanitize_text_field($tax_settings['title_format']) : '',
                    'default_description' => isset($tax_settings['default_description']) ? MDH_Helpers::sanitize_description($tax_settings['default_description']) : '',
                );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'meta-description-handler') === false && strpos($hook, 'mdh-') === false) {
            // Also load on post edit screens
            global $pagenow;
            if (!in_array($pagenow, array('post.php', 'post-new.php', 'edit-tags.php', 'term.php'))) {
                return;
            }
        }
        
        wp_enqueue_style(
            'mdh-admin-style',
            MDH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MDH_VERSION
        );
        
        wp_enqueue_script(
            'mdh-admin-script',
            MDH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MDH_VERSION,
            true
        );
        
        wp_localize_script('mdh-admin-script', 'mdhAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mdh_admin_nonce'),
            'strings' => array(
                'saving' => 'Zapisywanie...',
                'saved' => 'Ustawienia zapisane!',
                'error' => 'Błąd podczas zapisywania.',
                'titleOptimal' => 'Optymalna długość (≤580px)',
                'titleShort' => 'Za krótki (<400px)',
                'titleLong' => 'Za długi - zostanie obcięty (>580px)',
                'descOptimal' => 'Optymalna długość (≤920px)',
                'descShort' => 'Za krótki (<400px)',
                'descLong' => 'Za długi - zostanie obcięty (>920px)',
            ),
        ));
    }
    
    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('mdh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień.');
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $settings = $this->sanitize_settings($settings);
        
        $current_settings = MDH_Helpers::get_settings();
        $merged_settings = array_merge($current_settings, $settings);
        
        MDH_Helpers::update_settings($merged_settings);
        
        wp_send_json_success('Ustawienia zapisane pomyślnie.');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $settings = MDH_Helpers::get_settings();
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1>Meta Description Handler</h1>
            
            <div class="mdh-admin-header">
                <p>Skonfiguruj sposób generowania meta tytułów i opisów dla Twojej witryny.</p>
            </div>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#general" class="mdh-tab-link active">Ogólne</a>
                        <a href="#homepage" class="mdh-tab-link">Strona główna</a>
                        <a href="#defaults" class="mdh-tab-link">Formaty tytułów</a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- General Tab -->
                        <div id="general" class="mdh-tab-panel active">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="title_separator">Separator tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="title_separator" name="mdh_settings[title_separator]" 
                                               value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>" class="regular-text">
                                        <p class="description">Znak(i) używane do rozdzielania części tytułu.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3>Włączone typy wpisów</h3>
                            <p class="description">Wybierz, które typy wpisów powinny mieć włączone pola meta.</p>
                            <table class="form-table">
                                <tr>
                                    <td>
                                        <?php 
                                        $post_types = MDH_Helpers::get_public_post_types();
                                        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
                                        foreach ($post_types as $pt): 
                                        ?>
                                            <label class="mdh-checkbox-label">
                                                <input type="checkbox" name="mdh_settings[enabled_post_types][]" 
                                                       value="<?php echo esc_attr($pt->name); ?>"
                                                       <?php checked(in_array($pt->name, $enabled_post_types)); ?>>
                                                <?php echo esc_html($pt->labels->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3>Włączone taksonomie</h3>
                            <p class="description">Wybierz, które taksonomie powinny mieć włączone pola meta.</p>
                            <table class="form-table">
                                <tr>
                                    <td>
                                        <?php 
                                        $taxonomies = MDH_Helpers::get_public_taxonomies();
                                        $enabled_taxonomies = $settings['enabled_taxonomies'] ?? array('category', 'post_tag');
                                        foreach ($taxonomies as $tax): 
                                        ?>
                                            <label class="mdh-checkbox-label">
                                                <input type="checkbox" name="mdh_settings[enabled_taxonomies][]" 
                                                       value="<?php echo esc_attr($tax->name); ?>"
                                                       <?php checked(in_array($tax->name, $enabled_taxonomies)); ?>>
                                                <?php echo esc_html($tax->labels->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Homepage Tab -->
                        <div id="homepage" class="mdh-tab-panel">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="homepage_title">Tytuł strony głównej</label>
                                    </th>
                                    <td>
                                        <input type="text" id="homepage_title" name="mdh_settings[homepage_title]" 
                                               value="<?php echo esc_attr($settings['homepage_title'] ?? ''); ?>" class="large-text mdh-title-input">
                                        <div class="mdh-char-counter" data-type="title">
                                            <span class="mdh-char-count">0</span>/580px
                                        </div>
                                        <p class="description">Pozostaw puste, aby użyć domyślnego tytułu witryny.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="homepage_description">Opis strony głównej</label>
                                    </th>
                                    <td>
                                        <textarea id="homepage_description" name="mdh_settings[homepage_description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($settings['homepage_description'] ?? ''); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                        <p class="description">Pozostaw puste, aby użyć opisu witryny (tagline).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Title Formats Tab -->
                        <div id="defaults" class="mdh-tab-panel">
                            <div class="mdh-notice mdh-notice-info">
                                <p><strong>Dostępne znaczniki:</strong></p>
                                <code>%post_title%</code>, <code>%site_title%</code>, <code>%site_description%</code>, 
                                <code>%separator%</code>, <code>%archive_title%</code>, <code>%term_title%</code>, 
                                <code>%search_query%</code>, <code>%author_name%</code>, <code>%current_year%</code>, 
                                <code>%page_number%</code>
                            </div>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_post_title_format">Format tytułu wpisu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_post_title_format" name="mdh_settings[default_post_title_format]" 
                                               value="<?php echo esc_attr($settings['default_post_title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_page_title_format">Format tytułu strony</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_page_title_format" name="mdh_settings[default_page_title_format]" 
                                               value="<?php echo esc_attr($settings['default_page_title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_archive_title_format">Format tytułu archiwum</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_archive_title_format" name="mdh_settings[default_archive_title_format]" 
                                               value="<?php echo esc_attr($settings['default_archive_title_format'] ?? '%archive_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_taxonomy_title_format">Format tytułu taksonomii</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_taxonomy_title_format" name="mdh_settings[default_taxonomy_title_format]" 
                                               value="<?php echo esc_attr($settings['default_taxonomy_title_format'] ?? '%term_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_search_title_format">Format tytułu wyników wyszukiwania</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_search_title_format" name="mdh_settings[default_search_title_format]" 
                                               value="<?php echo esc_attr($settings['default_search_title_format'] ?? 'Wyniki wyszukiwania dla "%search_query%" %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_title">Tytuł strony 404</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_404_title" name="mdh_settings[default_404_title]" 
                                               value="<?php echo esc_attr($settings['default_404_title'] ?? 'Strona nie została znaleziona %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_description">Opis strony 404</label>
                                    </th>
                                    <td>
                                        <textarea id="default_404_description" name="mdh_settings[default_404_description]" 
                                                  rows="3" class="large-text"><?php echo esc_textarea($settings['default_404_description'] ?? 'Przepraszamy, strona której szukasz nie istnieje lub została przeniesiona.'); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Zapisz zmiany'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render post types settings page
     */
    public function render_post_types_page() {
        $settings = MDH_Helpers::get_settings();
        $post_types = MDH_Helpers::get_public_post_types();
        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
        $post_type_settings = $settings['post_type_settings'] ?? array();
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1>Ustawienia typów wpisów</h1>
            
            <p>Skonfiguruj domyślne formaty tytułów i opisy dla każdego typu wpisu.</p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                <input type="hidden" name="mdh_settings[homepage_title]" value="<?php echo esc_attr($settings['homepage_title'] ?? ''); ?>">
                <input type="hidden" name="mdh_settings[homepage_description]" value="<?php echo esc_attr($settings['homepage_description'] ?? ''); ?>">
                <?php foreach ($enabled_post_types as $ept): ?>
                    <input type="hidden" name="mdh_settings[enabled_post_types][]" value="<?php echo esc_attr($ept); ?>">
                <?php endforeach; ?>
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <?php $first = true; foreach ($post_types as $pt): ?>
                            <a href="#pt-<?php echo esc_attr($pt->name); ?>" class="mdh-tab-link <?php echo $first ? 'active' : ''; ?>">
                                <?php echo esc_html($pt->labels->name); ?>
                            </a>
                        <?php $first = false; endforeach; ?>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <?php $first = true; foreach ($post_types as $pt): 
                            $pt_settings = $post_type_settings[$pt->name] ?? array();
                        ?>
                            <div id="pt-<?php echo esc_attr($pt->name); ?>" class="mdh-tab-panel <?php echo $first ? 'active' : ''; ?>">
                                <h2>Ustawienia: <?php echo esc_html($pt->labels->singular_name); ?></h2>
                                
                                <?php if (!in_array($pt->name, $enabled_post_types)): ?>
                                    <div class="mdh-notice mdh-notice-warning">
                                        <p>Ten typ wpisu nie jest włączony. Włącz go w Ustawieniach ogólnych, aby używać pól meta.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="pt_title_format_<?php echo esc_attr($pt->name); ?>">
                                                Domyślny format tytułu
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="pt_title_format_<?php echo esc_attr($pt->name); ?>" 
                                                   name="mdh_settings[post_type_settings][<?php echo esc_attr($pt->name); ?>][title_format]" 
                                                   value="<?php echo esc_attr($pt_settings['title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                                   class="large-text">
                                            <p class="description">
                                                Dostępne: <code>%post_title%</code>, <code>%site_title%</code>, <code>%separator%</code>, <code>%post_excerpt%</code>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="pt_default_desc_<?php echo esc_attr($pt->name); ?>">
                                                Domyślny opis
                                            </label>
                                        </th>
                                        <td>
                                            <textarea id="pt_default_desc_<?php echo esc_attr($pt->name); ?>" 
                                                      name="mdh_settings[post_type_settings][<?php echo esc_attr($pt->name); ?>][default_description]" 
                                                      rows="3" class="large-text"><?php echo esc_textarea($pt_settings['default_description'] ?? ''); ?></textarea>
                                            <p class="description">Używany gdy nie ustawiono niestandardowego opisu. Pozostaw puste, aby użyć zajawki lub automatycznie wygenerowanej treści.</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php if ($pt->has_archive): ?>
                                    <h3>Ustawienia strony archiwum</h3>
                                    <?php 
                                    $archive_settings = $settings['archive_settings'][$pt->name] ?? array();
                                    ?>
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="archive_title_<?php echo esc_attr($pt->name); ?>">
                                                    Tytuł archiwum
                                                </label>
                                            </th>
                                            <td>
                                                <input type="text" id="archive_title_<?php echo esc_attr($pt->name); ?>" 
                                                       name="mdh_settings[archive_settings][<?php echo esc_attr($pt->name); ?>][title]" 
                                                       value="<?php echo esc_attr($archive_settings['title'] ?? ''); ?>" 
                                                       class="large-text mdh-title-input">
                                                <div class="mdh-char-counter" data-type="title">
                                                    <span class="mdh-char-count">0</span>/580px
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="archive_desc_<?php echo esc_attr($pt->name); ?>">
                                                    Opis archiwum
                                                </label>
                                            </th>
                                            <td>
                                                <textarea id="archive_desc_<?php echo esc_attr($pt->name); ?>" 
                                                          name="mdh_settings[archive_settings][<?php echo esc_attr($pt->name); ?>][description]" 
                                                          rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($archive_settings['description'] ?? ''); ?></textarea>
                                                <div class="mdh-char-counter" data-type="description">
                                                    <span class="mdh-char-count">0</span>/920px
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
                
                <?php submit_button('Zapisz zmiany'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render taxonomies settings page
     */
    public function render_taxonomies_page() {
        $settings = MDH_Helpers::get_settings();
        $taxonomies = MDH_Helpers::get_public_taxonomies();
        $enabled_taxonomies = $settings['enabled_taxonomies'] ?? array('category', 'post_tag');
        $taxonomy_settings = $settings['taxonomy_settings'] ?? array();
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1>Ustawienia taksonomii</h1>
            
            <p>Skonfiguruj domyślne formaty tytułów i opisy dla każdej taksonomii.</p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                <?php foreach ($enabled_taxonomies as $etax): ?>
                    <input type="hidden" name="mdh_settings[enabled_taxonomies][]" value="<?php echo esc_attr($etax); ?>">
                <?php endforeach; ?>
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <?php $first = true; foreach ($taxonomies as $tax): ?>
                            <a href="#tax-<?php echo esc_attr($tax->name); ?>" class="mdh-tab-link <?php echo $first ? 'active' : ''; ?>">
                                <?php echo esc_html($tax->labels->name); ?>
                            </a>
                        <?php $first = false; endforeach; ?>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <?php $first = true; foreach ($taxonomies as $tax): 
                            $tax_settings = $taxonomy_settings[$tax->name] ?? array();
                        ?>
                            <div id="tax-<?php echo esc_attr($tax->name); ?>" class="mdh-tab-panel <?php echo $first ? 'active' : ''; ?>">
                                <h2>Ustawienia: <?php echo esc_html($tax->labels->singular_name); ?></h2>
                                
                                <?php if (!in_array($tax->name, $enabled_taxonomies)): ?>
                                    <div class="mdh-notice mdh-notice-warning">
                                        <p>Ta taksonomia nie jest włączona. Włącz ją w Ustawieniach ogólnych, aby używać pól meta.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="tax_title_format_<?php echo esc_attr($tax->name); ?>">
                                                Domyślny format tytułu
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="tax_title_format_<?php echo esc_attr($tax->name); ?>" 
                                                   name="mdh_settings[taxonomy_settings][<?php echo esc_attr($tax->name); ?>][title_format]" 
                                                   value="<?php echo esc_attr($tax_settings['title_format'] ?? '%term_title% %separator% %site_title%'); ?>" 
                                                   class="large-text">
                                            <p class="description">
                                                Dostępne: <code>%term_title%</code>, <code>%term_description%</code>, <code>%site_title%</code>, <code>%separator%</code>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="tax_default_desc_<?php echo esc_attr($tax->name); ?>">
                                                Domyślny opis
                                            </label>
                                        </th>
                                        <td>
                                            <textarea id="tax_default_desc_<?php echo esc_attr($tax->name); ?>" 
                                                      name="mdh_settings[taxonomy_settings][<?php echo esc_attr($tax->name); ?>][default_description]" 
                                                      rows="3" class="large-text"><?php echo esc_textarea($tax_settings['default_description'] ?? ''); ?></textarea>
                                            <p class="description">Używany gdy nie ustawiono niestandardowego opisu dla terminu.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
                
                <?php submit_button('Zapisz zmiany'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render archives settings page
     */
    public function render_archives_page() {
        $settings = MDH_Helpers::get_settings();
        $archive_settings = $settings['archive_settings'] ?? array();
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1>Ustawienia archiwów</h1>
            
            <p>Skonfiguruj meta tytuły i opisy dla stron archiwów.</p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#date-archives" class="mdh-tab-link active">Archiwa dat</a>
                        <a href="#author-archives" class="mdh-tab-link">Archiwa autorów</a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- Date Archives -->
                        <div id="date-archives" class="mdh-tab-panel active">
                            <h2>Ustawienia archiwów dat</h2>
                            
                            <h3>Archiwa roczne</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_yearly_title">Format tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="archive_yearly_title" 
                                               name="mdh_settings[archive_settings][yearly][title_format]" 
                                               value="<?php echo esc_attr($archive_settings['yearly']['title_format'] ?? 'Archiwum: %archive_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="archive_yearly_desc">Opis</label>
                                    </th>
                                    <td>
                                        <textarea id="archive_yearly_desc" 
                                                  name="mdh_settings[archive_settings][yearly][description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($archive_settings['yearly']['description'] ?? 'Przeglądaj wszystkie wpisy z roku %archive_title%.'); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3>Archiwa miesięczne</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_monthly_title">Format tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="archive_monthly_title" 
                                               name="mdh_settings[archive_settings][monthly][title_format]" 
                                               value="<?php echo esc_attr($archive_settings['monthly']['title_format'] ?? 'Archiwum: %archive_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="archive_monthly_desc">Opis</label>
                                    </th>
                                    <td>
                                        <textarea id="archive_monthly_desc" 
                                                  name="mdh_settings[archive_settings][monthly][description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($archive_settings['monthly']['description'] ?? 'Przeglądaj wszystkie wpisy z miesiąca %archive_title%.'); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3>Archiwa dzienne</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_daily_title">Format tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="archive_daily_title" 
                                               name="mdh_settings[archive_settings][daily][title_format]" 
                                               value="<?php echo esc_attr($archive_settings['daily']['title_format'] ?? 'Archiwum: %archive_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="archive_daily_desc">Opis</label>
                                    </th>
                                    <td>
                                        <textarea id="archive_daily_desc" 
                                                  name="mdh_settings[archive_settings][daily][description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($archive_settings['daily']['description'] ?? 'Przeglądaj wszystkie wpisy z dnia %archive_title%.'); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Author Archives -->
                        <div id="author-archives" class="mdh-tab-panel">
                            <h2>Ustawienia archiwów autorów</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_author_title">Format tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="archive_author_title" 
                                               name="mdh_settings[archive_settings][author][title_format]" 
                                               value="<?php echo esc_attr($archive_settings['author']['title_format'] ?? 'Wpisy autora %author_name% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                        <p class="description">
                                            Dostępne: <code>%author_name%</code>, <code>%site_title%</code>, <code>%separator%</code>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="archive_author_desc">Format opisu</label>
                                    </th>
                                    <td>
                                        <textarea id="archive_author_desc" 
                                                  name="mdh_settings[archive_settings][author][description]" 
                                                  rows="3" class="large-text"><?php echo esc_textarea($archive_settings['author']['description'] ?? 'Wszystkie artykuły napisane przez %author_name%.'); ?></textarea>
                                        <p class="description">Użyj %author_name% aby wstawić nazwę autora.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Zapisz zmiany'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render special pages settings
     */
    public function render_special_pages() {
        $settings = MDH_Helpers::get_settings();
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1>Ustawienia stron specjalnych</h1>
            
            <p>Skonfiguruj meta tytuły i opisy dla specjalnych stron WordPress.</p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#search-page" class="mdh-tab-link active">Wyniki wyszukiwania</a>
                        <a href="#404-page" class="mdh-tab-link">Strona 404</a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- Search Results -->
                        <div id="search-page" class="mdh-tab-panel active">
                            <h2>Strona wyników wyszukiwania</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_search_title_format">Format tytułu</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_search_title_format" 
                                               name="mdh_settings[default_search_title_format]" 
                                               value="<?php echo esc_attr($settings['default_search_title_format'] ?? 'Wyniki wyszukiwania dla "%search_query%" %separator% %site_title%'); ?>" 
                                               class="large-text">
                                        <p class="description">
                                            Dostępne: <code>%search_query%</code>, <code>%site_title%</code>, <code>%separator%</code>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- 404 Page -->
                        <div id="404-page" class="mdh-tab-panel">
                            <h2>Strona błędu 404</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_title">Tytuł</label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_404_title" 
                                               name="mdh_settings[default_404_title]" 
                                               value="<?php echo esc_attr($settings['default_404_title'] ?? 'Strona nie została znaleziona %separator% %site_title%'); ?>" 
                                               class="large-text mdh-title-input">
                                        <div class="mdh-char-counter" data-type="title">
                                            <span class="mdh-char-count">0</span>/580px
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_description">Opis</label>
                                    </th>
                                    <td>
                                        <textarea id="default_404_description" 
                                                  name="mdh_settings[default_404_description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($settings['default_404_description'] ?? 'Przepraszamy, strona której szukasz nie istnieje lub została przeniesiona.'); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Zapisz zmiany'); ?>
            </form>
        </div>
        <?php
    }
}
