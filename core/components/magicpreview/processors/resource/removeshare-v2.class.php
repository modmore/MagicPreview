<?php

/**
 * Revokes (deletes) a share link, scoped to the resource it belongs to.
 * MODX 2.x version.
 *
 * @package magicpreview
 */
class MagicPreviewRemoveShareProcessorV2 extends modProcessor
{
    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        $shareId = (int) $this->getProperty('share_id');
        if ($resourceId < 1 || $shareId < 1) {
            return $this->failure('Invalid resource or share ID.');
        }

        $corePath = $this->modx->getOption('magicpreview.core_path', null,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        /** @var MagicPreview $service */
        $service = $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

        if (!$service->revokeShare($shareId, $resourceId)) {
            return $this->failure('Share link not found.');
        }

        return $this->success();
    }
}

return 'MagicPreviewRemoveShareProcessorV2';
