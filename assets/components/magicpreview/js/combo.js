/**
 * MagicPreview - Combo xtypes for system settings and resource settings
 *
 * Registers custom ExtJS combo components so the preview_mode and
 * panel_layout system settings render as dropdowns instead of plain
 * text fields in the MODX manager. Also provides resource-level
 * variants that include a "System Default" option.
 *
 * Loaded on settings pages and resource editor pages via
 * OnManagerPageBeforeRender.
 */
MagicPreview = window.MagicPreview || {};
MagicPreview.combo = MagicPreview.combo || {};

/**
 * Returns the localised label for "System Default", with a fallback.
 * @returns {string}
 */
function getSystemDefaultLabel() {
    if (typeof MagicPreviewConfig !== 'undefined'
        && MagicPreviewConfig.lexicon
        && MagicPreviewConfig.lexicon.system_default) {
        return MagicPreviewConfig.lexicon.system_default;
    }
    return 'System Default';
}

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

/**
 * Preview Mode combo for resource settings: includes a "System Default"
 * option (empty string value) that inherits from the system setting.
 */
MagicPreview.combo.PreviewModeResource = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v', 'd'],
            data: [
                ['system_default', getSystemDefaultLabel()],
                ['New Window', 'New Window'],
                ['Panel', 'Panel']
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
    MagicPreview.combo.PreviewModeResource.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PreviewModeResource, MODx.combo.ComboBox);
Ext.reg('magicpreview-combo-preview-mode-resource', MagicPreview.combo.PreviewModeResource);

/**
 * Panel Layout combo for resource settings: includes a "System Default"
 * option (empty string value) that inherits from the system setting.
 */
MagicPreview.combo.PanelLayoutResource = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v', 'd'],
            data: [
                ['system_default', getSystemDefaultLabel()],
                ['Overlay', 'Overlay'],
                ['On Page', 'On Page']
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
    MagicPreview.combo.PanelLayoutResource.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PanelLayoutResource, MODx.combo.ComboBox);
Ext.reg('magicpreview-combo-panel-layout-resource', MagicPreview.combo.PanelLayoutResource);
