<?php
/**
 * MagicPreview — model generator.
 *
 * Run once after editing _build/schema/magicpreview.mysql.schema.xml:
 *     php _build/build.schema.php
 *
 * Generates the classic 1.1 (non-namespaced) xPDO model tree into
 * core/components/magicpreview/model/magicpreview/ so the same generated code
 * loads on both MODX 2.x and 3.x.
 *
 * MUST be run against a MODX 2.x install: only the xPDO 1.x generator emits
 * the classic files. MODX 3's xPDO 3 generator produces namespaced output
 * (mpDraft.php, "magicpreview\mpDraft" metadata) that won't load on MODX 2,
 * and it doesn't substitute this template's [+class-lowercase+] placeholder.
 */
set_time_limit(0);

require_once dirname(__DIR__) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

// Guard: refuse to run under MODX 3 (see the note in the header).
$modxVersion = $modx->getVersionData();
if (version_compare($modxVersion['full_version'], '3.0.0-dev', '>=')) {
    echo "This generator must be run against a MODX 2.x install (detected {$modxVersion['full_version']}).\n";
    echo "The classic xPDO 1.x output it produces loads on both MODX 2 and 3;\n";
    echo "running it under MODX 3 would generate incompatible namespaced files.\n";
    exit(1);
}

$root = dirname(__DIR__) . '/';
$sources = [
    'model'  => $root . 'core/components/magicpreview/model/',
    'schema' => $root . '_build/schema/',
];

$manager = $modx->getManager();
$generator = $manager->getGenerator();

$generator->classTemplate = <<<EOD
<?php
/**
 * MagicPreview
 *
 * @package magicpreview
 */
class [+class+] extends [+extends+] {}

EOD;

$generator->platformTemplate = <<<EOD
<?php
/**
 * MagicPreview
 *
 * @package magicpreview
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\\\', '/') . '/[+class-lowercase+].class.php');
class [+class+]_[+platform+] extends [+class+] {}

EOD;

$generator->mapHeader = <<<EOD
<?php
/**
 * MagicPreview
 *
 * @package magicpreview
 */

EOD;

$generator->parseSchema($sources['schema'] . 'magicpreview.mysql.schema.xml', $sources['model']);

echo "Done.\n";
exit();
