(function() {
    Ext.onReady(function() {
        Ext.override(MODx.page.UpdateResource, {
            _originals: {
                getButtons: MODx.page.UpdateResource.prototype.getButtons
            },
            getButtons: function(config) {
                var btns = this._originals.getButtons.call(this, config);
                btns.splice(2, 0, {
                    text: 'Preview',
                    id: 'modx-abtn-real-preview',
                    handler: this.mpPreview,
                    scope: this
                });
                return btns;
            },

            mpPreview: function() {
                var o = this.config;
                if (!o.formpanel) return false;

                MODx.util.Progress.reset();
                o.form = Ext.getCmp(o.formpanel);
                if (!o.form) return false;

                if (!o.previewWindow) {
                    o.previewWindow = window.open(MagicPreviewConfig.assetsUrl + '/preview.html#loading', 'MagicPreview')
                }

                var f = o.form.getForm ? o.form.getForm() : o.form;
                var isv = true;
                if (f.items && f.items.items) {
                    for (var fld in f.items.items) {
                        if (f.items.items[fld] && f.items.items[fld].validate) {
                            var fisv = f.items.items[fld].validate();
                            if (!fisv) {
                                f.items.items[fld].markInvalid();
                                isv = false;
                            }
                        }
                    }
                }

                if (isv) {
                    o.previewWindow.location.hash = 'loading';
                    o.previewWindow = window.open(o.previewWindow.location.href, o.previewWindow.name);

                    var originalAction = o.form.baseParams['action'],
                        originalUrl = o.form.url;
                    f.baseParams['action'] = 'resource/preview';
                    f.url = MagicPreviewConfig.assetsUrl + '/connector.php';

                    o.form.on('success', function (r) {
                        f.baseParams['action'] = originalAction;
                        f.url = originalUrl;

                        if (r.result && r.result.object && r.result.object.preview_url) {
                            o.previewWindow.location.hash = r.result.object.preview_url;
                        }

                    }, this);
                    o.form.submit({
                        headers: {
                            'Powered-By': 'MODx'
                            , 'modAuth': MODx.siteId
                        }
                    });
                }
            }

        });
    });
})();

