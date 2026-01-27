<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\TextContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class TextContentTypeTest extends AbstractTest
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

    public function testCanHandleReturnsTrueForTextType(): void
    {
        $record = $this->createRecord('text');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new TextContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createRecord('header');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new TextContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentCallsHeaderContentType(): void
    {
        $record = $this->createRecord('text', ['bodytext' => 'Body content']);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $subject = new TextContentType($headerContentType);
        $subject->addContent($record, $dto);
    }

    public function testAddContentAppendsBodytext(): void
    {
        $record = $this->createRecord('text', ['bodytext' => '<p>Body content</p>']);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new TextContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('<p>Body content</p>', $dto->content);
    }
}
