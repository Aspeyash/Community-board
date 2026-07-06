/* global ZCRBSettings, jQuery */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var $form = $('.zcrb-settings form[action="options.php"]');
        if (!$form.length) return;

        // Change form action to prevent classic options.php path
        $form.attr('data-ajax-form', '1');

        var $submit = $form.find('input[type="submit"], button[type="submit"]');
        var originalLabel = $submit.val() || $submit.text();
        var $toast = $form.find('[data-zcrb-toast]');
        if (!$toast.length) {
            $toast = $('<div class="zcrb-settings-toast" data-zcrb-toast></div>');
            $submit.after($toast);
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            var formData = $form.serialize();

            $submit.prop('disabled', true);
            if ($submit.is('input')) {
                $submit.val(ZCRBSettings.i18n.saving);
            } else {
                $submit.text(ZCRBSettings.i18n.saving);
            }
            hideToast($toast);

            $.ajax({
                url: ZCRBSettings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'zcrb_save_settings',
                    nonce: ZCRBSettings.nonce,
                    settings: formData
                }
            }).done(function (response) {
                if (response && response.success) {
                    showToast($toast, (response.data && response.data.message) || ZCRBSettings.i18n.saved, 'success');
                } else {
                    var msg = (response && response.data && response.data.message) || ZCRBSettings.i18n.saveError;
                    showToast($toast, msg, 'error');
                }
            }).fail(function () {
                showToast($toast, ZCRBSettings.i18n.saveError, 'error');
            }).always(function () {
                $submit.prop('disabled', false);
                if ($submit.is('input')) {
                    $submit.val(originalLabel);
                } else {
                    $submit.text(originalLabel);
                }
            });
        });

        function showToast($el, message, kind) {
            $el.removeClass('is-success is-error is-visible');
            $el.text(message);
            $el.addClass('is-visible ' + (kind === 'success' ? 'is-success' : 'is-error'));
            setTimeout(function () {
                $el.removeClass('is-visible');
            }, 3000);
        }
        function hideToast($el) {
            $el.removeClass('is-visible is-success is-error').text('');
        }
    });
})(jQuery);
