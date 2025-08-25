<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Wrapper class around the Message bus to avoid https://forge.typo3.org/issues/101699 and create async events!
 */
readonly class Bus
{
    public function __construct(
        protected MessageBusInterface $bus,
    ) {}

    public function dispatch(object $message): void
    {
        $transport = $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*'] ?? null;
        if ($transport) {
            $this->bus->dispatch((new Envelope($message))->with(new TransportNamesStamp([$transport])));
        } else {
            $this->bus->dispatch($message);
        }
    }

}
