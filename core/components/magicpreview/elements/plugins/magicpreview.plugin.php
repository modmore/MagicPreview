<?php
/**
 * @var modX $modx
 */
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
            $jsConfig['previewMode'] = $modx->getOption('magicpreview.preview_mode', null, 'newwindow');
            $jsConfig['panelLayout'] = $modx->getOption('magicpreview.panel_layout', null, 'overlay');
            $jsConfig['panelExtended'] = (bool)$modx->getOption('magicpreview.panel_extended', null, false);
            $jsConfig['autoRefreshInterval'] = (int)$modx->getOption('magicpreview.auto_refresh_interval', null, 5);
            $jsConfig['baseFrameUrl'] = $baseFrameUrl;
            $jsConfig['breakpoints'] = [
                'desktop' => $modx->getOption('magicpreview.breakpoint_desktop', null, '1280px'),
                'tablet' => $modx->getOption('magicpreview.breakpoint_tablet', null, '768px'),
                'mobile' => $modx->getOption('magicpreview.breakpoint_mobile', null, '320px'),
            ];
            $jsConfig['lexicon'] = [
                'preview_button' => $modx->lexicon('magicpreview.preview_button'),
                'preparing_preview' => $modx->lexicon('magicpreview.preparing_preview'),
                'idle_message' => $modx->lexicon('magicpreview.idle_message'),
                'reload_preview' => $modx->lexicon('magicpreview.reload_preview'),
                'close_panel' => $modx->lexicon('magicpreview.close_panel'),
                'bp_full' => $modx->lexicon('magicpreview.bp_full'),
                'bp_desktop' => $modx->lexicon('magicpreview.bp_desktop'),
                'bp_tablet' => $modx->lexicon('magicpreview.bp_tablet'),
                'bp_mobile' => $modx->lexicon('magicpreview.bp_mobile'),
            ];

            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/window.js?v=' . $service::VERSION);
            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/panel.js?v=' . $service::VERSION);
            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/preview.js?v=' . $service::VERSION);

            // When onpage panel + auto-preview is active, the panel will be
            // visible immediately on page load. Inject an early CSS rule so
            // the action buttons bar starts at the correct offset instead of
            // flashing at full width before JS runs syncActionButtonsOffset().
            $earlyPanelCss = '';
            if ($jsConfig['previewMode'] === 'panel' && $jsConfig['panelLayout'] === 'onpage' && $jsConfig['panelExtended']) {
                $earlyPanelCss = '<style>.mmmp-panel-onpage-active #modx-action-buttons { right: 40%; }</style>';
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
        // Load combo xtypes for system settings dropdowns. Only needed
        // when viewing settings, but the JS is small enough that loading
        // it on every manager page is harmless and avoids page detection.
        $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/combo.js?v=' . $service::VERSION);
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