<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Cache;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\Cache\CacheIndexingQueue;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;

class CacheIndexingQueueTest extends AbstractTest
{
    /**
     * Creates an AfterCacheableContentIsGeneratedEvent compatible with TYPO3 v13 and v14.
     *
     * v13: __construct(ServerRequestInterface, TypoScriptFrontendController, string, bool)
     * v14: __construct(ServerRequestInterface, string, string, bool)
     */
    private function createCacheEvent(ServerRequestInterface $request, string $content, bool $usePageCache): AfterCacheableContentIsGeneratedEvent
    {
        // @phpstan-ignore-next-line
        if (class_exists(\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class)) {
            $tsfe = $this->createStub(\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class);
            $tsfe->content = $content;
            return new AfterCacheableContentIsGeneratedEvent($request, $tsfe, 'cache-id', $usePageCache);
        }

        return new AfterCacheableContentIsGeneratedEvent($request, $content, 'cache-id', $usePageCache);
    }

    public function testFillQueueReturnsEarlyWhenCachingDisabled(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $request = $this->createStub(ServerRequestInterface::class);
        $event = $this->createCacheEvent($request, '', false);

        $subject = new CacheIndexingQueue(
            $bus,
            $this->createStub(Context::class),
            $this->createStub(PageTitleProviderManager::class),
            $this->createStub(ConfigurationLoader::class),
            $this->createStub(FileIndexingQueue::class),
        );

        $subject->fillQueue($event);
    }

