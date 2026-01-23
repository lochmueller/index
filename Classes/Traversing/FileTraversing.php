<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileTraversing
{
    protected ResourceFactory $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    }

    public function getFileByCompinedIdentifier(string $combinedIdentifier): ?FileInterface
    {
        return $this->resourceFactory->getFileObjectFromCombinedIdentifier($combinedIdentifier);
    }

    /**
     * @param string[] $fileExtensions
     * @return iterable<File>
     */
    public function findFilesInFileMountUidRecursive(int $fileMountUid, array $fileExtensions): iterable
    {
        $row = BackendUtility::getRecord('sys_filemounts', $fileMountUid);
        if ($row) {
            /** @var Folder $folder */
            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($row['identifier']);
            foreach ($folder->getFiles(recursive: true) as $file) {
                /** @var File $file */
                if (in_array($file->getExtension(), $fileExtensions)) {
                    yield $file;
                }
            }
        }
    }

}
