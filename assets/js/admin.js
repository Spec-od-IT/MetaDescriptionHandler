/**
 * Meta Description Handler - Admin JavaScript
 * Pixel-based measurement for Google SERP preview
 */

(function($) {
    'use strict';

    var MDHAdmin = {
        
        // Canvas context for measuring text width
        canvas: null,
        
        // Google SERP font settings (calibrated to match ToTheWeb)
        fonts: {
            title: '20px Arial, sans-serif',       // Google title font ~20px
            description: '13px Arial, sans-serif'  // Google description font ~13px
        },
        
        // Pixel limits based on totheweb.com research
        limits: {
            title: {
                desktop: 580,
                mobile: 600,
                optimal_min: 400
            },
            description: {
                desktop: 920,
                mobile: 680,
                optimal_min: 400
            }
        },
        
        /**
         * Initialize
         */
        init: function() {
            this.initCanvas();
            this.bindEvents();
            this.initTabs();
            this.initPixelCounters();
            this.initPreview();
        },
        
        /**
         * Initialize canvas for text measurement
         */
        initCanvas: function() {
            this.canvas = document.createElement('canvas');
            this.ctx = this.canvas.getContext('2d');
        },
        
        /**
         * Measure text width in pixels
         * Uses floor for description to match ToTheWeb calculations
         */
        measureTextWidth: function(text, type) {
            if (!text) return 0;
            
            var font = type === 'title' ? this.fonts.title : this.fonts.description;
            this.ctx.font = font;
            
            var width = this.ctx.measureText(text).width;
            
            // Title uses round, description uses floor (matches ToTheWeb)
            return type === 'title' ? Math.round(width) : Math.floor(width);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.mdh-tab-link', this.handleTabClick.bind(this));
            
            // Pixel counter updates
            $(document).on('input', '.mdh-title-input, .mdh-description-input', this.updatePixelCounter.bind(this));
            
            // Live preview updates
            $(document).on('input', '#mdh_meta_title, #mdh_term_meta_title', this.updatePreviewTitle.bind(this));
            $(document).on('input', '#mdh_meta_description, #mdh_term_meta_description', this.updatePreviewDescription.bind(this));
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Check for hash in URL
            var hash = window.location.hash;
            if (hash && $(hash).length) {
                this.activateTab(hash);
            }
        },

        /**
         * Handle tab click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            var target = $(e.currentTarget).attr('href');
            this.activateTab(target);
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        },

        /**
         * Activate tab
         */
        activateTab: function(target) {
            var $link = $('.mdh-tab-link[href="' + target + '"]');
            var $panel = $(target);
            
            if (!$panel.length) return;
            
            // Update nav
            $link.siblings().removeClass('active');
            $link.addClass('active');
            
            // Update panels
            $panel.siblings('.mdh-tab-panel').removeClass('active');
            $panel.addClass('active');
        },

        /**
         * Initialize pixel counters
         */
        initPixelCounters: function() {
            var self = this;
            
            $('.mdh-title-input, .mdh-description-input').each(function() {
                var $input = $(this);
                self.updatePixelCounterForInput($input);
            });
        },

        /**
         * Update pixel counter on input
         */
        updatePixelCounter: function(e) {
            var $input = $(e.currentTarget);
            this.updatePixelCounterForInput($input);
        },
        
        /**
         * Update pixel counter for specific input
         */
        updatePixelCounterForInput: function($input) {
            var $counter = $input.siblings('.mdh-char-counter').find('.mdh-char-count');
            
            // Also check parent container for counter (for different markup structures)
            if (!$counter.length) {
                $counter = $input.closest('.mdh-field-group, .form-field, td').find('.mdh-char-count');
            }
            
            var $counterWrap = $input.siblings('.mdh-char-counter');
            if (!$counterWrap.length) {
                $counterWrap = $input.closest('.mdh-field-group, .form-field, td').find('.mdh-char-counter');
            }
            
            if (!$counter.length) return;
            
            var type = $counterWrap.data('type') || 'description';
            var text = $input.val() || '';
            var pixelWidth = this.measureTextWidth(text, type);
            var charCount = text.length;
            
            // Update display with both pixels and characters
            $counter.text(pixelWidth);
            
            this.updateCounterStatus($input, pixelWidth, type);
        },

        /**
         * Update counter status color based on pixel width
         */
        updateCounterStatus: function($input, pixelWidth, type) {
            var $counterWrap = $input.siblings('.mdh-char-counter');
            
            if (!$counterWrap.length) {
                $counterWrap = $input.closest('.mdh-field-group, .form-field, td').find('.mdh-char-counter');
            }
            
            if (!$counterWrap.length) return;
            
            var limits = this.limits[type];
            var maxPixels = limits.desktop;
            var minOptimal = limits.optimal_min;
            var status = '';
            var statusClass = '';
            
            // Remove existing status classes
            $counterWrap.removeClass('mdh-optimal mdh-short mdh-long mdh-empty');
            
            if (pixelWidth === 0) {
                statusClass = 'mdh-empty';
                status = '';
            } else if (pixelWidth < minOptimal) {
                statusClass = 'mdh-short';
                status = mdhAdmin.strings[type + 'Short'] || 'Za krótki';
            } else if (pixelWidth <= maxPixels) {
                statusClass = 'mdh-optimal';
                status = mdhAdmin.strings[type + 'Optimal'] || 'Optymalna długość';
            } else {
                statusClass = 'mdh-long';
                status = mdhAdmin.strings[type + 'Long'] || 'Za długi - zostanie obcięty';
            }
            
            $counterWrap.addClass(statusClass);
            $counterWrap.find('.mdh-char-status').text(status);
        },

        /**
         * Initialize preview
         */
        initPreview: function() {
            // Update preview on page load
            this.updatePreviewTitle();
            this.updatePreviewDescription();
        },

        /**
         * Update preview title
         */
        updatePreviewTitle: function(e) {
            var $input = e ? $(e.currentTarget) : $('#mdh_meta_title, #mdh_term_meta_title').first();
            var $preview = $('#mdh-preview-title');
            
            if (!$preview.length) return;
            
            var title = $input.val();
            var placeholder = $input.attr('placeholder') || '';
            
            if (!title && placeholder) {
                title = placeholder;
            }
            
            // Truncate based on pixel width
            var truncated = this.truncateByPixels(title, 'title', this.limits.title.desktop);
            $preview.text(truncated);
        },

        /**
         * Update preview description
         */
        updatePreviewDescription: function(e) {
            var $input = e ? $(e.currentTarget) : $('#mdh_meta_description, #mdh_term_meta_description').first();
            var $preview = $('#mdh-preview-description');
            
            if (!$preview.length) return;
            
            var description = $input.val();
            var placeholder = $input.attr('placeholder') || '';
            
            if (!description && placeholder) {
                description = placeholder;
            }
            
            // Truncate based on pixel width
            var truncated = this.truncateByPixels(description, 'description', this.limits.description.desktop);
            $preview.text(truncated);
        },

        /**
         * Truncate string based on pixel width
         */
        truncateByPixels: function(str, type, maxPixels) {
            if (!str) return '';
            
            var currentWidth = this.measureTextWidth(str, type);
            
            if (currentWidth <= maxPixels) {
                return str;
            }
            
            // Binary search for optimal truncation point
            var low = 0;
            var high = str.length;
            var ellipsis = '...';
            var ellipsisWidth = this.measureTextWidth(ellipsis, type);
            var targetWidth = maxPixels - ellipsisWidth;
            
            while (low < high) {
                var mid = Math.floor((low + high + 1) / 2);
                var testStr = str.substring(0, mid);
                var testWidth = this.measureTextWidth(testStr, type);
                
                if (testWidth <= targetWidth) {
                    low = mid;
                } else {
                    high = mid - 1;
                }
            }
            
            return str.substring(0, low) + ellipsis;
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            type = type || 'success';
            
            var $toast = $('<div class="mdh-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);
            
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * AJAX save settings
         */
        saveSettings: function(data, callback) {
            $.ajax({
                url: mdhAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mdh_save_settings',
                    nonce: mdhAdmin.nonce,
                    settings: data
                },
                beforeSend: function() {
                    $('.mdh-settings-form').addClass('mdh-loading');
                },
                success: function(response) {
                    $('.mdh-settings-form').removeClass('mdh-loading');
                    
                    if (response.success) {
                        MDHAdmin.showToast(mdhAdmin.strings.saved);
                    } else {
                        MDHAdmin.showToast(response.data || mdhAdmin.strings.error, 'error');
                    }
                    
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function() {
                    $('.mdh-settings-form').removeClass('mdh-loading');
                    MDHAdmin.showToast(mdhAdmin.strings.error, 'error');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        MDHAdmin.init();
    });

    // Expose to global scope
    window.MDHAdmin = MDHAdmin;

})(jQuery);
