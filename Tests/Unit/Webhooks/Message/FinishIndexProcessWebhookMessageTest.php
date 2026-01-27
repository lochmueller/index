<?php

declare(strict_types=1);

namespace Lochmueller\Index\Tests\Unit\Webhooks\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Tests\Unit\AbstractTest;
use Lochmueller\Index\Webhooks\Message\FinishIndexProcessWebhookMessage;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class FinishIndexProcessWebhookMessageTest extends AbstractTest
{
    public function testConstructorSetsEvent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Database,
            type: IndexType::Full,
            indexConfigurationRecordId: 42,
            indexProcessId: 'process-123',
            endTime: 1234567890.123,
        );

        $subject = new FinishIndexProcessWebhookMessage($event);

        self::assertSame($event, $subject->event);
    }

    public function testCreateFromEventReturnsNewInstance(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $event = new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Partial,
            indexConfigurationRecordId: 1,
            indexProcessId: 'process-456',
            endTime: 9876543210.456,
        );

        $subject = FinishIndexProcessWebhookMessage::createFromEvent($event);

        self::assertInstanceOf(FinishIndexProcessWebhookMessage::class, $subject);
        self::assertSame($event, $subject->event);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main-site');

        $event = new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::Http,
            type: IndexType::Full,
            indexConfigurationRecordId: 99,
            indexProcessId: 'finish-process-789',
            endTime: 1700000000.789,
        );

        $subject = new FinishIndexProcessWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertSame([
            'siteIdentifier' => 'main-site',
            'technology' => 'http',
            'type' => 'full',
            'indexConfigurationRecordId' => 99,
            'indexProcessId' => 'finish-process-789',
            'endTime' => 1700000000.789,
        ], $result);
    }

    public function testJsonSerializeWithNullConfigurationRecordId(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('test-site');

        $event = new FinishIndexProcessEvent(
            site: $site,
            technology: IndexTechnology::External,
            type: IndexType::Partial,
            indexConfigurationRecordId: null,
            indexProcessId: 'external-process',
            endTime: 1600000000.0,
        );

        $subject = new FinishIndexProcessWebhookMessage($event);
        $result = $subject->jsonSerialize();

        self::assertNull($result['indexConfigurationRecordId']);
    }
}
