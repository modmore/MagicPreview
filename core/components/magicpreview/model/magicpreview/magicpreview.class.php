<?php
/**
 * MagicPreview
 *
 * Copyright 2018 by Mark Hamstra <support@modmore.com>
 *
 * @package magicpreview
 */

class MagicPreview
{
    /**
     * @var modX|null $modx
     */
    public $modx = null;
    /**
     * @var array
     */
    public $config = [];
    /**
     * @var bool
     */
    public $debug = false;

    const VERSION = '1.2.1-pl';


    /**
     * @param \modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx =& $modx;

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
}

