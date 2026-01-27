<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\ContainerContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class ContainerContentTypeTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    public function testCanHandleReturnsTrueForContainerTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('container_2cols');

        $recordSelection = $this->createStub(RecordSelection::class);
        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = new ContainerContentType($recordSelection, $contentIndexing);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsTrueForVariousContainerPrefixes(): void
    {
        $recordSelection = $this->createStub(RecordSelection::class);
        $contentIndexing = $this->createStub(ContentIndexing::class);
        $subject = new ContainerContentType($recordSelection, $contentIndexing);

        $types = ['container_1col', 'container_3cols', 'container_accordion'];
        foreach ($types as $type) {
            $record = $this->createStub(Record::class);
            $record->method('getRecordType')->willReturn($type);
            self::assertTrue($subject->canHandle($record), "Should handle type: $type");
        }
    }

    public function testCanHandleReturnsFalseForNonContainerTypes(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('text');

        $recordSelection = $this->createStub(RecordSelection::class);
        $contentIndexing = $this->createStub(ContentIndexing::class);

        $subject = new ContainerContentType($recordSelection, $contentIndexing);

        self::assertFalse($subject->canHandle($record));
    }
}
