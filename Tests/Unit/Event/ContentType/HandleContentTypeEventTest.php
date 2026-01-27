<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event\ContentType;

use Lochmueller\Index\Event\ContentType\HandleContentTypeEvent;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class HandleContentTypeEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $record = $this->createStub(Record::class);
        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto(
            title: 'Test Title',
            content: 'Test Content',
            pageUid: 1,
            languageUid: 0,
            arguments: [],
            site: $site,
        );

        $subject = new HandleContentTypeEvent(
            record: $record,
            defaultHandled: true,
            content: 'Initial Content',
            dto: $dto,
        );

        self::assertSame($record, $subject->record);
        self::assertTrue($subject->defaultHandled);
        self::assertSame('Initial Content', $subject->content);
        self::assertSame($dto, $subject->dto);
    }

    public function testContentCanBeModified(): void
    {
        $record = $this->createStub(Record::class);
        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto(
            title: 'Test Title',
            content: 'Test Content',
            pageUid: 1,
            languageUid: 0,
            arguments: [],
            site: $site,
        );

        $subject = new HandleContentTypeEvent(
            record: $record,
            defaultHandled: false,
            content: null,
            dto: $dto,
        );

        $subject->content = 'Modified Content';

        self::assertSame('Modified Content', $subject->content);
    }

    public function testDtoCanBeModified(): void
    {
        $record = $this->createStub(Record::class);
        $site = $this->createStub(Site::class);
        $dto1 = new DatabaseIndexingDto(
            title: 'Title 1',
            content: 'Content 1',
            pageUid: 1,
            languageUid: 0,
            arguments: [],
            site: $site,
        );
        $dto2 = new DatabaseIndexingDto(
            title: 'Title 2',
            content: 'Content 2',
            pageUid: 2,
            languageUid: 1,
            arguments: ['key' => 'value'],
            site: $site,
        );

        $subject = new HandleContentTypeEvent(
            record: $record,
            defaultHandled: true,
            content: 'Content',
            dto: $dto1,
        );

        $subject->dto = $dto2;

        self::assertSame($dto2, $subject->dto);
        self::assertSame('Title 2', $subject->dto->title);
    }
}
