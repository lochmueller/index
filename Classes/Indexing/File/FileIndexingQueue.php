<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\File;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Traversing\FileTraversing;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class FileIndexingQueue implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FileTraversing           $fileTraversing,
        private readonly FileExtractor            $fileExtractor,
        private readonly MessageBusInterface      $bus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}


    public function fillQueue(Configuration $configuration, SiteInterface $site, string $indexProcessId): void
    {
        $extensions = $this->fileExtractor->resolveFileTypes($configuration->fileTypes);
        foreach ($configuration->fileMounts as $fileMount) {
            foreach ($this->fileTraversing->findFilesInFileMountUidRecursive((int) $fileMount, $extensions) as $file) {
                $this->bus->dispatch(new FileMessage(
                    siteIdentifier: $site->getIdentifier(),
                    indexConfigurationRecordId: $configuration->configurationId,
                    fileIdentifier: $file->getCombinedIdentifier(),
                    indexProcessId: $indexProcessId,
                ));
            }
        }
    }

}
