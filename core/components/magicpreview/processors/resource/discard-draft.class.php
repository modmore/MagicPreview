<?php

use MODX\Revolution\Processors\Processor;

require_once __DIR__ . '/DraftTrait.php';

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

        // The user's live share links resolve against this draft, so they
        // stop working when it goes. The service reports them instead of
        // discarding until the client confirms with remove_shares set.
        $result = $this->getMagicPreviewService()->discardDraft(
            $resourceId,
            (int) $this->modx->user->get('id'),
            (bool) $this->getProperty('remove_shares', false)
        );

        return $this->success('', $result);
    }
}

return 'MagicPreviewDiscardDraftProcessor';
