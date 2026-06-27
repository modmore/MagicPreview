<?php

$events = [];

$e = [
    'OnDocFormRender',
    'OnDocFormSave',
    'OnLoadWebDocument',
    'OnManagerPageBeforeRender',
    'OnWebPagePrerender',
    'ContentBlocks_AfterParse',
];

foreach ($e as $ev) {
    $events[$ev] = $modx->newObject('modPluginEvent');
    $events[$ev]->fromArray([
        'event' => $ev,
        'priority' => 0,
        'propertyset' => 0
    ],'',true,true);
}

return $events;


