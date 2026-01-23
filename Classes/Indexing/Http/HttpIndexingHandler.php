<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Http;

use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\HttpIndexMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

class HttpIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SiteFinder               $siteFinder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly HttpRequestBuilder       $httpRequestBuilder,
    ) {}

    #[AsMessageHandler]
    public function __invoke(HttpIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $content = $this->httpRequestBuilder->buildRequestForPage($message->uri);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return;
        }

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
            accessGroups: [], // @todo access_groups
        ));
    }

}
