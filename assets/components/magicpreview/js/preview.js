/**
 * MagicPreview - Orchestrator
 *
 * Thin orchestration layer that wires together the window module (_window)
 * and panel module (_panel). Owns: lazy config resolution, the public
 * window.MagicPreview API, form submission (manual + silent auto-refresh),
 * auto-refresh timer, auto-preview on page load, and Preview button
 * injection into the MODX manager action bar.
 *
 * Load order: window.js -> panel.js -> share.js -> preview.js (this file, last)
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

    /** @type {string} State manager key for panel open/width state */
    var STATE_KEY = 'mmmp-panel';

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
        if (_config) {
            return _config;
        }

        var baseFrameUrl = MagicPreviewConfig.baseFrameUrl ?? '';
        _config = {
            previewUrl: MODx.config.manager_url
                + '?namespace=magicpreview&a=preview&resource=' + MagicPreviewResource,
            baseFrameUrl: baseFrameUrl,
            frameJoiner: baseFrameUrl.indexOf('?') === -1 ? '?' : '&',
            previewMode: MagicPreviewConfig.previewMode ?? MODE_WINDOW,
            panelLayout: MagicPreviewConfig.panelLayout ?? LAYOUT_OVERLAY,
            resourcePreviewMode: MagicPreviewConfig.resourcePreviewMode ?? '',
            resourcePanelLayout: MagicPreviewConfig.resourcePanelLayout ?? '',
            resourceEnabled: MagicPreviewConfig.resourceEnabled ?? '',
            previewHidden: !!MagicPreviewConfig.previewHidden,
            autoRefreshInterval: parseInt(MagicPreviewConfig.autoRefreshInterval, 10) || 0,
            breakpoints: MagicPreviewConfig.breakpoints ?? {},
            lexicon: MagicPreviewConfig.lexicon ?? {},
            hasDraft: !!MagicPreviewConfig.hasDraft,
            draftSavedAt: MagicPreviewConfig.draftSavedAt ?? '',
            draftShareCount: parseInt(MagicPreviewConfig.draftShareCount, 10) || 0,
            iconSaveDraft: MagicPreviewConfig.iconSaveDraft ?? '',
            iconView: MagicPreviewConfig.iconView ?? ''
        };

        return _config;
    }

    /**
     * Returns a lexicon string by key. Handy for getting values in the preview.
     * Resolves from the small PHP-injected
     * map first (the preview window/panel strings), then from the manager's
     * lexicon via the global _() helper — the magicpreview:default topic is
     * registered with addLexiconTopic() in the plugin — and finally falls
     * back to the key itself.
     * @param {string} key
     * @returns {string}
     */
    function lexicon(key) {
        var lex = config().lexicon;
        if (lex && lex[key]) {
            return lex[key];
        }
        if (typeof _ === 'function') {
            var full = 'magicpreview.' + key;
            var s = _(full);
            if (s && s !== full) {
                return s;
            }
        }
        return key;
    }

    // =========================================================================
    // References to sub-modules (set by window.js, panel.js and share.js
    // before us)
    // =========================================================================

    var _window = window.MagicPreview._window;
    var _panel = window.MagicPreview._panel;
    var _share = window.MagicPreview._share;

    // =========================================================================
    // Panel state persistence (via MODX's Ext.state.Manager)
    // =========================================================================

    /**
     * Reads the saved panel state from the MODX state manager.
     * @returns {{ open: boolean, width: number|null }}
     */
    function getSavedPanelState() {
        var state = Ext.state.Manager.get(STATE_KEY);
        if (state && typeof state === 'object') {
            return {
                open: !!state.open,
                width: state.width ? parseInt(state.width, 10) : null
            };
        }
        return { open: false, width: null };
    }

    /**
     * Persists the panel state (open/closed + width) via the MODX state
     * manager. Writes are debounced by MODX's HttpProvider.
     * @param {boolean} open
     * @param {number|null} [width]
     */
    function savePanelState(open, width) {
        var state = { open: !!open };
        if (width) {
            state.width = width;
        }
        Ext.state.Manager.set(STATE_KEY, state);
    }

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
            // Only persist "open" when actually transitioning from closed,
            // to avoid redundant state writes on every auto-refresh that
            // could race with a close() write via the debounced HttpProvider.
            var wasOpen = _panel.isOpen();
            _panel.open();
            if (!wasOpen) {
                savePanelState(true, _panel.getPanelWidth() || null);
            }
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
            savePanelState(false, _panel.getPanelWidth() || null);
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
            if (hc) {
                hc.setValue(ta.dom.value); 
            }
        }

        // 2. Sync RTE editors (Redactor, TinyMCE, etc.)
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
     */
    function submitPreview(options) {
        options = options || {};
        var showLoading = options.showLoading !== false;

        var panel = Ext.getCmp('modx-panel-resource');
        if (!panel) {
            return;
        }

        var fm = panel.getForm();
        if (!fm) {
            return;
        }

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
        if (interval <= 0) {
            return;
        }
        if (config().previewMode !== MODE_PANEL) {
            return;
        }
        if (!MagicPreview.isOpen()) {
            return;
        }

        autoRefreshTimer = setInterval(function() {
            if (submitInFlight) {
                return;
            }

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
    // Draft management: save, restore, discard, and prompt
    // =========================================================================

    /**
     * Saves a draft by submitting the resource form to the preview
     * processor with save_draft=1. Does NOT open or update any preview
     * window/panel — only saves the draft and shows a status toast.
     */
    function saveDraft() {
        var panel = Ext.getCmp('modx-panel-resource');
        if (!panel) {
            return;
        }

        var fm = panel.getForm();
        if (!fm) {
            return;
        }

        // Fire beforeSubmit so extras (ContentBlocks, etc.) prepare data
        var canSubmit = panel.fireEvent('beforeSubmit', {
            form: fm,
            options: {},
            config: panel.config
        });
        if (canSubmit === false) {
            return;
        }

        var originalAction = fm.baseParams['action'];
        var originalUrl = fm.url;
        fm.baseParams['action'] = 'resource/preview';
        fm.baseParams['save_draft'] = '1';
        fm.url = MagicPreviewConfig.assetsUrl + 'connector.php';

        fm.submit({
            headers: {
                'Powered-By': 'MODx',
                'modAuth': MODx.siteId
            },
            success: function() {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                delete fm.baseParams['save_draft'];

                // Clear the dirty state so the browser doesn't warn
                // about unsaved changes when navigating away.
                panel.clearDirty();
                panel.warnUnsavedChanges = false;

                // Update the banner date, or show a new banner if
                // this is the first draft save on this page load.
                updateDraftBanner();

                MODx.msg.status({
                    title: lexicon('draft_saved'),
                    delay: 3
                });
            },
            failure: function() {
                fm.baseParams['action'] = originalAction;
                fm.url = originalUrl;
                delete fm.baseParams['save_draft'];
            }
        });
    }

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
                        // Suppress the "unsaved changes" browser alert
                        // before navigating to the reload URL.
                        var panel = Ext.getCmp('modx-panel-resource');
                        if (panel) {
                            panel.warnUnsavedChanges = false;
                        }

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
     * Discards the saved draft for the current resource. When the editor has
     * live share links resolving against the draft, the server reports them
     * instead of discarding; a confirmation then explains that the links will
     * be removed too before retrying with remove_shares set.
     * @param {HTMLElement} [banner] - The draft banner, removed once discarded.
     */
    function discardDraft(banner) {
        var finish = function() {
            if (banner) {
                banner.remove();
            }
            MODx.msg.status({
                title: lexicon('draft_discarded'),
                delay: 3
            });
        };

        MODx.Ajax.request({
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/discard-draft',
                id: MagicPreviewResource
            },
            listeners: {
                success: {
                    fn: function(r) {
                        var obj = r.object || {};
                        if (obj.discarded) {
                            finish();
                            return;
                        }

                        // The editor has live share links resolving against
                        // this draft: removing it also removes those links,
                        // so the server held off until they confirm.
                        if (obj.live_shares > 0) {
                            MODx.msg.confirm({
                                title: lexicon('draft_discard'),
                                text: lexicon('draft_discard_live_confirm')
                                    .replace('[[+count]]', obj.live_shares),
                                url: MagicPreviewConfig.assetsUrl + 'connector.php',
                                params: {
                                    action: 'resource/discard-draft',
                                    id: MagicPreviewResource,
                                    remove_shares: 1
                                },
                                listeners: {
                                    success: { fn: finish }
                                }
                            });
                        }
                    }
                }
            }
        });
    }

    /**
     * Builds the banner message HTML for a given datetime string.
     * @param {string} dateStr
     * @returns {string}
     */
    function bannerMsgHtml(dateStr) {
        var bookmarkSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mmmp-draft-banner__icon"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0z" /></svg>';
        var msg = lexicon('draft_banner_msg').replace('[[+date]]', '<b><em>' + dateStr + '</em></b>');
        return bookmarkSvg + msg;
    }

    /**
     * Builds the banner Share button label, appending the user's count of
     * non-expired share links when there are any: "Share (2)".
     * @param {number} count
     * @returns {string}
     */
    function shareButtonText(count) {
        var label = lexicon('draft_share');
        if (count > 0) {
            label += ' (' + count + ')';
        }
        return label;
    }

    /**
     * Updates the share count on the banner's Share button, if present.
     * Called by the share dialog (share.js) whenever its grid reloads, so
     * the count stays accurate after links are created or revoked.
     * @param {number} count
     */
    window.MagicPreview.setDraftShareCount = function(count) {
        var btn = document.querySelector('#mmmp-draft-banner .mmmp-draft-banner__btn--share');
        if (btn) {
            btn.textContent = shareButtonText(count);
        }
    };

    /**
     * Updates the draft banner's datetime to now, or creates the
     * banner if it doesn't exist yet (first save on this page load).
     */
    function updateDraftBanner() {
        // PHP-style tokens, matching the server's date('Y-m-d H:i:s')
        var dateStr = Ext.util.Format.date(new Date(), 'Y-m-d H:i:s');
        var banner = document.getElementById('mmmp-draft-banner');

        if (banner) {
            // Update just the message text, preserving the action buttons
            var msgEl = banner.querySelector('.mmmp-draft-banner__msg');
            if (msgEl) {
                msgEl.innerHTML = bannerMsgHtml(dateStr);
            }
        } else {
            // No banner yet — set config and show it
            _config.hasDraft = true;
            _config.draftSavedAt = dateStr;
            showDraftBanner();
        }
    }

    /**
     * Opens a manager-side preview of the user's saved draft in a new tab
     * via the standard mgr-only ?show_preview= mechanism. The previewdraft
     * processor writes the draft data into the preview cache and returns
     * the hash.
     */
    function previewDraft() {
        var win = window.open('about:blank', 'mmmp-draft-preview');

        MODx.Ajax.request({
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/previewdraft',
                id: MagicPreviewResource
            },
            listeners: {
                success: {
                    fn: function(r) {
                        var hash = (r.object && r.object.preview_hash) ? r.object.preview_hash : null;
                        if (!hash) {
                            if (win) {
                                win.close();
                            }
                            return;
                        }
                        if (win) {
                            win.location = previewFrameUrl(hash);
                        }
                    }
                },
                failure: {
                    fn: function() {
                        if (win) {
                            win.close();
                        }
                    }
                }
            }
        });
    }

    /**
     * Shows a draft banner above the resource panel. Appended to the
     * #modx-panel-resource-div container which sits directly above the
     * ExtJS-rendered resource panel in the DOM. Offers View, Share,
     * Restore and Discard for the saved draft; stays visible until the
     * draft is restored or discarded.
     */
    function showDraftBanner() {
        var c = config();
        if (!c.hasDraft) {
            return;
        }

        var container = document.getElementById('modx-panel-resource-div');
        if (!container) {
            return;
        }

        var banner = document.createElement('div');
        banner.id = 'mmmp-draft-banner';
        banner.className = 'mmmp-draft-banner';
        banner.innerHTML = '<span class="mmmp-draft-banner__msg">' + bannerMsgHtml(c.draftSavedAt) + '</span>'
            + '<span class="mmmp-draft-banner__actions">'
            + '<button type="button" class="mmmp-draft-banner__btn mmmp-draft-banner__btn--view" data-action="view">'
            + lexicon('draft_view') + '</button>'
            + '<button type="button" class="mmmp-draft-banner__btn mmmp-draft-banner__btn--share" data-action="share">'
            + shareButtonText(c.draftShareCount) + '</button>'
            + '<button type="button" class="mmmp-draft-banner__btn mmmp-draft-banner__btn--restore" data-action="restore">'
            + lexicon('draft_restore') + '</button>'
            + '<button type="button" class="mmmp-draft-banner__btn mmmp-draft-banner__btn--discard" data-action="discard">'
            + lexicon('draft_discard') + '</button>'
            + '</span>';

        container.appendChild(banner);

        // Delegate click events from the banner's buttons
        banner.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action]');
            if (!target) {
                return;
            }

            var action = target.getAttribute('data-action');
            if (action === 'view') {
                previewDraft();
            } else if (action === 'share') {
                _share.openDialog();
            } else if (action === 'restore') {
                restoreDraft();
            } else if (action === 'discard') {
                // The banner is removed by discardDraft() once the draft is
                // actually gone — discarding may first ask for confirmation.
                discardDraft(banner);
            }
        });
    }

    // =========================================================================
    // Auto-preview on page load
    // =========================================================================

    /**
     * Triggers an initial preview by submitting the form after the
     * resource panel has finished rendering. Only runs when the user's
     * saved panel state is open and previewMode is MODE_PANEL.
     *
     * Wired to the resource panel's 'afterrender' event (see the
     * Ext.override below) rather than polling for the component.
     */
    function initAutoPreview() {
        var savedState = getSavedPanelState();
        if (!savedState.open) {
            return;
        }
        if (config().previewMode !== MODE_PANEL) {
            return;
        }

        // For overlay mode, open the panel first (onpage is already open
        // via _panel.initOnpage)
        if (config().panelLayout === LAYOUT_OVERLAY) {
            _panel.open();
            _panel.showLoading();
        }

        // Give on page elements a moment to finish loading
        setTimeout(function() {
            submitPreview();
        }, 500);
    }

    // =========================================================================
    // Reload preview on save
    // =========================================================================

    /**
     * Handler for a resource save. MODX's FormPanel fires the
     * 'success' event after a completed save (Save button or Ctrl+S). Our own
     * preview/draft submissions call fm.submit() directly on the BasicForm and
     * never fire this event, so this only runs for real saves.
     *
     * Silently re-submits the (now-saved) form to refresh an open preview, so
     * the editor doesn't have to also press Preview/Ctrl+P. Issue #48.
     */
    function onResourceSaveSuccess() {
        // Only refresh an already-open preview; a save shouldn't pop it open.
        if (!MagicPreview.isOpen()) {
            return;
        }
        // Don't stack on top of an in-flight auto-refresh; that request will
        // pick up the saved content anyway.
        if (submitInFlight) {
            return;
        }

        submitPreview({ showLoading: false });
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
        var savedState = getSavedPanelState();
        _panel.init({
            panelLayout: c.panelLayout,
            panelOpen: savedState.open,
            savedWidth: savedState.width,
            breakpoints: c.breakpoints,
            lexicon: c.lexicon,
            onReload: function() {
                submitPreview();
            },
            onSaveDraft: function() {
                saveDraft();
            },
            onResize: function(width) {
                savePanelState(true, width);
            }
        });
    }

    // =========================================================================
    // ExtJS: Button injection
    // =========================================================================

    Ext.onReady(function() {
        var hidden = !!config().previewHidden;

        // Initialise the panel sub-module with config
        if (!hidden) {
            // Marker class for CSS rules that should only apply when the
            // Preview/Save Draft buttons are actually injected. Keeps the
            // native View button untouched when MagicPreview is hidden.
            Ext.getBody().addClass('magicpreview_active');
            initPanelModule();
        }

        // =================================================================
        // Per-resource settings: inject combos into the Settings tab
        // =================================================================

        /**
         * Returns an array of field configs for the per-resource
         * MagicPreview settings (preview mode + panel layout combos).
         * Each combo includes a "System Default" option (empty string)
         * that inherits the value from the system setting.
         * @returns {Array}
         */
        function getResourceSettingFields() {
            var c = config();
            return [
                {
                    xtype: 'fieldset',
                    title: c.lexicon.magicpreview || 'Magic Preview',
                    collapsible: false,
                    autoHeight: true,
                    defaults: { anchor: '100%' },
                    items: [
                        {
                            xtype: 'magicpreview-combo-resource-enabled',
                            fieldLabel: c.lexicon.resource_enabled || 'Use Magic Preview?',
                            description: '<b>[[*properties.magicpreview.enabled]]</b><br>' + (c.lexicon.resource_enabled_desc || ''),
                            name: 'magicpreview_enabled',
                            hiddenName: 'magicpreview_enabled',
                            value: c.resourceEnabled || 'system_default'
                        },
                        {
                            xtype: 'magicpreview-combo-preview-mode-resource',
                            fieldLabel: c.lexicon.resource_preview_mode || 'Preview Mode',
                            description: '<b>[[*properties.magicpreview.preview_mode]]</b><br>' + (c.lexicon.resource_preview_mode_desc || ''),
                            name: 'magicpreview_preview_mode',
                            hiddenName: 'magicpreview_preview_mode',
                            value: c.resourcePreviewMode || 'system_default'
                        },
                        {
                            xtype: 'magicpreview-combo-panel-layout-resource',
                            fieldLabel: c.lexicon.resource_panel_layout || 'Panel Layout',
                            description: '<b>[[*properties.magicpreview.panel_layout]]</b><br>' + (c.lexicon.resource_panel_layout_desc || ''),
                            name: 'magicpreview_panel_layout',
                            hiddenName: 'magicpreview_panel_layout',
                            value: c.resourcePanelLayout || 'system_default'
                        }
                    ]
                }
            ];
        }

        // Override the resource panel's getSettingLeftFields to append
        // MagicPreview's per-resource combos into the Settings tab.
        // This runs before the panel is constructed because addJavascript()
        // loads this file in <head>, so our Ext.onReady fires before the
        // controller's inline Ext.onReady that calls MODx.load().
        Ext.override(MODx.panel.Resource, {
            _mpOrigGetSettingLeftFields: MODx.panel.Resource.prototype.getSettingLeftFields,
            getSettingLeftFields: function(config) {
                var fields = this._mpOrigGetSettingLeftFields.call(this, config);
                return fields.concat(getResourceSettingFields());
            }
        });

        // Show the draft banner whenever a saved draft exists — even when the
        // Preview button is hidden below: drafts and their live share links
        // outlive a template-filter or per-resource visibility change, and the
        // banner is the only UI to view, share, restore or discard them.
        showDraftBanner();

        // If the Preview button is hidden for this resource (via the system
        // template filter or a per-resource override), skip button injection,
        // tooltips, panel onpage init, auto-preview, and keyboard shortcuts.
        // The Settings-tab combos and the draft banner above remain active so
        // editors can flip the override back on — and keep managing existing
        // drafts/share links — without leaving the page.
        if (hidden) {
            return;
        }

        // Hook the resource panel's lifecycle by wrapping initComponent rather
        // than polling for the component: this override is registered before
        // the controller constructs the panel (see getSettingLeftFields note
        // above), so every instance wires itself up on construction.
        //
        //  - 'success' fires after a completed native save (Save button or
        //    Ctrl+S). Our own preview/draft submissions bypass it (they call
        //    fm.submit() directly), so it only fires for real saves — we use it
        //    to reload an open preview.
        //  - 'afterrender' (once) triggers the initial auto-preview now that the
        //    panel and its form exist.
        Ext.override(MODx.panel.Resource, {
            _mpOrigInitComponent: MODx.panel.Resource.prototype.initComponent,
            initComponent: function() {
                this._mpOrigInitComponent.call(this);
                this.on('success', onResourceSaveSuccess);
                this.on('afterrender', initAutoPreview, this, { single: true });
            }
        });

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
                var hasViewBtn = btnView !== -1;
                // If the View button doesn't exist, insert at the start
                if (!hasViewBtn) {
                    btnView = 0;
                }

                // Save Draft icon button — sits between Preview and View
                btns.splice(btnView, 0, {
                    text: config().iconSaveDraft,
                    id: 'modx-abtn-save-draft',
                    tooltip: lexicon('save_draft'),
                    handler: function() { saveDraft(); },
                    scope: this
                });

                // Preview button
                btns.splice(btnView, 0, {
                    text: lexicon('preview_button'),
                    id: 'modx-abtn-real-preview',
                    handler: function() { submitPreview(); },
                    scope: this
                });

                // Replace the View button text with an icon
                if (hasViewBtn) {
                    btns[btnView + 2].text = config().iconView;
                }
                return btns;
            },

            // Make sure the view button still has the preview url.
            preview: function(previewConfig) {
                window.open(previewConfig.scope.preview_url);
                return false;
            }
        });

        // Create styled tooltips on the action bar buttons. Uses
        // Ext.ToolTip targeted at the button's outer element (the 3×3
        // <table> that ExtJS renders for each button). Because DOM
        // mouseover events bubble, hovering any child cell triggers the
        // tooltip. This renders with MODX's themed .x-tip styling
        // (dark background, light text) instead of unstyled native
        // title attributes. We defer to let the buttons render first.
        setTimeout(function() {
            var tooltips = {
                'modx-abtn-real-preview': lexicon('preview_button_tooltip'),
                'modx-abtn-save-draft': lexicon('save_draft'),
                'modx-abtn-preview': lexicon('view_button_tooltip')
            };
            for (var id in tooltips) {
                var cmp = Ext.getCmp(id);
                if (cmp && cmp.el) {
                    new Ext.ToolTip({
                        target: cmp.el,
                        html: tooltips[id]
                    });
                }
            }
        }, 100);

        // For "onpage" panel layout in panel mode, open the panel
        // immediately as a permanent column alongside the resource editor.
        if (config().previewMode === MODE_PANEL) {
            _panel.initOnpage();
        }

        // Auto-preview is triggered from the panel's 'afterrender' event,
        // wired up in the Ext.override(MODx.panel.Resource) block above.

        // =================================================================
        // Keyboard shortcuts
        // =================================================================

        /**
         * Registers keyboard shortcuts for the resource editor:
         *   Ctrl+Shift+S  — Save draft
         *   Ctrl+P        — Preview (mode-aware: opens/refreshes panel
         *                    or opens new window depending on setting)
         *
         * Uses Cmd instead of Ctrl on macOS. Both shortcuts preventDefault
         * to suppress the browser's native behaviour (Save As / Print).
         *
         * The listener is registered on the capture phase so it fires
         * before ExtJS's Ext.KeyMap (which listens on the bubble phase).
         * We're doing this to prevent the resource saving at the same time.
         */
        document.addEventListener('keydown', function(e) {
            var ctrlOrCmd = e.ctrlKey || e.metaKey;
            if (!ctrlOrCmd) {
                return;
            }

            // Ctrl+Shift+S  —  Save Draft
            if (e.shiftKey && (e.key === 'S' || e.key === 's')) {
                e.preventDefault();
                e.stopPropagation();
                if (!e.repeat) {
                    saveDraft();
                }
                return;
            }

            // Ctrl+P  —  Preview
            if (!e.shiftKey && !e.altKey && (e.key === 'P' || e.key === 'p')) {
                e.preventDefault();
                if (!e.repeat) {
                    submitPreview();
                }
                return;
            }
        }, true);
    });
})();
