<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database;

use Lochmueller\Index\Event\ContentType\HandleContentTypeEvent;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\ContentTypeInterface;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class ContentIndexingTest extends AbstractTest
{
    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('Title', '', 1, 0, [], $site);
    }

    private function createRecord(string $type = 'text'): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        return $record;
    }

    public function testGetVariantsCallsMatchingContentType(): void
    {
        $record = $this->createRecord('text');
        $queue = new \SplQueue();

        $contentType = $this->createMock(ContentTypeInterface::class);
        $contentType->method('canHandle')->willReturn(true);
        $contentType->expects(self::once())->method('addVariants')->with($record, $queue);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $subject = new ContentIndexing([$contentType], $eventDispatcher);
        $subject->getVariants($record, $queue);
    }

    public function testGetVariantsStopsAfterFirstMatch(): void
    {
        $record = $this->createRecord('text');
        $queue = new \SplQueue();

        $contentType1 = $this->createMock(ContentTypeInterface::class);
        $contentType1->method('canHandle')->willReturn(true);
        $contentType1->expects(self::once())->method('addVariants');

        $contentType2 = $this->createMock(ContentTypeInterface::class);
        $contentType2->expects(self::never())->method('canHandle');
        $contentType2->expects(self::never())->method('addVariants');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $subject = new ContentIndexing([$contentType1, $contentType2], $eventDispatcher);
        $subject->getVariants($record, $queue);
    }

    public function testGetVariantsSkipsNonMatchingContentTypes(): void
    {
        $record = $this->createRecord('text');
        $queue = new \SplQueue();

        $contentType1 = $this->createMock(ContentTypeInterface::class);
        $contentType1->method('canHandle')->willReturn(false);
        $contentType1->expects(self::never())->method('addVariants');

        $contentType2 = $this->createMock(ContentTypeInterface::class);
        $contentType2->method('canHandle')->willReturn(true);
        $contentType2->expects(self::once())->method('addVariants');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $subject = new ContentIndexing([$contentType1, $contentType2], $eventDispatcher);
        $subject->getVariants($record, $queue);
    }

    public function testAddContentCallsMatchingContentType(): void
    {
        $record = $this->createRecord('text');
        $dto = $this->createDto();

        $contentType = $this->createMock(ContentTypeInterface::class);
        $contentType->method('canHandle')->willReturn(true);
        $contentType->expects(self::once())->method('addContent')->with($record, $dto);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $subject = new ContentIndexing([$contentType], $eventDispatcher);
        $subject->addContent($record, $dto);
    }

    public function testAddContentDispatchesHandleContentTypeEvent(): void
    {
        $record = $this->createRecord('text');
        $dto = $this->createDto();

        $contentType = $this->createStub(ContentTypeInterface::class);
        $contentType->method('canHandle')->willReturn(true);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn(HandleContentTypeEvent $event): bool => $event->record === $record && $event->defaultHandled === true))
            ->willReturnCallback(fn($event) => $event);

        $subject = new ContentIndexing([$contentType], $eventDispatcher);
        $subject->addContent($record, $dto);
    }

    public function testAddContentReturnsNullWhenNoContentTypeMatches(): void
    {
        $record = $this->createRecord('unknown');
        $dto = $this->createDto();

        $contentType = $this->createStub(ContentTypeInterface::class);
        $contentType->method('canHandle')->willReturn(false);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (HandleContentTypeEvent $event) {
            $event->content = null;
            return $event;
        });

        $subject = new ContentIndexing([$contentType], $eventDispatcher);
        $result = $subject->addContent($record, $dto);

        self::assertNull($result);
    }

    public function testAddContentReturnsContentFromEvent(): void
    {
        $record = $this->createRecord('text');
        $dto = $this->createDto();
        $dto->content = 'Test content';

        $contentType = $this->createStub(ContentTypeInterface::class);
        $contentType->method('canHandle')->willReturn(true);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $subject = new ContentIndexing([$contentType], $eventDispatcher);
        $result = $subject->addContent($record, $dto);

        self::assertSame('Test content', $result);
    }
}
