<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class FileMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $configurationRecordId = 42;
        $fileIdentifier = '1:/user_upload/test.pdf';
        $processId = 'process-123';

        $subject = new FileMessage(
            siteIdentifier: $siteIdentifier,
            indexConfigurationRecordId: $configurationRecordId,
            fileIdentifier: $fileIdentifier,
            indexProcessId: $processId,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($configurationRecordId, $subject->indexConfigurationRecordId);
        self::assertSame($fileIdentifier, $subject->fileIdentifier);
        self::assertSame($processId, $subject->indexProcessId);
    }
}
