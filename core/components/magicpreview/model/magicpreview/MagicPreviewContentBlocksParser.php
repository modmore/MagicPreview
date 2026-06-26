<?php

/**
 * Temporary parser substitution used during preview generation.
 *
 * MODX's default parseProperties() collapses any array event param that has a
 * 'value' key down to just that string — it's designed for element property sets
 * where each property is stored as {value: '...', type: '...', ...}. That same
 * logic mangles ContentBlocks_AfterParse's $phs, which is a plain associative
 * array that may include a 'value' key (textarea, code, richtext field values).
 *
 * Installed temporarily on $modx->parser inside PreviewTrait::fireBeforeSaveEvent()
 * so that event params received by the ContentBlocks_AfterParse plugin handler
 * remain as their original arrays rather than being collapsed to strings.
 * ContentBlocks' loadParser()/restoreParser() correctly preserves this instance
 * through its own cbParser swap cycle.
 */

// MODX 3 branch — class defined only when \MODX\Revolution\modParser is available.
if (class_exists('\MODX\Revolution\modParser', false)) {
    if (!class_exists('MagicPreviewContentBlocksParser', false)) {
        class MagicPreviewContentBlocksParser extends \MODX\Revolution\modParser
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
    }
} elseif (!class_exists('MagicPreviewContentBlocksParser', false)) {
    // MODX 2 branch.
    class MagicPreviewContentBlocksParser extends modParser
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
}
