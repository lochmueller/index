<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;

class CachePageMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $technology = IndexTechnology::Cache;
        $type = IndexType::Full;
        $configurationRecordId = 42;
        $language = 1;
        $title = 'Test Page';
        $content = 'Test content';
        $pageUid = 123;
        $accessGroups = [1, 2, 3];
        $processId = 'process-123';

        $subject = new CachePageMessage(
            siteIdentifier: $siteIdentifier,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configurationRecordId,
            language: $language,
            title: $title,
            content: $content,
            pageUid: $pageUid,
            accessGroups: $accessGroups,
            indexProcessId: $processId,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($configurationRecordId, $subject->indexConfigurationRecordId);
        self::assertSame($language, $subject->language);
        self::assertSame($title, $subject->title);
        self::assertSame($content, $subject->content);
        self::assertSame($pageUid, $subject->pageUid);
        self::assertSame($accessGroups, $subject->accessGroups);
        self::assertSame($processId, $subject->indexProcessId);
    }

    public function testEmptyAccessGroups(): void
    {
        $subject = new CachePageMessage(
            siteIdentifier: 'site',
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            language: 0,
            title: 'Title',
            content: 'Content',
            pageUid: 1,
            accessGroups: [],
            indexProcessId: 'process',
        );

        self::assertSame([], $subject->accessGroups);
    }
}
