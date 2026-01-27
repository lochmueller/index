<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Webhooks\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Webhooks\Message\StartIndexProcessWebhookMessage;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class StartIndexProcessWebhookMessageTest extends AbstractTest
{
    public function testConstructorSetsEvent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            startTime: 1234567890.123,
        );

        $subject = new StartIndexProcessWebhookMessage($event);

        self::assertInstanceOf(StartIndexProcessWebhookMessage::class, $subject);
    }

    public function testCreateFromEventReturnsNewInstance(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-456',
            startTime: 9876543210.456,
        );

        $subject = StartIndexProcessWebhookMessage::createFromEvent($event);

        self::assertInstanceOf(StartIndexProcessWebhookMessage::class, $subject);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main-site');

        $event = new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 99,
            indexProcessId: 'start-process-789',
            startTime: 1700000000.789,
        );

        $subject = new StartIndexProcessWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame([
            'siteIdentifier' => 'main-site',
            'technology' => 'http',
            'type' => 'full',
            'indexConfigurationRecordId' => 99,
            'indexProcessId' => 'start-process-789',
            'startTime' => 1700000000.789,
        ], $result);
    }

    public function testJsonSerializeWithNullConfigurationRecordId(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $event = new StartIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'external-process',
            startTime: 1600000000.0,
        );

        $subject = new StartIndexProcessWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertNull($result['indexConfigurationRecordId']);
    }
}
