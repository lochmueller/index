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
        protected IndexPageEvent $event,
    ) {}

    public static function createFromEvent(IndexPageEvent $event): self
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
            'language' => $this->event->language,
            'title' => $this->event->title,
            'content' => $this->event->content,
            'pageUid' => $this->event->pageUid,
            'accessGroups' => $this->event->accessGroups,
        ];
    }
}
