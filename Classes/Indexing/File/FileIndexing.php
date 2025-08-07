<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\File;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Traversing\FileTraversing;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class FileIndexing implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private FileTraversing           $fileTraversing,
        private FileExtractor            $fileExtractor,
        private MessageBusInterface      $bus,
        private EventDispatcherInterface $eventDispatcher,
    ) {}


    public function fillQueue(Configuration $configuration): void
    {
        $extensions = $this->fileExtractor->resolveFileTypes($configuration->fileTypes);
        foreach ($configuration->fileMounts as $fileMount) {
            foreach ($this->fileTraversing->findFilesInFileMountUidRecursive((int) $fileMount, $extensions) as $file) {
                $this->bus->dispatch(new FileMessage(
                    indexConfigurationRecordId: $configuration->configurationId,
                    fileIdentifier: $file->getCombinedIdentifier(),
                ));
            }
        }
    }

    #[AsMessageHandler]
    public function handleMessage(FileMessage $message): void
    {
        $file = $this->fileTraversing->getFileByCompinedIdentifier($message->fileIdentifier);

        try {
            $content = $this->fileExtractor->extract($file);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return;
        }

        $this->eventDispatcher->dispatch(new IndexFileEvent(
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            title: $file->getNameWithoutExtension(),
            content: $content,
            fileIdentifier: $message->fileIdentifier,
        ));
    }
}
