<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Traversing;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Traversing\RecordSelection;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
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
            ->with($table, $row)
            ->willReturn($recordStub);

        $subject = new RecordSelection(
            $recordFactoryStub,
            $this->createStub(PageRepository::class),
            $this->createStub(TcaSchemaFactory::class),
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
        );

        $reflection = new \ReflectionMethod($subject, 'isExcludedDoktype');

        $result = $reflection->invoke($subject, []);

        self::assertFalse($result);
    }
}
