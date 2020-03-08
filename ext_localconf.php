<?php
defined('TYPO3_MODE') || die();

$rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
$rendererRegistry->registerRendererClass(\RVH\FalExtra\Rendering\FacebookRenderer::class);
unset($rendererRegistry);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['facebook'] = \RVH\FalExtra\Helpers\FacebookHelper::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType']['facebook'] = 'video/facebook';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',facebook';
