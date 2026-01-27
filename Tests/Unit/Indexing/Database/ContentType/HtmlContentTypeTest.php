<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\HtmlContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class HtmlContentTypeTest extends AbstractTest
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

    public function testCanHandleReturnsTrueForHtmlType(): void
    {
        $record = $this->createRecord('html');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new HtmlContentType($headerContentType);

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsFalseForOtherTypes(): void
    {
        $record = $this->createRecord('text');
        $headerContentType = $this->createStub(HeaderContentType::class);

        $subject = new HtmlContentType($headerContentType);

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentAppendsBodytextWithoutHeader(): void
    {
        $record = $this->createRecord('html', ['bodytext' => '<div>Custom HTML</div>']);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');

        $subject = new HtmlContentType($headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('<div>Custom HTML</div>', $dto->content);
    }
}
