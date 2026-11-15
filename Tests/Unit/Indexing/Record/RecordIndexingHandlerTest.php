<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\DeIndexRecordEvent;
use Lochmueller\Index\Event\IndexRecordEvent;
use Lochmueller\Index\Event\IndexRecordReadyEvent;
use Lochmueller\Index\Indexing\Field\Value\FieldValueResolver;
use Lochmueller\Index\Indexing\Record\RecordFieldDefinitionResolver;
use Lochmueller\Index\Indexing\Record\RecordIndexingHandler;
use Lochmueller\Index\Indexing\Record\RecordSourceResolver;
use Lochmueller\Index\Queue\Message\RecordIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final class RecordIndexingHandlerTest extends AbstractTest
{
    public function testListenersCanResolveRawRelationValuesBeforeTypedIndexEvent(): void
    {
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: IndexTechnology::Http,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [
                'records' => [[
                    'table' => 'tx_example_domain_model_item',
                    'recordStorages' => [12],
                    'fields' => [
                        'title' => 'title',
                        'departments' => ['source' => 'department_ids', 'multiple' => true],
                    ],
                ]],
            ],
            partialIndexing: [],
            languages: [],
        );

        $configurationLoader = self::createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($configuration);

        $record = self::createStub(Record::class);
        $record->method('getPid')->willReturn(12);
        $record->method('getRecordType')->willReturn('item');

        $recordSelection = self::createStub(RecordSelection::class);
        $recordSelection->method('findVisibleRecordDataByUid')->willReturn([
            'uid' => 42,
            'pid' => 12,
            'title' => 'Item title',
            'department_ids' => '3,5',
        ]);
        $recordSelection->method('mapRecord')->willReturn($record);

        $field = self::createStub(FieldTypeInterface::class);
        $field->method('getType')->willReturn('input');
        $field->method('getConfiguration')->willReturn([]);
        $field->method('isSearchable')->willReturn(false);

        $schema = self::createStub(TcaSchema::class);
        $schema->method('hasField')->willReturn(true);
        $schema->method('getField')->willReturn($field);

        $schemaFactory = self::createStub(TcaSchemaFactory::class);
        $schemaFactory->method('get')->willReturn($schema);

        $site = self::createStub(Site::class);
        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);

        $readyEvent = null;
        $recordEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$readyEvent, &$recordEvent, $record, $schema): object {
                if ($event instanceof IndexRecordEvent) {
                    $recordEvent = $event;
                    self::assertSame('3,5', $event->fields['departments']);
                    self::assertSame($record, $event->record);
                    self::assertSame('item', $event->recordType);
                    self::assertSame($schema, $event->tcaSchema);
                    self::assertSame('department_ids', $event->fieldDefinitions['departments']['source']);
                    self::assertSame('text', $event->fieldDefinitions['departments']['type']);
                    self::assertTrue($event->fieldDefinitions['departments']['multiple']);
                    $event->fields['departments'] = ['Sales', 'Service'];
                } elseif ($event instanceof IndexRecordReadyEvent) {
                    $readyEvent = $event;
                }

                return $event;
            });

        $handler = new RecordIndexingHandler(
            $siteFinder,
            $configurationLoader,
            $recordSelection,
            new RecordFieldDefinitionResolver($schemaFactory),
            new RecordSourceResolver(),
            $eventDispatcher,
            new FieldValueResolver(),
        );
        $handler(new RecordIndexMessage(
            siteIdentifier: 'main',
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 7,
            indexProcessId: 'process-1',
            table: 'tx_example_domain_model_item',
            recordUid: 42,
            language: 0,
        ));

        self::assertInstanceOf(IndexRecordReadyEvent::class, $readyEvent);
        self::assertSame(42, $readyEvent->recordUid);
        self::assertSame([
            'title' => 'Item title',
            'departments' => ['Sales', 'Service'],
        ], $readyEvent->fields);
    }

    public function testStaleLanguageMessageDeIndexesTheOldDocument(): void
    {
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: IndexTechnology::Database,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [
                'records' => [[
                    'table' => 'tx_example_domain_model_item',
                    'recordStorages' => [12],
                    'fields' => ['title' => 'title'],
                ]],
            ],
            partialIndexing: [],
            languages: [],
        );
        $configurationLoader = self::createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($configuration);

        $record = self::createStub(Record::class);
        $record->method('getPid')->willReturn(12);
        $recordSelection = self::createStub(RecordSelection::class);
        $recordSelection->method('findVisibleRecordDataByUid')->willReturn([
            'uid' => 42,
            'pid' => 12,
            'sys_language_uid' => 2,
            'title' => 'Item title',
        ]);
        $recordSelection->method('mapRecord')->willReturn($record);
        $recordSelection->method('getRecordLanguageId')->willReturn(2);

        $site = self::createStub(Site::class);
        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(\Lochmueller\Index\Event\DeIndexRecordEvent::class))
            ->willReturnCallback(static fn(object $event): object => $event);

        $handler = new RecordIndexingHandler(
            $siteFinder,
            $configurationLoader,
            $recordSelection,
            new RecordFieldDefinitionResolver(self::createStub(TcaSchemaFactory::class)),
            new RecordSourceResolver(),
            $eventDispatcher,
            new FieldValueResolver(),
        );

        $handler(new RecordIndexMessage(
            siteIdentifier: 'main',
            technology: IndexTechnology::Database,
            type: IndexType::Partial,
            indexConfigurationRecordId: 7,
            indexProcessId: 'process-1',
            table: 'tx_example_domain_model_item',
            recordUid: 42,
            language: 0,
        ));
    }

    public function testRecordMovedOutOfConfiguredStorageIsDeIndexed(): void
    {
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: IndexTechnology::Frontend,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [
                'records' => [[
                    'table' => 'tx_example_domain_model_item',
                    'recordStorages' => [12],
                    'fields' => ['title' => 'title'],
                ]],
            ],
            partialIndexing: [],
            languages: [],
        );
        $configurationLoader = self::createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($configuration);

        $record = self::createStub(Record::class);
        $record->method('getPid')->willReturn(13);
        $site = self::createStub(Site::class);
        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);
        $recordSelection = self::createStub(RecordSelection::class);
        $recordSelection->method('findVisibleRecordDataByUid')->willReturn([
            'uid' => 42,
            'pid' => 13,
            'sys_language_uid' => 0,
            'title' => 'Moved item',
        ]);
        $recordSelection->method('mapRecord')->willReturn($record);
        $recordSelection->method('getRecordLanguageId')->willReturn(0);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DeIndexRecordEvent::class))
            ->willReturnCallback(static fn(object $event): object => $event);
        $handler = new RecordIndexingHandler(
            $siteFinder,
            $configurationLoader,
            $recordSelection,
            new RecordFieldDefinitionResolver(self::createStub(TcaSchemaFactory::class)),
            new RecordSourceResolver(),
            $eventDispatcher,
            new FieldValueResolver(),
        );

        $handler(new RecordIndexMessage(
            siteIdentifier: 'main',
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 7,
            indexProcessId: 'process-1',
            table: 'tx_example_domain_model_item',
            recordUid: 42,
            language: 0,
        ));
    }

    public function testRecordIndexingFailuresAreLoggedAndPropagated(): void
    {
        $configuration = new Configuration(
            configurationId: 7,
            pageId: 1,
            technology: IndexTechnology::Http,
            skipNoSearchPages: false,
            contentIndexing: true,
            levels: 0,
            fileMounts: [],
            fileTypes: [],
            configuration: [
                'records' => [[
                    'table' => 'tx_example_domain_model_item',
                    'recordStorages' => [12],
                    'fields' => ['title' => 'title'],
                ]],
            ],
            partialIndexing: [],
            languages: [],
        );
        $configurationLoader = self::createStub(ConfigurationLoader::class);
        $configurationLoader->method('loadByUid')->willReturn($configuration);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findVisibleRecordDataByUid')
            ->willThrowException(new \RuntimeException('Record selection failed'));
        $handler = new RecordIndexingHandler(
            self::createStub(SiteFinder::class),
            $configurationLoader,
            $recordSelection,
            new RecordFieldDefinitionResolver(self::createStub(TcaSchemaFactory::class)),
            new RecordSourceResolver(),
            self::createStub(EventDispatcherInterface::class),
            new FieldValueResolver(),
        );
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $handler->setLogger($logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Record selection failed');
        $handler(new RecordIndexMessage(
            siteIdentifier: 'main',
            technology: IndexTechnology::Http,
            type: IndexType::Partial,
            indexConfigurationRecordId: 7,
            indexProcessId: 'process-1',
            table: 'tx_example_domain_model_item',
            recordUid: 42,
            language: 0,
        ));
    }
}
