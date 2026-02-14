<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Schema\Capability\LanguageAwareSchemaCapability;
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
}
