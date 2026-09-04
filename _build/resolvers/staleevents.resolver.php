<?php
/**
 * Removes plugin event registrations that older MagicPreview versions created
 * but current versions no longer use.
 *
 * The plugin vehicle registers PluginEvents with UPDATE_OBJECT => false, so
 * dropping an event from the package does not delete an existing row. Without
 * this, an upgraded install keeps firing the MagicPreview plugin on every
 * front-end page render for OnWebPagePrerender, which no longer has a handler.
 *
 * @var modX $modx
 * @var modTransportPackage $transport
 * @var array $options
 */
if ($transport->xpdo) {
    $modx = $transport->xpdo;

    $staleEvents = [
        'OnWebPagePrerender',
        'OnWebPageComplete',
    ];

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_UPGRADE:
            $plugin = $modx->getObject('modPlugin', ['name' => 'MagicPreview']);
            if ($plugin) {
                foreach ($staleEvents as $eventName) {
                    $pluginEvent = $modx->getObject('modPluginEvent', [
                        'pluginid' => $plugin->get('id'),
                        'event' => $eventName,
                    ]);
                    if ($pluginEvent) {
                        $pluginEvent->remove();
                    }
                }
            }

            break;
    }
}
return true;
