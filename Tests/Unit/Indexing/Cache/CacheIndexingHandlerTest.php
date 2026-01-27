<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Cache;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\Cache\CacheIndexingHandler;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class CacheIndexingHandlerTest extends AbstractTest
{
    public function testInvokeDispatchesIndexPageEvent(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->site === $site
                    && $event->technology === IndexTechnology::Cache
                    && $event->type === IndexType::Partial
                    && $event->indexConfigurationRecordId === 1
                    && $event->indexProcessId === 'process-123'
                    && $event->language === 0
                    && $event->title === 'Test Page'
                    && $event->content === '<html>content</html>'
                    && $event->pageUid === 42
                    && $event->accessGroups === [0, -1]));

        $subject = new CacheIndexingHandler($eventDispatcher, $siteFinder);

        $message = new CachePageMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            language: 0,
            title: 'Test Page',
            content: '<html>content</html>',
            pageUid: 42,
            accessGroups: [0, -1],
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyOnSiteFinderException(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willThrowException(new \Exception('Site not found'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new CacheIndexingHandler($eventDispatcher, $siteFinder);

        $message = new CachePageMessage(
            siteIdentifier: 'invalid-site',
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            language: 0,
            title: 'Test',
            content: 'content',
            pageUid: 1,
            accessGroups: [],
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }
}
