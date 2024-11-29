<?php
/**
 * The name of the controller is based on the path (home) and the
 * namespace (magicpreview). This home controller is the main client view.
 */
class MagicPreviewPreviewManagerController extends modExtraManagerController
{
    public $loadFooter = false;
    public $loadHeader = false;
    public $loadBaseJavascript = false;

    /** @var MagicPreview $magicpreview */
    public $magicpreview = null;

    /**
     * Initializes the main manager controller. In this case we set up the
     * MagicPreview class and add the required javascript on all controllers.
     */
    public function initialize()
    {
        /* Instantiate the MagicPreview class in the controller */
        $path = $this->modx->getOption('magicpreview.core_path', null, $this->modx->getOption('core_path') . 'components/magicpreview/') . 'model/magicpreview/';
        $this->magicpreview = $this->modx->getService('magicpreview', 'MagicPreview', $path);
        $this->setPlaceholder('mp_config', $this->magicpreview->config);
    }

    public function process(array $scriptProperties = array())
    {
        $resourceId = (int)$this->modx->getOption('resource', $scriptProperties, 0);
        $resource = $this->modx->getObject('modResource', ['id' => $resourceId]);
        if (!($resource instanceof modResource)) {
            return;
        }

        $this->setPlaceholder('resource', $resource->toArray());
        $this->setPlaceholder('baseFrameUrl', $this->modx->makeUrl($resourceId, '', '', 'full'));

        $this->setPlaceholder('breakpointDesktop', $this->modx->getOption("magicpreview.breakpoint_desktop", [], "1280px"));
        $this->setPlaceholder('breakpointTablet', $this->modx->getOption("magicpreview.breakpoint_tablet", [], "768px"));
        $this->setPlaceholder('breakpointMobile', $this->modx->getOption("magicpreview.breakpoint_mobile", [], "320px"));        
        
        $this->setPlaceholder('customPreviewCss', $this->modx->getOption("magicpreview.custom_preview_css", [], ""));
    }

    /**
     * Defines the lexicon topics to load in our controller.
     * @return array
     */
    public function getLanguageTopics(): array
    {
        return ['magicpreview:default'];
    }

    /**
     * The pagetitle to put in the <title> attribute.
     * @return null|string
     */
    public function getPageTitle(): ?string
    {
        return $this->modx->lexicon('magicpreview');
    }

    /**
     * Register all the needed javascript files. Using this method, it will automagically
     * combine and compress them if enabled in system settings.
     */
    public function loadCustomCssJs()
    {
        $this->addJavascript($this->magicpreview->config['jsUrl'].'mgr/magicpreview.class.js');
        $this->addCss($this->magicpreview->config['cssUrl'].'preview.css');
        $this->addHtml('<script type="text/javascript">
        Ext.onReady(function() {
            MagicPreviewConfig = ' . json_encode($this->magicpreview->config) . ';
        });
        </script>');

    }

    /**
     * The name for the template file to load.
     * @return string
     */
    public function getTemplateFile(): string
    {
        $custom = $this->modx->getOption('magicpreview.custom_preview_tpl');
        $tplPath = $this->magicpreview->config['templatesPath'];

        // If we're using a custom tpl, make sure it exists
        if ($custom) {
            $customTpl = $tplPath . $custom;
            if (file_exists($customTpl)) {
                return $customTpl;
            } else {
                $this->modx->log(MODX_LOG_LEVEL_ERROR, '[MagicPreview] Unable to load template file: ' . $customTpl);
            }
        }

        // Fallback to the default template
        return $tplPath . 'preview.tpl';
    }
}
