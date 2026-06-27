<?php

trait MagicPreviewCoreParserTrait
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
