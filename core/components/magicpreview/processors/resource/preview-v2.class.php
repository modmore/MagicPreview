<?php

require 'PreviewTrait.php';
require MODX_PROCESSORS_PATH . 'resource/update.class.php';

class MagicPreviewPreviewProcessorV2 extends modResourceUpdateProcessor {

    use PreviewTrait;

    public static function getInstance(modX &$modx, $className, $properties = array()) {
        return new self($modx, $properties);
    }
}

return 'MagicPreviewPreviewProcessorV2';