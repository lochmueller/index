<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Frontend;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexingHandler;
use Lochmueller\Index\Indexing\Frontend\FrontendRequestBuilder;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class FrontendIndexingHandlerTest extends AbstractTest
{
    public function testInvokeDispatchesIndexPageEvent(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $frontendRequestBuilder = $this->createStub(FrontendRequestBuilder::class);
        $frontendRequestBuilder->method('buildRequestForPage')
            ->willReturn('<html><title>Test Title</title><body>content</body></html>');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->site === $site
                    && $event->technology === IndexTechnology::Frontend
                    && $event->type === IndexType::Full
                    && $event->title === 'Test Title'
                    && $event->pageUid === 42));

        $subject = new FrontendIndexingHandler($siteFinder, $frontendRequestBuilder, $eventDispatcher);

        $message = new FrontendIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 42,
            indexProcessId: 'process-123',
            accessGroups: [0, -1],
        );

        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyWhenContentIsNull(): void
    {
        $site = $this->createStub(Site::class);
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $frontendRequestBuilder = $this->createStub(FrontendRequestBuilder::class);
        $frontendRequestBuilder->method('buildRequestForPage')->willReturn(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new FrontendIndexingHandler($siteFinder, $frontendRequestBuilder, $eventDispatcher);

        $message = new FrontendIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 42,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyOnException(): void
    {
        $uri = $this->createStub(UriInterface::class);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willThrowException(new \Exception('Site not found'));

        $frontendRequestBuilder = $this->createStub(FrontendRequestBuilder::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $subject = new FrontendIndexingHandler($siteFinder, $frontendRequestBuilder, $eventDispatcher);

        $message = new FrontendIndexMessage(
            siteIdentifier: 'invalid-site',
            technology: IndexTechnology::Frontend,
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

        $frontendRequestBuilder = $this->createStub(FrontendRequestBuilder::class);
        $frontendRequestBuilder->method('buildRequestForPage')
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

        $subject = new FrontendIndexingHandler($siteFinder, $frontendRequestBuilder, $eventDispatcher);

        $message = new FrontendIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            uri: $uri,
            pageUid: 1,
            indexProcessId: 'process-123',
        );

        $subject->__invoke($message);

        self::assertSame('Extracted Title', $capturedEvent->title);
    }
}
