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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LogIndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[AsEventListener('index-log-helper')]
    public function __invoke(StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event): void
    {
        // System logger
        $this->logger->debug('Execute ' . get_class($event), [
            'site' => isset($event->site) ? $event->site->getIdentifier() : null,
            'technology' => isset($event->technology) ? $event->technology->value : null,
        ]);

        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_index_domain_model_log');

        $qb = $connection->createQueryBuilder();
        $record = $qb->select('*')
            ->from('tx_index_domain_model_log')
            ->where($qb->expr()->eq('index_process_id', $qb->expr()->literal($event->indexProcessId)))
            ->executeQuery()
            ->fetchAssociative();
        $newRecord = $record === false;

        if ($record === false) {
            $record = [
                'index_process_id' => $event->indexProcessId,
            ];
        }
        if ($event instanceof StartIndexProcessEvent) {
            $record['start_time'] = (int) $event->startTime;
        } elseif ($event instanceof FinishIndexProcessEvent) {
            $record['end_time'] = (int) $event->endTime;
        } elseif ($event instanceof IndexPageEvent) {
            $record['pages_counter'] = ((int) $record['pages_counter']) + 1;
        } elseif ($event instanceof IndexFileEvent) {
            $record['files_counter'] = ((int) $record['files_counter']) + 1;
        }

        if ($newRecord) {
            $connection->insert('tx_index_domain_model_log', $record);
        } else {
            $connection->update('tx_index_domain_model_log', $record, ['index_process_id' => $event->indexProcessId]);
        }
    }
}
