<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\ShortcutContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class ShortcutContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForShortcutType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('shortcut');

        $contentIndexing = $this->createStub(ContentIndexing::class);
        $subject = new ShortcutContentType($contentIndexing);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $contentIndexing = $this->createStub(ContentIndexing::class);
        $subject = new ShortcutContentType($contentIndexing);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentProcessesReferencedRecords(): void
    {
        $referencedRecord1 = $this->createStub(Record::class);
        $referencedRecord2 = $this->createStub(Record::class);

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('shortcut');
        $record->method('get')->with('records')->willReturn([$referencedRecord1, $referencedRecord2]);

        $dto = $this->createDto();

        $contentIndexing = $this->createMock(ContentIndexing::class);
        $contentIndexing->expects(self::exactly(2))
            ->method('addContent')
            ->willReturnCallback(function ($rec, $d) use ($referencedRecord1, $referencedRecord2, $dto): void {
                self::assertTrue($rec === $referencedRecord1 || $rec === $referencedRecord2);
                self::assertSame($dto, $d);
            });

        $subject = new ShortcutContentType($contentIndexing);
        $subject->addContent($record, $dto);
    }

    public function testAddContentHandlesEmptyRecords(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('shortcut');
        $record->method('get')->with('records')->willReturn([]);

        $dto = $this->createDto();

        $contentIndexing = $this->createMock(ContentIndexing::class);
        $contentIndexing->expects(self::never())->method('addContent');

        $subject = new ShortcutContentType($contentIndexing);
        $subject->addContent($record, $dto);
    }
}
