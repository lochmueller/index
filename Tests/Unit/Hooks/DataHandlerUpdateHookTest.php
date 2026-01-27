<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Hooks\DataHandlerUpdateHook;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;

class DataHandlerUpdateHookTest extends AbstractTest
{
    private function createConfiguration(array $partialIndexing = []): Configuration
    {
        return new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 99,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: $partialIndexing,
            languages: [],
        );
    }

    public function testClearCacheCmdTriggersIndexingForConfiguredPages(): void
    {
        $configuration = $this->createConfiguration([IndexPartialTrigger::Clearcache->value]);

        $cacheStub = $this->createStub(FrontendInterface::class);
        $cacheStub->method('get')->willReturn([]);

        $configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $configurationLoaderStub->method('loadByPageTraversing')->willReturn($configuration);

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock
            ->expects(self::once())
            ->method('fillQueue')
            ->with(self::isInstanceOf(Configuration::class), true);

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
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

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
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

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
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

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
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

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
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

        $subject = new DataHandlerUpdateHook($configurationLoaderStub, $activeIndexingMock, $cacheStub);
        $subject->clearCacheCmd(['pageIdArray' => [123, 456]], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdHandlesEmptyPageIdArray(): void
    {
        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
        );
        $subject->clearCacheCmd(['pageIdArray' => []], $this->createStub(DataHandler::class));
    }

    public function testClearCacheCmdHandlesMissingPageIdArray(): void
    {
        $configurationLoaderMock = $this->createMock(ConfigurationLoader::class);
        $configurationLoaderMock->expects(self::never())->method('loadByPageTraversing');

        $activeIndexingMock = $this->createMock(ActiveIndexing::class);
        $activeIndexingMock->expects(self::never())->method('fillQueue');

        $subject = new DataHandlerUpdateHook(
            $configurationLoaderMock,
            $activeIndexingMock,
            $this->createStub(FrontendInterface::class),
        );
        $subject->clearCacheCmd([], $this->createStub(DataHandler::class));
    }
}
