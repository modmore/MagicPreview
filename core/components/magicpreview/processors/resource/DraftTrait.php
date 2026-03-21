<?php

/**
 * Shared draft utilities for the restore and discard processors.
 */
trait DraftTrait
{
    /**
     * Returns the cache key for the current resource + user draft.
     *
     * @return string
     */
    private function getDraftCacheKey()
    {
        $resourceId = (int) $this->getProperty('id');
        $userId = $this->modx->user->get('id');
        return $resourceId . '/' . $userId;
    }

    /**
     * Returns the draft data from the cache, or null if none exists.
     *
     * @return array|null
     */
    private function getDraft()
    {
        $data = $this->modx->cacheManager->get($this->getDraftCacheKey(), [
            xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
        ]);
        if (!empty($data) && is_array($data) && !empty($data['data'])) {
            return $data;
        }
        return null;
    }

    /**
     * Deletes the draft from the cache.
     *
     * @return void
     */
    private function deleteDraft()
    {
        $this->modx->cacheManager->delete($this->getDraftCacheKey(), [
            xPDO::OPT_CACHE_KEY => 'magicpreview_drafts',
        ]);
    }
}
