/* global ZCRB */
/**
 * ZYMARG Community Request Board - frontend script.
 *
 * Only the submission form is enhanced with JS (AJAX submit + character
 * counter + image validation). Pagination is fully server-rendered, so no
 * JavaScript is required to navigate between pages — every page link is a
 * real, crawlable URL such as /community/page/2/.
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var cfg = window.ZCRB || {};
    var i18n = cfg.i18n || {};

    document.addEventListener('DOMContentLoaded', initForm);

    function initForm() {
        var form = document.querySelector('[data-zcrb-form]');
        if (!form) return;

        var textarea = form.querySelector('[data-zcrb-message]');
        var counter = form.querySelector('[data-zcrb-counter]');
        var submitBtn = form.querySelector('[data-zcrb-submit]');
        var feedback = form.querySelector('[data-zcrb-feedback]');
        var fileInput = form.querySelector('input[name="zcrb_image"]');

        var limit = parseInt(cfg.messageLimit, 10) || 200;

        if (textarea && counter) {
            var updateCounter = function () {
                var used = textarea.value.length;
                if (used > limit) {
                    textarea.value = textarea.value.slice(0, limit);
                    used = limit;
                }
                counter.textContent = String(limit - used);
            };
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (!fileInput.files || !fileInput.files[0]) return;
                var file = fileInput.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    showFeedback(feedback, i18n.fileTooLarge || 'Image is too large.', 'error');
                    fileInput.value = '';
                    return;
                }
                var ok = ['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) !== -1;
                if (!ok) {
                    showFeedback(feedback, i18n.invalidImage || 'Invalid image format.', 'error');
                    fileInput.value = '';
                }
            });
        }

        form.addEventListener('submit', function (event) {
            // Allow non-JS fallback if AJAX requirements aren't met.
            if (!cfg.ajaxUrl || !cfg.nonce || !window.FormData || !window.fetch) {
                return;
            }
            event.preventDefault();

            if (!cfg.isLoggedIn) {
                showFeedback(feedback, i18n.mustLogin || 'Please log in.', 'error');
                if (cfg.loginUrl) {
                    window.location.href = cfg.loginUrl;
                }
                return;
            }

            var fd = new FormData(form);
            fd.append('action', 'zcrb_submit_request');
            fd.append('nonce', cfg.nonce);

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalLabel = submitBtn.textContent;
                submitBtn.textContent = i18n.submitting || 'Submitting…';
            }
            showFeedback(feedback, '', '');

            fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (res) { return res.json().catch(function () { return { success: false, data: { message: i18n.submitError } }; }); })
                .then(function (json) {
                    if (json && json.success) {
                        showFeedback(feedback, (json.data && json.data.message) || i18n.submitSuccess || 'Submitted.', 'success');
                        form.reset();
                        var counterEl = form.querySelector('[data-zcrb-counter]');
                        if (counterEl) counterEl.textContent = String(parseInt(cfg.messageLimit, 10) || 200);
                    } else {
                        var msg = (json && json.data && json.data.message) || i18n.submitError || 'Error.';
                        showFeedback(feedback, msg, 'error');
                    }
                })
                .catch(function () {
                    showFeedback(feedback, i18n.submitError || 'Network error.', 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalLabel || (i18n && i18n.submit) || 'Submit';
                    }
                });
        });
    }

    function showFeedback(el, message, kind) {
        if (!el) return;
        el.textContent = message || '';
        el.classList.remove('is-success', 'is-error');
        if (kind === 'success') el.classList.add('is-success');
        if (kind === 'error') el.classList.add('is-error');
    }
})();
