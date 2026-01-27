<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Frontend;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\PageTraversing;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class FrontendIndexingQueueTest extends AbstractTest
{
    private function createConfiguration(?IndexType $overrideIndexType = null): Configuration
    {
        return new Configuration(
            configurationId: 42,
            pageId: 1,
            technology: IndexTechnology::Frontend,
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

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[1]);
    }

    public function testFillQueueDispatchesFrontendIndexMessages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $frontendInfo = $this->createFrontendInformationDto(pageUid: 123, accessGroups: [1, 2]);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$frontendInfo]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $dispatchedMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessages): void {
                $dispatchedMessages[] = $message;
            });

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertInstanceOf(StartProcessMessage::class, $dispatchedMessages[0]);
        self::assertInstanceOf(FrontendIndexMessage::class, $dispatchedMessages[1]);
        self::assertSame('test-site', $dispatchedMessages[1]->siteIdentifier);
        self::assertSame(123, $dispatchedMessages[1]->pageUid);
        self::assertSame([1, 2], $dispatchedMessages[1]->accessGroups);
        self::assertSame(42, $dispatchedMessages[1]->indexConfigurationRecordId);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatchedMessages[2]);
    }

    public function testFillQueueUsesOverrideIndexType(): void
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

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(IndexType::Partial));

        self::assertSame(IndexType::Partial, $dispatchedMessages[0]->type);
    }

    public function testFillQueueDefaultsToFullIndexType(): void
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

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertSame(IndexType::Full, $dispatchedMessages[0]->type);
    }

    public function testFillQueueCallsFileIndexingWhenNotSkipped(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $fileIndexing = $this->createMock(FileIndexingQueue::class);
        $fileIndexing->expects(self::once())
            ->method('fillQueue')
            ->with(
                self::isInstanceOf(Configuration::class),
                self::identicalTo($site),
                self::isType('string'),
            );

        $bus = $this->createStub(Bus::class);

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());
    }

    public function testFillQueueSkipsFileIndexingWhenRequested(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $fileIndexing = $this->createMock(FileIndexingQueue::class);
        $fileIndexing->expects(self::never())->method('fillQueue');

        $bus = $this->createStub(Bus::class);

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration(), skipFiles: true);
    }

    public function testFillQueueDispatchesMultipleFrontendIndexMessages(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $frontendInfo1 = $this->createFrontendInformationDto(pageUid: 1);
        $frontendInfo2 = $this->createFrontendInformationDto(pageUid: 2);
        $frontendInfo3 = $this->createFrontendInformationDto(pageUid: 3);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([
            $frontendInfo1,
            $frontendInfo2,
            $frontendInfo3,
        ]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $frontendMessages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(5))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$frontendMessages): void {
                if ($message instanceof FrontendIndexMessage) {
                    $frontendMessages[] = $message;
                }
            });

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertCount(3, $frontendMessages);
        self::assertSame(1, $frontendMessages[0]->pageUid);
        self::assertSame(2, $frontendMessages[1]->pageUid);
        self::assertSame(3, $frontendMessages[2]->pageUid);
    }

    public function testFillQueueUsesConsistentProcessId(): void
    {
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $frontendInfo = $this->createFrontendInformationDto();

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$frontendInfo]);

        $fileIndexing = $this->createStub(FileIndexingQueue::class);

        $processIds = [];
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$processIds): void {
                $processIds[] = $message->indexProcessId;
            });

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertCount(3, $processIds);
        self::assertSame($processIds[0], $processIds[1]);
        self::assertSame($processIds[1], $processIds[2]);
        self::assertStringStartsWith('frontend-index', $processIds[0]);
    }

    public function testFillQueueSetsCorrectTechnology(): void
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

        $subject = new FrontendIndexingQueue($bus, $fileIndexing, $pageTraversing, $siteFinder);
        $subject->fillQueue($this->createConfiguration());

        self::assertSame(IndexTechnology::Frontend, $dispatchedMessages[0]->technology);
        self::assertSame(IndexTechnology::Frontend, $dispatchedMessages[1]->technology);
    }
}
