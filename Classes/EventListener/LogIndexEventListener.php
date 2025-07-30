<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Event\EndIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

class LogIndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[AsEventListener('index-cache-indexer')]
    public function __invoke(StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|EndIndexProcessEvent $event): void
    {
        $this->logger->debug('Execute ' . get_class($event), ['event' => $event]);
    }
}
