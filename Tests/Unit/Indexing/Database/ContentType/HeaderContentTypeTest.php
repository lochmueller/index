<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class HeaderContentTypeTest extends AbstractTest
{
    private function createRecord(string $type, array $data = []): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('get')->willReturnCallback(fn(string $field) => $data[$field] ?? '');
        return $record;
    }

    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForHeaderType(): void
    {
        $record = $this->createRecord('header');
        $subject = new HeaderContentType();

        self::assertTrue($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'text' => ['text'],
            'image' => ['image'],
            'textmedia' => ['textmedia'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForOtherTypes(string $type): void
    {
        $record = $this->createRecord($type);
        $subject = new HeaderContentType();

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCreatesH1ForLayoutZero(): void
    {
        $record = $this->createRecord('header', [
            'header_layout' => 0,
            'header' => 'Test Header',
            'subheader' => '',
        ]);
        $dto = $this->createDto();

        $subject = new HeaderContentType();
        $subject->addContent($record, $dto);

        self::assertSame('<h1>Test Header</h1>', $dto->content);
    }

    public function testAddContentCreatesCorrectHeadingLevel(): void
    {
        $record = $this->createRecord('header', [
            'header_layout' => 2,
            'header' => 'Test Header',
            'subheader' => '',
        ]);
        $dto = $this->createDto();

        $subject = new HeaderContentType();
        $subject->addContent($record, $dto);

        self::assertSame('<h2>Test Header</h2>', $dto->content);
    }

    public function testAddContentIncludesSubheader(): void
    {
        $record = $this->createRecord('header', [
            'header_layout' => 1,
            'header' => 'Main Header',
            'subheader' => 'Sub Header',
        ]);
        $dto = $this->createDto();

        $subject = new HeaderContentType();
        $subject->addContent($record, $dto);

        self::assertSame('<h1>Main Header</h1><p>Sub Header</p>', $dto->content);
    }

    public function testAddContentSkipsHiddenHeader(): void
    {
        $record = $this->createRecord('header', [
            'header_layout' => 100,
            'header' => 'Hidden Header',
            'subheader' => '',
        ]);
        $dto = $this->createDto();

        $subject = new HeaderContentType();
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddVariantsDoesNothing(): void
    {
        $record = $this->createRecord('header');
        $queue = new \SplQueue();
        $dto = $this->createDto();
        $queue[] = $dto;

        $subject = new HeaderContentType();
        $subject->addVariants($record, $queue);

        self::assertCount(1, $queue);
    }
}
