<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Indexing\Frontend;

use Lochmueller\Index\Indexing\Frontend\FrontendContextBuilder;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class FrontendContextBuilderTest extends AbstractTest
{
    public function testExecuteInFrontendContextReturnsCallbackResult(): void
    {
        $subject = new FrontendContextBuilder();

        $result = $subject->executeInFrontendContext(fn() => 'test-result');

        self::assertSame('test-result', $result);
    }

    public function testExecuteInFrontendContextRethrowsException(): void
    {
        $subject = new FrontendContextBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $subject->executeInFrontendContext(function (): void {
            throw new \RuntimeException('Test exception');
        });
    }

    public function testExecuteInFrontendContextRestoresStateAfterException(): void
    {
        $subject = new FrontendContextBuilder();
        $originalBeUser = $GLOBALS['BE_USER'] ?? null;

        try {
            $subject->executeInFrontendContext(function (): void {
                throw new \RuntimeException('Test');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertSame($originalBeUser, $GLOBALS['BE_USER'] ?? null);
    }
}
