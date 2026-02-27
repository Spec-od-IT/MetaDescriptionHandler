/**
 * Meta Description Handler - Bulk Editor JavaScript
 */

(function($) {
    'use strict';

    var currentPage = 1;
    var totalPages = 1;

    // Load items
    function loadItems(page) {
        page = page || 1;
        var contentType = $('#mdh-content-type').val();
        var filterStatus = $('#mdh-filter-status').val();
        var search = $('#mdh-search').val();

        $('#mdh-items-list').empty().append(
            $('<tr>').append(
                $('<td colspan="5" class="mdh-loading-row">').text(mdhAdmin.strings.loading)
            )
        );

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
                    $('#mdh-items-list').empty().append(
                        $('<tr>').append(
                            $('<td colspan="5">').text(response.data)
                        )
                    );
                }
            }
        });
    }

    // Update pagination
    function updatePagination() {
        var $container = $('#mdh-pagination').empty();
        if (totalPages > 1) {
            $container.append(
                $('<span class="mdh-page-info">').text(
                    mdhAdmin.strings.page + ' ' + currentPage + ' ' + mdhAdmin.strings.of + ' ' + totalPages
                )
            );

            if (currentPage > 1) {
                $container.append(
                    $('<button type="button" class="button mdh-page-btn">')
                        .attr('data-page', currentPage - 1)
                        .html('&laquo; ')
                        .append($('<span>').text(mdhAdmin.strings.previous))
                );
            }
            if (currentPage < totalPages) {
                $container.append(
                    $('<button type="button" class="button mdh-page-btn">')
                        .attr('data-page', currentPage + 1)
                        .append($('<span>').text(mdhAdmin.strings.next))
                        .append(' &raquo;')
                );
            }
        }
    }

    // Update modal counters using pixel measurement (consistent with admin.js)
    function updateModalCounters() {
        var titleVal = $('#mdh-edit-meta-title').val() || '';
        var descVal = $('#mdh-edit-meta-description').val() || '';

        var titlePx = window.MDHAdmin ? MDHAdmin.measureTextWidth(titleVal, 'title') : titleVal.length;
        var descPx = window.MDHAdmin ? MDHAdmin.measureTextWidth(descVal, 'description') : descVal.length;

        $('#mdh-edit-modal .mdh-char-counter[data-type="title"] .mdh-char-count').text(titlePx);
        $('#mdh-edit-modal .mdh-char-counter[data-type="description"] .mdh-char-count').text(descPx);
    }

    $(document).ready(function() {
        // Load button
        $('#mdh-load-items').on('click', function() {
            loadItems(1);
        });

        // Pagination
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
            $('#mdh-modal-preview-desc').text(metaDesc || mdhAdmin.strings.noDescription);

            updateModalCounters();

            $('#mdh-edit-modal').fadeIn(200);
        });

        // Live preview update
        $('#mdh-edit-meta-title').on('input', function() {
            var val = $(this).val();
            $('#mdh-modal-preview-title').text(val || mdhAdmin.strings.pageTitle);
            updateModalCounters();
        });

        $('#mdh-edit-meta-description').on('input', function() {
            var val = $(this).val();
            $('#mdh-modal-preview-desc').text(val || mdhAdmin.strings.noDescription);
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
            $btn.prop('disabled', true).text(mdhAdmin.strings.saving);

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
                    $btn.prop('disabled', false).text(mdhAdmin.strings.saveChanges);

                    if (response.success) {
                        $('#mdh-edit-modal').fadeOut(200);
                        loadItems(currentPage);
                        MDHAdmin.showToast(mdhAdmin.strings.savedMeta);
                    } else {
                        MDHAdmin.showToast(response.data || mdhAdmin.strings.saveError, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text(mdhAdmin.strings.saveChanges);
                    MDHAdmin.showToast(mdhAdmin.strings.connectionError, 'error');
                }
            });
        });

        // Initial load
        loadItems(1);
    });

})(jQuery);
