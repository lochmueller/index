<?php

declare(strict_types=1);

namespace Lochmueller\Index\Webhooks\Message;

use Lochmueller\Index\Event\FinishIndexProcessEvent;
use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;

#[WebhookMessage(
    identifier: 'index/finish',
    description: '... when a index process is finished.',
)]
class FinishIndexProcessWebhookMessage implements WebhookMessageInterface
{
    public function __construct(
        public FinishIndexProcessEvent $event,
    ) {}

    public static function createFromEvent(FinishIndexProcessEvent $event): self
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
            'endTime' => $this->event->endTime,
        ];
    }
}
