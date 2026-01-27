<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Event;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class IndexFileEventTest extends AbstractTest
{
    public function testConstructorSetsAllProperties(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            title: 'Test File',
            content: 'File content here',
            fileIdentifier: '/fileadmin/test.pdf',
            uri: 'https://example.com/fileadmin/test.pdf',
        );

        self::assertSame($site, $subject->site);
        self::assertSame(42, $subject->indexConfigurationRecordId);
        self::assertSame('process-123', $subject->indexProcessId);
        self::assertSame('Test File', $subject->title);
        self::assertSame('File content here', $subject->content);
        self::assertSame('/fileadmin/test.pdf', $subject->fileIdentifier);
        self::assertSame('https://example.com/fileadmin/test.pdf', $subject->uri);
    }

    public function testConstructorWithDefaultUri(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-456',
            title: 'Another File',
            content: 'Content',
            fileIdentifier: '/fileadmin/doc.docx',
        );

        self::assertSame('', $subject->uri);
    }

    public function testTitleCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-789',
            title: 'Original Title',
            content: 'Content',
            fileIdentifier: '/fileadmin/file.txt',
        );

        $subject->title = 'Modified Title';

        self::assertSame('Modified Title', $subject->title);
    }

    public function testContentCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-abc',
            title: 'Title',
            content: 'Original Content',
            fileIdentifier: '/fileadmin/file.txt',
        );

        $subject->content = 'Modified Content';

        self::assertSame('Modified Content', $subject->content);
    }

    public function testIndexProcessIdCanBeModified(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $subject = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'original-process',
            title: 'Title',
            content: 'Content',
            fileIdentifier: '/fileadmin/file.txt',
        );

        $subject->indexProcessId = 'new-process';

        self::assertSame('new-process', $subject->indexProcessId);
    }
}
