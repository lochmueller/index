<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class PowerpointFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'powerpoint';
    }

    public function getFileGroupLabel(): string
    {
        return 'Powerpoint';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-powerpoint';
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
