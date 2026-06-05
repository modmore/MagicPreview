<?php

/**
 * Lists the active share links for a resource. Metadata only: the raw token
 * is shown once at creation and cannot be reconstructed from the stored hash.
 * MODX 2.x version.
 *
 * @package magicpreview
 */
class MagicPreviewGetSharesProcessorV2 extends modProcessor
{
    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
        }

        $corePath = $this->modx->getOption('magicpreview.core_path', null,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        /** @var MagicPreview $service */
        $service = $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

        $shares = $service->listSharesForResource($resourceId);

        return $this->modx->toJSON([
            'success' => true,
            'total' => count($shares),
            'results' => $shares,
        ]);
    }
}

return 'MagicPreviewGetSharesProcessorV2';
