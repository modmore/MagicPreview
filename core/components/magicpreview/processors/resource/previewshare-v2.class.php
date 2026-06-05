<?php

/**
 * Prepares a manager-side preview of a share link: writes the share's
 * snapshot (or, for live shares, the creator's current draft) into the
 * preview cache and returns the hash, which the client opens through the
 * standard mgr-gated ?show_preview= front-end render. No public token is
 * involved — the raw share URL can't be reconstructed from the stored hash.
 * MODX 2.x version.
 *
 * @package magicpreview
 */
class MagicPreviewPreviewShareProcessorV2 extends modProcessor
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

        $share = $service->getShareById($shareId, $resourceId);
        if ($share === null) {
            return $this->failure('Share link not found.');
        }

        $hash = $service->cachePreviewData($resourceId, $share['data']);
        if ($hash === null) {
            return $this->failure('Could not prepare the preview.');
        }

        return $this->success('', [
            'preview_hash' => $hash,
        ]);
    }
}

return 'MagicPreviewPreviewShareProcessorV2';
