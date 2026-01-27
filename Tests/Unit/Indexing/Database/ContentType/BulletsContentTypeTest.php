<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\BulletsContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class BulletsContentTypeTest extends AbstractTest
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

    public function testCanHandleReturnsTrueForBulletsType(): void
    {
        $record = $this->createRecord('bullets');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new BulletsContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createRecord('text');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new BulletsContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsHeaderContentType(): void
    {
        $record = $this->createRecord('bullets', ['bodytext' => 'Item 1']);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new BulletsContentType($headerContentType);
        $subject->addContent($record, $dto);
    }

    public function testAddContentAppendsBodytext(): void
    {
        $record = $this->createRecord('bullets', ['bodytext' => "Item 1\nItem 2\nItem 3"]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new BulletsContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame("Item 1\nItem 2\nItem 3", $dto->content);
    }
}
