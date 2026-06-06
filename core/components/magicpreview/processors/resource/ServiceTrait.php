<?php

/**
 * Shared processor utilities: MagicPreview service loading and the resource
 * access gate used by the share processors.
 */
trait ServiceTrait
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
     * Whether the current user may save (edit) the resource. Share links
     * govern the resource's public exposure, so managing them requires edit
     * rights on the resource itself.
     *
     * @param int $resourceId
     * @return bool
     */
    private function canSaveResource(int $resourceId): bool
    {
        /** @var modResource|null $resource */
        $resource = $this->modx->getObject('modResource', $resourceId);
        return $resource !== null && $resource->checkPolicy('save');
    }
}
