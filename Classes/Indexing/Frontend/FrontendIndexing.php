<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Frontend;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class FrontendIndexing implements IndexingInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function fillQueue(Configuration $configuration): void
    {
        // @todo handle the message
        #$message = new FrontendIndexMessage();

        // Send the message async via doctrine transport
        // @todo check https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.4.x/Important-103140-AllowToConfigureRateLimiters.html
        #$this->bus->dispatch((new Envelope($message))->with(new TransportNamesStamp('doctrine')));
    }

    public function handleMessage(FrontendIndexMessage $message): void
    {
        // DebuggerUtility::var_dump($message);

        // @todo handle message
        // @todo Execute webrequest and index content
    }

}
