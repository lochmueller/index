<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Webhooks\Message;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Webhooks\Message\IndexFileWebhookMessage;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class IndexFileWebhookMessageTest extends AbstractTest
{
    public function testConstructorSetsEvent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            title: 'Test File',
            content: 'File content',
            fileIdentifier: '1:/documents/test.pdf',
        );

        $subject = new IndexFileWebhookMessage($event);

        self::assertInstanceOf(IndexFileWebhookMessage::class, $subject);
    }

    public function testCreateFromEventReturnsNewInstance(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-456',
            title: 'Another File',
            content: 'Content here',
            fileIdentifier: '1:/files/document.docx',
        );

        $subject = IndexFileWebhookMessage::createFromEvent($event);

        self::assertInstanceOf(IndexFileWebhookMessage::class, $subject);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 99,
            indexProcessId: 'file-process-789',
            title: 'Important Document',
            content: 'This is the extracted file content',
            fileIdentifier: '1:/uploads/important.pdf',
        );

        $subject = new IndexFileWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame([
            'indexConfigurationRecordId' => 99,
            'title' => 'Important Document',
            'content' => 'This is the extracted file content',
            'fileIdentifier' => '1:/uploads/important.pdf',
        ], $result);
    }

    public function testJsonSerializeWithEmptyContent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 5,
            indexProcessId: 'empty-content-process',
            title: 'Empty File',
            content: '',
            fileIdentifier: '1:/empty.txt',
        );

        $subject = new IndexFileWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame('', $result['content']);
    }
}
