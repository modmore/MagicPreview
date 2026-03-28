<?php
/**
 * @var modX $modx
 */
// Setting value constants
if (!defined('MAGICPREVIEW_MODE_PANEL')) {
    define('MAGICPREVIEW_MODE_PANEL', 'Panel');
    define('MAGICPREVIEW_MODE_WINDOW', 'New Window');
    define('MAGICPREVIEW_LAYOUT_OVERLAY', 'Overlay');
    define('MAGICPREVIEW_LAYOUT_ONPAGE', 'On Page');
}

$path = $modx->getOption('magicpreview.core_path', null, $modx->getOption('core_path') . 'components/magicpreview/');
$service = $modx->getService('magicpreview', 'MagicPreview', $path . '/model/magicpreview/');
if (!($service instanceof MagicPreview)) {
    return 'Could not load MagicPreview service.';
}

switch ($modx->event->name) {
    case 'OnDocFormRender':
        /** @var modResource|\MODX\Revolution\modResource $resource */
        if ($resource->get('id') > 0) {
            // Determine MODX version and add body class to assist with styling
            $versionCls = 'magicpreview_modx2';
            $modxVersion = $modx->getVersionData();
            if (version_compare($modxVersion['full_version'], '3.0.0-dev', '>=')) {
                $versionCls = 'magicpreview_modx3';
            }

            // Build the frontend URL for the resource (used by panel mode iframe)
            $baseFrameUrl = $modx->makeUrl($resource->get('id'), '', '', 'full');

            // Add preview mode and panel settings to the JS config
            $jsConfig = $service->config;
            $jsConfig['previewMode'] = $modx->getOption('magicpreview.preview_mode', null, MAGICPREVIEW_MODE_WINDOW);
            $jsConfig['panelLayout'] = $modx->getOption('magicpreview.panel_layout', null, MAGICPREVIEW_LAYOUT_OVERLAY);
            $jsConfig['autoRefreshInterval'] = (int)$modx->getOption('magicpreview.auto_refresh_interval', null, 5);

            // Per-resource overrides: stored in the resource's properties column
            $resourceProps = $resource->getProperties('magicpreview');
            $resourcePreviewMode = '';
            $resourcePanelLayout = '';
            $validModes = [MAGICPREVIEW_MODE_PANEL, MAGICPREVIEW_MODE_WINDOW];
            $validLayouts = [MAGICPREVIEW_LAYOUT_OVERLAY, MAGICPREVIEW_LAYOUT_ONPAGE];
            if (is_array($resourceProps)) {
                if (!empty($resourceProps['preview_mode']) && in_array($resourceProps['preview_mode'], $validModes, true)) {
                    $resourcePreviewMode = $resourceProps['preview_mode'];
                    $jsConfig['previewMode'] = $resourcePreviewMode;
                }
                if (!empty($resourceProps['panel_layout']) && in_array($resourceProps['panel_layout'], $validLayouts, true)) {
                    $resourcePanelLayout = $resourceProps['panel_layout'];
                    $jsConfig['panelLayout'] = $resourcePanelLayout;
                }
            }
            $jsConfig['resourcePreviewMode'] = $resourcePreviewMode;
            $jsConfig['resourcePanelLayout'] = $resourcePanelLayout;
            $jsConfig['baseFrameUrl'] = $baseFrameUrl;
            $jsConfig['breakpoints'] = [
                'desktop' => $modx->getOption('magicpreview.breakpoint_desktop', null, '1280px'),
                'tablet' => $modx->getOption('magicpreview.breakpoint_tablet', null, '768px'),
                'mobile' => $modx->getOption('magicpreview.breakpoint_mobile', null, '320px'),
            ];
            $jsConfig['lexicon'] = [
                'preview_button' => $modx->lexicon('magicpreview.preview_button'),
                'preview_button_tooltip' => $modx->lexicon('magicpreview.preview_button_tooltip'),
                'view_button_tooltip' => $modx->lexicon('magicpreview.view_button_tooltip'),
                'preparing_preview' => $modx->lexicon('magicpreview.preparing_preview'),
                'idle_message' => $modx->lexicon('magicpreview.idle_message'),
                'reload_preview' => $modx->lexicon('magicpreview.reload_preview'),
                'close_panel' => $modx->lexicon('magicpreview.close_panel'),
                'bp_full' => $modx->lexicon('magicpreview.bp_full'),
                'bp_desktop' => $modx->lexicon('magicpreview.bp_desktop'),
                'bp_tablet' => $modx->lexicon('magicpreview.bp_tablet'),
                'bp_mobile' => $modx->lexicon('magicpreview.bp_mobile'),
                'save_draft' => $modx->lexicon('magicpreview.save_draft'),
                'draft_saved' => $modx->lexicon('magicpreview.draft_saved'),
                'draft_discarded' => $modx->lexicon('magicpreview.draft_discarded'),
                'draft_banner_msg' => $modx->lexicon('magicpreview.draft_banner_msg'),
                'draft_restore' => $modx->lexicon('magicpreview.draft_restore'),
                'draft_discard' => $modx->lexicon('magicpreview.draft_discard'),
                'magicpreview' => $modx->lexicon('magicpreview'),
                'resource_preview_mode' => $modx->lexicon('magicpreview.resource_preview_mode'),
                'resource_preview_mode_desc' => $modx->lexicon('magicpreview.resource_preview_mode_desc'),
                'resource_panel_layout' => $modx->lexicon('magicpreview.resource_panel_layout'),
                'resource_panel_layout_desc' => $modx->lexicon('magicpreview.resource_panel_layout_desc'),
                'system_default' => $modx->lexicon('magicpreview.system_default'),
            ];

            // Check for a saved draft for this resource + user
            $draftKey = MagicPreview::getDraftCacheKey($resource->get('id'), $modx->user->get('id'));
            $draft = $modx->cacheManager->get($draftKey, [
                xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
            ]);
            if (!empty($draft) && is_array($draft) && !empty($draft['data'])) {
                $jsConfig['hasDraft'] = true;
                $jsConfig['draftSavedAt'] = date('Y-m-d H:i:s', isset($draft['saved_at']) ? (int) $draft['saved_at'] : time());
            }
            // Build icon HTML for the Save Draft and View action bar buttons.
            // Empty setting = default SVG; otherwise treat as FA class name.
            $iconSaveDraft = trim($modx->getOption('magicpreview.icon_save_draft', null, ''));
            $iconView = trim($modx->getOption('magicpreview.icon_view', null, ''));
            $jsConfig['iconSaveDraft'] = $iconSaveDraft !== ''
                ? '<i class="icon ' . htmlspecialchars($iconSaveDraft, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mmmp-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0z" /></svg>';
            $jsConfig['iconView'] = $iconView !== ''
                ? '<i class="icon ' . htmlspecialchars($iconView, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mmmp-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>';

            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/window.js?v=' . $service::VERSION);
            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/panel.js?v=' . $service::VERSION);
            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/preview.js?v=' . $service::VERSION);

            // When onpage panel is active, the panel will be visible
            // immediately on page load. Read the user's saved panel state
            // from the MODX file-based registry to get the exact width, so
            // the action buttons bar starts at the correct offset instead of
            // flashing at full width before JS runs syncActionButtonsOffset().
            $earlyPanelCss = '';
            if ($jsConfig['previewMode'] === MAGICPREVIEW_MODE_PANEL && $jsConfig['panelLayout'] === MAGICPREVIEW_LAYOUT_ONPAGE) {
                $stateFile = $modx->getCachePath() . 'registry/state/ys/user-' . $modx->user->get('id') . '/mmmp-panel.msg.php';
                $panelState = @include $stateFile;
                if (is_array($panelState) && !empty($panelState['open'])) {
                    $panelWidth = !empty($panelState['width']) ? (int)$panelState['width'] . 'px' : '40%';
                    $earlyPanelCss = '<style>.mmmp-panel-onpage-active #modx-action-buttons { right: ' . $panelWidth . '; }</style>';
                }
            }

            $modx->controller->addHtml('
                <script>
                    Ext.onReady(() => {
                        Ext.getBody().addClass("' . $versionCls . '");
                    });
                </script>
                <link
                    rel="stylesheet"
                    type="text/css"
                    href="' . $service->config['assetsUrl'] . 'css/mgr.css?v=' . $service::VERSION . '"
                />
                ' . $earlyPanelCss . '
                <script>
                    MagicPreviewConfig = ' . json_encode($jsConfig) . ';
                    MagicPreviewResource = ' . $resource->get('id') . ';
                </script>
            ');
        }
        break;

    case 'OnManagerPageBeforeRender':
        // Load combo xtypes for system settings dropdowns and resource
        // editor settings tab. Needed on settings pages and resource pages.
        $comboActions = [
            'system/settings',
            'context/update',
            'security/usergroup/update',
            'security/user/update',
            'resource/update',
            'resource/create',
        ];
        if (in_array($modx->request->action, $comboActions, true)) {
            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/combo.js?v=' . $service::VERSION);
        }
        break;

    case 'OnDocFormSave':
        /** @var modResource|\MODX\Revolution\modResource $resource */
        $validModes = [MAGICPREVIEW_MODE_PANEL, MAGICPREVIEW_MODE_WINDOW];
        $validLayouts = [MAGICPREVIEW_LAYOUT_OVERLAY, MAGICPREVIEW_LAYOUT_ONPAGE];

        $props = [];
        if (isset($_POST['magicpreview_preview_mode'])) {
            $val = (string)$_POST['magicpreview_preview_mode'];
            $props['preview_mode'] = in_array($val, $validModes, true) ? $val : '';
        }
        if (isset($_POST['magicpreview_panel_layout'])) {
            $val = (string)$_POST['magicpreview_panel_layout'];
            $props['panel_layout'] = in_array($val, $validLayouts, true) ? $val : '';
        }
        if (!empty($props)) {
            $resource->setProperties($props, 'magicpreview');
            $resource->save();
        }
        break;

    case 'OnLoadWebDocument':
        if (!array_key_exists('show_preview', $_GET)) {
            return;
        }
        if (!$modx->user->hasSessionContext('mgr')) {
            $modx->log(modX::LOG_LEVEL_WARN, 'User without mgr session tried to access preview for resource ' . $modx->resource->get('id'));
            return;
        }
        $key = (string)$_GET['show_preview'];
        $data = $modx->cacheManager->get($modx->resource->get('id') . '/' . $key, [
            xPDO::OPT_CACHE_KEY => 'magicpreview'
        ]);

        if (is_array($data)) {
            $modx->resource->fromArray($data, '', true, true);
            $modx->resource->set('cacheable', false);
            $modx->resource->setProcessed(false);
            // The in-memory element cache needs to be wiped, otherwise placeholder values will show the existing cached value.
            $modx->elementCache = null;
        }
        break;

}

return true;