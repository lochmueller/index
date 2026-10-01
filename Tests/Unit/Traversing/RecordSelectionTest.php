<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\LanguageInfo;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use Lochmueller\Index\Database\Query\Restriction\NonContainerElementsRestrictionContainer;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\Field\LanguageFieldType;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

class RecordSelectionTest extends AbstractTest
{
    public function testMapRecordReturnsRecordFromFactory(): void
    {
        $table = 'tt_content';
        $row = ['uid' => 1, 'pid' => 10, 'header' => 'Test'];

        $recordStub = $this->createStub(Record::class);

        $recordFactoryStub = $this->createStub(RecordFactory::class);
        $recordFactoryStub->method('createResolvedRecordFromDatabaseRow')
            ->willReturn($recordStub);

        $subject = new RecordSelection(
            $recordFactoryStub,
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
            $this->createStub(GenericRepository::class),
        );

        $result = $subject->mapRecord($table, $row);

        self::assertSame($recordStub, $result);
    }

    #[DataProvider('excludedDoktypeDataProvider')]
    public function testIsExcludedDoktypeReturnsTrueForExcludedTypes(int $doktype): void
    {
        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
            $this->createStub(GenericRepository::class),
        );

        $reflection = new \ReflectionMethod($subject, 'isExcludedDoktype');

        $result = $reflection->invoke($subject, ['doktype' => $doktype]);

        self::assertTrue($result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function excludedDoktypeDataProvider(): iterable
    {
        yield 'sysfolder' => [PageRepository::DOKTYPE_SYSFOLDER];
        yield 'spacer' => [PageRepository::DOKTYPE_SPACER];
        yield 'link' => [PageRepository::DOKTYPE_LINK];
        yield 'be_user_section' => [PageRepository::DOKTYPE_BE_USER_SECTION];
        yield 'shortcut' => [PageRepository::DOKTYPE_SHORTCUT];
        yield 'mountpoint' => [PageRepository::DOKTYPE_MOUNTPOINT];
    }

    #[DataProvider('allowedDoktypeDataProvider')]
    public function testIsExcludedDoktypeReturnsFalseForAllowedTypes(int $doktype): void
    {
        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
            $this->createStub(GenericRepository::class),
        );

        $reflection = new \ReflectionMethod($subject, 'isExcludedDoktype');

        $result = $reflection->invoke($subject, ['doktype' => $doktype]);

        self::assertFalse($result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function allowedDoktypeDataProvider(): iterable
    {
        yield 'default' => [PageRepository::DOKTYPE_DEFAULT];
        yield 'custom type 42' => [42];
    }

    public function testIsExcludedDoktypeReturnsFalseWhenDoktypeNotSet(): void
    {
        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
            $this->createStub(GenericRepository::class),
        );

        $reflection = new \ReflectionMethod($subject, 'isExcludedDoktype');

        $result = $reflection->invoke($subject, []);

        self::assertFalse($result);
    }

    public function testFindRenderablePageReturnsNullWhenPageNotFound(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn(null);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertNull($subject->findRenderablePage(999));
    }

    public function testFindRenderablePageReturnsNullForExcludedDoktype(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn([
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_SYSFOLDER,
        ]);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertNull($subject->findRenderablePage(1));
    }

    public function testFindRenderablePageReturnsRowForDefaultLanguage(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
        ];

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $this->createStub(PageRepository::class),
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertSame($row, $subject->findRenderablePage(1));
    }

    public function testFindRenderablePageReturnsNullWhenOverlayLanguageMismatch(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $pageRepositoryStub = $this->createStub(PageRepository::class);
        $pageRepositoryStub->method('getPageOverlay')->willReturn(array_merge($row, ['sys_language_uid' => 0]));

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryStub,
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertNull($subject->findRenderablePage(1, 2));
    }

    public function testFindRenderablePageReturnsNullWhenPageIsHidden(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $pageRepositoryStub = $this->createStub(PageRepository::class);
        $pageRepositoryStub->method('getPageOverlay')->willReturn(array_merge($row, ['sys_language_uid' => 2]));
        $pageRepositoryStub->method('checkIfPageIsHidden')->willReturn(true);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryStub,
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertNull($subject->findRenderablePage(1, 2));
    }

    public function testFindRenderablePageReturnsOverlayForTranslatedPage(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];
        $overlayRow = array_merge($row, ['sys_language_uid' => 2, 'title' => 'Translated']);

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturn($genericRepositoryStub);
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $pageRepositoryStub = $this->createStub(PageRepository::class);
        $pageRepositoryStub->method('getPageOverlay')->willReturn($overlayRow);
        $pageRepositoryStub->method('checkIfPageIsHidden')->willReturn(false);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $languageFieldStub = new LanguageFieldType('sys_language_uid', []);
        $languageCapability = new LanguageAwareSchemaCapability(
            $languageFieldStub,
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('getCapability')->willReturn($languageCapability);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryStub,
            $tcaSchemaFactoryStub,
            $genericRepositoryStub,
        );

