<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class TextFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'txt';
    }

    public function getFileGroupLabel(): string
    {
        return 'Txt';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-text-text';
    }

    public function getFileExtensions(): array
    {
        return ['txt'];
    }

    public function getFileContent(FileInterface $file): string
    {
        return $file->getContents();
    }

}
