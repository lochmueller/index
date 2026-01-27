<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Http;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\Http\HttpIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\HttpIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\PageTraversing;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class HttpIndexingQueueTest extends AbstractTest
{
    private function createConfiguration(int $pageId = 1, ?IndexType $overrideIndexType = null): Configuration
    {
        return new Configuration(
            configurationId: 42,
            pageId: $pageId,
            technology: IndexTechnology::Http,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
            overrideIndexType: $overrideIndexType,
        );
    }

    private function createFrontendInformationDto(int $pageUid = 1, array $accessGroups = []): FrontendInformationDto
    {
        $uri = $this->createStub(UriInterface::class);
        $language = $this->createStub(SiteLanguage::class);

        return new FrontendInformationDto(
            uri: $uri,
            arguments: [],
            pageUid: $pageUid,
            language: $language,
            row: [],
            accessGroups: $accessGroups,
        );
    }

    public function testFillQueueDispatchesStartAndFinishMessages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $dispatchedMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), true);

        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[1]);
        self::assertSame('test-site', $dispatchedMessages[0]->siteIdentifier);
        self::assertSame(IndexTechnology::Http, $dispatchedMessages[0]->technology);
        self::assertSame(IndexType::Full, $dispatchedMessages[0]->type);
        self::assertSame(42, $dispatchedMessages[0]->indexConfigurationRecordId);
    }

    public function testFillQueueDispatchesHttpIndexMessagesForEachPage(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $frontendInfo1 = $this->createFrontendInformationDto(10, [1, 2]);
        $frontendInfo2 = $this->createFrontendInformationDto(20, [3]);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$frontendInfo1, $frontendInfo2]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $httpMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(4))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$httpMessages): void {
                if ($message instanceof HttpIndexMessage) {
                    $httpMessages[] = $message;
                }
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), true);

        self::assertCount(2, $httpMessages);
        self::assertSame(10, $httpMessages[0]->pageUid);
        self::assertSame([1, 2], $httpMessages[0]->accessGroups);
        self::assertSame(20, $httpMessages[1]->pageUid);
        self::assertSame([3], $httpMessages[1]->accessGroups);
    }

    public function testFillQueueCallsFileIndexingWhenNotSkipped(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $bus = $this->createStub(Bus::class);

        $fileIndexingCalled = false;
        $fileIndexing = $this->createStub(FileIndexingQueue::class);
        $fileIndexing->method('fillQueue')
            ->willReturnCallback(function () use (&$fileIndexingCalled): void {
                $fileIndexingCalled = true;
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), false);

        self::assertTrue($fileIndexingCalled);
    }

    public function testFillQueueSkipsFileIndexingWhenFlagIsTrue(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $bus = $this->createStub(Bus::class);

        $fileIndexingCalled = false;
        $fileIndexing = $this->createStub(FileIndexingQueue::class);
        $fileIndexing->method('fillQueue')
            ->willReturnCallback(function () use (&$fileIndexingCalled): void {
                $fileIndexingCalled = true;
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), true);

        self::assertFalse($fileIndexingCalled);
    }

    public function testFillQueueUsesOverrideIndexTypeWhenSet(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $dispatchedMessages = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(1, IndexType::Partial), true);

        self::assertSame(IndexType::Partial, $dispatchedMessages[0]->type);
    }

    public function testFillQueueUsesCorrectSiteFromPageId(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('my-custom-site');

        $siteFinderCalled = false;
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')
            ->willReturnCallback(function (int $pageId) use (&$siteFinderCalled, $site): Site {
                $siteFinderCalled = true;
                self::assertSame(123, $pageId);
                return $site;
            });

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);
        $bus = $this->createStub(Bus::class);

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(123), true);

        self::assertTrue($siteFinderCalled);
    }

    public function testFillQueueSharesProcessIdAcrossAllMessages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $frontendInfo = $this->createFrontendInformationDto(10);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$frontendInfo]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $processIds = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$processIds): void {
                $processIds[] = $message->indexProcessId;
            });

        $subject = new HttpIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), true);

        self::assertCount(3, $processIds);
        self::assertSame($processIds[0], $processIds[1]);
        self::assertSame($processIds[1], $processIds[2]);
        self::assertStringStartsWith('http-index', $processIds[0]);
    }
}
