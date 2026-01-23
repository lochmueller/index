<?php

declare(strict_types=1);

namespace Lochmueller\Index\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

#[Autoconfigure(public: true)]
class DataHandlerUpdateHook
{
    public function __construct(
        protected ConfigurationLoader      $configurationLoader,
        protected ActiveIndexing           $activeIndexing,
        #[Autowire(service: 'cache.runtime')]
        private readonly FrontendInterface $cache,
    ) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            $record = BackendUtility::getRecord($table, $id);
            if ($record) {
                $field = $table === 'pages' ? 'uid' : 'pid';
                $this->triggerPartialIndexProcessForPage((int) $record[$field], IndexPartialTrigger::Datamap);
            }
        }
    }

    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        DataHandler $dataHandler,
        mixed $pasteUpdate,
        mixed $pasteDatamap,
    ): void {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            $record = BackendUtility::getRecord($table, $id);
            if ($record) {
                $field = $table === 'pages' ? 'uid' : 'pid';
                $this->triggerPartialIndexProcessForPage((int) $record[$field], IndexPartialTrigger::Cmdmap);
            }

        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function clearCacheCmd(array $params, DataHandler $dataHandler): void
    {
        $pageIds = $params['pageIdArray'] ?? [];
        foreach ($pageIds as $pageId) {
            $this->triggerPartialIndexProcessForPage((int) $pageId, IndexPartialTrigger::Clearcache);
        }
    }

    protected function triggerPartialIndexProcessForPage(int $pageId, IndexPartialTrigger $trigger): void
    {
        $alreadyTriggered = (array) $this->cache->get('index-already-triggered');
        $configuration = $this->configurationLoader->loadByPageTraversing($pageId);
        if (!$configuration instanceof Configuration || !in_array($trigger->value, $configuration->partialIndexing, true)) {
            return;
        }

        if (in_array($pageId, $alreadyTriggered, true)) {
            return;
        }
        $alreadyTriggered[] = $pageId;
        $this->cache->set('index-already-triggered', $alreadyTriggered);
        if ($pageId === 0) {
            return;
        }

        $this->activeIndexing->fillQueue($configuration->modifyForPartialIndexing($pageId), true);
    }

}
