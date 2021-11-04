<?php
/* Get the core config */
if (!file_exists(dirname(dirname(__FILE__)).'/config.core.php')) {
    die('ERROR: missing '.dirname(dirname(__FILE__)).'/config.core.php file defining the MODX core path.');
}

echo "<pre>";
/* Boot up MODX */
echo "Loading modX...\n";
require_once dirname(dirname(__FILE__)).'/config.core.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
echo "Initializing manager...\n";
$modx->initialize('mgr');
$modx->getService('error','error.modError', '', '');

$componentPath = dirname(dirname(__FILE__));

$MagicPreview = $modx->getService('magicpreview','MagicPreview', $componentPath.'/core/components/magicpreview/model/magicpreview/', array(
    'magicpreview.core_path' => $componentPath.'/core/components/magicpreview/',
));


/* Namespace */
if (!createObject('modNamespace',array(
    'name' => 'magicpreview',
    'path' => $componentPath.'/core/components/magicpreview/',
    'assets_path' => $componentPath.'/assets/components/magicpreview/',
),'name', true)) {
    echo "Error creating namespace magicpreview.\n";
}

/* Path settings */
if (!createObject('modSystemSetting', array(
    'key' => 'magicpreview.core_path',
    'value' => $componentPath.'/core/components/magicpreview/',
    'xtype' => 'textfield',
    'namespace' => 'magicpreview',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating magicpreview.core_path setting.\n";
}

/* Fetch assets url */
$url = 'http';
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on')) {
    $url .= 's';
}
$url .= '://'.$_SERVER['SERVER_NAME'];
if ($_SERVER['SERVER_PORT'] !== '80') {
    $url .= ':'.$_SERVER['SERVER_PORT'];
}
$requestUri = $_SERVER['REQUEST_URI'];
$bootstrapPos = strpos($requestUri, '_bootstrap/');
$requestUri = rtrim(substr($requestUri, 0, $bootstrapPos), '/').'/';
$assetsUrl = "{$url}{$requestUri}assets/components/magicpreview/";

if (!createObject('modSystemSetting', array(
    'key' => 'magicpreview.assets_url',
    'value' => $assetsUrl,
    'xtype' => 'textfield',
    'namespace' => 'magicpreview',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating magicpreview.assets_url setting.\n";
}
if (!createObject('modPlugin', array(
    'name' => 'MagicPreview',
    'static' => true,
    'static_file' => $componentPath.'/core/components/magicpreview/elements/plugins/magicpreview.plugin.php',
), 'name', true)) {
    echo "Error creating MagicPreview Plugin.\n";
}
$vcPlugin = $modx->getObject('modPlugin', array('name' => 'MagicPreview'));
if ($vcPlugin) {
    if (!createObject('modPluginEvent', array(
        'pluginid' => $vcPlugin->get('id'),
        'event' => 'OnDocFormRender',
        'priority' => 0,
    ), array('pluginid','event'), false)) {
        echo "Error creating modPluginEvent.\n";
    }
    if (!createObject('modPluginEvent', array(
        'pluginid' => $vcPlugin->get('id'),
        'event' => 'OnLoadWebDocument',
        'priority' => 0,
    ), array('pluginid','event'), false)) {
        echo "Error creating modPluginEvent.\n";
    }
}



$event = $modx->getObject('modEvent', ['name' => 'OnResourceMagicPreview']);
if (!$event) {
    $event = $modx->newObject('modEvent');
}
$event->set('name', 'OnResourceMagicPreview');
$event->set('groupname', 'Magic Preview');
$event->set('service', 6);
$event->save();

//$settings = include dirname(dirname(__FILE__)).'/_build/data/settings.php';
//foreach ($settings as $key => $opts) {
//    if (!createObject('modSystemSetting', array(
//        'key' => 'magicpreview.' . $key,
//        'value' => $opts['value'],
//        'xtype' => (isset($opts['xtype'])) ? $opts['xtype'] : 'textfield',
//        'namespace' => 'magicpreview',
//        'area' => $opts['area'],
//        'editedon' => time(),
//    ), 'key', false)) {
//        echo "Error creating magicpreview.".$key." setting.\n";
//    }
//}


/* Create the tables */
$objectContainers = array(

);
echo "Creating tables...\n";
$manager = $modx->getManager();
foreach ($objectContainers as $oC) {
    $manager->createObjectContainer($oC);
}

echo "Done.\n";

// Refresh the cache
$modx->cacheManager->refresh();


/**
 * Creates an object.
 *
 * @param string $className
 * @param array $data
 * @param string $primaryField
 * @param bool $update
 * @return bool
 */
function createObject ($className = '', array $data = array(), $primaryField = '', $update = true) {
    global $modx;
    /* @var xPDOObject $object */
    $object = null;

    /* Attempt to get the existing object */
    if (!empty($primaryField)) {
        if (is_array($primaryField)) {
            $condition = array();
            foreach ($primaryField as $key) {
                $condition[$key] = $data[$key];
            }
        }
        else {
            $condition = array($primaryField => $data[$primaryField]);
        }
        $object = $modx->getObject($className, $condition);
        if ($object instanceof $className) {
            if ($update) {
                $object->fromArray($data);
                return $object->save();
            } else {
                $condition = $modx->toJSON($condition);
                echo "Skipping {$className} {$condition}: already exists.\n";
                return true;
            }
        }
    }

    /* Create new object if it doesn't exist */
    if (!$object) {
        $object = $modx->newObject($className);
        $object->fromArray($data, '', true);
        return $object->save();
    }

    return false;
}
