<?php
namespace RVH\FalExtra\Rendering;

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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;

/**
 * Class FacebookVideoRenderer
 *
 * @author Thomas Löffler <loeffler@spooner-web.de>
 */
class FacebookVideoRenderer implements FileRendererInterface {
    
    /**
     * @var OnlineMediaHelperInterface
     */
    protected $onlineMediaHelper;
    
    /**
     * @return integer
     */
    public function getPriority()
    {
        return 1;
    }
    
    /**
     * @param \TYPO3\CMS\Core\Resource\FileInterface $file
     * @return boolean
     */
    public function canRender(\TYPO3\CMS\Core\Resource\FileInterface $file)
    {
        return ($file->getMimeType() === 'video/facebook' || $file->getExtension() === 'facebook') && $this->getOnlineMediaHelper($file) !== false;
    }
    
    /**
     * Get online media helper
     *
     * @param FileInterface $file
     * @return bool|OnlineMediaHelperInterface
     */
    protected function getOnlineMediaHelper(FileInterface $file)
    {
        if ($this->onlineMediaHelper === null) {
            $orgFile = $file;
            if ($orgFile instanceof FileReference) {
                $orgFile = $orgFile->getOriginalFile();
            }
            if ($orgFile instanceof File) {
                $this->onlineMediaHelper = OnlineMediaHelperRegistry::getInstance()->getOnlineMediaHelper($orgFile);
            } else {
                $this->onlineMediaHelper = false;
            }
        }
        
        return $this->onlineMediaHelper;
    }
    
    /**
     * @param \TYPO3\CMS\Core\Resource\FileInterface $file
     * @param int|string $width
     * @param int|string $height
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript
     * @return string
     */
    public function render(\TYPO3\CMS\Core\Resource\FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {
        // Due to the rendering via fluid_styled_content, text/media element and click-enlarge the rendering happens not here
        return '';
    }
    
    
}
