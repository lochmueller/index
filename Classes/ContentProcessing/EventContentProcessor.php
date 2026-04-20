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

    public function process(string $htmlContent): string
    {
        $event = $this->eventDispatcher->dispatch(new ModifyContentEvent($htmlContent));

        return $event->content;
    }
}
