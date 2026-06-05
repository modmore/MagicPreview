<?php

/**
 * MagicPreview main service class
 *
 * @package magicpreview
 */
class MagicPreview
{
    public ?modX $modx = null;
    public array $config = [];
    public bool $debug = false;

    const VERSION = '1.6.0-pl';

    /** Share link types: a frozen copy taken at share time, or the live draft. */
    const SHARE_TYPE_SNAPSHOT = 'snapshot';
    const SHARE_TYPE_LIVE = 'live';

    /**
     * Returns the LEGACY (pre-1.7) cache key for a resource draft, scoped by user.
     *
     * Drafts now live in the magicpreview_drafts table; this key is only used
     * to find and clean up old cache entries (see getDraft()'s lazy migration).
     *
     * @param int $resourceId
     * @param int $userId
     * @return string
     */
    public static function getDraftCacheKey(int $resourceId, int $userId): string
    {
        return (int) $resourceId . '/' . (int) $userId;
    }

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
     * Saves (upserts) a user's draft of a resource. One draft per (resource, user).
     *
     * The expiry is derived from the magicpreview.draft_ttl setting relative to
     * the save time (0 = never expires), matching the old cache TTL behaviour.
     *
     * @param int $resourceId
     * @param int $userId
     * @param array $data The resource snapshot (incl. flattened TVs), as built by the preview processor.
     * @param string $contextKey
     * @param int|null $savedAt Original save time to preserve when migrating; null = now.
     * @return bool
     */
    public function saveDraft(
        int $resourceId,
        int $userId,
        array $data,
        string $contextKey = 'web',
        ?int $savedAt = null)
    : bool
    {
        $now = time();
        $savedAt = $savedAt !== null ? (int) $savedAt : $now;
        $ttl = (int) $this->modx->getOption('magicpreview.draft_ttl', null, 0);

        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Could not encode draft data for resource ' . $resourceId
            );
            return false;
        }

        /** @var mpDraft|null $draft */
        $draft = $this->modx->getObject('mpDraft', [
            'resource_id' => (int) $resourceId,
            'user_id' => (int) $userId,
        ]);
        if (!$draft) {
            $draft = $this->modx->newObject('mpDraft');
            $draft->set('resource_id', (int) $resourceId);
            $draft->set('user_id', (int) $userId);
            $draft->set('createdon', $now);
        }
        $draft->fromArray([
            'context_key' => (string) $contextKey,
            'data' => $encoded,
            'saved_at' => $savedAt,
            'expires_at' => $ttl > 0 ? $savedAt + $ttl : 0,
            'updatedon' => $now,
        ]);

        if (!$draft->save()) {
            return false;
        }

        // Remove any legacy cache entry for this draft so the lazy migration in
        // getDraft() can never resurrect an outdated copy.
        $this->modx->cacheManager->delete(self::getDraftCacheKey($resourceId, $userId), [
            xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
        ]);

        // Drafts no longer expire by themselves the way cache entries did, so
        // sweep out expired rows while we're writing anyway.
        $this->garbageCollectExpired();

        return true;
    }

    /**
     * Returns the user's draft for a resource, or null if there is none (or it expired).
     *
     * The shape matches what the old cache entries contained:
     * ['data' => array, 'saved_at' => int, 'user_id' => int, 'resource_id' => int]
     *
     * @param int $resourceId
     * @param int $userId
     * @return array|null
     */
    public function getDraft(int $resourceId, int $userId): ?array
    {
        /** @var mpDraft|null $draft */
        $draft = $this->modx->getObject('mpDraft', [
            'resource_id' => (int) $resourceId,
            'user_id' => (int) $userId,
        ]);

        // No row yet: this may be a pre-1.7 draft still living in the cache —
        // including on Redis/Memcache setups the bulk migration script can't scan.
        if (!$draft) {
            return $this->migrateCachedDraft($resourceId, $userId);
        }

        // Enforce expiry on read.
        $expiresAt = (int) $draft->get('expires_at');
        if ($expiresAt > 0 && $expiresAt < time()) {
            $draft->remove();
            return null;
        }

        $data = json_decode($draft->get('data'), true);
        if (!is_array($data) || empty($data)) {
            return null;
        }

        return [
            'data' => $data,
            'saved_at' => (int) $draft->get('saved_at'),
            'user_id' => (int) $draft->get('user_id'),
            'resource_id' => (int) $draft->get('resource_id'),
        ];
    }

    /**
     * Deletes the user's draft for a resource.
     *
     * @param int $resourceId
     * @param int $userId
     * @return bool
     */
    public function deleteDraft(int $resourceId, int $userId): bool
    {
        $success = true;
        /** @var mpDraft|null $draft */
        $draft = $this->modx->getObject('mpDraft', [
            'resource_id' => (int) $resourceId,
            'user_id' => (int) $userId,
        ]);
        if ($draft) {
            $success = $draft->remove();
        }

        // Always clear any legacy cache entry too, otherwise the lazy migration
        // in getDraft() would resurrect a discarded draft.
        $this->modx->cacheManager->delete(self::getDraftCacheKey($resourceId, $userId), [
            xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
        ]);

        return $success;
    }

    /**
     * Deletes expired drafts and share links. Cheap thanks to the expires_at
     * indexes; called opportunistically on writes since there is no cron in MODX.
     *
     * @return int The number of rows removed.
     */
    public function garbageCollectExpired(): int
    {
        $criteria = [
            'expires_at:>' => 0,
            'expires_at:<' => time(),
        ];
        // removeCollection() returns false on failure; normalise to 0 so the
        // return type stays a plain int (union types would require PHP 8.0,
        // and the project floor is 7.4).
        $drafts = $this->modx->removeCollection('mpDraft', $criteria);
        $shares = $this->modx->removeCollection('mpShare', $criteria);
        return ($drafts === false ? 0 : (int) $drafts)
            + ($shares === false ? 0 : (int) $shares);
    }

    /**
     * Creates a shareable public link to a draft of a resource.
     *
     * The raw token is returned ONCE here (embedded in the url) and is never
     * stored — only its sha256 hash is persisted, so a database leak can't be
     * used to mint working links.
     *
     * @param int $resourceId
     * @param int $userId The user creating the share.
     * @param array $data The resource snapshot for snapshot shares; ignored for live shares.
     * @param string $contextKey
     * @param string $type self::SHARE_TYPE_SNAPSHOT (frozen copy taken now) or
     *                     self::SHARE_TYPE_LIVE (renders the creator's current draft at view time).
     * @param int|null $ttl Lifetime in seconds; null = the magicpreview.share_link_ttl setting, 0 = never expires.
     * @param string $label
     * @return array|null ['id' => int, 'token' => string, 'url' => string, 'expires_at' => int], or null on failure.
     */
    public function createShare(
        int $resourceId,
        int $userId,
        array $data,
        string $contextKey = 'web',
        string $type = self::SHARE_TYPE_SNAPSHOT,
        ?int $ttl = null,
        string $label = ''
    ): ?array
    {
        if (!in_array($type, [self::SHARE_TYPE_SNAPSHOT, self::SHARE_TYPE_LIVE], true)) {
            return null;
        }

        $encoded = null;
        if ($type === self::SHARE_TYPE_SNAPSHOT) {
            $encoded = json_encode($data);
            if (empty($data) || !is_string($encoded)) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not encode share data for resource ' . $resourceId);
                return null;
            }
        }

        if ($ttl === null) {
            $ttl = (int) $this->modx->getOption('magicpreview.share_link_ttl', null, 604800);
        }
        $now = time();

        // Generate an unguessable token; only its hash is stored. The unique
        // index on token_hash stays authoritative — this loop merely avoids a
        // doomed insert on the (astronomically unlikely) collision.
        $token = null;
        $tokenHash = null;
        for ($i = 0; $i < 3; $i++) {
            $candidate = bin2hex(random_bytes(32));
            $candidateHash = hash('sha256', $candidate);
            if (!$this->modx->getObject('mpShare', ['token_hash' => $candidateHash])) {
                $token = $candidate;
                $tokenHash = $candidateHash;
                break;
            }
        }
        if ($token === null) {
            return null;
        }

        /** @var mpShare $share */
        $share = $this->modx->newObject('mpShare');
        $share->fromArray([
            'token_hash' => $tokenHash,
            'resource_id' => $resourceId,
            'user_id' => $userId,
            'context_key' => $contextKey,
            'type' => $type,
            'data' => $encoded,
            'label' => $label,
            'createdon' => $now,
            'expires_at' => $ttl > 0 ? $now + $ttl : 0,
        ]);
        if (!$share->save()) {
            return null;
        }

        $this->garbageCollectExpired();

        return [
            'id' => (int) $share->get('id'),
            'token' => $token,
            'url' => $this->buildShareUrl($token, $contextKey),
            'expires_at' => (int) $share->get('expires_at'),
        ];
    }

    /**
     * Resolves a raw share token to a renderable draft, or null if the token
     * is unknown or expired — or, for live shares, if the creator's draft no
     * longer exists.
     *
     * Deliberately performs no writes on misses, so probing random tokens
     * can't generate database load; the view counter is only bumped for
     * valid shares.
     *
     * @param string $token The raw token from the share URL.
     * @return array|null ['resource_id' => int, 'context_key' => string, 'type' => string, 'data' => array]
     */
    public function getValidShare(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        /** @var mpShare|null $share */
        $share = $this->modx->getObject('mpShare', ['token_hash' => hash('sha256', $token)]);
        if (!$share) {
            return null;
        }

        $expiresAt = (int) $share->get('expires_at');
        if ($expiresAt > 0 && $expiresAt < time()) {
            return null;
        }

        if ($share->get('type') === self::SHARE_TYPE_LIVE) {
            // A live share renders whatever the creator's draft currently holds.
            $draft = $this->getDraft((int) $share->get('resource_id'), (int) $share->get('user_id'));
            $data = $draft !== null ? $draft['data'] : null;
        } else {
            $data = json_decode((string) $share->get('data'), true);
        }
        if (!is_array($data) || empty($data)) {
            return null;
        }

        // Best-effort view tracking.
        $share->set('views', (int) $share->get('views') + 1);
        $share->set('last_viewed_at', time());
        $share->save();

        return [
            'resource_id' => (int) $share->get('resource_id'),
            'context_key' => (string) $share->get('context_key'),
            'type' => (string) $share->get('type'),
            'data' => $data,
        ];
    }

    /**
     * Returns the active (non-expired) share links for a resource, newest
     * first. Neither the token hash nor the snapshot data are included — the
     * raw token is shown once at creation and cannot be reconstructed, so the
     * listing is metadata only.
     *
     * @param int $resourceId
     * @return array
     */
    public function listSharesForResource(int $resourceId): array
    {
        $query = $this->modx->newQuery('mpShare');
        $query->where([
            'resource_id' => $resourceId,
            [
                'expires_at' => 0,
                'OR:expires_at:>' => time(),
            ],
        ]);
        $query->sortby('createdon', 'DESC');

        $shares = [];
        /** @var mpShare $share */
        foreach ($this->modx->getCollection('mpShare', $query) as $share) {
            $shares[] = [
                'id' => (int) $share->get('id'),
                'type' => (string) $share->get('type'),
                'label' => (string) $share->get('label'),
                'user_id' => (int) $share->get('user_id'),
                'createdon' => (int) $share->get('createdon'),
                'expires_at' => (int) $share->get('expires_at'),
                'last_viewed_at' => (int) $share->get('last_viewed_at'),
                'views' => (int) $share->get('views'),
            ];
        }
        return $shares;
    }

    /**
     * Revokes (deletes) a share link by id, optionally constrained to a
     * resource so callers can't reach across to another resource's links.
     *
     * @param int $shareId
     * @param int|null $resourceId
     * @return bool
     */
    public function revokeShare(int $shareId, ?int $resourceId = null): bool
    {
        $criteria = ['id' => $shareId];
        if ($resourceId !== null) {
            $criteria['resource_id'] = $resourceId;
        }
        $share = $this->modx->getObject('mpShare', $criteria);
        if (!$share) {
            return false;
        }
        return $share->remove();
    }

    /**
     * Builds the absolute public URL for a share token, using the target
     * context's site_url so multi-context sites link to the right host.
     *
     * @param string $token
     * @param string $contextKey
     * @return string
     */
    public function buildShareUrl(string $token, string $contextKey = 'web'): string
    {
        $siteUrl = '';
        $context = $this->modx->getContext($contextKey);
        if ($context) {
            $siteUrl = (string) $context->getOption('site_url', null, '');
        }
        if ($siteUrl === '') {
            $siteUrl = (string) $this->modx->getOption('site_url');
        }

        // assetsUrl is usually relative (/assets/components/magicpreview/) but
        // can be configured absolute; only prefix the site URL when relative.
        $assetsUrl = (string) $this->config['assetsUrl'];
        if (strpos($assetsUrl, 'http://') !== 0 && strpos($assetsUrl, 'https://') !== 0) {
            $assetsUrl = rtrim($siteUrl, '/') . '/' . ltrim($assetsUrl, '/');
        }

        return $assetsUrl . 'share.php?t=' . $token;
    }

    /**
     * One-time lazy migration of a pre-1.7 cached draft into the database.
     * Returns the draft in the same shape as getDraft(), or null if the cache
     * holds nothing either.
     *
     * @param int $resourceId
     * @param int $userId
     * @return array|null
     */
    private function migrateCachedDraft(int $resourceId, int $userId): ?array
    {
        $cached = $this->modx->cacheManager->get(self::getDraftCacheKey($resourceId, $userId), [
            xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
        ]);
        if (empty($cached) || !is_array($cached) || empty($cached['data'])) {
            return null;
        }

        $savedAt = isset($cached['saved_at']) ? (int) $cached['saved_at'] : time();
        $contextKey = isset($cached['data']['context_key']) ? (string) $cached['data']['context_key'] : 'web';

        // Already older than the current draft lifetime (e.g. the draft_ttl
        // setting changed since it was written): treat as expired and clean up.
        $ttl = (int) $this->modx->getOption('magicpreview.draft_ttl', null, 0);
        if ($ttl > 0 && $savedAt + $ttl < time()) {
            $this->modx->cacheManager->delete(self::getDraftCacheKey($resourceId, $userId), [
                xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
            ]);
            return null;
        }

        // saveDraft() also removes the cache entry on success, so this runs once.
        $this->saveDraft($resourceId, $userId, $cached['data'], $contextKey, $savedAt);

        return [
            'data' => $cached['data'],
            'saved_at' => $savedAt,
            'user_id' => (int) $userId,
            'resource_id' => (int) $resourceId,
        ];
    }
}

