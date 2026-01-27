<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\MediaContentType;
use Lochmueller\Index\Indexing\Database\ContentType\TextContentType;
use Lochmueller\Index\Indexing\Database\ContentType\TextmediaContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class TextmediaContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForTextmediaType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('textmedia');

        $textContentType = $this->createStub(TextContentType::class);
        $mediaContentType = $this->createStub(MediaContentType::class);

        $subject = new TextmediaContentType($textContentType, $mediaContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $textContentType = $this->createStub(TextContentType::class);
        $mediaContentType = $this->createStub(MediaContentType::class);

        $subject = new TextmediaContentType($textContentType, $mediaContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsBothContentTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('textmedia');

        $dto = $this->createDto();

        $textContentType = $this->createMock(TextContentType::class);
        $textContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $mediaContentType = $this->createMock(MediaContentType::class);
        $mediaContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new TextmediaContentType($textContentType, $mediaContentType);
        $subject->addContent($record, $dto);
    }
}
