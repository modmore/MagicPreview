<?php

return [
    'breakpoint_desktop' => [
        'area' => 'Breakpoints',
        'value' => '1280px'
    ],
    'breakpoint_tablet' => [
        'area' => 'Breakpoints',
        'value' => '768px'
    ],
    'breakpoint_mobile' => [
        'area' => 'Breakpoints',
        'value' => '320px'
    ],
    'custom_preview_tpl' => [
        'area' => 'Preview',
        'value' => ''
    ],
    'custom_preview_css' => [
        'area' => 'Preview',
        'value' => ''
    ],
    'preview_mode' => [
        'area' => 'Preview',
        'value' => 'newwindow',
        'xtype' => 'magicpreview-combo-preview-mode',
    ],
    'panel_layout' => [
        'area' => 'Preview',
        'value' => 'overlay',
        'xtype' => 'magicpreview-combo-panel-layout',
    ],
    'panel_extended' => [
        'area' => 'Preview',
        'value' => false,
        'xtype' => 'combo-boolean',
    ],
    'auto_refresh_interval' => [
        'area' => 'Preview',
        'value' => '5',
        'xtype' => 'numberfield',
    ],
];