<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Index\Cache\CacheIndex;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

readonly class AfterCacheableContentIsGeneratedEventListener
{
    public function __construct(
        private CacheIndex $cacheIndex,
    ) {}

    #[AsEventListener('index-cache-indexer')]
    public function indexPageContent(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $this->cacheIndex->indexPageContentViaAfterCacheableContentIsGeneratedEvent($event);
    }
}
