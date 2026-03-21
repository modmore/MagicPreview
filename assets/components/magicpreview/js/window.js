/**
 * MagicPreview - Window module
 *
 * Manages the preview window lifecycle: open, navigate, close.
 * Exposes its interface on MagicPreview._window so the orchestrator
 * (preview.js) can delegate to it.
 *
 * This module has no dependencies on config — the orchestrator passes
 * the required URLs directly as arguments.
 */
(function() {
    window.MagicPreview = window.MagicPreview || {};

    /** @type {Window|null} */
    var previewWindow = null;

    /**
     * Opens or focuses the preview window with a loading state.
     * @param {string} previewUrl - The manager preview controller URL
     * @param {number} resourceId - The resource ID (for unique window name)
     */
    function open(previewUrl, resourceId) {
        if (previewWindow && !previewWindow.closed) {
            previewWindow.focus();
        } else {
            previewWindow = window.open(previewUrl + '#loading', 'MagicPreview_' + resourceId);
            previewWindow.opener = window;
        }
    }

    /**
     * Navigates the preview window to the given preview hash.
     * @param {string} previewUrl - The manager preview controller URL
     * @param {string} hash - The preview hash from the processor
     */
    function show(previewUrl, hash) {
        if (previewWindow && !previewWindow.closed) {
            previewWindow.location = previewUrl + '#' + hash;
        }
    }

    /**
     * Closes the preview window.
     */
    function close() {
        if (previewWindow && !previewWindow.closed) {
            previewWindow.close();
        }
        previewWindow = null;
    }

    /**
     * Check if the preview window is currently open.
     * @returns {boolean}
     */
    function isOpen() {
        return previewWindow !== null && !previewWindow.closed;
    }

    /**
     * Get the preview window reference (for consumers that need to set
     * custom location with additional query params, e.g. VersionX).
     * @returns {Window|null}
     */
    function getWindow() {
        if (previewWindow && !previewWindow.closed) {
            return previewWindow;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Internal API (consumed by the orchestrator in preview.js)
    // -------------------------------------------------------------------------

    window.MagicPreview._window = {
        open: open,
        show: show,
        close: close,
        isOpen: isOpen,
        getWindow: getWindow
    };
})();
