/**
 * MagicPreview - Combo xtypes for system settings
 *
 * Registers custom ExtJS combo components so the preview_mode and
 * panel_layout system settings render as dropdowns instead of plain
 * text fields in the MODX manager.
 *
 * Loaded on every manager page via OnManagerPageBeforeRender.
 */
MagicPreview = window.MagicPreview || {};
MagicPreview.combo = MagicPreview.combo || {};

/**
 * Preview Mode combo: newwindow or panel.
 */
MagicPreview.combo.PreviewMode = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['d', 'v'],
            data: [
                ['New Window', 'newwindow'],
                ['Panel', 'panel']
            ]
        }),
        displayField: 'd',
        valueField: 'v',
        mode: 'local',
        triggerAction: 'all',
        editable: false,
        selectOnFocus: false,
        preventRender: true,
        forceSelection: true,
        enableKeyEvents: true
    });
    MagicPreview.combo.PreviewMode.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PreviewMode, MODx.combo.ComboBox);
Ext.reg('magicpreview-combo-preview-mode', MagicPreview.combo.PreviewMode);

/**
 * Panel Layout combo: overlay or onpage.
 */
MagicPreview.combo.PanelLayout = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['d', 'v'],
            data: [
                ['Overlay', 'overlay'],
                ['On Page', 'onpage']
            ]
        }),
        displayField: 'd',
        valueField: 'v',
        mode: 'local',
        triggerAction: 'all',
        editable: false,
        selectOnFocus: false,
        preventRender: true,
        forceSelection: true,
        enableKeyEvents: true
    });
    MagicPreview.combo.PanelLayout.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PanelLayout, MODx.combo.ComboBox);
Ext.reg('magicpreview-combo-panel-layout', MagicPreview.combo.PanelLayout);
