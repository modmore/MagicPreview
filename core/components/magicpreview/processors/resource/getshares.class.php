<?php

use MODX\Revolution\Processors\Processor;

require_once __DIR__ . '/ServiceTrait.php';

/**
 * Lists the active share links for a resource. Metadata only: the raw token
 * is shown once at creation and cannot be reconstructed from the stored hash.
 *
 * @package magicpreview
 */
class MagicPreviewGetSharesProcessor extends Processor
{
    use ServiceTrait;

    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
        }

        if (!$this->canSaveResource($resourceId)) {
            return $this->failure('Access denied.');
        }

        $shares = $this->getMagicPreviewService()->shares();
        $links = $shares->listSharesForResource($resourceId, $shares->scopeUserId());

        return $this->modx->toJSON([
            'success' => true,
            'total' => count($links),
            'results' => $links,
        ]);
    }
}

return 'MagicPreviewGetSharesProcessor';
