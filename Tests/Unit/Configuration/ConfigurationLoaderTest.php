<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Configuration;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\ConfigurationRepository;
use Lochmueller\Index\Domain\Repository\PagesRepository;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class ConfigurationLoaderTest extends AbstractTest
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetConfigurationCache();
    }

    public function testLoadByUidReturnsNullWhenNotFound(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadByUid(999);

        self::assertNull($result);
    }

    public function testLoadByUidReturnsConfigurationWhenFound(): void
    {
        $configuration = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadByUid(1);

        self::assertSame($configuration, $result);
    }

    public function testLoadByPageReturnsNullWhenNotFound(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadByPage(999);

        self::assertNull($result);
    }

    public function testLoadByPageReturnsConfigurationWhenFound(): void
    {
        $configuration = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadByPage(10);

        self::assertSame($configuration, $result);
    }

    public function testGetAllReturnsAllConfigurations(): void
    {
        $config1 = $this->createConfiguration(1, 10);
        $config2 = $this->createConfiguration(2, 20);
        $this->setConfigurationCache([1 => $config1, 2 => $config2]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->getAll();

        self::assertCount(2, $result);
        self::assertSame($config1, $result[1]);
        self::assertSame($config2, $result[2]);
    }

    public function testGetAllReturnsEmptyArrayWhenNoConfigurations(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->getAll();

        self::assertSame([], $result);
    }

    public function testLoadByPageTraversingReturnsConfigurationWhenFoundDirectly(): void
    {
        $configuration = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadByPageTraversing(10);

        self::assertSame($configuration, $result);
    }

    public function testLoadByPageTraversingReturnsConfigurationFromRootline(): void
    {
        $configuration = $this->createConfiguration(1, 5);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('getRootline')->willReturn([
            ['uid' => 10],
            ['uid' => 5],
            ['uid' => 1],
        ]);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $pagesRepository,
        );
        $result = $subject->loadByPageTraversing(15);

        self::assertSame($configuration, $result);
    }

    public function testLoadByPageTraversingReturnsNullWhenNotFoundInRootline(): void
    {
        $configuration = $this->createConfiguration(1, 100);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('getRootline')->willReturn([
            ['uid' => 10],
            ['uid' => 5],
        ]);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $pagesRepository,
        );
        $result = $subject->loadByPageTraversing(15);

        self::assertNull($result);
    }

    public function testLoadBySiteReturnsConfigurationForRootPage(): void
    {
        $configuration = $this->createConfiguration(1, 1);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getRootPageId')->willReturn(1);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadBySite($site);

        self::assertSame($configuration, $result);
    }

    public function testLoadBySiteReturnsNullWhenNoConfigurationForRootPage(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getRootPageId')->willReturn(999);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $this->createStub(PagesRepository::class),
        );
        $result = $subject->loadBySite($site);

        self::assertNull($result);
    }

    public function testLoadAllBySiteReturnsConfigurationsInSiteRootline(): void
    {
        $config1 = $this->createConfiguration(1, 10);
        $config2 = $this->createConfiguration(2, 20);
        $config3 = $this->createConfiguration(3, 30);
        $this->setConfigurationCache([1 => $config1, 2 => $config2, 3 => $config3]);
        $this->setPreloadExecuted(true);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getRootPageId')->willReturn(1);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('getRootlineIds')->willReturnMap([
            [10, [10, 5, 1]],
            [20, [20, 15, 1]],
            [30, [30, 25, 2]],
        ]);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $pagesRepository,
        );
        $result = iterator_to_array($subject->loadAllBySite($site));

        self::assertCount(2, $result);
        self::assertContains($config1, $result);
        self::assertContains($config2, $result);
        self::assertNotContains($config3, $result);
    }

    public function testLoadAllBySiteReturnsEmptyWhenNoConfigurationsInSite(): void
    {
        $config1 = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $config1]);
        $this->setPreloadExecuted(true);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getRootPageId')->willReturn(999);

        $pagesRepository = $this->createStub(PagesRepository::class);
        $pagesRepository->method('getRootlineIds')->willReturn([10, 5, 1]);

        $subject = new ConfigurationLoader(
            $this->createStub(ConfigurationRepository::class),
            $pagesRepository,
        );
        $result = iterator_to_array($subject->loadAllBySite($site));

        self::assertCount(0, $result);
    }

    public function testPreloadConfigurationsLoadsFromRepository(): void
    {
        $this->resetConfigurationCache();

        $configurationRepository = $this->createStub(ConfigurationRepository::class);
        $configurationRepository->method('findAll')->willReturn([
            [
                'uid' => 1,
                'pid' => 10,
                'technology' => 'database',
                'content_indexing' => 1,
                'skip_no_search_pages' => 0,
                'levels' => 0,
                'file_mounts' => '',
                'file_types' => '',
                'configuration' => '',
                'partial_indexing' => '',
                'languages' => '',
            ],
        ]);

        $subject = new ConfigurationLoader(
            $configurationRepository,
            $this->createStub(PagesRepository::class),
        );
        $subject->preloadConfigurations();
        $result = $subject->getAll();

        self::assertCount(1, $result);
        self::assertArrayHasKey(1, $result);
        self::assertSame(10, $result[1]->pageId);
    }

    public function testPreloadConfigurationsOnlyExecutesOnce(): void
    {
        $this->resetConfigurationCache();

        $configurationRepository = $this->createMock(ConfigurationRepository::class);
        $configurationRepository->expects(self::once())->method('findAll')->willReturn([]);

        $subject = new ConfigurationLoader(
            $configurationRepository,
            $this->createStub(PagesRepository::class),
        );
        $subject->preloadConfigurations();
        $subject->preloadConfigurations();
    }

    private function createConfiguration(int $uid, int $pageId): Configuration
    {
        return new Configuration(
            configurationId: $uid,
            pageId: $pageId,
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
    }

    private function setConfigurationCache(array $cache): void
    {
        $reflection = new \ReflectionClass(ConfigurationLoader::class);
        $property = $reflection->getProperty('runtimeConfigurationCache');
        $property->setValue(null, $cache);
    }

    private function setPreloadExecuted(bool $value): void
    {
        $reflection = new \ReflectionClass(ConfigurationLoader::class);
        $property = $reflection->getProperty('preloadExecuted');
        $property->setValue(null, $value);
    }

    private function resetConfigurationCache(): void
    {
        $this->setConfigurationCache([]);
        $this->setPreloadExecuted(false);
    }
}
