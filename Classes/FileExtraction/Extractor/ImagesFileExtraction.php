<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class ImagesFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'images';
    }

    public function getFileGroupLabel(): string
    {
        return 'Images';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-media-image';
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
