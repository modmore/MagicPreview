<?php
/**
 * mpField - returns the click-to-field data attributes for a resource field or TV.
 *
 * Place it inside your own element so MagicPreview never has to inject markup:
 *
 *   <h1 [[mpField? &name=`pagetitle`]]>[[*pagetitle]]</h1>
 *
 * With pdoTools/Fenom any of these work, because pdoTools runs an unknown
 * modifier name as a snippet of that name:
 *
 *   {'pagetitle' | mpField}
 *   {'mpField' | snippet: ['name' => 'pagetitle']}
 *   {$_modx->runSnippet('mpField', ['name' => 'pagetitle'])}
 *
 * Returns an empty string unless this is a manager preview render with
 * magicpreview.click_to_field enabled, so it is inert on live pages and on
 * public share links.
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @package magicpreview
 */

$corePath = $modx->getOption('magicpreview.core_path', null,
    $modx->getOption('core_path') . 'components/magicpreview/');
/** @var MagicPreview $service */
$service = $modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');
if (!($service instanceof MagicPreview)) {
    return '';
}
if (!$service->isClickToFieldActive()) {
    return '';
}

$name = (string)$modx->getOption('name', $scriptProperties, '');
if ($name === '') {
    // The pdoTools auto-modifier path ({'pagetitle'|mpField}) passes the piped
    // value as 'input' rather than 'name'.
    $name = (string)$modx->getOption('input', $scriptProperties, '');
}
$name = trim($name);
if ($name === '') {
    $modx->log(modX::LOG_LEVEL_WARN, '[MagicPreview] mpField called without a field name.');
    return '';
}

// Resource fields win over TVs of the same name, matching how modParser itself
// resolves [[*name]] (see modParser::processTag).
$identifier = null;
if ($modx->resource && is_array($modx->resource->_fieldMeta)
    && array_key_exists($name, $modx->resource->_fieldMeta)) {
    $identifier = $name;
} else {
    $tv = $modx->getObject('modTemplateVar', ['name' => $name]);
    if ($tv) {
        $identifier = 'tv' . $tv->get('id');
    }
}

if ($identifier === null) {
    $modx->log(modX::LOG_LEVEL_WARN,
        '[MagicPreview] mpField: no resource field or TV named "' . $name . '".');
    return '';
}

$output = 'data-magicpreview-field="' . htmlspecialchars($identifier, ENT_QUOTES) . '"';

// The click handler defaults idx to 0, so only emit it when asked for.
$idx = $modx->getOption('idx', $scriptProperties, '');
if ($idx !== '' && $idx !== null) {
    $output .= ' data-magicpreview-idx="' . (int)$idx . '"';
}

return $output;