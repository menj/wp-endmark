/**
 * Endmark Admin Scripts
 * 
 * @package Endmark
 * @version 4.2
 */

(function($) {
    'use strict';

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        initTabs();
        initTypeSelection();
        initSymbolButtons();
        initMediaUploader();
        initSizeSlider();
        initPlacementMode();
        initSchemaToggle();
        initLivePreview();
    });

    /**
     * Tab Navigation
     */
    function initTabs() {
        $('.endmark-tab').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Update tab states
            $('.endmark-tab').removeClass('active');
            $(this).addClass('active');
            
            // Update content states
            $('.endmark-tab-content').removeClass('active');
            $('#endmark-tab-' + tabId).addClass('active');
        });
    }

    /**
     * Type Card Selection (Symbol vs Image)
     */
    function initTypeSelection() {
        // Handle click on type option cards
        $('.endmark-type-option').on('click', function() {
            var input = $(this).find('input');
            input.prop('checked', true);
            
            // Update selected states
            $('.endmark-type-option').removeClass('selected');
            $(this).addClass('selected');
            
            // Show corresponding options panel
            $('.endmark-options-panel').removeClass('active');
            $('#endmark-' + input.val() + '-options').addClass('active');
            
            updatePreview();
        });

        // Initialize selected state on page load
        $('.endmark-type-option input:checked').closest('.endmark-type-option').addClass('selected');
        
        // Show the correct options panel
        var initType = $('input[name="' + endmarkAdmin.optionName + '[type]"]:checked').val();
        $('#endmark-' + initType + '-options').addClass('active');
    }

    /**
     * Symbol Quick-Select Buttons
     */
    function initSymbolButtons() {
        $('.endmark-sym-btn').on('click', function() {
            $('#endmark_symbol').val($(this).data('symbol'));
            updatePreview();
        });
    }

    /**
     * Media Library Uploader
     */
    function initMediaUploader() {
        var frame;

        // Upload button
        $('#endmark-upload-btn').on('click', function(e) {
            e.preventDefault();
            
            // If frame already exists, reopen it
            if (frame) {
                frame.open();
                return;
            }
            
            // Create new media frame
            frame = wp.media({
                title: endmarkAdmin.i18n.selectImage,
                button: {
                    text: endmarkAdmin.i18n.useImage
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // Handle image selection
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var sizes = attachment.sizes;
                var endmarkUrl = attachment.url;
                
                // Prefer thumbnail size if available
                if (sizes && sizes.thumbnail) {
                    endmarkUrl = sizes.thumbnail.url;
                }
                
                // Update form fields
                $('#endmark_image_url').val(endmarkUrl);
                $('#endmark_image_id').val(attachment.id);
                
                // Update preview box
                $('.endmark-image-box')
                    .addClass('has-image')
                    .html('<img src="' + endmarkUrl + '">');
                
                // Show remove button
                $('#endmark-remove-btn').show();
                
                updatePreview();
            });
            
            frame.open();
        });

        // Remove image button
        $('#endmark-remove-btn').on('click', function(e) {
            e.preventDefault();
            
            // Clear form fields
            $('#endmark_image_url, #endmark_image_id').val('');
            
            // Reset preview box
            $('.endmark-image-box')
                .removeClass('has-image')
                .html('<span class="dashicons dashicons-format-image"></span>');
            
            // Hide remove button
            $(this).hide();
            
            updatePreview();
        });
    }

    /**
     * Image Size Slider
     */
    function initSizeSlider() {
        $('#endmark_image_size_px').on('input', function() {
            $('#endmark-size-value').text($(this).val() + 'px');
            updatePreview();
        });
    }

    /**
     * Placement Mode Selector Toggle
     */
    function initPlacementMode() {
        $('#endmark_placement_mode').on('change', function() {
            var mode = $(this).val();
            
            if (mode === 'selector') {
                $('.endmark-selector-field').slideDown(200);
            } else {
                $('.endmark-selector-field').slideUp(200);
            }
        });
        
        // Initialize visibility on page load
        if ($('#endmark_placement_mode').val() !== 'selector') {
            $('.endmark-selector-field').hide();
        }
    }

    /**
     * Schema Enabled Toggle
     */
    function initSchemaToggle() {
        $('#endmark_schema_enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('.endmark-schema-mode-field').removeClass('disabled');
            } else {
                $('.endmark-schema-mode-field').addClass('disabled');
            }
        });
        
        // Initialize state on page load
        if (!$('#endmark_schema_enabled').is(':checked')) {
            $('.endmark-schema-mode-field').addClass('disabled');
        }
    }

    /**
     * Live Preview
     */
    function initLivePreview() {
        // Update on symbol input
        $('#endmark_symbol').on('input', updatePreview);
        
        // Initial preview render
        updatePreview();
    }

    /**
     * Update the live preview display
     */
    function updatePreview() {
        var type = $('input[name="' + endmarkAdmin.optionName + '[type]"]:checked').val();
        var html = '';
        
        if (type === 'symbol') {
            var symbol = $('#endmark_symbol').val() || '∎';
            // Escape HTML entities for security
            var escaped = $('<div>').text(symbol).html();
            html = '<span style="margin-left:0.25em;">' + escaped + '</span>';
        } else {
            var url = $('#endmark_image_url').val();
            var size = $('#endmark_image_size_px').val() || 16;
            
            if (url) {
                html = '<span style="margin-left:0.25em;">' +
                       '<img src="' + url + '" style="height:' + size + 'px;width:auto;vertical-align:baseline;">' +
                       '</span>';
            }
        }
        
        $('#endmark-live-mark').html(html);
    }

})(jQuery);
