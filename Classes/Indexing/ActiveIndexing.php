<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing;

use Lochmueller\Index\Configuration\Configuration;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Indexing\Record\RecordIndexingQueueInterface;

/**
 * Entry point for every index process that is started actively - by the queue command, by the
 * publishing dates command or by a DataHandler operation.
 */
class ActiveIndexing implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param iterable<IndexQueueInterface> $indexQueues
     */
    public function __construct(
        #[AutowireIterator('index.technology')]
        private readonly iterable $indexQueues,
        private readonly RecordIndexingQueueInterface $recordIndexingQueue,
    ) {}

    public function fillQueue(Configuration $configuration, bool $skipFiles = false): void
    {
        if (!$configuration->technology->isTriggeredByConfiguration()) {
            return;
        }

        foreach ($this->indexQueues as $indexQueue) {
            if ($indexQueue->getTechnology() === $configuration->technology) {
                $indexQueue->fillQueue($configuration, $skipFiles);

                return;
            }
        }

        $this->logger?->warning('Index configuration is skipped because no queue handles its technology', [
            'technology' => $configuration->technology->value,
            'configurationId' => $configuration->configurationId,
        ]);
    }

    /**
     * @param array<string, mixed>|null $recordBeforeChange
     * @param array<string, mixed>|null $recordAfterChange
     */
    public function queueRecordChange(
        string $table,
        ?array $recordBeforeChange,
        ?array $recordAfterChange,
        IndexPartialTrigger $trigger,
    ): void {
        $this->recordIndexingQueue->queueRecordChange($table, $recordBeforeChange, $recordAfterChange, $trigger);
    }
}
