<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Indexing\Web;

use Lochmueller\Indexing\Queue\Message\WebIndexMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class WebIndexing
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function fillQueueForWebIndexing(SiteInterface $site): void
    {
        // @todo handle the message
        $message = new WebIndexMessage();

        // Send the message async via doctrine transport
        $this->bus->dispatch((new Envelope($message))->with(new TransportNamesStamp('doctrine')));
    }

    public function handleMessage(WebIndexMessage $message): void
    {
        // DebuggerUtility::var_dump($message);

        // @todo handle message
        // @todo Execute webrequest and index content
    }

}
