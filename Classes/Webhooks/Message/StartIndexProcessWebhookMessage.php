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
        // @todo add all fields
    ) {}

    public static function createFromEvent(StartIndexProcessEvent $event): self
    {

        // @todo $event...
        return new self();
    }

    public function jsonSerialize(): mixed
    {
        return [
            # '' Add Attributes
        ];
    }
}
