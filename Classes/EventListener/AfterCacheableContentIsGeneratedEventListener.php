<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\EventListener;

use Lochmueller\Indexing\Indexing\Cache\CacheIndexing;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class AfterCacheableContentIsGeneratedEventListener
{
    public function __construct(
        private CacheIndexing $cacheIndexing,
    ) {}

    #[AsEventListener('indexing-cache-indexer')]
    public function indexPageContent(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $this->cacheIndexing->indexPageContentViaAfterCacheableContentIsGeneratedEvent($event);
    }
}
