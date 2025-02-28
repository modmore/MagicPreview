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

            $modx->controller->addJavascript($service->config['assetsUrl'] . 'js/preview.js?v=' . $service::VERSION);
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
                <script>
                    MagicPreviewConfig = ' . json_encode($service->config) . ';
                    MagicPreviewResource = ' . $resource->get('id') . ';
                </script>
            ');
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
        }
        break;

}