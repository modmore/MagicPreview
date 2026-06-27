<?php

trait MagicPreviewContentBlocksParserTrait
{
    /** @param mixed $propSource */
    public function parseProperties($propSource)
    {
        if (is_array($propSource)) {
            $properties = [];
            foreach ($propSource as $propName => &$property) {
                $properties[$propName] = &$property;
            }
            return $properties;
        }
        return parent::parseProperties($propSource);
    }
}
