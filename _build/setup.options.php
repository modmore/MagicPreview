<?php

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:

        return <<<HTML
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

return '';
