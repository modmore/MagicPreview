<?php

use MODX\Revolution\Processors\Processor;

require_once __DIR__ . '/ServiceTrait.php';

/**
 * Revokes (deletes) a share link, scoped to the resource it belongs to.
 *
 * @package magicpreview
 */
class MagicPreviewRemoveShareProcessor extends Processor
{
    use ServiceTrait;

    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        $shareId = (int) $this->getProperty('share_id');
        if ($resourceId < 1 || $shareId < 1) {
            return $this->failure('Invalid resource or share ID.');
        }

        if (!$this->canSaveResource($resourceId)) {
            return $this->failure('Access denied.');
        }

        // Editors may only revoke their own links; sudo/Administrator any.
        $shares = $this->getMagicPreviewService()->shares();
        if (!$shares->revokeShare($shareId, $resourceId, $shares->scopeUserId())) {
            return $this->failure('Share link not found.');
        }

        return $this->success();
    }
}

return 'MagicPreviewRemoveShareProcessor';
