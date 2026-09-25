<?php

require_once __DIR__ . '/magicpreviewdrafts.class.php';
require_once __DIR__ . '/magicpreviewshares.class.php';

/**
 * MagicPreview main service class: configuration, the shared preview cache
 * and access to the domain services (drafts() and shares()).
 *
 * @package magicpreview
 */
class MagicPreview
{
    public ?modX $modx = null;
    public array $config = [];
    public bool $debug = false;
    /**
     * @var bool True only while markContentBlocks() regenerates ContentBlocks
     * HTML for a front-end preview render. Checked by the plugin's ContentBlocks
     * parse handlers so click-to-field markers are generated for display only,
     * never on normal saves, content rebuilds or the stored preview snapshot.
     */
    public bool $addFieldMarkers = false;

    const VERSION = '1.8.0-pl';

    /**
     * Query parameter the preview panel adds to its iframe URL to ask for
     * click-to-field markers. Must match CLICK_TO_FIELD_PARAM in preview.js.
     */
    const CLICK_TO_FIELD_PARAM = 'mp_click_to_field';

    /**
     * Name of the placeholder a ContentBlocks field template uses to put the
     * click-to-field attributes on its own element: [[+mpClickToFieldAttributes]].
     */
    const CB_PLACEHOLDER_KEY = 'mpClickToFieldAttributes';

    /**
     * Attribute that numbers each ContentBlocks parse while markers are added,
     * so ContentBlocks_AfterParse can tell whether a parse's own template placed
     * the attributes. generateContentBlocksHtml() removes it before returning.
     */
    const CB_PARSE_ATTRIBUTE = 'data-magicpreview-parse';

    /**
     * The opening tag of the ContentBlocks wrapper as a stored snapshot can hold
     * it: the only click-to-field markup that was ever saved (by 1.7.x, without
     * the class). The class form is today's wrapper, matched in case it was stored.
     */
    private const STORED_WRAPPER_PATTERN = '/<div(?: class="mmmp-cb-field")? style="display:contents" data-magicpreview-field="\d+" data-magicpreview-idx="\d+">/';

    private ?MagicPreviewDrafts $drafts = null;
    private ?MagicPreviewShares $shares = null;
    private bool $garbageCollected = false;
    /**
     * @var object|null The resource applyManagerPreview() put a manager preview
     * snapshot on, which is also what makes its render non-cacheable.
     * isClickToFieldActive() requires it to be the resource being rendered.
     */
    private ?object $managerPreviewResource = null;
    /**
     * @var int Number of the ContentBlocks parse most recently started by
     * startCbParse().
     */
    private int $cbParse = 0;

    /**
     * @param \modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;

        $corePath = $this->modx->getOption('magicpreview.core_path', $config,
            $this->modx->getOption('core_path') . 'components/magicpreview/');
        $assetsUrl = $this->modx->getOption('magicpreview.assets_url', $config,
            $this->modx->getOption('assets_url') . 'components/magicpreview/');
        $assetsPath = $this->modx->getOption('magicpreview.assets_path', $config,
            $this->modx->getOption('assets_path') . 'components/magicpreview/');
        $this->config = array_merge([
            'basePath' => $corePath,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'processorsPath' => $corePath . 'processors/',
            'elementsPath' => $corePath . 'elements/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl . 'connector.php',
            'version' => self::VERSION,
        ], $config);

        $modelPath = $this->config['modelPath'];
        $this->modx->addPackage('magicpreview', $modelPath);
        $this->modx->lexicon->load('magicpreview:default');
    }

    /**
     * Returns the drafts service (per-user resource drafts).
     *
     * @return MagicPreviewDrafts
     */
    public function drafts(): MagicPreviewDrafts
    {
        if ($this->drafts === null) {
            $this->drafts = new MagicPreviewDrafts($this);
        }
        return $this->drafts;
    }

    /**
     * Returns the shares service (public share links to drafts).
     *
     * @return MagicPreviewShares
     */
    public function shares(): MagicPreviewShares
    {
        if ($this->shares === null) {
            $this->shares = new MagicPreviewShares($this);
        }
        return $this->shares;
    }

