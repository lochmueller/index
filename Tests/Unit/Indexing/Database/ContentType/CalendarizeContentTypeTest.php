<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType;

use HDNET\Calendarize\Domain\Model\Index;
use HDNET\Calendarize\Domain\Repository\IndexRepository;
use Lochmueller\Index\Indexing\Database\ContentIndexing;
use Lochmueller\Index\Indexing\Database\ContentType\CalendarizeContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class CalendarizeContentTypeTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    /**
     * IndexRepository is an Extbase repository (SingletonInterface), so it is registered as singleton.
     */
    private function registerIndexRepository(?Index $indexObject): void
    {
        $repository = $this->createStub(IndexRepository::class);
        $repository->method('findByUid')->willReturn($indexObject);

        GeneralUtility::setSingletonInstance(IndexRepository::class, $repository);
    }

    private function createIndexObject(?AbstractEntity $originalObject): Index
    {
        $index = $this->createStub(Index::class);
        $index->method('getOriginalObject')->willReturn($originalObject);
        return $index;
    }

    private function createRecord(string $type): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        return $record;
    }

    /**
     * @param array<string, mixed> $flexFormData
     */
    private function createPluginRecord(array $flexFormData = []): Record
    {
        $flexForm = $this->createStub(FlexFormFieldValues::class);
        $flexForm->method('toArray')->willReturn($flexFormData);

        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn('calendarize_listdetail');
        $record->method('get')->willReturnCallback(fn(string $field) => $field === 'pi_flexform' ? $flexForm : '');
        return $record;
    }

    private function createIndexRecord(int $uid, string $type = '0'): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('getUid')->willReturn($uid);
        return $record;
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function createDto(array $arguments = [], int $languageUid = 0): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('Title', 'Content', 1, $languageUid, $arguments, $site);
    }

    private function createSubject(
        ?RecordSelection $recordSelection = null,
        ?ViewFactoryInterface $viewFactory = null,
    ): CalendarizeContentType {
        return new CalendarizeContentType(
            $recordSelection ?? $this->createStub(RecordSelection::class),
            $this->createStub(ContentIndexing::class),
            $viewFactory ?? $this->createStub(ViewFactoryInterface::class),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedTypesProvider(): array
    {
        return [
            'listdetail' => ['calendarize_listdetail'],
            'detail' => ['calendarize_detail'],
        ];
    }

    #[DataProvider('supportedTypesProvider')]
    public function testCanHandleReturnsTrueForCalendarizeTypes(string $type): void
    {
        self::assertTrue($this->createSubject()->canHandle($this->createRecord($type)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'text' => ['text'],
            'news' => ['news_pi1'],
            'calendarize_list' => ['calendarize_list'],
            'calendarize_calendar' => ['calendarize_calendar'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForOtherTypes(string $type): void
    {
        self::assertFalse($this->createSubject()->canHandle($this->createRecord($type)));
    }

    /**
     * @return array<string, array{array<int|string, mixed>}>
     */
    public static function missingIndexArgumentsProvider(): array
    {
        return [
            'no arguments' => [[]],
            'index zero' => [['tx_calendarize_calendar' => ['index' => 0]]],
            'index negative' => [['tx_calendarize_calendar' => ['index' => -5]]],
        ];
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    #[DataProvider('missingIndexArgumentsProvider')]
    public function testAddContentReturnsEarlyWithoutValidIndex(array $arguments): void
    {
        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::never())->method('create');

        $dto = $this->createDto($arguments);

        $this->createSubject(viewFactory: $viewFactory)->addContent($this->createRecord('calendarize_detail'), $dto);

        self::assertSame('Content', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenIndexObjectNotFound(): void
    {
        $repository = $this->createMock(IndexRepository::class);
        $repository->expects(self::once())->method('findByUid')->with(42)->willReturn(null);
        GeneralUtility::setSingletonInstance(IndexRepository::class, $repository);

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::never())->method('create');

        $dto = $this->createDto(['tx_calendarize_calendar' => ['index' => 42]]);

        $this->createSubject(viewFactory: $viewFactory)->addContent($this->createRecord('calendarize_detail'), $dto);

        self::assertSame('Content', $dto->content);
    }

    public function testAddContentReturnsEarlyWhenOriginalObjectMissing(): void
    {
        $this->registerIndexRepository($this->createIndexObject(null));

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::never())->method('create');

        $dto = $this->createDto(['tx_calendarize_calendar' => ['index' => 42]]);

        $this->createSubject(viewFactory: $viewFactory)->addContent($this->createRecord('calendarize_detail'), $dto);

        self::assertSame('Content', $dto->content);
    }

    public function testAddContentAppendsRenderedDetailView(): void
    {
        $this->registerIndexRepository($this->createIndexObject($this->createStub(AbstractEntity::class)));

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())
            ->method('assignMultiple')
            ->with(['index' => 42])
            ->willReturnSelf();
        $view->expects(self::once())
            ->method('render')
            ->with('Calendar/Detail')
            ->willReturn('<p>Event</p>');

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                self::assertSame(['EXT:calendarize/Resources/Private/Templates'], $data->templateRootPaths);
                self::assertSame(['EXT:calendarize/Resources/Private/Partials'], $data->partialRootPaths);
                self::assertSame(['EXT:calendarize/Resources/Private/Layouts/'], $data->layoutRootPaths);
                self::assertSame('html', $data->format);
                return true;
            }))
            ->willReturn($view);

        $dto = $this->createDto(['tx_calendarize_calendar' => ['index' => 42]]);

        $this->createSubject(viewFactory: $viewFactory)->addContent($this->createRecord('calendarize_detail'), $dto);

        self::assertSame('Content<p>Event</p>', $dto->content);
    }

    public function testAddVariantsReplacesQueueWithIndexRecords(): void
    {
        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([
            $this->createIndexRecord(11),
            $this->createIndexRecord(12),
        ]);

        $dto = $this->createDto();
        $queue = new \SplQueue();
        $queue[] = $dto;

        $this->createSubject(recordSelection: $recordSelection)->addVariants($this->createPluginRecord(), $queue);

        self::assertCount(2, $queue);

        /** @var DatabaseIndexingDto $first */
        $first = $queue->dequeue();
        self::assertNotSame($dto, $first);
        self::assertSame('Title', $first->title);
        self::assertSame('Content', $first->content);
        self::assertSame(1, $first->pageUid);
        self::assertSame($dto->site, $first->site);
        self::assertSame([
            '_language' => 0,
            'tx_calendarize_calendar' => [
                'action' => 'detail',
                'controller' => 'Calendar',
                'index' => 11,
            ],
        ], $first->arguments);

        /** @var DatabaseIndexingDto $second */
        $second = $queue->dequeue();
        self::assertSame(12, $second->arguments['tx_calendarize_calendar']['index']);
    }

    public function testAddVariantsSkipsNonStandardIndexRecords(): void
    {
        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([
            $this->createIndexRecord(11, '1'),
            $this->createIndexRecord(12),
        ]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto();

        $this->createSubject(recordSelection: $recordSelection)->addVariants($this->createPluginRecord(), $queue);

        self::assertCount(1, $queue);
        self::assertSame(12, $queue->dequeue()->arguments['tx_calendarize_calendar']['index']);
    }

    public function testAddVariantsEmptiesQueueWhenNoIndexRecordsFound(): void
    {
        $recordSelection = $this->createStub(RecordSelection::class);
        $recordSelection->method('findRecordsOnPage')->willReturn([]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto();

        $this->createSubject(recordSelection: $recordSelection)->addVariants($this->createPluginRecord(), $queue);

        self::assertCount(0, $queue);
    }

    public function testAddVariantsPreservesLanguageUid(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_calendarize_domain_model_index', [-99], 2)
            ->willReturn([$this->createIndexRecord(11)]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto(languageUid: 2);

        $this->createSubject(recordSelection: $recordSelection)->addVariants($this->createPluginRecord(), $queue);

        /** @var DatabaseIndexingDto $newDto */
        $newDto = $queue->dequeue();
        self::assertSame(2, $newDto->languageUid);
        self::assertSame(2, $newDto->arguments['_language']);
    }

    public function testAddVariantsUsesStoragePidsFromFlexForm(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_calendarize_domain_model_index', [5, 7], 0)
            ->willReturn([]);

        $pluginRecord = $this->createPluginRecord([
            'general' => [
                'persistence' => [
                    'storagePid' => '5,7',
                ],
            ],
        ]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto();

        $this->createSubject(recordSelection: $recordSelection)->addVariants($pluginRecord, $queue);
    }

    public function testAddVariantsTrimsAndIgnoresEmptyStoragePids(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_calendarize_domain_model_index', [3, 9], 1)
            ->willReturn([]);

        $pluginRecord = $this->createPluginRecord([
            'general' => [
                'persistence' => [
                    'storagePid' => ' 3, ,9,',
                ],
            ],
        ]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto(languageUid: 1);

        $this->createSubject(recordSelection: $recordSelection)->addVariants($pluginRecord, $queue);

        self::assertCount(0, $queue);
    }

    public function testAddVariantsFallsBackToInvalidStorageForEmptyStoragePid(): void
    {
        $recordSelection = $this->createMock(RecordSelection::class);
        $recordSelection->expects(self::once())
            ->method('findRecordsOnPage')
            ->with('tx_calendarize_domain_model_index', [-99], 0)
            ->willReturn([]);

        $pluginRecord = $this->createPluginRecord([
            'general' => [
                'persistence' => [
                    'storagePid' => '',
                ],
            ],
        ]);

        $queue = new \SplQueue();
        $queue[] = $this->createDto();

        $this->createSubject(recordSelection: $recordSelection)->addVariants($pluginRecord, $queue);
    }
}
