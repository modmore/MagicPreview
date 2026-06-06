<?php

/**
 * MagicPreview drafts service: persistence of per-user resource drafts in
 * the magicpreview_drafts table, including the lazy migration of pre-1.7
 * cache-stored drafts. Obtain via MagicPreview::drafts().
 *
 * @package magicpreview
 */
class MagicPreviewDrafts
{
    private MagicPreview $magicpreview;
    private modX $modx;

    /**
     * @param MagicPreview $magicpreview
     */
    public function __construct(MagicPreview $magicpreview)
    {
        $this->magicpreview = $magicpreview;
        $this->modx = $magicpreview->modx;
    }

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
        $this->magicpreview->garbageCollectExpired();

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
        // including on Redis/Memcache setups a file scan could never find.
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
     * Deletes expired drafts. Cheap thanks to the expires_at index; called
     * via MagicPreview::garbageCollectExpired() on writes (no cron in MODX).
     *
     * @return int The number of rows removed.
     */
    public function garbageCollect(): int
    {
        // removeCollection() returns false on failure; normalise to 0 so the
        // return type stays a plain int (union types would require PHP 8.0,
        // and the project floor is 7.4).
        $removed = $this->modx->removeCollection('mpDraft', [
            'expires_at:>' => 0,
            'expires_at:<' => time(),
        ]);
        return $removed === false ? 0 : (int) $removed;
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