    public function testFillQueueReturnsEarlyWhenNoConfiguration(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn(null);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
        ]);

        $event = $this->createCacheEvent($request, '', true);

        $subject = new CacheIndexingQueue(
            $bus,
            $this->createStub(Context::class),
            $this->createStub(PageTitleProviderManager::class),
            $configurationLoader,
            $this->createStub(FileIndexingQueue::class),
        );

        $subject->fillQueue($event);
    }

    public function testFillQueueReturnsEarlyWhenTechnologyIsNotCache(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

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

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
        ]);

        $event = $this->createCacheEvent($request, '', true);

        $subject = new CacheIndexingQueue(
            $bus,
            $this->createStub(Context::class),
            $this->createStub(PageTitleProviderManager::class),
            $configurationLoader,
            $this->createStub(FileIndexingQueue::class),
        );

        $subject->fillQueue($event);
    }

    public function testFillQueueReturnsEarlyWhenSkipNoSearchPagesAndPageHasNoSearch(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: true,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $pageInformation->setPageRecord(['no_search' => true]);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
        ]);

        $event = $this->createCacheEvent($request, '', true);

        $subject = new CacheIndexingQueue(
            $bus,
            $this->createStub(Context::class),
            $this->createStub(PageTitleProviderManager::class),
            $configurationLoader,
            $this->createStub(FileIndexingQueue::class),
        );

        $subject->fillQueue($event);
    }

    public function testFillQueueDispatchesStartCacheAndFinishMessages(): void
    {
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 42,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $pageInformation->setPageRecord(['no_search' => false]);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
            ['site', null, $site],
        ]);

        $context = $this->createStub(Context::class);
        $context->method('getAspect')->willReturnMap([
            ['language', new LanguageAspect(1)],
        ]);
        $context->method('getPropertyFromAspect')->willReturnMap([
            ['frontend.user', 'groupIds', [0, -1], [0, -1]],
        ]);

        $pageTitleProviderManager = $this->createStub(PageTitleProviderManager::class);
        $pageTitleProviderManager->method('getTitle')->willReturn('Test Page Title');

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createMock(FileIndexingQueue::class);
        $fileIndexingQueue->expects(self::once())
            ->method('fillQueue')
            ->with($configuration, $site, self::isString());

        $subject = new CacheIndexingQueue(
            $bus,
            $context,
            $pageTitleProviderManager,
            $configurationLoader,
            $fileIndexingQueue,
        );

        $event = $this->createCacheEvent($request, '<p>Test content</p>', true);
        $subject->fillQueue($event);

        self::assertCount(3, $dispatched);
        self::assertInstanceOf(StartProcessMessage::class, $dispatched[0]);
        self::assertInstanceOf(CachePageMessage::class, $dispatched[1]);
        self::assertInstanceOf(FinishProcessMessage::class, $dispatched[2]);
    }

    public function testFillQueueDispatchesMessagesWithCorrectData(): void
    {
        $configuration = new Configuration(
            configurationId: 99,
            pageId: 10,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(10);
        $pageInformation->setPageRecord([]);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('my-site');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
            ['site', null, $site],
        ]);

        $context = $this->createStub(Context::class);
        $context->method('getAspect')->willReturnMap([
            ['language', new LanguageAspect(2)],
        ]);
        $context->method('getPropertyFromAspect')->willReturnMap([
            ['frontend.user', 'groupIds', [0, -1], [3, 5]],
        ]);

        $pageTitleProviderManager = $this->createStub(PageTitleProviderManager::class);
        $pageTitleProviderManager->method('getTitle')->willReturn('My Title');

        $dispatched = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): void {
                $dispatched[] = $message;
            });

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new CacheIndexingQueue(
            $bus,
            $context,
            $pageTitleProviderManager,
            $configurationLoader,
            $fileIndexingQueue,
        );

        $event = $this->createCacheEvent($request, 'page body', true);
        $subject->fillQueue($event);

        /** @var StartProcessMessage $startMessage */
        $startMessage = $dispatched[0];
        self::assertSame('my-site', $startMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Cache, $startMessage->technology);
        self::assertSame(IndexType::Partial, $startMessage->type);
        self::assertSame(99, $startMessage->indexConfigurationRecordId);
        self::assertStringStartsWith('cache-index', $startMessage->indexProcessId);

        /** @var CachePageMessage $cacheMessage */
        $cacheMessage = $dispatched[1];
        self::assertSame('my-site', $cacheMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Cache, $cacheMessage->technology);
        self::assertSame(IndexType::Partial, $cacheMessage->type);
        self::assertSame(99, $cacheMessage->indexConfigurationRecordId);
        self::assertSame(2, $cacheMessage->language);
        self::assertSame('My Title', $cacheMessage->title);
        self::assertSame('page body', $cacheMessage->content);
        self::assertSame(10, $cacheMessage->pageUid);
        self::assertSame([3, 5], $cacheMessage->accessGroups);
        self::assertSame($startMessage->indexProcessId, $cacheMessage->indexProcessId);

        /** @var FinishProcessMessage $finishMessage */
        $finishMessage = $dispatched[2];
        self::assertSame('my-site', $finishMessage->siteIdentifier);
        self::assertSame(IndexTechnology::Cache, $finishMessage->technology);
        self::assertSame(IndexType::Partial, $finishMessage->type);
        self::assertSame(99, $finishMessage->indexConfigurationRecordId);
        self::assertSame($startMessage->indexProcessId, $finishMessage->indexProcessId);
    }

    public function testFillQueueUsesConsistentProcessIdAcrossAllMessages(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $pageInformation->setPageRecord([]);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
            ['site', null, $site],
        ]);

        $context = $this->createStub(Context::class);
        $context->method('getAspect')->willReturnMap([
            ['language', new LanguageAspect(0)],
        ]);
        $context->method('getPropertyFromAspect')->willReturnMap([
            ['frontend.user', 'groupIds', [0, -1], [0, -1]],
        ]);

        $pageTitleProviderManager = $this->createStub(PageTitleProviderManager::class);
        $pageTitleProviderManager->method('getTitle')->willReturn('');

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

        $subject = new CacheIndexingQueue(
            $bus,
            $context,
            $pageTitleProviderManager,
            $configurationLoader,
            $fileIndexingQueue,
        );

        $event = $this->createCacheEvent($request, '', true);
        $subject->fillQueue($event);

        $processId = $dispatched[0]->indexProcessId;
        self::assertNotEmpty($processId);
        self::assertSame($processId, $dispatched[1]->indexProcessId);
        self::assertSame($processId, $dispatched[2]->indexProcessId);
        self::assertSame($processId, $fileIndexingQueueProcessId);
    }

    public function testFillQueuePassesConfigurationAndSiteToFileIndexingQueue(): void
    {
        $configuration = new Configuration(
            configurationId: 5,
            pageId: 100,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: ['1', '2'],
            fileTypes: ['pdf', 'docx'],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(100);
        $pageInformation->setPageRecord([]);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('file-site');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
            ['site', null, $site],
        ]);

        $context = $this->createStub(Context::class);
        $context->method('getAspect')->willReturnMap([
            ['language', new LanguageAspect(0)],
        ]);
        $context->method('getPropertyFromAspect')->willReturnMap([
            ['frontend.user', 'groupIds', [0, -1], [0, -1]],
        ]);

        $pageTitleProviderManager = $this->createStub(PageTitleProviderManager::class);
        $pageTitleProviderManager->method('getTitle')->willReturn('');

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

        $subject = new CacheIndexingQueue(
            $bus,
            $context,
            $pageTitleProviderManager,
            $configurationLoader,
            $fileIndexingQueue,
        );

        $event = $this->createCacheEvent($request, 'content', true);
        $subject->fillQueue($event);

        self::assertSame($configuration, $capturedConfiguration);
        self::assertSame($site, $capturedSite);
    }

    public function testFillQueueDoesNotSkipWhenNoSearchIsFalse(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 42,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: true,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByPageTraversing')->willReturn($configuration);

        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $pageInformation->setPageRecord(['no_search' => false]);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('site');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnMap([
            ['frontend.page.information', null, $pageInformation],
            ['site', null, $site],
        ]);

        $context = $this->createStub(Context::class);
        $context->method('getAspect')->willReturnMap([
            ['language', new LanguageAspect(0)],
        ]);
        $context->method('getPropertyFromAspect')->willReturnMap([
            ['frontend.user', 'groupIds', [0, -1], [0, -1]],
        ]);

        $pageTitleProviderManager = $this->createStub(PageTitleProviderManager::class);
        $pageTitleProviderManager->method('getTitle')->willReturn('');

        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))->method('dispatch');

        $fileIndexingQueue = $this->createStub(FileIndexingQueue::class);

        $subject = new CacheIndexingQueue(
            $bus,
            $context,
            $pageTitleProviderManager,
            $configurationLoader,
            $fileIndexingQueue,
        );

        $event = $this->createCacheEvent($request, '', true);
        $subject->fillQueue($event);
    }
}
