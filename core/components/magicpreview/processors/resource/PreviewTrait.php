<?php

require_once __DIR__ . '/ServiceTrait.php';

/**
 * This trait includes overridden update processor methods that both 2.x and 3.x processors need.
 */
trait PreviewTrait
{
    use ServiceTrait;

    private ?string $previewHash = null;
    private bool $failedSuccessfully = false;
    private ?array $shareResult = null;
    private ?string $draftSavedAt = null;

    public function fireBeforeSaveEvent()
    {
        $service = $this->getMagicPreviewService();

        // Invoke an event to allow other modules to prepare/modify the resource before preview.
        // The flag marks this render as a preview so listeners that fire during it (the
        // plugin's ContentBlocks_AfterFieldRender handler) add jump-to-field markers.
        $service->addFieldMarkers = true;
        try {
            $this->modx->invokeEvent('OnResourceMagicPreview', [
                'resource' => $this->object,
                'properties' => $this->getProperties(),
            ]);
        } finally {
            $service->addFieldMarkers = false;
        }

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

        // Cache the preview data under a deterministic content hash for the
        // ?show_preview= front-end render (see MagicPreview::cachePreviewData).
        $this->previewHash = $service->cachePreviewData((int) $this->object->get('id'), $data);

        // Save a draft of the current form state so the user can restore
        // it later, even after closing the browser or losing the session.
        // One draft per resource per user, stored in the magicpreview_drafts table.
        $saveDraft = (bool) $this->getProperty('save_draft', false);
        $createShare = (bool) $this->getProperty('create_share', false);
        if ($saveDraft || $createShare) {
            // Tracks whether the draft a share link would resolve against
            // actually exists — a failed draft write must not mint a link
            // that could only ever respond 410.
            $draftAvailable = true;
            if ($saveDraft) {
                $draftAvailable = $service->drafts()->saveDraft(
                    $this->object->get('id'),
                    $this->modx->user->get('id'),
                    $data,
                    $this->object->get('context_key')
                );
                if ($draftAvailable) {
                    // Server-formatted so the client banner shows the same
                    // timestamp (and timezone) it will see after a reload.
                    $this->draftSavedAt = date('Y-m-d H:i:s');
                }
            }

            // Create a shareable public link. The result (url with the
            // one-time token) is returned to the client by failure().
            if ($createShare) {
                // A link renders the creator's current draft at view time,
                // so make sure one exists — but never overwrite an existing
                // draft: the editor may not have restored it into the form,
                // and the submitted state would clobber their work
                // (keepExisting leaves any existing draft untouched).
                if (!$saveDraft) {
                    $draftAvailable = $service->drafts()->saveDraft(
                        $this->object->get('id'),
                        $this->modx->user->get('id'),
                        $data,
                        $this->object->get('context_key'),
                        null,
                        true
                    );
                }

                if ($draftAvailable) {
                    // Empty/missing TTL means "use the share_link_ttl system setting".
                    $ttl = $this->getProperty('share_ttl');
                    $ttl = ($ttl === null || $ttl === '') ? null : (int) $ttl;

                    $this->shareResult = $service->shares()->createShare(
                        $this->object->get('id'),
                        $this->modx->user->get('id'),
                        $this->object->get('context_key'),
                        $ttl,
                        (string) $this->getProperty('share_label', '')
                    );
                }
                // shareResult stays null when the draft write failed; the
                // client reports that as a failed link creation.
            }
        }

        return false;
    }

    public function failure($msg = '',$object = null) {
        if ($this->failedSuccessfully) {
            $response = [
                'preview_hash' => $this->previewHash,
            ];
            // Set when a draft was explicitly saved; the client banner uses
            // this server-side timestamp instead of the browser clock.
            if ($this->draftSavedAt !== null) {
                $response['draft_saved_at'] = $this->draftSavedAt;
            }
            // Only present when create_share was requested; null means creation
            // failed. The client only needs the link itself — the raw token is
            // already embedded in it, so don't ship the rest of the row.
            if ((bool) $this->getProperty('create_share', false)) {
                $response['share'] = $this->shareResult !== null
                    ? ['url' => $this->shareResult['url']]
                    : null;
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