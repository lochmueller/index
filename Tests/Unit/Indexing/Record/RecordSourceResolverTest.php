<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\Record\RecordSourceResolver;
use Lochmueller\Index\Tests\Unit\AbstractTest;

final class RecordSourceResolverTest extends AbstractTest
{
    public function testResolvesRecordSourceAndNormalizesStorageValues(): void
    {
        $configuration = $this->createConfiguration([
            'records' => [[
                'table' => 'tx_example_domain_model_item',
                'recordStorages' => [12, '24'],
                'recordTypes' => [0, 'item'],
                'fields' => ['title' => 'title'],
                'constraints' => ['enabled' => true],
            ]],
        ]);

        $source = (new RecordSourceResolver())->findForTable($configuration, 'tx_example_domain_model_item');

        self::assertNotNull($source);
        self::assertSame('tx_example_domain_model_item', $source->table);
        self::assertSame([12, 24], $source->recordStorages);
        self::assertSame(['0', 'item'], $source->recordTypes);
        self::assertSame(['title' => 'title'], $source->fields);
        self::assertSame(['enabled' => true], $source->constraints);
    }

    public function testFindsOnlyRecordsInConfiguredStoragePages(): void
    {
        $configuration = $this->createConfiguration([
            'records' => [[
                'table' => 'tx_example_domain_model_item',
                'recordStorages' => [12],
            ]],
        ]);
        $resolver = new RecordSourceResolver();

        self::assertNotNull($resolver->findForRecord($configuration, 'tx_example_domain_model_item', 12));
        self::assertNull($resolver->findForRecord($configuration, 'tx_example_domain_model_item', 13));
        self::assertNull($resolver->findForRecord($configuration, 'tx_other_domain_model_item', 12));
    }

    public function testRejectsDuplicateTableSources(): void
    {
        $configuration = $this->createConfiguration([
            'records' => [
                ['table' => 'tx_example_domain_model_item', 'recordStorages' => [12]],
                ['table' => 'tx_example_domain_model_item', 'recordStorages' => [24]],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        (new RecordSourceResolver())->resolve($configuration);
    }

    private function createConfiguration(array $configuration): Configuration
    {
        return new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: IndexTechnology::Http,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: $configuration,
            partialIndexing: [],
            languages: [],
        );
    }
}
