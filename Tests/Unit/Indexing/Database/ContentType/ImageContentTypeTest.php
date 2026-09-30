<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\ImageContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Resource\Collection\LazyFileReferenceCollection;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Site\Entity\Site;

class ImageContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForImageType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('image');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ImageContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $headerContentType = $this->createStub(HeaderContentType::class);
        $subject = new ImageContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsHeaderContentType(): void
    {
        $collection = $this->createStub(LazyFileReferenceCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('image');
        $record->method('get')->willReturn($collection);

        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new ImageContentType($headerContentType);
        $subject->addContent($record, $dto);
    }

    public function testAddContentAddsTitleAndDescriptionOfTwoImages(): void
    {
        $first = $this->createStub(FileReference::class);
        $first->method('getTitle')->willReturn('First Title');
        $first->method('getDescription')->willReturn('First Description');

        $second = $this->createStub(FileReference::class);
        $second->method('getTitle')->willReturn('Second Title');
        $second->method('getDescription')->willReturn('Second Description');

        $collection = $this->createStub(LazyFileReferenceCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$first, $second]));

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('image');
        $record->method('get')->willReturnCallback(fn(string $field) => $field === 'image' ? $collection : null);

        $dto = $this->createDto();

        $subject = new ImageContentType($this->createStub(HeaderContentType::class));
        $subject->addContent($record, $dto);

        self::assertSame('First Title First Description Second Title Second Description', $dto->content);
    }
}
