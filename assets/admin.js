jQuery(document).ready(function($) {
    'use strict';

    // User Select field settings
    if (typeof gform !== 'undefined' && gform.addFilter) {
        gform.addFilter('gform_field_settings', function(settings, field) {
            if (field.type === 'user_select') {
                // Add custom settings UI here if needed
                console.log('User Select field settings loaded');
            }
            return settings;
        });
    }

    // Searchable dropdown functionality
    $('.gf-user-select-searchable').each(function() {
        var $select = $(this);
        var fieldId = $select.data('field-id');
        var $container = $select.closest('.ginput_container_user_select');
        var $results = $container.find('.gf-user-select-search-results');
        
        // Convert to searchable dropdown
        $select.select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'gf_user_select_search',
                        nonce: gf_user_select_ajax.nonce,
                        search: params.term,
                        roles: $select.data('roles') || [],
                        display_format: $select.data('display-format') || 'display_name',
                        custom_template: $select.data('custom-template') || '',
                        excluded_users: $select.data('excluded-users') || '',
                        page: params.page || 1,
                        per_page: 20
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.results,
                            pagination: {
                                more: data.data.pagination.page < data.data.pagination.total_pages
                            }
                        };
                    }
                    return {
                        results: []
                    };
                },
                cache: true
            },
            placeholder: 'Search for a user...',
            minimumInputLength: 2,
            allowClear: true,
            width: '100%'
        });
    });

    // Settings page functionality
    $('#gf_user_select_display_format').on('change', function() {
        var format = $(this).val();
        var $customTemplate = $('#gf_user_select_custom_template').closest('tr');
        
        if (format === 'custom') {
            $customTemplate.show();
        } else {
            $customTemplate.hide();
        }
    });

    // Initialize display format visibility
    $('#gf_user_select_display_format').trigger('change');

    // Clear cache button
    $('.gf-user-select-clear-cache').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear the cache? This may temporarily slow down form loading.')) {
            return;
        }

        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Clearing...');
        
        $.post(ajaxurl, {
            action: 'gf_user_select_clear_cache',
            nonce: gf_user_select_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Cache cleared successfully!');
            } else {
                alert('Error clearing cache: ' + (response.data || 'Unknown error'));
            }
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
});

