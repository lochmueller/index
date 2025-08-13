<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class CacheIndexingQueue implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface      $bus,
        private Context                  $context,
        private PageTitleProviderManager $pageTitleProviderManager,
        private ConfigurationLoader    $configurationLoader,
        private FileIndexingQueue $fileIndexingQueue,
    ) {}

    public function fillQueue(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (!$event->isCachingEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $tsfe = $request->getAttribute('frontend.controller');
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $pageInformation = $request->getAttribute('frontend.page.information');

        $configuration = $this->configurationLoader->loadByPageTraversing((int) $pageInformation->getId());
        if ($configuration === null || $configuration->technology !== IndexTechnology::Cache) {
            return;
        }

        if ($configuration->skipNoSearchPages && $pageInformation->getPageRecord()['no_search'] ?? false) {
            return;
        }

        $id = uniqid('cache-index', true);

        $this->bus->dispatch(new StartProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));

        $this->bus->dispatch(new CachePageMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            language: (int) $this->context->getAspect('language')->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: $tsfe->content,
            pageUid: (int) $pageInformation->getId(),
            accessGroups: $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
            indexProcessId: $id,
        ));

        $this->fileIndexingQueue->fillQueue($configuration, $site, $id);

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }
}
