<?php
namespace RVH\FalExtra\Command;

use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Controller Update Storage Index
 *
 */

/**
 * This task which indexes files in storage
 */
class FileStorageIndexingCommandController extends CommandController 
{
    
    private $storageUid = 1;
    
    /**
     * Update Storage Index
     *
     * @cli
     */
    public function runCommand()
    {
        if ((int)$this->storageUid > 0) {
            $storage = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getStorageObject($this->storageUid);
            $storage->setEvaluatePermissions(false);
            $indexer = $this->getIndexer($storage);
            $indexer->processChangesInStorages();
            $storage->setEvaluatePermissions(true);
        }
        return true;
    }
    
    /**
     * Gets the indexer
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
     * @return \TYPO3\CMS\Core\Resource\Index\Indexer
     */
    protected function getIndexer(\TYPO3\CMS\Core\Resource\ResourceStorage $storage)
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\Indexer::class, $storage);
    }
}