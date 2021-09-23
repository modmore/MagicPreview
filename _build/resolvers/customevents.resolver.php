<?php
/**
 * @var modX $modx
 * @var modTransportPackage $transport
 * @var array $options
 */
if ($transport->xpdo) {
    $modx =& $transport->xpdo;

    $events = [
        'OnResourceMagicPreview',
    ];

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            foreach ($events as $eventName) {
                $event = $modx->getObject('modEvent', ['name' => $eventName]);
                if (!$event) {
                    $event = $modx->newObject('modEvent');
                    $event->set('name', $eventName);
                    $event->set('groupname', 'Magic Preview');
                    $event->save();
                }
            }

            break;
        case xPDOTransport::ACTION_UNINSTALL:
            foreach ($events as $eventName) {
                $event = $modx->getObject('modEvent', ['name' => $eventName]);
                if ($event) {
                    $event->remove();
                }
            }

            break;
    }
}
return true;