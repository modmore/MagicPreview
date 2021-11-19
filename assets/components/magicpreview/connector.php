<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$corePath = $modx->getOption('magicpreview.core_path',null,$modx->getOption('core_path').'components/magicpreview/');
$modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');
//$modx->lexicon->load('magicpreview:default');

// Check for MODX version
if (version_compare($modx->getOption('settings_version'), '3.0.0-alpha1') < 1) {
    // Switch to the old version of the processor if less that 3.0.0-alpha1 (i.e. for any 2.x release)
    $_REQUEST['action'] = 'resource/preview-v2';
}

/* handle request */
$path = $modx->getOption('processorsPath', $modx->magicpreview->config,$corePath  .'processors/');
$modx->request->handleRequest(array(
    'processors_path' => $path,
    'location' => '',
));