        self::assertSame($overlayRow, $subject->findRenderablePage(1, 2));
    }

    public function testFindRecordsOnPageWithNonLanguageAwareSchemaAndDefaultLanguage(): void
    {
        $rows = [
            ['uid' => 1, 'pid' => 10],
            ['uid' => 2, 'pid' => 11],
        ];

        $schemaMock = $this->createMock(TcaSchema::class);
        $schemaMock->method('isLanguageAware')->willReturn(false);
        $schemaMock->expects(self::never())->method('getCapability');

        $tcaSchemaFactoryMock = $this->createMock(TcaSchemaFactory::class);
        $tcaSchemaFactoryMock->expects(self::once())->method('get')->with('tx_foo')->willReturn($schemaMock);

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->expects(self::once())->method('setTableName')->with('tx_foo')->willReturnSelf();
        $genericRepositoryMock->expects(self::once())->method('findRecordsOnPages')
            ->with(
                [10, 11],
                null,
                [0, -1],
                [FrontendRestrictionContainer::class, NonContainerElementsRestrictionContainer::class],
            )
            ->willReturn($rows);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->expects(self::never())->method('getLanguageOverlay');

        $subject = new RecordSelection(
            $this->createRecordFactoryStub(),
            $pageRepositoryMock,
            $tcaSchemaFactoryMock,
            $genericRepositoryMock,
        );

        $result = iterator_to_array($subject->findRecordsOnPage('tx_foo', [10, 11]), false);

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]->getUid());
        self::assertSame(2, $result[1]->getUid());
    }

    public function testFindRecordsOnPageWithLanguageAwareSchemaAndDefaultLanguagePassesLanguageFieldAndRestrictions(): void
    {
        $rows = [
            ['uid' => 5, 'pid' => 10, 'sys_language_uid' => 0],
        ];
        $restrictions = [FrontendRestrictionContainer::class];

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->method('setTableName')->willReturnSelf();
        $genericRepositoryMock->expects(self::once())->method('findRecordsOnPages')
            ->with([10], 'sys_language_uid', [0, -1], $restrictions)
            ->willReturn($rows);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->expects(self::never())->method('getLanguageOverlay');

        $subject = new RecordSelection(
            $this->createRecordFactoryStub(),
            $pageRepositoryMock,
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryMock,
        );

        $result = iterator_to_array($subject->findRecordsOnPage('tt_content', [10], 0, $restrictions), false);

        self::assertCount(1, $result);
        self::assertSame(5, $result[0]->getUid());
    }

    public function testFindRecordsOnPageWithTranslationYieldsOnlyMatchingOverlays(): void
    {
        $rows = [
            ['uid' => 1, 'pid' => 10, 'sys_language_uid' => 0],
            ['uid' => 2, 'pid' => 10, 'sys_language_uid' => 0],
            ['uid' => 3, 'pid' => 10, 'sys_language_uid' => 0],
            ['uid' => 4, 'pid' => 10, 'sys_language_uid' => -1],
        ];
        $overlays = [
            1 => null,
            2 => ['uid' => 20, 'pid' => 10, 'sys_language_uid' => 2],
            3 => ['uid' => 3, 'pid' => 10, 'sys_language_uid' => 0],
            4 => ['uid' => 4, 'pid' => 10, 'sys_language_uid' => -1],
        ];

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->method('setTableName')->willReturnSelf();
        $genericRepositoryMock->expects(self::once())->method('findRecordsOnPages')
            ->with(
                [10],
                'sys_language_uid',
                [0, -1, 2],
                [FrontendRestrictionContainer::class, NonContainerElementsRestrictionContainer::class],
            )
            ->willReturn($rows);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->expects(self::exactly(4))->method('getLanguageOverlay')
            ->willReturnCallback(static function (string $table, array $row, LanguageAspect $aspect) use ($overlays): ?array {
                self::assertSame('tt_content', $table);
                self::assertSame(2, $aspect->getId());
                self::assertSame(2, $aspect->getContentId());
                return $overlays[$row['uid']];
            });

        $subject = new RecordSelection(
            $this->createRecordFactoryStub(),
            $pageRepositoryMock,
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryMock,
        );

        $result = iterator_to_array($subject->findRecordsOnPage('tt_content', [10], 2), false);

        self::assertCount(2, $result);
        self::assertSame(20, $result[0]->getUid());
        self::assertSame(2, $result[0]->getLanguageInfo()?->getLanguageId());
        self::assertSame(4, $result[1]->getUid());
        self::assertSame(-1, $result[1]->getLanguageInfo()?->getLanguageId());
    }

    public function testFindRecordsOnPageReturnsNothingWhenNoRowsFound(): void
    {
        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findRecordsOnPages')->willReturn([]);

        $subject = new RecordSelection(
            $this->createRecordFactoryStub(),
            $this->createStub(PageRepository::class),
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryStub,
        );

        self::assertSame([], iterator_to_array($subject->findRecordsOnPage('tt_content', [10], 2), false));
    }

    public function testFindRenderablePagePassesLanguageAspectToPageRepository(): void
    {
        $row = [
            'uid' => 7,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];
        $overlayRow = array_merge($row, ['sys_language_uid' => 3]);

        $genericRepositoryMock = $this->createMock(GenericRepository::class);
        $genericRepositoryMock->expects(self::once())->method('setTableName')->with('pages')->willReturnSelf();
        $genericRepositoryMock->expects(self::once())->method('findByUid')->with(7)->willReturn($row);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->expects(self::once())->method('getPageOverlay')
            ->with($row, self::callback(static fn(LanguageAspect $aspect): bool => $aspect->getId() === 3 && $aspect->getContentId() === 3))
            ->willReturn($overlayRow);
        $pageRepositoryMock->expects(self::once())->method('checkIfPageIsHidden')
            ->with(7, self::callback(static fn(LanguageAspect $aspect): bool => $aspect->getId() === 3))
            ->willReturn(false);

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryMock,
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryMock,
        );

        self::assertSame($overlayRow, $subject->findRenderablePage(7, 3));
    }

    public function testFindRenderablePageDoesNotCheckHiddenStateOnLanguageMismatch(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->method('getPageOverlay')->willReturn($row);
        $pageRepositoryMock->expects(self::never())->method('checkIfPageIsHidden');

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryMock,
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryStub,
        );

        self::assertNull($subject->findRenderablePage(1, 2));
    }

    public function testFindRenderablePageForDefaultLanguageDoesNotUseOverlay(): void
    {
        $row = [
            'uid' => 1,
            'pid' => 0,
            'doktype' => PageRepository::DOKTYPE_DEFAULT,
            'sys_language_uid' => 0,
        ];

        $genericRepositoryStub = $this->createStub(GenericRepository::class);
        $genericRepositoryStub->method('setTableName')->willReturnSelf();
        $genericRepositoryStub->method('findByUid')->willReturn($row);

        $pageRepositoryMock = $this->createMock(PageRepository::class);
        $pageRepositoryMock->expects(self::never())->method('getPageOverlay');
        $pageRepositoryMock->expects(self::never())->method('checkIfPageIsHidden');

        $subject = new RecordSelection(
            $this->createStub(RecordFactory::class),
            $pageRepositoryMock,
            $this->createLanguageAwareSchemaFactoryStub(),
            $genericRepositoryStub,
        );

        self::assertSame($row, $subject->findRenderablePage(1));
    }

    private function createRecordFactoryStub(): RecordFactory
    {
        $recordFactoryStub = $this->createStub(RecordFactory::class);
        $recordFactoryStub->method('createResolvedRecordFromDatabaseRow')
            ->willReturnCallback(function (string $table, array $row): Record {
                $recordStub = $this->createStub(Record::class);
                $recordStub->method('getUid')->willReturn($row['uid']);
                $recordStub->method('getLanguageInfo')->willReturn(
                    new LanguageInfo((int) ($row['sys_language_uid'] ?? 0), null, null),
                );
                return $recordStub;
            });

        return $recordFactoryStub;
    }

    private function createLanguageAwareSchemaFactoryStub(): TcaSchemaFactory
    {
        $languageCapability = new LanguageAwareSchemaCapability(
            new LanguageFieldType('sys_language_uid', []),
            $this->createStub(FieldTypeInterface::class),
            null,
            null,
        );
        $tcaSchemaStub = $this->createStub(TcaSchema::class);
        $tcaSchemaStub->method('isLanguageAware')->willReturn(true);
        $tcaSchemaStub->method('getCapability')
            ->willReturnCallback(static fn(TcaSchemaCapability $capability): LanguageAwareSchemaCapability => $languageCapability);

        $tcaSchemaFactoryStub = $this->createStub(TcaSchemaFactory::class);
        $tcaSchemaFactoryStub->method('get')->willReturn($tcaSchemaStub);

        return $tcaSchemaFactoryStub;
    }
}