    /**
     * The click-to-field attributes for one ContentBlocks field instance, as the
     * automatic wrapper carries them. The placeholder's value (startCbParse())
     * is built from the same string.
     *
     * @param int $fieldId ContentBlocks field id
     * @param int $fieldTypeIdx 0-based instance number of that field on the page
     * @return string
     */
    public static function cbFieldAttributes(int $fieldId, int $fieldTypeIdx): string
    {
        return 'data-magicpreview-field="' . $fieldId . '" data-magicpreview-idx="' . $fieldTypeIdx . '"';
    }

    /**
     * Starts a new numbered ContentBlocks parse and returns the value of
     * [[+mpClickToFieldAttributes]] for it: the field's attributes plus the
     * parse number. Called by ContentBlocks_BeforeParse while markers are added.
     *
     * ContentBlocks never nests parse() calls - a list, repeater or layout field
     * renders its children before its own parse() starts - so the next
     * ContentBlocks_AfterParse belongs to this parse. Parses that return early
     * (@CHUNK, @JSON, @PDO_FILE) never reach AfterParse, and the next call here
     * simply replaces their number.
     *
     * @param int $fieldId ContentBlocks field id
     * @param int $fieldTypeIdx 0-based instance number of that field on the page
     * @return string
     */
    public function startCbParse(int $fieldId, int $fieldTypeIdx): string
    {
        $this->cbParse++;
        return self::cbFieldAttributes($fieldId, $fieldTypeIdx)
            . ' ' . self::CB_PARSE_ATTRIBUTE . '="' . $this->cbParse . '"';
    }

    /**
     * Whether the output of the ContentBlocks parse that just finished carries
     * the attributes its own template placed, inline or from a file.
     *
     * Only this parse's number counts. The same field's attributes can also
     * arrive from an earlier parse - a nested list's sub-list is parsed with the
     * list's own field and index - and that must not cost the field its wrapper.
     *
     * @param string $output The parse output
     * @return bool
     */
    public function cbParsePlacedAttributes(string $output): bool
    {
        return strpos($output, self::CB_PARSE_ATTRIBUTE . '="' . $this->cbParse . '"') !== false;
    }

    /**
     * True only when click-to-field markup should be emitted during a front-end
     * preview render: the setting is on, this request is a preview whose
     * snapshot has been applied, it was loaded by the preview panel, and the
     * visitor holds a manager session.
     *
     * The preview and manager-session conditions keep attributes off live pages
     * and public share links - assets/components/magicpreview/share.php renders
     * without show_preview and without a manager session. The panel condition
     * keeps them off full-page previews (New Window mode, a draft's View link),
     * where there is no editor for a click to scroll.
     *
     * The snapshot condition is the one that keeps them out of the resource
     * cache. A show_preview URL whose hash is unknown or expired renders the
     * live resource, and MODX writes live renders to the cache every visitor is
     * served from. So nothing is marked unless the resource being rendered is
     * the very object applyManagerPreview() put the snapshot on. A request can
     * render more than one resource: sendErrorPage(), sendUnauthorizedPage(),
     * sendForward() and symlinks swap in another resource object mid-render,
     * and that page is live, not the preview.
     *
     * NOT the test to use inside the ContentBlocks parse events: those check
     * $addFieldMarkers, which markContentBlocks() holds for exactly the
     * regeneration it performs, whereas this is true for the whole request.
     *
     * @return bool
     */
    public function isClickToFieldActive(): bool
    {
        if (!$this->modx->getOption('magicpreview.click_to_field', null, false)) {
            return false;
        }
        if (!array_key_exists('show_preview', $_GET)) {
            return false;
        }
        if ($this->managerPreviewResource === null || $this->modx->resource !== $this->managerPreviewResource) {
            return false;
        }
        if (!array_key_exists(self::CLICK_TO_FIELD_PARAM, $_GET)) {
            return false;
        }
        if (!$this->modx->user) {
            return false;
        }
        if (!$this->modx->user->hasSessionContext('mgr')) {
            return false;
        }
        return true;
    }

    /**
     * The single definition of expiry for drafts and share links: a row is
     * expired iff it has an expiry (expires_at > 0) that has passed.
     * 0 always means "never expires".
     *
     * @param int $expiresAt Unix timestamp; 0 = never expires.
     * @return bool
     */
    public static function isExpired(int $expiresAt): bool
    {
        return $expiresAt > 0 && $expiresAt < time();
    }

