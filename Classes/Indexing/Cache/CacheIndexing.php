<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class CacheIndexing
{
    public function __construct(
        private Context                  $context,
        private PageTitleProviderManager $pageTitleProviderManager,
        private EventDispatcherInterface $eventDispatcher,
        protected ConfigurationLoader $configurationLoader,
    ) {}

    public function indexPageContentViaAfterCacheableContentIsGeneratedEvent(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (!$event->isCachingEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $tsfe = $request->getAttribute('frontend.controller');
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $pageInformation = $request->getAttribute('frontend.page.information');

        $configuration = $this->configurationLoader->loadByPage((int) $pageInformation->getId());
        if ($configuration === null || $configuration->technology !== IndexTechnology::Cache) {
            return;
        }

        if ($configuration->skipNoSearchPages && $pageInformation->getPageRecord()['no_search'] ?? false) {
            return;
        }

        $id = uniqid('cache-index', true);

        $this->eventDispatcher->dispatch(new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
            startTime: microtime(true),
        ));
        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            language: (int) $this->context->getAspect('language')->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: $tsfe->content,
            pageUid: (int) $pageInformation->getId(),
            accessGroups: $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
        ));

        $this->eventDispatcher->dispatch(new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $id,
            endTime: microtime(true),
        ));
    }
}
