<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\External;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

class ExternalIndexingHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private SiteFinder               $siteFinder,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsMessageHandler]
    public function fileIndexing(ExternalFileIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $this->eventDispatcher->dispatch(new IndexFileEvent(
                site: $site,
                indexConfigurationRecordId: -1,
                indexProcessId: $message->indexProcessId,
                title: $message->title,
                content: $message->content,
                fileIdentifier: '',
                uri: $message->uri,
            ));
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

    #[AsMessageHandler]
    public function pageIndexing(ExternalPageIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $this->eventDispatcher->dispatch(new IndexPageEvent(
                site: $site,
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: -1,
                indexProcessId: $message->indexProcessId,
                language: 0,
                title: $message->title,
                content: $message->content,
                pageUid: -1,
                accessGroups: $message->accessGroups,
                uri: $message->uri,
            ));
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
