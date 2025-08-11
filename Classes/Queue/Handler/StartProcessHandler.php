<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Handler;

use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class StartProcessHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private SiteFinder               $siteFinder,
    ) {}

    #[AsMessageHandler]
    public function __invoke(StartProcessMessage $message): void
    {
        $this->eventDispatcher->dispatch(new StartIndexProcessEvent(
            site: $this->siteFinder->getSiteByIdentifier($message->siteIdentifier),
            technology: $message->technology,
            type: $message->type,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            startTime: microtime(true),
        ));
    }
}
