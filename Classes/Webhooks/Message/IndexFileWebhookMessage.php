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
        protected IndexFileEvent $indexFileEvent,
    ) {}

    public static function createFromEvent(IndexFileEvent $event): self
    {
        return new self($event);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'indexConfigurationRecordId' => $this->indexFileEvent->indexConfigurationRecordId,
            'title' => $this->indexFileEvent->title,
            'content' => $this->indexFileEvent->content,
            'fileIdentifier' => $this->indexFileEvent->fileIdentifier,
        ];
    }
}
