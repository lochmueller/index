<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Database\ContentType\BootstrapPackage;

use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\AbstractBootstrapPackageContentType;
use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\BootstrapPackageInlineContentType;
use Lochmueller\Index\Indexing\Database\ContentType\BootstrapPackage\InlineRelationService;
use Lochmueller\Index\Indexing\Database\ContentType\HeaderContentType;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BootstrapPackageInlineContentTypeTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AbstractBootstrapPackageContentType::resetBootstrapPackageActiveCache();
    }

    protected function tearDown(): void
    {
        AbstractBootstrapPackageContentType::resetBootstrapPackageActiveCache();
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    private function setBootstrapPackageActive(bool $active): void
    {
        $packageManager = $this->createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn($active);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);
    }

    private function createRecordWithType(string $type): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        return $record;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createRecordWithFields(string $type, array $fields, int $languageId = 0): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('getRecordType')->willReturn($type);
        $record->method('getLanguageId')->willReturn($languageId);
        $record->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? null);
        return $record;
    }

    private function createDto(): DatabaseIndexingDto
    {
        $site = $this->createStub(Site::class);
        return new DatabaseIndexingDto('', '', 1, 0, [], $site);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function supportedTypesProvider(): array
    {
        return [
            'accordion' => ['accordion', 'tx_bootstrappackage_accordion_item'],
            'tab' => ['tab', 'tx_bootstrappackage_tab_item'],
            'card_group' => ['card_group', 'tx_bootstrappackage_card_group_item'],
            'icon_group' => ['icon_group', 'tx_bootstrappackage_icon_group_item'],
            'timeline' => ['timeline', 'tx_bootstrappackage_timeline_item'],
            'carousel' => ['carousel', 'tx_bootstrappackage_carousel_item'],
            'carousel_small' => ['carousel_small', 'tx_bootstrappackage_carousel_item'],
            'carousel_fullscreen' => ['carousel_fullscreen', 'tx_bootstrappackage_carousel_item'],
        ];
    }

    /**
     * Property 10: canHandle returns true for supported types when installed
     */
    #[DataProvider('supportedTypesProvider')]
    public function testCanHandleReturnsTrueForSupportedTypesWhenPackageActive(string $type, string $table): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);

        self::assertTrue($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypesProvider(): array
    {
        return [
            'textcolumn' => ['textcolumn'],
            'textteaser' => ['textteaser'],
            'quote' => ['quote'],
            'text' => ['text'],
            'header' => ['header'],
            'image' => ['image'],
        ];
    }

    #[DataProvider('unsupportedTypesProvider')]
    public function testCanHandleReturnsFalseForUnsupportedTypes(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);

        self::assertFalse($subject->canHandle($record));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allInlineTypesProvider(): array
    {
        return [
            'accordion' => ['accordion'],
            'tab' => ['tab'],
            'card_group' => ['card_group'],
            'icon_group' => ['icon_group'],
            'timeline' => ['timeline'],
            'carousel' => ['carousel'],
            'carousel_small' => ['carousel_small'],
            'carousel_fullscreen' => ['carousel_fullscreen'],
        ];
    }

    #[DataProvider('allInlineTypesProvider')]
    public function testCanHandleReturnsFalseWhenPackageNotActive(string $type): void
    {
        $this->setBootstrapPackageActive(false);

        $record = $this->createRecordWithType($type);
        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);

        self::assertFalse($subject->canHandle($record));
    }

    /**
     * Property 4: Inline types extract parent header
     * For any Bootstrap Package inline content element with a non-empty header field,
     * the indexed content SHALL contain the parent header text.
     */
    #[DataProvider('allInlineTypesProvider')]
    public function testParentHeaderIsExtracted(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['uid' => 123]);
        $dto = $this->createDto();

        $headerContentType = $this->createMock(HeaderContentType::class);
        $headerContentType->expects(self::once())->method('addContent')->with($record, $dto);

        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);
        $subject->addContent($record, $dto);
    }

    /**
     * Test inline items are queried with correct table name
     */
    #[DataProvider('supportedTypesProvider')]
    public function testInlineItemsAreQueriedWithCorrectTableName(string $type, string $expectedTable): void
    {
        $this->setBootstrapPackageActive(true);

        $parentUid = 456;
        $languageUid = 0;
        $record = $this->createRecordWithFields($type, ['uid' => $parentUid], $languageUid);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);

        $inlineRelationService = $this->createMock(InlineRelationService::class);
        $inlineRelationService->expects(self::once())
            ->method('findByParent')
            ->with($parentUid, $expectedTable, $languageUid)
            ->willReturn([]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);
        $subject->addContent($record, $dto);
    }

    /**
     * Test that empty inline items are handled gracefully
     */
    #[DataProvider('allInlineTypesProvider')]
    public function testEmptyInlineItemsAreHandledGracefully(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['uid' => 123]);
        $dto = $this->createDto();

        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);

        // Should not throw any exception
        $subject->addContent($record, $dto);

        // Content should be empty (only header would be added by HeaderContentType)
        self::assertSame('', $dto->content);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createItemRecord(array $fields): Record
    {
        $record = $this->createStub(Record::class);
        $record->method('get')->willReturnCallback(fn(string $field) => $fields[$field] ?? null);
        return $record;
    }

    /**
     * Generates random data for accordion/tab/icon_group items (header + bodytext).
     *
     * @return \Generator<string, array{string, string, string}>
     */
    public static function headerBodytextItemsProvider(): \Generator
    {
        srand(45);
        $types = ['accordion', 'tab', 'icon_group'];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $type = $types[array_rand($types)];
            $header = 'Item header ' . random_int(1, 10000);
            $bodytext = 'Item body content ' . random_int(1, 10000);
            yield "iteration_{$i}_{$type}" => [$type, $header, $bodytext];
        }
    }

    /**
     * Property 5: Inline items with header and bodytext are fully extracted
     * For any inline content element (accordion, tab, icon_group) with child items
     * containing non-empty header and bodytext fields, the indexed content SHALL
     * contain all item headers and all item bodytexts.
     */
    #[DataProvider('headerBodytextItemsProvider')]
    public function testInlineItemsWithHeaderAndBodytextAreFullyExtracted(
        string $type,
        string $header,
        string $bodytext,
    ): void {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['uid' => 123]);
        $dto = $this->createDto();

        $itemRecord = $this->createItemRecord([
            'header' => $header,
            'bodytext' => $bodytext,
        ]);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([$itemRecord]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);
        $subject->addContent($record, $dto);

        self::assertStringContainsString($header, $dto->content, 'Item header not found in content');
        self::assertStringContainsString($bodytext, $dto->content, 'Item bodytext not found in content');
    }

    /**
     * Generates random data for card_group/carousel items (header + subheader + bodytext).
     *
     * @return \Generator<string, array{string, string, string, string}>
     */
    public static function cardCarouselItemsProvider(): \Generator
    {
        srand(46);
        $types = ['card_group', 'carousel', 'carousel_small', 'carousel_fullscreen'];

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $type = $types[array_rand($types)];
            $header = 'Card header ' . random_int(1, 10000);
            $subheader = 'Card subheader ' . random_int(1, 10000);
            $bodytext = 'Card body content ' . random_int(1, 10000);
            yield "iteration_{$i}_{$type}" => [$type, $header, $subheader, $bodytext];
        }
    }

    /**
     * Property 6: Card and carousel items with subheader are fully extracted
     * For any card_group or carousel content element with child items containing
     * non-empty header, subheader, and bodytext fields, the indexed content SHALL
     * contain all item headers, subheaders, and bodytexts.
     */
    #[DataProvider('cardCarouselItemsProvider')]
    public function testCardAndCarouselItemsWithSubheaderAreFullyExtracted(
        string $type,
        string $header,
        string $subheader,
        string $bodytext,
    ): void {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['uid' => 123]);
        $dto = $this->createDto();

        $itemRecord = $this->createItemRecord([
            'header' => $header,
            'subheader' => $subheader,
            'bodytext' => $bodytext,
        ]);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([$itemRecord]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);
        $subject->addContent($record, $dto);

        self::assertStringContainsString($header, $dto->content, 'Item header not found in content');
        self::assertStringContainsString($subheader, $dto->content, 'Item subheader not found in content');
        self::assertStringContainsString($bodytext, $dto->content, 'Item bodytext not found in content');
    }

    /**
     * Generates random data for timeline items (header + bodytext + date).
     *
     * @return \Generator<string, array{string, string, string}>
     */
    public static function timelineItemsProvider(): \Generator
    {
        srand(47);

        for ($i = 0; $i < self::TEST_ITERATIONS; $i++) {
            $header = 'Timeline header ' . random_int(1, 10000);
            $bodytext = 'Timeline body content ' . random_int(1, 10000);
            $date = '2024-' . str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
            yield "iteration_{$i}" => [$header, $bodytext, $date];
        }
    }

    /**
     * Property 7: Timeline items with date are fully extracted
     * For any timeline content element with child items containing non-empty header,
     * bodytext, and date fields, the indexed content SHALL contain all item headers,
     * bodytexts, and dates.
     */
    #[DataProvider('timelineItemsProvider')]
    public function testTimelineItemsWithDateAreFullyExtracted(
        string $header,
        string $bodytext,
        string $date,
    ): void {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields('timeline', ['uid' => 123]);
        $dto = $this->createDto();

        $itemRecord = $this->createItemRecord([
            'header' => $header,
            'bodytext' => $bodytext,
            'date' => $date,
        ]);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([$itemRecord]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);
        $subject->addContent($record, $dto);

        self::assertStringContainsString($header, $dto->content, 'Item header not found in content');
        self::assertStringContainsString($bodytext, $dto->content, 'Item bodytext not found in content');
        self::assertStringContainsString($date, $dto->content, 'Item date not found in content');
    }

    /**
     * Test that inline items with empty fields are handled gracefully
     */
    #[DataProvider('allInlineTypesProvider')]
    public function testInlineItemsWithEmptyFieldsAreHandledGracefully(string $type): void
    {
        $this->setBootstrapPackageActive(true);

        $record = $this->createRecordWithFields($type, ['uid' => 123]);
        $dto = $this->createDto();

        $itemRecord = $this->createItemRecord([
            'header' => '',
            'subheader' => '',
            'bodytext' => '',
            'date' => '',
        ]);

        $headerContentType = $this->createStub(HeaderContentType::class);
        $inlineRelationService = $this->createStub(InlineRelationService::class);
        $inlineRelationService->method('findByParent')->willReturn([$itemRecord]);

        $subject = new BootstrapPackageInlineContentType($headerContentType, $inlineRelationService);

        // Should not throw any exception
        $subject->addContent($record, $dto);

        // Content should be empty (only header would be added by HeaderContentType)
        self::assertSame('', $dto->content);
    }
}
