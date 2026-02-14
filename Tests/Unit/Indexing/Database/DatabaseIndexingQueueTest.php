<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingQueue;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\PageTraversing;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexingQueueTest extends AbstractTest
{
    public function testFillQueueDispatchesStartAndFinishMessagesWithNoPages(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        self::assertCount(2, $dispatched);
        self::assertInstanceOf(StartProcessMessage::class, $dispatched[0]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatched[1]);
    }

    public function testFillQueueDispatchesDatabaseIndexMessageForEachPage(): void
    {
        $configuration = new Configuration(
            configurationId: 5,
            pageId: 10,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 2,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('my-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $uri1 = $this->createStub(UriInterface::class);
        $uri2 = $this->createStub(UriInterface::class);

        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(1);

        $info1 = new FrontendInformationDto(
            uri: $uri1,
            arguments: [],
            pageUid: 10,
            language: $language,
            row: [],
            accessGroups: [0, -1],
        );
        $info2 = new FrontendInformationDto(
            uri: $uri2,
            arguments: [],
            pageUid: 20,
            language: $language,
            row: [],
            accessGroups: [3],
        );

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$info1, $info2]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(4))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        self::assertCount(4, $dispatched);
        self::assertInstanceOf(StartProcessMessage::class, $dispatched[0]);
        self::assertInstanceOf(DatabaseIndexMessage::class, $dispatched[1]);
        self::assertInstanceOf(DatabaseIndexMessage::class, $dispatched[2]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatched[3]);
    }

    public function testFillQueueSetsCorrectDataOnDatabaseIndexMessage(): void
    {
        $configuration = new Configuration(
            configurationId: 99,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('data-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $uri = $this->createStub(UriInterface::class);
        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(2);

        $info = new FrontendInformationDto(
            uri: $uri,
            arguments: [],
            pageUid: 42,
            language: $language,
            row: [],
            accessGroups: [5, 7],
        );

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$info]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        /** @var DatabaseIndexMessage $dbMessage */
        $dbMessage = $dispatched[1];
        self::assertSame('data-site', $dbMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Database, $dbMessage->technology);
        self::assertSame(IndexType::Full, $dbMessage->type);
        self::assertSame(99, $dbMessage->indexConfigurationRecordId);
        self::assertSame($uri, $dbMessage->uri);
        self::assertSame(42, $dbMessage->pageUid);
        self::assertSame(2, $dbMessage->language);
        self::assertSame([5, 7], $dbMessage->accessGroups);
    }

    public function testFillQueueUsesOverrideIndexTypeWhenSet(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
            overrideIndexType: IndexType::Partial,
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        /** @var StartProcessMessage $startMessage */
        $startMessage = $dispatched[0];
        self::assertSame(IndexType::Partial, $startMessage->type);

        /** @var FinishProcessMessage $finishMessage */
        $finishMessage = $dispatched[1];
        self::assertSame(IndexType::Partial, $finishMessage->type);
    }

    public function testFillQueueDefaultsToFullIndexType(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        self::assertSame(IndexType::Full, $dispatched[0]->type);
        self::assertSame(IndexType::Full, $dispatched[1]->type);
    }

    public function testFillQueueUsesConsistentProcessIdAcrossAllMessages(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $uri = $this->createStub(UriInterface::class);
        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(0);

        $info = new FrontendInformationDto(
            uri: $uri,
            arguments: [],
            pageUid: 42,
            language: $language,
            row: [],
        );

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([$info]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueueProcessId = null;
        $fileIndexingQueue = $this->createMock(FileIndexingQueue::class);
        $fileIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->willReturnCallback(function (Configuration $conf, $s, string $processId) use (&$fileIndexingQueueProcessId): void {
                $fileIndexingQueueProcessId = $processId;
            });

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        $processId = $dispatched[0]->indexProcessId;
        self::assertNotEmpty($processId);
        self::assertStringStartsWith('database-index', $processId);
        self::assertSame($processId, $dispatched[1]->indexProcessId);
        self::assertSame($processId, $dispatched[2]->indexProcessId);
        self::assertSame($processId, $fileIndexingQueueProcessId);
    }

    public function testFillQueueCallsFileIndexingQueueByDefault(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $bus = $this->createStub(Bus::class);

        $capturedConfiguration = null;
        $capturedSite = null;
        $fileIndexingQueue = $this->createMock(FileIndexingQueue::class);
        $fileIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->willReturnCallback(function (Configuration $conf, $s, string $processId) use (&$capturedConfiguration, &$capturedSite): void {
                $capturedConfiguration = $conf;
                $capturedSite = $s;
            });

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        self::assertSame($configuration, $capturedConfiguration);
        self::assertSame($site, $capturedSite);
    }

    public function testFillQueueSkipsFileIndexingWhenSkipFilesIsTrue(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $bus = $this->createStub(Bus::class);

        $fileIndexingQueue = $this->createMock(FileIndexingQueue::class);
        $fileIndexingQueue->expects(self::never())->method('fillQueue');

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration, skipFiles: true);
    }

    public function testFillQueueSetsCorrectStartAndFinishMessageData(): void
    {
        $configuration = new Configuration(
            configurationId: 77,
            pageId: 100,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('prod-site');

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $pageTraversing = $this->createStub(PageTraversing::class);
        $pageTraversing->method('getFrontendInformation')->willReturn([]);

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new DatabaseIndexingQueue($bus, $siteFinder, $pageTraversing, $fileIndexingQueue);
        $subject->fillQueue($configuration);

        /** @var StartProcessMessage $startMessage */
        $startMessage = $dispatched[0];
        self::assertSame('prod-site', $startMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Database, $startMessage->technology);
        self::assertSame(IndexType::Full, $startMessage->type);
        self::assertSame(77, $startMessage->indexConfigurationRecordId);

        /** @var FinishProcessMessage $finishMessage */
        $finishMessage = $dispatched[1];
        self::assertSame('prod-site', $finishMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Database, $finishMessage->technology);
        self::assertSame(IndexType::Full, $finishMessage->type);
        self::assertSame(77, $finishMessage->indexConfigurationRecordId);
    }
}
