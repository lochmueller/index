<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class FinishProcessMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $technology = IndexTechnology::Database;
        $type = IndexType::Full;
        $configurationRecordId = 42;
        $processId = 'process-123';

        $subject = new FinishProcessMessage(
            siteIdentifier: $siteIdentifier,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configurationRecordId,
            indexProcessId: $processId,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($configurationRecordId, $subject->indexConfigurationRecordId);
        self::assertSame($processId, $subject->indexProcessId);
    }

    public function testNullConfigurationRecordId(): void
    {
        $subject = new FinishProcessMessage(
            siteIdentifier: 'site',
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'process',
        );

        self::assertNull($subject->indexConfigurationRecordId);
    }
}
