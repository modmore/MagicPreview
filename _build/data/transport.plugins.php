<?php
$plugins = array();

/* create the plugin object */
$plugins[0] = $modx->newObject('modPlugin');
$plugins[0]->set('id',1);
$plugins[0]->set('name','MagicPreview');
$plugins[0]->set('description','Registers the magic preview button to your resources.');
$plugins[0]->set('plugincode', getSnippetContent($sources['plugins'] . 'magicpreview.plugin.php'));
$plugins[0]->set('category', 0);

$events = include $sources['events'].'events.magicpreview.php';
if (is_array($events) && !empty($events)) {
    $plugins[0]->addMany($events);
    $modx->log(xPDO::LOG_LEVEL_INFO,'Packaged in '.count($events).' Plugin Events for MagicPreview plugin.'); flush();
} else {
    $modx->log(xPDO::LOG_LEVEL_ERROR,'Could not find plugin events for MagicPreview!');
}
unset($events);

return $plugins;
