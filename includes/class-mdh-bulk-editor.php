<?php
/**
 * Bulk Meta Editor Class
 * Allows editing meta data for all posts, pages, CPTs, and taxonomies in one place
 */

if (!defined('ABSPATH')) {
    exit;
}

class MDH_Bulk_Editor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('wp_ajax_mdh_bulk_save_meta', array($this, 'ajax_save_meta'));
        add_action('wp_ajax_mdh_load_items', array($this, 'ajax_load_items'));
    }
    
    /**
     * Add submenu page
     */
    public function add_submenu_page() {
        add_submenu_page(
            'meta-description-handler',
            __('Edytor zbiorczy', 'meta-description-handler'),
            __('Edytor zbiorczy', 'meta-description-handler'),
            'manage_options',
            'mdh-bulk-editor',
            array($this, 'render_page')
        );
    }
    
    /**
     * Render bulk editor page
     */
    public function render_page() {
        $settings = MDH_Helpers::get_settings();
        $enabled_post_types = $settings['enabled_post_types'] ?? array('post', 'page');
        $enabled_taxonomies = $settings['enabled_taxonomies'] ?? array('category', 'post_tag');
        
        $post_types = MDH_Helpers::get_public_post_types();
        $taxonomies = MDH_Helpers::get_public_taxonomies();
        ?>
        <div class="wrap mdh-admin-wrap mdh-bulk-editor">
            <h1><?php _e('Edytor zbiorczy meta danych', 'meta-description-handler'); ?></h1>
            
            <p class="mdh-description">
                <?php _e('Zarządzaj meta tytułami i opisami wszystkich treści w jednym miejscu. Kliknij na wiersz, aby edytować.', 'meta-description-handler'); ?>
            </p>
            
            <div class="mdh-bulk-filters">
                <div class="mdh-filter-group">
                    <label for="mdh-content-type"><?php _e('Typ treści:', 'meta-description-handler'); ?></label>
                    <select id="mdh-content-type">
                        <optgroup label="<?php _e('Typy wpisów', 'meta-description-handler'); ?>">
                            <?php foreach ($post_types as $pt): 
                                if (!in_array($pt->name, $enabled_post_types)) continue;
                            ?>
                                <option value="post_type:<?php echo esc_attr($pt->name); ?>">
                                    <?php echo esc_html($pt->labels->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="<?php _e('Taksonomie', 'meta-description-handler'); ?>">
                            <?php foreach ($taxonomies as $tax): 
                                if (!in_array($tax->name, $enabled_taxonomies)) continue;
                            ?>
                                <option value="taxonomy:<?php echo esc_attr($tax->name); ?>">
                                    <?php echo esc_html($tax->labels->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <div class="mdh-filter-group">
                    <label for="mdh-filter-status"><?php _e('Status meta:', 'meta-description-handler'); ?></label>
                    <select id="mdh-filter-status">
                        <option value="all"><?php _e('Wszystkie', 'meta-description-handler'); ?></option>
                        <option value="with-meta"><?php _e('Z ustawionym meta', 'meta-description-handler'); ?></option>
                        <option value="without-meta"><?php _e('Bez meta', 'meta-description-handler'); ?></option>
                    </select>
                </div>
                
                <div class="mdh-filter-group">
                    <label for="mdh-search"><?php _e('Szukaj:', 'meta-description-handler'); ?></label>
                    <input type="text" id="mdh-search" placeholder="<?php _e('Wpisz tytuł...', 'meta-description-handler'); ?>">
                </div>
                
                <button type="button" id="mdh-load-items" class="button button-primary">
                    <?php _e('Załaduj', 'meta-description-handler'); ?>
                </button>
            </div>
            
            <div class="mdh-bulk-table-wrap">
                <table class="mdh-bulk-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="mdh-col-title"><?php _e('Tytuł', 'meta-description-handler'); ?></th>
                            <th class="mdh-col-meta-title"><?php _e('Meta tytuł', 'meta-description-handler'); ?></th>
                            <th class="mdh-col-meta-desc"><?php _e('Meta opis', 'meta-description-handler'); ?></th>
                            <th class="mdh-col-status"><?php _e('Status', 'meta-description-handler'); ?></th>
                            <th class="mdh-col-actions"><?php _e('Akcje', 'meta-description-handler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="mdh-items-list">
                        <tr class="mdh-no-items">
                            <td colspan="5"><?php _e('Wybierz typ treści i kliknij "Załaduj" aby wyświetlić elementy.', 'meta-description-handler'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="mdh-pagination" id="mdh-pagination"></div>
            </div>
            
            <!-- Edit Modal -->
            <div id="mdh-edit-modal" class="mdh-modal" style="display:none;">
                <div class="mdh-modal-content">
                    <div class="mdh-modal-header">
                        <h2><?php _e('Edytuj meta dane', 'meta-description-handler'); ?></h2>
                        <button type="button" class="mdh-modal-close">&times;</button>
                    </div>
                    <div class="mdh-modal-body">
                        <input type="hidden" id="mdh-edit-id">
                        <input type="hidden" id="mdh-edit-type">
                        
                        <div class="mdh-modal-preview">
                            <h4><?php _e('Podgląd Google:', 'meta-description-handler'); ?></h4>
                            <div class="mdh-serp-preview">
                                <div class="mdh-serp-title" id="mdh-modal-preview-title"></div>
                                <div class="mdh-serp-url" id="mdh-modal-preview-url"></div>
                                <div class="mdh-serp-description" id="mdh-modal-preview-desc"></div>
                            </div>
                        </div>
                        
                        <div class="mdh-form-group">
                            <label for="mdh-edit-meta-title"><?php _e('Meta tytuł', 'meta-description-handler'); ?></label>
                            <input type="text" id="mdh-edit-meta-title" class="widefat mdh-title-input">
                            <div class="mdh-char-counter" data-type="title">
                                <span class="mdh-char-count">0</span>/580px
                            </div>
                        </div>
                        
                        <div class="mdh-form-group">
                            <label for="mdh-edit-meta-description"><?php _e('Meta opis', 'meta-description-handler'); ?></label>
                            <textarea id="mdh-edit-meta-description" class="widefat mdh-description-input" rows="4"></textarea>
                            <div class="mdh-char-counter" data-type="description">
                                <span class="mdh-char-count">0</span>/920px
                            </div>
                        </div>
                        
                        <div class="mdh-form-group mdh-robots-options">
                            <label>
                                <input type="checkbox" id="mdh-edit-noindex">
                                <?php _e('NoIndex - Nie indeksuj tej strony', 'meta-description-handler'); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="mdh-edit-nofollow">
                                <?php _e('NoFollow - Nie śledź linków na tej stronie', 'meta-description-handler'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="mdh-modal-footer">
                        <button type="button" class="button mdh-modal-cancel"><?php _e('Anuluj', 'meta-description-handler'); ?></button>
                        <button type="button" class="button button-primary" id="mdh-save-meta">
                            <?php _e('Zapisz zmiany', 'meta-description-handler'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var currentPage = 1;
            var totalPages = 1;
            
            // Load items
            function loadItems(page) {
                page = page || 1;
                var contentType = $('#mdh-content-type').val();
                var filterStatus = $('#mdh-filter-status').val();
                var search = $('#mdh-search').val();
                
                $('#mdh-items-list').html('<tr><td colspan="5" class="mdh-loading-row"><?php _e('Ładowanie...', 'meta-description-handler'); ?></td></tr>');
                
                $.ajax({
                    url: mdhAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mdh_load_items',
                        nonce: mdhAdmin.nonce,
                        content_type: contentType,
                        filter_status: filterStatus,
                        search: search,
                        page: page
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mdh-items-list').html(response.data.html);
                            currentPage = response.data.current_page;
                            totalPages = response.data.total_pages;
                            updatePagination();
                        } else {
                            $('#mdh-items-list').html('<tr><td colspan="5">' + response.data + '</td></tr>');
                        }
                    }
                });
            }
            
            // Update pagination
            function updatePagination() {
                var html = '';
                if (totalPages > 1) {
                    html += '<span class="mdh-page-info"><?php _e('Strona', 'meta-description-handler'); ?> ' + currentPage + ' <?php _e('z', 'meta-description-handler'); ?> ' + totalPages + '</span>';
                    
                    if (currentPage > 1) {
                        html += '<button type="button" class="button mdh-page-btn" data-page="' + (currentPage - 1) + '">&laquo; <?php _e('Poprzednia', 'meta-description-handler'); ?></button>';
                    }
                    if (currentPage < totalPages) {
                        html += '<button type="button" class="button mdh-page-btn" data-page="' + (currentPage + 1) + '"><?php _e('Następna', 'meta-description-handler'); ?> &raquo;</button>';
                    }
                }
                $('#mdh-pagination').html(html);
            }
            
            // Event handlers
            $('#mdh-load-items').on('click', function() {
                loadItems(1);
            });
            
            $(document).on('click', '.mdh-page-btn', function() {
                loadItems($(this).data('page'));
            });
            
            // Edit item
            $(document).on('click', '.mdh-edit-btn', function() {
                var $row = $(this).closest('tr');
                var id = $row.data('id');
                var type = $row.data('type');
                var title = $row.data('title');
                var url = $row.data('url');
                var metaTitle = $row.data('meta-title') || '';
                var metaDesc = $row.data('meta-description') || '';
                var noindex = $row.data('noindex') == '1';
                var nofollow = $row.data('nofollow') == '1';
                
                $('#mdh-edit-id').val(id);
                $('#mdh-edit-type').val(type);
                $('#mdh-edit-meta-title').val(metaTitle);
                $('#mdh-edit-meta-description').val(metaDesc);
                $('#mdh-edit-noindex').prop('checked', noindex);
                $('#mdh-edit-nofollow').prop('checked', nofollow);
                
                $('#mdh-modal-preview-title').text(metaTitle || title);
                $('#mdh-modal-preview-url').text(url);
                $('#mdh-modal-preview-desc').text(metaDesc || '<?php _e('Brak opisu...', 'meta-description-handler'); ?>');
                
                updateModalCounters();
                
                $('#mdh-edit-modal').fadeIn(200);
            });
            
            // Update modal counters
            function updateModalCounters() {
                var titleLen = $('#mdh-edit-meta-title').val().length;
                var descLen = $('#mdh-edit-meta-description').val().length;
                
                $('#mdh-edit-modal .mdh-char-counter[data-type="title"] .mdh-char-count').text(titleLen);
                $('#mdh-edit-modal .mdh-char-counter[data-type="description"] .mdh-char-count').text(descLen);
            }
            
            // Live preview update
            $('#mdh-edit-meta-title').on('input', function() {
                var val = $(this).val();
                $('#mdh-modal-preview-title').text(val || '<?php _e('Tytuł strony', 'meta-description-handler'); ?>');
                updateModalCounters();
            });
            
            $('#mdh-edit-meta-description').on('input', function() {
                var val = $(this).val();
                $('#mdh-modal-preview-desc').text(val || '<?php _e('Brak opisu...', 'meta-description-handler'); ?>');
                updateModalCounters();
            });
            
            // Close modal
            $('.mdh-modal-close, .mdh-modal-cancel').on('click', function() {
                $('#mdh-edit-modal').fadeOut(200);
            });
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#mdh-edit-modal').fadeOut(200);
                }
            });
            
            // Save meta
            $('#mdh-save-meta').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Zapisywanie...', 'meta-description-handler'); ?>');
                
                $.ajax({
                    url: mdhAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mdh_bulk_save_meta',
                        nonce: mdhAdmin.nonce,
                        id: $('#mdh-edit-id').val(),
                        type: $('#mdh-edit-type').val(),
                        meta_title: $('#mdh-edit-meta-title').val(),
                        meta_description: $('#mdh-edit-meta-description').val(),
                        noindex: $('#mdh-edit-noindex').is(':checked') ? 1 : 0,
                        nofollow: $('#mdh-edit-nofollow').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php _e('Zapisz zmiany', 'meta-description-handler'); ?>');
                        
                        if (response.success) {
                            $('#mdh-edit-modal').fadeOut(200);
                            loadItems(currentPage);
                            MDHAdmin.showToast('<?php _e('Meta dane zostały zapisane!', 'meta-description-handler'); ?>');
                        } else {
                            alert(response.data || '<?php _e('Błąd podczas zapisywania.', 'meta-description-handler'); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php _e('Zapisz zmiany', 'meta-description-handler'); ?>');
                        alert('<?php _e('Błąd połączenia.', 'meta-description-handler'); ?>');
                    }
                });
            });
            
            // Initial load
            loadItems(1);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX load items
     */
    public function ajax_load_items() {
        check_ajax_referer('mdh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'meta-description-handler'));
        }
        
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';
        $filter_status = isset($_POST['filter_status']) ? sanitize_text_field($_POST['filter_status']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 20;
        
        list($type, $name) = explode(':', $content_type);
        
        $html = '';
        $total_items = 0;
        
        if ($type === 'post_type') {
            $args = array(
                'post_type' => $name,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC',
            );
            
            if (!empty($search)) {
                $args['s'] = $search;
            }
            
            // Filter by meta status
            if ($filter_status === 'with-meta') {
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_mdh_meta_title',
                        'value' => '',
                        'compare' => '!='
                    ),
                    array(
                        'key' => '_mdh_meta_description',
                        'value' => '',
                        'compare' => '!='
                    ),
                );
            } elseif ($filter_status === 'without-meta') {
                $args['meta_query'] = array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => '_mdh_meta_title',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => '_mdh_meta_title',
                            'value' => '',
                            'compare' => '='
                        ),
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => '_mdh_meta_description',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => '_mdh_meta_description',
                            'value' => '',
                            'compare' => '='
                        ),
                    ),
                );
            }
            
            $query = new WP_Query($args);
            $total_items = $query->found_posts;
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $meta_title = get_post_meta($post_id, '_mdh_meta_title', true);
                    $meta_description = get_post_meta($post_id, '_mdh_meta_description', true);
                    $noindex = get_post_meta($post_id, '_mdh_robots_noindex', true);
                    $nofollow = get_post_meta($post_id, '_mdh_robots_nofollow', true);
                    
                    $has_meta = !empty($meta_title) || !empty($meta_description);
                    $status_class = $has_meta ? 'mdh-status-set' : 'mdh-status-empty';
                    $status_text = $has_meta ? __('Ustawione', 'meta-description-handler') : __('Brak', 'meta-description-handler');
                    
                    $html .= sprintf(
                        '<tr data-id="%d" data-type="post" data-title="%s" data-url="%s" data-meta-title="%s" data-meta-description="%s" data-noindex="%s" data-nofollow="%s">
                            <td class="mdh-col-title">
                                <strong>%s</strong>
                                <div class="row-actions">
                                    <a href="%s" target="_blank">%s</a> | 
                                    <a href="%s">%s</a>
                                </div>
                            </td>
                            <td class="mdh-col-meta-title">%s</td>
                            <td class="mdh-col-meta-desc">%s</td>
                            <td class="mdh-col-status"><span class="%s">%s</span></td>
                            <td class="mdh-col-actions">
                                <button type="button" class="button button-small mdh-edit-btn">%s</button>
                            </td>
                        </tr>',
                        $post_id,
                        esc_attr(get_the_title()),
                        esc_attr(get_permalink()),
                        esc_attr($meta_title),
                        esc_attr($meta_description),
                        esc_attr($noindex),
                        esc_attr($nofollow),
                        esc_html(get_the_title()),
                        esc_url(get_permalink()),
                        __('Zobacz', 'meta-description-handler'),
                        esc_url(get_edit_post_link()),
                        __('Edytuj', 'meta-description-handler'),
                        esc_html($meta_title ? MDH_Helpers::truncate($meta_title, 40) : '—'),
                        esc_html($meta_description ? MDH_Helpers::truncate($meta_description, 50) : '—'),
                        $status_class,
                        $status_text,
                        __('Edytuj meta', 'meta-description-handler')
                    );
                }
                wp_reset_postdata();
            } else {
                $html = '<tr><td colspan="5">' . __('Nie znaleziono elementów.', 'meta-description-handler') . '</td></tr>';
            }
            
        } elseif ($type === 'taxonomy') {
            $args = array(
                'taxonomy' => $name,
                'hide_empty' => false,
                'number' => $per_page,
                'offset' => ($page - 1) * $per_page,
                'orderby' => 'name',
                'order' => 'ASC',
            );
            
            if (!empty($search)) {
                $args['search'] = $search;
            }
            
            $terms = get_terms($args);
            
            // Get total count
            $count_args = array(
                'taxonomy' => $name,
                'hide_empty' => false,
            );
            if (!empty($search)) {
                $count_args['search'] = $search;
            }
            $all_terms = get_terms($count_args);
            $total_items = is_array($all_terms) ? count($all_terms) : 0;
            
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $meta_title = get_term_meta($term->term_id, '_mdh_meta_title', true);
                    $meta_description = get_term_meta($term->term_id, '_mdh_meta_description', true);
                    $noindex = get_term_meta($term->term_id, '_mdh_robots_noindex', true);
                    
                    // Filter by status
                    $has_meta = !empty($meta_title) || !empty($meta_description);
                    if ($filter_status === 'with-meta' && !$has_meta) continue;
                    if ($filter_status === 'without-meta' && $has_meta) continue;
                    
                    $status_class = $has_meta ? 'mdh-status-set' : 'mdh-status-empty';
                    $status_text = $has_meta ? __('Ustawione', 'meta-description-handler') : __('Brak', 'meta-description-handler');
                    
                    $html .= sprintf(
                        '<tr data-id="%d" data-type="term" data-title="%s" data-url="%s" data-meta-title="%s" data-meta-description="%s" data-noindex="%s" data-nofollow="">
                            <td class="mdh-col-title">
                                <strong>%s</strong>
                                <div class="row-actions">
                                    <a href="%s" target="_blank">%s</a> | 
                                    <a href="%s">%s</a>
                                </div>
                            </td>
                            <td class="mdh-col-meta-title">%s</td>
                            <td class="mdh-col-meta-desc">%s</td>
                            <td class="mdh-col-status"><span class="%s">%s</span></td>
                            <td class="mdh-col-actions">
                                <button type="button" class="button button-small mdh-edit-btn">%s</button>
                            </td>
                        </tr>',
                        $term->term_id,
                        esc_attr($term->name),
                        esc_attr(get_term_link($term)),
                        esc_attr($meta_title),
                        esc_attr($meta_description),
                        esc_attr($noindex),
                        esc_html($term->name),
                        esc_url(get_term_link($term)),
                        __('Zobacz', 'meta-description-handler'),
                        esc_url(get_edit_term_link($term->term_id, $name)),
                        __('Edytuj', 'meta-description-handler'),
                        esc_html($meta_title ? MDH_Helpers::truncate($meta_title, 40) : '—'),
                        esc_html($meta_description ? MDH_Helpers::truncate($meta_description, 50) : '—'),
                        $status_class,
                        $status_text,
                        __('Edytuj meta', 'meta-description-handler')
                    );
                }
            }
            
            if (empty($html)) {
                $html = '<tr><td colspan="5">' . __('Nie znaleziono elementów.', 'meta-description-handler') . '</td></tr>';
            }
        }
        
        $total_pages = ceil($total_items / $per_page);
        
        wp_send_json_success(array(
            'html' => $html,
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'current_page' => $page,
        ));
    }
    
    /**
     * AJAX save meta
     */
    public function ajax_save_meta() {
        check_ajax_referer('mdh_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'meta-description-handler'));
        }
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $meta_title = isset($_POST['meta_title']) ? MDH_Helpers::sanitize_title($_POST['meta_title']) : '';
        $meta_description = isset($_POST['meta_description']) ? MDH_Helpers::sanitize_description($_POST['meta_description']) : '';
        $noindex = isset($_POST['noindex']) && $_POST['noindex'] == '1' ? '1' : '';
        $nofollow = isset($_POST['nofollow']) && $_POST['nofollow'] == '1' ? '1' : '';
        
        if (!$id || !$type) {
            wp_send_json_error(__('Nieprawidłowe dane.', 'meta-description-handler'));
        }
        
        if ($type === 'post') {
            update_post_meta($id, '_mdh_meta_title', $meta_title);
            update_post_meta($id, '_mdh_meta_description', $meta_description);
            update_post_meta($id, '_mdh_robots_noindex', $noindex);
            update_post_meta($id, '_mdh_robots_nofollow', $nofollow);
        } elseif ($type === 'term') {
            update_term_meta($id, '_mdh_meta_title', $meta_title);
            update_term_meta($id, '_mdh_meta_description', $meta_description);
            update_term_meta($id, '_mdh_robots_noindex', $noindex);
        }
        
        wp_send_json_success(__('Zapisano pomyślnie.', 'meta-description-handler'));
    }
}
