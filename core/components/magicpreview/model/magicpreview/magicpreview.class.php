<?php

require_once __DIR__ . '/magicpreviewdrafts.class.php';
require_once __DIR__ . '/magicpreviewshares.class.php';

/**
 * MagicPreview main service class: configuration, the shared preview cache
 * and access to the domain services (drafts() and shares()).
 *
 * @package magicpreview
 */
class MagicPreview
{
    public ?modX $modx = null;
    public array $config = [];
    public bool $debug = false;

    const VERSION = '1.7.0-pl';

    private ?MagicPreviewDrafts $drafts = null;
    private ?MagicPreviewShares $shares = null;
    private bool $garbageCollected = false;

    /**
     * @param \modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;

        $corePath = $this->modx->getOption('magicpreview.core_path', $config,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        $assetsUrl = $this->modx->getOption('magicpreview.assets_url', $config,
            $this->modx->getOption('assets_url') . 'components/magicpreview/');
        $assetsPath = $this->modx->getOption('magicpreview.assets_path', $config,
            $this->modx->getOption('assets_path') . 'components/magicpreview/');
        $this->config = array_merge([
            'basePath' => $corePath,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'processorsPath' => $corePath . 'processors/',
            'elementsPath' => $corePath . 'elements/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl . 'connector.php',
            'version' => self::VERSION,
        ], $config);

        $modelPath = $this->config['modelPath'];
        $this->modx->addPackage('magicpreview', $modelPath);
        $this->modx->lexicon->load('magicpreview:default');
    }

    /**
     * Returns the drafts service (per-user resource drafts).
     *
     * @return MagicPreviewDrafts
     */
    public function drafts(): MagicPreviewDrafts
    {
        if ($this->drafts === null) {
            $this->drafts = new MagicPreviewDrafts($this);
        }
        return $this->drafts;
    }

    /**
     * Returns the shares service (public share links to drafts).
     *
     * @return MagicPreviewShares
     */
    public function shares(): MagicPreviewShares
    {
        if ($this->shares === null) {
            $this->shares = new MagicPreviewShares($this);
        }
        return $this->shares;
    }

    /**
     * The single definition of expiry for drafts and share links: a row is
     * expired iff it has an expiry (expires_at > 0) that has passed.
     * 0 always means "never expires".
     *
     * @param int $expiresAt Unix timestamp; 0 = never expires.
     * @return bool
     */
    public static function isExpired(int $expiresAt): bool
    {
        return $expiresAt > 0 && $expiresAt < time();
    }

    /**
     * The xPDO criteria group matching non-expired rows — the query form of
     * isExpired()'s negation, for listing/counting active rows.
     *
     * @return array
     */
    public static function notExpiredCriteria(): array
    {
        return [
            'expires_at' => 0,
            'OR:expires_at:>' => time(),
        ];
    }

    /**
     * The xPDO criteria matching expired rows — the query form of
     * isExpired(), for garbage collection.
     *
     * @return array
     */
    public static function expiredCriteria(): array
    {
        return [
            'expires_at:>' => 0,
            'expires_at:<' => time(),
        ];
    }

    /**
     * Deletes expired drafts and share links. Cheap thanks to the expires_at
     * indexes; called opportunistically on writes since there's no cron by default.
     * Runs at most once per request — a single request can hit several
     * write paths (e.g. save draft + create share) and one sweep covers all.
     *
     * @return int The number of rows removed.
     */
    public function garbageCollectExpired(): int
    {
        if ($this->garbageCollected) {
            return 0;
        }
        $this->garbageCollected = true;
        return $this->drafts()->garbageCollect() + $this->shares()->garbageCollect();
    }

    /**
     * Discards a user's draft, coordinating its dependent share links: live
     * links resolve the draft at view time, so they must be removed with it.
     * Unless $removeShares is set, an existing link blocks the discard so
     * the caller can ask the user to confirm first.
     *
     * @param int $resourceId
     * @param int $userId
     * @param bool $removeShares Confirmed: also remove the user's share links.
     * @return array ['discarded' => bool, 'live_shares' => int]
     */
    public function discardDraft(int $resourceId, int $userId, bool $removeShares = false): array
    {
        $liveShares = $this->shares()->countLiveShares($resourceId, $userId);
        if ($liveShares > 0 && !$removeShares) {
            return ['discarded' => false, 'live_shares' => $liveShares];
        }

        if ($liveShares > 0) {
            $this->shares()->removeLiveShares($resourceId, $userId);
        }
        $this->drafts()->deleteDraft($resourceId, $userId);

        return ['discarded' => true, 'live_shares' => 0];
    }

    /**
     * Removes a user's draft unless live share links still depend on it —
     * used after a restore, which consumes the draft only when nothing
     * public resolves against it anymore.
     *
     * @param int $resourceId
     * @param int $userId
     * @return void
     */
    public function deleteDraftIfUnshared(int $resourceId, int $userId): void
    {
        if ($this->shares()->countLiveShares($resourceId, $userId) === 0) {
            $this->drafts()->deleteDraft($resourceId, $userId);
        }
    }

    /**
     * Applies cached preview / draft data to an in-memory resource for an
     * overridden render: nothing is saved, cacheable=false keeps the result
     * out of the real resource cache, and the in-memory element cache is
     * wiped so placeholders don't show the live cached values. Shared by the
     * manager preview render (plugin OnLoadWebDocument) and the public share
     * endpoint (share.php).
     *
     * @param modResource $resource
     * @param array $data The resource snapshot (incl. flattened TVs).
     * @return void
     */
    public function applyPreviewData($resource, array $data): void
    {
        $resource->fromArray($data, '', true, true);
        $resource->set('cacheable', false);
        $resource->setProcessed(false);
        $this->modx->elementCache = null;
    }

    /**
     * Writes preview data into the short-lived preview cache and returns the
     * cache hash, consumed by the front end as ?show_preview=<hash> (manager
     * sessions only; see the OnLoadWebDocument handler in the plugin).
     *
     * The hash is a deterministic digest of the data, so identical content
     * always yields the same key — this lets the client-side auto-refresh
     * skip reloading the iframe when nothing has actually changed.
     *
     * @param int $resourceId
     * @param array $data The resource snapshot (incl. flattened TVs).
     * @return string|null The 24-character hash, or null if encoding failed.
     */
    public function cachePreviewData(int $resourceId, array $data): ?string
    {
        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not encode preview data for resource ' . $resourceId);
            return null;
        }

        $hash = substr(hash('sha256', $encoded), 0, 24);
        $this->modx->cacheManager->set($resourceId . '/' . $hash, $data, 3600, [
            xPDO::OPT_CACHE_KEY => 'magicpreview',
        ]);
        return $hash;
    }
}
