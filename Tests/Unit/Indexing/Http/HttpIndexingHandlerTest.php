<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Http;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\Http\HttpIndexingHandler;
use Lochmueller\Index\Indexing\Http\HttpRequestBuilder;
use Lochmueller\Index\Queue\Message\HttpIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class HttpIndexingHandlerTest extends AbstractTest
{
    public function testInvokeDispatchesIndexPageEvent(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $httpRequestBuilder = $this->createStub(HttpRequestBuilder::class);
        $httpRequestBuilder->method('buildRequestForPage')
            ->willReturn('<html><title>Test Title</title><body>content</body></html>');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->site === $site
                    && $event->technology === IndexTechnology::Http
                    && $event->type === IndexType::Full
                    && $event->title === 'Test Title'
                    && $event->pageUid === 42));

        $subject = new HttpIndexingHandler($siteFinder, $eventDispatcher, $httpRequestBuilder);

        $message = new HttpIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 42,
            indexProcessId: 'process-123',
            accessGroups: [0, -1],
        );

        $subject->__invoke($message);
    }

    public function testInvokeDispatchesEventWithEmptyContent(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $httpRequestBuilder = $this->createStub(HttpRequestBuilder::class);
        $httpRequestBuilder->method('buildRequestForPage')->willReturn('');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->content === '' && $event->title === ''));

        $subject = new HttpIndexingHandler($siteFinder, $eventDispatcher, $httpRequestBuilder);

        $message = new HttpIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 42,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyOnSiteFinderException(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willThrowException(new \Exception('Site not found'));

        $httpRequestBuilder = $this->createStub(HttpRequestBuilder::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new HttpIndexingHandler($siteFinder, $eventDispatcher, $httpRequestBuilder);

        $message = new HttpIndexMessage(
            siteIdentifier: 'invalid-site',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 42,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeExtractsTitleFromHtml(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $httpRequestBuilder = $this->createStub(HttpRequestBuilder::class);
        $httpRequestBuilder->method('buildRequestForPage')
            ->willReturn('<html><head><title>Extracted Title</title></head></html>');

        $capturedEvent = null;
        $eventDispatcher = new class ($capturedEvent) implements EventDispatcherInterface {
            public function __construct(private mixed &$capturedEvent) {}

            public function dispatch(object $event): object
            {
                $this->capturedEvent = $event;
                return $event;
            }
        };

        $subject = new HttpIndexingHandler($siteFinder, $eventDispatcher, $httpRequestBuilder);

        $message = new HttpIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 1,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);

        self::assertSame('Extracted Title', $capturedEvent->title);
    }

    public function testInvokeUsesEmptyTitleWhenNoTitleTag(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $httpRequestBuilder = $this->createStub(HttpRequestBuilder::class);
        $httpRequestBuilder->method('buildRequestForPage')->willReturn('<html><body>No title</body></html>');

        $capturedEvent = null;
        $eventDispatcher = new class ($capturedEvent) implements EventDispatcherInterface {
            public function __construct(private mixed &$capturedEvent) {}

            public function dispatch(object $event): object
            {
                $this->capturedEvent = $event;
                return $event;
            }
        };

        $subject = new HttpIndexingHandler($siteFinder, $eventDispatcher, $httpRequestBuilder);

        $message = new HttpIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 1,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);

        self::assertSame('', $capturedEvent->title);
    }
}
