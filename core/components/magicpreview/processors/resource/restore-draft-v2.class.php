<?php

require __DIR__ . '/DraftTrait.php';

/**
 * Restores a saved draft by injecting its data into the MODX reload
 * registry, then returning the reload token and redirect parameters.
 * MODX 2.x version.
 *
 * @package magicpreview
 */
class MagicPreviewRestoreDraftProcessorV2 extends modProcessor
{
    use DraftTrait;

    public function process()
    {
        $resourceId = (int) $this->getProperty('id');
        if ($resourceId < 1) {
            return $this->failure('Invalid resource ID.');
        }

        $draft = $this->getDraft();
        if (!$draft) {
            return $this->failure('No draft found.');
        }

        $token = $this->getProperty('create-resource-token');
        if (empty($token)) {
            return $this->failure('Missing resource token.');
        }

        $data = $draft['data'];

        // Ensure the reload data includes the resource token and a
        // reloaded flag so the controller treats this as a reload.
        $data['create-resource-token'] = $token;
        $data['reloaded'] = '1';

        // Write to the MODX reload registry — the same mechanism used
        // when a user changes the template on the resource form.
        if (!isset($this->modx->registry)) {
            $this->modx->getService('registry', 'registry.modRegistry');
        }
        $this->modx->registry->addRegister('resource_reload', 'registry.modDbRegister', [
            'directory' => 'resource_reload',
        ]);
        /** @var modRegister $reg */
        $reg = $this->modx->registry->resource_reload;
        if (!$reg->connect()) {
            return $this->failure('Could not connect to reload registry.');
        }
        $topic = '/resourcereload/';
        $reg->subscribe($topic);
        $reg->send($topic, [$token => $data], [
            'ttl' => 300,
            'delay' => -time(),
        ]);

        // Delete the draft now that it has been written to the reload
        // registry — prevents the restore prompt from appearing again
        // after the page redirects.
        $this->deleteDraft();

        // Determine context_key and class_key from the draft data,
        // falling back to sensible defaults.
        $contextKey = !empty($data['context_key']) ? $data['context_key'] : 'web';
        $classKey = !empty($data['class_key']) ? $data['class_key'] : 'modDocument';

        return $this->success('', [
            'id' => $resourceId,
            'reload' => $token,
            'action' => 'resource/update',
            'context_key' => $contextKey,
            'class_key' => $classKey,
        ]);
    }
}

return 'MagicPreviewRestoreDraftProcessorV2';
