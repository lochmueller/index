<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

class LogIndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[AsEventListener('index-log-debug-helper')]
    public function __invoke(StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event): void
    {
        $this->logger->debug('Execute ' . get_class($event), [
            'site' => $event->site->getIdentifier(),
            'technology' => $event->technology->value,
        ]);
    }
}
