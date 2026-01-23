<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Utility;

use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Utility\AccessGroupParser;

class AccessGroupParserTest extends AbstractTest
{
    /**
     * @test
     * Tests: Requirements 6.2
     */
    public function testParseEmptyStringReturnsEmptyArray(): void
    {
        $result = AccessGroupParser::parse('');

        self::assertSame([], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.3
     */
    public function testParseZeroStringReturnsEmptyArray(): void
    {
        $result = AccessGroupParser::parse('0');

        self::assertSame([], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.4
     */
    public function testParseSingleValueReturnsArrayWithOneElement(): void
    {
        $result = AccessGroupParser::parse('1');

        self::assertSame([1], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.4
     */
    public function testParseCommaSeparatedValuesReturnsIntegerArray(): void
    {
        $result = AccessGroupParser::parse('1,2,3');

        self::assertSame([1, 2, 3], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.5
     */
    public function testParseValuesWithWhitespaceReturnsCleanArray(): void
    {
        $result = AccessGroupParser::parse('1, 2, 3');

        self::assertSame([1, 2, 3], $result);
    }

    /**
     * @test
     * Tests: Requirements 1.4
     */
    public function testParseSpecialValueMinusOneReturnsArrayWithMinusOne(): void
    {
        $result = AccessGroupParser::parse('-1');

        self::assertSame([-1], $result);
    }

    /**
     * @test
     * Tests: Requirements 1.5
     */
    public function testParseSpecialValueMinusTwoReturnsArrayWithMinusTwo(): void
    {
        $result = AccessGroupParser::parse('-2');

        self::assertSame([-2], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.4
     */
    public function testParseMixedValuesWithSpecialValuesReturnsCorrectArray(): void
    {
        $result = AccessGroupParser::parse('1,-1,2');

        self::assertSame([1, -1, 2], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.5
     */
    public function testParseTrimsLeadingAndTrailingWhitespace(): void
    {
        $result = AccessGroupParser::parse('  1,2,3  ');

        self::assertSame([1, 2, 3], $result);
    }

    /**
     * @test
     * Tests: Requirements 6.4
     */
    public function testParseFiltersZeroValuesFromResult(): void
    {
        $result = AccessGroupParser::parse('1,0,2');

        self::assertSame([1, 2], $result);
    }

    /**
     * @test
     */
    public function testFormatEmptyArrayReturnsEmptyString(): void
    {
        $result = AccessGroupParser::format([]);

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function testFormatSingleValueReturnsString(): void
    {
        $result = AccessGroupParser::format([1]);

        self::assertSame('1', $result);
    }

    /**
     * @test
     */
    public function testFormatMultipleValuesReturnsCommaSeparatedString(): void
    {
        $result = AccessGroupParser::format([1, 2, 3]);

        self::assertSame('1,2,3', $result);
    }

    /**
     * @test
     */
    public function testFormatSpecialValuesReturnsCorrectString(): void
    {
        $result = AccessGroupParser::format([-1, -2]);

        self::assertSame('-1,-2', $result);
    }

    /**
     * @test
     */
    public function testFormatMixedValuesReturnsCorrectString(): void
    {
        $result = AccessGroupParser::format([1, -1, 2]);

        self::assertSame('1,-1,2', $result);
    }

    /**
     * Generates random non-zero integer arrays for property testing.
     *
     * @return \Generator<string, array{int[]}>
     */
    public static function randomNonZeroIntegerArraysProvider(): \Generator
    {
        // Seed for reproducibility in case of failures
        srand(42);

        for ($i = 0; $i < 100; $i++) {
            $length = random_int(0, 10);
            $accessGroups = [];

            for ($j = 0; $j < $length; $j++) {
                // Generate non-zero integers in range [-100, 100] excluding 0
                $value = random_int(-100, 100);
                if ($value === 0) {
                    $value = random_int(1, 100);
                }
                $accessGroups[] = $value;
            }

            yield "iteration_{$i}" => [$accessGroups];
        }
    }

    /**
     * **Property 1: Round-Trip Konsistenz**
     * Für alle gültigen Integer-Arrays (Access Groups), wenn wir das Array
     * formatieren und dann wieder parsen, soll das Ergebnis dem ursprünglichen
     * Array entsprechen.
     *
     * **Validates: Requirements 6.6**
     *
     * @param int[] $accessGroups
     */
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('randomNonZeroIntegerArraysProvider')]
    public function testRoundTripConsistencyProperty(array $accessGroups): void
    {
        $formatted = AccessGroupParser::format($accessGroups);
        $parsed = AccessGroupParser::parse($formatted);

        self::assertSame(
            $accessGroups,
            $parsed,
            sprintf(
                'Round-trip failed: format(%s) = "%s", parse("%s") = %s',
                json_encode($accessGroups),
                $formatted,
                $formatted,
                json_encode($parsed),
            ),
        );
    }
}
