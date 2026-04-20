<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Http;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\ContentProcessing\ContentProcessor;
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
        private readonly ContentProcessor         $contentProcessor,
        private readonly ConfigurationLoader      $configurationLoader,
    ) {}

    #[AsMessageHandler]
    public function __invoke(HttpIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $content = $this->httpRequestBuilder->buildRequestForPage($message->uri);

            $title = '';
            if (preg_match('/<title\b[^>]*>([\s\S]*?)<\/title>/i', $content, $matches)) {
                $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
            $content = $this->contentProcessor->process($content, $configuration?->contentProcessors ?? []);

            $this->eventDispatcher->dispatch(new IndexPageEvent(
                site: $site,
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                language: $message->language,
                title: $title,
                content: $content,
                pageUid: $message->pageUid,
                accessGroups: $message->accessGroups,
            ));
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

}
