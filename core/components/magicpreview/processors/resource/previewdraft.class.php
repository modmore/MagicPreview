<?php

use MODX\Revolution\Processors\Processor;

require_once __DIR__ . '/DraftTrait.php';

/**
 * Prepares a manager-side preview of the user's saved draft: writes the
 * draft data into the preview cache and returns the hash, which the client
 * opens through the standard mgr-gated ?show_preview= front-end render.
 *
 * @package magicpreview
 */
class MagicPreviewPreviewDraftProcessor extends Processor
{
    use DraftTrait;

    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
        }

        $draft = $this->getDraft();
        if ($draft === null) {
            return $this->failure('No draft found.');
        }

        $hash = $this->getMagicPreviewService()->cachePreviewData($resourceId, $draft['data']);
        if ($hash === null) {
            return $this->failure('Could not prepare the preview.');
        }

        return $this->success('', [
            'preview_hash' => $hash,
        ]);
    }
}

return 'MagicPreviewPreviewDraftProcessor';
