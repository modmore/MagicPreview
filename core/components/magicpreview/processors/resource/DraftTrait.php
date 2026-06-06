<?php

require_once __DIR__ . '/ServiceTrait.php';

/**
 * Shared draft utilities for the restore and discard processors.
 */
trait DraftTrait
{
    use ServiceTrait;

    /**
     * Returns the draft for the current resource + user, or null if none exists.
     *
     * @return array|null ['data' => array, 'saved_at' => int, 'user_id' => int, 'resource_id' => int]
     */
    private function getDraft(): ?array
    {
        return $this->getMagicPreviewService()->drafts()->getDraft(
            (int) $this->getProperty('id'),
            $this->modx->user->get('id')
        );
    }
}
