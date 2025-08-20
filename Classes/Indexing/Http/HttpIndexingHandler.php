<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Http;

use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\HttpIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class HttpIndexingHandler implements IndexingInterface
{
    public function __construct(
        private SiteFinder               $siteFinder,
        private EventDispatcherInterface $eventDispatcher,
        private HttpRequestBuilder       $httpRequestBuilder,
    ) {}

    #[AsMessageHandler]
    public function __invoke(HttpIndexMessage $message): void
    {
        $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

        $content = $this->httpRequestBuilder->buildRequestForPage($message->uri);

        $title = '';
        if (preg_match('/<title>([^>]*)<\/title>/', $content, $matches)) {
            $title = $matches[1];
        }

        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            language: 0,
            title: $title,
            content: $content,
            pageUid: $message->pageUid,
            accessGroups: [],
        ));
    }

}
