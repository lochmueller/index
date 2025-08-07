<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Web;

use Lochmueller\Index\Queue\Message\WebIndexMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class WebIndexing
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function fillQueueForWebIndex(SiteInterface $site): void
    {
        // @todo handle the message
        $message = new WebIndexMessage();

        // Send the message async via doctrine transport
        // @todo check https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.4.x/Important-103140-AllowToConfigureRateLimiters.html
        $this->bus->dispatch((new Envelope($message))->with(new TransportNamesStamp('doctrine')));
    }

    public function handleMessage(WebIndexMessage $message): void
    {
        // DebuggerUtility::var_dump($message);

        // @todo handle message
        // @todo Execute webrequest and index content
    }

}
