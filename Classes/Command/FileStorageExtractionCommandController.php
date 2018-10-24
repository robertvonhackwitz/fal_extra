<?php
namespace RVH\FalExtra\Command;

use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Controller Update File Metadata
 *
 */

/**
 * This task which indexes files in storage
 */
class FileStorageExtractionCommandController extends CommandController
{
    /**
     * Storage Uid
     *
     * @var int
     */
    private $storageUid = 1;

    /**
     * FileCount
     *
     * @var int
     */
    public $maxFileCount = 1000;

    /**
     * Update File Metadata
     *
     * @cli
     */
    public function runCommand()
    {
        $success = false;
        if ((int)$this->storageUid > 0) {
            $storage = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getStorageObject($this->storageUid);
            $storage->setEvaluatePermissions(false);
            $indexer = $this->getIndexer($storage);
            try {
                $indexer->runMetaDataExtraction((int)$this->maxFileCount);
                $success = true;
            } catch (\Exception $e) {
                $success = false;
                $this->logException($e);
            }
            $storage->setEvaluatePermissions(true);
        }
        return $success;
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
