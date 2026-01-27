<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\MediaContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection;
use TYPO3\CMS\Core\Site\Entity\Site;

class MediaContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForMediaType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('media');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new MediaContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new MediaContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsHeaderContentType(): void
    {
        $collection = $this->createStub(LazyFileReferenceCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('media');
        $record->method('get')->willReturn($collection);

        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new MediaContentType($headerContentType);
        $subject->addContent($record, $dto);
    }
}
