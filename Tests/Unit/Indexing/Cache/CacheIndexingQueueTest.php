<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Cache;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\Cache\CacheIndexingQueue;
use Lochmueller\Index\Indexing\File\FileIndexingQueue;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;

class CacheIndexingQueueTest extends AbstractTest
{
    public function testFillQueueReturnsEarlyWhenCachingDisabled(): void
    {
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $request = $this->createStub(ServerRequestInterface::class);
        $tsfe = $this->createStub(TypoScriptFrontendController::class);
        $event = new AfterCacheableContentIsGeneratedEvent($request, $tsfe, 'cache-id', false);

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

        $tsfe = $this->createStub(TypoScriptFrontendController::class);
        $event = new AfterCacheableContentIsGeneratedEvent($request, $tsfe, 'cache-id', true);

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

        $tsfe = $this->createStub(TypoScriptFrontendController::class);
        $event = new AfterCacheableContentIsGeneratedEvent($request, $tsfe, 'cache-id', true);

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

        $tsfe = $this->createStub(TypoScriptFrontendController::class);
        $event = new AfterCacheableContentIsGeneratedEvent($request, $tsfe, 'cache-id', true);

        $subject = new CacheIndexingQueue(
            $bus,
            $this->createStub(Context::class),
            $this->createStub(PageTitleProviderManager::class),
            $configurationLoader,
            $this->createStub(FileIndexingQueue::class),
        );

        $subject->fillQueue($event);
    }
}
