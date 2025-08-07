<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Resource\FileInterface;

#[AutoconfigureTag(name: 'index.file_extractor')]
interface FileExtractionInterface
{
    public function getFileGroupName(): string;

    public function getFileGroupLabel(): string;

    public function getFileGroupIconIdentifier(): string;

    public function getFileExtensions(): array;

    public function getFileContent(FileInterface $file): string;

}
