<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

use Lochmueller\Index\Event\Extractor\CustomExtensionsFileExtraction;
use Lochmueller\Index\Event\Extractor\CustomFileExtraction;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\FileInterface;

class EventFileExtraction implements FileExtractionInterface
{
    public function __construct(protected EventDispatcherInterface $eventDispatcher) {}

    public function getFileGroupName(): string
    {
        return 'event';
    }

    public function getFileGroupLabel(): string
    {
        return 'Event / Custom';
    }

    public function getFileGroupIconIdentifier(): string
    {
        return 'avatar-default';
    }

    public function getFileExtensions(): array
    {
        /** @var CustomExtensionsFileExtraction $event */
        $event = $this->eventDispatcher->dispatch(new CustomExtensionsFileExtraction());
        return $event->fileExtensions;
    }

    public function getFileContent(FileInterface $file): string
    {
        /** @var CustomFileExtraction $event */
        $event = $this->eventDispatcher->dispatch(new CustomFileExtraction($file));
        return (string) $event->content;
    }

}
