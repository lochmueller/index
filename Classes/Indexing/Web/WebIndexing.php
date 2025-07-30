<?php

declare(strict_types=1);

namespace Lochmueller\Index\Index\Web;

use Lochmueller\Index\Queue\Message\WebIndexMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class WebIndex
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function fillQueueForWebIndex(SiteInterface $site): void
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
