<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class FileTraversing
{
    public function __construct(
        protected ResourceFactory $resourceFactory,
        protected GenericRepository $genericRepository
    ) {}

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
        $row = $this->genericRepository->setTableName('sys_filemounts')->findByUid($fileMountUid);
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
