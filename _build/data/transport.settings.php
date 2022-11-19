<?php

$settingSource = include dirname(__FILE__) . '/settings.php';

$settings = [];
foreach ($settingSource as $key => $options) {
    $val = $options['value'];

    if (isset($options['xtype'])) $xtype = $options['xtype'];
    elseif (is_int($val)) $xtype = 'numberfield';
    elseif (is_bool($val)) $xtype = 'modx-combo-boolean';
    else $xtype = 'textfield';

    /** @var modX $modx */
    $settings[$key] = $modx->newObject('modSystemSetting');
    $settings[$key]->fromArray([
        'key' => 'magicpreview.' . $key,
        'xtype' => $xtype,
        'value' => $options['value'],
        'namespace' => 'magicpreview',
        'area' => $options['area'],
        'editedon' => time(),
    ], '', true, true);
}

return $settings;


