<?php

require MODX_PROCESSORS_PATH . 'resource/update.class.php';

class MagicPreviewPreviewProcessor extends modResourceUpdateProcessor {
    private $previewHash;
    private $failedSuccessfully = false;

    public static function getInstance(modX &$modx,$className,$properties = array()) {
        return new self($modx,$properties);
    }

    public function fireBeforeSaveEvent() {
        $this->failedSuccessfully = true;

        if ($tvs = $this->object->getMany('TemplateVars', 'all')) {
            /** @var modTemplateVar $tv */
            foreach ($tvs as $tv) {
                $this->object->set($tv->get('name'), array(
                    $tv->get('name'),
                    $this->object->get('tv' . $tv->get('id')),//$tv->getValue($resource->get('id')),
                    $tv->get('display'),
                    $tv->get('display_params'),
                    $tv->get('type'),
                ));
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
            return $this->success('Failed successfully', [
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

return 'MagicPreviewPreviewProcessor';