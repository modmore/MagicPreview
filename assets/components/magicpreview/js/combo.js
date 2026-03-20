/**
 * MagicPreview - Combo xtypes for system settings
 *
 * Registers custom ExtJS combo components so the preview_mode and
 * panel_layout system settings render as dropdowns instead of plain
 * text fields in the MODX manager.
 *
 * Loaded on settings pages via OnManagerPageBeforeRender.
 */
MagicPreview = window.MagicPreview || {};
MagicPreview.combo = MagicPreview.combo || {};

/**
 * Preview Mode combo: New Window or Panel.
 */
MagicPreview.combo.PreviewMode = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v'],
            data: [
                ['New Window'],
                ['Panel']
            ]
        }),
        displayField: 'v',
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
 * Panel Layout combo: Overlay or On Page.
 */
MagicPreview.combo.PanelLayout = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v'],
            data: [
                ['Overlay'],
                ['On Page']
            ]
        }),
        displayField: 'v',
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
