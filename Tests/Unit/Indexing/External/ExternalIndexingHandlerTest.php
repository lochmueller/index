<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\External;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\External\ExternalIndexingHandler;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class ExternalIndexingHandlerTest extends AbstractTest
{
    public function testFileIndexingDispatchesIndexFileEvent(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexFileEvent $event): bool => $event->site === $site
                    && $event->indexConfigurationRecordId === -1
                    && $event->indexProcessId === 'process-123'
                    && $event->title === 'Test File'
                    && $event->content === 'File content'
                    && $event->fileIdentifier === ''
                    && $event->uri === 'https://example.com/file.pdf'));

        $subject = new ExternalIndexingHandler($siteFinder, $eventDispatcher);

        $message = new ExternalFileIndexMessage(
            siteIdentifier: 'test-site',
            language: 0,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            uri: 'https://example.com/file.pdf',
            title: 'Test File',
            content: 'File content',
            indexProcessId: 'process-123',
        );

        $subject->fileIndexing($message);
    }

    public function testPageIndexingDispatchesIndexPageEvent(): void
    {
        $site = $this->createStub(Site::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->site === $site
                    && $event->technology === IndexTechnology::External
                    && $event->type === IndexType::Partial
                    && $event->indexConfigurationRecordId === -1
                    && $event->indexProcessId === 'process-123'
                    && $event->language === 0
                    && $event->title === 'Test Page'
                    && $event->content === 'Page content'
                    && $event->pageUid === -1
                    && $event->accessGroups === [0, -1]
                    && $event->uri === 'https://example.com/page'));

        $subject = new ExternalIndexingHandler($siteFinder, $eventDispatcher);

        $message = new ExternalPageIndexMessage(
            siteIdentifier: 'test-site',
            language: 0,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            uri: 'https://example.com/page',
            title: 'Test Page',
            content: 'Page content',
            indexProcessId: 'process-123',
            accessGroups: [0, -1],
        );

        $subject->pageIndexing($message);
    }
}
