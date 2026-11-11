<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

interface RecordIndexingQueueInterface
{
    public function queueConfiguredRecords(
        Configuration $configuration,
        SiteInterface $site,
        string $indexProcessId,
        IndexType $indexType,
    ): void;

    /**
     * @param array<string, mixed>|null $recordBeforeChange
     * @param array<string, mixed>|null $recordAfterChange
     */
    public function queueRecordChange(
        string $table,
        ?array $recordBeforeChange,
        ?array $recordAfterChange,
        IndexPartialTrigger $trigger,
    ): void;
}
