<?php
/**
 * MagicPreview — public share-link endpoint.
 *
 * Renders a shared draft preview for ANONYMOUS visitors. The token from the
 * URL is looked up by its sha256 hash; the creator's current draft is applied
 * to the in-memory resource, which is then rendered through MODX's normal
 * response pipeline.
 *
 * modRequest::getResource() — and with it the published/permission gate — is
 * never called, so the only thing an anonymous visitor can ever see through
 * this endpoint is the single resource that was deliberately shared. The
 * resource itself is never saved and is forced non-cacheable; the only
 * writes a request can cause are view tracking on the share row and, at
 * most once per draft, the lazy migration of a pre-1.7 cache-stored draft
 * into the drafts table.
 *
 * Only GET/HEAD are accepted, so no form handler or processor side effects
 * can be triggered through a share link.
 */

// This is the one anonymous entry point: never leak PHP errors (and the
// absolute paths in them) to visitors, whatever the host's INI says.
ini_set('display_errors', '0');

/**
 * Sends the endpoint's hardening headers: share links must never be
 * indexed, cached by shared caches, or leak their token via the referrer.
 *
 * @return void
 */
function mpShareHeaders(): void
{
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
}

/**
 * Sends a minimal, non-cacheable, non-indexable error page and stops.
 *
 * @param int $code
 * @param string $message
 * @return void
 */
function mpShareAbort(int $code, string $message): void
{
    http_response_code($code);
    mpShareHeaders();
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta name="robots" content="noindex, nofollow"><title>'
        . $safe . '</title></head><body><p>' . $safe . '</p></body></html>';
    exit;
}

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    mpShareAbort(405, 'Method not allowed.');
}

// Strictly validate the token shape before booting anything.
$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    mpShareAbort(404, 'Not found.');
}

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');

$corePath = $modx->getOption('magicpreview.core_path', null,
    $modx->getOption('core_path') . 'components/magicpreview/');
/** @var MagicPreview $service */
$service = $modx->getService('magicpreview', 'MagicPreview', $corePath . 'model/magicpreview/');
if (!($service instanceof MagicPreview)) {
    mpShareAbort(503, 'Service unavailable.');
}

// Localised "gone" message, shared by every 410 path below.
$goneMessage = $modx->lexicon('magicpreview.share_unavailable');

$share = $service->shares()->getValidShare($token);
if ($share === null) {
    mpShareAbort(410, $goneMessage);
}

// Render in the resource's own context (site_url, culture, context settings).
if ($share['context_key'] !== $modx->context->get('key')) {
    if (!$modx->switchContext($share['context_key'])) {
        mpShareAbort(410, $goneMessage);
    }
}

/** @var modResource|null $resource */
$resource = $modx->getObject('modResource', (int) $share['resource_id']);
if (!$resource) {
    mpShareAbort(410, $goneMessage);
}

// Apply the draft to the in-memory resource only (never saved, never
// cached — see MagicPreview::applyPreviewData), forced visible since the
// whole point is previewing unpublished work through the token gate.
$service->applyPreviewData($resource, $share['data']);
$resource->set('id', (int) $share['resource_id']);
$resource->set('published', true);
$resource->set('deleted', false);

$modx->resource = $resource;
$modx->resourceIdentifier = (int) $share['resource_id'];
$modx->resourceMethod = 'id';

// Every render gate has passed — count the view.
$service->shares()->recordView((int) $share['share_id']);

mpShareHeaders();
header('X-Frame-Options: SAMEORIGIN');

// Send the Content-Type ourselves and disable MODX's own header pass:
// modResponse::outputContent() would otherwise re-emit the ContentType's
// custom headers (which may include a Cache-Control directive overriding the
// no-store above) and, for content_dispo resources, an explicit
// "Cache-Control: public" — letting shared caches store the private draft.
$mimeType = 'text/html';
/** @var modContentType|null $resourceContentType */
$resourceContentType = $resource->getOne('ContentType');
if ($resourceContentType) {
    $mimeType = (string) $resourceContentType->get('mime_type');
}
$charset = (string) $modx->getOption('modx_charset', null, 'UTF-8');
header('Content-Type: ' . $mimeType . ($charset !== '' ? '; charset=' . $charset : ''));
$modx->setOption('set_header', false);

$modx->getRequest();
$modx->getResponse();
$modx->response->outputContent();
exit;
