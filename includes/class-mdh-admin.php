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

        // SEO plugin conflict notice
        add_action('admin_notices', array($this, 'display_seo_conflict_notice'));
        add_action('wp_ajax_mdh_dismiss_conflict_notice', array($this, 'ajax_dismiss_conflict_notice'));
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
            __('Ustawienia ogólne', 'meta-description-handler'),
            __('Ustawienia ogólne', 'meta-description-handler'),
            'manage_options',
            'meta-description-handler',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'meta-description-handler',
            __('Typy wpisów', 'meta-description-handler'),
            __('Typy wpisów', 'meta-description-handler'),
            'manage_options',
            'mdh-post-types',
            array($this, 'render_post_types_page')
        );

        add_submenu_page(
            'meta-description-handler',
            __('Taksonomie', 'meta-description-handler'),
            __('Taksonomie', 'meta-description-handler'),
            'manage_options',
            'mdh-taxonomies',
            array($this, 'render_taxonomies_page')
        );

        add_submenu_page(
            'meta-description-handler',
            __('Archiwa', 'meta-description-handler'),
            __('Archiwa', 'meta-description-handler'),
            'manage_options',
            'mdh-archives',
            array($this, 'render_archives_page')
        );

        add_submenu_page(
            'meta-description-handler',
            __('Strony specjalne', 'meta-description-handler'),
            __('Strony specjalne', 'meta-description-handler'),
            'manage_options',
            'mdh-special-pages',
            array($this, 'render_special_pages')
        );

        add_submenu_page(
            'meta-description-handler',
            'Social Media',
            'Social Media',
            'manage_options',
            'mdh-social',
            array($this, 'render_social_page')
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

        // Sitemap toggle — only process when the field is actually on the page
        if (isset($input['_page_has_sitemap'])) {
            $sanitized['sitemap_enabled'] = !empty($input['sitemap_enabled']);
        }

        // Social Media settings
        if (isset($input['default_og_image'])) {
            $sanitized['default_og_image'] = esc_url_raw($input['default_og_image']);
        }
        if (isset($input['default_og_image_id'])) {
            $sanitized['default_og_image_id'] = absint($input['default_og_image_id']);
        }
        if (isset($input['twitter_site'])) {
            $handle = sanitize_text_field($input['twitter_site']);
            if (!empty($handle) && strpos($handle, '@') !== 0) {
                $handle = '@' . $handle;
            }
            $sanitized['twitter_site'] = $handle;
        }

        // Post types and taxonomies — only process when the checkboxes are on the page
        if (isset($input['_page_has_post_types'])) {
            $sanitized['enabled_post_types'] = isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])
                ? array_map('sanitize_key', $input['enabled_post_types'])
                : array();
        }

        if (isset($input['_page_has_taxonomies'])) {
            $sanitized['enabled_taxonomies'] = isset($input['enabled_taxonomies']) && is_array($input['enabled_taxonomies'])
                ? array_map('sanitize_key', $input['enabled_taxonomies'])
                : array();
        }

        // Auto-generated descriptions — only process when the checkbox is on the page
        if (isset($input['_page_has_autogenerate'])) {
            $sanitized['autogenerate_description'] = !empty($input['autogenerate_description']);
        }

        // Force frontend output (advanced) — only when on the page that has it
        if (isset($input['_page_has_force_output'])) {
            $sanitized['force_frontend_output'] = !empty($input['force_frontend_output']);
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

        // Merge with existing settings — preserves values from other admin pages
        $current = get_option('mdh_settings', array());
        return array_merge($current, $sanitized);
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
        
        // Load media uploader on Social Media settings page
        if (strpos($hook, 'mdh-social') !== false) {
            wp_enqueue_media();
        }

        wp_localize_script('mdh-admin-script', 'mdhAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mdh_admin_nonce'),
            'strings' => array(
                'saving' => __('Zapisywanie...', 'meta-description-handler'),
                'saved' => __('Ustawienia zapisane!', 'meta-description-handler'),
                'error' => __('Błąd podczas zapisywania.', 'meta-description-handler'),
                'titleOptimal' => __('Optymalna długość (≤580px)', 'meta-description-handler'),
                'titleShort' => __('Za krótki (<400px)', 'meta-description-handler'),
                'titleLong' => __('Za długi - zostanie obcięty (>580px)', 'meta-description-handler'),
                'descOptimal' => __('Optymalna długość (≤920px)', 'meta-description-handler'),
                'descShort' => __('Za krótki (<400px)', 'meta-description-handler'),
                'descLong' => __('Za długi - zostanie obcięty (>920px)', 'meta-description-handler'),
                // Bulk editor strings
                'loading' => __('Ładowanie...', 'meta-description-handler'),
                'page' => __('Strona', 'meta-description-handler'),
                'of' => __('z', 'meta-description-handler'),
                'previous' => __('Poprzednia', 'meta-description-handler'),
                'next' => __('Następna', 'meta-description-handler'),
                'noDescription' => __('Brak opisu...', 'meta-description-handler'),
                'pageTitle' => __('Tytuł strony', 'meta-description-handler'),
                'saveChanges' => __('Zapisz zmiany', 'meta-description-handler'),
                'savedMeta' => __('Meta dane zostały zapisane!', 'meta-description-handler'),
                'saveError' => __('Błąd podczas zapisywania.', 'meta-description-handler'),
                'connectionError' => __('Błąd połączenia.', 'meta-description-handler'),
                'chooseOgImage' => __('Wybierz domyślny obrazek OG', 'meta-description-handler'),
                'useThisImage' => __('Użyj tego obrazka', 'meta-description-handler'),
                'removeImage' => __('Usuń obrazek', 'meta-description-handler'),
            ),
        ));

        // Enqueue bulk editor script on its page
        if (strpos($hook, 'mdh-bulk-editor') !== false) {
            wp_enqueue_script(
                'mdh-bulk-editor-script',
                MDH_PLUGIN_URL . 'assets/js/bulk-editor.js',
                array('jquery', 'mdh-admin-script'),
                MDH_VERSION,
                true
            );
        }
    }
    
    /**
     * Display admin notice when SEO plugin conflict is detected
     */
    public function display_seo_conflict_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $conflicts = MDH_Helpers::detect_seo_conflicts();

        if ( empty( $conflicts ) ) {
            // Clean up dismissal meta if no conflicts remain
            delete_user_meta( get_current_user_id(), 'mdh_conflict_notice_dismissed' );
            delete_user_meta( get_current_user_id(), 'mdh_conflict_notice_plugins' );
            return;
        }

        // Check if notice was dismissed for this exact set of plugins
        $dismissed = get_user_meta( get_current_user_id(), 'mdh_conflict_notice_dismissed', true );
        if ( $dismissed ) {
            $dismissed_for = get_user_meta( get_current_user_id(), 'mdh_conflict_notice_plugins', true );
            if ( $dismissed_for === implode( ',', $conflicts ) ) {
                return;
            }
        }

        $plugin_list = '<strong>' . esc_html( implode( ', ', $conflicts ) ) . '</strong>';
        $force_enabled = MDH_Helpers::get_setting( 'force_frontend_output', false );

        ?>
        <div class="notice notice-warning is-dismissible mdh-conflict-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mdh_dismiss_conflict' ) ); ?>">
            <p>
                <strong><?php esc_html_e( 'Meta Description Handler — wykryto konflikt z wtyczką SEO', 'meta-description-handler' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    __( 'Wykryto aktywną wtyczkę SEO: %s. Aby uniknąć duplikowania meta tagów, Meta Description Handler %s.', 'meta-description-handler' ),
                    $plugin_list,
                    $force_enabled
                        ? '<span style="color: #dc3232;">' . __( 'nadal generuje tagi meta (tryb wymuszony)', 'meta-description-handler' ) . '</span>'
                        : __( 'automatycznie wyłączył generowanie tagów meta na froncie', 'meta-description-handler' )
                );
                ?>
            </p>
            <p><?php esc_html_e( 'Zalecane działania:', 'meta-description-handler' ); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e( 'Dezaktywuj jedną z wtyczek SEO, aby uniknąć konfliktów.', 'meta-description-handler' ); ?></li>
                <li>
                    <?php
                    printf(
                        __( 'Lub przejdź do <a href="%s">ustawień Meta Description Handler</a>, aby wymusić generowanie tagów (zaawansowane).', 'meta-description-handler' ),
                        esc_url( admin_url( 'admin.php?page=meta-description-handler' ) )
                    );
                    ?>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * AJAX handler to dismiss the conflict notice
     */
    public function ajax_dismiss_conflict_notice() {
        check_ajax_referer( 'mdh_dismiss_conflict', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $conflicts = MDH_Helpers::detect_seo_conflicts();

        update_user_meta( get_current_user_id(), 'mdh_conflict_notice_dismissed', true );
        update_user_meta( get_current_user_id(), 'mdh_conflict_notice_plugins', implode( ',', $conflicts ) );

        wp_send_json_success();
    }

    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('mdh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'meta-description-handler'));
        }
        
        $settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        $settings = $this->sanitize_settings($settings);
        
        $current_settings = MDH_Helpers::get_settings();
        $merged_settings = array_merge($current_settings, $settings);
        
        MDH_Helpers::update_settings($merged_settings);
        
        wp_send_json_success(__('Ustawienia zapisane pomyślnie.', 'meta-description-handler'));
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
                <p><?php esc_html_e('Skonfiguruj sposób generowania meta tytułów i opisów dla Twojej witryny.', 'meta-description-handler'); ?></p>
            </div>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                <input type="hidden" name="mdh_settings[_page_has_post_types]" value="1">
                <input type="hidden" name="mdh_settings[_page_has_taxonomies]" value="1">
                <input type="hidden" name="mdh_settings[_page_has_sitemap]" value="1">
                <input type="hidden" name="mdh_settings[_page_has_force_output]" value="1">
                <input type="hidden" name="mdh_settings[_page_has_autogenerate]" value="1">

                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#general" class="mdh-tab-link active"><?php esc_html_e('Ogólne', 'meta-description-handler'); ?></a>
                        <a href="#homepage" class="mdh-tab-link"><?php esc_html_e('Strona główna', 'meta-description-handler'); ?></a>
                        <a href="#defaults" class="mdh-tab-link"><?php esc_html_e('Formaty tytułów', 'meta-description-handler'); ?></a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- General Tab -->
                        <div id="general" class="mdh-tab-panel active">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="title_separator"><?php esc_html_e('Separator tytułu', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="title_separator" name="mdh_settings[title_separator]"
                                               value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e('Znak(i) używane do rozdzielania części tytułu.', 'meta-description-handler'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Opisy generowane automatycznie', 'meta-description-handler'); ?>
                                    </th>
                                    <td>
                                        <label class="mdh-checkbox-label">
                                            <input type="checkbox" name="mdh_settings[autogenerate_description]" value="1"
                                                   <?php checked(!isset($settings['autogenerate_description']) || $settings['autogenerate_description']); ?>>
                                            <?php esc_html_e('Gdy brak własnego opisu, użyj zajawki lub początku treści', 'meta-description-handler'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Wyłącz, jeśli wolisz brak znacznika opisu od opisu sklejonego z treści — wyszukiwarka dobierze wtedy własny fragment.', 'meta-description-handler'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3><?php esc_html_e('Włączone typy wpisów', 'meta-description-handler'); ?></h3>
                            <p class="description"><?php esc_html_e('Wybierz, które typy wpisów powinny mieć włączone pola meta.', 'meta-description-handler'); ?></p>
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
                            
                            <h3><?php esc_html_e('Włączone taksonomie', 'meta-description-handler'); ?></h3>
                            <p class="description"><?php esc_html_e('Wybierz, które taksonomie powinny mieć włączone pola meta.', 'meta-description-handler'); ?></p>
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

                            <h3><?php esc_html_e('Mapa witryny XML', 'meta-description-handler'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="sitemap_enabled"><?php esc_html_e('Mapa witryny', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="sitemap_enabled"
                                                   name="mdh_settings[sitemap_enabled]"
                                                   value="1"
                                                   <?php checked($settings['sitemap_enabled'] ?? true, true); ?>>
                                            <?php esc_html_e('Włącz wbudowaną mapę witryny WordPress', 'meta-description-handler'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Strony oznaczone jako noindex oraz wyłączone typy wpisów/taksonomie są automatycznie wykluczane z mapy witryny. Odznacz, aby całkowicie wyłączyć mapę witryny.', 'meta-description-handler'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <?php $seo_conflicts = MDH_Helpers::detect_seo_conflicts(); ?>
                            <?php if ( ! empty( $seo_conflicts ) ) : ?>
                                <h3><?php esc_html_e( 'Wykrywanie konfliktów SEO', 'meta-description-handler' ); ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="force_frontend_output">
                                                <?php esc_html_e( 'Wymuś generowanie tagów', 'meta-description-handler' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <label>
                                                <input type="checkbox" id="force_frontend_output"
                                                       name="mdh_settings[force_frontend_output]"
                                                       value="1"
                                                       <?php checked( $settings['force_frontend_output'] ?? false, true ); ?>>
                                                <?php esc_html_e( 'Generuj tagi meta nawet gdy wykryto inną wtyczkę SEO (zaawansowane)', 'meta-description-handler' ); ?>
                                            </label>
                                            <p class="description">
                                                <?php esc_html_e( 'Włącz tę opcję tylko jeśli wiesz co robisz. Może powodować duplikowanie meta tagów.', 'meta-description-handler' ); ?>
                                            </p>
                                            <p class="description" style="margin-top: 8px;">
                                                <?php
                                                printf(
                                                    __( 'Obecnie wykryto: %s', 'meta-description-handler' ),
                                                    '<strong>' . esc_html( implode( ', ', $seo_conflicts ) ) . '</strong>'
                                                );
                                                ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Homepage Tab -->
                        <div id="homepage" class="mdh-tab-panel">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="homepage_title"><?php esc_html_e('Tytuł strony głównej', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="homepage_title" name="mdh_settings[homepage_title]" 
                                               value="<?php echo esc_attr($settings['homepage_title'] ?? ''); ?>" class="large-text mdh-title-input">
                                        <div class="mdh-char-counter" data-type="title">
                                            <span class="mdh-char-count">0</span>/580px
                                        </div>
                                        <p class="description"><?php esc_html_e('Pozostaw puste, aby użyć domyślnego tytułu witryny.', 'meta-description-handler'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="homepage_description"><?php esc_html_e('Opis strony głównej', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="homepage_description" name="mdh_settings[homepage_description]" 
                                                  rows="3" class="large-text mdh-description-input"><?php echo esc_textarea($settings['homepage_description'] ?? ''); ?></textarea>
                                        <div class="mdh-char-counter" data-type="description">
                                            <span class="mdh-char-count">0</span>/920px
                                        </div>
                                        <p class="description"><?php esc_html_e('Pozostaw puste, aby użyć opisu witryny (tagline).', 'meta-description-handler'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Title Formats Tab -->
                        <div id="defaults" class="mdh-tab-panel">
                            <div class="mdh-notice mdh-notice-info">
                                <p><strong><?php esc_html_e('Dostępne znaczniki:', 'meta-description-handler'); ?></strong></p>
                                <code>%post_title%</code>, <code>%site_title%</code>, <code>%site_description%</code>, 
                                <code>%separator%</code>, <code>%archive_title%</code>, <code>%term_title%</code>, 
                                <code>%search_query%</code>, <code>%author_name%</code>, <code>%current_year%</code>, 
                                <code>%page_number%</code>
                            </div>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_post_title_format"><?php esc_html_e('Format tytułu wpisu', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_post_title_format" name="mdh_settings[default_post_title_format]" 
                                               value="<?php echo esc_attr($settings['default_post_title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_page_title_format"><?php esc_html_e('Format tytułu strony', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_page_title_format" name="mdh_settings[default_page_title_format]" 
                                               value="<?php echo esc_attr($settings['default_page_title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_archive_title_format"><?php esc_html_e('Format tytułu archiwum', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_archive_title_format" name="mdh_settings[default_archive_title_format]" 
                                               value="<?php echo esc_attr($settings['default_archive_title_format'] ?? '%archive_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_taxonomy_title_format"><?php esc_html_e('Format tytułu taksonomii', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_taxonomy_title_format" name="mdh_settings[default_taxonomy_title_format]" 
                                               value="<?php echo esc_attr($settings['default_taxonomy_title_format'] ?? '%term_title% %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_search_title_format"><?php esc_html_e('Format tytułu wyników wyszukiwania', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_search_title_format" name="mdh_settings[default_search_title_format]" 
                                               value="<?php echo esc_attr($settings['default_search_title_format'] ?? 'Wyniki wyszukiwania dla "%search_query%" %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_title"><?php esc_html_e('Tytuł strony 404', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_404_title" name="mdh_settings[default_404_title]" 
                                               value="<?php echo esc_attr($settings['default_404_title'] ?? 'Strona nie została znaleziona %separator% %site_title%'); ?>" 
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_description"><?php esc_html_e('Opis strony 404', 'meta-description-handler'); ?></label>
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
                
                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
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
            <h1><?php esc_html_e('Ustawienia typów wpisów', 'meta-description-handler'); ?></h1>

            <p><?php esc_html_e('Skonfiguruj domyślne formaty tytułów i opisy dla każdego typu wpisu.', 'meta-description-handler'); ?></p>
            
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
                                <h2><?php printf(__('Ustawienia: %s', 'meta-description-handler'), esc_html($pt->labels->singular_name)); ?></h2>

                                <?php if (!in_array($pt->name, $enabled_post_types)): ?>
                                    <div class="mdh-notice mdh-notice-warning">
                                        <p><?php esc_html_e('Ten typ wpisu nie jest włączony. Włącz go w Ustawieniach ogólnych, aby używać pól meta.', 'meta-description-handler'); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="pt_title_format_<?php echo esc_attr($pt->name); ?>">
                                                <?php esc_html_e('Domyślny format tytułu', 'meta-description-handler'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="pt_title_format_<?php echo esc_attr($pt->name); ?>" 
                                                   name="mdh_settings[post_type_settings][<?php echo esc_attr($pt->name); ?>][title_format]" 
                                                   value="<?php echo esc_attr($pt_settings['title_format'] ?? '%post_title% %separator% %site_title%'); ?>" 
                                                   class="large-text">
                                            <p class="description">
                                                <?php esc_html_e('Dostępne:', 'meta-description-handler'); ?> <code>%post_title%</code>, <code>%site_title%</code>, <code>%separator%</code>, <code>%post_excerpt%</code>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="pt_default_desc_<?php echo esc_attr($pt->name); ?>">
                                                <?php esc_html_e('Domyślny opis', 'meta-description-handler'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <textarea id="pt_default_desc_<?php echo esc_attr($pt->name); ?>"
                                                      name="mdh_settings[post_type_settings][<?php echo esc_attr($pt->name); ?>][default_description]"
                                                      rows="3" class="large-text"><?php echo esc_textarea($pt_settings['default_description'] ?? ''); ?></textarea>
                                            <p class="description"><?php esc_html_e('Używany gdy nie ustawiono niestandardowego opisu. Pozostaw puste, aby użyć zajawki lub automatycznie wygenerowanej treści.', 'meta-description-handler'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php if ($pt->has_archive): ?>
                                    <h3><?php esc_html_e('Ustawienia strony archiwum', 'meta-description-handler'); ?></h3>
                                    <?php 
                                    $archive_settings = $settings['archive_settings'][$pt->name] ?? array();
                                    ?>
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="archive_title_<?php echo esc_attr($pt->name); ?>">
                                                    <?php esc_html_e('Tytuł archiwum', 'meta-description-handler'); ?>
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
                                                    <?php esc_html_e('Opis archiwum', 'meta-description-handler'); ?>
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
                
                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
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
            <h1><?php esc_html_e('Ustawienia taksonomii', 'meta-description-handler'); ?></h1>

            <p><?php esc_html_e('Skonfiguruj domyślne formaty tytułów i opisy dla każdej taksonomii.', 'meta-description-handler'); ?></p>
            
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
                                <h2><?php printf(__('Ustawienia: %s', 'meta-description-handler'), esc_html($tax->labels->singular_name)); ?></h2>

                                <?php if (!in_array($tax->name, $enabled_taxonomies)): ?>
                                    <div class="mdh-notice mdh-notice-warning">
                                        <p><?php esc_html_e('Ta taksonomia nie jest włączona. Włącz ją w Ustawieniach ogólnych, aby używać pól meta.', 'meta-description-handler'); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="tax_title_format_<?php echo esc_attr($tax->name); ?>">
                                                <?php esc_html_e('Domyślny format tytułu', 'meta-description-handler'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="tax_title_format_<?php echo esc_attr($tax->name); ?>"
                                                   name="mdh_settings[taxonomy_settings][<?php echo esc_attr($tax->name); ?>][title_format]"
                                                   value="<?php echo esc_attr($tax_settings['title_format'] ?? '%term_title% %separator% %site_title%'); ?>"
                                                   class="large-text">
                                            <p class="description">
                                                <?php esc_html_e('Dostępne:', 'meta-description-handler'); ?> <code>%term_title%</code>, <code>%term_description%</code>, <code>%site_title%</code>, <code>%separator%</code>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="tax_default_desc_<?php echo esc_attr($tax->name); ?>">
                                                <?php esc_html_e('Domyślny opis', 'meta-description-handler'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <textarea id="tax_default_desc_<?php echo esc_attr($tax->name); ?>"
                                                      name="mdh_settings[taxonomy_settings][<?php echo esc_attr($tax->name); ?>][default_description]"
                                                      rows="3" class="large-text"><?php echo esc_textarea($tax_settings['default_description'] ?? ''); ?></textarea>
                                            <p class="description"><?php esc_html_e('Używany gdy nie ustawiono niestandardowego opisu dla terminu.', 'meta-description-handler'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
                
                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
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
            <h1><?php esc_html_e('Ustawienia archiwów', 'meta-description-handler'); ?></h1>

            <p><?php esc_html_e('Skonfiguruj meta tytuły i opisy dla stron archiwów.', 'meta-description-handler'); ?></p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#date-archives" class="mdh-tab-link active"><?php esc_html_e('Archiwa dat', 'meta-description-handler'); ?></a>
                        <a href="#author-archives" class="mdh-tab-link"><?php esc_html_e('Archiwa autorów', 'meta-description-handler'); ?></a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- Date Archives -->
                        <div id="date-archives" class="mdh-tab-panel active">
                            <h2><?php esc_html_e('Ustawienia archiwów dat', 'meta-description-handler'); ?></h2>

                            <h3><?php esc_html_e('Archiwa roczne', 'meta-description-handler'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_yearly_title"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                                        <label for="archive_yearly_desc"><?php esc_html_e('Opis', 'meta-description-handler'); ?></label>
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
                            
                            <h3><?php esc_html_e('Archiwa miesięczne', 'meta-description-handler'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_monthly_title"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                                        <label for="archive_monthly_desc"><?php esc_html_e('Opis', 'meta-description-handler'); ?></label>
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
                            
                            <h3><?php esc_html_e('Archiwa dzienne', 'meta-description-handler'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_daily_title"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                                        <label for="archive_daily_desc"><?php esc_html_e('Opis', 'meta-description-handler'); ?></label>
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
                            <h2><?php esc_html_e('Ustawienia archiwów autorów', 'meta-description-handler'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="archive_author_title"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                                        <label for="archive_author_desc"><?php esc_html_e('Format opisu', 'meta-description-handler'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="archive_author_desc" 
                                                  name="mdh_settings[archive_settings][author][description]" 
                                                  rows="3" class="large-text"><?php echo esc_textarea($archive_settings['author']['description'] ?? 'Wszystkie artykuły napisane przez %author_name%.'); ?></textarea>
                                        <p class="description"><?php esc_html_e('Użyj %author_name% aby wstawić nazwę autora.', 'meta-description-handler'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render Social Media settings page
     */
    public function render_social_page() {
        $settings = MDH_Helpers::get_settings();
        $og_image = $settings['default_og_image'] ?? '';
        $og_image_id = $settings['default_og_image_id'] ?? 0;
        $twitter_site = $settings['twitter_site'] ?? '';
        ?>
        <div class="wrap mdh-admin-wrap">
            <h1><?php esc_html_e('Ustawienia Social Media', 'meta-description-handler'); ?></h1>

            <p><?php esc_html_e('Skonfiguruj domyślne ustawienia Open Graph i profili społecznościowych.', 'meta-description-handler'); ?></p>

            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>

                <h2><?php esc_html_e('Domyślny obrazek Open Graph', 'meta-description-handler'); ?></h2>
                <p class="description"><?php esc_html_e('Ten obrazek będzie używany jako domyślny og:image gdy strona nie ma wyróżnionego obrazka. Zalecany rozmiar: 1200x630 pikseli.', 'meta-description-handler'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Domyślny obrazek OG', 'meta-description-handler'); ?></label>
                        </th>
                        <td>
                            <div class="mdh-og-image-preview" id="mdh-og-image-preview">
                                <?php if (!empty($og_image)): ?>
                                    <img src="<?php echo esc_url($og_image); ?>" style="max-width: 400px; height: auto; display: block; margin-bottom: 10px;">
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="mdh_default_og_image" name="mdh_settings[default_og_image]" value="<?php echo esc_attr($og_image); ?>">
                            <input type="hidden" id="mdh_default_og_image_id" name="mdh_settings[default_og_image_id]" value="<?php echo esc_attr($og_image_id); ?>">
                            <button type="button" class="button" id="mdh-upload-og-image"><?php esc_html_e('Wybierz obrazek', 'meta-description-handler'); ?></button>
                            <?php if (!empty($og_image)): ?>
                                <button type="button" class="button" id="mdh-remove-og-image"><?php esc_html_e('Usuń obrazek', 'meta-description-handler'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Konta społecznościowe', 'meta-description-handler'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="twitter_site">Twitter / X</label>
                        </th>
                        <td>
                            <input type="text" id="twitter_site" name="mdh_settings[twitter_site]"
                                   value="<?php echo esc_attr($twitter_site); ?>"
                                   class="regular-text" placeholder="@nazwa_konta">
                            <p class="description"><?php echo wp_kses( __('Nazwa konta Twitter/X (np. @mojafirma). Zostanie dodany jako tag <code>twitter:site</code>.', 'meta-description-handler'), array( 'code' => array() ) ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
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
            <h1><?php esc_html_e('Ustawienia stron specjalnych', 'meta-description-handler'); ?></h1>

            <p><?php esc_html_e('Skonfiguruj meta tytuły i opisy dla specjalnych stron WordPress.', 'meta-description-handler'); ?></p>
            
            <form method="post" action="options.php" class="mdh-settings-form">
                <?php settings_fields('mdh_settings_group'); ?>
                
                <input type="hidden" name="mdh_settings[title_separator]" value="<?php echo esc_attr($settings['title_separator'] ?? '|'); ?>">
                
                <div class="mdh-tabs">
                    <nav class="mdh-tabs-nav">
                        <a href="#search-page" class="mdh-tab-link active"><?php esc_html_e('Wyniki wyszukiwania', 'meta-description-handler'); ?></a>
                        <a href="#404-page" class="mdh-tab-link"><?php esc_html_e('Strona 404', 'meta-description-handler'); ?></a>
                    </nav>
                    
                    <div class="mdh-tabs-content">
                        <!-- Search Results -->
                        <div id="search-page" class="mdh-tab-panel active">
                            <h2><?php esc_html_e('Strona wyników wyszukiwania', 'meta-description-handler'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_search_title_format"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                            <h2><?php esc_html_e('Strona błędu 404', 'meta-description-handler'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_404_title"><?php esc_html_e('Format tytułu', 'meta-description-handler'); ?></label>
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
                                        <label for="default_404_description"><?php esc_html_e('Opis', 'meta-description-handler'); ?></label>
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
                
                <?php submit_button(__('Zapisz zmiany', 'meta-description-handler')); ?>
            </form>
        </div>
        <?php
    }
}
