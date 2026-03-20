/**
 * MagicPreview - Preview unsaved resource changes
 *
 * Provides a global MagicPreview API for showing previews in either
 * a new browser window or an inline side panel. The API is consumed both
 * internally (via the Preview button) and externally (e.g. VersionX).
 *
 * @global {object} MagicPreviewConfig - Injected by PHP plugin
 * @global {number} MagicPreviewResource - Injected by PHP plugin
 */
(function() {
    // =========================================================================
    // Configuration (lazy-loaded: resolved on first access because the globals
    // MagicPreviewConfig, MagicPreviewResource and MODx.config may not exist
    // yet when this script file is parsed)
    // =========================================================================

    var _config = null;

    /**
     * Resolves and caches all configuration values from the PHP-injected globals.
     * Called lazily on first use.
     * @returns {object}
     */
    function config() {
        if (_config) return _config;

        var baseFrameUrl = MagicPreviewConfig.baseFrameUrl || '';
        _config = {
            previewUrl: MODx.config.manager_url
                + '?namespace=magicpreview&a=preview&resource=' + MagicPreviewResource,
            baseFrameUrl: baseFrameUrl,
            frameJoiner: baseFrameUrl.indexOf('?') === -1 ? '?' : '&',
            previewMode: MagicPreviewConfig.previewMode || 'newwindow',
            panelLayout: MagicPreviewConfig.panelLayout || 'overlay',
            panelExtended: !!MagicPreviewConfig.panelExtended,
            autoRefreshInterval: parseInt(MagicPreviewConfig.autoRefreshInterval, 10) || 0,
            breakpoints: MagicPreviewConfig.breakpoints || {},
            lexicon: MagicPreviewConfig.lexicon || {},
        };

        return _config;
    }

    /**
     * Returns a lexicon string by key, falling back to the key itself.
     * @param {string} key - Lexicon key (e.g. 'preview_button')
     * @returns {string}
     */
    function lexicon(key) {
        var lex = config().lexicon;
        return (lex && lex[key]) ? lex[key] : key;
    }

    // =========================================================================
    // Panel DOM management
    // =========================================================================

    /** @type {HTMLElement|null} */
    var panelEl = null;
    /** @type {HTMLIFrameElement|null} */
    var panelIframe = null;
    /** @type {HTMLElement|null} */
    var panelLoading = null;
    /** @type {HTMLElement|null} */
    var panelIdle = null;
    /** @type {string|null} The last preview hash loaded into the panel */
    var lastPanelHash = null;

    /**
     * Creates the panel DOM structure and appends it to the document body.
     * The panel is hidden by default (no .mmmp-panel--open class).
     */
    function createPanel() {
        if (panelEl) return;

        var c = config();

        panelEl = document.createElement('div');
        panelEl.id = 'mmmp-panel';
        panelEl.className = 'mmmp-panel mmmp-panel--' + c.panelLayout;

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
            +     '<button type="button" class="mmmp-panel__reload" title="' + lexicon('reload_preview') + '">'
            +       '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">'
            +         '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.015 4.356v4.992" />'
            +       '</svg>'
            +     '</button>'
            +     '<button type="button" class="mmmp-panel__close" title="' + lexicon('close_panel') + '">'
            +       '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">'
            +         '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />'
            +       '</svg>'
            +     '</button>'
            +   '</div>'
            + '</div>'
            + '<div class="mmmp-panel__content">'
            +   '<div class="mmmp-panel__frame-wrapper">'
            +     '<iframe class="mmmp-panel__iframe" id="mmmp-panel-iframe"></iframe>'
            +   '</div>'
            +   '<div class="mmmp-panel__loading">'
            +     '<div class="mmmp-c-animation"><div></div><div></div><div></div><div></div></div>'
            +     '<p>' + lexicon('preparing_preview') + '</p>'
            +   '</div>'
            +   '<div class="mmmp-panel__idle">'
            +     '<p>' + lexicon('idle_message') + '</p>'
            +   '</div>'
            + '</div>';

        document.body.appendChild(panelEl);

        panelIframe = document.getElementById('mmmp-panel-iframe');
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

        // Reload button: re-submits the form to regenerate the preview
        panelEl.querySelector('.mmmp-panel__reload').addEventListener('click', function() {
            submitPreview();
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

        var c = config();
        var frameWrapper = panelEl.querySelector('.mmmp-panel__frame-wrapper');
        var width;

        switch (bp) {
            case 'desktop':
                width = c.breakpoints.desktop || '1280px';
                break;
            case 'tablet':
                width = c.breakpoints.tablet || '768px';
                break;
            case 'mobile':
                width = c.breakpoints.mobile || '320px';
                break;
            default:
                width = '100%';
        }

        frameWrapper.style.width = width;
    }

    /**
     * Shows the loading state in the panel.
     */
    function showPanelLoading() {
        if (!panelIframe || !panelLoading) return;
        panelIframe.parentElement.style.display = 'none';
        panelLoading.style.display = 'flex';
        if (panelIdle) panelIdle.style.display = 'none';
        panelIframe.src = '';
    }

    /**
     * Shows the idle/placeholder state in the panel (no preview yet).
     */
    function showPanelIdle() {
        if (!panelIframe || !panelIdle) return;
        panelIframe.parentElement.style.display = 'none';
        panelLoading.style.display = 'none';
        panelIdle.style.display = 'flex';
        panelIframe.src = '';
    }

    /**
     * Loads a preview hash into the panel iframe.
     * @param {string} hash - The preview hash from the processor
     */
    function showPanelPreview(hash) {
        if (!panelIframe || !panelLoading) return;
        var c = config();
        lastPanelHash = hash;
        panelLoading.style.display = 'none';
        if (panelIdle) panelIdle.style.display = 'none';
        panelIframe.parentElement.style.display = 'block';
        panelIframe.src = c.baseFrameUrl + c.frameJoiner + 'show_preview=' + hash;
    }

    /** @type {boolean} Whether the window resize handler has been registered */
    var resizeHandlerRegistered = false;

    /**
     * Opens the panel and applies the layout mode.
     */
    function openPanel() {
        createPanel();

        // Restore previously set custom width from drag-resize
        if (customPanelWidth) {
            panelEl.style.width = customPanelWidth + 'px';
        }

        panelEl.classList.add('mmmp-panel--open');

        if (config().panelLayout === 'onpage') {
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
    function closePanel() {
        if (!panelEl) return;
        panelEl.classList.remove('mmmp-panel--open');
        document.body.classList.remove('mmmp-panel-onpage-active');
        syncActionButtonsOffset();
        stopAutoRefresh();

        if (panelIframe) {
            panelIframe.src = '';
        }
        lastPanelHash = null;

        relayoutModx();
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
            if (panelIframe) panelIframe.style.pointerEvents = 'none';

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

            if (panelIframe) panelIframe.style.pointerEvents = '';
            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            customPanelWidth = panelEl.offsetWidth;

            // For onpage mode, recalculate the ExtJS layout
            if (config().panelLayout === 'onpage' && document.body.classList.contains('mmmp-panel-onpage-active')) {
                relayoutModx();
            }
        }
    }

    /**
     * Synchronises the fixed-position action buttons bar's right offset
     * with the panel's current width, so it doesn't extend behind the panel.
     * Only applies in onpage mode when the panel is open.
     */
    function syncActionButtonsOffset() {
        var actionBar = document.getElementById('modx-action-buttons');
        if (!actionBar) return;

        if (config().panelLayout === 'onpage' && document.body.classList.contains('mmmp-panel-onpage-active')) {
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

        var isOpen = document.body.classList.contains('mmmp-panel-onpage-active');

        if (isOpen) {
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
            if (isOpen) {
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

    /**
     * For "onpage" layout with panel_extended: opens the panel immediately
     * on page load so it appears as a column alongside the editor with
     * a loading state while the preview is being generated.
     *
     * When panel_extended is off, the panel stays hidden until the user
     * clicks Preview — openPanel() handles everything at that point.
     */
    function initOnpagePanel() {
        if (config().panelLayout !== 'onpage') return;
        if (config().previewMode !== 'panel') return;
        if (!config().panelExtended) return;

        createPanel();
        panelEl.classList.add('mmmp-panel--open');
        document.body.classList.add('mmmp-panel-onpage-active');
        showPanelLoading();

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

    /**
     * Triggers an initial preview by submitting the form after the resource
     * panel has finished rendering. Only runs when panelExtended is
     * enabled and previewMode is 'panel'.
     */
    function initAutoPreview() {
        if (!config().panelExtended) return;
        if (config().previewMode !== 'panel') return;

        // For overlay mode, open the panel first (onpage is already open via initOnpagePanel)
        if (config().panelLayout === 'overlay') {
            openPanel();
            showPanelLoading();
        }

        // Wait for the resource panel to be available, then submit.
        // Use a short delay to let MODX finish initialising the form
        // (RTEs, resource groups, etc.) before we try to read the form data.
        var checkInterval = setInterval(function() {
            var panel = Ext.getCmp('modx-panel-resource');
            if (panel && panel.getForm()) {
                clearInterval(checkInterval);
                // Give RTEs a moment to initialise their content
                setTimeout(function() {
                    submitPreview();
                }, 500);
            }
        }, 100);
    }

    // =========================================================================
    // Popup window management
    // =========================================================================

    /** @type {Window|null} */
    var popupWindow = null;

    /**
     * Opens or focuses the popup window with a loading state.
     */
    function openPopup() {
        var c = config();
        if (popupWindow && !popupWindow.closed) {
            popupWindow.focus();
        } else {
            popupWindow = window.open(c.previewUrl + '#loading', 'MagicPreview_' + MagicPreviewResource);
            popupWindow.opener = window;
        }
    }

    /**
     * Navigates the popup window to the given preview hash.
     * @param {string} hash - The preview hash from the processor
     */
    function showPopupPreview(hash) {
        if (popupWindow && !popupWindow.closed) {
            popupWindow.location = config().previewUrl + '#' + hash;
        }
    }

    /**
     * Closes the popup window.
     */
    function closePopup() {
        if (popupWindow && !popupWindow.closed) {
            popupWindow.close();
        }
        popupWindow = null;
    }

    // =========================================================================
    // Public API: window.MagicPreview
    // =========================================================================

    /**
     * Global MagicPreview API.
     *
     * Used internally by the Preview button and externally by consumers
     * like VersionX. The API abstracts over new window vs panel mode so
     * consumers don't need to know the display mechanism.
     *
     * @namespace MagicPreview
     */
    window.MagicPreview = window.MagicPreview || {};

    /**
     * Show a preview for the given hash. Opens the new window or panel
     * depending on the configured preview mode.
     *
     * @param {string} hash - The preview hash returned by the processor.
     *   Pass 'loading' or empty string to show the loading state.
     */
    window.MagicPreview.show = function(hash) {
        if (config().previewMode === 'panel') {
            openPanel();
            if (!hash || hash === 'loading') {
                showPanelLoading();
            } else {
                showPanelPreview(hash);
            }
        } else {
            openPopup();
            if (hash && hash !== 'loading') {
                showPopupPreview(hash);
            }
        }
    };

    /**
     * Close the preview (new window or panel).
     */
    window.MagicPreview.close = function() {
        if (config().previewMode === 'panel') {
            closePanel();
        } else {
            closePopup();
        }
    };

    /**
     * Check if the preview is currently open/visible.
     * @returns {boolean}
     */
    window.MagicPreview.isOpen = function() {
        if (config().previewMode === 'panel') {
            return panelEl !== null && panelEl.classList.contains('mmmp-panel--open');
        } else {
            return popupWindow !== null && !popupWindow.closed;
        }
    };

    /**
     * Get the current preview mode.
     * @returns {string} 'newwindow' or 'panel'
     */
    window.MagicPreview.getMode = function() {
        return config().previewMode;
    };

    /**
     * Get the popup preview URL (used by VersionX to build custom URLs
     * with additional query parameters like revert type and version).
     * @returns {string}
     */
    window.MagicPreview.getPreviewUrl = function() {
        return config().previewUrl;
    };

    /**
     * Get the popup window reference (for consumers that need to set
     * custom location with additional query params, e.g. VersionX).
     * Returns null if in panel mode or if no popup is open.
     * @returns {Window|null}
     */
    window.MagicPreview.getPopupWindow = function() {
        if (popupWindow && !popupWindow.closed) {
            return popupWindow;
        }
        return null;
    };

    /**
     * Open/focus the new window or panel in a loading state, without a hash.
     * Used before the processor request is made.
     */
    window.MagicPreview.showLoading = function() {
        window.MagicPreview.show('loading');
    };

    // =========================================================================
    // Form submission: submits resource data to the preview processor
    // =========================================================================

    /**
     * Syncs form data for a silent auto-refresh preview submission.
     *
     * Replicates the essential parts of the resource panel's beforeSubmit
     * handler (RTE content sync, resource groups encoding) and collects
     * third-party extra data (e.g. ContentBlocks) without firing the
     * panel's 'save' event — which would cause side effects if triggered
     * every few seconds.
     *
     * @param {Ext.form.BasicForm} fm - The resource form
     * @param {MODx.panel.Resource} panel - The resource panel component
     */
    function syncFormForPreview(fm, panel) {
        // 1. Sync RTE content: copy textarea value to hiddenContent
        var ta = Ext.get(panel.contentField);
        if (ta) {
            var hc = Ext.getCmp('hiddenContent');
            if (hc) { hc.setValue(ta.dom.value); }
        }

        // 2. Sync RTE editors (TinyMCE, CKEditor, etc.)
        if (panel.cleanupEditor) {
            panel.cleanupEditor();
        }

        // 3. Encode resource groups into baseParams
        var g = Ext.getCmp('modx-grid-resource-security');
        if (g) {
            Ext.apply(fm.baseParams, {
                resource_groups: g.encode()
            });
        }

        // 4. ContentBlocks: inject block data if available
        if (typeof ContentBlocks !== 'undefined' && ContentBlocks.getData) {
            fm.baseParams['contentblocks'] = ContentBlocks.getData();
        }
    }

    /**
     * Submits the resource form to the MagicPreview processor.
     *
     * Bypasses MODx.FormPanel.submit() entirely to avoid the save mask
     * (waitMsg), tree refresh, dirty-state reset, and "Save successful"
     * status message that MODX's default success handler triggers.
     *
     * For manual previews (button click, initial auto-preview), we fire
     * the panel's beforeSubmit event so all extras can prepare their data.
     *
     * For silent auto-refreshes, we use syncFormForPreview() which
     * replicates the essential data syncing without firing the 'save'
     * event that beforeSubmit triggers at the end.
     *
     * @param {object} [options] Optional settings for this submission
     * @param {boolean} [options.showLoading=true] Whether to show the loading
     *   animation. Set to false for background auto-refreshes.
     */
    function submitPreview(options) {
        options = options || {};
        var showLoading = options.showLoading !== false;

        var panel = Ext.getCmp('modx-panel-resource');
        if (!panel) return;

        var fm = panel.getForm();
        if (!fm) return;

        // Show loading state immediately (only for manual/initial previews)
        if (showLoading) {
            MagicPreview.showLoading();
        }

        // Mark a request as in-flight so auto-refresh doesn't overlap
        submitInFlight = true;

        // Validate form fields (only for manual previews — skip for
        // silent auto-refreshes to avoid marking fields invalid)
        if (showLoading) {
            var isValid = true;
            if (fm.items && fm.items.items) {
                for (var fld in fm.items.items) {
                    if (fm.items.items[fld] && fm.items.items[fld].validate) {
                        if (!fm.items.items[fld].validate()) {
                            fm.items.items[fld].markInvalid();
                            isValid = false;
                        }
                    }
                }
            }
            if (!isValid) {
                submitInFlight = false;
                return;
            }
        }

        if (showLoading) {
            // Manual preview: fire beforeSubmit so all extras can prepare
            // their data (ContentBlocks, etc.) — same as a normal save.
            var canSubmit = panel.fireEvent('beforeSubmit', {
                form: fm,
                options: {},
                config: panel.config
            });
            if (canSubmit === false) {
                submitInFlight = false;
                return;
            }
        } else {
            // Silent auto-refresh: sync form data without firing save event
            syncFormForPreview(fm, panel);
        }

        // Stash and swap the action + URL on the BasicForm so ExtJS
        // posts to our preview connector instead of the real save endpoint.
        var originalAction = fm.baseParams['action'];
        var originalUrl = fm.url;
        fm.baseParams['action'] = 'resource/preview';
        fm.url = MagicPreviewConfig.assetsUrl + 'connector.php';

        fm.submit({
            // No waitMsg — prevents the "Saving..." mask
            headers: {
                'Powered-By': 'MODx',
                'modAuth': MODx.siteId
            },
            success: function(form, action) {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                submitInFlight = false;

                var result = action.result;
                if (result && result.object && result.object.preview_hash) {
                    var hash = result.object.preview_hash;

                    // Only reload the iframe if the hash (and therefore
                    // the preview data) has actually changed.  The server
                    // returns a deterministic hash based on the cached
                    // data, so identical content produces the same key.
                    if (hash !== lastPanelHash) {
                        MagicPreview.show(hash);
                    }
                }

                // (Re)start the auto-refresh timer after a successful preview
                startAutoRefresh();
            },
            failure: function(form, action) {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                submitInFlight = false;
            }
        });
    }

    // =========================================================================
    // Auto-refresh: periodically re-submits the form to detect changes
    // =========================================================================

    /** @type {boolean} Whether a preview submit request is currently in flight */
    var submitInFlight = false;

    /** @type {number|null} The setInterval ID for auto-refresh */
    var autoRefreshTimer = null;

    /**
     * Starts the auto-refresh timer. If one is already running, it is
     * restarted (reset). Only runs when the panel is open and the
     * interval setting is > 0.
     *
     * The timer always re-submits the form to the preview processor.
     * Change detection happens server-side: the processor returns a
     * deterministic hash of the cached data, and the client skips
     * reloading the iframe when the hash hasn't changed.  This approach
     * correctly captures data from all sources (TVs, ContentBlocks,
     * and any extras that inject data via beforeSubmit / baseParams).
     */
    function startAutoRefresh() {
        stopAutoRefresh();

        var interval = config().autoRefreshInterval;
        if (interval <= 0) return;
        if (config().previewMode !== 'panel') return;
        if (!MagicPreview.isOpen()) return;

        autoRefreshTimer = setInterval(function() {
            // Skip if a request is already in flight
            if (submitInFlight) return;

            // Skip if the panel was closed in the meantime
            if (!MagicPreview.isOpen()) {
                stopAutoRefresh();
                return;
            }

            // Always re-submit; the server returns a deterministic hash
            // and the client skips the iframe reload when data is unchanged.
            submitPreview({ showLoading: false });
        }, interval * 1000);
    }

    /**
     * Stops the auto-refresh timer.
     */
    function stopAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }

    // =========================================================================
    // ExtJS: Button injection
    // =========================================================================

    Ext.onReady(function() {
        var basePage = MODx.page.UpdateResource;

        // Check for custom page types and extend those instead.
        // If a custom resource is loaded its type will be 'object'.
        switch ('object') {
            case typeof Articles:
                basePage = Articles.page.UpdateArticle ?? basePage;
                break;
            case typeof collections:
                basePage = collections.page.UpdateCategory ?? basePage;
                basePage = collections.page.UpdateSelection ?? basePage;
                break;
            case typeof LocationResources:
                basePage = LocationResources.page.UpdateLocation ?? basePage;
                break;
        }

        Ext.override(basePage, {
            _originals: {
                getButtons: basePage.prototype.getButtons
            },
            getButtons: function(cfg) {
                var btns = this._originals.getButtons.call(this, cfg);
                var btnView = btns.map(function(btn) { return btn.id; }).indexOf('modx-abtn-preview');
                // If the View button doesn't exist, insert at the start of the array
                if (btnView === -1) btnView = 0;
                btns.splice(btnView, 0, {
                    text: lexicon('preview_button'),
                    id: 'modx-abtn-real-preview',
                    handler: function() { submitPreview(); },
                    scope: this
                });
                return btns;
            },

            // Make sure the view button still has the preview url.
            preview: function(previewConfig) {
                window.open(previewConfig.scope.preview_url);
                return false;
            }
        });

        // For "onpage" panel layout, open the panel immediately as a
        // permanent column alongside the resource editor.
        initOnpagePanel();

        // Auto-preview: submit the form immediately to generate a preview
        initAutoPreview();
    });
})();
