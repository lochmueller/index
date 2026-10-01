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
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
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

    /**
     * @param array<string, mixed> $fields
     */
    private function createFieldRecord(array $fields, int $uid = 0): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? '');
        $record->method('getUid')->willReturn($uid);
        $record->method('getRecordType')->willReturn('ttaddress_listview');
        return $record;
    }

    private function invokeProtected(AddressContentType $subject, string $method, Record $record): string
    {
        $result = (new \ReflectionClass($subject))->getMethod($method)->invoke($subject, $record);
        self::assertIsString($result);
        return $result;
    }

    private function createSubject(): AddressContentType
    {
        return new AddressContentType(
            $this->createStub(HeaderContentType::class),
            $this->createStub(RecordSelection::class),
            $this->createStub(GenericRepository::class),
        );
    }

    public function testAddContentReturnsEarlyWhenAddressIdIsNegative(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto(['tx_ttaddress_listview' => ['address' => -1]]);

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');
        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::never())->method('setTableName');

        $subject = new AddressContentType($headerContentType, $this->createStub(RecordSelection::class), $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->title);
        self::assertSame('', $dto->content);
    }

    public function testAddContentReturnsAfterHeaderWhenAddressRowNotFound(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto(['tx_ttaddress_listview' => ['address' => 42]]);

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::once())->method('setTableName')->with('tt_address')->willReturnSelf();
        $genericRepository->expects(self::once())->method('findByUid')->with(42)->willReturn(null);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::never())->method('mapRecord');

        $subject = new AddressContentType($headerContentType, $recordSelection, $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->title);
        self::assertSame('', $dto->content);
    }

    public function testAddContentSetsTitleAndAppendsContent(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto(['tx_ttaddress_listview' => ['address' => 7]]);
        $dto->title = 'Original';
        $dto->content = 'Header ';

        $row = ['uid' => 7, 'first_name' => 'Max'];
        $addressRecord = $this->createFieldRecord([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company' => 'ACME',
            'city' => 'Berlin',
        ]);

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::once())->method('setTableName')->with('tt_address')->willReturnSelf();
        $genericRepository->expects(self::once())->method('findByUid')->with(7)->willReturn($row);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())->method('mapRecord')->with('tt_address', $row)->willReturn($addressRecord);

        $subject = new AddressContentType($this->createStub(HeaderContentType::class), $recordSelection, $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('Max Mustermann | Test Site', $dto->title);
        self::assertSame('Header Max Mustermann ACME Berlin ', $dto->content);
    }

    public function testAddContentKeepsTitleWhenFullNameIsEmpty(): void
    {
        $record = $this->createRecordWithType('ttaddress_listview');
        $dto = $this->createDto(['tx_ttaddress_listview' => ['address' => 3]]);
        $dto->title = 'Original';

        $addressRecord = $this->createFieldRecord(['company' => 'ACME']);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->willReturn(['uid' => 3]);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('mapRecord')->willReturn($addressRecord);

        $subject = new AddressContentType($this->createStub(HeaderContentType::class), $recordSelection, $genericRepository);
        $subject->addContent($record, $dto);

        self::assertSame('Original', $dto->title);
        self::assertSame('ACME ', $dto->content);
    }

    /**
     * @param array<string, mixed> $sheets
     */
    private function createFlexFormRecord(array $sheets): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturnCallback(
            fn(string $field) => $field === 'pi_flexform' ? new FlexFormFieldValues($sheets) : null,
        );
        return $record;
    }

    /**
     * @return \SplQueue<DatabaseIndexingDto>
     */
    private function createQueue(DatabaseIndexingDto ...$dtos): \SplQueue
    {
        $queue = new \SplQueue();
        foreach ($dtos as $dto) {
            $queue[] = $dto;
        }
        return $queue;
    }

    public function testAddVariantsKeepsQueueForUnsupportedDisplayMode(): void
    {
        $record = $this->createFlexFormRecord(['sDISPLAY' => ['settings' => ['displayMode' => 'map']]]);
        $dto = $this->createDto();
        $queue = $this->createQueue($dto);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::never())->method('findRecordsOnPage');

        $subject = new AddressContentType($this->createStub(HeaderContentType::class), $recordSelection, $this->createStub(GenericRepository::class));
        $subject->addVariants($record, $queue);

        self::assertCount(1, $queue);
        self::assertSame($dto, $queue->offsetGet(0));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function supportedDisplayModeProvider(): array
    {
        return [
            'default list (no setting)' => [[]],
            'explicit list' => [['sDISPLAY' => ['settings' => ['displayMode' => 'list']]]],
            'single' => [['sDISPLAY' => ['settings' => ['displayMode' => 'single']]]],
        ];
    }

    /**
     * @param array<string, mixed> $sheets
     */
    #[DataProvider('supportedDisplayModeProvider')]
    public function testAddVariantsClearsQueueAndUsesDefaultStorage(array $sheets): void
    {
        $record = $this->createFlexFormRecord($sheets);
        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 5, 2, [], $site);
        $other = new DatabaseIndexingDto('Other', 'Other', 6, 2, [], $site);
        $queue = $this->createQueue($dto, $other);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tt_address', [-99], 2)
            ->willReturn([]);

        $subject = new AddressContentType($this->createStub(HeaderContentType::class), $recordSelection, $this->createStub(GenericRepository::class));
        $subject->addVariants($record, $queue);

        self::assertCount(0, $queue);
    }

    public function testAddVariantsCreatesDtoPerAddressRecordWithStoragePages(): void
    {
        $page1 = $this->createStub(Record::class);
        $page1->method('get')->willReturnMap([['uid', 10]]);
        $page2 = $this->createStub(Record::class);
        $page2->method('get')->willReturnMap([['uid', 20]]);

        $record = $this->createFlexFormRecord([
            'sDEF' => ['settings' => ['pages' => [$page1, $page2]]],
            'sDISPLAY' => ['settings' => ['displayMode' => 'single']],
        ]);
        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 5, 1, ['foo' => 'bar'], $site);
        $queue = $this->createQueue($dto);

        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tt_address', [-99, 10, 20], 1)
            ->willReturn([$this->createFieldRecord([], 101), $this->createFieldRecord([], 102)]);

        $subject = new AddressContentType($this->createStub(HeaderContentType::class), $recordSelection, $this->createStub(GenericRepository::class));
        $subject->addVariants($record, $queue);

        self::assertCount(2, $queue);
        foreach ([101, 102] as $index => $addressUid) {
            $variant = $queue->offsetGet($index);
            self::assertNotSame($dto, $variant);
            self::assertSame('Title', $variant->title);
            self::assertSame('Content', $variant->content);
            self::assertSame(5, $variant->pageUid);
            self::assertSame(1, $variant->languageUid);
            self::assertSame($site, $variant->site);
            self::assertSame([
                '_language' => 1,
                'tx_ttaddress_listview' => [
                    'action' => 'show',
                    'controller' => 'Address',
                    'address' => $addressUid,
                ],
            ], $variant->arguments);
        }
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function fullNameCombinationsProvider(): array
    {
        return [
            'only first name' => [['first_name' => 'John'], 'John'],
            'only last name' => [['last_name' => 'Doe'], 'Doe'],
            'title and last name' => [['title' => 'Dr.', 'last_name' => 'Doe'], 'Dr. Doe'],
            'middle name only' => [['middle_name' => 'William'], 'William'],
            'suffix without name parts' => [['title_suffix' => 'PhD', 'name' => 'Ignored'], ', PhD'],
            'name parts win over name field' => [['first_name' => 'John', 'name' => 'Ignored'], 'John'],
            'everything empty' => [[], ''],
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('fullNameCombinationsProvider')]
    public function testBuildFullNameCombinations(array $fields, string $expected): void
    {
        self::assertSame($expected, $this->invokeProtected($this->createSubject(), 'buildFullName', $this->createFieldRecord($fields)));
    }

    public function testBuildIndexContentReturnsExactStringForAllFields(): void
    {
        $record = $this->createFieldRecord([
            'title' => 'Prof.',
            'first_name' => 'Maria',
            'last_name' => 'Schmidt',
            'title_suffix' => 'MBA',
            'company' => 'University',
            'position' => 'Professor',
            'address' => "Street 1\nBuilding A",
            'zip' => '54321',
            'city' => 'Munich',
            'region' => 'Bavaria',
            'country' => 'Germany',
            'description' => '<p>Some <strong>description</strong> text</p>',
        ]);

        self::assertSame(
            'Prof. Maria Schmidt, MBA University Professor Street 1 Building A 54321 Munich Bavaria Germany Some description text ',
            $this->invokeProtected($this->createSubject(), 'buildIndexContent', $record),
        );
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function indexContentPartsProvider(): array
    {
        return [
            'name fallback' => [['name' => 'Jane Smith'], 'Jane Smith '],
            'company only' => [['company' => 'ACME'], 'ACME '],
            'position only' => [['position' => 'CEO'], 'CEO '],
            'address with multiple newlines' => [['address' => "A\nB\nC"], 'A B C '],
            'zip only' => [['zip' => '12345'], '12345 '],
            'city only' => [['city' => 'Berlin'], 'Berlin '],
            'zip and city' => [['zip' => '12345', 'city' => 'Berlin'], '12345 Berlin '],
            'region only' => [['region' => 'Bavaria'], 'Bavaria '],
            'country only' => [['country' => 'Germany'], 'Germany '],
            'description with tags' => [['description' => '<b>Bold</b> <i>text</i>'], 'Bold text '],
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('indexContentPartsProvider')]
    public function testBuildIndexContentSingleParts(array $fields, string $expected): void
    {
        self::assertSame($expected, $this->invokeProtected($this->createSubject(), 'buildIndexContent', $this->createFieldRecord($fields)));
    }
}
