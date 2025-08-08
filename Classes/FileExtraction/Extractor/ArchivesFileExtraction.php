<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class ArchivesFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'archives';
    }

    public function getFileGroupLabel(): string
    {
        return 'Archives';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-compressed';
    }

    public function getFileExtensions(): array
    {
        // @todo fix
        return [];
    }

    public function getFileContent(FileInterface $file): string
    {
        return ''; // @todo integratre
    }

}
