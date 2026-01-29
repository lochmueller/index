<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\ContentType\NewsContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;

class NewsContentTypeTest extends AbstractTest
{
    private function createRecord(string $type, array $data = []): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('get')->willReturnCallback(fn(string $field) => $data[$field] ?? '');
        $record->method('getUid')->willReturn($data['uid'] ?? 1);
        return $record;
    }

    private function createDto(array $arguments = []): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        $site->method('getAttribute')->willReturn('Test Site');
        return new DatabaseIndexingDto('', '', 1, 0, $arguments, $site);
    }

    private function createSubject(
        ?HeaderContentType $headerContentType = null,
        ?RecordSelection $recordSelection = null,
        ?ContentIndexing $contentIndexing = null,
        ?GenericRepository $genericRepository = null,
    ): NewsContentType {
        return new NewsContentType(
            $headerContentType ?? $this->createStub(HeaderContentType::class),
            $recordSelection ?? $this->createStub(RecordSelection::class),
            $contentIndexing ?? $this->createStub(ContentIndexing::class),
            $genericRepository ?? $this->createStub(GenericRepository::class),
        );
    }

    public function testCanHandleReturnsTrueForNewsPi1Type(): void
    {
        $record = $this->createRecord('news_pi1');
        $subject = $this->createSubject();

        self::assertTrue($subject->canHandle($record));
    }

    public function testCanHandleReturnsTrueForNewsNewsdetailType(): void
    {
        $record = $this->createRecord('news_newsdetail');
        $subject = $this->createSubject();

        self::assertTrue($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'text' => ['text'],
            'header' => ['header'],
            'image' => ['image'],
            'textmedia' => ['textmedia'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForOtherTypes(string $type): void
    {
        $record = $this->createRecord($type);
        $subject = $this->createSubject();

        self::assertFalse($subject->canHandle($record));
    }

    public function testAddContentReturnsEarlyWhenNewsIdIsZero(): void
    {
        $record = $this->createRecord('news_pi1');
        $dto = $this->createDto(['tx_news_pi1' => ['news' => 0]]);

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');

        $subject = $this->createSubject(headerContentType: $headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenNewsIdIsMissing(): void
    {
        $record = $this->createRecord('news_pi1');
        $dto = $this->createDto([]);

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::never())->method('addContent');

        $subject = $this->createSubject(headerContentType: $headerContentType);
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenNewsRecordNotFound(): void
    {
        $record = $this->createRecord('news_pi1');
        $dto = $this->createDto(['tx_news_pi1' => ['news' => 123]]);

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent');

        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::once())->method('setTableName')->with('tx_news_domain_model_news')->willReturnSelf();
        $genericRepository->expects(self::once())->method('findByUid')->with(123)->willReturn(null);

        $subject = $this->createSubject(
            headerContentType: $headerContentType,
            genericRepository: $genericRepository,
        );
        $subject->addContent($record, $dto);

        self::assertSame('', $dto->content);
    }

    public function testAddContentAddsNewsDataToDto(): void
    {
        $record = $this->createRecord('news_pi1');
        $dto = $this->createDto(['tx_news_pi1' => ['news' => 123]]);

        $newsRecord = $this->createStub(Record::class);
        $newsRecord->method('get')->willReturnCallback(fn(string $field) => match ($field) {
            'title' => 'News Title',
            'teaser' => 'News Teaser',
            'bodytext' => 'News Body',
            'content_elements' => [],
            default => '',
        });

        $headerContentType = $this->createStub(HeaderContentType::class);

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->willReturn(['uid' => 123]);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('mapRecord')->willReturn($newsRecord);

        $subject = $this->createSubject(
            headerContentType: $headerContentType,
            recordSelection: $recordSelection,
            genericRepository: $genericRepository,
        );
        $subject->addContent($record, $dto);

        self::assertSame('News Title | Test Site', $dto->title);
        self::assertStringContainsString('News Title', $dto->content);
        self::assertStringContainsString('News Teaser', $dto->content);
        self::assertStringContainsString('News Body', $dto->content);
    }

    public function testAddContentProcessesContentElements(): void
    {
        $record = $this->createRecord('news_pi1');
        $dto = $this->createDto(['tx_news_pi1' => ['news' => 123]]);

        $contentElement = $this->createStub(Record::class);

        $newsRecord = $this->createStub(Record::class);
        $newsRecord->method('get')->willReturnCallback(fn(string $field) => match ($field) {
            'title' => 'News Title',
            'teaser' => '',
            'bodytext' => '',
            'content_elements' => [$contentElement],
            default => '',
        });

        $genericRepository = $this->createStub(GenericRepository::class);
        $genericRepository->method('setTableName')->willReturnSelf();
        $genericRepository->method('findByUid')->willReturn(['uid' => 123]);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('mapRecord')->willReturn($newsRecord);

        $contentIndexing = $this->createMock(ContentIndexing::class);
        $contentIndexing->expects(self::once())->method('addContent')->with($contentElement, $dto);

        $subject = $this->createSubject(
            recordSelection: $recordSelection,
            contentIndexing: $contentIndexing,
            genericRepository: $genericRepository,
        );
        $subject->addContent($record, $dto);
    }

    public function testAddVariantsCreatesNewQueueWithNewsRecords(): void
    {
        $pageRecord = $this->createStub(Record::class);
        $pageRecord->method('get')->willReturnCallback(function (string $field) {
            if ($field === 'pi_flexform') {
                $flexForm = $this->createStub(FlexFormFieldValues::class);
                $flexForm->method('toArray')->willReturn([
                    'sDEF' => [
                        'settings' => [
                            'startingpoint' => [],
                        ],
                    ],
                ]);
                return $flexForm;
            }
            return '';
        });

        $newsRecord = $this->createStub(Record::class);
        $newsRecord->method('getRecordType')->willReturn('0');
        $newsRecord->method('getUid')->willReturn(456);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([$newsRecord]);

        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 1, 0, [], $site);

        $queue = new \SplQueue();
        $queue[] = $dto;

        $subject = $this->createSubject(recordSelection: $recordSelection);
        $subject->addVariants($pageRecord, $queue);

        self::assertCount(1, $queue);

        /** @var DatabaseIndexingDto $newDto */
        $newDto = $queue->dequeue();
        self::assertSame(456, $newDto->arguments['tx_news_pi1']['news']);
        self::assertSame('detail', $newDto->arguments['tx_news_pi1']['action']);
        self::assertSame('News', $newDto->arguments['tx_news_pi1']['controller']);
    }

    public function testAddVariantsSkipsNonStandardNewsRecords(): void
    {
        $pageRecord = $this->createStub(Record::class);
        $pageRecord->method('get')->willReturnCallback(function (string $field) {
            if ($field === 'pi_flexform') {
                $flexForm = $this->createStub(FlexFormFieldValues::class);
                $flexForm->method('toArray')->willReturn([
                    'sDEF' => [
                        'settings' => [
                            'startingpoint' => [],
                        ],
                    ],
                ]);
                return $flexForm;
            }
            return '';
        });

        $newsRecord = $this->createStub(Record::class);
        $newsRecord->method('getRecordType')->willReturn('1');
        $newsRecord->method('getUid')->willReturn(456);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([$newsRecord]);

        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 1, 0, [], $site);

        $queue = new \SplQueue();
        $queue[] = $dto;

        $subject = $this->createSubject(recordSelection: $recordSelection);
        $subject->addVariants($pageRecord, $queue);

        self::assertCount(0, $queue);
    }

    public function testAddVariantsPreservesLanguageUid(): void
    {
        $pageRecord = $this->createStub(Record::class);
        $pageRecord->method('get')->willReturnCallback(function (string $field) {
            if ($field === 'pi_flexform') {
                $flexForm = $this->createStub(FlexFormFieldValues::class);
                $flexForm->method('toArray')->willReturn([
                    'sDEF' => [
                        'settings' => [
                            'startingpoint' => [],
                        ],
                    ],
                ]);
                return $flexForm;
            }
            return '';
        });

        $newsRecord = $this->createStub(Record::class);
        $newsRecord->method('getRecordType')->willReturn('0');
        $newsRecord->method('getUid')->willReturn(456);

        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([$newsRecord]);

        $site = $this->createStub(Site::class);
        $dto = new DatabaseIndexingDto('Title', 'Content', 1, 2, [], $site);

        $queue = new \SplQueue();
        $queue[] = $dto;

        $subject = $this->createSubject(recordSelection: $recordSelection);
        $subject->addVariants($pageRecord, $queue);

        /** @var DatabaseIndexingDto $newDto */
        $newDto = $queue->dequeue();
        self::assertSame(2, $newDto->arguments['_language']);
        self::assertSame(2, $newDto->languageUid);
    }
}
