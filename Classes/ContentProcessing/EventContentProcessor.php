<?php

declare(strict_types=1);

namespace Lochmueller\Index\ContentProcessing;

use Lochmueller\Index\Event\ModifyContentEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventContentProcessor implements ContentProcessorInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getLabel(): string
    {
        return 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.content_processors.type.event';
    }

    public function process(string $htmlContent): string
    {
        $event = $this->eventDispatcher->dispatch(new ModifyContentEvent($htmlContent));

        return $event->content;
    }
}
