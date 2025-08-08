<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class ExcelFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'excel';
    }

    public function getFileGroupLabel(): string
    {
        return 'Excel';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-excel';
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
