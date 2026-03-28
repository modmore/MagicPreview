<?php

$output = '';

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_UPGRADE:
        $output .= <<<HTML
<div style="background: #fdf6ec; border: 1px solid #e6c47f; border-radius: 4px; padding: 1em; margin-bottom: 1.5em;">
    <p style="margin: 0 0 0.5em; font-weight: bold;">Note for users with custom templates or CSS</p>
    <p style="margin: 0;">This version of Magic Preview includes significant changes to the preview template and stylesheet. If you are using a custom preview template or custom CSS file (via the <code>magicpreview.custom_preview_tpl</code> or <code>magicpreview.custom_preview_css</code> system settings), please review and update your custom files after upgrading to ensure compatibility.</p>
</div>
HTML;
        // fall through to show donation message
    case xPDOTransport::ACTION_INSTALL:
        $output .= <<<HTML
<p style="margin-bottom: 1em;">Magic Preview brings a magical preview button to MODX, but its development and maintenance is a bit more down to earth. It takes real time and effort to bring you an extra like this, for free.</p>
<p style="margin-bottom: 1em;">Please consider a donation if Magic Preview makes you smile. Thank you!</p>
<p style="margin-bottom: 1em;">
    <a href="https://www.modmore.com/extras/magicpreview/donate/" target="_blank" rel="noopener" class="x-btn primary-button">
        Donate
    </a>
</p>
HTML;
        break;
}

return $output;
