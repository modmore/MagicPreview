<?php

require __DIR__ . '/DraftTrait.php';

/**
 * Discards a saved draft for the given resource.
 * MODX 2.x version.
 *
 * @package magicpreview
 */
class MagicPreviewDiscardDraftProcessorV2 extends modProcessor
{
    use DraftTrait;

    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
        }

        $this->deleteDraft();

        return $this->success();
    }
}

return 'MagicPreviewDiscardDraftProcessorV2';
