<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\FetchIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class FetchIndexMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $subject = new FetchIndexMessage(
            siteIdentifier: 'test-site',
            technology: IndexTechnology::Fetch,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            language: 1,
            uri: 'https://example.com/',
            depth: 3,
            skipFiles: true,
        );

        self::assertSame('test-site', $subject->siteIdentifier);
        self::assertSame(IndexTechnology::Fetch, $subject->technology);
        self::assertSame(IndexType::Full, $subject->type);
        self::assertSame(42, $subject->indexConfigurationRecordId);
        self::assertSame('process-123', $subject->indexProcessId);
        self::assertSame(1, $subject->language);
        self::assertSame('https://example.com/', $subject->uri);
        self::assertSame(3, $subject->depth);
        self::assertTrue($subject->skipFiles);
    }

    public function testSkipFilesDefaultsToFalse(): void
    {
        $subject = new FetchIndexMessage('test-site', IndexTechnology::Fetch, IndexType::Full, 42, 'process-123', 0, 'https://example.com/', 0);

        self::assertFalse($subject->skipFiles);
    }
}
