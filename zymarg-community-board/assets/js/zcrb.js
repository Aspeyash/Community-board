/* global ZCRB */
/**
 * ZYMARG Community Request Board - frontend script.
 *
 * Pagination is fully server-rendered (every page link is a real /community/page/N/ URL),
 * so this script only handles:
 *  - The submission form: AJAX submit + character counter + image validation
 *  - The drag-drop style upload area: filename preview when a file is chosen
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var cfg = window.ZCRB || {};
    var i18n = cfg.i18n || {};

    document.addEventListener('DOMContentLoaded', initForm);
    document.addEventListener('DOMContentLoaded', initUpvote);
    document.addEventListener('DOMContentLoaded', initVendorResponse);
    document.addEventListener('DOMContentLoaded', initDuplicateDetection);

    function initForm() {
        var form = document.querySelector('[data-zcrb-form]');
        if (!form) return;

        var textarea = form.querySelector('[data-zcrb-message]');
        var counter = form.querySelector('[data-zcrb-counter]');
        var submitBtn = form.querySelector('[data-zcrb-submit]');
        var feedback = form.querySelector('[data-zcrb-feedback]');
        var fileInput = form.querySelector('input[name="zcrb_images[]"]');
        var fileInputs = form.querySelectorAll('input[name="zcrb_images[]"]');
        var fileLabel = form.querySelector('[data-zcrb-filename]');

        var limit = parseInt(cfg.messageLimit, 10) || 200;
        var maxMb = parseInt(cfg.imageMaxMb, 10) || 2;
        var maxCount = parseInt(cfg.imageMaxCount, 10) || 1;
        var allowedTypes = Array.isArray(cfg.imageTypes) && cfg.imageTypes.length
            ? cfg.imageTypes
            : ['image/jpeg', 'image/png', 'image/webp'];

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

        if (fileInputs && fileInputs.length) {
            for (var fi = 0; fi < fileInputs.length; fi++) {
                (function(input) {
                    var label = input.closest('.zcrb-upload') ? input.closest('.zcrb-upload').querySelector('[data-zcrb-filename]') : null;
                    input.addEventListener('change', function () {
                        if (!input.files || !input.files[0]) {
                            if (label) label.textContent = '';
                            return;
                        }
                        var file = input.files[0];
                        if (file.size > maxMb * 1024 * 1024) {
                            showFeedback(feedback, i18n.fileTooLarge || 'Image is too large.', 'error');
                            input.value = '';
                            if (label) label.textContent = '';
                            return;
                        }
                        if (allowedTypes.indexOf(file.type) === -1) {
                            showFeedback(feedback, i18n.invalidImage || 'Invalid image format.', 'error');
                            input.value = '';
                            if (label) label.textContent = '';
                            return;
                        }
                        if (label) label.textContent = file.name;
                        showFeedback(feedback, '', '');
                    });
                })(fileInputs[fi]);
            }
        } else if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (!fileInput.files || !fileInput.files[0]) {
                    if (fileLabel) fileLabel.textContent = '';
                    return;
                }
                var file = fileInput.files[0];
                if (file.size > maxMb * 1024 * 1024) {
                    showFeedback(feedback, i18n.fileTooLarge || 'Image is too large.', 'error');
                    fileInput.value = '';
                    if (fileLabel) fileLabel.textContent = '';
                    return;
                }
                if (allowedTypes.indexOf(file.type) === -1) {
                    showFeedback(feedback, i18n.invalidImage || 'Invalid image format.', 'error');
                    fileInput.value = '';
                    if (fileLabel) fileLabel.textContent = '';
                    return;
                }
                if (fileLabel) fileLabel.textContent = file.name;
                showFeedback(feedback, '', '');
            });
        }

        form.addEventListener('submit', function (event) {
            if (!cfg.ajaxUrl || !cfg.nonce || !window.FormData || !window.fetch) {
                return; // graceful fallback to classic POST
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
                        if (fileLabel) fileLabel.textContent = '';
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

    /**
     * Upvote toggle handler.
     */
    function initUpvote() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-zcrb-upvote]');
            if (!btn) return;
            if (!cfg.ajaxUrl || !cfg.upvoteNonce || !cfg.isLoggedIn) return;

            e.preventDefault();
            var postId = btn.getAttribute('data-post-id');
            if (!postId) return;

            var fd = new FormData();
            fd.append('action', 'zcrb_upvote');
            fd.append('post_id', postId);
            fd.append('nonce', cfg.upvoteNonce);

            fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        var countEl = btn.querySelector('[data-zcrb-upvote-count]');
                        if (countEl) countEl.textContent = String(json.data.count);
                        if (json.data.active) {
                            btn.classList.add('is-active');
                            btn.setAttribute('aria-label', i18n.upvoted || 'Upvoted');
                            var icon = btn.querySelector('.zcrb-upvote__icon');
                            if (icon) icon.setAttribute('fill', 'currentColor');
                        } else {
                            btn.classList.remove('is-active');
                            btn.setAttribute('aria-label', i18n.upvote || 'Upvote');
                            var icon2 = btn.querySelector('.zcrb-upvote__icon');
                            if (icon2) icon2.setAttribute('fill', 'none');
                        }
                    }
                })
                .catch(function () {});
        });
    }

    /**
     * Vendor response form handler.
     */
    function initVendorResponse() {
        var form = document.querySelector('[data-zcrb-response-form]');
        if (!form) return;
        if (!cfg.canVendorRespond) return;

        var submitBtn = form.querySelector('[data-zcrb-vendor-submit]');
        var textarea = form.querySelector('[data-zcrb-vendor-message]');
        var feedback = form.querySelector('[data-zcrb-vendor-feedback]');
        var postId = form.getAttribute('data-post-id');

        if (!submitBtn || !textarea || !postId) return;

        submitBtn.addEventListener('click', function () {
            var message = textarea.value.trim();
            if (!message) return;
            if (!cfg.ajaxUrl || !cfg.vendorNonce) return;

            submitBtn.disabled = true;
            showFeedback(feedback, '', '');

            var fd = new FormData();
            fd.append('action', 'zcrb_vendor_respond');
            fd.append('post_id', postId);
            fd.append('message', message);
            fd.append('nonce', cfg.vendorNonce);

            fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        showFeedback(feedback, (json.data && json.data.message) || i18n.responseSubmitted || 'Submitted.', 'success');
                        textarea.value = '';
                    } else {
                        var msg = (json && json.data && json.data.message) || i18n.submitError || 'Error.';
                        showFeedback(feedback, msg, 'error');
                    }
                })
                .catch(function () {
                    showFeedback(feedback, i18n.submitError || 'Network error.', 'error');
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }

    /**
     * Duplicate detection (search-before-submit).
     * Listens to the message textarea and shows similar existing requests.
     */
    function initDuplicateDetection() {
        var form = document.querySelector('[data-zcrb-form]');
        if (!form) return;

        var textarea = form.querySelector('[data-zcrb-message]');
        var similarDiv = form.querySelector('[data-zcrb-similar]');
        if (!textarea || !similarDiv) return;
        if (!cfg.ajaxUrl || !cfg.nonce) return;

        var debounceTimer = null;

        textarea.addEventListener('input', function () {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            var text = textarea.value.trim();
            if (text.length < 10) {
                while (similarDiv.firstChild) {
                    similarDiv.removeChild(similarDiv.firstChild);
                }
                return;
            }
            debounceTimer = setTimeout(function () {
                var fd = new FormData();
                fd.append('action', 'zcrb_duplicate_search');
                fd.append('nonce', cfg.nonce);
                fd.append('query', text);

                fetch(cfg.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        while (similarDiv.firstChild) {
                            similarDiv.removeChild(similarDiv.firstChild);
                        }
                        if (json && json.success && json.data && json.data.matches && json.data.matches.length > 0) {
                            var heading = document.createElement('p');
                            heading.className = 'zcrb-similar__heading';
                            heading.textContent = i18n.similarRequestsFound || 'Similar requests found:';
                            similarDiv.appendChild(heading);

                            var list = document.createElement('div');
                            list.className = 'zcrb-similar__list';

                            json.data.matches.forEach(function (match) {
                                var link = document.createElement('a');
                                link.className = 'zcrb-similar__item';
                                link.href = match.permalink;
                                link.target = '_blank';
                                link.rel = 'noopener';

                                var refSpan = document.createElement('span');
                                refSpan.className = 'zcrb-similar__item-ref';
                                refSpan.textContent = '#' + match.ref;

                                var titleSpan = document.createElement('span');
                                titleSpan.className = 'zcrb-similar__item-title';
                                titleSpan.textContent = match.title;

                                link.appendChild(refSpan);
                                link.appendChild(titleSpan);
                                list.appendChild(link);
                            });

                            similarDiv.appendChild(list);
                        }
                    })
                    .catch(function () {
                        while (similarDiv.firstChild) {
                            similarDiv.removeChild(similarDiv.firstChild);
                        }
                    });
            }, 500);
        });
    }
})();
