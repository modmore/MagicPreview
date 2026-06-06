<?php
/**
 * MagicPreview
 *
 * @package magicpreview
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/mpdraft.class.php');
class mpDraft_mysql extends mpDraft {}
