/* global jQuery, gf_user_select_ajax */
(function ($) {
    'use strict';

    function init(scope) {
        $(scope).find('select.gf-user-select-searchable').each(function () {
            var $select = $(this);
            if ($select.data('gfUserSelectInit') || typeof gf_user_select_ajax === 'undefined') {
                return;
            }
            $select.data('gfUserSelectInit', true);

            var $input = $('<input type="text" class="gf-user-select-search-input" autocomplete="off" />')
                .attr('placeholder', gf_user_select_ajax.i18n.placeholder)
                .attr('aria-controls', $select.attr('id'));
            $select.before($input);

            var timer = null;
            $input.on('input', function () {
                var term = $.trim($input.val());
                window.clearTimeout(timer);
                if (term.length < 2) {
                    return;
                }
                timer = window.setTimeout(function () {
                    $.post(gf_user_select_ajax.ajax_url, {
                        action: 'gf_user_select_search',
                        nonce: gf_user_select_ajax.nonce,
                        form_id: $select.data('form-id'),
                        field_id: $select.data('field-id'),
                        search: term,
                        per_page: 20
                    }).done(function (response) {
                        var current = $select.val();
                        $select.find('option[value!=""]').remove();
                        if (!response || !response.success || !response.data.results.length) {
                            $select.append($('<option disabled>').text(gf_user_select_ajax.i18n.no_results));
                            return;
                        }
                        $.each(response.data.results, function (_, user) {
                            $select.append($('<option>').val(user.id).text(user.text));
                        });
                        if (current) {
                            $select.val(current);
                        }
                    });
                }, 250);
            });
        });
    }

    $(function () { init(document); });
    $(document).on('gform_post_render', function (event, formId) { init('#gform_' + formId); });
})(jQuery);
