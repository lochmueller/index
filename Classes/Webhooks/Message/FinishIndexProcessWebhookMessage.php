<?php

declare(strict_types=1);

namespace Lochmueller\Index\Webhooks\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

#[WebhookMessage(
    identifier: 'index/finish',
    description: '... when a index process is finished.',
)]
class FinishIndexProcessWebhookMessage implements WebhookMessageInterface
{
    public function __construct(
        public SiteInterface   $site,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        public string          $indexProcessId,
        public float           $endTime,
    ) {}

    public static function createFromEvent(FinishIndexProcessEvent $event): self
    {
        return new self(
            $event->site,
            $event->technology,
            $event->type,
            $event->indexConfigurationRecordId,
            $event->indexProcessId,
            $event->endTime,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'siteIdentifier' => $this->site->getIdentifier(),
            'technology' => $this->technology->value,
            'type' => $this->type->value,
            'indexConfigurationRecordId' => $this->indexConfigurationRecordId,
            'indexProcessId' => $this->indexProcessId,
            'endTime' => $this->endTime,
        ];
    }
}
