<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\ImageContentType;
use Lochmueller\Index\Indexing\Database\ContentType\TextContentType;
use Lochmueller\Index\Indexing\Database\ContentType\TextpicContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class TextpicContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForTextpicType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('textpic');

        $textContentType = $this->createStub(TextContentType::class);
        $imageContentType = $this->createStub(ImageContentType::class);

        $subject = new TextpicContentType($textContentType, $imageContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $textContentType = $this->createStub(TextContentType::class);
        $imageContentType = $this->createStub(ImageContentType::class);

        $subject = new TextpicContentType($textContentType, $imageContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsBothContentTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('textpic');

        $dto = $this->createDto();

        $textContentType = $this->createMock(TextContentType::class);
        $textContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $imageContentType = $this->createMock(ImageContentType::class);
        $imageContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new TextpicContentType($textContentType, $imageContentType);
        $subject->addContent($record, $dto);
    }
}
