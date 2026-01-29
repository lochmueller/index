<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Handler;

use Lochmueller\Index\Event\StartIndexProcessEvent;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Site\SiteFinder;

final class StartProcessHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SiteFinder               $siteFinder,
    ) {}

    #[AsMessageHandler]
    public function __invoke(StartProcessMessage $message): void
    {
        try {
            $this->eventDispatcher->dispatch(new StartIndexProcessEvent(
                site: $this->siteFinder->getSiteByIdentifier($message->siteIdentifier),
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                startTime: microtime(true),
            ));
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
