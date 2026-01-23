<?php

declare(strict_types=1);

namespace Lochmueller\Index\FileExtraction\Extractor;

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

    /**
     * @return string[]
     */
    public function getFileExtensions(): array
    {
        $eventObject = new CustomFileExtraction();
        $this->eventDispatcher->dispatch($eventObject);
        return $eventObject->extensions;
    }

    public function getFileContent(FileInterface $file): string
    {
        $eventObject = new CustomFileExtraction($file);
        $this->eventDispatcher->dispatch($eventObject);
        return (string) $eventObject->content;
    }

}
