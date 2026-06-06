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

        $service = $this->getMagicPreviewService();
        $userId = (int) $this->modx->user->get('id');

        // The user's live share links resolve against this draft, so they
        // stop working when it goes. Report them instead of discarding until
        // the client confirms with remove_shares set.
        $liveShares = $service->shares()->countLiveShares($resourceId, $userId);
        if ($liveShares > 0 && !(bool) $this->getProperty('remove_shares', false)) {
            return $this->success('', [
                'discarded' => false,
                'live_shares' => $liveShares,
            ]);
        }
        if ($liveShares > 0) {
            $service->shares()->removeLiveShares($resourceId, $userId);
        }

        $this->deleteDraft();

        return $this->success('', [
            'discarded' => true,
        ]);
    }
}

return 'MagicPreviewDiscardDraftProcessor';
