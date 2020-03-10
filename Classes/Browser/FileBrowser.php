<?php
namespace RVH\FalExtra\Browser;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Tree\View\ElementBrowserFolderTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Search\FileSearchDemand;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Recordlist\Tree\View\LinkParameterProviderInterface;
use RVH\FalExtra\View\FolderUtilityRenderer;
use TYPO3\CMS\Recordlist\Browser\AbstractElementBrowser;
use TYPO3\CMS\Recordlist\Browser\ElementBrowserInterface;

/**
 * Browser for files
 * @internal This class is a specific LinkBrowser implementation and is not part of the TYPO3's Core API.
 */
class FileBrowser extends AbstractElementBrowser implements ElementBrowserInterface, LinkParameterProviderInterface
{
    /**
     * When you click a folder name/expand icon to see the content of a certain file folder,
     * this value will contain the path of the expanded file folder.
     * If the value is NOT set, then it will be restored from the module session data.
     * Example value: "/www/htdocs/typo3/32/3dsplm/fileadmin/css/"
     *
     * @var string|null
     */
    protected $expandFolder;
    
    /**
     * @var Folder
     */
    protected $selectedFolder;
    
    /**
     * Holds information about files
     *
     * @var mixed[][]
     */
    protected $elements = [];
    
    /**
     * @var string
     */
    protected $searchWord;
    
    /**
     * @var FileRepository
     */
    protected $fileRepository;
    
    /**
     * @var array
     */
    protected $thumbnailConfiguration = [];
    
    /**
     * @var int
     */
    protected $elementBrowserThumbEnable = 0;
    
    /**
     * @var int
     */
    protected $elementBrowserMaxTitleLen = 0;
    
    /**
     * @var int
     */
    protected $iLimit = 30;
    
    /**
     * @var int
     */
    protected $totalItems = 0;
    
    /**
     * @var int
     */
    protected $firstElementNumber = 0;
    
    /**
     *
     * @var integer
     */
    protected $elementBrowserCols = 3;
    
    /**
     * @var integer
     */
    protected $elementBrowserPageBrowserEnable = 0;
    
    /**
     * @var string
     */
    protected $sortBy = 'tstamp:DESC';
    
    
    /**
     * Loads additional JavaScript
     */
    protected function initialize()
    {
        parent::initialize();
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Recordlist/BrowseFiles');
        $this->pageRenderer->addCssFile($GLOBALS['TBE_STYLES']['stylesheets']['fal_extra']);
        $this->fileRepository = GeneralUtility::makeInstance(FileRepository::class);

        $extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fal_extra']);
        $this->elementBrowserThumbEnable = (int)$extensionConfiguration['elementBrowserThumbEnable'];
        $this->elementBrowserPageBrowserEnable = (int)$extensionConfiguration['elementBrowserPageBrowserEnable'];
        $this->elementBrowserMaxTitleLen = (int)$extensionConfiguration['elementBrowserMaxTitleLen'];

        $thumbnailConfig = $this->getBackendUser()->getTSConfig()['options.']['file_list.']['bigThumbnail.'] ?? [];
        
        if (isset($thumbnailConfig['width'])) {
            $this->thumbnailConfiguration['width'] = $thumbnailConfig['width'];
        }
        
        if (isset($thumbnailConfig['height'])) {
            $this->thumbnailConfiguration['height'] = $thumbnailConfig['height'];
        }
 
    }
    
    /**
     * Checks additional GET/POST requests
     */
    protected function initVariables()
    {
        parent::initVariables();
        $this->expandFolder = GeneralUtility::_GP('expandFolder');
        $this->searchWord = (string)GeneralUtility::_GP('searchWord');
        $this->firstElementNumber = (int)GeneralUtility::_GP('pointer');
        
        // _RVH: new: 2020-02-26
        if((string)GeneralUtility::_GP('sortBy')!='') {
            $this->sortBy = (string)GeneralUtility::_GP('sortBy');
            $GLOBALS['BE_USER']->uc['moduleData']['file_list']['sortBy'] = (string)GeneralUtility::_GP('sortBy');
        } elseif ($GLOBALS['BE_USER']->uc['moduleData']['file_list']['sortBy']!='') {
            $this->sortBy = $GLOBALS['BE_USER']->uc['moduleData']['file_list']['sortBy'];
        } else {
            $GLOBALS['BE_USER']->uc['moduleData']['file_list']['sortBy'] = $this->sortBy;
        }
        
        if((int)GeneralUtility::_GP('jumpLimit')>0) {
            $this->iLimit = (int)GeneralUtility::_GP('jumpLimit');
            $GLOBALS['BE_USER']->uc['moduleData']['file_list']['jumpLimit'] = (int)GeneralUtility::_GP('jumpLimit');
        } elseif ($GLOBALS['BE_USER']->uc['moduleData']['file_list']['jumpLimit']>0) {
            $this->iLimit = $GLOBALS['BE_USER']->uc['moduleData']['file_list']['jumpLimit'];
        } else {
            $GLOBALS['BE_USER']->uc['moduleData']['file_list']['jumpLimit'] = $this->iLimit;
        }
        
    }
    
