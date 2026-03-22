<?php

use MODX\Revolution\Processors\Processor;

require __DIR__ . '/DraftTrait.php';

/**
 * Discards a saved draft for the given resource.
 *
 * @package magicpreview
 */
class MagicPreviewDiscardDraftProcessor extends Processor
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

return 'MagicPreviewDiscardDraftProcessor';
