<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\ExternalPageIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class ExternalPageIndexMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $language = 1;
        $technology = IndexTechnology::External;
        $type = IndexType::Full;
        $uri = 'https://example.com/page';
        $title = 'Test Page';
        $content = 'Page content';
        $processId = 'process-123';
        $accessGroups = [1, 2, 3];

        $subject = new ExternalPageIndexMessage(
            siteIdentifier: $siteIdentifier,
            language: $language,
            technology: $technology,
            type: $type,
            uri: $uri,
            title: $title,
            content: $content,
            indexProcessId: $processId,
            accessGroups: $accessGroups,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($language, $subject->language);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($uri, $subject->uri);
        self::assertSame($title, $subject->title);
        self::assertSame($content, $subject->content);
        self::assertSame($processId, $subject->indexProcessId);
        self::assertSame($accessGroups, $subject->accessGroups);
    }

    public function testDefaultAccessGroupsIsEmptyArray(): void
    {
        $subject = new ExternalPageIndexMessage(
            siteIdentifier: 'site',
            language: 0,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            uri: 'https://example.com',
            title: 'Title',
            content: 'Content',
            indexProcessId: 'process',
        );

        self::assertSame([], $subject->accessGroups);
    }
}
