<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class CacheIndexingHandler implements IndexingInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private SiteFinder               $siteFinder,
    ) {}

    #[AsMessageHandler]
    public function __invoke(CachePageMessage $message): void
    {
        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $this->siteFinder->getSiteByIdentifier($message->siteIdentifier),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            language: $message->language,
            title: $message->title,
            content: $message->content,
            pageUid: $message->pageUid,
            accessGroups: $message->accessGroups,
        ));
    }
}
