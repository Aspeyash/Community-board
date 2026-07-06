/* global ZCRB */
/**
 * ZYMARG Community Request Board - Freeform Image Cropper
 *
 * A lightweight, dependency-free image crop tool built with HTML5 Canvas API.
 * After file selection, shows a modal overlay with the image preview and a
 * draggable/resizable crop rectangle. User can confirm crop or skip.
 *
 * @package ZymargCommunityBoard
 * @since 2.5.0
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    /**
     * State for each cropper instance.
     */
    var activeCropper = null;

    /**
     * Create and show the crop modal for a given File object.
     *
     * @param {File}     file       The image file to crop.
     * @param {Function} onCrop     Callback with (croppedBlob, croppedFileName).
     * @param {Function} onSkip     Callback when user skips cropping (uses original).
     */
    function openCropper(file, onCrop, onSkip) {
        if (activeCropper) {
            closeCropper();
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                buildModal(img, file, onCrop, onSkip);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    /**
     * Build the crop modal UI.
     */
    function buildModal(img, file, onCrop, onSkip) {
        // Overlay
        var overlay = document.createElement('div');
        overlay.className = 'zcrb-cropper-overlay';

        // Modal
        var modal = document.createElement('div');
        modal.className = 'zcrb-cropper-modal';

        // Header
        var header = document.createElement('div');
        header.className = 'zcrb-cropper-header';
        var title = document.createElement('span');
        title.className = 'zcrb-cropper-title';
        title.textContent = 'Crop Image';
        header.appendChild(title);

        // Canvas container
        var canvasWrap = document.createElement('div');
        canvasWrap.className = 'zcrb-cropper-canvas-wrap';

        var canvas = document.createElement('canvas');
        canvas.className = 'zcrb-cropper-canvas';
        canvasWrap.appendChild(canvas);

        // Crop selection overlay (rendered via a second canvas)
        var cropCanvas = document.createElement('canvas');
        cropCanvas.className = 'zcrb-cropper-selection';
        canvasWrap.appendChild(cropCanvas);

        // Footer buttons
        var footer = document.createElement('div');
        footer.className = 'zcrb-cropper-footer';

        var skipBtn = document.createElement('button');
        skipBtn.type = 'button';
        skipBtn.className = 'zcrb-cropper-btn zcrb-cropper-btn--skip';
        skipBtn.textContent = 'Skip Crop';

        var cropBtn = document.createElement('button');
        cropBtn.type = 'button';
        cropBtn.className = 'zcrb-cropper-btn zcrb-cropper-btn--crop';
        cropBtn.textContent = 'Crop & Use';

        footer.appendChild(skipBtn);
        footer.appendChild(cropBtn);

        modal.appendChild(header);
        modal.appendChild(canvasWrap);
        modal.appendChild(footer);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Force reflow for animation
        overlay.offsetHeight; // eslint-disable-line no-unused-expressions
        overlay.classList.add('is-visible');

        // Size the canvases to fit the modal
        var maxW = Math.min(window.innerWidth - 80, 800);
        var maxH = Math.min(window.innerHeight - 220, 600);
        var scale = Math.min(maxW / img.width, maxH / img.height, 1);
        var dispW = Math.round(img.width * scale);
        var dispH = Math.round(img.height * scale);

        canvas.width = dispW;
        canvas.height = dispH;
        cropCanvas.width = dispW;
        cropCanvas.height = dispH;

        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, dispW, dispH);

        // Crop rectangle state (start with a centered 60% region)
        var cropRect = {
            x: Math.round(dispW * 0.2),
            y: Math.round(dispH * 0.2),
            w: Math.round(dispW * 0.6),
            h: Math.round(dispH * 0.6)
        };

        var cropCtx = cropCanvas.getContext('2d');
        drawCropOverlay(cropCtx, cropRect, dispW, dispH);

        // Interaction state
        var dragging = false;
        var resizing = false;
        var resizeHandle = null; // 'nw','ne','sw','se','n','s','e','w'
        var dragStart = { x: 0, y: 0 };
        var rectStart = { x: 0, y: 0, w: 0, h: 0 };
        var HANDLE_SIZE = 12;
        var MIN_SIZE = 30;

        function getPos(e) {
            var rect = cropCanvas.getBoundingClientRect();
            var clientX, clientY;
            if (e.touches && e.touches.length) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function hitTest(pos) {
            var r = cropRect;
            var hs = HANDLE_SIZE;

            // Check corners first
            if (pos.x >= r.x - hs && pos.x <= r.x + hs && pos.y >= r.y - hs && pos.y <= r.y + hs) return 'nw';
            if (pos.x >= r.x + r.w - hs && pos.x <= r.x + r.w + hs && pos.y >= r.y - hs && pos.y <= r.y + hs) return 'ne';
            if (pos.x >= r.x - hs && pos.x <= r.x + hs && pos.y >= r.y + r.h - hs && pos.y <= r.y + r.h + hs) return 'sw';
            if (pos.x >= r.x + r.w - hs && pos.x <= r.x + r.w + hs && pos.y >= r.y + r.h - hs && pos.y <= r.y + r.h + hs) return 'se';

            // Check edges
            if (pos.x >= r.x + hs && pos.x <= r.x + r.w - hs && pos.y >= r.y - hs && pos.y <= r.y + hs) return 'n';
            if (pos.x >= r.x + hs && pos.x <= r.x + r.w - hs && pos.y >= r.y + r.h - hs && pos.y <= r.y + r.h + hs) return 's';
            if (pos.y >= r.y + hs && pos.y <= r.y + r.h - hs && pos.x >= r.x - hs && pos.x <= r.x + hs) return 'w';
            if (pos.y >= r.y + hs && pos.y <= r.y + r.h - hs && pos.x >= r.x + r.w - hs && pos.x <= r.x + r.w + hs) return 'e';

            // Check inside
            if (pos.x >= r.x && pos.x <= r.x + r.w && pos.y >= r.y && pos.y <= r.y + r.h) return 'move';

            return null;
        }

        function setCursor(handle) {
            var cursors = {
                'nw': 'nwse-resize', 'se': 'nwse-resize',
                'ne': 'nesw-resize', 'sw': 'nesw-resize',
                'n': 'ns-resize', 's': 'ns-resize',
                'e': 'ew-resize', 'w': 'ew-resize',
                'move': 'move'
            };
            cropCanvas.style.cursor = cursors[handle] || 'crosshair';
        }

        function onMouseDown(e) {
            e.preventDefault();
            var pos = getPos(e);
            var handle = hitTest(pos);

            if (handle === 'move') {
                dragging = true;
                dragStart = pos;
                rectStart = { x: cropRect.x, y: cropRect.y, w: cropRect.w, h: cropRect.h };
            } else if (handle) {
                resizing = true;
                resizeHandle = handle;
                dragStart = pos;
                rectStart = { x: cropRect.x, y: cropRect.y, w: cropRect.w, h: cropRect.h };
            } else {
                // Start new selection
                dragging = false;
                resizing = true;
                resizeHandle = 'se';
                cropRect.x = pos.x;
                cropRect.y = pos.y;
                cropRect.w = MIN_SIZE;
                cropRect.h = MIN_SIZE;
                dragStart = pos;
                rectStart = { x: cropRect.x, y: cropRect.y, w: cropRect.w, h: cropRect.h };
            }
        }

        function onMouseMove(e) {
            e.preventDefault();
            var pos = getPos(e);

            if (!dragging && !resizing) {
                var handle = hitTest(pos);
                setCursor(handle);
                return;
            }

            var dx = pos.x - dragStart.x;
            var dy = pos.y - dragStart.y;

            if (dragging) {
                cropRect.x = Math.max(0, Math.min(dispW - cropRect.w, rectStart.x + dx));
                cropRect.y = Math.max(0, Math.min(dispH - cropRect.h, rectStart.y + dy));
            } else if (resizing) {
                var r = rectStart;
                var newX = r.x, newY = r.y, newW = r.w, newH = r.h;

                if (resizeHandle.indexOf('e') !== -1) {
                    newW = Math.max(MIN_SIZE, r.w + dx);
                }
                if (resizeHandle.indexOf('w') !== -1) {
                    newX = r.x + dx;
                    newW = r.w - dx;
                    if (newW < MIN_SIZE) { newX = r.x + r.w - MIN_SIZE; newW = MIN_SIZE; }
                }
                if (resizeHandle.indexOf('s') !== -1) {
                    newH = Math.max(MIN_SIZE, r.h + dy);
                }
                if (resizeHandle.indexOf('n') !== -1) {
                    newY = r.y + dy;
                    newH = r.h - dy;
                    if (newH < MIN_SIZE) { newY = r.y + r.h - MIN_SIZE; newH = MIN_SIZE; }
                }

                // Clamp to canvas bounds
                if (newX < 0) { newW += newX; newX = 0; }
                if (newY < 0) { newH += newY; newY = 0; }
                if (newX + newW > dispW) { newW = dispW - newX; }
                if (newY + newH > dispH) { newH = dispH - newY; }

                cropRect.x = newX;
                cropRect.y = newY;
                cropRect.w = Math.max(MIN_SIZE, newW);
                cropRect.h = Math.max(MIN_SIZE, newH);
            }

            drawCropOverlay(cropCtx, cropRect, dispW, dispH);
        }

        function onMouseUp(e) {
            e.preventDefault();
            dragging = false;
            resizing = false;
            resizeHandle = null;
        }

        // Mouse events
        cropCanvas.addEventListener('mousedown', onMouseDown);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);

        // Touch events
        cropCanvas.addEventListener('touchstart', onMouseDown, { passive: false });
        document.addEventListener('touchmove', onMouseMove, { passive: false });
        document.addEventListener('touchend', onMouseUp);

        // Button handlers
        skipBtn.addEventListener('click', function () {
            closeCropper();
            if (onSkip) onSkip();
        });

        cropBtn.addEventListener('click', function () {
            // Extract crop region from original image at full resolution
            var sx = Math.round(cropRect.x / scale);
            var sy = Math.round(cropRect.y / scale);
            var sw = Math.round(cropRect.w / scale);
            var sh = Math.round(cropRect.h / scale);

            // Clamp to image bounds
            sx = Math.max(0, Math.min(sx, img.width - 1));
            sy = Math.max(0, Math.min(sy, img.height - 1));
            sw = Math.min(sw, img.width - sx);
            sh = Math.min(sh, img.height - sy);

            var outCanvas = document.createElement('canvas');
            outCanvas.width = sw;
            outCanvas.height = sh;
            var outCtx = outCanvas.getContext('2d');
            outCtx.drawImage(img, sx, sy, sw, sh, 0, 0, sw, sh);

            // Determine output type
            var mimeType = file.type || 'image/jpeg';
            if (mimeType === 'image/png' || mimeType === 'image/webp') {
                // Keep format
            } else {
                mimeType = 'image/jpeg';
            }
            var quality = mimeType === 'image/png' ? undefined : 0.92;

            outCanvas.toBlob(function (blob) {
                if (!blob) {
                    if (onSkip) onSkip();
                    closeCropper();
                    return;
                }
                // Build a filename
                var ext = mimeType === 'image/png' ? '.png' : mimeType === 'image/webp' ? '.webp' : '.jpg';
                var baseName = file.name.replace(/\.[^.]+$/, '');
                var croppedName = baseName + '-cropped' + ext;

                closeCropper();
                if (onCrop) onCrop(blob, croppedName, mimeType);
            }, mimeType, quality);
        });

        // Close on overlay click (outside modal)
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeCropper();
                if (onSkip) onSkip();
            }
        });

        // ESC to close
        function onKeyDown(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeCropper();
                if (onSkip) onSkip();
            }
        }
        document.addEventListener('keydown', onKeyDown);

        activeCropper = {
            overlay: overlay,
            cleanup: function () {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                document.removeEventListener('touchmove', onMouseMove);
                document.removeEventListener('touchend', onMouseUp);
                document.removeEventListener('keydown', onKeyDown);
            }
        };
    }

    /**
     * Draw the semi-transparent overlay with the crop rectangle cut out.
     */
    function drawCropOverlay(ctx, rect, w, h) {
        ctx.clearRect(0, 0, w, h);

        // Dark overlay outside crop
        ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        ctx.fillRect(0, 0, w, h);

        // Clear the crop region
        ctx.clearRect(rect.x, rect.y, rect.w, rect.h);

        // Crop border
        ctx.strokeStyle = '#9500a5';
        ctx.lineWidth = 2;
        ctx.strokeRect(rect.x, rect.y, rect.w, rect.h);

        // Corner handles
        var hs = 8;
        ctx.fillStyle = '#9500a5';
        // NW
        ctx.fillRect(rect.x - hs / 2, rect.y - hs / 2, hs, hs);
        // NE
        ctx.fillRect(rect.x + rect.w - hs / 2, rect.y - hs / 2, hs, hs);
        // SW
        ctx.fillRect(rect.x - hs / 2, rect.y + rect.h - hs / 2, hs, hs);
        // SE
        ctx.fillRect(rect.x + rect.w - hs / 2, rect.y + rect.h - hs / 2, hs, hs);

        // Edge midpoint handles
        ctx.fillRect(rect.x + rect.w / 2 - hs / 2, rect.y - hs / 2, hs, hs);           // N
        ctx.fillRect(rect.x + rect.w / 2 - hs / 2, rect.y + rect.h - hs / 2, hs, hs);  // S
        ctx.fillRect(rect.x - hs / 2, rect.y + rect.h / 2 - hs / 2, hs, hs);           // W
        ctx.fillRect(rect.x + rect.w - hs / 2, rect.y + rect.h / 2 - hs / 2, hs, hs);  // E

        // Grid lines (rule of thirds)
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
        ctx.lineWidth = 0.5;
        ctx.beginPath();
        // Vertical thirds
        ctx.moveTo(rect.x + rect.w / 3, rect.y);
        ctx.lineTo(rect.x + rect.w / 3, rect.y + rect.h);
        ctx.moveTo(rect.x + 2 * rect.w / 3, rect.y);
        ctx.lineTo(rect.x + 2 * rect.w / 3, rect.y + rect.h);
        // Horizontal thirds
        ctx.moveTo(rect.x, rect.y + rect.h / 3);
        ctx.lineTo(rect.x + rect.w, rect.y + rect.h / 3);
        ctx.moveTo(rect.x, rect.y + 2 * rect.h / 3);
        ctx.lineTo(rect.x + rect.w, rect.y + 2 * rect.h / 3);
        ctx.stroke();
    }

    /**
     * Close and remove the active cropper modal.
     */
    function closeCropper() {
        if (!activeCropper) return;
        activeCropper.cleanup();
        if (activeCropper.overlay && activeCropper.overlay.parentNode) {
            activeCropper.overlay.classList.remove('is-visible');
            // Remove after transition
            setTimeout(function () {
                if (activeCropper && activeCropper.overlay && activeCropper.overlay.parentNode) {
                    activeCropper.overlay.parentNode.removeChild(activeCropper.overlay);
                }
                activeCropper = null;
            }, 200);
        } else {
            activeCropper = null;
        }
    }

    /**
     * Replace a file input's FileList with a blob.
     * Uses DataTransfer API (modern browsers) with fallback.
     *
     * @param {HTMLInputElement} input
     * @param {Blob}            blob
     * @param {string}          fileName
     * @param {string}          mimeType
     */
    function replaceFileInput(input, blob, fileName, mimeType) {
        var file = new File([blob], fileName, { type: mimeType, lastModified: Date.now() });
        if (typeof DataTransfer !== 'undefined') {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        }
        // Dispatch a change event so any other listeners know
        var event;
        if (typeof Event === 'function') {
            event = new Event('change', { bubbles: true });
        } else {
            event = document.createEvent('Event');
            event.initEvent('change', true, true);
        }
        input._zcrbCropProcessed = true;
        input.dispatchEvent(event);
    }

    /**
     * Initialize cropper integration for all file inputs on the form.
     */
    function initCropper() {
        var form = document.querySelector('[data-zcrb-form]');
        if (!form) return;

        var fileInputs = form.querySelectorAll('input[name="zcrb_images[]"]');
        if (!fileInputs || !fileInputs.length) return;

        var cfg = window.ZCRB || {};
        var maxMb = parseInt(cfg.imageMaxMb, 10) || 2;
        var allowedTypes = Array.isArray(cfg.imageTypes) && cfg.imageTypes.length
            ? cfg.imageTypes
            : ['image/jpeg', 'image/png', 'image/webp'];

        for (var fi = 0; fi < fileInputs.length; fi++) {
            (function (input) {
                input.addEventListener('change', function () {
                    // Skip if this change was triggered by our own replaceFileInput
                    if (input._zcrbCropProcessed) {
                        input._zcrbCropProcessed = false;
                        return;
                    }

                    if (!input.files || !input.files[0]) return;
                    var file = input.files[0];

                    // Only open cropper for valid image files
                    if (file.size > maxMb * 1024 * 1024) return;
                    if (allowedTypes.indexOf(file.type) === -1) return;

                    // Only crop image types
                    if (file.type.indexOf('image/') !== 0) return;

                    openCropper(
                        file,
                        function onCrop(blob, croppedName, mimeType) {
                            replaceFileInput(input, blob, croppedName, mimeType);
                            // Update the filename label
                            var uploadWrap = input.closest('.zcrb-upload');
                            var label = uploadWrap ? uploadWrap.querySelector('[data-zcrb-filename]') : null;
                            if (label) label.textContent = croppedName;
                        },
                        function onSkip() {
                            // Keep original file, nothing to do
                        }
                    );
                });
            })(fileInputs[fi]);
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', initCropper);

    // Expose for potential programmatic use
    window.ZCRBCropper = {
        open: openCropper,
        close: closeCropper
    };
})();
