<?php

/**
 * Temporary parser substitution used while ContentBlocks HTML is regenerated
 * with click-to-field markers.
 *
 * MODX's default parseProperties() collapses any array event param that has a
 * 'value' key down to just that string — it's designed for element property sets
 * where each property is stored as {value: '...', type: '...', ...}. That same
 * logic mangles the ContentBlocks parse events' $phs, which is a plain associative
 * array that may include a 'value' key (textarea, code, richtext field values).
 *
 * Installed temporarily on $modx->parser inside MagicPreview::markContentBlocks(),
 * during a front-end preview render, so that event params received by the
 * ContentBlocks_BeforeParse / _AfterParse plugin handlers remain as their original
 * arrays rather than being collapsed to strings. It is removed again before MODX
 * parses the page, so the site's own parser always renders the page itself.
 * ContentBlocks' loadParser()/restoreParser() correctly preserves this instance
 * through its own cbParser swap cycle.
 */

require_once __DIR__ . '/MagicPreviewContentBlocksParserTrait.php';

// MODX 3 branch — class defined only when \MODX\Revolution\modParser is available.
if (class_exists('\MODX\Revolution\modParser', false)) {
    if (!class_exists('MagicPreviewContentBlocksParser', false)) {
        class MagicPreviewContentBlocksParser extends \MODX\Revolution\modParser
        {
            use MagicPreviewContentBlocksParserTrait;
        }
    }
} elseif (!class_exists('MagicPreviewContentBlocksParser', false)) {
    // MODX 2 branch.
    class MagicPreviewContentBlocksParser extends modParser
    {
        use MagicPreviewContentBlocksParserTrait;
    }
}
