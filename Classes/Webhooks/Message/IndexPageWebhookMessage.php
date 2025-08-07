<?php

declare(strict_types=1);

namespace Lochmueller\Index\Webhooks\Message;

use Lochmueller\Index\Event\IndexPageEvent;
use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;

#[WebhookMessage(
    identifier: 'index/page',
    description: '... when a page is indexed.',
)]
class IndexPageWebhookMessage implements WebhookMessageInterface
{
    public function __construct(
        // @todo add all fields
    ) {}

    public static function createFromEvent(IndexPageEvent $event): self
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
