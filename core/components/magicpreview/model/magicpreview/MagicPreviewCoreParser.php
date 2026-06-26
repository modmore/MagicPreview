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
 * plugin handler then resolves them: markers inside <head> or HTML attribute
 * contexts are stripped (leaving just the value); markers in body content become
 * <span data-magicpreview-field="pagetitle" style="display:contents"> elements
 * that the frontend click handler can target.
 *
 * TV tags use "tv{id}" as the field identifier (e.g. "tv42") so the manager-side
 * scrollToField() can locate the input via [name="tv42"].
 *
 * Installed on $modx->parser during OnLoadWebDocument (preview requests only).
 * Restoration is not needed — the request ends after the page is rendered.
 */

// MODX 3 branch — class defined only when \MODX\Revolution\modParser is available.
if (class_exists('\MODX\Revolution\modParser', false)) {
    if (!class_exists('MagicPreviewCoreParser', false)) {
        class MagicPreviewCoreParser extends \MODX\Revolution\modParser
        {
            /** @var string[] Core resource fields wrapped using their own name as the field identifier. */
            protected $wrappableFields = [
                'pagetitle', 'longtitle', 'description', 'menutitle', 'introtext',
            ];

            /** @var array<string, string|null> Cache of TV name → "tv{id}" (or null if not a TV). */
            protected $tvIdCache = [];

            /**
             * @param array|string $tag
             * @param bool $processUncacheable
             */
            public function processTag($tag, $processUncacheable = true)
            {
                $innerTag = is_array($tag) ? (isset($tag[1]) ? (string)$tag[1] : '') : (string)$tag;

                // Detect the tag token, accounting for the optional uncacheable ! prefix.
                $tagName = trim($innerTag);
                $tokenOffset = 0;
                if (substr($tagName, 0, 1) === '!') {
                    $tokenOffset = 1;
                }
                $token = substr($tagName, $tokenOffset, 1);

                if ($token !== '*') {
                    return parent::processTag($tag, $processUncacheable);
                }

                // Extract the bare field name: strip token, optional # modifier,
                // property string (after ?), and output modifiers (after :).
                $fieldName = substr($tagName, $tokenOffset + 1);
                if (substr($fieldName, 0, 1) === '#') {
                    $fieldName = substr($fieldName, 1);
                }
                $fieldName = explode('?', $fieldName)[0];
                $fieldName = explode(':', $fieldName)[0];
                $fieldName = trim($fieldName);

                // Determine the marker field identifier: use the field name directly
                // for known core fields, or look up "tv{id}" for TVs.
                if (in_array($fieldName, $this->wrappableFields, true)) {
                    $markerField = $fieldName;
                } else {
                    $markerField = $this->getTvFieldId($fieldName);
                    if ($markerField === null) {
                        return parent::processTag($tag, $processUncacheable);
                    }
                }

                $output = parent::processTag($tag, $processUncacheable);

                // Only wrap if the parent returned a processed string value, not the
                // original tag back (which starts with [[ when unresolved).
                if (!is_string($output) || $output === '' || substr($output, 0, 2) === '[[') {
                    return $output;
                }

                return "\x02MMMP:{$markerField}\x02{$output}\x03MMMP\x03";
            }

            /**
             * Returns "tv{id}" for a TV identified by name, or null if not a TV.
             * Results are cached on the instance to avoid repeated DB queries.
             *
             * @param string $name
             * @return string|null
             */
            private function getTvFieldId($name)
            {
                if (!array_key_exists($name, $this->tvIdCache)) {
                    $tv = $this->modx->getObject('modTemplateVar', ['name' => $name]);
                    $this->tvIdCache[$name] = $tv ? 'tv' . $tv->get('id') : null;
                }
                return $this->tvIdCache[$name];
            }
        }
    }
} elseif (!class_exists('MagicPreviewCoreParser', false)) {
    // MODX 2 branch.
    class MagicPreviewCoreParser extends modParser
    {
        /** @var string[] Core resource fields wrapped using their own name as the field identifier. */
        protected $wrappableFields = [
            'pagetitle', 'longtitle', 'description', 'menutitle', 'introtext',
        ];

        /** @var array<string, string|null> Cache of TV name → "tv{id}" (or null if not a TV). */
        protected $tvIdCache = [];

        /**
         * @param array|string $tag
         * @param bool $processUncacheable
         */
        public function processTag($tag, $processUncacheable = true)
        {
            $innerTag = is_array($tag) ? (isset($tag[1]) ? (string)$tag[1] : '') : (string)$tag;

            $tagName = trim($innerTag);
            $tokenOffset = 0;
            if (substr($tagName, 0, 1) === '!') {
                $tokenOffset = 1;
            }
            $token = substr($tagName, $tokenOffset, 1);

            if ($token !== '*') {
                return parent::processTag($tag, $processUncacheable);
            }

            $fieldName = substr($tagName, $tokenOffset + 1);
            if (substr($fieldName, 0, 1) === '#') {
                $fieldName = substr($fieldName, 1);
            }
            $fieldName = explode('?', $fieldName)[0];
            $fieldName = explode(':', $fieldName)[0];
            $fieldName = trim($fieldName);

            if (in_array($fieldName, $this->wrappableFields, true)) {
                $markerField = $fieldName;
            } else {
                $markerField = $this->getTvFieldId($fieldName);
                if ($markerField === null) {
                    return parent::processTag($tag, $processUncacheable);
                }
            }

            $output = parent::processTag($tag, $processUncacheable);

            if (!is_string($output) || $output === '' || substr($output, 0, 2) === '[[') {
                return $output;
            }

            return "\x02MMMP:{$markerField}\x02{$output}\x03MMMP\x03";
        }

        /**
         * @param string $name
         * @return string|null
         */
        private function getTvFieldId($name)
        {
            if (!array_key_exists($name, $this->tvIdCache)) {
                $tv = $this->modx->getObject('modTemplateVar', ['name' => $name]);
                $this->tvIdCache[$name] = $tv ? 'tv' . $tv->get('id') : null;
            }
            return $this->tvIdCache[$name];
        }
    }
}
