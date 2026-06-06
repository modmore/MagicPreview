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

        // Load the service ourselves: this processor may be invoked through
        // a third-party connector (e.g. VersionX) that hasn't loaded it.
        $corePath = $this->modx->getOption('magicpreview.core_path', null,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        /** @var MagicPreview $service */
        $service = $this->modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');

        // Cache the preview data under a deterministic content hash for the
        // ?show_preview= front-end render (see MagicPreview::cachePreviewData).
        $this->previewHash = $service->cachePreviewData((int) $this->object->get('id'), $data);

        // Save a draft of the current form state so the user can restore
        // it later, even after closing the browser or losing the session.
        // One draft per resource per user, stored in the magicpreview_drafts table.
        $saveDraft = (bool) $this->getProperty('save_draft', false);
        $createShare = (bool) $this->getProperty('create_share', false);
        if ($saveDraft || $createShare) {
            if ($saveDraft) {
                $service->saveDraft(
                    $this->object->get('id'),
                    $this->modx->user->get('id'),
                    $data,
                    $this->object->get('context_key')
                );
            }

            // Create a shareable public link. The result (url with the
            // one-time token) is returned to the client by failure().
            if ($createShare) {
                // A link renders the creator's current draft at view time,
                // so make sure one exists — but never overwrite an existing
                // draft: the editor may not have restored it into the form,
                // and the submitted state would clobber their work.
                if (!$saveDraft
                    && $service->getDraft((int) $this->object->get('id'), (int) $this->modx->user->get('id')) === null
                ) {
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
                    $this->object->get('context_key'),
                    $ttl,
                    (string) $this->getProperty('share_label', '')
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