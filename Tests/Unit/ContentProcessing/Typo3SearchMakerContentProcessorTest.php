<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\ContentProcessing;

use Lochmueller\Index\ContentProcessing\Typo3SearchMakerContentProcessor;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class Typo3SearchMakerContentProcessorTest extends AbstractTest
{
    public function testContentWithoutMarkersIsReturnedUnchanged(): void
    {
        $html = '<html><body><p>Hello World</p></body></html>';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame($html, $subject->process($html));
    }

    public function testFirstMarkerEndIncludesPreviousContentAndExcludesAfter(): void
    {
        $html = 'included<!--TYPO3SEARCH_end-->excluded';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('included', $subject->process($html));
    }

    public function testFirstMarkerBeginExcludesPreviousContentAndIncludesAfter(): void
    {
        $html = 'excluded<!--TYPO3SEARCH_begin-->included';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('included', $subject->process($html));
    }

    public function testMultipleMarkerPairsIncludeContentBetweenEachPair(): void
    {
        $html = 'skip<!--TYPO3SEARCH_begin-->one<!--TYPO3SEARCH_end-->skip<!--TYPO3SEARCH_begin-->two<!--TYPO3SEARCH_end-->skip';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('onetwo', $subject->process($html));
    }

    public function testEndBeginEndSequenceIncludesOuterAndInnerSections(): void
    {
        $html = 'first<!--TYPO3SEARCH_end-->skip<!--TYPO3SEARCH_begin-->second<!--TYPO3SEARCH_end-->skip';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('firstsecond', $subject->process($html));
    }

    public function testTrailingContentAfterBeginIsIncluded(): void
    {
        $html = 'skip<!--TYPO3SEARCH_begin-->trailing';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('trailing', $subject->process($html));
    }

    public function testTrailingContentAfterEndIsExcluded(): void
    {
        $html = 'included<!--TYPO3SEARCH_end-->trailing';

        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('included', $subject->process($html));
    }

    public function testEmptyStringReturnsEmptyString(): void
    {
        $subject = new Typo3SearchMakerContentProcessor();

        self::assertSame('', $subject->process(''));
    }
}
