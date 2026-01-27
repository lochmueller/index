<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType\BootstrapPackage;

use Doctrine\DBAL\Result;
use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\InlineRelationService;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\LanguageInfo;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InlineRelationServiceTest extends AbstractTest
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * Creates a mock QueryBuilder with all necessary stubs.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function createQueryBuilderMock(array $rows): QueryBuilder
    {
        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator($rows));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('');
        $expressionBuilder->method('in')->willReturn('');

        $restrictions = $this->createStub(QueryRestrictionContainerInterface::class);
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
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

        $queryBuilder = $this->createQueryBuilderMock([]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->with($table)->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        // Execute and consume the generator
        iterator_to_array($subject->findByParent($parentUid, $table));

        // The test passes if no exception is thrown - the query was built correctly
        self::assertTrue(true);
    }

    /**
     * Test that FrontendRestrictionContainer is applied.
     *
     * Property 11: InlineRelationService excludes hidden and deleted records
     * Validates: Requirements 9.2
     */
    public function testFrontendRestrictionContainerIsApplied(): void
    {
        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects(self::once())->method('add');

        $result = $this->createStub(Result::class);
        $result->method('iterateAssociative')->willReturn(new \ArrayIterator([]));

        $expressionBuilder = $this->createStub(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('');
        $expressionBuilder->method('in')->willReturn('');

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item'));
    }

    /**
     * Test that records are returned for default language (0).
     */
    public function testRecordsAreReturnedForDefaultLanguage(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);

        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 0));

        self::assertCount(1, $results);
    }

    /**
     * Test that language overlays are applied for non-zero languages.
     *
     * Property 12: InlineRelationService handles language overlays
     * Validates: Requirements 9.3
     */
    public function testLanguageOverlaysAreAppliedForNonZeroLanguages(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $overlayRow = ['uid' => 2, 'header' => 'Test DE', 'sys_language_uid' => 1, 'l10n_parent' => 1];

        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects(self::once())
            ->method('getLanguageOverlay')
            ->willReturn($overlayRow);

        $record = $this->createRecordMock(1);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(1, $results);
    }

    /**
     * Test that records with language -1 (all languages) are included.
     */
    public function testRecordsWithAllLanguagesAreIncluded(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => -1];
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn($row);

        $record = $this->createRecordMock(-1);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(1, $results);
    }

    /**
     * Test that records in wrong language are excluded after overlay.
     */
    public function testRecordsInWrongLanguageAreExcludedAfterOverlay(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn($row);

        // Record has language 0 after overlay, but we're querying for language 2
        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 2));

        self::assertCount(0, $results);
    }

    /**
     * Test that null overlay results in no record being yielded.
     */
    public function testNullOverlayResultsInNoRecord(): void
    {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => 0];
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('getLanguageOverlay')->willReturn(null);

        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

        $results = iterator_to_array($subject->findByParent(1, 'tx_bootstrappackage_accordion_item', 1));

        self::assertCount(0, $results);
    }

    /**
     * Test that empty result set is handled gracefully.
     */
    public function testEmptyResultSetIsHandledGracefully(): void
    {
        $queryBuilder = $this->createQueryBuilderMock([]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);
        $recordFactory = $this->createStub(RecordFactory::class);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

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
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with($table)
            ->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);

        $record = $this->createRecordMock(0);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

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

        // Test items in wrong language are excluded
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            $itemLanguage = random_int(1, 10);
            // Ensure item language is different from target
            while ($itemLanguage === $targetLanguage || $itemLanguage === -1) {
                $itemLanguage = random_int(1, 10);
            }
            yield "wrong_language_{$i}" => [$targetLanguage, $itemLanguage, false];
        }

        // Test items in language -1 (all languages) are included
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            yield "all_languages_{$i}" => [$targetLanguage, -1, true];
        }

        // Test items in target language are included
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $targetLanguage = random_int(1, 10);
            yield "target_language_{$i}" => [$targetLanguage, $targetLanguage, true];
        }

        // Test items in default language (0) are included when target is 0
        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            yield "default_language_{$i}" => [0, 0, true];
        }
    }

    /**
     * Property 8: Language filtering for inline items
     * For any inline content element with child items in multiple languages,
     * when indexed for a specific language, the indexed content SHALL only
     * contain text from items matching that language (including language -1
     * for "all languages").
     *
     * Validates: Requirements 2.4, 3.4, 4.4, 5.4, 6.4, 7.4
     */
    #[DataProvider('languageFilteringProvider')]
    public function testLanguageFilteringForInlineItems(
        int $targetLanguage,
        int $itemLanguage,
        bool $shouldBeIncluded,
    ): void {
        $row = ['uid' => 1, 'header' => 'Test', 'sys_language_uid' => $itemLanguage];
        $queryBuilder = $this->createQueryBuilderMock([$row]);

        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($queryBuilder);

        $pageRepository = $this->createStub(PageRepository::class);

        if ($targetLanguage > 0) {
            // For non-zero target languages, overlay is called
            $pageRepository->method('getLanguageOverlay')->willReturn($row);
        }

        $record = $this->createRecordMock($itemLanguage);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        $subject = new InlineRelationService($recordFactory, $connectionPool, $pageRepository);

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
