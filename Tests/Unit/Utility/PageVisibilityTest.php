<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Utility;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\PageVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

class PageVisibilityTest extends AbstractTest
{
    private const NOW = 1_700_000_000;

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('visibilityDataProvider')]
    public function testIsVisible(array $row, bool $expected): void
    {
        self::assertSame($expected, PageVisibility::isVisible($row, self::NOW));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function visibilityDataProvider(): iterable
    {
        yield 'no visibility fields' => [[], true];
        yield 'visible page' => [['hidden' => 0, 'starttime' => 0, 'endtime' => 0], true];
        yield 'hidden page' => [['hidden' => 1], false];
        yield 'starttime reached' => [['starttime' => self::NOW], true];
        yield 'starttime in the future' => [['starttime' => self::NOW + 1], false];
        yield 'endtime in the future' => [['endtime' => self::NOW + 1], true];
        yield 'endtime reached' => [['endtime' => self::NOW], false];
        yield 'endtime in the past' => [['endtime' => self::NOW - 1], false];
    }

    public function testIsRenderableReturnsFalseForExcludedDoktype(): void
    {
        self::assertFalse(PageVisibility::isRenderable(['doktype' => PageRepository::DOKTYPE_SYSFOLDER], self::NOW));
    }

    public function testIsRenderableReturnsFalseForInvisiblePage(): void
    {
        self::assertFalse(PageVisibility::isRenderable(['doktype' => PageRepository::DOKTYPE_DEFAULT, 'hidden' => 1], self::NOW));
    }

    public function testIsRenderableReturnsTrueForVisibleDefaultPage(): void
    {
        self::assertTrue(PageVisibility::isRenderable(['doktype' => PageRepository::DOKTYPE_DEFAULT], self::NOW));
    }
}
