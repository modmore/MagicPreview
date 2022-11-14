<?php

$s = [
    "breakpointDesktop" => "1280px",
    "breakpointTablet" => "768px",
    "breakpointMobile" => "320px",
];

$settings = [];

foreach ($s as $key => $value) {
    if (is_string($value) || is_int($value)) { $type = 'textfield'; }
    elseif (is_bool($value)) { $type = 'combo-boolean'; }
    else { $type = 'textfield'; }

    $parts = explode('.',$key);
    if (count($parts) == 1) { $area = 'Default'; }
    else { $area = $parts[0]; }

    $settings['magicpreview.'.$key] = $modx->newObject('modSystemSetting');
    $settings['magicpreview.'.$key]->set('key', 'magicpreview.'.$key);
    $settings['magicpreview.'.$key]->fromArray(array(
        'value' => $value,
        'xtype' => $type,
        'namespace' => 'magicpreview',
        'area' => $area
    ));
}

return $settings;


