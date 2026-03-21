/**
 * MagicPreview - Orchestrator
 *
 * Thin orchestration layer that wires together the window module (_window)
 * and panel module (_panel). Owns: lazy config resolution, the public
 * window.MagicPreview API, form submission (manual + silent auto-refresh),
 * auto-refresh timer, auto-preview on page load, and Preview button
 * injection into the MODX manager action bar.
 *
 * Load order: window.js -> panel.js -> preview.js (this file, last)
 *
 * @global {object}  MagicPreviewConfig   - Injected by PHP plugin
 * @global {number}  MagicPreviewResource - Injected by PHP plugin
 */
(function() {
    // =========================================================================
    // Setting value constants
    // =========================================================================

    /** @type {string} Setting value for panel preview mode */
    var MODE_PANEL = 'Panel';
    /** @type {string} Setting value for new window preview mode */
    var MODE_WINDOW = 'New Window';
    /** @type {string} Setting value for overlay panel layout */
    var LAYOUT_OVERLAY = 'Overlay';

    // =========================================================================
    // Configuration (lazy-loaded: resolved on first access because the
    // globals MagicPreviewConfig, MagicPreviewResource and MODx.config may
    // not exist yet when this script is parsed from <head>)
    // =========================================================================

    var _config = null;

    /**
     * Resolves and caches all configuration values from the PHP-injected
     * globals. Called lazily on first use.
     * @returns {object}
     */
    function config() {
        if (_config) return _config;

        var baseFrameUrl = MagicPreviewConfig.baseFrameUrl ?? '';
        _config = {
            previewUrl: MODx.config.manager_url
                + '?namespace=magicpreview&a=preview&resource=' + MagicPreviewResource,
            baseFrameUrl: baseFrameUrl,
            frameJoiner: baseFrameUrl.indexOf('?') === -1 ? '?' : '&',
            previewMode: MagicPreviewConfig.previewMode ?? MODE_WINDOW,
            panelLayout: MagicPreviewConfig.panelLayout ?? LAYOUT_OVERLAY,
            panelExtended: !!MagicPreviewConfig.panelExtended,
            autoRefreshInterval: parseInt(MagicPreviewConfig.autoRefreshInterval, 10) || 0,
            breakpoints: MagicPreviewConfig.breakpoints ?? {},
            lexicon: MagicPreviewConfig.lexicon ?? {},
            hasDraft: !!MagicPreviewConfig.hasDraft,
            draftSavedAt: MagicPreviewConfig.draftSavedAt ?? '',
            autoSaveDraft: !!MagicPreviewConfig.autoSaveDraft
        };

        return _config;
    }

    /**
     * Returns a lexicon string by key, falling back to the key itself.
     * @param {string} key
     * @returns {string}
     */
    function lexicon(key) {
        var lex = config().lexicon;
        return (lex && lex[key]) ? lex[key] : key;
    }

    // =========================================================================
    // References to sub-modules (set by window.js and panel.js before us)
    // =========================================================================

    var _window = window.MagicPreview._window;
    var _panel = window.MagicPreview._panel;

    // =========================================================================
    // Panel helper: build the full preview URL from a hash
    // =========================================================================

    /**
     * @param {string} hash
     * @returns {string}
     */
    function previewFrameUrl(hash) {
        var c = config();
        return c.baseFrameUrl + c.frameJoiner + 'show_preview=' + hash;
    }

    // =========================================================================
    // Public API: window.MagicPreview
    // =========================================================================

    /**
     * Show a preview for the given hash. Opens the window or panel
     * depending on the configured preview mode.
     *
     * @param {string} hash - The preview hash returned by the processor.
     *   Pass 'loading' or empty string to show the loading state.
     */
    window.MagicPreview.show = function(hash) {
        var c = config();

        if (c.previewMode === MODE_PANEL) {
            _panel.open();
            if (!hash || hash === 'loading') {
                _panel.showLoading();
            } else {
                _panel.setLastHash(hash);
                _panel.showPreview(previewFrameUrl(hash));
            }
        } else {
            _window.open(c.previewUrl, MagicPreviewResource);
            if (hash && hash !== 'loading') {
                _window.show(c.previewUrl, hash);
            }
        }
    };

    /**
     * Close the preview (window or panel).
     */
    window.MagicPreview.close = function() {
        if (config().previewMode === MODE_PANEL) {
            _panel.close();
            stopAutoRefresh();
        } else {
            _window.close();
        }
    };

    /**
     * Check if the preview is currently open/visible.
     * @returns {boolean}
     */
    window.MagicPreview.isOpen = function() {
        if (config().previewMode === MODE_PANEL) {
            return _panel.isOpen();
        }
        return _window.isOpen();
    };

    /**
     * Get the current preview mode.
     * @returns {string} MODE_WINDOW or MODE_PANEL
     */
    window.MagicPreview.getMode = function() {
        return config().previewMode;
    };

    /**
     * Get the preview URL (used by VersionX to build custom URLs
     * with additional query parameters like revert type and version).
     * @returns {string}
     */
    window.MagicPreview.getPreviewUrl = function() {
        return config().previewUrl;
    };

    /**
     * Get the preview window reference. Returns null if in panel mode or
     * if no window is open.
     * @returns {Window|null}
     */
    window.MagicPreview.getWindow = function() {
        return _window.getWindow();
    };

    /**
     * Open/focus the window or panel in a loading state, without a hash.
     * Used before the processor request is made.
     */
    window.MagicPreview.showLoading = function() {
        window.MagicPreview.show('loading');
    };

    // =========================================================================
    // Form submission: submits resource data to the preview processor
    // =========================================================================

    /** @type {boolean} Whether a preview submit request is currently in flight */
    var submitInFlight = false;

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
     * @param {boolean} [options.showLoading=true] Whether to show the
     *   loading animation. Set to false for background auto-refreshes.
     * @param {boolean} [options.saveDraft=false] Whether to explicitly
     *   request a draft save (sent as save_draft=1 to the processor).
     */
    function submitPreview(options) {
        options = options || {};
        var showLoading = options.showLoading !== false;
        var saveDraft = !!options.saveDraft;

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

        // When explicitly saving a draft, tell the processor to always
        // save regardless of the auto_save_draft setting.
        if (saveDraft) {
            fm.baseParams['save_draft'] = '1';
        }

        fm.submit({
            // No waitMsg — prevents the "Saving..." mask
            headers: {
                'Powered-By': 'MODx',
                'modAuth': MODx.siteId
            },
            success: function(form, action) {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                delete fm.baseParams['save_draft'];
                submitInFlight = false;

                // If the panel was closed while the request was in flight,
                // discard the result to avoid re-opening the panel.
                if (config().previewMode === MODE_PANEL && !MagicPreview.isOpen()) {
                    return;
                }

                var result = action.result;
                if (result && result.object && result.object.preview_hash) {
                    var hash = result.object.preview_hash;

                    // Only reload the iframe if the hash (and therefore
                    // the preview data) has actually changed.
                    if (config().previewMode === MODE_PANEL) {
                        if (hash !== _panel.getLastHash()) {
                            MagicPreview.show(hash);
                        }
                    } else {
                        MagicPreview.show(hash);
                    }
                }

                // Show status feedback when a draft was explicitly saved
                if (saveDraft) {
                    MODx.msg.status({
                        title: lexicon('draft_saved'),
                        delay: 3
                    });
                }

                // (Re)start the auto-refresh timer after a successful preview
                startAutoRefresh();
            },
            failure: function(form, action) {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                delete fm.baseParams['save_draft'];
                submitInFlight = false;
            }
        });
    }

    // =========================================================================
    // Auto-refresh: periodically re-submits the form to detect changes
    // =========================================================================

    /** @type {number|null} The setInterval ID for auto-refresh */
    var autoRefreshTimer = null;

    /**
     * Starts the auto-refresh timer. If one is already running, it is
     * restarted (reset). Only runs when the panel is open and the
     * interval setting is > 0.
     */
    function startAutoRefresh() {
        stopAutoRefresh();

        var interval = config().autoRefreshInterval;
        if (interval <= 0) return;
        if (config().previewMode !== MODE_PANEL) return;
        if (!MagicPreview.isOpen()) return;

        autoRefreshTimer = setInterval(function() {
            if (submitInFlight) return;

            if (!MagicPreview.isOpen()) {
                stopAutoRefresh();
                return;
            }

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
    // Draft management: restore, discard, and prompt
    // =========================================================================

    /**
     * Restores a saved draft by sending form data to the restore-draft
     * processor, which injects it into the MODX reload registry. On
     * success, the page redirects with ?reload=<token> so MODX natively
     * restores all fields, TVs, and extras data.
     */
    function restoreDraft() {
        var tokenField = Ext.getCmp('modx-create-resource-token');
        var token = tokenField ? tokenField.getValue() : '';

        MODx.Ajax.request({
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/restore-draft',
                id: MagicPreviewResource,
                'create-resource-token': token
            },
            listeners: {
                success: {
                    fn: function(r) {
                        var obj = r.object || r;
                        MODx.loadPage(
                            obj.action,
                            'id=' + (obj.id || 0)
                                + '&reload=' + obj.reload
                                + '&class_key=' + obj.class_key
                                + '&context_key=' + obj.context_key
                        );
                    }
                }
            }
        });
    }

    /**
     * Discards the saved draft for the current resource.
     */
    function discardDraft() {
        MODx.Ajax.request({
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/discard-draft',
                id: MagicPreviewResource
            },
            listeners: {
                success: {
                    fn: function() {
                        MODx.msg.status({
                            title: lexicon('draft_discarded'),
                            delay: 3
                        });
                    }
                }
            }
        });
    }

    /**
     * Shows the draft restore/discard prompt if a draft exists for this
     * resource. Uses Ext.Msg.show with Yes/No buttons remapped to
     * Restore/Discard labels.
     */
    function showDraftPrompt() {
        var c = config();
        if (!c.hasDraft) return;

        // Build the message, replacing the [[+date]] placeholder
        var msg = lexicon('draft_restore_msg').replace('[[+date]]', c.draftSavedAt);

        // Temporarily override ExtJS button labels for this dialog
        var origYes = Ext.Msg.buttonText.yes;
        var origNo = Ext.Msg.buttonText.no;
        Ext.Msg.buttonText.yes = lexicon('draft_restore');
        Ext.Msg.buttonText.no = lexicon('draft_discard');

        Ext.Msg.show({
            title: lexicon('draft_restore_title'),
            msg: msg,
            buttons: Ext.Msg.YESNO,
            icon: Ext.Msg.QUESTION,
            closable: false,
            width: 420,
            fn: function(btnId) {
                // Restore original button labels
                Ext.Msg.buttonText.yes = origYes;
                Ext.Msg.buttonText.no = origNo;

                if (btnId === 'yes') {
                    restoreDraft();
                } else {
                    discardDraft();
                }
            }
        });
    }

    // =========================================================================
    // Auto-preview on page load
    // =========================================================================

    /**
     * Triggers an initial preview by submitting the form after the
     * resource panel has finished rendering. Only runs when panelExtended
     * is enabled and previewMode is MODE_PANEL.
     */
    function initAutoPreview() {
        if (!config().panelExtended) return;
        if (config().previewMode !== MODE_PANEL) return;

        // For overlay mode, open the panel first (onpage is already open
        // via _panel.initOnpage)
        if (config().panelLayout === LAYOUT_OVERLAY) {
            _panel.open();
            _panel.showLoading();
        }

        // Wait for the resource panel to be available, then submit.
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
    // Panel module initialisation
    // =========================================================================

    /**
     * Initialises the panel module with config from the lazy-loaded
     * PHP globals. Must be called inside Ext.onReady so the globals
     * are guaranteed to exist.
     */
    function initPanelModule() {
        var c = config();
        _panel.init({
            panelLayout: c.panelLayout,
            panelExtended: c.panelExtended,
            breakpoints: c.breakpoints,
            lexicon: c.lexicon,
            onReload: function() {
                submitPreview();
            },
            onSaveDraft: function() {
                submitPreview({ saveDraft: true, showLoading: false });
            }
        });
    }

    // =========================================================================
    // ExtJS: Button injection
    // =========================================================================

    Ext.onReady(function() {
        // Initialise the panel sub-module with config
        initPanelModule();

        // Override getButtons on the base UpdateResource prototype. Extras
        // like Collections, Articles, and LocationResources extend this class
        // without overriding getButtons, so patching the base covers them all.
        // If another extra also wraps getButtons via Ext.override(), the
        // wrap-and-delegate pattern ensures both run regardless of load order.
        Ext.override(MODx.page.UpdateResource, {
            _mpOrigGetButtons: MODx.page.UpdateResource.prototype.getButtons,
            getButtons: function(cfg) {
                var btns = this._mpOrigGetButtons.call(this, cfg);
                var btnView = btns.map(function(btn) { return btn.id; }).indexOf('modx-abtn-preview');
                // If the View button doesn't exist, insert at the start
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

        // For "onpage" panel layout in panel mode, open the panel
        // immediately as a permanent column alongside the resource editor.
        if (config().previewMode === MODE_PANEL) {
            _panel.initOnpage();
        }

        // Check for a saved draft and show restore/discard prompt
        showDraftPrompt();

        // Auto-preview: submit the form immediately to generate a preview
        initAutoPreview();
    });
})();
