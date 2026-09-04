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
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busMock
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );

        self::assertInstanceOf(DeIndexDocumentMessage::class, $dispatchedMessage);
        self::assertSame(123, $dispatchedMessage->pageUid);
        self::assertSame(2, $dispatchedMessage->languageId);
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotDispatchDeIndexMessageWhenSkipNoSearchPagesIsDisabled(): void
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
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $busMock = $this->createMock(Bus::class);
        $busMock->expects(self::never())->method('dispatch');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderStub,
            $activeIndexingMock,
            $cacheStub,
            $genericRepositoryStub,
            $busMock
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
            $busMock
        );
        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            [],
            $this->createStub(DataHandler::class),
        );
    }

    public function testProcessDatamapAfterDatabaseOperationsDoesNotDispatchDeIndexMessageForAlreadyTriggeredPage(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Datamap->value], true);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([123]);

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
            $busMock
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
            $busMock
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
            $busMock
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
            $busStub
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
}
