<!doctype html>
<html lang="{$config.manager_language|escape}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{$_lang['magicpreview.preview']} {$resource.pagetitle|escape}</title>
    <link rel="stylesheet" href="{$mp_config.assetsUrl}preview.css">
</head>
<body>
{if $resource['id'] > 0}
    <div class="mp-container">
        <div class="mp-container__inner">
            <h1 class="mp-title">
                <span class="mp-title__span">{$_lang['magicpreview.preview']}</span>
                <a class="mp-title__link" href="{$config.manager_url}?a=resource/update&id={$resource.id}">
                    {$resource.pagetitle|escape}
                </a>
            </h1>

            <div class="mp-breakpoints">
                <input class="mp-breakpoints__input" type="radio" name="breakpoint" value="full" id="mp-breakpoint-full" checked>
                <div class="breakpoints__item mp-js-breakpoint-input">
                    <label class="breakpoints__item-label" for="breakpoint-full">{$_lang['magicpreview.bp_full']}</label>
                </div>

                <input class="mp-breakpoints__input" type="radio" name="breakpoint" value="desktop" id="mp-breakpoint-desktop">
                <div class="breakpoints__item mp-js-breakpoint-input">
                    <label class="breakpoints__item-label" for="breakpoint-desktop">{$_lang['magicpreview.bp_desktop']}</label>
                </div>

                <input class="mp-breakpoints__input" type="radio" name="breakpoint" value="tablet" id="mp-breakpoint-tablet">
                <div class="breakpoints__item mp-js-breakpoint-input">
                    <label class="breakpoints__item-label" for="breakpoint-tablet">{$_lang['magicpreview.bp_tablet']}</label>
                </div>

                <input class="mp-breakpoints__input" type="radio" name="breakpoint" value="mobile" id="mp-breakpoint-mobile">
                <div class="breakpoints__item mp-js-breakpoint-input">
                    <label class="breakpoints__item-label" for="breakpoint-mobile">{$_lang['magicpreview.bp_mobile']}</label>
                </div>
            </div>
        </div>
        <div class="mp-frame" id="mp-js-frame">
            <iframe class="mp-frame__inner" id="mp-js-frame-inner"></iframe>
        </div>
        <div class="mp-loading" id="mp-js-loading">
            <p class="mp-loading__text">{$_lang['magicpreview.preparing_preview']}</p>
        </div>
    </div>
    <script>
        (function() {
            var frame = document.getElementById('mp-js-frame-inner'),
                frameWrapper = document.getElementById('mp-js-frame'),
                loadingWrapper = document.getElementById('mp-js-loading'),
                baseFrameUrl = '{$baseFrameUrl|escape:javascript}',
                joiner = baseFrameUrl.indexOf('?') === -1 ? '?' : '&';
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
            var breakpoints = document.querySelectorAll('.mp-js-breakpoint-input');
            breakpoints.forEach(function(bp) {
                bp.addEventListener('change', function () {
                    switch (this.value) {
                        case 'full':
                            frameWrapper.style.width = '100%';
                            break;

                        case 'desktop':
                            frameWrapper.style.width = '1280px';
                            break;

                        case 'tablet':
                            frameWrapper.style.width = '768px';
                            break;

                        case 'mobile':
                            frameWrapper.style.width = '320px';
                            break;
                    }
                });
            });
        })()
    </script>
{else}
    <div class="mp-container">
        <div class="mp-container__inner">
            <h1 class="mp-title">
                Resource not found
            </h1>
        </div>
    </div>
{/if}
</body>
</html>
