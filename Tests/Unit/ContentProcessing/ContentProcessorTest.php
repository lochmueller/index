<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\ContentProcessing;

use Lochmueller\Index\ContentProcessing\ContentProcessor;
use Lochmueller\Index\ContentProcessing\ContentProcessorInterface;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class ContentProcessorTest extends AbstractTest
{
    public function testGetBackendItemsAddsEntryForEachProcessor(): void
    {
        $processorA = new class implements ContentProcessorInterface {
            public function getLabel(): string
            {
                return 'Label A';
            }

            public function process(string $htmlContent): string
            {
                return $htmlContent;
            }
        };

        $processorB = new class implements ContentProcessorInterface {
            public function getLabel(): string
            {
                return 'Label B';
            }

            public function process(string $htmlContent): string
            {
                return $htmlContent;
            }
        };

        $subject = new ContentProcessor([$processorA, $processorB]);

        $params = ['items' => []];
        $subject->getBackendItems($params);

        self::assertCount(2, $params['items']);
        self::assertSame('Label A', $params['items'][0]['label']);
        self::assertSame($processorA::class, $params['items'][0]['value']);
        self::assertSame('Label B', $params['items'][1]['label']);
        self::assertSame($processorB::class, $params['items'][1]['value']);
    }

    public function testGetBackendItemsPreservesExistingItems(): void
    {
        $processor = new class implements ContentProcessorInterface {
            public function getLabel(): string
            {
                return 'Label';
            }

            public function process(string $htmlContent): string
            {
                return $htmlContent;
            }
        };

        $subject = new ContentProcessor([$processor]);

        $params = ['items' => [['label' => 'existing', 'value' => 'existing']]];
        $subject->getBackendItems($params);

        self::assertCount(2, $params['items']);
        self::assertSame('existing', $params['items'][0]['value']);
        self::assertSame($processor::class, $params['items'][1]['value']);
    }

    public function testGetBackendItemsWithoutProcessorsLeavesItemsEmpty(): void
    {
        $subject = new ContentProcessor([]);

        $params = ['items' => []];
        $subject->getBackendItems($params);

        self::assertSame([], $params['items']);
    }
}
