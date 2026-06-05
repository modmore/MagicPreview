<?php
/**
 * Creates the MagicPreview database tables on install/upgrade.
 *
 * The model is the classic (non-namespaced) xPDO tree under
 * core/components/magicpreview/model/, so the same code runs on MODX 2.x and 3.x.
 *
 * @var modX|\MODX\Revolution\modX $modx
 * @var xPDOTransport|\xPDO\Transport\xPDOTransport $object
 * @var array $options
 */
if ($object->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx = $object->xpdo;

            $modelPath = $modx->getOption(
                'magicpreview.core_path',
                null,
                $modx->getOption('core_path') . 'components/magicpreview/'
            ) . 'model/';

            $modx->addPackage('magicpreview', $modelPath);
            $manager = $modx->getManager();
            $loglevel = $modx->setLogLevel(modX::LOG_LEVEL_ERROR);

            $objects = [
                'mpDraft',
                'mpShare',
            ];
            foreach ($objects as $obj) {
                $manager->createObjectContainer($obj);
            }

            $modx->setLogLevel($loglevel);

            break;
    }
}
return true;
