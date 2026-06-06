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

        // Share links govern the resource's public exposure, so managing
        // them requires edit rights on the resource itself.
        /** @var modResource|null $resource */
        $resource = $this->modx->getObject('modResource', $resourceId);
        if (!$resource || !$resource->checkPolicy('save')) {
            return $this->failure('Access denied.');
        }

        $corePath = $this->modx->getOption('magicpreview.core_path', null,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        /** @var MagicPreview $service */
        $service = $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

        // Editors may only revoke their own links; sudo/Administrator any.
        $revoked = $service->shares()->revokeShare(
            $shareId,
            $resourceId,
            $service->shares()->currentUserSeesAllShares() ? null : (int) $this->modx->user->get('id')
        );
        if (!$revoked) {
            return $this->failure('Share link not found.');
        }

        return $this->success();
    }
}

return 'MagicPreviewRemoveShareProcessorV2';
