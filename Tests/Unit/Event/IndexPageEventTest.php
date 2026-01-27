<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class IndexPageEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $accessGroups = [1, 2, 3];

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            language: 0,
            title: 'Test Page',
            content: 'Page content here',
            pageUid: 100,
            accessGroups: $accessGroups,
            uri: 'https://example.com/test-page',
        );

        self::assertSame($site, $subject->site);
        self::assertSame(IndexTechnology::Database, $subject->technology);
        self::assertSame(IndexType::Full, $subject->type);
        self::assertSame(42, $subject->indexConfigurationRecordId);
        self::assertSame('process-123', $subject->indexProcessId);
        self::assertSame(0, $subject->language);
        self::assertSame('Test Page', $subject->title);
        self::assertSame('Page content here', $subject->content);
        self::assertSame(100, $subject->pageUid);
        self::assertSame($accessGroups, $subject->accessGroups);
        self::assertSame('https://example.com/test-page', $subject->uri);
    }

    public function testConstructorWithDefaultUri(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-456',
            language: 1,
            title: 'Another Page',
            content: 'Content',
            pageUid: 200,
            accessGroups: [],
        );

        self::assertSame('', $subject->uri);
    }

    public function testTitleCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-789',
            language: 0,
            title: 'Original Title',
            content: 'Content',
            pageUid: 300,
            accessGroups: [],
        );

        $subject->title = 'Modified Title';

        self::assertSame('Modified Title', $subject->title);
    }

    public function testContentCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Cache,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-abc',
            language: 0,
            title: 'Title',
            content: 'Original Content',
            pageUid: 400,
            accessGroups: [],
        );

        $subject->content = 'Modified Content';

        self::assertSame('Modified Content', $subject->content);
    }

    public function testIndexProcessIdCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            indexProcessId: 'original-process',
            language: 0,
            title: 'Title',
            content: 'Content',
            pageUid: 500,
            accessGroups: [],
        );

        $subject->indexProcessId = 'new-process';

        self::assertSame('new-process', $subject->indexProcessId);
    }

    public function testEmptyAccessGroupsAreHandled(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-def',
            language: 0,
            title: 'Public Page',
            content: 'Content',
            pageUid: 600,
            accessGroups: [],
        );

        self::assertSame([], $subject->accessGroups);
    }
}
