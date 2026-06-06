<?php

/**
 * Shared draft utilities for the restore and discard processors.
 */
trait DraftTrait
{
    /**
     * Returns the MagicPreview service, loading it if needed (the processor
     * may be invoked through a third-party connector that hasn't loaded it).
     *
     * @return MagicPreview
     */
    private function getMagicPreviewService(): MagicPreview
    {
        $corePath = $this->modx->getOption('magicpreview.core_path', null,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        return $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');
    }

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

    /**
     * Deletes the draft for the current resource + user.
     *
     * @return void
     */
    private function deleteDraft(): void
    {
        $this->getMagicPreviewService()->drafts()->deleteDraft(
            (int) $this->getProperty('id'),
            $this->modx->user->get('id')
        );
    }
}
