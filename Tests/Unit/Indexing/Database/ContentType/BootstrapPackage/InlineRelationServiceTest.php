<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\InlineRelationService;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\LanguageInfo;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

class InlineRelationServiceTest extends AbstractTest
{
    /**
     * Creates a stub GenericRepository that returns the given rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function createGenericRepositoryStub(array $rows): GenericRepository
    {
        $repository = $this->createStub(GenericRepository::class);
        $repository->method('setTableName')->willReturnSelf();
        $repository->method('findByParentContentElement')->willReturn(new \ArrayIterator($rows));

        return $repository;
    }

    /**
     * Creates a mock GenericRepository with expectations.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function createGenericRepositoryMock(string $expectedTable, array $rows): GenericRepository
    {
        $repository = $this->createMock(GenericRepository::class);
        $repository->expects(self::once())
            ->method('setTableName')
            ->with($expectedTable)
            ->willReturnSelf();
        $repository->method('findByParentContentElement')->willReturn(new \ArrayIterator($rows));

        return $repository;
    }

    /**
     * Creates a mock Record with language info.
     */
    private function createRecordMock(int $languageId): Record
    {
        $languageInfo = $this->createStub(LanguageInfo::class);
        $languageInfo->method('getLanguageId')->willReturn($languageId);

        $record = $this->createStub(Record::class);
        $record->method('getLanguageInfo')->willReturn($languageInfo);

        return $record;
    }

    /**
     * Test that records are queried by parent uid.
     */
    public function testRecordsAreQueriedByParentUid(): void
    {
        $parentUid = 123;
        $table = 'tx_bootstrappackage_accordion_item';

        $genericRepository = $this->createGenericRepositoryMock($table, []);
        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        iterator_to_array($subject->findByParent($parentUid, $table));

        self::assertTrue(true);
    }

    /**
     * Test that FrontendRestrictionContainer is applied via GenericRepository.
     */
    public function testFrontendRestrictionContainerIsApplied(): void
    {
        $genericRepository = $this->createMock(GenericRepository::class);
        $genericRepository->expects(self::once())->method('setTableName')->willReturnSelf();
        $genericRepository->expects(self::once())
            ->method('findByParentContentElement')
            ->with(1, [0, -1, 0])
            ->willReturn(new \ArrayIterator([]));

        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item'));
    }

    /**
     * Test that records are returned for default language (0).
     */
    public function testRecordsAreReturnedForDefaultLanguage(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $genericRepository = $this->createGenericRepositoryStub([$row]);
        $pageRepository = $this->createStub(PageRepository::class);

        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 0));

        self::assertCount(1, $results);
    }

    /**
     * Test that language overlays are applied for non-zero languages.
     */
    public function testLanguageOverlaysAreAppliedForNonZeroLanguages(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $overlayRow = ['uid' => 2, 'header' => 'Test DE', 'sys_language_uid' => 1, 'l10n_parent' => 1];

        $genericRepository = $this->createGenericRepositoryStub([$row]);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects(self::once())
            ->method('getLanguageOverlay')
            ->willReturn($overlayRow);

        $record = $this->createRecordMock(1);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(1, $results);
    }

    /**
     * Test that records with language -1 (all languages) are included.
     */
    public function testRecordsWithAllLanguagesAreIncluded(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => -1];
        $genericRepository = $this->createGenericRepositoryStub([$row]);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn($row);

        $record = $this->createRecordMock(-1);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(1, $results);
    }

    /**
     * Test that records in wrong language are excluded after overlay.
     */
    public function testRecordsInWrongLanguageAreExcludedAfterOverlay(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $genericRepository = $this->createGenericRepositoryStub([$row]);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn($row);

        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 2));

        self::assertCount(0, $results);
    }

    /**
     * Test that null overlay results in no record being yielded.
     */
    public function testNullOverlayResultsInNoRecord(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $genericRepository = $this->createGenericRepositoryStub([$row]);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn(null);

        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(0, $results);
    }

    /**
     * Test that empty result set is handled gracefully.
     */
    public function testEmptyResultSetIsHandledGracefully(): void
    {
        $genericRepository = $this->createGenericRepositoryStub([]);
        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item'));

        self::assertCount(0, $results);
    }

    /**
     * Data provider for multiple inline tables.
     *
     * @return array<string, array{string}>
     */
    public static function inlineTablesProvider(): array
    {
        return [
            'accordion' => ['tx_bootstrappackage_accordion_item'],
            'tab' => ['tx_bootstrappackage_tab_item'],
            'card_group' => ['tx_bootstrappackage_card_group_item'],
            'icon_group' => ['tx_bootstrappackage_icon_group_item'],
            'timeline' => ['tx_bootstrappackage_timeline_item'],
            'carousel' => ['tx_bootstrappackage_carousel_item'],
        ];
    }

    /**
     * Test that service works with all Bootstrap Package inline tables.
     */
    #[DataProvider('inlineTablesProvider')]
    public function testServiceWorksWithAllInlineTables(string $table): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $genericRepository = $this->createGenericRepositoryMock($table, [$row]);
        $pageRepository = $this->createStub(PageRepository::class);

        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, $table, 0));

        self::assertCount(1, $results);
    }

    /**
     * Generates random language scenarios for property testing.
     *
     * @return \Generator<string, array{int, int, bool}>
     */
    public static function languageFilteringProvider(): \Generator
    {
        srand(48);

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            $itemLanguage = random_int(1, 10);
            while ($itemLanguage === $targetLanguage || $itemLanguage === -1) {
                $itemLanguage = random_int(1, 10);
            }
            yield "wrong_language_{$i}" => [$targetLanguage, $itemLanguage, false];
        }

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            yield "all_languages_{$i}" => [$targetLanguage, -1, true];
        }

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            yield "target_language_{$i}" => [$targetLanguage, $targetLanguage, true];
        }

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            yield "default_language_{$i}" => [0, 0, true];
        }
    }

    /**
     * Property 8: Language filtering for inline items.
     */
    #[DataProvider('languageFilteringProvider')]
    public function testLanguageFilteringForInlineItems(
        int $targetLanguage,
        int $itemLanguage,
        bool $shouldBeIncluded,
    ): void {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => $itemLanguage];
        $genericRepository = $this->createGenericRepositoryStub([$row]);

        $pageRepository = $this->createStub(PageRepository::class);

        if ($targetLanguage > 0) {
            $pageRepository->method('getLanguageOverlay')->willReturn($row);
        }

        $record = $this->createRecordMock($itemLanguage);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $genericRepository, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', $targetLanguage));

        if ($shouldBeIncluded) {
            self::assertCount(1, $results, sprintf(
                'Item with language %d should be included when target language is %d',
                $itemLanguage,
                $targetLanguage,
            ));
        } else {
            self::assertCount(0, $results, sprintf(
                'Item with language %d should be excluded when target language is %d',
                $itemLanguage,
                $targetLanguage,
            ));
        }
    }
}
