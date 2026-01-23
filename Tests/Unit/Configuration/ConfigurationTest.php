<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Configuration;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class ConfigurationTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Database,
            skipNoSearchPages: true,
            contentIndexing: true,
            levels: 5,
            fileMounts: ['fileadmin'],
            fileTypes: ['pdf', 'docx'],
            configuration: ['key' => 'value'],
            partialIndexing: ['pages'],
            languages: [0, 1],
        );

        self::assertSame(1, $configuration->configurationId);
        self::assertSame(10, $configuration->pageId);
        self::assertSame(IndexTechnology::Database, $configuration->technology);
        self::assertTrue($configuration->skipNoSearchPages);
        self::assertTrue($configuration->contentIndexing);
        self::assertSame(5, $configuration->levels);
        self::assertSame(['fileadmin'], $configuration->fileMounts);
        self::assertSame(['pdf', 'docx'], $configuration->fileTypes);
        self::assertSame(['key' => 'value'], $configuration->configuration);
        self::assertSame(['pages'], $configuration->partialIndexing);
        self::assertSame([0, 1], $configuration->languages);
        self::assertNull($configuration->overrideIndexType);
    }

    public function testConstructorWithOverrideIndexType(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
            overrideIndexType: IndexType::Full,
        );

        self::assertSame(IndexType::Full, $configuration->overrideIndexType);
    }

    public function testCreateByDatabaseRowWithDatabaseTechnology(): void
    {
        $row = [
            'uid' => 42,
            'pid' => 100,
            'technology' => 'database',
            'content_indexing' => 1,
            'skip_no_search_pages' => 1,
            'levels' => 3,
            'file_mounts' => 'fileadmin,uploads',
            'file_types' => 'pdf,doc,txt',
            'configuration' => '{"test": "value"}',
            'partial_indexing' => 'pages,files',
            'languages' => '0,1,2',
        ];

        $configuration = Configuration::createByDatabaseRow($row);

        self::assertSame(42, $configuration->configurationId);
        self::assertSame(100, $configuration->pageId);
        self::assertSame(IndexTechnology::Database, $configuration->technology);
        self::assertTrue($configuration->contentIndexing);
        self::assertTrue($configuration->skipNoSearchPages);
        self::assertSame(3, $configuration->levels);
        self::assertSame(['fileadmin', 'uploads'], $configuration->fileMounts);
        self::assertSame(['pdf', 'doc', 'txt'], $configuration->fileTypes);
        self::assertSame([], $configuration->configuration);
        self::assertSame(['pages', 'files'], $configuration->partialIndexing);
        self::assertSame([0, 1, 2], $configuration->languages);
    }

    public function testCreateByDatabaseRowWithFrontendTechnologyParsesConfiguration(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 1,
            'technology' => 'frontend',
            'content_indexing' => 0,
            'skip_no_search_pages' => 0,
            'levels' => 0,
            'file_mounts' => '',
            'file_types' => '',
            'configuration' => '{"baseUrl": "https://example.com"}',
            'partial_indexing' => '',
            'languages' => '',
        ];

        $configuration = Configuration::createByDatabaseRow($row);

        self::assertSame(IndexTechnology::Frontend, $configuration->technology);
        self::assertSame(['baseUrl' => 'https://example.com'], $configuration->configuration);
    }

    public function testCreateByDatabaseRowWithHttpTechnologyParsesConfiguration(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 1,
            'technology' => 'http',
            'content_indexing' => 0,
            'skip_no_search_pages' => 0,
            'levels' => 0,
            'file_mounts' => '',
            'file_types' => '',
            'configuration' => '{"timeout": 30}',
            'partial_indexing' => '',
            'languages' => '',
        ];

        $configuration = Configuration::createByDatabaseRow($row);

        self::assertSame(IndexTechnology::Http, $configuration->technology);
        self::assertSame(['timeout' => 30], $configuration->configuration);
    }

    public function testCreateByDatabaseRowWithEmptyOptionalFields(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 1,
            'technology' => 'cache',
            'content_indexing' => 0,
            'skip_no_search_pages' => 0,
            'levels' => 0,
        ];

        $configuration = Configuration::createByDatabaseRow($row);

        self::assertSame([''], $configuration->fileMounts);
        self::assertSame([''], $configuration->fileTypes);
        self::assertSame([], $configuration->configuration);
        self::assertSame([], $configuration->partialIndexing);
        self::assertSame([], $configuration->languages);
    }

    public function testModifyForPartialIndexingSetsCorrectValues(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Database,
            skipNoSearchPages: true,
            contentIndexing: true,
            levels: 5,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $result = $configuration->modifyForPartialIndexing(99);

        self::assertSame($configuration, $result);
        self::assertSame(IndexType::Partial, $configuration->overrideIndexType);
        self::assertSame(99, $configuration->pageId);
        self::assertSame(0, $configuration->levels);
    }

    public function testModifyForPartialIndexingReturnsSameInstance(): void
    {
        $configuration = new Configuration(
            configurationId: 1,
            pageId: 10,
            technology: IndexTechnology::Cache,
            skipNoSearchPages: false,
            contentIndexing: false,
            levels: 10,
            fileMounts: [],
            fileTypes: [],
            configuration: [],
            partialIndexing: [],
            languages: [],
        );

        $result = $configuration->modifyForPartialIndexing(50);

        self::assertSame($configuration, $result);
    }
}
