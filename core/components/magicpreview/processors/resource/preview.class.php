<?php

use MODX\Revolution\Processors\Resource\Update;

require 'PreviewTrait.php';

class MagicPreviewPreviewProcessor extends Update {

    use PreviewTrait;

    public static function getInstance(modX $modx, $className, $properties = []) {
        return new self($modx, $properties);
    }
}

return 'MagicPreviewPreviewProcessor';