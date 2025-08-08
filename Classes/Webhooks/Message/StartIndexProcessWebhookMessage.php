<?php

declare(strict_types=1);

namespace Lochmueller\Index\Webhooks\Message;

use Lochmueller\Index\Event\StartIndexProcessEvent;
use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;

#[WebhookMessage(
    identifier: 'index/start',
    description: '... when a index process is started.',
)]
class StartIndexProcessWebhookMessage implements WebhookMessageInterface
{
    public function __construct(
        protected StartIndexProcessEvent $event,
    ) {}

    public static function createFromEvent(StartIndexProcessEvent $event): self
    {
        return new self($event);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'siteIdentifier' => $this->event->site->getIdentifier(),
            'technology' => $this->event->technology->value,
            'type' => $this->event->type->value,
            'indexConfigurationRecordId' => $this->event->indexConfigurationRecordId,
            'indexProcessId' => $this->event->indexProcessId,
            'startTime' => $this->event->startTime,
        ];
    }
}
