<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\ContentProcessing\ContentProcessor;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

class CacheIndexingHandler implements IndexingInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SiteFinder               $siteFinder,
        private readonly ContentProcessor         $contentProcessor,
        private readonly ConfigurationLoader      $configurationLoader,
    ) {}

    #[AsMessageHandler]
    public function __invoke(CachePageMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);

            $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
            $content = $this->contentProcessor->process(
                $message->content,
                $configuration?->contentProcessors ?? [],
            );

            $this->eventDispatcher->dispatch(new IndexPageEvent(
                site: $site,
                technology: IndexTechnology::Cache,
                type: IndexType::Partial,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                language: $message->language,
                title: $message->title,
                content: $content,
                pageUid: $message->pageUid,
                accessGroups: $message->accessGroups,
            ));
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
