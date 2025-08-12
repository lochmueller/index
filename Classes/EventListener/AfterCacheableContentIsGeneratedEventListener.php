<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Indexing\Cache\CacheIndexingQueue;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

final readonly class AfterCacheableContentIsGeneratedEventListener
{
    public function __construct(
        private CacheIndexingQueue $cacheIndex,
    ) {}

    #[AsEventListener('index-cache-indexer')]
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $this->cacheIndex->fillQueue($event);
    }
}
