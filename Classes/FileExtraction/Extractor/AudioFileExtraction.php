<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class AudioFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'audio';
    }

    public function getFileGroupLabel(): string
    {
        return 'Audio';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-media-audio';
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
