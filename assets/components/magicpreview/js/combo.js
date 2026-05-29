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
 * Base combo: shared config for every MagicPreview settings dropdown.
 * Subclasses supply their own store, displayField and valueField.
 */
MagicPreview.combo.Base = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        mode: 'local',
        triggerAction: 'all',
        editable: false,
        selectOnFocus: false,
        preventRender: true,
        forceSelection: true,
        enableKeyEvents: true
    });
    MagicPreview.combo.Base.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.Base, MODx.combo.ComboBox);

/**
 * Base for per-resource combos: prepends a localised "System Default" row
 * (value 'system_default', cleared to '' on save) ahead of the subclass's
 * own rows. Subclasses pass their [value, label] pairs via config.rows.
 * Rows are read at construction time so labels that depend on
 * MagicPreviewConfig (injected lazily) resolve correctly.
 */
MagicPreview.combo.ResourceBase = function(config) {
    config = config || {};
    var rows = config.rows || [];
    delete config.rows;
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v', 'd'],
            data: [['system_default', getSystemDefaultLabel()]].concat(rows)
        }),
        displayField: 'd',
        valueField: 'v'
    });
    MagicPreview.combo.ResourceBase.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.ResourceBase, MagicPreview.combo.Base);

// -- System settings combos --------------------------------------------------

/**
 * Preview Mode combo: New Window or Panel.
 */
MagicPreview.combo.PreviewMode = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v'],
            data: [['New Window'], ['Panel']]
        }),
        displayField: 'v',
        valueField: 'v'
    });
    MagicPreview.combo.PreviewMode.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PreviewMode, MagicPreview.combo.Base);
Ext.reg('magicpreview-combo-preview-mode', MagicPreview.combo.PreviewMode);

/**
 * Panel Layout combo: Overlay or On Page.
 */
MagicPreview.combo.PanelLayout = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v'],
            data: [['Overlay'], ['On Page']]
        }),
        displayField: 'v',
        valueField: 'v'
    });
    MagicPreview.combo.PanelLayout.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PanelLayout, MagicPreview.combo.Base);
Ext.reg('magicpreview-combo-panel-layout', MagicPreview.combo.PanelLayout);

/**
 * Template Filter Mode combo for system settings.
 */
MagicPreview.combo.TemplateFilterMode = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        store: new Ext.data.SimpleStore({
            fields: ['v'],
            data: [['None'], ['Block Listed'], ['Allow Listed Only']]
        }),
        displayField: 'v',
        valueField: 'v'
    });
    MagicPreview.combo.TemplateFilterMode.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.TemplateFilterMode, MagicPreview.combo.Base);
Ext.reg('magicpreview-combo-template-filter-mode', MagicPreview.combo.TemplateFilterMode);

// -- Per-resource combos (include a "System Default" inherit option) ---------

/**
 * Preview Mode combo for resource settings.
 */
MagicPreview.combo.PreviewModeResource = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        rows: [['New Window', 'New Window'], ['Panel', 'Panel']]
    });
    MagicPreview.combo.PreviewModeResource.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PreviewModeResource, MagicPreview.combo.ResourceBase);
Ext.reg('magicpreview-combo-preview-mode-resource', MagicPreview.combo.PreviewModeResource);

/**
 * Panel Layout combo for resource settings.
 */
MagicPreview.combo.PanelLayoutResource = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        rows: [['Overlay', 'Overlay'], ['On Page', 'On Page']]
    });
    MagicPreview.combo.PanelLayoutResource.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.PanelLayoutResource, MagicPreview.combo.ResourceBase);
Ext.reg('magicpreview-combo-panel-layout-resource', MagicPreview.combo.PanelLayoutResource);

/**
 * Per-resource enabled override combo: System Default / Yes / No.
 * Inherits from the template filter system setting when "System Default".
 * Yes/No labels use MODX's core lexicon via the manager's _() helper.
 */
MagicPreview.combo.ResourceEnabled = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        rows: [
            ['Yes', _('yes')],
            ['No', _('no')]
        ]
    });
    MagicPreview.combo.ResourceEnabled.superclass.constructor.call(this, config);
};
Ext.extend(MagicPreview.combo.ResourceEnabled, MagicPreview.combo.ResourceBase);
Ext.reg('magicpreview-combo-resource-enabled', MagicPreview.combo.ResourceEnabled);
