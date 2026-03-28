/**
 * MagicPreview - Panel module
 *
 * Manages the inline side panel: DOM creation, open/close lifecycle,
 * double-buffered iframe swap with scroll preservation, breakpoint
 * controls, drag-to-resize, ExtJS Viewport layout override, and
 * action buttons offset.
 *
 * Exposes its interface on MagicPreview._panel so the orchestrator
 * (preview.js) can delegate to it.
 *
 * Must be initialised via init(cfg) before any other method is called.
 * The config object is provided by the orchestrator from the lazy-loaded
 * PHP-injected globals.
 */
(function() {
    window.MagicPreview = window.MagicPreview || {};

    // =========================================================================
    // Configuration (set via init)
    // =========================================================================

    /** @type {string} Setting value for overlay layout */
    var LAYOUT_OVERLAY = 'Overlay';
    /** @type {string} Setting value for on-page layout */
    var LAYOUT_ONPAGE = 'On Page';

    /** @type {object|null} */
    var _cfg = null;

    /**
     * Returns a CSS-safe slug from a setting value.
     * e.g. 'On Page' -> 'onpage', 'Overlay' -> 'overlay'
     * @param {string} value
     * @returns {string}
     */
    function cssSlug(value) {
        return value.toLowerCase().replace(/\s+/g, '');
    }

    /**
     * Returns a lexicon string by key, falling back to the key itself.
     * @param {string} key
     * @returns {string}
     */
    function lexicon(key) {
        var lex = _cfg && _cfg.lexicon;
        return (lex && lex[key]) ? lex[key] : key;
    }

    // =========================================================================
    // Panel DOM management
    // =========================================================================

    /** @type {HTMLElement|null} */
    var panelEl = null;
    /** @type {HTMLIFrameElement|null} The currently visible iframe */
    var panelIframeA = null;
    /** @type {HTMLIFrameElement|null} The off-screen staging iframe */
    var panelIframeB = null;
    /** @type {HTMLElement|null} */
    var panelLoading = null;
    /** @type {HTMLElement|null} */
    var panelIdle = null;
    /** @type {string|null} The last preview hash loaded into the panel */
    var lastHash = null;

    /**
     * Creates the panel DOM structure and appends it to the document body.
     * The panel is hidden by default (no .mmmp-panel--open class).
     */
    function createPanel() {
        if (panelEl) return;

        panelEl = document.createElement('div');
        panelEl.id = 'mmmp-panel';
        panelEl.className = 'mmmp-panel mmmp-panel--' + cssSlug(_cfg.panelLayout);

        // Build breakpoint buttons HTML
        var bpKeys = [
            { key: 'full', label: lexicon('bp_full') },
            { key: 'desktop', label: lexicon('bp_desktop') },
            { key: 'tablet', label: lexicon('bp_tablet') },
            { key: 'mobile', label: lexicon('bp_mobile') }
        ];
        var bpHtml = '';
        for (var i = 0; i < bpKeys.length; i++) {
            var bp = bpKeys[i];
            var active = bp.key === 'full' ? ' mmmp-panel__bp-btn--active' : '';
            bpHtml += '<button type="button" class="mmmp-panel__bp-btn' + active + '" data-bp="' + bp.key + '">'
                + bp.label
                + '</button>';
        }

        panelEl.innerHTML = ''
            + '<div class="mmmp-panel__toolbar">'
            +   '<div class="mmmp-panel__breakpoints">' + bpHtml + '</div>'
            +   '<div class="mmmp-panel__actions">'
            +     '<button type="button" class="mmmp-panel__reload" title="' + lexicon('reload_preview') + '" aria-label="' + lexicon('reload_preview') + '">'
            +       '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">'
            +         '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.015 4.356v4.992" />'
            +       '</svg>'
            +     '</button>'
            +     '<button type="button" class="mmmp-panel__save-draft" title="' + lexicon('save_draft') + '" aria-label="' + lexicon('save_draft') + '">'
            +       '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
            +         '<path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0z" />'
            +       '</svg>'
            +     '</button>'
            +     '<button type="button" class="mmmp-panel__close" title="' + lexicon('close_panel') + '" aria-label="' + lexicon('close_panel') + '">'
            +       '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">'
            +         '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />'
            +       '</svg>'
            +     '</button>'
            +   '</div>'
            + '</div>'
            + '<div class="mmmp-panel__content">'
            +   '<div class="mmmp-panel__frame-wrapper">'
            +     '<iframe class="mmmp-panel__iframe mmmp-panel__iframe--active" id="mmmp-panel-iframe-a"></iframe>'
            +     '<iframe class="mmmp-panel__iframe mmmp-panel__iframe--staging" id="mmmp-panel-iframe-b"></iframe>'
            +   '</div>'
            +   '<div class="mmmp-panel__loading">'
            +     '<div class="mmmp-c-animation"><div></div><div></div><div></div><div></div></div>'
            +     '<p>' + lexicon('preparing_preview') + '</p>'
            +   '</div>'
            +   '<div class="mmmp-panel__idle">'
            +     '<p>' + lexicon('idle_message') + '</p>'
            +   '</div>'
            + '</div>';

        // Apply saved width before appending to prevent width flash on reload
        if (customPanelWidth) {
            panelEl.style.width = customPanelWidth + 'px';
        }

        document.body.appendChild(panelEl);

        panelIframeA = document.getElementById('mmmp-panel-iframe-a');
        panelIframeB = document.getElementById('mmmp-panel-iframe-b');
        panelLoading = panelEl.querySelector('.mmmp-panel__loading');
        panelIdle = panelEl.querySelector('.mmmp-panel__idle');

        // Resize handle on the left edge of the panel
        var resizeHandle = document.createElement('div');
        resizeHandle.className = 'mmmp-panel__resize-handle';
        panelEl.appendChild(resizeHandle);
        initResize(resizeHandle);

        // Close button
        panelEl.querySelector('.mmmp-panel__close').addEventListener('click', function() {
            MagicPreview.close();
        });

        // Reload button: delegates to the orchestrator's onReload callback
        panelEl.querySelector('.mmmp-panel__reload').addEventListener('click', function() {
            if (_cfg.onReload) _cfg.onReload();
        });

        // Save Draft button: delegates to the orchestrator's onSaveDraft callback
        panelEl.querySelector('.mmmp-panel__save-draft').addEventListener('click', function() {
            if (_cfg.onSaveDraft) _cfg.onSaveDraft();
        });

        // Breakpoint buttons
        var bpBtns = panelEl.querySelectorAll('.mmmp-panel__bp-btn');
        for (var b = 0; b < bpBtns.length; b++) {
            bpBtns[b].addEventListener('click', function() {
                setBreakpoint(this.getAttribute('data-bp'));

                // Update active state
                for (var j = 0; j < bpBtns.length; j++) {
                    bpBtns[j].classList.remove('mmmp-panel__bp-btn--active');
                }
                this.classList.add('mmmp-panel__bp-btn--active');
            });
        }
    }

    /**
     * Sets the iframe content width based on the selected breakpoint.
     * The panel width stays fixed; the frame wrapper width changes so
     * the content overflows horizontally and becomes scrollable.
     * @param {string} bp - Breakpoint key: 'full', 'desktop', 'tablet', 'mobile'
     */
    function setBreakpoint(bp) {
        if (!panelEl) return;

        var frameWrapper = panelEl.querySelector('.mmmp-panel__frame-wrapper');
        var width;

        switch (bp) {
            case 'desktop':
                width = (_cfg.breakpoints && _cfg.breakpoints.desktop) || '1280px';
                break;
            case 'tablet':
                width = (_cfg.breakpoints && _cfg.breakpoints.tablet) || '768px';
                break;
            case 'mobile':
                width = (_cfg.breakpoints && _cfg.breakpoints.mobile) || '320px';
                break;
            default:
                width = '100%';
        }

        frameWrapper.style.width = width;
    }

    /**
     * Shows the loading state in the panel.
     */
    function showLoading() {
        if (!panelIframeA || !panelLoading) return;
        panelIframeA.parentElement.style.display = 'none';
        panelLoading.style.display = 'flex';
        if (panelIdle) panelIdle.style.display = 'none';
        panelIframeA.src = '';
        panelIframeB.src = '';

        // Reset iframe roles to a known state
        panelIframeA.className = 'mmmp-panel__iframe mmmp-panel__iframe--active';
        panelIframeB.className = 'mmmp-panel__iframe mmmp-panel__iframe--staging';
    }

    /**
     * Shows the idle/placeholder state in the panel (no preview yet).
     */
    function showIdle() {
        if (!panelIframeA || !panelIdle) return;
        panelIframeA.parentElement.style.display = 'none';
        panelLoading.style.display = 'none';
        panelIdle.style.display = 'flex';
        panelIframeA.src = '';
        panelIframeB.src = '';

        // Reset iframe roles to a known state
        panelIframeA.className = 'mmmp-panel__iframe mmmp-panel__iframe--active';
        panelIframeB.className = 'mmmp-panel__iframe mmmp-panel__iframe--staging';
    }

    /**
     * Loads a preview URL into the panel using a double-buffered iframe
     * swap to avoid any visual flash or scroll-jump.
     *
     * The new URL is loaded into the hidden "staging" iframe. Once its
     * load event fires, the previous scroll position is restored, then
     * the staging iframe is swapped to active (visible) and the old
     * active iframe becomes the new staging (hidden). The user sees
     * the old content the entire time, then an instant cut to the new
     * content already at the correct scroll position.
     *
     * @param {string} url - The full preview URL to load into the iframe
     */
    function showPreview(url) {
        if (!panelIframeA || !panelIframeB || !panelLoading) return;
        panelLoading.style.display = 'none';
        if (panelIdle) panelIdle.style.display = 'none';

        // Determine which iframe is currently active (visible) and
        // which is staging (hidden). Active has the --active class.
        var active = panelIframeA.classList.contains('mmmp-panel__iframe--active')
            ? panelIframeA : panelIframeB;
        var staging = (active === panelIframeA) ? panelIframeB : panelIframeA;

        // Make sure the frame wrapper is visible
        active.parentElement.style.display = 'block';

        // Capture the current scroll position from the active iframe.
        var scrollX = 0, scrollY = 0;
        try {
            if (active.contentWindow) {
                scrollX = active.contentWindow.scrollX || 0;
                scrollY = active.contentWindow.scrollY || 0;
            }
        } catch (e) { /* cross-origin or no document — use 0,0 */ }

        // Register the load handler before setting src to avoid a race
        // condition where a cached response fires the event synchronously.
        var onLoad = function() {
            staging.removeEventListener('load', onLoad);

            // Restore scroll position in the staging iframe before
            // making it visible, so there is no flash.
            try {
                staging.contentWindow.scrollTo(scrollX, scrollY);
            } catch (e) { /* ignore */ }

            // Swap: staging becomes active, active becomes staging.
            staging.classList.remove('mmmp-panel__iframe--staging');
            staging.classList.add('mmmp-panel__iframe--active');
            active.classList.remove('mmmp-panel__iframe--active');
            active.classList.add('mmmp-panel__iframe--staging');

            // Clear the old iframe so it doesn't consume resources
            // in the background.
            active.src = '';
        };
        staging.addEventListener('load', onLoad);

        // Load the new preview into the staging (hidden) iframe.
        staging.src = url;
    }

    // =========================================================================
    // Panel open / close lifecycle
    // =========================================================================

    /** @type {boolean} Whether the window resize handler has been registered */
    var resizeHandlerRegistered = false;

    /**
     * Opens the panel and applies the layout mode.
     */
    function open() {
        createPanel();

        // Restore previously set custom width from drag-resize
        if (customPanelWidth) {
            panelEl.style.width = customPanelWidth + 'px';
        }

        panelEl.classList.add('mmmp-panel--open');

        if (_cfg.panelLayout === LAYOUT_ONPAGE) {
            document.body.classList.add('mmmp-panel-onpage-active');
            syncActionButtonsOffset();
            relayoutModx();

            // Register the resize handler once so the layout stays
            // correct when the browser window is resized.
            if (!resizeHandlerRegistered) {
                resizeHandlerRegistered = true;
                Ext.EventManager.onWindowResize(function() {
                    if (document.body.classList.contains('mmmp-panel-onpage-active')) {
                        syncActionButtonsOffset();
                        relayoutModx();
                    }
                });
            }
        }
    }

    /**
     * Closes the panel and restores the editor layout.
     */
    function close() {
        if (!panelEl) return;
        panelEl.classList.remove('mmmp-panel--open');
        document.body.classList.remove('mmmp-panel-onpage-active');
        syncActionButtonsOffset();

        if (panelIframeA) {
            panelIframeA.src = '';
        }
        if (panelIframeB) {
            panelIframeB.src = '';
        }
        lastHash = null;

        relayoutModx();
    }

    /**
     * Check if the panel is currently open/visible.
     * @returns {boolean}
     */
    function isOpen() {
        return panelEl !== null && panelEl.classList.contains('mmmp-panel--open');
    }

    /**
     * Returns the panel's current pixel width, accounting for CSS
     * min-width / max-width clamping.
     * @returns {number}
     */
    function getPanelWidth() {
        if (panelEl && panelEl.classList.contains('mmmp-panel--open')) {
            return panelEl.offsetWidth;
        }
        return 0;
    }

    // =========================================================================
    // Drag-to-resize
    // =========================================================================

    /** @type {number|null} Custom panel width set by drag, in pixels */
    var customPanelWidth = null;

    /**
     * Initialises drag-to-resize on the given handle element.
     * Dragging the left edge of the panel resizes it horizontally.
     * On mouse-up, the new width is applied and the ExtJS layout is
     * recalculated (for onpage mode).
     * @param {HTMLElement} handle
     */
    function initResize(handle) {
        var startX, startWidth;

        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startX = e.clientX;
            startWidth = panelEl.offsetWidth;

            // Disable iframe pointer events during drag so mousemove
            // isn't swallowed by the iframe
            if (panelIframeA) panelIframeA.style.pointerEvents = 'none';
            if (panelIframeB) panelIframeB.style.pointerEvents = 'none';

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        function onMouseMove(e) {
            // Panel is on the right, so dragging left (decreasing clientX) should increase width
            var newWidth = startWidth + (startX - e.clientX);

            // Clamp to min/max from CSS (320px min, 80% of viewport max)
            var minW = 320;
            var maxW = window.innerWidth * 0.8;
            newWidth = Math.max(minW, Math.min(maxW, newWidth));

            panelEl.style.width = newWidth + 'px';
            syncActionButtonsOffset();
        }

        function onMouseUp() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            if (panelIframeA) panelIframeA.style.pointerEvents = '';
            if (panelIframeB) panelIframeB.style.pointerEvents = '';
            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            customPanelWidth = panelEl.offsetWidth;

            // Notify the orchestrator so it can persist the new width
            if (_cfg.onResize) {
                _cfg.onResize(customPanelWidth);
            }

            // For onpage mode, recalculate the ExtJS layout
            if (_cfg.panelLayout === LAYOUT_ONPAGE && document.body.classList.contains('mmmp-panel-onpage-active')) {
                relayoutModx();
            }
        }
    }

    // =========================================================================
    // Action buttons offset + ExtJS Viewport layout
    // =========================================================================

    /**
     * Synchronises the fixed-position action buttons bar's right offset
     * with the panel's current width, so it doesn't extend behind the panel.
     * Only applies in onpage mode when the panel is open.
     */
    function syncActionButtonsOffset() {
        var actionBar = document.getElementById('modx-action-buttons');
        if (!actionBar) return;

        if (_cfg.panelLayout === LAYOUT_ONPAGE && document.body.classList.contains('mmmp-panel-onpage-active')) {
            actionBar.style.right = getPanelWidth() + 'px';
        } else {
            actionBar.style.right = '';
        }
    }

    /** @type {Function|null} Stored reference to the original getViewSize method */
    var _originalGetViewSize = null;

    /**
     * Overrides the ExtJS Viewport's size calculation so its border
     * layout positions panels within a narrower area, leaving room
     * for our preview panel on the right.
     *
     * Ext.Viewport always measures document.body, and its getViewSize()
     * returns window.innerWidth regardless of any CSS width constraints.
     * We override that method on the Viewport's element so the border
     * layout reads our reduced width, then call doLayout() to trigger
     * a full recalculation.
     */
    function relayoutModx() {
        var layout = Ext.getCmp('modx-layout');
        if (!layout) return;

        var panelIsOpen = document.body.classList.contains('mmmp-panel-onpage-active');

        if (panelIsOpen) {
            // Store the original on first override
            if (!_originalGetViewSize) {
                _originalGetViewSize = layout.el.getViewSize.bind(layout.el);
            }

            var pw = getPanelWidth();
            layout.el.getViewSize = function() {
                return {
                    width: window.innerWidth - pw,
                    height: window.innerHeight
                };
            };
        } else if (_originalGetViewSize) {
            // Remove the instance override so the prototype method is used again
            delete layout.el.getViewSize;
            _originalGetViewSize = null;
        }

        // Delay to allow the panel to render and be measurable
        setTimeout(function() {
            // Re-read panel width now that it's in the DOM
            if (panelIsOpen) {
                var pw = getPanelWidth();
                layout.el.getViewSize = function() {
                    return {
                        width: window.innerWidth - pw,
                        height: window.innerHeight
                    };
                };
            }
            layout.doLayout();
        }, 50);
    }

    // =========================================================================
    // Onpage panel initialisation
    // =========================================================================

    /**
     * For "onpage" layout with saved panel state open: opens the panel
     * immediately on page load so it appears as a column alongside the
     * editor with a loading state while the preview is being generated.
     *
     * When the saved state is closed, the panel stays hidden until the
     * user clicks Preview — open() handles everything at that point.
     */
    function initOnpage() {
        if (_cfg.panelLayout !== LAYOUT_ONPAGE) return;
        if (!_cfg.panelOpen) return;

        createPanel();
        panelEl.classList.add('mmmp-panel--open');
        document.body.classList.add('mmmp-panel-onpage-active');
        showLoading();

        syncActionButtonsOffset();
        relayoutModx();

        // Register the resize handler so the layout stays correct
        // when the browser window is resized.
        resizeHandlerRegistered = true;
        Ext.EventManager.onWindowResize(function() {
            if (document.body.classList.contains('mmmp-panel-onpage-active')) {
                syncActionButtonsOffset();
                relayoutModx();
            }
        });
    }

    // =========================================================================
    // Internal API (consumed by the orchestrator in preview.js)
    // =========================================================================

    window.MagicPreview._panel = {
        /**
         * Initialise the panel module with configuration.
         * Must be called once before any other method.
         * @param {object} cfg
         * @param {string} cfg.panelLayout - LAYOUT_OVERLAY or LAYOUT_ONPAGE
         * @param {boolean} cfg.panelOpen - Whether the panel was open when last saved
         * @param {number|null} cfg.savedWidth - Saved panel width in pixels
         * @param {object} cfg.breakpoints - {desktop, tablet, mobile}
         * @param {object} cfg.lexicon - Lexicon strings
         * @param {Function} cfg.onReload - Callback for reload button
         * @param {Function} cfg.onSaveDraft - Callback for save draft button
         * @param {Function} cfg.onResize - Callback when panel is resized (receives width in px)
         */
        init: function(cfg) {
            _cfg = cfg;
            if (cfg.savedWidth) {
                customPanelWidth = cfg.savedWidth;
            }
        },

        open: open,
        close: close,
        isOpen: isOpen,
        showLoading: showLoading,
        showIdle: showIdle,
        showPreview: showPreview,
        initOnpage: initOnpage,
        getPanelWidth: getPanelWidth,

        /**
         * Get the last preview hash that was loaded.
         * Used by the orchestrator to skip duplicate iframe reloads.
         * @returns {string|null}
         */
        getLastHash: function() { return lastHash; },

        /**
         * Set the last preview hash.
         * @param {string|null} hash
         */
        setLastHash: function(hash) { lastHash = hash; }
    };
})();