    /**
     * The xPDO criteria group matching non-expired rows — the query form of
     * isExpired()'s negation, for listing/counting active rows.
     *
     * @return array
     */
    public static function notExpiredCriteria(): array
    {
        return [
            'expires_at' => 0,
            'OR:expires_at:>' => time(),
        ];
    }

    /**
     * The xPDO criteria matching expired rows — the query form of
     * isExpired(), for garbage collection.
     *
     * @return array
     */
    public static function expiredCriteria(): array
    {
        return [
            'expires_at:>' => 0,
            'expires_at:<' => time(),
        ];
    }

    /**
     * Deletes expired drafts and share links. Cheap thanks to the expires_at
     * indexes; called opportunistically on writes since there's no cron by default.
     * Runs at most once per request — a single request can hit several
     * write paths (e.g. save draft + create share) and one sweep covers all.
     *
     * @return int The number of rows removed.
     */
    public function garbageCollectExpired(): int
    {
        if ($this->garbageCollected) {
            return 0;
        }
        $this->garbageCollected = true;
        return $this->drafts()->garbageCollect() + $this->shares()->garbageCollect();
    }

    /**
     * Discards a user's draft, coordinating its dependent share links: live
     * links resolve the draft at view time, so they must be removed with it.
     * Unless $removeShares is set, an existing link blocks the discard so
     * the caller can ask the user to confirm first.
     *
     * @param int $resourceId
     * @param int $userId
     * @param bool $removeShares Confirmed: also remove the user's share links.
     * @return array ['discarded' => bool, 'live_shares' => int]
     */
    public function discardDraft(int $resourceId, int $userId, bool $removeShares = false): array
    {
        $liveShares = $this->shares()->countLiveShares($resourceId, $userId);
        if ($liveShares > 0 && !$removeShares) {
            return ['discarded' => false, 'live_shares' => $liveShares];
        }

        if ($liveShares > 0) {
            $this->shares()->removeLiveShares($resourceId, $userId);
        }
        $this->drafts()->deleteDraft($resourceId, $userId);

        return ['discarded' => true, 'live_shares' => 0];
    }

    /**
     * Removes a user's draft unless live share links still depend on it —
     * used after a restore, which consumes the draft only when nothing
     * public resolves against it anymore.
     *
     * @param int $resourceId
     * @param int $userId
     * @return void
     */
    public function deleteDraftIfUnshared(int $resourceId, int $userId): void
    {
        if ($this->shares()->countLiveShares($resourceId, $userId) === 0) {
            $this->drafts()->deleteDraft($resourceId, $userId);
        }
    }

    /**
     * Applies cached preview / draft data to an in-memory resource for an
     * overridden render: nothing is saved, cacheable=false keeps the result
     * out of the real resource cache, and the in-memory element cache is
     * wiped so placeholders don't show the live cached values. Shared by the
     * manager preview render (plugin OnLoadWebDocument) and the public share
     * endpoint (share.php).
     *
     * @param modResource $resource
     * @param array $data The resource snapshot (incl. flattened TVs).
     * @return void
     */
    public function applyPreviewData($resource, array $data): void
    {
        $resource->fromArray($data, '', true, true);
        $resource->set('cacheable', false);
        $resource->setProcessed(false);
        $this->modx->elementCache = null;
    }

    /**
     * Applies a manager preview snapshot to the page being rendered and records
     * which resource object it went on, which isClickToFieldActive() requires.
     * Only the plugin's OnLoadWebDocument handler calls this; the public share
     * endpoint uses applyPreviewData() and so never qualifies for click-to-field.
     *
     * @param modResource|\MODX\Revolution\modResource $resource
     * @param array $data The preview snapshot (incl. flattened TVs).
     * @return void
     */
    public function applyManagerPreview($resource, array $data): void
    {
        $this->applyPreviewData($resource, $data);
        $this->managerPreviewResource = $resource;
    }

    /**
     * Regenerates a resource's ContentBlocks HTML from its JSON with the
     * click-to-field markers switched on. Called only from the front-end preview
     * render, once isClickToFieldActive() has passed.
     *
     * Markers are deliberately never generated when the preview snapshot is
     * taken: that snapshot is also stored as the draft that public share links
     * render. They are added here instead, in the one request that displays them.
     *
     * The parser swap is confined to ContentBlocks' own generation and undone
     * before returning. This runs from OnLoadWebDocument, which fires before
     * MODX parses the page, so the page itself is always rendered by the site's
     * own parser - pdoTools/Fenom included (#55).
     *
     * @param modResource|\MODX\Revolution\modResource $resource
     * @param string $cbJson The ContentBlocks JSON from the preview snapshot
     * @return bool Whether the content was regenerated. On false the snapshot's
     *              unmarked content is left in place.
     */
    public function markContentBlocks($resource, string $cbJson): bool
    {
        $html = $this->generateContentBlocksHtml($resource, $cbJson, true);
        if ($html === null) {
            return false;
        }
        $resource->setContent($html);
        return true;
    }

