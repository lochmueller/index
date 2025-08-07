<?php

declare(strict_types=1);

namespace Lochmueller\Index\Webhooks\Message;

use Lochmueller\Index\Event\IndexFileEvent;
use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;

#[WebhookMessage(
    identifier: 'index/file',
    description: '... when a file is indexed.',
)]
class IndexFileWebhookMessage implements WebhookMessageInterface
{
    public function __construct(
        // @todo add all fields
    ) {}

    public static function createFromEvent(IndexFileEvent $event): self
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
