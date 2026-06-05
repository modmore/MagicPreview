<?php
/**
 * MagicPreview — migrate cached drafts into the magicpreview_drafts table.
 *
 * Previously, drafts were stored in the MODX cache (partition
 * "magicpreview_drafts", keyed "{resourceId}/{userId}"). They now live in a
 * database table. This optional script copies any existing cached
 * drafts across. It ships inside the component so it is available after an
 * upgrade, but it is never run automatically — run it yourself if you want to
 * keep existing drafts.
 *
 *     php core/components/magicpreview/migrations/migrate.drafts.php
 *     php core/components/magicpreview/migrations/migrate.drafts.php --purge
 *
 * --purge also deletes each cache entry after a successful copy.
 */

// CLI only. Anything arriving over the web gets a 404 so the script isn't even
// acknowledged, let alone able to trigger database writes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

set_time_limit(0);

// Locate MODX. When installed, this script lives inside the MODX core at
// {core_path}components/magicpreview/migrations/, so the core is three levels up.
// In the development repo there is a config.core.php at the package root instead.
$installedCore = dirname(__DIR__, 3) . '/';
if (file_exists($installedCore . 'model/modx/modx.class.php')) {
    if (!defined('MODX_CORE_PATH')) {
        define('MODX_CORE_PATH', $installedCore);
    }
    if (!defined('MODX_CONFIG_KEY')) {
        define('MODX_CONFIG_KEY', 'config');
    }
} elseif (file_exists(dirname(__DIR__, 4) . '/config.core.php')) {
    require_once dirname(__DIR__, 4) . '/config.core.php';
} else {
    exit('MagicPreview: unable to locate the MODX core to run the migration.' . PHP_EOL);
}

require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('ECHO');

// Load the service so the magicpreview package (mpDraft) is registered.
$corePath = $modx->getOption('magicpreview.core_path', null,
    $modx->getOption('core_path') . 'components/magicpreview/');
$modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

$purge = in_array('--purge', $argv, true);

$partition = 'magicpreview_drafts';
$base = rtrim(str_replace('\\', '/', $modx->getCachePath()), '/') . '/' . $partition . '/';
$cacheManager = $modx->getCacheManager();

echo 'MODX core:  ' . MODX_CORE_PATH . "\n";
echo 'Cache scan: ' . $base . "\n";

$migrated = 0;
$skipped = 0;
$purged = 0;

if (!is_dir($base)) {
    echo "No file-cache drafts found at {$base}\n";
    echo "(Non-file cache providers can't be enumerated; drafts will migrate lazily on access.)\n";
    echo "Done.\n";
    return;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -10) !== '.cache.php') {
        continue;
    }

    // Derive the cache key ("{resourceId}/{userId}") from the file path.
    $full = str_replace('\\', '/', $file->getPathname());
    $key = substr($full, strlen($base), -strlen('.cache.php'));
    if (!preg_match('#^(\d+)/(\d+)$#', $key, $m)) {
        continue;
    }
    $resourceId = (int) $m[1];
    $userId = (int) $m[2];

    // Read through the cache manager so expiry is honoured and decoding handled.
    $draft = $cacheManager->get($key, [xPDO::OPT_CACHE_KEY => $partition]);
    if (empty($draft) || !is_array($draft) || empty($draft['data'])) {
        $skipped++;
        continue;
    }

    $now = time();
    $savedAt = isset($draft['saved_at']) ? (int) $draft['saved_at'] : $now;
    $ttl = (int) $modx->getOption('magicpreview.draft_ttl', null, 0);
    $expiresAt = $ttl > 0 ? $savedAt + $ttl : 0;
    $contextKey = isset($draft['data']['context_key']) ? (string) $draft['data']['context_key'] : 'web';

    /** @var mpDraft $obj */
    $obj = $modx->getObject('mpDraft', ['resource_id' => $resourceId, 'user_id' => $userId]);
    $isNew = false;
    if (!$obj) {
        $obj = $modx->newObject('mpDraft');
        $isNew = true;
    }
    $obj->fromArray([
        'resource_id' => $resourceId,
        'user_id' => $userId,
        'context_key' => $contextKey,
        'data' => json_encode($draft['data']),
        'saved_at' => $savedAt,
        'expires_at' => $expiresAt,
        'createdon' => $isNew ? $savedAt : (int) $obj->get('createdon'),
        'updatedon' => $now,
    ]);

    if ($obj->save()) {
        $migrated++;
        if ($purge) {
            if ($cacheManager->delete($key, [xPDO::OPT_CACHE_KEY => $partition])) {
                $purged++;
            }
        }
    } else {
        $skipped++;
        echo "Failed to save draft for resource {$resourceId}, user {$userId}.\n";
    }
}

echo "Migrated {$migrated} draft(s), skipped {$skipped}.\n";
if ($purge) {
    echo "Purged {$purged} cache entr(ies).\n";
}
echo "Done.\n";
