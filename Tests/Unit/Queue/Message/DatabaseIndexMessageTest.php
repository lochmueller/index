<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Psr\Http\Message\UriInterface;

class DatabaseIndexMessageTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $siteIdentifier = 'test-site';
        $technology = IndexTechnology::Database;
        $type = IndexType::Full;
        $configurationRecordId = 42;
        $uri = $this->createStub(UriInterface::class);
        $pageUid = 123;
        $processId = 'process-123';
        $language = 1;
        $accessGroups = [1, 2, 3];

        $subject = new DatabaseIndexMessage(
            siteIdentifier: $siteIdentifier,
            technology: $technology,
            type: $type,
            indexConfigurationRecordId: $configurationRecordId,
            uri: $uri,
            pageUid: $pageUid,
            indexProcessId: $processId,
            language: $language,
            accessGroups: $accessGroups,
        );

        self::assertSame($siteIdentifier, $subject->siteIdentifier);
        self::assertSame($technology, $subject->technology);
        self::assertSame($type, $subject->type);
        self::assertSame($configurationRecordId, $subject->indexConfigurationRecordId);
        self::assertSame($uri, $subject->uri);
        self::assertSame($pageUid, $subject->pageUid);
        self::assertSame($processId, $subject->indexProcessId);
        self::assertSame($language, $subject->language);
        self::assertSame($accessGroups, $subject->accessGroups);
    }

    public function testDefaultAccessGroupsIsEmptyArray(): void
    {
        $subject = new DatabaseIndexMessage(
            siteIdentifier: 'site',
            technology: IndexTechnology::Database,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            uri: $this->createStub(UriInterface::class),
            pageUid: 1,
            indexProcessId: 'process',
            language: 0,
        );

        self::assertSame([], $subject->accessGroups);
    }
}
