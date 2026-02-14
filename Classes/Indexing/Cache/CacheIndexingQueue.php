<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Cache;

use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class CacheIndexingQueue implements IndexingInterface
{
    public function __construct(
        private Bus      $bus,
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
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $pageInformation = $request->getAttribute('frontend.page.information');

        $configuration = $this->configurationLoader->loadByPageTraversing((int) $pageInformation->getId());
        if ($configuration === null || $configuration->technology !== IndexTechnology::Cache) {
            return;
        }

        if ($configuration->skipNoSearchPages && ($pageInformation->getPageRecord()['no_search'] ?? false)) {
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
            language: $this->context->getAspect('language')->getId(),
            title: $this->pageTitleProviderManager->getTitle($request),
            content: $this->resolveContent($event),
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

    /**
     * Resolves page content from the event, compatible with TYPO3 v13 and v14.
     *
     * v14 provides getContent() directly on the event.
     * v13 requires fetching content from TypoScriptFrontendController via getController().
     */
    private function resolveContent(AfterCacheableContentIsGeneratedEvent $event): string
    {
        // TYPO3 v14+ provides getContent() directly on the event
        // TYPO3 v13 requires fetching content from TypoScriptFrontendController via getController()
        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($event, 'getContent')) {
            return $event->getContent();
        }

        // @phpstan-ignore method.notFound
        return $event->getController()->content;
    }
}
