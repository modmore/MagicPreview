/**
 * MagicPreview - Share links module
 *
 * Owns the share dialog (a native MODx.Window holding the create form and a
 * MODx.grid.Grid of active links), the one-time result window that displays
 * a freshly created URL, and the connector calls for creating, listing and
 * revoking share links.
 *
 * Exposed as window.MagicPreview._share with a single openDialog() entry
 * point, called by the orchestrator (preview.js) from the action bar button.
 *
 * Load order: window.js -> panel.js -> share.js -> preview.js
 *
 * @global {object}  MagicPreviewConfig   - Injected by PHP plugin
 * @global {number}  MagicPreviewResource - Injected by PHP plugin
 */
(function() {
    window.MagicPreview = window.MagicPreview || {};
    MagicPreview.grid = MagicPreview.grid || {};
    MagicPreview.window = MagicPreview.window || {};

    /** @type {MagicPreview.window.Share|null} The share window (singleton) */
    var _shareWindow = null;

    /** @type {MagicPreview.window.ShareResult|null} The result window (singleton) */
    var _shareResultWindow = null;

    /**
     * Copies text to the clipboard via the async Clipboard API, falling back
     * to the deprecated execCommand('copy') only when that API is missing.
     * The Clipboard API is restricted to secure contexts (https/localhost) by
     * spec and no modern replacement exists for plain-http managers, so the
     * deprecated call is kept deliberately as the only one-click option there.
     * @param {string} text
     * @param {function} onCopied
     */
    function copyToClipboard(text, onCopied) {
        var fallback = function() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { document.execCommand('copy'); } catch (e) { /* noop */ }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onCopied, function() { fallback(); onCopied(); });
        } else {
            fallback();
            onCopied();
        }
    }

    /**
     * Submits the resource form to the preview processor with
     * create_share=1, creating a share link from the current form state.
     * The processor returns the link's URL (with its one-time token) in
     * result.object.share — or null when creation failed.
     *
     * @param {object} opts
     * @param {string} opts.ttl - Lifetime in seconds; '' = system default
     * @param {string} opts.label - Optional label identifying the link
     * @param {function} onDone - Called with the share object or null
     */
    function createShareLink(opts, onDone) {
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
        fm.baseParams['create_share'] = '1';
        fm.baseParams['share_ttl'] = opts.ttl;
        fm.baseParams['share_label'] = opts.label || '';
        fm.url = MagicPreviewConfig.assetsUrl + 'connector.php';

        var cleanup = function() {
            fm.baseParams['action'] = originalAction;
            fm.url = originalUrl;
            delete fm.baseParams['create_share'];
            delete fm.baseParams['share_ttl'];
            delete fm.baseParams['share_label'];
        };

        fm.submit({
            headers: {
                'modAuth': MODx.siteId
            },
            success: function(form, action) {
                cleanup();
                var result = action.result;
                onDone((result && result.object && result.object.share) ? result.object.share : null);
            },
            failure: function() {
                cleanup();
                onDone(null);
            }
        });
    }

    /**
     * Confirms then revokes a share link, refreshing the window's grid.
     * @param {number} shareId
     */
    function revokeShare(shareId) {
        if (!shareId) {
            return;
        }
        MODx.msg.confirm({
            title: _('magicpreview.share_revoke'),
            text: _('magicpreview.share_revoke_confirm'),
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/removeshare',
                id: MagicPreviewResource,
                share_id: shareId
            },
            listeners: {
                success: {
                    fn: function() {
                        MODx.msg.status({ title: _('magicpreview.share_revoked'), delay: 3 });
                        if (_shareWindow && _shareWindow.shareGrid) {
                            _shareWindow.shareGrid.refresh();
                        }
                    }
                }
            }
        });
    }

    /**
     * Opens a manager-side preview of a share's content in a new tab via the
     * standard mgr-only ?show_preview= mechanism — the public share URL can't
     * be reconstructed (only the token's hash is stored), and the editor is
     * logged in anyway. The previewshare processor writes the share's data
     * into the preview cache and returns the hash.
     *
     * The tab is opened synchronously so popup blockers count it as part of
     * the user's click; its location is set once the hash arrives.
     * @param {number} shareId
     */
    function previewShare(shareId) {
        if (!shareId) return;

        var win = window.open('about:blank', 'mmmp-share-preview');

        MODx.Ajax.request({
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            params: {
                action: 'resource/previewshare',
                id: MagicPreviewResource,
                share_id: shareId
            },
            listeners: {
                success: {
                    fn: function(r) {
                        var hash = (r.object && r.object.preview_hash) ? r.object.preview_hash : null;
                        if (!hash) {
                            if (win) win.close();
                            return;
                        }
                        var base = MagicPreviewConfig.baseFrameUrl || '';
                        var joiner = base.indexOf('?') === -1 ? '?' : '&';
                        if (win) {
                            win.location = base + joiner + 'show_preview=' + hash;
                        }
                    }
                },
                failure: {
                    fn: function() {
                        if (win) win.close();
                    }
                }
            }
        });
    }

    // -- Grid: active share links for the current resource -------------------

    MagicPreview.grid.Shares = function(config) {
        config = config || {};
        Ext.applyIf(config, {
            id: 'mmmp-share-grid',
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            baseParams: {
                action: 'resource/getshares',
                id: MagicPreviewResource
            },
            fields: ['id', 'label', 'user_id', 'createdon', 'expires_at', 'last_viewed_at', 'views'],
            paging: false,
            remoteSort: false,
            showActionsColumn: false,
            autoHeight: false,
            height: 200,
            anchor: '100%',
            hideLabel: true,
            columns: [
                {
                    header: _('magicpreview.share_col_label'),
                    dataIndex: 'label',
                    width: 150,
                    sortable: false,
                    renderer: function(v) { return v ? Ext.util.Format.htmlEncode(v) : ''; }
                },
                {
                    header: _('magicpreview.share_col_created'),
                    dataIndex: 'createdon',
                    width: 130,
                    sortable: false,
                    renderer: function(v) { return v ? Ext.util.Format.date(new Date(v * 1000), 'Y-m-d H:i:s') : ''; }
                },
                {
                    header: _('magicpreview.share_col_expires'),
                    dataIndex: 'expires_at',
                    width: 130,
                    sortable: false,
                    renderer: function(v) { return v > 0 ? Ext.util.Format.date(new Date(v * 1000), 'Y-m-d H:i:s') : _('magicpreview.share_expiry_never'); }
                },
                {
                    header: _('magicpreview.share_col_views'),
                    dataIndex: 'views',
                    width: 60,
                    sortable: false
                },
                {
                    header: '',
                    dataIndex: 'id',
                    width: 120,
                    sortable: false,
                    renderer: function() {
                        return '<a class="mmmp-share-view" href="javascript:;">' + _('magicpreview.share_view') + '</a>'
                            + ' <a class="mmmp-share-revoke" href="javascript:;">' + _('magicpreview.share_revoke') + '</a>';
                    }
                }
            ],
            viewConfig: {
                forceFit: true,
                emptyText: _('magicpreview.share_none')
            },
            listeners: {
                cellclick: { fn: this.onCellClick, scope: this }
            }
        });
        MagicPreview.grid.Shares.superclass.constructor.call(this, config);
    };
    Ext.extend(MagicPreview.grid.Shares, MODx.grid.Grid, {
        /**
         * Triggers view/revoke only when the matching link in the action
         * column was clicked (clicking elsewhere in the row does nothing).
         */
        onCellClick: function(grid, rowIndex, colIndex, e) {
            var rec = this.getStore().getAt(rowIndex);
            if (!rec) return;

            if (e.getTarget('.mmmp-share-view')) {
                previewShare(rec.get('id'));
            } else if (e.getTarget('.mmmp-share-revoke')) {
                revokeShare(rec.get('id'));
            }
        }
    });

    // -- Window: create + manage share links ---------------------------------

    MagicPreview.window.Share = function(config) {
        config = config || {};

        var grid = new MagicPreview.grid.Shares({});
        this.shareGrid = grid;

        Ext.applyIf(config, {
            title: _('magicpreview.share_title'),
            width: 760,
            autoHeight: true,
            resizable: false,
            closeAction: 'hide',
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            fields: [
                {
                    // Label + expiry share one row: two nested form columns so
                    // each control keeps its own aligned field label.
                    xtype: 'container',
                    layout: 'column',
                    anchor: '100%',
                    items: [
                        {
                            columnWidth: 0.6,
                            layout: 'form',
                            labelWidth: 60,
                            items: [{
                                xtype: 'textfield',
                                id: 'mmmp-share-label',
                                name: 'share_label',
                                fieldLabel: _('magicpreview.share_label'),
                                emptyText: _('magicpreview.share_label_emptytext'),
                                maxLength: 190,
                                anchor: '100%'
                            }]
                        },
                        {
                            columnWidth: 0.4,
                            layout: 'form',
                            labelWidth: 60,
                            items: [{
                                xtype: 'combo',
                                id: 'mmmp-share-ttl',
                                name: 'share_ttl',
                                fieldLabel: _('magicpreview.share_col_expires'),
                                mode: 'local',
                                triggerAction: 'all',
                                editable: false,
                                forceSelection: true,
                                value: '',
                                valueField: 'v',
                                displayField: 'd',
                                anchor: '100%',
                                store: new Ext.data.SimpleStore({
                                    fields: ['v', 'd'],
                                    data: [
                                        ['', _('magicpreview.share_expiry_default')],
                                        ['86400', _('magicpreview.share_expiry_1day')],
                                        ['604800', _('magicpreview.share_expiry_1week')],
                                        ['2592000', _('magicpreview.share_expiry_30days')],
                                        ['0', _('magicpreview.share_expiry_never')]
                                    ]
                                })
                            }]
                        }
                    ]
                },
                {
                    xtype: 'displayfield',
                    hideLabel: true,
                    style: 'margin-top:10px;font-weight:600;',
                    value: _('magicpreview.share_existing')
                },
                grid
            ],
            buttons: [
                {
                    text: _('magicpreview.share_create'),
                    cls: 'primary-button',
                    handler: this.onCreate,
                    scope: this
                },
                {
                    text: _('close'),
                    handler: function() { this.hide(); },
                    scope: this
                }
            ]
        });
        MagicPreview.window.Share.superclass.constructor.call(this, config);
    };
    Ext.extend(MagicPreview.window.Share, MODx.Window, {
        // Override so there's no focus
        focusFirstField: function() {
            return;
        },
        /**
         * Create-link handler: submits the resource form (via createShareLink)
         * and, on success, shows the one-time URL and refreshes the grid.
         * @param {Ext.Button} btn
         */
        onCreate: function(btn) {
            var self = this;
            var ttl = Ext.getCmp('mmmp-share-ttl');
            var label = Ext.getCmp('mmmp-share-label');
            if (btn) {
                btn.disable();
            }

            createShareLink({
                ttl: ttl ? ttl.getValue() : '',
                label: label ? label.getValue() : ''
            }, function(share) {
                if (btn) btn.enable();
                if (share && share.url) {
                    // The label belongs to the link just created — clear it
                    // so the next link doesn't inherit it accidentally.
                    if (label) label.setValue('');
                    self.shareGrid.refresh();
                    // The URL is shown once, in its own small modal on top, so
                    // the share window's height stays constant on small viewports.
                    showShareResult(share.url);
                } else {
                    MODx.msg.alert(_('magicpreview'), _('magicpreview.share_failed'));
                }
            });
        }
    });

    // -- Window: one-time display of a freshly created link ------------------

    MagicPreview.window.ShareResult = function(config) {
        config = config || {};
        Ext.applyIf(config, {
            title: _('magicpreview.share_created'),
            width: 520,
            autoHeight: true,
            modal: true,
            resizable: false,
            closeAction: 'hide',
            url: MagicPreviewConfig.assetsUrl + 'connector.php',
            fields: [
                {
                    xtype: 'textfield',
                    id: 'mmmp-share-url',
                    anchor: '100%',
                    readOnly: true,
                    hideLabel: true
                },
                {
                    xtype: 'label',
                    cls: 'desc-under',
                    text: _('magicpreview.share_link_note')
                }
            ],
            buttons: [
                {
                    text: _('magicpreview.share_copy'),
                    cls: 'primary-button',
                    handler: this.onCopy,
                    scope: this
                },
                {
                    text: _('close'),
                    handler: function() { this.hide(); },
                    scope: this
                }
            ]
        });
        MagicPreview.window.ShareResult.superclass.constructor.call(this, config);
    };
    Ext.extend(MagicPreview.window.ShareResult, MODx.Window, {
        // Override so there's no focus
        focusFirstField: function() {
            return;
        },
        /** Copy-link handler: copies the generated URL to the clipboard. */
        onCopy: function() {
            var f = Ext.getCmp('mmmp-share-url');
            if (!f || !f.getValue()) return;
            copyToClipboard(f.getValue(), function() {
                MODx.msg.status({ title: _('magicpreview.share_copied'), delay: 3 });
            });
        }
    });

    /**
     * Shows a freshly created link in a small modal stacked over the share
     * window — the only time the URL can ever be displayed.
     * @param {string} url
     */
    function showShareResult(url) {
        if (!_shareResultWindow) {
            _shareResultWindow = new MagicPreview.window.ShareResult({});
        }
        var f = Ext.getCmp('mmmp-share-url');
        if (f) {
            f.setValue(url);
        }
        _shareResultWindow.show();
    }

    /**
     * Opens the share window (singleton), resetting the label field and
     * refreshing the active-links grid.
     */
    function openShareDialog() {
        var first = !_shareWindow;
        if (first) {
            _shareWindow = new MagicPreview.window.Share({});
        }

        // Reset the label from a previous open (the one-time URL lives in
        // its own ShareResult window and is set fresh on each creation)
        var label = Ext.getCmp('mmmp-share-label');
        if (label) label.setValue('');

        _shareWindow.show();
        // The grid auto-loads on first render; refresh on subsequent opens.
        if (!first && _shareWindow.shareGrid) {
            _shareWindow.shareGrid.refresh();
        }
    }

    // Public module API, consumed by preview.js
    window.MagicPreview._share = {
        openDialog: openShareDialog
    };
})();