    /**
     * Removes click-to-field markup from a stored snapshot's content.
     *
     * Drafts saved by 1.7.x with click_to_field on hold the ContentBlocks
     * wrapper (<div style="display:contents" data-magicpreview-field="…" …>) in
     * their content, and share links render drafts publicly. Only that exact
     * wrapper counts: other mentions of the attributes are the author's content.
     *
     * Where possible the ContentBlocks HTML is regenerated unmarked from the
     * draft's own JSON, which also drops the wrapper elements. Any wrappers that
     * remain - no JSON, ContentBlocks unavailable, or generation failed - lose
     * their attributes and become inert <div style="display:contents"> elements,
     * so the result never matches the check again.
     *
     * @param array $data A draft or preview snapshot, as built by the preview processor
     * @param int $resourceId
     * @return array $data, with 'content' cleaned if it held any markup
     */
    public function removeClickToFieldMarkup(array $data, int $resourceId): array
    {
        $content = (string)($data['content'] ?? '');
        if (!preg_match(self::STORED_WRAPPER_PATTERN, $content)) {
            return $data;
        }
        if (!empty($data['contentblocks'])) {
            $resource = $this->modx->getObject('modResource', $resourceId);
            if ($resource) {
                $resource->fromArray($data, '', true, true);
                $html = $this->generateContentBlocksHtml($resource, (string)$data['contentblocks'], false);
                if ($html !== null) {
                    $content = $html;
                }
            }
        }
        $inert = preg_replace(self::STORED_WRAPPER_PATTERN, '<div style="display:contents">', $content);
        if ($inert !== null) {
            $content = $inert;
        }
        $data['content'] = $content;
        return $data;
    }

