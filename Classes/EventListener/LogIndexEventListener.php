<?php

declare(strict_types=1);

namespace Lochmueller\Index\EventListener;

use Lochmueller\Index\Domain\Repository\LogRepository;
use Lochmueller\Index\Event\FinishIndexProcessEvent;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Event\StartIndexProcessEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class LogIndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly LogRepository $logRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    #[AsEventListener('index-log-helper')]
    public function __invoke(StartIndexProcessEvent|IndexPageEvent|IndexFileEvent|FinishIndexProcessEvent $event): void
    {
        // System logger
        $this->logger?->debug('Execute ' . $event::class, [
            'site' => isset($event->site) ? $event->site->getIdentifier() : null,
            'technology' => isset($event->technology) ? $event->technology->value : null,
            'uri' => $event->uri ?? null,
        ]);

        $record = $this->logRepository->findByIndexProcessId($event->indexProcessId);
        $newRecord = $record === null;

        if ($newRecord) {
            $record = [
                'index_process_id' => $event->indexProcessId,
            ];
        }
        if ($event instanceof StartIndexProcessEvent) {
            $record['start_time'] = (int) $event->startTime;
        } elseif ($event instanceof FinishIndexProcessEvent) {
            $record['end_time'] = (int) $event->endTime;
            $this->deleteOldEntries();
        } elseif ($event instanceof IndexPageEvent) {
            $record['pages_counter'] = ((int) $record['pages_counter']) + 1;
        } elseif ($event instanceof IndexFileEvent) {
            $record['files_counter'] = ((int) $record['files_counter']) + 1;
        }

        if ($newRecord) {
            $this->logRepository->insert($record);
        } else {
            $this->logRepository->update($record, ['index_process_id' => $event->indexProcessId]);
        }
    }

    protected function deleteOldEntries(): void
    {
        $extensionConfiguration = $this->extensionConfiguration->get('index');
        $keepIndexLogEntriesDays = isset($extensionConfiguration['keepIndexLogEntriesDays']) ? (int) $extensionConfiguration['keepIndexLogEntriesDays'] : 14;
        if ($keepIndexLogEntriesDays) {
            $this->logRepository->deleteOlderThan(time() - $keepIndexLogEntriesDays * 86400);
        }
    }
}
