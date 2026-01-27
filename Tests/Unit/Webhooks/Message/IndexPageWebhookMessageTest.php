<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Webhooks\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Webhooks\Message\IndexPageWebhookMessage;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class IndexPageWebhookMessageTest extends AbstractTest
{
    public function testConstructorSetsEvent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            language: 0,
            title: 'Test Page',
            content: 'Page content',
            pageUid: 100,
            accessGroups: [1, 2],
        );

        $subject = new IndexPageWebhookMessage($event);

        self::assertInstanceOf(IndexPageWebhookMessage::class, $subject);
    }

    public function testCreateFromEventReturnsNewInstance(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new IndexPageEvent(
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

        $subject = IndexPageWebhookMessage::createFromEvent($event);

        self::assertInstanceOf(IndexPageWebhookMessage::class, $subject);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main-site');

        $accessGroups = [1, 2, 3];
        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 99,
            indexProcessId: 'page-process-789',
            language: 2,
            title: 'Important Page',
            content: 'This is the page content',
            pageUid: 500,
            accessGroups: $accessGroups,
        );

        $subject = new IndexPageWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame([
            'siteIdentifier' => 'main-site',
            'technology' => 'http',
            'type' => 'full',
            'indexConfigurationRecordId' => 99,
            'language' => 2,
            'title' => 'Important Page',
            'content' => 'This is the page content',
            'pageUid' => 500,
            'accessGroups' => [1, 2, 3],
        ], $result);
    }

    public function testJsonSerializeWithEmptyAccessGroups(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Cache,
            type: IndexType::Partial,
            indexConfigurationRecordId: 5,
            indexProcessId: 'public-page-process',
            language: 0,
            title: 'Public Page',
            content: 'Public content',
            pageUid: 300,
            accessGroups: [],
        );

        $subject = new IndexPageWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame([], $result['accessGroups']);
    }
}
