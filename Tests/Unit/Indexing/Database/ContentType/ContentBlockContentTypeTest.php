<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\ContentType\ContentBlockContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use TYPO3\CMS\ContentBlocks\Definition\Capability\TableDefinitionCapability;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeInterface;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeIcon;
use TYPO3\CMS\ContentBlocks\Definition\PaletteDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\SqlColumnDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinition;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinition;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinitionCollection;
use TYPO3\CMS\ContentBlocks\FieldType\FieldTypeInterface as ContentBlockFieldTypeInterface;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\AutomaticLanguageKeysRegistry;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\ContentBlocks\Schema\SimpleTcaSchemaFactory;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\LanguageInfo;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\Field\LanguageFieldType;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockContentTypeTest extends AbstractTest
{
    private function createRecord(?string $type, array $data = []): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('get')->willReturnCallback(fn(string $field) => $data[$field] ?? '');
        $record->method('getUid')->willReturn($data['uid'] ?? 1);
        return $record;
    }

    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        $site->method('getAttribute')->willReturn('Test Site');
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    /**
     * @param array<string, LoadedContentBlock> $contentBlockList
     */
    private function createSubject(
        ?HeaderContentType $headerContentType = null,
        array $contentBlockList = [],
        ?TableDefinitionCollection $tableDefinitionCollection = null,
        ?GenericRepository $genericRepository = null,
        ?RecordFactory $recordFactory = null,
        ?TcaSchemaFactory $tcaSchemaFactory = null,
        ?PageRepository $pageRepository = null,
    ): TestableContentBlockContentType {
        return new TestableContentBlockContentType(
            $headerContentType ?? $this->createStub(HeaderContentType::class),
            $genericRepository ?? $this->createStub(GenericRepository::class),
            $recordFactory ?? $this->createStub(RecordFactory::class),
            $pageRepository ?? $this->createStub(PageRepository::class),
            $tcaSchemaFactory ?? $this->createStub(TcaSchemaFactory::class),
            $contentBlockList,
            $tableDefinitionCollection,
        );
    }

    public function testCanHandleReturnsFalseWhenRecordTypeIsNull(): void
    {
        $record = $this->createRecord(null);
        $subject = $this->createSubject();

        self::assertFalse($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function standardContentTypesProvider(): array
    {
        return [
            'text' => ['text'],
            'header' => ['header'],
            'image' => ['image'],
            'textmedia' => ['textmedia'],
            'bullets' => ['bullets'],
            'table' => ['table'],
        ];
    }

    #[DataProvider('standardContentTypesProvider')]
    public function testCanHandleReturnsFalseForStandardContentTypes(string $type): void
    {
        $record = $this->createRecord($type);
        $subject = $this->createSubject();

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentReturnsEarlyWhenRecordTypeIsNull(): void
    {
        $record = $this->createRecord(null);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');

        $subject = $this->createSubject(headerContentType: $headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenContentBlockNotFound(): void
    {
        $record = $this->createRecord('unknown_type');
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');

        $subject = $this->createSubject(headerContentType: $headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testGetFieldValueReturnsStringValue(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => 'test value']);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertSame('test value', $result);
    }

    public function testGetFieldValueReturnsTrimmedString(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => '  trimmed  ']);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertSame('trimmed', $result);
    }

    public function testGetFieldValueReturnsEmptyStringForEmptyValue(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => '']);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertSame('', $result);
    }

    public function testGetFieldValueConvertsIntegerToString(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => 42]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertSame('42', $result);
    }

    public function testGetFieldValueConvertsFloatToString(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => 3.14]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertSame('3.14', $result);
    }

    public function testGetFieldValueReturnsNullForArrayValue(): void
    {
        $record = $this->createRecord('test_type', ['test_field' => ['array', 'value']]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'test_field');

        self::assertNull($result);
    }

    public function testGetFieldValueReturnsNullOnException(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willThrowException(new \Exception('Field not found'));

        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'getFieldValue');
        $result = $reflection->invoke($subject, $record, 'nonexistent_field');

        self::assertNull($result);
    }

    public function testExtractFileContentReturnsEmptyStringForNullFiles(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturn(null);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertSame('', $result);
    }

    public function testExtractFileContentExtractsTitleAndDescription(): void
    {
        $fileReference = $this->createStub(FileReference::class);
        $fileReference->method('getTitle')->willReturn('File Title');
        $fileReference->method('getDescription')->willReturn('File Description');

        $record = $this->createRecord('test_type', ['file_field' => [$fileReference]]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertStringContainsString('File Title', $result);
        self::assertStringContainsString('File Description', $result);
    }

    public function testExtractFileContentHandlesSingleFileReference(): void
    {
        $fileReference = $this->createStub(FileReference::class);
        $fileReference->method('getTitle')->willReturn('Single File');
        $fileReference->method('getDescription')->willReturn('');

        $record = $this->createRecord('test_type', ['file_field' => $fileReference]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertSame('Single File', $result);
    }

    public function testExtractFileContentSkipsEmptyTitleAndDescription(): void
    {
        $fileReference = $this->createStub(FileReference::class);
        $fileReference->method('getTitle')->willReturn('');
        $fileReference->method('getDescription')->willReturn('');

        $record = $this->createRecord('test_type', ['file_field' => [$fileReference]]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertSame('', $result);
    }

    public function testExtractFileContentReturnsEmptyStringOnException(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willThrowException(new \Exception('Field error'));

        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertSame('', $result);
    }

    public function testExtractFileContentHandlesMultipleFileReferences(): void
    {
        $fileReference1 = $this->createStub(FileReference::class);
        $fileReference1->method('getTitle')->willReturn('First File');
        $fileReference1->method('getDescription')->willReturn('First Description');

        $fileReference2 = $this->createStub(FileReference::class);
        $fileReference2->method('getTitle')->willReturn('Second File');
        $fileReference2->method('getDescription')->willReturn('');

        $record = $this->createRecord('test_type', ['file_field' => [$fileReference1, $fileReference2]]);
        $subject = $this->createSubject();

        $reflection = new \ReflectionMethod($subject, 'extractFileContent');
        $result = $reflection->invoke($subject, $record, 'file_field');

        self::assertStringContainsString('First File', $result);
        self::assertStringContainsString('First Description', $result);
        self::assertStringContainsString('Second File', $result);
    }

    public function testAddVariantsDoesNotModifyQueue(): void
    {
        $record = $this->createRecord('test_type');
        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 1, 0, [], $site);

        $queue = new \SplQueue();
        $queue[] = $dto;

        $subject = $this->createSubject();
        $subject->addVariants($record, $queue);

        self::assertCount(1, $queue);
    }

    public function testFindChildRecordsKeepsChildrenOfTablesWithoutLanguageFieldInTranslations(): void
    {
        $row = ['uid' => 7, 'foreign_uid' => 42, 'sorting' => 1];
        $childRecord = $this->createRecord('tx_test_items', ['uid' => 7]);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->expects(self::once())
            ->method('findByParentField')
            ->with(42, 'foreign_uid', [0, -1, 1], null)
            ->willReturn(new \ArrayIterator([$row]));

        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($childRecord);

        $tcaSchema = $this->createStub(TcaSchema::class);
        $tcaSchema->method('isLanguageAware')->willReturn(false);
        $tcaSchemaFactory = $this->createStub(TcaSchemaFactory::class);
        $tcaSchemaFactory->method('get')->willReturn($tcaSchema);

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            recordFactory: $recordFactory,
            tcaSchemaFactory: $tcaSchemaFactory,
        );

        $reflection = new \ReflectionMethod($subject, 'findChildRecords');
        $records = iterator_to_array($reflection->invoke($subject, 42, 'tx_test_items', 'foreign_uid', 1));

        self::assertSame([$childRecord], $records);
    }

    public function testFindChildRecordsRestrictsToLanguageFieldForLanguageAwareTables(): void
    {
        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->expects(self::once())
            ->method('findByParentField')
            ->with(42, 'foreign_uid', [0, -1, 0], 'sys_language_uid')
            ->willReturn(new \ArrayIterator([]));

        $languageCapability = new LanguageAwareSchemaCapability(
            new LanguageFieldType('sys_language_uid', []),
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchema = $this->createStub(TcaSchema::class);
        $tcaSchema->method('isLanguageAware')->willReturn(true);
        $tcaSchema->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactory = $this->createStub(TcaSchemaFactory::class);
        $tcaSchemaFactory->method('get')->willReturn($tcaSchema);

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tcaSchemaFactory: $tcaSchemaFactory,
        );

        $reflection = new \ReflectionMethod($subject, 'findChildRecords');
        iterator_to_array($reflection->invoke($subject, 42, 'tx_test_items', 'foreign_uid', 0));
    }

    public function testCanHandleReturnsTrueForRegisteredContentBlock(): void
    {
        $subject = $this->createSubject(contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block')]);

        self::assertTrue($subject->canHandle($this->createRecord('vendor_block')));
        self::assertFalse($subject->canHandle($this->createRecord('vendor_other')));
    }

    public function testAddContentAddsOnlyHeaderWithoutTableDefinitionCollection(): void
    {
        $record = $this->createRecord('vendor_block');
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())
            ->method('addContent')
            ->with($record, $dto)
            ->willReturnCallback(static function (Record $record, DatabaseIndexingDto $dto): void {
                $dto->content .= '<h1>Header</h1>';
            });

        $subject = $this->createSubject(
            headerContentType: $headerContentType,
            contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block')],
        );
        $subject->addContent($record, $dto);

        self::assertSame('<h1>Header</h1>', $dto->content);
    }

    public function testAddContentAddsOnlyHeaderWhenTableIsNotDefined(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent');

        $subject = $this->createSubject(
            headerContentType: $headerContentType,
            contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block')],
            tableDefinitionCollection: new TableDefinitionCollection(new AutomaticLanguageKeysRegistry()),
        );
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testExtractFieldsFromColumnsCollectsInputAndTextFields(): void
    {
        $record = $this->createRecord('vendor_block', [
            'title_field' => ' Title ',
            'text_field' => 'Some text',
            'number_field' => 42,
        ]);
        $collection = $this->createFieldCollection([
            'title_field' => 'input',
            'text_field' => 'text',
            'number_field' => 'input',
        ]);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['title_field', 'text_field', 'number_field'], $collection, $dto, 0);

        self::assertSame('Title Some text 42', $dto->content);
    }

    public function testExtractFieldsFromColumnsSkipsHeaderFields(): void
    {
        $record = $this->createRecord('vendor_block', [
            'header' => 'Header',
            'subheader' => 'Subheader',
            'bodytext' => 'Body',
        ]);
        $collection = $this->createFieldCollection([
            'header' => 'input',
            'subheader' => 'input',
            'bodytext' => 'text',
        ]);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['header', 'subheader', 'bodytext'], $collection, $dto, 0);

        self::assertSame('Body', $dto->content);
    }

    public function testExtractFieldsFromColumnsSkipsUnknownEmptyAndUnsupportedFields(): void
    {
        $record = $this->createRecord('vendor_block', [
            'unknown' => 'Unknown',
            'empty' => '   ',
            'checkbox' => '1',
            'bodytext' => 'Body',
        ]);
        $collection = $this->createFieldCollection([
            'empty' => 'input',
            'checkbox' => 'check',
            'bodytext' => 'text',
        ]);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['unknown', 'empty', 'checkbox', 'bodytext'], $collection, $dto, 0);

        self::assertSame('Body', $dto->content);
    }

    public function testExtractFieldsFromColumnsAddsFileMetadata(): void
    {
        $fileReference = $this->createStub(FileReference::class);
        $fileReference->method('getTitle')->willReturn('Image Title');
        $fileReference->method('getDescription')->willReturn('');

        $record = $this->createRecord('vendor_block', [
            'bodytext' => 'Body',
            'image' => [$fileReference],
        ]);
        $collection = $this->createFieldCollection([
            'bodytext' => 'text',
            'image' => 'file',
        ]);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['bodytext', 'image'], $collection, $dto, 0);

        self::assertSame('Body Image Title', $dto->content);
    }

    public function testExtractFieldsFromColumnsAppendsToExistingContent(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $collection = $this->createFieldCollection(['bodytext' => 'text']);
        $dto = $this->createDto();
        $dto->content = '<h1>Header</h1>';

        $this->invokeExtractFieldsFromColumns($record, ['bodytext'], $collection, $dto, 0);

        self::assertSame('<h1>Header</h1>Body', $dto->content);
    }

    public function testExtractFieldsFromColumnsStopsAtMaxInlineDepth(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $collection = $this->createFieldCollection(['bodytext' => 'text']);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['bodytext'], $collection, $dto, 11);

        self::assertSame('', $dto->content);
    }

    public function testExtractInlineContentReturnsEmptyStringWithoutForeignTable(): void
    {
        $fieldDefinition = $this->createFieldDefinition('items', 'inline', ['config' => ['type' => 'inline']]);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('findByParentField');

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tableDefinitionCollection: new TableDefinitionCollection(new AutomaticLanguageKeysRegistry()),
        );

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke($subject, $this->createRecord('vendor_block'), $fieldDefinition, $this->createDto(), 0);

        self::assertSame('', $result);
    }

    public function testExtractInlineContentReturnsEmptyStringWhenForeignTableIsNotDefined(): void
    {
        $fieldDefinition = $this->createFieldDefinition('items', 'inline', [
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_vendor_items',
                'foreign_field' => 'foreign_table_parent_uid',
            ],
        ]);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('findByParentField');

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tableDefinitionCollection: new TableDefinitionCollection(new AutomaticLanguageKeysRegistry()),
        );

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke($subject, $this->createRecord('vendor_block'), $fieldDefinition, $this->createDto(), 0);

        self::assertSame('', $result);
    }

    public function testAddContentAddsOnlyHeaderWhenTypeNameIsMissing(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $dto = $this->createDto();

        $subject = $this->createSubject(
            contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block', ['table' => 'tt_content'])],
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tt_content', $this->createFieldCollection(['bodytext' => 'text']), ['vendor_block' => ['bodytext']]),
            ),
        );
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentAddsOnlyHeaderWhenTypeIsNotDefined(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $dto = $this->createDto();

        $subject = $this->createSubject(
            contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block')],
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tt_content', $this->createFieldCollection(['bodytext' => 'text']), ['vendor_other' => ['bodytext']]),
            ),
        );
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentExtractsContentBlockFieldsIncludingInlineChildren(): void
    {
        $record = $this->createRecord('vendor_block', [
            'uid' => 42,
            'header' => 'Header',
            'bodytext' => ' Body ',
        ]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $headerContentType->method('addContent')->willReturnCallback(static function (Record $record, DatabaseIndexingDto $dto): void {
            $dto->content .= '<h1>Header</h1>';
        });

        $parentFields = $this->createFieldCollection(['header' => 'input', 'bodytext' => 'text']);
        $parentFields->addField($this->createInlineFieldDefinition('items', 'tx_vendor_items'));
        $childFields = $this->createFieldCollection(['title' => 'input', 'description' => 'text']);

        $children = [
            7 => $this->createRecord('tx_vendor_items', ['uid' => 7, 'title' => 'Child One', 'description' => 'First child']),
            8 => $this->createRecord('tx_vendor_items', ['uid' => 8]),
            9 => $this->createRecord('tx_vendor_items', ['uid' => 9, 'title' => 'Child Two']),
        ];

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->expects(self::once())
            ->method('findByParentField')
            ->with(42, 'foreign_table_parent_uid', [0, -1, 0], null)
            ->willReturn(new \ArrayIterator([['uid' => 7], ['uid' => 8], ['uid' => 9]]));

        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')
            ->willReturnCallback(static fn(string $table, array $row): Record => $children[$row['uid']]);

        $subject = $this->createSubject(
            headerContentType: $headerContentType,
            contentBlockList: ['vendor_block' => $this->createLoadedContentBlock('vendor_block')],
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tt_content', $parentFields, ['vendor_block' => ['header', 'bodytext', 'items']]),
                $this->createTableDefinition('tx_vendor_items', $childFields),
            ),
            genericRepository: $genericRepository,
            recordFactory: $recordFactory,
            tcaSchemaFactory: $this->createTcaSchemaFactory(false),
        );
        $subject->addContent($record, $dto);

        self::assertSame('<h1>Header</h1>Body Child One First child Child Two', $dto->content);
    }

    public function testExtractFieldsFromColumnsSkipsEmptyInlineContent(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $collection = $this->createFieldCollection(['bodytext' => 'text']);
        $collection->addField($this->createFieldDefinition('items', 'inline', ['config' => ['type' => 'inline']]));
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['items', 'bodytext'], $collection, $dto, 0);

        self::assertSame('Body', $dto->content);
    }

    public function testExtractFieldsFromColumnsSkipsEmptyFileContent(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body', 'image' => []]);
        $collection = $this->createFieldCollection(['image' => 'file', 'bodytext' => 'text']);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['image', 'bodytext'], $collection, $dto, 0);

        self::assertSame('Body', $dto->content);
    }

    public function testExtractFieldsFromColumnsProcessesMaxInlineDepth(): void
    {
        $record = $this->createRecord('vendor_block', ['bodytext' => 'Body']);
        $collection = $this->createFieldCollection(['bodytext' => 'text']);
        $dto = $this->createDto();

        $this->invokeExtractFieldsFromColumns($record, ['bodytext'], $collection, $dto, 10);

        self::assertSame('Body', $dto->content);
    }

    public function testExtractInlineContentReturnsEmptyStringWithoutForeignField(): void
    {
        $fieldDefinition = $this->createFieldDefinition('items', 'inline', [
            'config' => ['type' => 'inline', 'foreign_table' => 'tx_vendor_items'],
        ]);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('findByParentField');

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tx_vendor_items', $this->createFieldCollection(['title' => 'input'])),
            ),
        );

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke($subject, $this->createRecord('vendor_block'), $fieldDefinition, $this->createDto(), 0);

        self::assertSame('', $result);
    }

    public function testExtractInlineContentReturnsEmptyStringWithoutTableDefinitionCollection(): void
    {
        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('findByParentField');

        $subject = $this->createSubject(genericRepository: $genericRepository);

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke(
            $subject,
            $this->createRecord('vendor_block'),
            $this->createInlineFieldDefinition('items', 'tx_vendor_items'),
            $this->createDto(),
            0,
        );

        self::assertSame('', $result);
    }

    public function testExtractInlineContentReturnsEmptyStringWhenParentUidIsNotAccessible(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willThrowException(new \Exception('Field uid not available'));

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('findByParentField');

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tx_vendor_items', $this->createFieldCollection(['title' => 'input'])),
            ),
        );

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke(
            $subject,
            $record,
            $this->createInlineFieldDefinition('items', 'tx_vendor_items'),
            $this->createDto(),
            0,
        );

        self::assertSame('', $result);
    }

    public function testExtractInlineContentUsesLanguageOfParentRecord(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturnCallback(static fn(string $field): mixed => $field === 'uid' ? '42' : '');
        $record->method('getLanguageId')->willReturn(2);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->expects(self::once())
            ->method('findByParentField')
            ->with(42, 'foreign_table_parent_uid', [0, -1, 2], null)
            ->willReturn(new \ArrayIterator([]));

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            tcaSchemaFactory: $this->createTcaSchemaFactory(false),
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tx_vendor_items', $this->createFieldCollection(['title' => 'input'])),
            ),
        );

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke(
            $subject,
            $record,
            $this->createInlineFieldDefinition('items', 'tx_vendor_items'),
            $this->createDto(),
            0,
        );

        self::assertSame('', $result);
    }

    public function testExtractInlineContentDoesNotModifyGivenDto(): void
    {
        $dto = $this->createDto();
        $dto->content = 'Existing';

        $subject = $this->createInlineSubject([
            $this->createRecord('tx_vendor_items', ['uid' => 7, 'title' => 'Child']),
        ]);

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke(
            $subject,
            $this->createRecord('vendor_block', ['uid' => 42]),
            $this->createInlineFieldDefinition('items', 'tx_vendor_items'),
            $dto,
            0,
        );

        self::assertSame('Child', $result);
        self::assertSame('Existing', $dto->content);
    }

    public function testExtractInlineContentStopsChildrenBeyondMaxInlineDepth(): void
    {
        $subject = $this->createInlineSubject([
            $this->createRecord('tx_vendor_items', ['uid' => 7, 'title' => 'Child']),
        ]);

        $reflection = new \ReflectionMethod($subject, 'extractInlineContent');
        $result = $reflection->invoke(
            $subject,
            $this->createRecord('vendor_block', ['uid' => 42]),
            $this->createInlineFieldDefinition('items', 'tx_vendor_items'),
            $this->createDto(),
            10,
        );

        self::assertSame('', $result);
    }

    public function testFindChildRecordsUsesLanguageOverlayForTranslations(): void
    {
        $translated = $this->createLanguageRecord(1);
        $allLanguages = $this->createLanguageRecord(-1);
        $resultsByUid = [
            2 => $translated,
            3 => $allLanguages,
            4 => $this->createLanguageRecord(0),
            5 => $this->createLanguageRecord(null),
            6 => $this->createStub(RecordInterface::class),
        ];

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByParentField')->willReturn(new \ArrayIterator([
            ['uid' => 1],
            ['uid' => 2],
            ['uid' => 3],
            ['uid' => 4],
            ['uid' => 5],
            ['uid' => 6],
            ['uid' => 7],
        ]));

        $languageIds = [];
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturnCallback(
            static function (string $table, array $row, LanguageAspect $languageAspect) use (&$languageIds): ?array {
                $languageIds[] = $languageAspect->getId();
                return $row['uid'] === 1 ? null : $row + ['sys_language_uid' => 1];
            },
        );

        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturnCallback(
            static function (string $table, array $row) use ($resultsByUid): RecordInterface {
                if (!isset($resultsByUid[$row['uid']])) {
                    throw new \RuntimeException('Record could not be resolved');
                }
                self::assertSame(1, $row['sys_language_uid']);
                return $resultsByUid[$row['uid']];
            },
        );

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            recordFactory: $recordFactory,
            tcaSchemaFactory: $this->createTcaSchemaFactory(true),
            pageRepository: $pageRepository,
        );

        $reflection = new \ReflectionMethod($subject, 'findChildRecords');
        $records = iterator_to_array($reflection->invoke($subject, 42, 'tx_test_items', 'foreign_uid', 1), false);

        self::assertSame([$translated, $allLanguages], $records);
        self::assertSame([1, 1, 1, 1, 1, 1, 1], $languageIds);
    }

    public function testFindChildRecordsSkipsUnresolvableRecordsInDefaultLanguage(): void
    {
        $childRecord = $this->createRecord('tx_test_items', ['uid' => 3]);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByParentField')->willReturn(new \ArrayIterator([
            ['uid' => 1],
            ['uid' => 2],
            ['uid' => 3],
        ]));

        $nonRecord = $this->createStub(RecordInterface::class);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturnCallback(
            static function (string $table, array $row) use ($nonRecord, $childRecord): RecordInterface {
                return match ($row['uid']) {
                    1 => $nonRecord,
                    2 => throw new \RuntimeException('Record could not be resolved'),
                    default => $childRecord,
                };
            },
        );

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects(self::never())->method('getLanguageOverlay');

        $subject = $this->createSubject(
            genericRepository: $genericRepository,
            recordFactory: $recordFactory,
            tcaSchemaFactory: $this->createTcaSchemaFactory(true),
            pageRepository: $pageRepository,
        );

        $reflection = new \ReflectionMethod($subject, 'findChildRecords');
        $records = iterator_to_array($reflection->invoke($subject, 42, 'tx_test_items', 'foreign_uid', 0), false);

        self::assertSame([$childRecord], $records);
    }

    /**
     * Runs in a separate process because the lookups cache their results in function-level static variables.
     */
    #[RunInSeparateProcess]
    public function testContentBlockLookupsAreEmptyWhenContentBlocksIsNotActive(): void
    {
        $this->resetSingletonInstances = true;

        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->expects(self::exactly(2))
            ->method('isPackageActive')
            ->with('content_blocks')
            ->willReturn(false);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $subject = $this->createRealSubject();
        $getContentBlockList = new \ReflectionMethod($subject, 'getContentBlockList');
        $getTableDefinitionCollection = new \ReflectionMethod($subject, 'getTableDefinitionCollection');

        self::assertSame([], $getContentBlockList->invoke($subject));
        self::assertSame([], $getContentBlockList->invoke($subject));
        self::assertNull($getTableDefinitionCollection->invoke($subject));
        self::assertNull($getTableDefinitionCollection->invoke($subject));
        self::assertFalse($subject->canHandle($this->createRecord('vendor_block')));
    }

    /**
     * Runs in a separate process because the lookups cache their results in function-level static variables.
     */
    #[RunInSeparateProcess]
    public function testContentBlockLookupsUseContentBlocksServicesWhenActive(): void
    {
        $this->resetSingletonInstances = true;

        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn(true);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);

        $simpleTcaSchemaFactory = $this->createStub(SimpleTcaSchemaFactory::class);
        $simpleTcaSchemaFactory->method('has')->willReturn(false);

        $contentElement = $this->createLoadedContentBlock(
            'vendor_block',
            ['table' => 'tt_content', 'typeName' => 'vendor_block', 'typeField' => 'CType'],
        );
        $registry = new ContentBlockRegistry($simpleTcaSchemaFactory);
        $registry->register($this->createLoadedContentBlock('', ['table' => 'tt_content'], 'vendor/without-type-name'));
        $registry->register($contentElement);
        $registry->register($this->createLoadedContentBlock(
            'vendor_record',
            ['table' => 'tx_vendor_record', 'typeName' => 'vendor_record', 'typeField' => 'type'],
            'vendor/record',
            ContentType::RECORD_TYPE,
        ));
        GeneralUtility::addInstance(ContentBlockRegistry::class, $registry);

        $tableDefinitionCollection = new TableDefinitionCollection(new AutomaticLanguageKeysRegistry());
        GeneralUtility::addInstance(TableDefinitionCollection::class, $tableDefinitionCollection);

        $subject = $this->createRealSubject();
        $getContentBlockList = new \ReflectionMethod($subject, 'getContentBlockList');
        $getTableDefinitionCollection = new \ReflectionMethod($subject, 'getTableDefinitionCollection');

        // Second calls must be served from the static cache, as no further instances are queued.
        self::assertSame(['vendor_block' => $contentElement], $getContentBlockList->invoke($subject));
        self::assertSame(['vendor_block' => $contentElement], $getContentBlockList->invoke($subject));
        self::assertSame($tableDefinitionCollection, $getTableDefinitionCollection->invoke($subject));
        self::assertSame($tableDefinitionCollection, $getTableDefinitionCollection->invoke($subject));
        self::assertTrue($subject->canHandle($this->createRecord('vendor_block')));
        self::assertFalse($subject->canHandle($this->createRecord('vendor_record')));
    }

    private function createRealSubject(): ContentBlockContentType
    {
        return new ContentBlockContentType(
            $this->createStub(HeaderContentType::class),
            $this->createStub(GenericRepository::class),
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
        );
    }

    /**
     * @param list<Record> $childRecords
     */
    private function createInlineSubject(array $childRecords): TestableContentBlockContentType
    {
        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByParentField')->willReturn(new \ArrayIterator(array_fill(0, count($childRecords), ['uid' => 1])));

        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturnOnConsecutiveCalls(...$childRecords);

        return $this->createSubject(
            tableDefinitionCollection: $this->createTableDefinitionCollection(
                $this->createTableDefinition('tx_vendor_items', $this->createFieldCollection(['title' => 'input'])),
            ),
            genericRepository: $genericRepository,
            recordFactory: $recordFactory,
            tcaSchemaFactory: $this->createTcaSchemaFactory(false),
        );
    }

    private function createLanguageRecord(?int $languageId): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getLanguageInfo')->willReturn($languageId === null ? null : new LanguageInfo($languageId, null, null));
        return $record;
    }

    private function createTcaSchemaFactory(bool $languageAware): TcaSchemaFactory
    {
        $tcaSchema = $this->createStub(TcaSchema::class);
        $tcaSchema->method('isLanguageAware')->willReturn($languageAware);
        if ($languageAware) {
            $tcaSchema->method('getCapability')->willReturn(new LanguageAwareSchemaCapability(
                new LanguageFieldType('sys_language_uid', []),
                $this->createStub(FieldTypeInterface::class),
                null,
                null,
            ));
        }
        $tcaSchemaFactory = $this->createStub(TcaSchemaFactory::class);
        $tcaSchemaFactory->method('get')->willReturn($tcaSchema);
        return $tcaSchemaFactory;
    }

    private function createInlineFieldDefinition(string $identifier, string $foreignTable): TcaFieldDefinition
    {
        return $this->createFieldDefinition($identifier, 'inline', [
            'config' => [
                'type' => 'inline',
                'foreign_table' => $foreignTable,
                'foreign_field' => 'foreign_table_parent_uid',
            ],
        ]);
    }

    /**
     * @param array<string, array<string>> $types typeName => columns
     */
    private function createTableDefinition(string $table, TcaFieldDefinitionCollection $fields, array $types = []): TableDefinition
    {
        $typeCollection = new ContentTypeDefinitionCollection();
        foreach ($types as $typeName => $columns) {
            $typeDefinition = $this->createStub(ContentTypeInterface::class);
            $typeDefinition->method('getTypeName')->willReturn($typeName);
            $typeDefinition->method('getColumns')->willReturn($columns);
            $typeCollection->addType($typeDefinition);
        }

        return new TableDefinition(
            table: $table,
            capability: TableDefinitionCapability::createFromArray([]),
            typeField: $types === [] ? null : 'CType',
            contentType: $table === 'tt_content' ? ContentType::CONTENT_ELEMENT : ContentType::RECORD_TYPE,
            contentTypeDefinitionCollection: $typeCollection,
            sqlColumnDefinitionCollection: SqlColumnDefinitionCollection::createFromArray([], $table),
            tcaFieldDefinitionCollection: $fields,
            paletteDefinitionCollection: PaletteDefinitionCollection::createFromArray([], $table),
            parentReferences: [],
        );
    }

    private function createTableDefinitionCollection(TableDefinition ...$tableDefinitions): TableDefinitionCollection
    {
        $collection = new TableDefinitionCollection(new AutomaticLanguageKeysRegistry());
        foreach ($tableDefinitions as $tableDefinition) {
            $collection->addTable($tableDefinition);
        }
        return $collection;
    }

    /**
     * @param array<string, mixed>|null $yaml
     */
    private function createLoadedContentBlock(
        string $typeName,
        ?array $yaml = null,
        string $name = 'vendor/block',
        ContentType $contentType = ContentType::CONTENT_ELEMENT,
    ): LoadedContentBlock {
        return new LoadedContentBlock(
            name: $name,
            yaml: $yaml ?? ['table' => 'tt_content', 'typeName' => $typeName],
            icon: ContentTypeIcon::fromArray([]),
            hostExtension: 'site_package',
            extPath: 'EXT:site_package/ContentBlocks/ContentElements/block',
            contentType: $contentType,
        );
    }

    /**
     * @param array<string, mixed> $tca
     */
    private function createFieldDefinition(string $identifier, string $tcaType, array $tca = []): TcaFieldDefinition
    {
        $fieldType = $this->createStub(ContentBlockFieldTypeInterface::class);
        $fieldType->method('getTcaType')->willReturn($tcaType);
        $fieldType->method('getTca')->willReturn($tca);

        return new TcaFieldDefinition(
            parentContentType: ContentType::CONTENT_ELEMENT,
            parentTable: 'tt_content',
            identifier: $identifier,
            uniqueIdentifier: $identifier,
            labelPath: '',
            descriptionPath: '',
            placeholderPath: '',
            useExistingField: false,
            fieldType: $fieldType,
        );
    }

    /**
     * @param array<string, string> $fields identifier => TCA type
     */
    private function createFieldCollection(array $fields): TcaFieldDefinitionCollection
    {
        $collection = new TcaFieldDefinitionCollection();
        foreach ($fields as $identifier => $tcaType) {
            $collection->addField($this->createFieldDefinition($identifier, $tcaType));
        }
        return $collection;
    }

    /**
     * @param array<string> $columns
     */
    private function invokeExtractFieldsFromColumns(
        Record $record,
        array $columns,
        TcaFieldDefinitionCollection $collection,
        DatabaseIndexingDto $dto,
        int $depth,
    ): void {
        $subject = $this->createSubject();
        $reflection = new \ReflectionMethod($subject, 'extractFieldsFromColumns');
        $reflection->invoke($subject, $record, $columns, $collection, 'tt_content', $dto, $depth);
    }
}

class TestableContentBlockContentType extends ContentBlockContentType
{
    /**
     * @param array<string, mixed> $testContentBlockList
     */
    public function __construct(
        HeaderContentType $headerContentType,
        GenericRepository $genericRepository,
        RecordFactory $recordFactory,
        PageRepository $pageRepository,
        TcaSchemaFactory $tcaSchemaFactory,
        private readonly array $testContentBlockList = [],
        private readonly ?TableDefinitionCollection $testTableDefinitionCollection = null,
    ) {
        parent::__construct($headerContentType, $genericRepository, $recordFactory, $pageRepository, $tcaSchemaFactory);
    }

    protected function getContentBlockList(): array
    {
        return $this->testContentBlockList;
    }

    protected function getTableDefinitionCollection(): ?TableDefinitionCollection
    {
        return $this->testTableDefinitionCollection;
    }
}
