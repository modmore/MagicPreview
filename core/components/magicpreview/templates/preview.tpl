<!doctype html>
<html lang="{$config.manager_language|escape}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{$_lang['magicpreview.preview']} {$resource.pagetitle|escape}</title>
    <link rel="stylesheet" href="{$mp_config.assetsUrl}preview.css?v={$mp_config.version}">
    {if $customPreviewCss}
        <link rel="stylesheet" href="{$customPreviewCss}?v={$mp_config.version}">
    {/if}
</head>
<body class="mmmp">
{if $resource['id'] > 0}
    <div class="mmmp-c-container">
        <div class="mmmp-c-container__inner">
            <h1 class="mmmp-c-title">
                <span class="mmmp-c-title__span">{$_lang['magicpreview.preview']}</span>
                <span class="mmmp-c-title__pagetitle">{$resource.pagetitle|escape}</span>
            </h1>
            <div class="mmmp-c-breakpoints__row">
                <div class="mmmp-c-breakpoints">
                    <input class="mmmp-c-breakpoints__input mmmp-js-breakpoint-input" type="radio" name="breakpoint" value="full" id="mmmp-breakpoint-full" checked>
                    <div class="mmmp-c-breakpoints__item">
                        <label class="mmmp-c-breakpoints__item-label" for="mmmp-breakpoint-full">{$_lang['magicpreview.bp_full']}</label>
                    </div>

                    <input class="mmmp-c-breakpoints__input mmmp-js-breakpoint-input" type="radio" name="breakpoint" value="desktop" id="mmmp-breakpoint-desktop">
                    <div class="mmmp-c-breakpoints__item">
                        <label class="mmmp-c-breakpoints__item-label" for="mmmp-breakpoint-desktop">{$_lang['magicpreview.bp_desktop']}</label>
                    </div>

                    <input class="mmmp-c-breakpoints__input mmmp-js-breakpoint-input" type="radio" name="breakpoint" value="tablet" id="mmmp-breakpoint-tablet">
                    <div class="mmmp-c-breakpoints__item">
                        <label class="mmmp-c-breakpoints__item-label" for="mmmp-breakpoint-tablet">{$_lang['magicpreview.bp_tablet']}</label>
                    </div>

                    <input class="mmmp-c-breakpoints__input mmmp-js-breakpoint-input" type="radio" name="breakpoint" value="mobile" id="mmmp-breakpoint-mobile">
                    <div class="mmmp-c-breakpoints__item">
                        <label class="mmmp-c-breakpoints__item-label" for="mmmp-breakpoint-mobile">{$_lang['magicpreview.bp_mobile']}</label>
                    </div>
                </div>
                <button class="mmmp-c-close" id="mmmp-c-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="mmmp-c-frame" id="mmmp-js-frame">
            <iframe class="mmmp-c-frame__inner" id="mmmp-js-frame-inner"></iframe>
        </div>
        <div class="mmmp-c-loading" id="mmmp-js-loading">
            <div class="mmmp-c-animation">
                <div></div><div></div><div></div><div></div>
            </div>
            <p class="mmmp-c-loading__text">{$_lang['magicpreview.preparing_preview']}</p>
        </div>
    </div>
    <script>
        (function() {
            var frame = document.getElementById('mmmp-js-frame-inner'),
                frameWrapper = document.getElementById('mmmp-js-frame'),
                loadingWrapper = document.getElementById('mmmp-js-loading'),
                baseFrameUrl = '{$baseFrameUrl|escape:javascript}',
                joiner = baseFrameUrl.indexOf('?') === -1 ? '?' : '&',
                closeBtn = document.getElementById('mmmp-c-close');
            window.onhashchange = refreshFrame;
            refreshFrame();

            function refreshFrame() {
                if (location.hash === '' || location.hash === '#loading') {
                    frameWrapper.style.display = 'none';
                    loadingWrapper.style.display = 'flex';
                    frame.src = '';
                }
                else {
                    loadingWrapper.style.display = 'none';
                    frameWrapper.style.display = 'flex';
                    frame.src = baseFrameUrl + joiner + 'show_preview=' + location.hash.substring(1);
                }
            }

            // Handle dynamic breakpoint sizing
            var breakpoints = document.querySelectorAll('.mmmp-js-breakpoint-input');
            breakpoints.forEach(function(bp) {
                bp.addEventListener('change', function () {
                    switch (this.value) {
                        case 'full':
                            frameWrapper.style.width = '100%';
                            break;

                        case 'desktop':
                            frameWrapper.style.width = '{$breakpointDesktop}';
                            break;

                        case 'tablet':
                            frameWrapper.style.width = '{$breakpointTablet}';
                            break;

                        case 'mobile':
                            frameWrapper.style.width = '{$breakpointMobile}';
                            break;
                    }
                });
            });

            // Close the preview tab/window on title click
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.close();
            });
        })()
    </script>
{else}
    <div class="mmmp-c-container">
        <div class="mmmp-c-container__inner">
            <h1 class="mmmp-c-title mmmp-c-title--error">
                Resource not found
            </h1>
        </div>
    </div>
{/if}
</body>
</html>
