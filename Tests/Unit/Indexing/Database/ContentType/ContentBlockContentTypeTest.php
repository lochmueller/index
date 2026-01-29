<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\ContentBlockContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\ContentBlocks\Definition\TableDefinitionCollection;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\FileReference;
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
    ): TestableContentBlockContentType {
        return new TestableContentBlockContentType(
            $headerContentType ?? $this->createStub(HeaderContentType::class),
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
}

/**
 * Testable subclass that allows overriding protected methods
 */
class TestableContentBlockContentType extends ContentBlockContentType
{
    /**
     * @param array<string, mixed> $testContentBlockList
     */
    public function __construct(
        HeaderContentType $headerContentType,
        private readonly array $testContentBlockList = [],
        private readonly ?TableDefinitionCollection $testTableDefinitionCollection = null,
    ) {
        parent::__construct($headerContentType);
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
