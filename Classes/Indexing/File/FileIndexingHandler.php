<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\File;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\FileExtraction\FileExtractor;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Traversing\FileTraversing;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

class FileIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FileTraversing           $fileTraversing,
        private readonly FileExtractor            $fileExtractor,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}


    #[AsMessageHandler]
    public function __invoke(FileMessage $message): void
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
