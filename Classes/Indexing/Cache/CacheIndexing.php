<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class CacheIndexing implements IndexingInterface
{
    public function __construct(
        private MessageBusInterface      $bus,
        private Context                  $context,
        private PageTitleProviderManager $pageTitleProviderManager,
        private EventDispatcherInterface $eventDispatcher,
        protected ConfigurationLoader    $configurationLoader,
        protected SiteFinder             $siteFinder,
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
        ));

        $this->bus->dispatch(new FinishProcessMessage(
            siteIdentifier: $site->getIdentifier(),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
        ));
    }

    #[AsMessageHandler]
    public function handleMessage(CachePageMessage $message)
    {
        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $this->siteFinder->getSiteByIdentifier($message->siteIdentifier),
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            language: $message->language,
            title: $message->title,
            content: $message->content,
            pageUid: $message->pageUid,
            accessGroups: $message->accessGroups,
        ));
    }
}
