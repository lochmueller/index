<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\External;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

class ExternalIndexingHandler
{
    public function __construct(
        private SiteFinder               $siteFinder,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsMessageHandler]
    public function fileIndexing(ExternalFileIndexMessage $message): void
    {

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
    }

    #[AsMessageHandler]
    public function pageIndexing(ExternalPageIndexMessage $message): void
    {
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
            accessGroups: [],
            uri: $message->uri,
        ));

    }
}
