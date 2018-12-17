<?php
/** @var modX $modx */
$modx =& $transport->xpdo;
$success = false;
switch($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $success = true;
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Checking if server meets the minimum requirements...');

        $level = xPDO::LOG_LEVEL_INFO;
        $modxVersion = $modx->getVersionData();
        if (version_compare($modxVersion['full_version'], '2.6.5') < 0) {
            $level = xPDO::LOG_LEVEL_ERROR;
            $success = false;
        }
        $modx->log($level, '- MODX Revolution 2.6.5+: ' . $modxVersion['full_version']);

        $level = xPDO::LOG_LEVEL_INFO;
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            $level = xPDO::LOG_LEVEL_ERROR;
            $success = false;
        }
        $modx->log($level, '- PHP version 7.0+: ' . PHP_VERSION);

        if ($success) {
            $modx->log(xPDO::LOG_LEVEL_INFO, 'Requirements look good!');
        }
        else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Unfortunately, your server does not meet the minimum requirements and installation cannot continue.');
        }

        break;
    case xPDOTransport::ACTION_UNINSTALL:
        $success = true;
        break;
}
return $success;