<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Indexing\Record\RecordIndexingQueue;
use Lochmueller\Index\Indexing\Record\RecordSourceResolver;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\RecordIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final class RecordIndexingQueueTest extends AbstractTest
{
    public function testQueuesConfiguredRecordsInTheFullProcess(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Database, [
            'records' => [[
                'table' => 'tx_example_domain_model_item',
                'recordStorages' => [12, '24'],
                'fields' => ['title' => 'title'],
            ]],
        ]);
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRawRecordsOnStorages')
            ->with('tx_example_domain_model_item', [12, 24])
            ->willReturn([
                ['uid' => 42, 'pid' => 12, 'sys_language_uid' => 0],
                ['uid' => 43, 'pid' => 24, 'sys_language_uid' => 2],
            ]);
        $recordSelection->expects(self::exactly(2))
            ->method('getRecordLanguageId')
            ->willReturnMap([
                ['tx_example_domain_model_item', ['uid' => 42, 'pid' => 12, 'sys_language_uid' => 0], 0],
                ['tx_example_domain_model_item', ['uid' => 43, 'pid' => 24, 'sys_language_uid' => 2], 2],
            ]);

        $messages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$messages): void {
                $messages[] = $message;
            });

        $queue = new RecordIndexingQueue(
            $bus,
            $this->createStub(ConfigurationLoader::class),
            $recordSelection,
            new RecordSourceResolver(),
            $this->createStub(SiteFinder::class),
        );
        $queue->queueConfiguredRecords($configuration, $site, 'process-1', IndexType::Full);

        self::assertContainsOnlyInstancesOf(RecordIndexMessage::class, $messages);
        self::assertSame([42, 43], array_map(static fn(RecordIndexMessage $message): int => $message->recordUid, $messages));
        self::assertSame([0, 2], array_map(static fn(RecordIndexMessage $message): int => $message->language, $messages));
        self::assertSame([IndexTechnology::Database, IndexTechnology::Database], array_map(
            static fn(RecordIndexMessage $message): IndexTechnology => $message->technology,
            $messages,
        ));
    }

    public function testPartialPageIndexDoesNotRescanStandaloneRecords(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Http, [
            'records' => [['table' => 'tx_example_domain_model_item', 'recordStorages' => [12]]],
        ]);
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::never())->method('findRawRecordsOnStorages');
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::never())->method('dispatch');

        $queue = new RecordIndexingQueue(
            $bus,
            $this->createStub(ConfigurationLoader::class),
            $recordSelection,
            new RecordSourceResolver(),
            $this->createStub(SiteFinder::class),
        );
        $queue->queueConfiguredRecords(
            $configuration,
            $this->createStub(Site::class),
            'partial-process',
            IndexType::Partial,
        );
    }

    /**
     * @return array<string, array{IndexTechnology}>
     */
    public static function supportedTechnologies(): array
    {
        return [
            'database' => [IndexTechnology::Database],
            'frontend' => [IndexTechnology::Frontend],
            'http' => [IndexTechnology::Http],
        ];
    }

    #[DataProvider('supportedTechnologies')]
    public function testRecordChangesAreQueuedForEachSupportedTechnology(IndexTechnology $technology): void
    {
        $configuration = $this->createConfiguration($technology, [
            'records' => [['table' => 'tx_example_domain_model_item', 'recordStorages' => [12, 13]]],
        ], [IndexPartialTrigger::Datamap->value]);
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadAllBySite')->willReturn([$configuration]);
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::exactly(2))
            ->method('getRecordLanguageId')
            ->willReturnMap([
                ['tx_example_domain_model_item', ['uid' => 42, 'pid' => 12, 'sys_language_uid' => 0], 0],
                ['tx_example_domain_model_item', ['uid' => 42, 'pid' => 13, 'sys_language_uid' => 2], 2],
            ]);

        $messages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(4))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$messages): void {
                $messages[] = $message;
            });
        $queue = new RecordIndexingQueue(
            $bus,
            $configurationLoader,
            $recordSelection,
            new RecordSourceResolver(),
            $siteFinder,
        );

        $queue->queueRecordChange(
            'tx_example_domain_model_item',
            ['uid' => 42, 'pid' => 12, 'sys_language_uid' => 0],
            ['uid' => 42, 'pid' => 13, 'sys_language_uid' => 2],
            IndexPartialTrigger::Datamap,
        );

        self::assertInstanceOf(StartProcessMessage::class, $messages[0]);
        self::assertInstanceOf(RecordIndexMessage::class, $messages[1]);
        self::assertInstanceOf(RecordIndexMessage::class, $messages[2]);
        self::assertInstanceOf(FinishProcessMessage::class, $messages[3]);
        self::assertSame([0, 2], [$messages[1]->language, $messages[2]->language]);
        self::assertSame([$technology, $technology], [$messages[1]->technology, $messages[2]->technology]);
    }

    public function testMovingRecordOutOfStorageQueuesItsPreviousSourceForDeIndexing(): void
    {
        $configuration = $this->createConfiguration(IndexTechnology::Http, [
            'records' => [['table' => 'tx_example_domain_model_item', 'recordStorages' => [12]]],
        ], [IndexPartialTrigger::Cmdmap->value]);
        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');

        $configurationLoader = $this->createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadAllBySite')->willReturn([$configuration]);
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);
        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('getRecordLanguageId')->willReturn(0);

        $messages = [];
        $bus = $this->createMock(Bus::class);
        $bus->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$messages): void {
                $messages[] = $message;
            });
        $queue = new RecordIndexingQueue(
            $bus,
            $configurationLoader,
            $recordSelection,
            new RecordSourceResolver(),
            $siteFinder,
        );

        $queue->queueRecordChange(
            'tx_example_domain_model_item',
            ['uid' => 42, 'pid' => 12, 'sys_language_uid' => 0],
            ['uid' => 42, 'pid' => 13, 'sys_language_uid' => 0],
            IndexPartialTrigger::Cmdmap,
        );

        self::assertInstanceOf(StartProcessMessage::class, $messages[0]);
        self::assertInstanceOf(RecordIndexMessage::class, $messages[1]);
        self::assertSame(42, $messages[1]->recordUid);
        self::assertInstanceOf(FinishProcessMessage::class, $messages[2]);
    }

    private function createConfiguration(
        IndexTechnology $technology,
        array $configuration,
        array $partialIndexing = [],
    ): Configuration {
        return new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: $technology,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: $configuration,
            partialIndexing: $partialIndexing,
            languages: [],
        );
    }
}
