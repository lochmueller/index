<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use TYPO3\CMS\Core\Resource\FileInterface;

class VideosFileExtraction implements FileExtractionInterface
{
    public function getFileGroupName(): string
    {
        return 'videos';
    }

    public function getFileGroupLabel(): string
    {
        return 'Videos';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'mimetypes-media-video';
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
