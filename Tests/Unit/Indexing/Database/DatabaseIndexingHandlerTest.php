<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingHandler;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

class DatabaseIndexingHandlerTest extends AbstractTest
{
    private function createMessage(
        string $siteIdentifier = 'test-site',
        int $pageUid = 42,
        int $language = 0,
        int $indexConfigurationRecordId = 1,
        string $indexProcessId = 'process-123',
        array $accessGroups = [0, -1],
    ): DatabaseIndexMessage {
        $uri = $this->createStub(UriInterface::class);

        return new DatabaseIndexMessage(
            siteIdentifier: $siteIdentifier,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: $indexConfigurationRecordId,
            uri: $uri,
            pageUid: $pageUid,
            indexProcessId: $indexProcessId,
            language: $language,
            accessGroups: $accessGroups,
        );
    }

    private function createConfiguration(
        bool $skipNoSearchPages = false,
        bool $contentIndexing = false,
    ): Configuration {
        return new Configuration(
            configurationId: 1,
            pageId: 1,
            technology: IndexTechnology::Database,
            skipNoSearchPages: $skipNoSearchPages,
            contentIndexing: $contentIndexing,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );
    }

    private function createSiteWithRouter(string $generatedUri = 'https://example.com/page'): Site
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('__toString')->willReturn($generatedUri);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willReturn($uri);

        $site = $this->createStub(Site::class);
        $site->method('getRouter')->willReturn($router);
        $site->method('getAttribute')->willReturn('Test Website');

        return $site;
    }

    private function createSubject(
        SiteFinder $siteFinder,
        ContentIndexing $contentIndexing,
        EventDispatcherInterface $eventDispatcher,
        RecordSelection $recordSelection,
        ConfigurationLoader $configurationLoader,
    ): DatabaseIndexingHandler {
        return new DatabaseIndexingHandler(
            $siteFinder,
            $contentIndexing,
            $eventDispatcher,
            $recordSelection,
            $configurationLoader,
        );
    }

    public function testInvokeReturnsEarlyWhenPageRowIsNull(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration());

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyWhenConfigurationIsNull(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn(null);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Test', 'no_search' => false]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeReturnsEarlyWhenSkipNoSearchPagesAndPageHasNoSearch(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(skipNoSearchPages: true));

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Test', 'no_search' => true]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeDoesNotSkipPageWhenSkipNoSearchPagesDisabled(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(skipNoSearchPages: false));

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Test', 'no_search' => true]);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeDispatchesOneEventPerContentElementWhenContentIndexingEnabled(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(contentIndexing: true));

        $record1 = $this->createStub(Record::class);
        $record1->method('getUid')->willReturn(100);
        $record2 = $this->createStub(Record::class);
        $record2->method('getUid')->willReturn(200);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Page Title']);
        $recordSelection->method('findRecordsOnPage')->willReturn([$record1, $record2]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch')
            ->with(self::isInstanceOf(IndexPageEvent::class));

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeDispatchesSingleEventWhenContentIndexingDisabled(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(contentIndexing: false));

        $record1 = $this->createStub(Record::class);
        $record2 = $this->createStub(Record::class);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Page Title']);
        $recordSelection->method('findRecordsOnPage')->willReturn([$record1, $record2]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->title === 'Page Title | Test Website'
                && $event->pageUid === 42
                && $event->technology === IndexTechnology::Database
                && $event->accessGroups === [0, -1]));

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeCatchesExceptionAndLogsError(): void
    {
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willThrowException(new \Exception('Site not found'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $configurationLoader = $this->createStub(ConfigurationLoader::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeWithNoContentElementsDispatchesSingleEvent(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(contentIndexing: false));

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Empty Page']);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->title === 'Empty Page | Test Website'
                && $event->content === ''));

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokeWithContentIndexingEnabledButNoContentElementsDispatchesNothing(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage();

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(contentIndexing: true));

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Empty Page']);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }

    public function testInvokePassesLanguageInEventArguments(): void
    {
        $site = $this->createSiteWithRouter();
        $message = $this->createMessage(language: 2);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($this->createConfiguration(contentIndexing: false));

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRenderablePage')->willReturn(['title' => 'Seite']);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::callback(fn(IndexPageEvent $event): bool => $event->language === 2));

        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = $this->createSubject($siteFinder, $contentIndexing, $eventDispatcher, $recordSelection, $configurationLoader);
        $subject->__invoke($message);
    }
}
