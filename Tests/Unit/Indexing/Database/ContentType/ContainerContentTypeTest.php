<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Database\Query\Restriction\ContainerElementsRestrictionContainer;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\ContainerContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
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

    public function testCanHandleReturnsFalseForNullRecordType(): void
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn(null);

        $subject = new ContainerContentType($this->createStub(RecordSelection::class), $this->createStub(ContentIndexing::class));

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentWrapsChildElementsInSections(): void
    {
        $container = $this->createContainerRecord(10, 5, 0);
        $childA = $this->createChildRecord(21);
        $childB = $this->createChildRecord(22);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([$childA, $childB]);

        $contentIndexing = $this->createMock(ContentIndexing::class);
        $contentIndexing->expects(self::exactly(2))
            ->method('addContent')
            ->willReturnCallback(static function (Record $record, DatabaseIndexingDto $dto): ?string {
                $dto->content .= 'content-' . $record->get('uid');
                return null;
            });

        $dto = $this->createDto();
        $subject = new ContainerContentType($recordSelection, $contentIndexing);
        $subject->addContent($container, $dto);

        self::assertSame(
            '<section id="container_10">'
            . '<div id="section_21">content-21</div>'
            . '<div id="section_22">content-22</div>'
            . '</section>',
            $dto->content,
        );
    }

    public function testAddContentAddsEmptySectionWithoutChildElements(): void
    {
        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $contentIndexing = $this->createMock(ContentIndexing::class);
        $contentIndexing->expects(self::never())->method('addContent');

        $dto = $this->createDto();
        $dto->content = 'before';
        $subject = new ContainerContentType($recordSelection, $contentIndexing);
        $subject->addContent($this->createContainerRecord(10, 5, 0), $dto);

        self::assertSame('before<section id="container_10"></section>', $dto->content);
    }

    public function testAddContentSelectsChildElementsOfContainer(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with(
                'tt_content',
                [5],
                2,
                self::callback(static function (array $restrictions): bool {
                    if (count($restrictions) !== 2 || $restrictions[0] !== FrontendRestrictionContainer::class) {
                        return false;
                    }
                    if (!$restrictions[1] instanceof ContainerElementsRestrictionContainer) {
                        return false;
                    }
                    return (new \ReflectionProperty($restrictions[1], 'containerParent'))->getValue($restrictions[1]) === 10;
                }),
            )
            ->willReturn([]);

        $subject = new ContainerContentType($recordSelection, $this->createStub(ContentIndexing::class));
        $subject->addContent($this->createContainerRecord(10, 5, 2), $this->createDto());
    }

    public function testAddContentFallsBackToDefaultLanguage(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tt_content', [5], 0, self::anything())
            ->willReturn([]);

        $subject = new ContainerContentType($recordSelection, $this->createStub(ContentIndexing::class));
        $subject->addContent($this->createContainerRecord(10, 5, null), $this->createDto());
    }

    private function createContainerRecord(int $uid, int $pid, ?int $languageId): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('container_2cols');
        $record->method('getLanguageId')->willReturn($languageId);
        $record->method('get')->willReturnCallback(static fn(string $field) => match ($field) {
            'uid' => $uid,
            'pid' => $pid,
            default => null,
        });
        return $record;
    }

    private function createChildRecord(int $uid): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturnCallback(static fn(string $field) => $field === 'uid' ? $uid : null);
        return $record;
    }
}
