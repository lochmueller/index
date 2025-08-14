<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Wrapper class around the Message bus to avoid https://forge.typo3.org/issues/101699 and create async events!
 */
class Bus
{
    public function __construct(
        protected readonly MessageBusInterface $bus,
    ) {}

    public function dispatch(object $message): void
    {
        $this->bus->dispatch(new Envelope($message)->with(new TransportNamesStamp(['index'])));
    }

}
