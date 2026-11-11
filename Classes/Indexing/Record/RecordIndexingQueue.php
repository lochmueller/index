<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\RecordIndexMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class RecordIndexingQueue implements RecordIndexingQueueInterface
{
    public function __construct(
        private Bus $bus,
        private ConfigurationLoader $configurationLoader,
        private RecordSelection $recordSelection,
        private RecordSourceResolver $recordSourceResolver,
        private SiteFinder $siteFinder,
    ) {}

    public function queueConfiguredRecords(
        Configuration $configuration,
        SiteInterface $site,
        string $indexProcessId,
        IndexType $indexType,
    ): void {
        if (!$configuration->technology->supportsRecordIndexing() || $indexType !== IndexType::Full) {
            return;
        }

        foreach ($this->recordSourceResolver->resolve($configuration) as $source) {
            foreach ($this->recordSelection->findRawRecordsOnStorages($source->table, $source->recordStorages) as $row) {
                $recordUid = filter_var($row['uid'] ?? null, FILTER_VALIDATE_INT);
                if ($recordUid === false || $recordUid < 1) {
                    throw new \UnexpectedValueException('A record selected for indexing has no valid UID.');
                }

                $this->dispatchRecord(
                    $configuration,
                    $site->getIdentifier(),
                    $indexProcessId,
                    $indexType,
                    $source->table,
                    $recordUid,
                    $this->recordSelection->getRecordLanguageId($source->table, $row),
                );
            }
        }
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
        /** @var array<int, array{
         *     configuration: Configuration,
         *     site: SiteInterface,
         *     recordUid: int,
         *     languages: array<int, int>
         * }> $targets
         */
        $targets = [];
        foreach ([$recordBeforeChange, $recordAfterChange] as $record) {
            if ($record === null) {
                continue;
            }

            $pid = filter_var($record['pid'] ?? null, FILTER_VALIDATE_INT);
            $recordUid = filter_var($record['uid'] ?? null, FILTER_VALIDATE_INT);
            if ($pid === false || $pid < 1 || $recordUid === false || $recordUid < 1) {
                continue;
            }

            try {
                $site = $this->siteFinder->getSiteByPageId($pid);
            } catch (SiteNotFoundException) {
                continue;
            }

            foreach ($this->configurationLoader->loadAllBySite($site) as $configuration) {
                if (
                    !$configuration->technology->supportsRecordIndexing()
                    || !in_array($trigger->value, $configuration->partialIndexing, true)
                    || $this->recordSourceResolver->findForRecord($configuration, $table, $pid) === null
                ) {
                    continue;
                }

                $configurationId = $configuration->configurationId;
                if (!isset($targets[$configurationId])) {
                    $targets[$configurationId] = [
                        'configuration' => $configuration,
                        'site' => $site,
                        'recordUid' => $recordUid,
                        'languages' => [],
                    ];
                }
                $language = $this->recordSelection->getRecordLanguageId($table, $record);
                $targets[$configurationId]['languages'][$language] = $language;
            }
        }

        foreach ($targets as $target) {
            /** @var Configuration $configuration */
            $configuration = $target['configuration'];
            /** @var SiteInterface $site */
            $site = $target['site'];
            $indexProcessId = uniqid('record-index', true);
            $indexType = IndexType::Partial;
            $this->bus->dispatch(new StartProcessMessage(
                siteIdentifier: $site->getIdentifier(),
                technology: $configuration->technology,
                type: $indexType,
                indexConfigurationRecordId: $configuration->configurationId,
                indexProcessId: $indexProcessId,
            ));

            foreach ($target['languages'] as $language) {
                $this->dispatchRecord(
                    $configuration,
                    $site->getIdentifier(),
                    $indexProcessId,
                    $indexType,
                    $table,
                    $target['recordUid'],
                    $language,
                );
            }

            $this->bus->dispatch(new FinishProcessMessage(
                siteIdentifier: $site->getIdentifier(),
                technology: $configuration->technology,
                type: $indexType,
                indexConfigurationRecordId: $configuration->configurationId,
                indexProcessId: $indexProcessId,
            ));
        }
    }

    private function dispatchRecord(
        Configuration $configuration,
        string $siteIdentifier,
        string $indexProcessId,
        IndexType $indexType,
        string $table,
        int $recordUid,
        int $language,
    ): void {
        $this->bus->dispatch(new RecordIndexMessage(
            siteIdentifier: $siteIdentifier,
            technology: $configuration->technology,
            type: $indexType,
            indexConfigurationRecordId: $configuration->configurationId,
            indexProcessId: $indexProcessId,
            table: $table,
            recordUid: $recordUid,
            language: $language,
        ));
    }
}