    /**
     * Generates a resource's ContentBlocks HTML from its JSON, optionally with
     * the click-to-field markers switched on.
     *
     * $modx->resource points at $resource for the duration, as it does in
     * ContentBlocks' own preview listener. It is restored afterwards, along with
     * the parser, the element cache, the click-to-field placeholder and
     * ContentBlocks' instance counters.
     *
     * @param modResource|\MODX\Revolution\modResource $resource
     * @param string $cbJson ContentBlocks JSON, as stored in a preview snapshot or draft
     * @param bool $withMarkers Swap in the substitute parser and open the marking window
     * @return string|null The HTML, or null when ContentBlocks is unavailable, the
     *                     JSON is invalid, or generation failed
     */
    private function generateContentBlocksHtml($resource, string $cbJson, bool $withMarkers): ?string
    {
        $cbContent = $this->modx->fromJSON($cbJson);
        if (!is_array($cbContent)) {
            return null;
        }
        $cbCorePath = $this->modx->getOption('contentblocks.core_path', null,
            $this->modx->getOption('core_path') . 'components/contentblocks/');
        // A draft can outlive an uninstalled ContentBlocks, and getService() logs
        // an error for a class it cannot find.
        if (!file_exists($cbCorePath . 'model/contentblocks/contentblocks.class.php')) {
            return null;
        }
        $contentBlocks = $this->modx->getService('contentblocks', 'ContentBlocks', $cbCorePath . 'model/contentblocks/');
        if (!$contentBlocks) {
            return null;
        }
        if ($withMarkers && !class_exists('MagicPreviewContentBlocksParser', false)) {
            require_once __DIR__ . '/MagicPreviewContentBlocksParser.class.php';
        }

        $this->modx->getParser();
        $savedParser = $this->modx->parser;
        $savedElementCache = $this->modx->elementCache;
        $savedResource = $this->modx->resource;
        $savedResourceIdentifier = $this->modx->resourceIdentifier;
        $savedPlaceholder = $this->modx->getPlaceholder(self::CB_PLACEHOLDER_KEY);
        // ContentBlocks numbers field instances (field_type_idx, unique_idx) with
        // counters it never resets, so they carry on from any generation earlier
        // in this request. Start from zero, as a save does, so each click target's
        // idx matches its field's position in the editor.
        $savedCounters = $this->swapContentBlocksCounters($contentBlocks, ['fieldTypeIdx' => [], 'uniqueIdx' => 0]);
        $this->modx->resource = $resource;
        $this->modx->resourceIdentifier = $resource->get('id');
        // An empty element cache makes every ContentBlocks parse event run fresh
        // rather than returning a cached output.
        $this->modx->elementCache = [];
        if ($withMarkers) {
            // The substitute parser keeps $phs intact for the ContentBlocks parse
            // events (see MagicPreviewContentBlocksParser).
            $this->modx->parser = new MagicPreviewContentBlocksParser($this->modx);
            $this->addFieldMarkers = true;
        }
        try {
            $html = (string)$contentBlocks->generateHtml($cbContent);
            // generateHtml() catches its own exceptions and returns a one-paragraph
            // error message in place of the content. Never let that stand in for
            // it. The whole output must match: a field template of the author's
            // may well start with the same markup.
            if (preg_match('/^<p class="error">[^<]*<\/p>$/', trim($html))) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[MagicPreview] ContentBlocks returned an error instead of the content of resource '
                    . $resource->get('id') . '.');
                return null;
            }
            if ($withMarkers) {
                // Parse numbers only serve ContentBlocks_AfterParse; see startCbParse().
                $html = (string)preg_replace('/ ' . self::CB_PARSE_ATTRIBUTE . '="\d+"/', '', $html);
            }
            return $html;
        } catch (Throwable $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[MagicPreview] Could not generate the ContentBlocks content of resource '
                . $resource->get('id') . ': ' . $e->getMessage());
            return null;
        } finally {
            $this->addFieldMarkers = false;
            $this->swapContentBlocksCounters($contentBlocks, $savedCounters);
            $this->modx->elementCache = $savedElementCache;
            $this->modx->parser = $savedParser;
            $this->modx->resource = $savedResource;
            $this->modx->resourceIdentifier = $savedResourceIdentifier;
            // ContentBlocks_BeforeParse publishes each field's attributes as a
            // placeholder for file templates. Put back what was there before, so
            // they cannot reach the page this request goes on to render.
            if ($savedPlaceholder === null) {
                $this->modx->unsetPlaceholder(self::CB_PLACEHOLDER_KEY);
            } else {
                $this->modx->setPlaceholder(self::CB_PLACEHOLDER_KEY, $savedPlaceholder);
            }
        }
    }

    /**
     * Sets ContentBlocks' instance counters and returns their previous values,
     * so the same call puts them back. Counters this ContentBlocks version does
     * not have are skipped (fieldTypeIdx arrived in ContentBlocks 1.14).
     *
     * @param object $contentBlocks The ContentBlocks service
     * @param array $counters Property name => value to set
     * @return array Property name => previous value
     */
    private function swapContentBlocksCounters($contentBlocks, array $counters): array
    {
        $previous = [];
        foreach ($counters as $name => $value) {
            if (!property_exists($contentBlocks, $name)) {
                continue;
            }
            $previous[$name] = $contentBlocks->$name;
            $contentBlocks->$name = $value;
        }
        return $previous;
    }

    /**
     * Writes preview data into the short-lived preview cache and returns the
     * cache hash, consumed by the front end as ?show_preview=<hash> (manager
     * sessions only; see the OnLoadWebDocument handler in the plugin).
     *
     * The hash is a deterministic digest of the data, so identical content
     * always yields the same key — this lets the client-side auto-refresh
     * skip reloading the iframe when nothing has actually changed.
     *
     * @param int $resourceId
     * @param array $data The resource snapshot (incl. flattened TVs).
     * @return string|null The 24-character hash, or null if encoding failed.
     */
    public function cachePreviewData(int $resourceId, array $data): ?string
    {
        $encoded = json_encode($data);
        if (!is_string($encoded)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not encode preview data for resource ' . $resourceId);
            return null;
        }

        $hash = substr(hash('sha256', $encoded), 0, 24);
        $this->modx->cacheManager->set($resourceId . '/' . $hash, $data, 3600, [
            xPDO::OPT_CACHE_KEY => 'magicpreview',
        ]);
        return $hash;
    }
}
