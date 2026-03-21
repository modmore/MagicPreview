<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$corePath = $modx->getOption('magicpreview.core_path',null,$modx->getOption('core_path').'components/magicpreview/');
$modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');
//$modx->lexicon->load('magicpreview:default');

// Check for MODX version: swap to v2 processor variants for MODX 2.x
if (version_compare($modx->getOption('settings_version'), '3.0.0-alpha1') < 1) {
    $v2Map = [
        'resource/preview'       => 'resource/preview-v2',
        'resource/restore-draft' => 'resource/restore-draft-v2',
        'resource/discard-draft' => 'resource/discard-draft-v2',
    ];
    $action = $_REQUEST['action'] ?? '';
    if (isset($v2Map[$action])) {
        $_REQUEST['action'] = $v2Map[$action];
    }
}

/* handle request */
$path = $modx->getOption('processorsPath', $modx->magicpreview->config,$corePath  .'processors/');
$modx->request->handleRequest(array(
    'processors_path' => $path,
    'location' => '',
));
