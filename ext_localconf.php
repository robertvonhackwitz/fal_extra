<?php
defined('TYPO3_MODE') || die();

call_user_func(
    function()
    {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fal_extra']);
        
        // Online Media Facebook Videos
        if($settings['falOnlineMediaFacebook'] == 1) {
            $rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
            $rendererRegistry->registerRendererClass(\RVH\FalExtra\Rendering\FacebookRenderer::class);
            unset($rendererRegistry);
            
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['facebook'] = \RVH\FalExtra\Helpers\FacebookHelper::class;
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType']['facebook'] = 'video/facebook';
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',facebook';
        }
        
        if($settings['elementBrowserThumbEnable'] == 1) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/browse_links.php']['browserRendering'][] = \RVH\FalExtra\Hooks\ElementBrowserController::class;
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
                options.file_list.bigThumbnail {
                    width = 128c
                    height = 128c
                }
            ');
            
        }
        
        unset($settings);
    }
);