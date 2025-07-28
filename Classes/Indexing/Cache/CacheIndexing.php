<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Indexing\Cache;

use Lochmueller\Indexing\Enums\IndexTechnology;
use Lochmueller\Indexing\Enums\IndexType;
use Lochmueller\Indexing\Event\EndIndexProcessEvent;
use Lochmueller\Indexing\Event\IndexPageEvent;
use Lochmueller\Indexing\Event\StartIndexProcessEvent;
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
        // @todo move to DB configuration
        $indexType = $site->getConfiguration()['sealIndexType'] ?? '';
        if ($indexType !== 'cache') {
            return;
        }

        $pageInformation = $request->getAttribute('frontend.page.information');
        $pageRecord = $pageInformation->getPageRecord();

        if ($pageRecord['no_search'] ?? false) {
            return;
        }
        $languageAspect = $this->context->getAspect('language');
        if ($languageAspect->getId() !== $languageAspect->getContentId()) {
            // Index page? No, languageId was different from contentId which indicates that the page contains fall-back
            // content and that would be falsely indexed as localized content.
            return;
        }

        $id = uniqid('cache-indexing', true);
        $this->eventDispatcher->dispatch(new StartIndexProcessEvent(IndexTechnology::Cache, IndexType::Partial, $id, microtime(true)));
        $this->eventDispatcher->dispatch(new IndexPageEvent(
            site: $site,
            language: (int) $languageAspect->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: strip_tags($tsfe->content),
            pageUid: (int) $pageInformation->getId(),
            accessGroups: $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
        ));
        $this->eventDispatcher->dispatch(new EndIndexProcessEvent(IndexTechnology::Cache, IndexType::Partial, $id, microtime(true)));
    }
}
