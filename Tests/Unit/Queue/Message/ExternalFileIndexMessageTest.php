<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\ExternalFileIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class ExternalFileIndexMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $language = 1;
        $technology = IndexTechnology::External;
        $type = IndexType::Full;
        $uri = 'https://example.com/file.pdf';
        $title = 'Test File';
        $content = 'File content';
        $processId = 'process-123';

        $subject = new ExternalFileIndexMessage(
            siteIdentifier: $siteIdentifier,
            language: $language,
            technology: $technology,
            type: $type,
            uri: $uri,
            title: $title,
            content: $content,
            indexProcessId: $processId,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($language, $subject->language);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($uri, $subject->uri);
        self::assertSame($title, $subject->title);
        self::assertSame($content, $subject->content);
        self::assertSame($processId, $subject->indexProcessId);
    }
}
