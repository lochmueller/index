<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\ContentType\ContentBlockContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeIcon;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinition;
use TYPO3\CMS\ContentBlocks\Definition\TcaFieldDefinitionCollection;
use TYPO3\CMS\ContentBlocks\FieldType\FieldTypeInterface as ContentBlockFieldTypeInterface;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\AutomaticLanguageKeysRegistry;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\Field\LanguageFieldType;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\Entity\Site;

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
    ): TestableContentBlockContentType {
        return new TestableContentBlockContentType(
            $headerContentType ?? $this->createStub(HeaderContentType::class),
            $genericRepository ?? $this->createStub(GenericRepository::class),
            $recordFactory ?? $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
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
        $record = $this->createRecord('test_type', ['file_field' => null]);
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

    private function createLoadedContentBlock(string $typeName): LoadedContentBlock
    {
        return new LoadedContentBlock(
            name: 'vendor/block',
            yaml: ['table' => 'tt_content', 'typeName' => $typeName],
            icon: ContentTypeIcon::fromArray([]),
            hostExtension: 'site_package',
            extPath: 'EXT:site_package/ContentBlocks/ContentElements/block',
            contentType: ContentType::CONTENT_ELEMENT,
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
