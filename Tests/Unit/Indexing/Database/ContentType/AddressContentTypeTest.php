<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\ContentType\AddressContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class AddressContentTypeTest extends AbstractTest
{
    private function createRecordWithType(string $type): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        return $record;
    }

    public function testCanHandleReturnsTrueForTtAddressListView(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);

        self::assertTrue($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'text' => ['text'],
            'header' => ['header'],
            'news_pi1' => ['news_pi1'],
            'calendarize_listdetail' => ['calendarize_listdetail'],
            'image' => ['image'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForUnsupportedTypes(string $type): void
    {
        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);

        self::assertFalse($subject->canHandle($record));
    }

    private function createDto(array $arguments = []): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        $site->method('getAttribute')->willReturn('Test Site');
        return new DatabaseIndexingDto('', '', 1, 0, $arguments, $site);
    }

    public function testAddContentReturnsEarlyWhenNoAddressId(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenAddressIdIsZero(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto(['tx_ttaddress_listview' => ['address' => 0]]);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    /**
     * @return \Generator<string, array{array<string, string>, string}>
     */
    public static function addressFieldsProvider(): \Generator
    {
        yield 'full name with all parts' => [
            [
                'title' => 'Dr.',
                'first_name' => 'John',
                'middle_name' => 'William',
                'last_name' => 'Doe',
                'title_suffix' => 'PhD',
                'company' => '',
                'position' => '',
                'address' => '',
                'zip' => '',
                'city' => '',
                'region' => '',
                'country' => '',
                'description' => '',
                'name' => '',
            ],
            'Dr. John William Doe, PhD',
        ];

        yield 'name fallback when no parts' => [
            [
                'title' => '',
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
                'title_suffix' => '',
                'company' => '',
                'position' => '',
                'address' => '',
                'zip' => '',
                'city' => '',
                'region' => '',
                'country' => '',
                'description' => '',
                'name' => 'Jane Smith',
            ],
            'Jane Smith',
        ];

        yield 'company and position' => [
            [
                'title' => '',
                'first_name' => 'Max',
                'middle_name' => '',
                'last_name' => 'Mustermann',
                'title_suffix' => '',
                'company' => 'ACME Corp',
                'position' => 'Developer',
                'address' => '',
                'zip' => '',
                'city' => '',
                'region' => '',
                'country' => '',
                'description' => '',
                'name' => '',
            ],
            'Max Mustermann',
        ];

        yield 'full address' => [
            [
                'title' => '',
                'first_name' => 'Test',
                'middle_name' => '',
                'last_name' => 'User',
                'title_suffix' => '',
                'company' => '',
                'position' => '',
                'address' => 'Main Street 123',
                'zip' => '12345',
                'city' => 'Berlin',
                'region' => 'Brandenburg',
                'country' => 'Germany',
                'description' => '',
                'name' => '',
            ],
            'Test User',
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('addressFieldsProvider')]
    public function testBuildFullNameReturnsExpectedName(array $fields, string $expectedName): void
    {
        $addressRecord = $this->createStub(Record::class);
        $addressRecord->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? '');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('buildFullName');

        $result = $method->invoke($subject, $addressRecord);

        self::assertSame($expectedName, $result);
    }

    public function testBuildIndexContentIncludesAllFields(): void
    {
        $fields = [
            'title' => 'Prof.',
            'first_name' => 'Maria',
            'middle_name' => '',
            'last_name' => 'Schmidt',
            'title_suffix' => '',
            'company' => 'University',
            'position' => 'Professor',
            'address' => "Street 1\nBuilding A",
            'zip' => '54321',
            'city' => 'Munich',
            'region' => 'Bavaria',
            'country' => 'Germany',
            'description' => '<p>Some <strong>description</strong> text</p>',
            'name' => '',
        ];

        $addressRecord = $this->createStub(Record::class);
        $addressRecord->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? '');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('buildIndexContent');

        $result = $method->invoke($subject, $addressRecord);

        self::assertStringContainsString('Prof. Maria Schmidt', $result);
        self::assertStringContainsString('University', $result);
        self::assertStringContainsString('Professor', $result);
        self::assertStringContainsString('Street 1 Building A', $result);
        self::assertStringContainsString('54321 Munich', $result);
        self::assertStringContainsString('Bavaria', $result);
        self::assertStringContainsString('Germany', $result);
        self::assertStringContainsString('Some description text', $result);
        self::assertStringNotContainsString('<p>', $result);
        self::assertStringNotContainsString('<strong>', $result);
    }

    public function testBuildIndexContentHandlesEmptyFields(): void
    {
        $fields = [
            'title' => '',
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'title_suffix' => '',
            'company' => '',
            'position' => '',
            'address' => '',
            'zip' => '',
            'city' => '',
            'region' => '',
            'country' => '',
            'description' => '',
            'name' => '',
        ];

        $addressRecord = $this->createStub(Record::class);
        $addressRecord->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? '');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $recordSelection = $this->createStub(RecordSelection::class);
        $genericRepository = $this->createStub(GenericRepository::class);

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);

        $reflection = new \ReflectionClass($subject);
        $method = $reflection->getMethod('buildIndexContent');

        $result = $method->invoke($subject, $addressRecord);

        self::assertSame(' ', $result);
    }
}
