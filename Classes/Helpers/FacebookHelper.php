<?php
namespace RVH\FalExtra\Helpers;

/**
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

use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\AbstractOEmbedHelper;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Class FacebookVideoHelper
 *
 * @author Thomas Löffler <loeffler@spooner-web.de>
 */
class FacebookHelper extends AbstractOEmbedHelper {

    /**
     * @param string $url
     * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
     * @return File
     */
    public function transformUrlToFile($url, \TYPO3\CMS\Core\Resource\Folder $targetFolder)
    {
        $videoId = null;
        $matches = [];
        $subMatches = [];
        $fMatches = [];
        
        // Try to get Facebook code from given url.
        if (preg_match('/facebook\.com\/watch\/\?v=*([0-9]+)/i', $url, $fMatches)) {
           
            $facebookWebPage = GeneralUtility::getUrl($url);
            if (is_string($facebookWebPage)) {
                
                $pos = strpos($facebookWebPage, 'permalinkURL');
                $permaLinkString = substr($facebookWebPage, $pos, 100);
                $permaLinkArr = explode('/',$permaLinkString);

                $videoId = $permaLinkArr[1] . '_' . $permaLinkArr[3];
              
            }
        }   
        
        // These formats are supported with and without http(s)://
        // - facebook.com/<site>/videos/<code> # Share URL
        if (preg_match('/facebook\.com\/(.*)\/videos\/*([0-9]+)/i', $url, $matches)) {
            // Video Id = Channel name + '_' + Video ID
            $videoId = $matches[1] . '_' . $matches[2];
        } 
        
        if (empty($videoId)) {
            return null;
        }
        
        return $this->transformMediaIdToFile($videoId, $targetFolder, $this->extension);
    }
    
    /**
     * Transform mediaId to File
     *
     * @param string $mediaId
     * @param Folder $targetFolder
     * @param string $fileExtension
     * @return File
     */
    protected function transformMediaIdToFile($mediaId, Folder $targetFolder, $fileExtension)
    {
        $file = $this->findExistingFileByOnlineMediaId($mediaId, $targetFolder, $fileExtension);
        
        // no existing file create new
        if ($file === null) {
            $oEmbed = $this->getOEmbedData($mediaId);
            if (!empty($oEmbed) && isset($oEmbed['name'])) {
                $fileName = $oEmbed['name'] . '.' . $fileExtension;
            } else {
                $fileName = $mediaId . '.' . $fileExtension;
            }
            $file = $this->createNewFile($targetFolder, $fileName, $mediaId);
        }
        
        return $file;
    }
    
    
    /**
     * Get meta data for OnlineMedia item
     * Using the meta data from oEmbed
     *
     * @param File $file
     * @return array with metadata
     */
    public function getMetaData(File $file)
    {
        $metadata = [];
        
        $oEmbed = $this->getOEmbedData($this->getOnlineMediaId($file));
        
        if ($oEmbed) {
            $metadata['width'] = (int)$oEmbed['width'];
            $metadata['height'] = (int)$oEmbed['height'];
            if (empty($file->getProperty('title')) && isset($oEmbed['name'])) {
                $metadata['title'] = strip_tags($oEmbed['name']);
            }
            if (empty($file->getProperty('description')) && isset($oEmbed['description'])) {
                $metadata['description'] = strip_tags($oEmbed['description']);
            }
            $metadata['author'] = $oEmbed['from']['name'];
        }
        
        return $metadata;
    }
    
    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @param bool $relativeToCurrentScript
     * @return string
     */
    public function getPublicUrl(\TYPO3\CMS\Core\Resource\File $file, $relativeToCurrentScript = false)
    {
        $videoId = $this->getOnlineMediaId($file);
        $video = GeneralUtility::trimExplode('_', $videoId);
        $videoId = $video[1];
        $videoChannel = $video[0];
        $facebookUrl = sprintf(
            'https://www.facebook.com/plugins/video.php?href=https://www.facebook.com/%s/videos/%s',
            $videoChannel,
            $videoId
            );

        // $videoLink = sprintf('https://www.facebook.com/video.php?v=%s', $videoId);
        // return 'https://www.facebook.com/v2.5/plugins/video.php?href=' . urlencode($videoLink);
        return $facebookUrl;
    }
    
    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return string
     */
    public function getPreviewImage(\TYPO3\CMS\Core\Resource\File $file)
    {
        $videoId = $this->getOnlineMediaId($file);
        $temporaryFileName = $this->getTempFolderPath() . 'facebook_' . md5($videoId) . '.jpg';
        
        if (!file_exists($temporaryFileName)) {

            $fbPage = GeneralUtility::getUrl($this->getPublicUrl($file));
            /*
            $pattern = '/<img (.*) src="(https:\/\/scontent\-[a-zA-Z0-9\-\/\.\_\?\=\&\;]+)/';
            preg_match($pattern, $fbPage, $matches);
            */
            $dom = new \DOMDocument;
            @$dom->loadHTML($fbPage);
            $tags = $dom->getElementsByTagName('img');
            $imgSrc = $tags[0]->getAttribute('src');

            if($imgSrc!='') {
    
                $previewImage = GeneralUtility::getUrl($imgSrc);
                if ($previewImage !== false) {
                    file_put_contents($temporaryFileName, $previewImage);
                    GeneralUtility::fixPermissions($temporaryFileName);
                }
            }
        }
        
        return $temporaryFileName;
    }
    
    /**
     * @param string $mediaId
     * @param string $format
     * @return string
     */
    public function getOEmbedUrl($mediaId, $format = 'json')
    {
        return 'https://graph.facebook.com/' . $mediaId . '/';
    }
}

