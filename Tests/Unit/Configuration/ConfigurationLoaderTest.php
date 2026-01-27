<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Configuration;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Tests\Unit\AbstractTest;

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

        $subject = new ConfigurationLoader();
        $result = $subject->loadByUid(999);

        self::assertNull($result);
    }

    public function testLoadByUidReturnsConfigurationWhenFound(): void
    {
        $configuration = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader();
        $result = $subject->loadByUid(1);

        self::assertSame($configuration, $result);
    }

    public function testLoadByPageReturnsNullWhenNotFound(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader();
        $result = $subject->loadByPage(999);

        self::assertNull($result);
    }

    public function testLoadByPageReturnsConfigurationWhenFound(): void
    {
        $configuration = $this->createConfiguration(1, 10);
        $this->setConfigurationCache([1 => $configuration]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader();
        $result = $subject->loadByPage(10);

        self::assertSame($configuration, $result);
    }

    public function testGetAllReturnsAllConfigurations(): void
    {
        $config1 = $this->createConfiguration(1, 10);
        $config2 = $this->createConfiguration(2, 20);
        $this->setConfigurationCache([1 => $config1, 2 => $config2]);
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader();
        $result = $subject->getAll();

        self::assertCount(2, $result);
        self::assertSame($config1, $result[1]);
        self::assertSame($config2, $result[2]);
    }

    public function testGetAllReturnsEmptyArrayWhenNoConfigurations(): void
    {
        $this->resetConfigurationCache();
        $this->setPreloadExecuted(true);

        $subject = new ConfigurationLoader();
        $result = $subject->getAll();

        self::assertSame([], $result);
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
