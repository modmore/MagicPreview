<?php

/**
 * This trait includes overridden update processor methods that both 2.x and 3.x processors need.
 */
trait PreviewTrait
{
    private $previewHash;
    private $failedSuccessfully = false;

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
                    $this->modx->getObject('modTemplateVar', $tv->get('id'))->renderOutput($this->object->get('id')),
                    $tv->get('display'),
                    $tv->get('display_params'),
                    $tv->get('type'),
                ]);
            }
        }
        $data = $this->object->toArray('', true);

        $key = bin2hex(random_bytes(12));
        $this->modx->cacheManager->set($this->object->get('id') . '/' . $key, $data, 3600, [
            xPDO::OPT_CACHE_KEY => 'magicpreview'
        ]);
        $this->previewHash = $key;

        return false;
    }

    public function failure($msg = '',$object = null) {
        if ($this->failedSuccessfully) {
            return $this->success('', [
                'preview_hash' => $this->previewHash,
            ]);
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