    /**
     * Session data for this class can be set from outside with this method.
     *
     * @param mixed[] $data Session data array
     * @return array[] Session data and boolean which indicates that data needs to be stored in session because it's changed
     */
    public function processSessionData($data)
    {
        
        if ($this->expandFolder !== null) {
            $data['expandFolder'] = $this->expandFolder;
            $store = true;
        } else {
            $this->expandFolder = $data['expandFolder'];
            $store = false;
        }

        return [$data, $store];
    }
    
    /**
     * @return string HTML content
     */
    public function render()
    {
        $backendUser = $this->getBackendUser();
        
        // The key number 3 of the bparams contains the "allowed" string. Disallowed is not passed to
        // the element browser at all but only filtered out in DataHandler afterwards
        $allowedFileExtensions = GeneralUtility::trimExplode(',', explode('|', $this->bparams)[3], true);
        if (!empty($allowedFileExtensions) && $allowedFileExtensions[0] !== 'sys_file' && $allowedFileExtensions[0] !== '*') {
            // Create new filter object
            $filterObject = GeneralUtility::makeInstance(FileExtensionFilter::class);
            $filterObject->setAllowedFileExtensions($allowedFileExtensions);
            // Set file extension filters on all storages
            $storages = $backendUser->getFileStorages();
            /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
            foreach ($storages as $storage) {
                $storage->addFileAndFolderNameFilter([$filterObject, 'filterFileList']);
            }
        }
        if ($this->expandFolder) {
            $fileOrFolderObject = null;
            
            // Try to fetch the folder the user had open the last time he browsed files
            // Fallback to the default folder in case the last used folder is not existing
            try {
                $fileOrFolderObject = ResourceFactory::getInstance()->retrieveFileOrFolderObject($this->expandFolder);
            } catch (Exception $accessException) {
                // We're just catching the exception here, nothing to be done if folder does not exist or is not accessible.
            } catch (\InvalidArgumentException $driverMissingExecption) {
                // We're just catching the exception here, nothing to be done if the driver does not exist anymore.
            }
            
            if ($fileOrFolderObject instanceof Folder) {
                // It's a folder
                $this->selectedFolder = $fileOrFolderObject;
            } elseif ($fileOrFolderObject instanceof FileInterface) {
                // It's a file
                $this->selectedFolder = $fileOrFolderObject->getParentFolder();
            }
        }
        // Or get the user's default upload folder
        if (!$this->selectedFolder) {
            try {
                [, $pid, $table,, $field] = explode('-', explode('|', $this->bparams)[4]);
                $this->selectedFolder = $backendUser->getDefaultUploadFolder($pid, $table, $field);
            } catch (\Exception $e) {
                // The configured default user folder does not exist
            }
        }
        // Build the file upload and folder creation form
        $uploadForm = '';
        $createFolder = '';
        if ($this->selectedFolder) {
            $folderUtilityRenderer = GeneralUtility::makeInstance(FolderUtilityRenderer::class, $this);
            $uploadForm = $folderUtilityRenderer->uploadForm($this->selectedFolder, $allowedFileExtensions);
            $createFolder = $folderUtilityRenderer->createFolder($this->selectedFolder);
        }
        
        // Getting flag for showing/not showing thumbnails:
        $noThumbs = $backendUser->getTSConfig()['options.']['noThumbsInEB'] ?? false;
        $_MOD_SETTINGS = [];
        if (!$noThumbs) {
            // MENU-ITEMS, fetching the setting for thumbnails from File>List module:
            $_MOD_MENU = ['displayThumbs' => ''];
            $_MCONF['name'] = 'file_list';
            $_MOD_SETTINGS = BackendUtility::getModuleData($_MOD_MENU, GeneralUtility::_GP('SET'), $_MCONF['name']);
        }
        $displayThumbs = $_MOD_SETTINGS['displayThumbs'] ?? false;

        $noThumbs = $noThumbs ?: !$displayThumbs;
        // Create folder tree:
        /** @var ElementBrowserFolderTreeView $folderTree */
        $folderTree = GeneralUtility::makeInstance(ElementBrowserFolderTreeView::class);
        $folderTree->setLinkParameterProvider($this);
        $tree = $folderTree->getBrowsableTree();
        if ($this->selectedFolder) {
            $files = $this->renderFilesInFolder($this->selectedFolder, $allowedFileExtensions, $noThumbs);
        } else {
            $files = '';
        }
        
        $this->initDocumentTemplate();
        // Starting content:
        $content = $this->doc->startPage(htmlspecialchars($this->getLanguageService()->getLL('fileSelector')));
        
        // Putting the parts together, side by side:
        $markup = [];
        $markup[] = '<!-- Wrapper table for folder tree / filelist: -->';

        $markup[] = '<div class="element-browser fal-extra-browser">';
        // _RVH: end
        $markup[] = '   <div class="element-browser-panel element-browser-main">';
        $markup[] = '       <div class="element-browser-main-sidebar">';
        $markup[] = '           <div class="element-browser-body">';
        $markup[] = '               <h3>' . htmlspecialchars($this->getLanguageService()->getLL('folderTree')) . ':</h3>';
        $markup[] = '               ' . $tree;
        $markup[] = '           </div>';
        $markup[] = '       </div>';
        $markup[] = '       <div class="element-browser-main-content">';
        $markup[] = '           <div class="element-browser-body">';
        $markup[] = '               ' . $this->doc->getFlashMessages();
        $markup[] = '               ' . $files;
        $markup[] = '               <div id="t3UploadForm">' . $uploadForm . '</div>';
        $markup[] = '               <div id="t3CreateFolder">' . $createFolder . '</div>';
        $markup[] = '           </div>';
        $markup[] = '       </div>';
        $markup[] = '   </div>';
        $markup[] = '</div>';
        $content .= implode('', $markup);
        
        // Ending page, returning content:
        $content .= $this->doc->endPage();
        return $this->doc->insertStylesAndJS($content);
    }
    
