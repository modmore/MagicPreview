<?php

/**
 * This trait includes overridden update processor methods that both 2.x and 3.x processors need.
 */
trait PreviewTrait
{
    private ?string $previewHash = null;
    private bool $failedSuccessfully = false;
    private ?array $shareResult = null;

    public function fireBeforeSaveEvent()
    {
        // Invoke an event to allow other modules to prepare/modify the resource before preview.
        $this->modx->invokeEvent('OnResourceMagicPreview', [
            'resource' => $this->object,
            'properties' => $this->getProperties(),
        ]);

        $this->failedSuccessfully = true;

        if ($tvs = $this->object->getMany('TemplateVars', 'all')) {
            /** @var modTemplateVar $tv */
            foreach ($tvs as $tv) {
                $this->object->set($tv->get('name'), [
                    $tv->get('name'),
                    $this->object->get('tv' . $tv->get('id')),
                    $tv->get('display'),
                    $tv->get('display_params'),
                    $tv->get('type'),
                ]);
            }
        }
        $data = $this->object->toArray('', true);

        // Use a deterministic hash of the data so identical content
        // returns the same key. This allows the client-side auto-refresh
        // to skip reloading the iframe when nothing has actually changed.
        $key = substr(hash('sha256', json_encode($data)), 0, 24);
        $this->modx->cacheManager->set($this->object->get('id') . '/' . $key, $data, 3600, [
            xPDO::OPT_CACHE_KEY => 'magicpreview'
        ]);
        $this->previewHash = $key;

        // Save a draft of the current form state so the user can restore
        // it later, even after closing the browser or losing the session.
        // One draft per resource per user, stored in the magicpreview_drafts table.
        $saveDraft = (bool) $this->getProperty('save_draft', false);
        $createShare = (bool) $this->getProperty('create_share', false);
        if ($saveDraft || $createShare) {
            // Load the service ourselves: this processor may be invoked through
            // a third-party connector (e.g. VersionX) that hasn't loaded it.
            $corePath = $this->modx->getOption('magicpreview.core_path', null,
                $this->modx->getOption('core_path') . 'components/magicpreview/');
            /** @var MagicPreview $service */
            $service = $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

            if ($saveDraft) {
                $service->saveDraft(
                    $this->object->get('id'),
                    $this->modx->user->get('id'),
                    $data,
                    $this->object->get('context_key')
                );
            }

            // Create a shareable public link to this form state. The result
            // (url with the one-time token) is returned to the client by failure().
            if ($createShare) {
                $type = (string) $this->getProperty('share_type', MagicPreview::SHARE_TYPE_SNAPSHOT);

                // A live link renders the creator's current draft at view time,
                // so make sure one exists matching what was just shared.
                if ($type === MagicPreview::SHARE_TYPE_LIVE && !$saveDraft) {
                    $service->saveDraft(
                        $this->object->get('id'),
                        $this->modx->user->get('id'),
                        $data,
                        $this->object->get('context_key')
                    );
                }

                // Empty/missing TTL means "use the share_link_ttl system setting".
                $ttl = $this->getProperty('share_ttl');
                $ttl = ($ttl === null || $ttl === '') ? null : (int) $ttl;

                $this->shareResult = $service->createShare(
                    $this->object->get('id'),
                    $this->modx->user->get('id'),
                    $data,
                    $this->object->get('context_key'),
                    $type,
                    $ttl
                );
            }
        }

        return false;
    }

    public function failure($msg = '',$object = null) {
        if ($this->failedSuccessfully) {
            $response = [
                'preview_hash' => $this->previewHash,
            ];
            // Only present when create_share was requested; null means creation failed.
            if ((bool) $this->getProperty('create_share', false)) {
                $response['share'] = $this->shareResult;
            }
            return $this->success('', $response);
        }
        return parent::failure($msg, $object);
    }

    /**
     * Always prevent actual saving
     * @return bool
     */
    public function saveObject()
    {
        return false;
    }
}