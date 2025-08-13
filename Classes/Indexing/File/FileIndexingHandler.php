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
use TYPO3\CMS\Core\Site\SiteFinder;

class FileIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FileTraversing           $fileTraversing,
        private readonly FileExtractor            $fileExtractor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SiteFinder $siteFinder,
    ) {}


    #[AsMessageHandler]
    public function __invoke(FileMessage $message): void
    {
        $file = $this->fileTraversing->getFileByCompinedIdentifier($message->fileIdentifier);

        $base = [
            $file->getProperty('title'),
            $file->getProperty('alternative'),
            $file->getProperty('description'),
            $file->getProperty('name'),
        ];

        $content = implode(' ', $base);
        try {
            $content .= $this->fileExtractor->extract($file);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return;
        }

        $this->eventDispatcher->dispatch(new IndexFileEvent(
            site: $this->siteFinder->getSiteByIdentifier($message->siteIdentifier),
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            title: $file->getNameWithoutExtension(),
            content: $content,
            indexProcessId: $message->indexProcessId,
            fileIdentifier: $message->fileIdentifier,
        ));
    }
}
