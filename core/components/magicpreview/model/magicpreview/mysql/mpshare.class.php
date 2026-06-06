<?php
/**
 * MagicPreview
 *
 * @package magicpreview
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/mpshare.class.php');
class mpShare_mysql extends mpShare {}
