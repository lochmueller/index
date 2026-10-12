<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Hooks\DataHandlerUpdateHook;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\DeIndexDocumentMessageFactory;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\DataHandling\DataHandler;

class DataHandlerUpdateHookTest extends AbstractTest
{
    private function createConfiguration(array $partialIndexing = [], bool $skipNoSearchPages = false): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Database,
            skipNoSearchPages: $skipNoSearchPages,
            contentIndexing: true,
            levels: 99,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: $partialIndexing,
            languages: [],
        );
    }

    private function createGenericRepositoryStub(): GenericRepository
    {
        $stub = $this->createStub(GenericRepository::class);
        $stub->method('setTableName')->willReturnSelf();

        return $stub;
    }

    public function testClearCacheCmdTriggersIndexingForConfiguredPages(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $busStub = $this->createStub(Bus::class);
        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            bus: $busStub,
            context: new Context(),
            deIndexDocumentMessageFactory: $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [123]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdDoesNotTriggerWhenTriggerNotConfigured(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);
        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [123]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdDoesNotTriggerWhenNoConfiguration(): void
    {
        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn(null);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);
        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [123]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdSkipsAlreadyTriggeredPages(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([123]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [123]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdSkipsPageIdZero(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [0]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdHandlesMultiplePages(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::exactly(2))->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => [123, 456]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdHandlesEmptyPageIdArray(): void
    {
        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);
        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => []], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdHandlesMissingPageIdArray(): void
    {
        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $this->createGenericRepositoryStub(),
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->clearCacheCmd([], $this->createStub(DataHandler::class));
    }

    public function testProcessDatamapAfterDatabaseOperationsTriggersIndexingForPageRecord(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 123, 'pid' => 10]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsTriggersIndexingForContentRecord(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 456, 'pid' => 123]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'tt_content',
            456,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotTriggerForNonIntegerId(): void
    {
        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->expects(self::never())->method('setTableName');

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $this->createStub(ConfigurationLoader::class),
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $genericRepositoryMock,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123abc',
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotTriggerWhenRecordNotFound(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(null);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $this->createStub(ConfigurationLoader::class),
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotTriggerWhenTriggerNotConfigured(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 123, 'pid' => 10]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDispatchesDeIndexMessageForNoSearchPage(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 123,
            'pid' => 10,
            'sys_language_uid' => 2,
            'no_search' => 1,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $deIndexDocumentMessageFactoryMock = $this->createMock(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryMock->expects(self::once())
            ->method('createForPage')
            ->with(123, 2)
            ->willReturn([2 => new DeIndexDocumentMessage(123, 2, 'https://example.com/en/page', 'main')]);

        $dispatchedMessage = null;
        $busMock = $this->createMock(Bus::class);
        $busMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessage): void {
                $dispatchedMessage = $message;
            });

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $deIndexDocumentMessageFactoryMock,
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );

        self::assertInstanceOf(DeIndexDocumentMessage::class, $dispatchedMessage);
        self::assertSame('https://example.com/en/page', $dispatchedMessage->uri);
        self::assertSame(123, $dispatchedMessage->pageUid);
        self::assertSame(2, $dispatchedMessage->languageId);
    }

    public function testProcessDatamapAfterDatabaseOperationsIndexesNoSearchPageWhenSkipNoSearchPagesIsDisabled(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], false);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 123,
            'pid' => 10,
            'sys_language_uid' => 0,
            'no_search' => 1,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::once())->method('fillQueue');

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotDispatchDeIndexMessageWhenTriggerNotConfigured(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 123,
            'pid' => 10,
            'sys_language_uid' => 0,
            'no_search' => 1,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotDispatchDeIndexMessageForAlreadyDeIndexedDocument(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn(['https://example.com/page']);

        $deIndexDocumentMessageFactoryStub = $this->createStub(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryStub->method('createForPage')
            ->willReturn([0 => new DeIndexDocumentMessage(123, 0, 'https://example.com/page', 'main')]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 123,
            'pid' => 10,
            'sys_language_uid' => 0,
            'no_search' => 1,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $deIndexDocumentMessageFactoryStub,
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotDispatchDeIndexMessageForPageIdZero(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 0,
            'pid' => 0,
            'sys_language_uid' => 0,
            'no_search' => 1,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            0,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsIndexesInsteadOfDeIndexingWhenNoSearchIsNotSet(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 123,
            'pid' => 10,
            'sys_language_uid' => 0,
            'no_search' => 0,
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessCmdmapPostProcessTriggersIndexingForPageRecord(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Cmdmap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 123, 'pid' => 10]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'move',
            'pages',
            123,
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );
    }

    public function testProcessCmdmapPostProcessTriggersIndexingForContentRecord(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Cmdmap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 456, 'pid' => 123]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);
        $busStub = $this->createStub(Bus::class);


        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'copy',
            'tt_content',
            456,
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );
    }

    public function testProcessCmdmapPostProcessDoesNotTriggerForNonIntegerId(): void
    {
        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->expects(self::never())->method('setTableName');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $this->createStub(ConfigurationLoader::class),
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $genericRepositoryMock,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'delete',
            'pages',
            'NEW123abc',
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );
    }

    public function testProcessCmdmapPostProcessDoesNotTriggerWhenRecordNotFound(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(null);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $this->createStub(ConfigurationLoader::class),
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'delete',
            'pages',
            123,
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );
    }

    public function testProcessCmdmapPostProcessDoesNotTriggerWhenTriggerNotConfigured(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 123, 'pid' => 10]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');
        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'move',
            'pages',
            123,
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsResolvesVersionToLiveRecord(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock
            ->expects(self::atLeastOnce())
            ->method('loadByPageTraversing')
            ->with(123)
            ->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturnMap([
            [456, ['uid' => 456, 'pid' => -1, 't3ver_wsid' => 1, 't3ver_oid' => 123]],
            [123, ['uid' => 123, 'pid' => 10, 't3ver_wsid' => 0, 't3ver_oid' => 0]],
        ]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            456,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsSkipsVersionWithoutLiveRecord(): void
    {
        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 456, 'pid' => -1, 't3ver_wsid' => 1, 't3ver_oid' => 0]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            456,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessCmdmapPostProcessIndexesWithLiveWorkspaceAspect(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Cmdmap->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn(['uid' => 123, 'pid' => 10]);

        $context = new Context();
        $context->setAspect('workspace', new WorkspaceAspect(3));

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->willReturnCallback(static function () use ($context): void {
                self::assertSame(0, $context->getAspect('workspace')->getId());
            });

        $busStub = $this->createStub(Bus::class);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            $context,
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess(
            'move',
            'pages',
            123,
            null,
            $this->createStub(DataHandler::class),
            null,
            null,
        );

        self::assertSame(3, $context->getAspect('workspace')->getId());
    }

    /**
     * @param array<string, mixed> $visibilityFields
     */
    #[DataProvider('removedDefaultPageDataProvider')]
    public function testProcessDatamapAfterDatabaseOperationsDeIndexesAllLanguagesOfRemovedPage(array $visibilityFields): void
    {
        $documents = [
            0 => new DeIndexDocumentMessage(123, 0, 'https://example.com/page', 'main'),
            1 => new DeIndexDocumentMessage(123, 1, 'https://example.com/en/page', 'main'),
        ];
        $deIndexDocumentMessageFactoryMock = $this->createMock(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryMock->expects(self::once())->method('createForPage')->with(123, null)->willReturn($documents);

        $dispatched = [];
        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([123 => ['uid' => 123, 'pid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0] + $visibilityFields]),
            $deIndexDocumentMessageFactoryMock,
            $dispatched,
            $activeIndexingMock,
        );
        $subject->processDatamap_afterDatabaseOperations('update', 'pages', 123, [], $this->createStub(DataHandler::class));

        self::assertSame(array_values($documents), $dispatched);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function removedDefaultPageDataProvider(): iterable
    {
        yield 'hidden' => [['hidden' => 1]];
        yield 'starttime in the future' => [['starttime' => time() + 3600]];
        yield 'endtime reached' => [['endtime' => time() - 1]];
        yield 'sysfolder' => [['doktype' => 254]];
    }

    public function testProcessDatamapAfterDatabaseOperationsDeIndexesOnlyHiddenTranslation(): void
    {
        $document = new DeIndexDocumentMessage(123, 1, 'https://example.com/en/page', 'main');
        $deIndexDocumentMessageFactoryMock = $this->createMock(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryMock->expects(self::once())->method('createForPage')->with(123, 1)->willReturn([1 => $document]);

        $dispatched = [];
        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([124 => ['uid' => 124, 'pid' => 10, 'sys_language_uid' => 1, 'l10n_parent' => 123, 'hidden' => 1]]),
            $deIndexDocumentMessageFactoryMock,
            $dispatched,
        );
        $subject->processDatamap_afterDatabaseOperations('update', 'pages', 124, [], $this->createStub(DataHandler::class));

        self::assertSame([$document], $dispatched);
    }

    public function testProcessDatamapAfterDatabaseOperationsIndexesPageInDefaultLanguageForTranslation(): void
    {
        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::once())
            ->method('fillQueue')
            ->with(self::callback(static fn(Configuration $configuration): bool => $configuration->pageId === 123), true);

        $dispatched = [];
        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([124 => ['uid' => 124, 'pid' => 10, 'sys_language_uid' => 1, 'l10n_parent' => 123]]),
            $this->createStub(DeIndexDocumentMessageFactory::class),
            $dispatched,
            $activeIndexingMock,
        );
        $subject->processDatamap_afterDatabaseOperations('update', 'pages', 124, [], $this->createStub(DataHandler::class));

        self::assertSame([], $dispatched);
    }

    public function testSlugChangeDeIndexesOldUriAndIndexesPage(): void
    {
        $oldDocument = new DeIndexDocumentMessage(123, 0, 'https://example.com/old', 'main');
        $deIndexDocumentMessageFactoryStub = $this->createStub(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryStub->method('createForPage')->willReturnOnConsecutiveCalls(
            [0 => $oldDocument],
            [0 => new DeIndexDocumentMessage(123, 0, 'https://example.com/new', 'main')],
        );

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::once())->method('fillQueue');

        $dispatched = [];
        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([123 => ['uid' => 123, 'pid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0]]),
            $deIndexDocumentMessageFactoryStub,
            $dispatched,
            $activeIndexingMock,
        );

        $incomingFieldArray = ['slug' => '/new'];
        $subject->processDatamap_preProcessFieldArray($incomingFieldArray, 'pages', 123, $this->createStub(DataHandler::class));
        $subject->processDatamap_afterDatabaseOperations('update', 'pages', 123, [], $this->createStub(DataHandler::class));

        self::assertSame([$oldDocument], $dispatched);
    }

    public function testUnchangedSlugDoesNotDeIndex(): void
    {
        $deIndexDocumentMessageFactoryStub = $this->createStub(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryStub->method('createForPage')
            ->willReturn([0 => new DeIndexDocumentMessage(123, 0, 'https://example.com/page', 'main')]);

        $dispatched = [];
        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([123 => ['uid' => 123, 'pid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0]]),
            $deIndexDocumentMessageFactoryStub,
            $dispatched,
        );

        $incomingFieldArray = ['slug' => '/page'];
        $subject->processDatamap_preProcessFieldArray($incomingFieldArray, 'pages', 123, $this->createStub(DataHandler::class));
        $subject->processDatamap_afterDatabaseOperations('update', 'pages', 123, [], $this->createStub(DataHandler::class));

        self::assertSame([], $dispatched);
    }

    public function testDeletedPageIsDeIndexedWithUrisBeforeDeletion(): void
    {
        $documents = [
            0 => new DeIndexDocumentMessage(123, 0, 'https://example.com/page', 'main'),
            1 => new DeIndexDocumentMessage(123, 1, 'https://example.com/en/page', 'main'),
        ];
        $deIndexDocumentMessageFactoryMock = $this->createMock(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryMock->expects(self::once())->method('createForPage')->with(123, null)->willReturn($documents);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $records = new \ArrayObject([123 => ['uid' => 123, 'pid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0]]);
        $dispatched = [];
        $subject = $this->createSubjectForPageRecords($records, $deIndexDocumentMessageFactoryMock, $dispatched, $activeIndexingMock);

        $subject->processCmdmap_preProcess('delete', 'pages', 123, null, $this->createStub(DataHandler::class), null);
        $records[123] = null;
        $subject->processCmdmap_postProcess('delete', 'pages', 123, null, $this->createStub(DataHandler::class), null, null);

        self::assertSame(array_values($documents), $dispatched);
    }

    public function testPageMovedOutOfIndexConfigurationIsDeIndexed(): void
    {
        $document = new DeIndexDocumentMessage(123, 0, 'https://example.com/page', 'main');
        $deIndexDocumentMessageFactoryStub = $this->createStub(DeIndexDocumentMessageFactory::class);
        $deIndexDocumentMessageFactoryStub->method('createForPage')->willReturn([0 => $document]);

        $configurations = [123 => $this->createConfiguration([IndexPartialTrigger::Cmdmap->value])];
        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturnCallback(
            static function (int $pageId) use (&$configurations): ?Configuration {
                return $configurations[$pageId] ?? null;
            },
        );

        $dispatched = [];
        $subject = $this->createSubjectForPageRecords(
            new \ArrayObject([123 => ['uid' => 123, 'pid' => 10, 'sys_language_uid' => 0, 'l10n_parent' => 0]]),
            $deIndexDocumentMessageFactoryStub,
            $dispatched,
            configurationLoader: $configurationLoaderStub,
        );

        $subject->processCmdmap_preProcess('move', 'pages', 123, 99, $this->createStub(DataHandler::class), null);
        $configurations = [];
        $subject->processCmdmap_postProcess('move', 'pages', 123, 99, $this->createStub(DataHandler::class), null, null);

        self::assertSame([$document], $dispatched);
    }

    public function testDeletedContentElementTriggersIndexingOfItsPage(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturnCallback(
            static fn(int $uid, bool $includeDeleted = false): ?array => $includeDeleted ? ['uid' => 456, 'pid' => 123] : null,
        );

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($this->createConfiguration([IndexPartialTrigger::Cmdmap->value]));

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::once())
            ->method('fillQueue')
            ->with(self::callback(static fn(Configuration $configuration): bool => $configuration->pageId === 123), true);

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $this->createStub(Bus::class),
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processCmdmap_postProcess('delete', 'tt_content', 456, null, $this->createStub(DataHandler::class), null, null);
    }

    public function testNewRecordTriggersIndexingWithSubstitutedId(): void
    {
        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->method('setTableName')->willReturnSelf();
        $genericRepositoryMock->expects(self::once())->method('findByUid')->with(456)->willReturn(['uid' => 456, 'pid' => 123]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($this->createConfiguration([IndexPartialTrigger::Datamap->value]));

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::once())->method('fillQueue');

        $dataHandlerStub = $this->createStub(DataHandler::class);
        $dataHandlerStub->substNEWwithIDs = ['NEW123abc' => 456];

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryMock,
            $this->createStub(Bus::class),
            new Context(),
            $this->createStub(DeIndexDocumentMessageFactory::class),
        );
        $subject->processDatamap_afterDatabaseOperations('new', 'tt_content', 'NEW123abc', [], $dataHandlerStub);
    }

    /**
     * @param \ArrayObject<int, array<string, mixed>|null> $records Live page records by uid - changes are visible to the subject
     * @param array<int, DeIndexDocumentMessage> $dispatched
     */
    private function createSubjectForPageRecords(
        \ArrayObject $records,
        DeIndexDocumentMessageFactory $deIndexDocumentMessageFactory,
        array &$dispatched,
        ?ActiveIndexing $activeIndexing = null,
        ?ConfigurationLoader $configurationLoader = null,
    ): DataHandlerUpdateHook {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturnCallback(
            static fn(int $uid): ?array => $records[$uid] ?? null,
        );

        if ($configurationLoader === null) {
            $configurationLoader = $this->createStub(ConfigurationLoader::class);
            $configurationLoader->method('loadByPageTraversing')->willReturn($this->createConfiguration([
                IndexPartialTrigger::Datamap->value,
                IndexPartialTrigger::Cmdmap->value,
            ]));
        }

        $cache = [];
        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturnCallback(static function (string $identifier) use (&$cache): mixed {
            return $cache[$identifier] ?? false;
        });
        $cacheStub->method('set')->willReturnCallback(static function (string $identifier, mixed $data) use (&$cache): void {
            $cache[$identifier] = $data;
        });

        $busStub = $this->createStub(Bus::class);
        $busStub->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): void {
            $dispatched[] = $message;
        });

        return new DataHandlerUpdateHook(
            $configurationLoader,
            $activeIndexing ?? $this->createStub(ActiveIndexing::class),
            $cacheStub,
            $genericRepositoryStub,
            $busStub,
            new Context(),
            $deIndexDocumentMessageFactory,
        );
    }
}
