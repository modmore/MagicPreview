<?php

/**
 * Frontend parser substitution used during preview page rendering.
 *
 * Overrides processTag() to intercept [[*fieldname]] resource field tags for
 * known wrappable core fields and TVs. Instead of returning plain text, it wraps
 * the rendered value in STX/ETX control-character markers:
 *
 *   \x02MMMP:pagetitle\x02rendered value\x03MMMP\x03
 *   \x02MMMP:tv42\x02rendered value\x03MMMP\x03
 *
 * These markers are safe to embed anywhere in HTML because control characters
 * (U+0002 / U+0003) never appear in valid HTML content. The OnWebPagePrerender
 * plugin handler then resolves them: markers inside <head>, <script>, <style>,
 * or HTML attribute contexts are stripped (leaving just the value); markers in
 * body content become <span data-magicpreview-field="pagetitle" style="display:contents">
 * elements that the frontend click handler can target.
 *
 * TV tags use "tv{id}" as the field identifier (e.g. "tv42") so the manager-side
 * scrollToField() can locate the input via [name="tv42"].
 *
 * Installed on $modx->parser during OnLoadWebDocument (preview requests only).
 * Restoration is not needed — the request ends after the page is rendered.
 */

require_once __DIR__ . '/MagicPreviewCoreParserTrait.php';

// MODX 3 branch — class defined only when \MODX\Revolution\modParser is available.
if (class_exists('\MODX\Revolution\modParser', false)) {
    if (!class_exists('MagicPreviewCoreParser', false)) {
        class MagicPreviewCoreParser extends \MODX\Revolution\modParser
        {
            use MagicPreviewCoreParserTrait;
        }
    }
} elseif (!class_exists('MagicPreviewCoreParser', false)) {
    // MODX 2 branch.
    class MagicPreviewCoreParser extends modParser
    {
        use MagicPreviewCoreParserTrait;
    }
}