    /**
     * For TYPO3 Element Browser: Expand folder of files.
     *
     * @param Folder $folder The folder path to expand
     * @param array $extensionList List of fileextensions to show
     * @param bool $noThumbs Whether to show thumbnails or not. If set, no thumbnails are shown.
     * @return string HTML output
     */
    public function renderFilesInFolder(Folder $folder, array $extensionList = [], $noThumbs = false)
    {
        if (!$folder->checkActionPermission('read')) {
            return '';
        }
        $lang = $this->getLanguageService();
        // _RVH: begin
        // If enabled fal_extra use maxTitleLen from EXT Settings
        if ($this->elementBrowserThumbEnable && !$noThumbs) {
            $titleLen = $this->elementBrowserMaxTitleLen;
            $titleLenFolder =(int)$this->getBackendUser()->uc['titleLen'];
        } else {
            $titleLen = (int)$this->getBackendUser()->uc['titleLen'];
        }
        // _RVH: end
        
        if ($this->searchWord !== '') {
            $searchDemand = FileSearchDemand::createForSearchTerm($this->searchWord)->withRecursive();
            $files = $folder->searchFiles($searchDemand);
        } else {
            $extensionList = !empty($extensionList) && $extensionList[0] === '*' ? [] : $extensionList;
            
            // _RVH: begin
            // If elementBrowserPageBrowserEnable count files for pager otherwise the classical way
            if ($this->elementBrowserPageBrowserEnable) {
                $this->totalItems = $this->getCountFilesInFolder($folder, $extensionList);
                $files = $this->getFilesInFolder($folder, $extensionList);
                $filesCount = $this->totalItems;
                
            } else {
                $files = $this->getFilesInFolder($folder, $extensionList);
                $filesCount = count($files);
            }
            // _RVH: end
        }
        
        $lines = [];
        
        // _RVH: begin
        // if big thumbs change rendering way
        $linesHeader = [];
        // _RVH: end
        
        // Create the header of current folder:
        $folderIcon = $this->iconFactory->getIconForResource($folder, Icon::SIZE_SMALL);
        
        // RVH: begin
        /*
         * 1) added if !$noThumbs
         * 2) upload icon added.
         * 3) create new folder icon added
         * 4) removed <th class="nowrap">&nbsp;</th>
         * 5) colspan = 3
         *
         */
        
        // _RVH: begin: added anchors
        if (!$noThumbs) {
            // Big thumbs
            $linesHeader[] = '<table class="table table-striped table-hover">';
            $linesHeader[] = '
    			<tr>
    				<th class="col-title nowrap">' . $folderIcon . ' ' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($folder->getIdentifier(), $titleLenFolder?$titleLenFolder:$titleLen)) . '</th>
    				<th class="col-control nowrap"></th>
    				<th class="col-clipboard nowrap">
                        <a href="#t3UploadForm" class="btn btn-default" id="uploadFormAnchor" title="' . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:file_upload.php.pagetitle')) . '">' . $this->iconFactory->getIcon('actions-upload', Icon::SIZE_SMALL) . '</a>
    					<a href="#t3CreateFolder" class="btn btn-default" id="createNewFormAnchor" title="' . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:create_folder.title')) . '">' . $this->iconFactory->getIcon('actions-document-new', Icon::SIZE_SMALL) . '</a>
    					<a href="#" class="btn btn-default" id="t3js-importSelection" title="' . htmlspecialchars($lang->getLL('importSelection')) . '">' . $this->iconFactory->getIcon('actions-document-import-t3d', Icon::SIZE_SMALL) . '</a>
    					<a href="#" class="btn btn-default" id="t3js-toggleSelection" title="' . htmlspecialchars($lang->getLL('toggleSelection')) . '">' . $this->iconFactory->getIcon('actions-document-select', Icon::SIZE_SMALL) . '</a>
    				</th>
    				<!-- <th class="nowrap">&nbsp;</th> -->
    			</tr>';
            // RVH: colspan="3"
            
            /*
             *
             if ($filesCount === 0) {
             $lines[] = '
             <tr>
             <td colspan="4">No files found.</td>
             </tr>';
             }
             */
            if ($filesCount === 0) {
                $linesHeader[] = '
    				<tr>
    					<td colspan="3">No files found.</td>
    				</tr>';
            }
            $linesHeader[] = '</table>';
        } else {
            $lines[] = '
    			<tr>
    				<td class="col-title nowrap">' . $folderIcon . ' ' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($folder->getIdentifier(), $titleLen)) . '</td>
    				<td class="col-control nowrap"></td>
    				<td class="col-clipboard nowrap">
                        <a href="#t3UploadForm" class="btn btn-default" id="uploadFormAnchor" title="' . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:file_upload.php.pagetitle')) . '">' . $this->iconFactory->getIcon('actions-upload', Icon::SIZE_SMALL) . '</a>
    					<a href="#t3CreateFolder" class="btn btn-default" id="createNewFormAnchor" title="' . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:create_folder.title')) . '">' . $this->iconFactory->getIcon('actions-document-new', Icon::SIZE_SMALL) . '</a>
    					<a href="#" class="btn btn-default" id="t3js-importSelection" title="' . htmlspecialchars($lang->getLL('importSelection')) . '">' . $this->iconFactory->getIcon('actions-document-import-t3d', Icon::SIZE_SMALL) . '</a>
    					<a href="#" class="btn btn-default" id="t3js-toggleSelection" title="' . htmlspecialchars($lang->getLL('toggleSelection')) . '">' . $this->iconFactory->getIcon('actions-document-select', Icon::SIZE_SMALL) . '</a>
    				</td>
    			</tr>';
            if ($filesCount === 0) {
                $lines[] = '
    				<tr>
    					<td colspan="3">No files found.</td>
    				</tr>';
            }
        }
        // RVH: end
        // RVH: begin
        $cc = 0;
        // RVH: end
        foreach ($files as $fileObject) {
            $fileExtension = $fileObject->getExtension();
            // Thumbnail/size generation:
            $imgInfo = [];
            if (!$noThumbs && GeneralUtility::inList(strtolower($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] . ',' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext']), strtolower($fileExtension))) {
                
                // RVH: begin: if fal_extra thumbnails enabled use CONTEXT_IMAGECROPSCALEMASK otherwise the classical way
                
                if ($this->elementBrowserThumbEnable) {
                    $processedFile = $fileObject->process(
                        ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                        $this->thumbnailConfiguration
                        );
                } else {
                    $processedFile = $fileObject->process(
                        ProcessedFile::CONTEXT_IMAGEPREVIEW,
                        $this->thumbnailConfiguration
                        );
                }
                // RVH: end
               
                $imageUrl = $processedFile->getPublicUrl(true);
                $imgInfo = [
                    $fileObject->getProperty('width'),
                    $fileObject->getProperty('height')
                ];
                $pDim = $imgInfo[0] . 'x' . $imgInfo[1] . ' pixels';
                
                // _RVH: remove image width & height ???
                // TODO:
                $clickIcon = '<img src="' . $imageUrl . '"'
                    . ' width="' . $processedFile->getProperty('width') . '"'
                    . ' height="' . $processedFile->getProperty('height') . '"'
                // _RVH: removed hspace, vspace & border
                    // . ' hspace="5" vspace="5" border="1" />';
                . '  />';
            } else {
                $clickIcon = '';
                $pDim = '';
            }
            // Create file icon:
            $size = ' (' . GeneralUtility::formatSize($fileObject->getSize(), $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:byteSizeUnits')) . ($pDim ? ', ' . $pDim : '') . ')';
            
            // _RVH: begin
            // added if (!$noThumbs) {
            if (!$noThumbs) {
                $icon = '<span title="id=' . htmlspecialchars($fileObject->getUid()) . '" class="fileicon btn btn-default">' . $this->iconFactory->getIconForResource($fileObject, Icon::SIZE_DEFAULT) . '</span>';
            } else {
                $icon = '<span title="id=' . htmlspecialchars($fileObject->getUid()) . '">' . $this->iconFactory->getIconForResource($fileObject, Icon::SIZE_SMALL) . '</span>';
            }
            // _RVH: end
            
            // Create links for adding the file:
            $filesIndex = count($this->elements);
            $this->elements['file_' . $filesIndex] = [
                'type' => 'file',
                'table' => 'sys_file',
                'uid' => $fileObject->getUid(),
                'fileName' => $fileObject->getName(),
                'filePath' => $fileObject->getUid(),
                'fileExt' => $fileExtension,
                'fileIcon' => $icon
            ];
            if ($this->fileIsSelectableInFileList($fileObject, $imgInfo)) {
                $ATag = '<a href="#" class="btn btn-default" title="' . htmlspecialchars($fileObject->getName()) . '" data-file-index="' . htmlspecialchars($filesIndex) . '" data-close="0">';
                $ATag .= '<span title="' . htmlspecialchars($lang->getLL('addToList')) . '">' . $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL)->render() . '</span>';
                $ATag_alt = '<a href="#" title="' . htmlspecialchars($fileObject->getName()) . $size . '" data-file-index="' . htmlspecialchars($filesIndex) . '" data-close="1">';
                $ATag_e = '</a>';
                $bulkCheckBox = '<label class="btn btn-default btn-checkbox"><input type="checkbox" class="typo3-bulk-item" name="file_' . $filesIndex . '" value="0" /><span class="t3-icon fa"></span></label>';
            } else {
                $ATag = '';
                $ATag_alt = '';
                $ATag_e = '';
                $bulkCheckBox = '';
            }
            /** @var \TYPO3\CMS\Backend\Routing\UriBuilder $uriBuilder */
            $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Routing\UriBuilder::class);
            // Create link to showing details about the file in a window:
            $Ahref = (string)$uriBuilder->buildUriFromRoute('show_item', [
                'type' => 'file',
                'table' => '_FILE',
                'uid' => $fileObject->getCombinedIdentifier(),
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
            ]);
            
            // Combine the stuff:
            $filenameAndIcon = $ATag_alt . $icon . htmlspecialchars(GeneralUtility::fixed_lgd_cs($fileObject->getName(), $titleLen)) . $ATag_e;
            
            // _RVH: begin
            // 
            
            if (!$noThumbs) {
                // Big thumbs
                if(($cc%$this->elementBrowserCols)==0) {
                    $lines[] = '<tr><td>';
                } else {
                    $lines[] = '<td class="col-clipboard">';
                }
                $lines[] = '        <div class="btn-group btn-group-fal-extra">';
                $lines[] =              $ATag . $ATag_e;
                $lines[] = '             <a href="' . htmlspecialchars($Ahref) . '" class="btn btn-default" title="';
                $lines[] = '                ' . htmlspecialchars($lang->getLL('info')) . '">';
                $lines[] = '                ' . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) . '</a>';
                $lines[] = '                ' . $bulkCheckBox . '';
                $lines[] = '                ' . $ATag_alt . $icon .  $ATag_e . '';
                $lines[] = '        </div>';
                $lines[] = '         <div class="big-thumb-image">';
                $lines[] = '            ' . $ATag_alt . $clickIcon . $ATag_e . '';
                $lines[] = '        </div>';
                $lines[] = '        <div class="big-thumb-caption">';
                $lines[] = '            ' . $ATag_alt . htmlspecialchars(GeneralUtility::fixed_lgd_cs($fileObject->getName(), $titleLen)) . $ATag_e . '';
                $lines[] = '        </div>';
                
                if (($cc%$this->elementBrowserCols)==($this->elementBrowserCols-1) || $cc==($this->iLimit-1)) {
                    $lines[] = '</td></tr>';
                } else {
                    $lines[] = '</td>';
                }
            } else {
                $lines[] = '
					<tr class="file_list_normal">
						<td class="col-title nowrap">' . $filenameAndIcon . '&nbsp;</td>
						<td class="col-control">
							<div class="btn-group">' . $ATag . $ATag_e . '
							<a href="' . htmlspecialchars($Ahref) . '" class="btn btn-default" title="' . htmlspecialchars($lang->getLL('info')) . '">' . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) . '</a>
						</td>
						<td class="col-clipboard" valign="top">' . $bulkCheckBox . '</td>
						<td class="nowrap">&nbsp;' . $pDim . '</td>
					</tr>';
                if ($pDim) {
                    $lines[] = '
					<tr>
						<td class="filelistThumbnail" colspan="3">' . $ATag_alt . $clickIcon . $ATag_e . '</td>
					</tr>';
                }
            }
            // RVH: begin
            $cc++;
            // RVH: end
           
        }
        
        $markup = [];
        // _RVH: begin
        // Removed $filesCount, moved to list navigation
        // $markup[] = '<h3>' . htmlspecialchars($lang->getLL('files')) . ' ' . $filesCount . ':</h3>';
        $markup[] = GeneralUtility::makeInstance(FolderUtilityRenderer::class, $this)->getFileSearchField($this->searchWord);
        $markup[] = '<div id="filelist">';
        
        // _RVH: begin
        $markup[] = '   <!-- Filelisting -->';
        // $markup[] = '   <div class="table-fit">';
        
        // RVH: added if !$noThumbs ...
        
        if (!$noThumbs) {
            // Big thumbs
            $markup[] = '           ' . implode('', $linesHeader);
            if ( $this->elementBrowserPageBrowserEnable ) {
                $markup[] = '' . $this->renderListNavigation('top') . '';
            }
            // $markup[] = '   <div class="table-filelist">';
            $markup[] = '       <table class="table" id="typo3-filelist">';
            $markup[] = '           ' . implode('', $lines);
            $markup[] = '       </table';
            if ( $this->elementBrowserPageBrowserEnable ) {
                $markup[] = '' . $this->renderListNavigation('bottom') . '';
            }
            
            // $markup[] = '   </div>';
        } else {
            if ( $this->elementBrowserPageBrowserEnable ) {
                $markup[] = '' . $this->renderListNavigation('top') . '';
            }
            $markup[] = '       <table class="table table-striped table-hover" id="typo3-filelist">';
            $markup[] = '           ' . implode('', $lines);
            $markup[] = '       </table>';
            if ( $this->elementBrowserPageBrowserEnable ) {
                $markup[] = '' . $this->renderListNavigation('bottom') . '';
            }
        }
        
        // $markup[] = '   </div>';
        
        // _RVH: end
        $markup[] = ' </div>';

        $markup[] = '   ' . $this->getBulkSelector($filesCount);

        $content = implode('', $markup);
        
        return $content;
    }
    
    /**
     * Get a list of Files in a folder filtered by extension
     *
     * @param Folder $folder
     * @param array $extensionList
     * @return File[]
     */
    protected function getFilesInFolder(Folder $folder, array $extensionList)
    {
        if (!empty($extensionList)) {
            /** @var FileExtensionFilter $filter */
            $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
            $filter->setAllowedFileExtensions($extensionList);
            $folder->setFileAndFolderNameFilters([[$filter, 'filterFileList']]);
        }
        // _RVH: begin
        // return $folder->getFiles();
        $allowedSortBy = 'tstamp,name,extension';
        $sortBy = GeneralUtility::trimExplode(':', $this->sortBy);
        if(GeneralUtility::inList($allowedSortBy, $sortBy[0])) {
            $sort = $sortBy[0];
            $sortRev = ($sortBy[1]=='DESC')?true:false;
        } else {
            $sort = 'tstamp';
            $sortRev = true;
        }

        
        if ($this->totalItems > $this->iLimit) {
            return $folder->getFiles($this->firstElementNumber,$this->iLimit, $folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false, $sort, $sortRev);
        } else {
            return $folder->getFiles(0,0, $folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false, $sort, $sortRev);
        }
        // _RVH: end
    }
    
    /**
     * Get the HTML data required for a bulk selection of files of the TYPO3 Element Browser.
     *
     * @param int $filesCount Number of files currently displayed
     * @return string HTML data required for a bulk selection of files - if $filesCount is 0, nothing is returned
     */
    protected function getBulkSelector($filesCount)
    {
        if (!$filesCount) {
            return '';
        }
        
        $lang = $this->getLanguageService();
        $out = '';
        
        // Getting flag for showing/not showing thumbnails:
        $noThumbsInEB = $this->getBackendUser()->getTSConfig()['options.']['noThumbsInEB'] ?? false;
        if (!$noThumbsInEB && $this->selectedFolder) {
            // MENU-ITEMS, fetching the setting for thumbnails from File>List module:
            $_MOD_MENU = ['displayThumbs' => ''];
            $_MCONF['name'] = 'file_list';
            $_MOD_SETTINGS = BackendUtility::getModuleData($_MOD_MENU, GeneralUtility::_GP('SET'), $_MCONF['name']);
            // $addParams = GeneralUtility::implodeArrayForUrl('', $this->getUrlParameters(['identifier' => $this->selectedFolder->getCombinedIdentifier()]));
            $addParams = HttpUtility::buildQueryString($this->getUrlParameters(['identifier' => $this->selectedFolder->getCombinedIdentifier()]), '&');
            $thumbNailCheck = '<div class="checkbox" style="padding:5px 0 15px 0"><label for="checkDisplayThumbs">'
                . BackendUtility::getFuncCheck(
                    '',
                    'SET[displayThumbs]',
                    $_MOD_SETTINGS['displayThumbs'],
                    $this->thisScript,
                    $addParams,
                    'id="checkDisplayThumbs"'
                    )
                    . htmlspecialchars($lang->sL('LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:displayThumbs')) . '</label></div>';
                    $out .= $thumbNailCheck;
        } else {
            $out .= '<div style="padding-top: 15px;"></div>';
        }
        return $out;
    }
    
    /**
     * Checks if the given file is selectable in the filelist.
     *
     * By default all files are selectable. This method may be overwritten in child classes.
     *
     * @param FileInterface $file
     * @param mixed[] $imgInfo Image dimensions from \TYPO3\CMS\Core\Imaging\GraphicalFunctions::getImageDimensions()
     * @return bool TRUE if file is selectable.
     */
    protected function fileIsSelectableInFileList(FileInterface $file, array $imgInfo)
    {
        return true;
    }
    
    /**
     * @return string[] Array of body-tag attributes
     */
    protected function getBodyTagAttributes()
    {
        return [
            'data-mode' => 'file',
            'data-elements' => json_encode($this->elements)
        ];
    }
    
    /**
     * @param array $values Array of values to include into the parameters
     * @return string[] Array of parameters which have to be added to URLs
     */
    public function getUrlParameters(array $values)
    {
        return [
            'mode' => 'file',
            'expandFolder' => $values['identifier'] ?? $this->expandFolder,
            'bparams' => $this->bparams
        ];
    }
    
    /**
     * @param array $values Values to be checked
     * @return bool Returns TRUE if the given values match the currently selected item
     */
    public function isCurrentlySelectedItem(array $values)
    {
        return false;
    }
    
    /**
     * Returns the URL of the current script
     *
     * @return string
     */
    public function getScriptUrl()
    {
        return $this->thisScript;
    }
    
    // _RVH: begin new methods
    /**
     * Get a list of Files in a folder filtered by extension
     *
     * @param Folder $folder
     * @param array $extensionList
     * @return File[]
     */
    protected function getCountFilesInFolder(Folder $folder, array $extensionList)
    {
        if (!empty($extensionList)) {
            /** @var FileExtensionFilter $filter */
            $filter = GeneralUtility::makeInstance(FileExtensionFilter::class);
            $filter->setAllowedFileExtensions($extensionList);
            $folder->setFileAndFolderNameFilters([[$filter, 'filterFileList']]);
        }
        
        // getFiles($start = 0, $numberOfItems = 0, $filterMode = self::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $recursive = false, $sort = '', $sortRev = false)
        return $folder->getFileCount();
    }
    
    /**
     * Get pointer for first element on the page
     *
     * @param int $page Page number starting with 1
     * @return int Pointer to first element on the page (starting with 0)
     */
    protected function getPointerForPage($page)
    {
        return ($page - 1) * $this->iLimit;
    }
    
    protected function renderListNavigation(string $renderPart = 'top')
    {
        $totalPages = ceil($this->totalItems / $this->iLimit);
        
        // Show page selector if not all records fit into one page
        if ($totalPages <= 1) {
            return '';
        }
        $content = '';
        //$listURL = $this->listURL('', $this->table);
        // RVH
        $listURL = GeneralUtility::makeInstance(FolderUtilityRenderer::class, $this)->getUrl([]);
        $fuckingILimit = '&jumpLimit=' . $this->iLimit;
        
        // 1 = first page
        // 0 = first element
        $currentPage = floor($this->firstElementNumber / $this->iLimit) + 1;
        // Compile first, previous, next, last and refresh buttons
        if ($currentPage > 1) {
            $labelFirst = htmlspecialchars($this->getLanguageService()
                ->sL('LLL:EXT:lang/Resources/Private/Language/locallang_common.xlf:first'));
            $labelPrevious = htmlspecialchars($this->getLanguageService()
                ->sL('LLL:EXT:lang/Resources/Private/Language/locallang_common.xlf:previous'));
            $first = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage(1) . $fuckingILimit . '" title="' . $labelFirst . '">'
                . $this->iconFactory->getIcon('actions-view-paging-first', Icon::SIZE_SMALL)->render() . '</a></li>';
                $previous = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage - 1) . $fuckingILimit . '" title="' . $labelPrevious . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-previous', Icon::SIZE_SMALL)->render() . '</a></li>';
        } else {
            $first = '<li class="disabled"><span>' . $this->iconFactory->getIcon('actions-view-paging-first', Icon::SIZE_SMALL)->render() . '</span></li>';
            $previous = '<li class="disabled"><span>' . $this->iconFactory->getIcon('actions-view-paging-previous', Icon::SIZE_SMALL)->render() . '</span></li>';
        }
        if ($currentPage < $totalPages) {
            $labelNext = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_common.xlf:next'));
            $labelLast = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_common.xlf:last'));
            $next = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($currentPage + 1) . $fuckingILimit . '" title="' . $labelNext . '">'
                . $this->iconFactory->getIcon('actions-view-paging-next', Icon::SIZE_SMALL)->render() . '</a></li>';
                $last = '<li><a href="' . $listURL . '&pointer=' . $this->getPointerForPage($totalPages) . $fuckingILimit . '" title="' . $labelLast . '">'
                    . $this->iconFactory->getIcon('actions-view-paging-last', Icon::SIZE_SMALL)->render() . '</a></li>';
        } else {
            $next = '<li class="disabled"><span>' . $this->iconFactory->getIcon('actions-view-paging-next', Icon::SIZE_SMALL)->render() . '</span></li>';
            $last = '<li class="disabled"><span>' . $this->iconFactory->getIcon('actions-view-paging-last', Icon::SIZE_SMALL)->render() . '</span></li>';
        }
        
        $reload = '<li><a href="#" onclick="return jumpToUrl(' . GeneralUtility::quoteJSvalue($listURL
            . '&pointer=') . '+calculatePointer(document.getElementById(' . GeneralUtility::quoteJSvalue('jumpPage-' . $renderPart)
            . ').value,document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').options[document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').selectedIndex].value)+' 
            . GeneralUtility::quoteJSvalue('&jumpLimit=') . '+document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').options[document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').selectedIndex].value
             );" title="'
                . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_common.xlf:reload')) . '">'
                    . $this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL)->render() . '</a></li>';
        
                    
        if ($renderPart === 'top') {
            // Add js to traverse a page select input to a pointer value
            $content = '
<script type="text/javascript">
/*<![CDATA[*/
	function calculatePointer(page,iLimit) {
		if (page > ' . $totalPages . ') {
			page = ' . $totalPages . ';
		}
		if (page < 1) {
			page = 1;
		}
		return (page - 1) * iLimit;
	}
/*]]>*/
</script>
            ';
        }
        $pageNumberInput = '
			<input type="number" min="1" max="' . $totalPages . '" value="' . $currentPage . '" size="3" class="form-control input-sm paginator-input" id="jumpPage-' . $renderPart . '" name="jumpPage-'
			    . $renderPart . '" onkeyup="if (event.keyCode == 13) { 
                    return jumpToUrl(' . htmlspecialchars(GeneralUtility::quoteJSvalue($listURL . '&pointer='))
			    . '+calculatePointer(this.value,document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').options[document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').selectedIndex].value)+'
            . GeneralUtility::quoteJSvalue('&jumpLimit=') . '+document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').options[document.getElementById(' . GeneralUtility::quoteJSvalue('jumpLimit-' . $renderPart)
            . ').selectedIndex].value
            ); }" />
			';
        $pageIndicatorText = sprintf(
            $this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_mod_web_list.xlf:pageIndicator'),
            $pageNumberInput,
            $totalPages
        );
        $pageIndicator = '<li><span>' . $pageIndicatorText . '</span></li>';
        if ($this->totalItems > $this->firstElementNumber + $this->iLimit) {
            $lastElementNumber = $this->firstElementNumber + $this->iLimit;
        } else {
            $lastElementNumber = $this->totalItems;
        }
        $rangeIndicator = '<li><span>'
            . sprintf($this->getLanguageService()
            ->sL('LLL:EXT:fal_extra/Resources/Private/Language/locallang_be.xlf:rangeIndicator'), ($this->firstElementNumber + 1), $lastElementNumber, $this->totalItems)
            . '</span></li>';
    
        // _RVH: begin

        $onChange = 'onchange="return jumpToUrl(' . htmlspecialchars(GeneralUtility::quoteJSvalue($listURL . '&pointer='))
			    . '+calculatePointer(document.getElementById(' . GeneralUtility::quoteJSvalue('jumpPage-' . $renderPart)
			    . ').value,this.options[this.selectedIndex].value)+'
			    . GeneralUtility::quoteJSvalue('&jumpLimit=') . '+this.options[this.selectedIndex].value);"';
        $elementsNumberInput ='
            <select class="form-control input-sm paginator-input" id="jumpLimit-' . $renderPart . '" name="jumpLimit" ' . $onChange . '>
                <option value="10" ' . ($this->iLimit==10?'selected':'') . '>10</option>
                <option value="20" ' . ($this->iLimit==20?'selected':'') . '>20</option>
                <option value="30" ' . ($this->iLimit==30?'selected':'') . '>30</option>
                <option value="50" ' . ($this->iLimit==50?'selected':'') . '>50</option>
                <option value="100" ' . ($this->iLimit==100?'selected':'') . '>100</option>
            </select>
        ';
        $elementsNumberInputText = $this->getLanguageService()->sL('LLL:EXT:fal_extra/Resources/Private/Language/locallang_be.xlf:itemsPerPage');
        
        $elementsNumber = '<li><span>' . $elementsNumberInputText . $elementsNumberInput . '</span></li>';
        
        $sortByInputText = $this->getLanguageService()->sL('LLL:EXT:fal_extra/Resources/Private/Language/locallang_be.xlf:sortBy');
        $onChangeSortBy = 'onchange="return jumpToUrl(' 
            . htmlspecialchars(GeneralUtility::quoteJSvalue($listURL . '&sortBy=')) 
            . '+this.options[this.selectedIndex].value'
            . ');"';

        $sortByInput = '
            <select class="form-control input-sm paginator-input" id="sortBy-' . $renderPart . '" name="sortBy" ' . $onChangeSortBy . '>
                <option value="tstamp:ASC" ' . ($this->sortBy=='tstamp:ASC'?'selected':'') . '>Data ASC</option>
                <option value="tstamp:DESC" ' . ($this->sortBy=='tstamp:DESC'?'selected':'') . '>Data DESC</option>
                <option value="name:ASC" ' . ($this->sortBy=='name:ASC'?'selected':'') . '>Nome ASC</option>
                <option value="name:DESC" ' . ($this->sortBy=='name:DESC'?'selected':'') . '>Nome DESC</option>
                <option value="extension:ASC" ' . ($this->sortBy=='extension:ASC'?'selected':'') . '>Ext ASC</option>
                <option value="extension:DESC" ' . ($this->sortBy=='extension:DESC'?'selected':'') . '>Ext DESC</option>
            </select>
        ';
        
       
        
        $sortBy = '<li><span>' . $sortByInputText . $sortByInput. '</span></li>';
    
        $titleColumn = $this->fieldArray[0];
        $data = [
        $titleColumn => $content . '
    	<nav class="pagination-wrap">
    		<ul class="pagination pagination-block">
    			' . $first . '
    			' . $previous . '
    			' . $rangeIndicator . '
    			' . $pageIndicator . '
                ' . $elementsNumber . '
    			' . $next . '
    			' . $last . '
    			' . /* $reload . */ '
                ' . $sortBy .'
    		</ul>
    	</nav>
    '
                ];
        return implode($data);
    }
}