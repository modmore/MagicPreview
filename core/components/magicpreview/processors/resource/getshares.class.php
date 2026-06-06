<?php

use MODX\Revolution\Processors\Processor;

/**
 * Lists the active share links for a resource. Metadata only: the raw token
 * is shown once at creation and cannot be reconstructed from the stored hash.
 *
 * @package magicpreview
 */
class MagicPreviewGetSharesProcessor extends Processor
{
    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
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

        // Editors see only their own links; sudo/Administrator users see all.
        $shares = $service->shares()->listSharesForResource(
            $resourceId,
            $service->shares()->currentUserSeesAllShares() ? null : (int) $this->modx->user->get('id')
        );

        return $this->modx->toJSON([
            'success' => true,
            'total' => count($shares),
            'results' => $shares,
        ]);
    }
}

return 'MagicPreviewGetSharesProcessor';
