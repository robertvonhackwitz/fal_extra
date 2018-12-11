<?php
defined('TYPO3_MODE') || die();

$GLOBALS['TBE_STYLES']['skins']['fal_extra'] = [
    'name' => 'fal_extra',
    'stylesheetDirectories' => [
        'css' => 'EXT:fal_extra/Resources/Public/Css/'
    ]
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['facebook'] = \RVH\FalExtra\Helpers\FacebookVideoHelper::class;

$rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
$rendererRegistry->registerRendererClass(
    \RVH\FalExtra\Rendering\FacebookVideoRenderer::class
    );

$GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType']['facebook'] = 'video/facebook';
