<?php
defined('TYPO3_MODE') || die();

/***************
 * Make the extension configuration accessible

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY] = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);
}

// Add BackendLayouts BackendLayouts for the BackendLayout DataProvider

if (!$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]['disablePageTsBackendLayouts']) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/PageTS/Mod/WebLayout/BackendLayouts.txt">');
}


**************
 * Reset extConf array to avoid errors

if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY] = serialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);
}
 */

$GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',facebook';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/browse_links.php']['browserRendering'][] = \RVH\FalExtra\Hooks\ElementBrowserController::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \RVH\FalExtra\Command\FileStorageIndexingCommandController::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \RVH\FalExtra\Command\FileStorageExtractionCommandController::class;

/***************
 * USER TSconfig
 */

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
    options.file_list.bigThumbnail {
        width = 128c
        height = 128c
    }
');