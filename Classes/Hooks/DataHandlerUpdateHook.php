<?php

declare(strict_types=1);

namespace Lochmueller\Index\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingQueue;
use Lochmueller\Index\Indexing\Frontend\FrontendIndexingQueue;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

#[Autoconfigure(public: true)]
class DataHandlerUpdateHook
{
    public function __construct(
        protected ConfigurationLoader   $configurationLoader,
        protected DatabaseIndexingQueue $databaseIndexingQueue,
        protected FrontendIndexingQueue $frontendIndexingQueue,
    ) {}

    public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, DataHandler $dataHandler): void
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {

            $record = BackendUtility::getRecord($table, $id);
            if ($record) {
                $field = $table === 'pages' ? 'uid' : 'pid';
                $this->triggerPartialIndexProcessForPage((int) $record[$field], IndexPartialTrigger::Datamap);
            }
        }
    }

    public function processCmdmap_postProcess($command, $table, $id, $value, DataHandler $dataHandler, $pasteUpdate, $pasteDatamap): void
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            $record = BackendUtility::getRecord($table, $id);
            if ($record) {
                $field = $table === 'pages' ? 'uid' : 'pid';
                $this->triggerPartialIndexProcessForPage((int) $record[$field], IndexPartialTrigger::Cmdmap);
            }

        }
    }

    public function clearCacheCmd($params, DataHandler $dataHandler): void
    {

        $pageIds = $params['pageIdArray'] ?? [];
        foreach ($pageIds as $pageId) {
            $this->triggerPartialIndexProcessForPage((int) $pageId, IndexPartialTrigger::Clearcache);
        }
    }

    protected function triggerPartialIndexProcessForPage(int $pageId, IndexPartialTrigger $trigger): void
    {
        static $alreadyTriggered = [];

        $configuration = $this->configurationLoader->loadByPageTraversing($pageId);
        if (!$configuration instanceof Configuration || !in_array($trigger->value, $configuration->partialIndexing, true)) {
            return;
        }

        if (in_array($pageId, $alreadyTriggered, true)) {
            return;
        }
        $alreadyTriggered[] = $pageId;
        if ($pageId === 0) {
            return;
        }

        if ($configuration->technology === IndexTechnology::Database) {
            $this->databaseIndexingQueue->fillQueue($configuration->modifyForPartialIndexing($pageId), true);
        } elseif ($configuration->technology === IndexTechnology::Frontend) {
            $this->frontendIndexingQueue->fillQueue($configuration->modifyForPartialIndexing($pageId), true);
        }
    }

}
