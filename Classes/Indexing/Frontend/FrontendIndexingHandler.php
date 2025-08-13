<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class FrontendIndexingHandler implements IndexingInterface
{
    public function __construct(
        private SiteFinder               $siteFinder,
        private FrontendRequestBuilder   $frontendRequestBuilder,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsMessageHandler]
    public function __invoke(FrontendIndexMessage $message): void
    {
        $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

        $content = $this->frontendRequestBuilder->buildRequestForPage($message->uri);

        $title = '';
        if (preg_match('/<title>([^>]*)<\/title>/', $content, $matches)) {
            $title = $matches[1];
        }

        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            language: 0,
            title: $title,
            content: $content,
            pageUid: $message->pageUid,
            accessGroups: [],
        ));
    }

}
