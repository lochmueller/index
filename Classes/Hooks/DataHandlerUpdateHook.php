<?php

declare(strict_types=1);

namespace Lochmueller\Index\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
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
        private readonly GenericRepository $genericRepository,
        private readonly Bus $bus,
        private readonly Context $context,
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
            $record = $this->resolveLiveRecord($table, (int) $id);
            if ($record) {
                $field = $table === 'pages' ? 'uid' : 'pid';
                if (isset($record['no_search']) && $record['no_search']) {
                    $this->triggerDocumentDeleteForPage((int) $record['uid'], (int) $record['sys_language_uid'], IndexPartialTrigger::Datamap);
                    return;
                }

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
            $record = $this->resolveLiveRecord($table, (int) $id);
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

    protected function triggerDocumentDeleteForPage(int $pageId, int $languageId, IndexPartialTrigger $trigger): void
    {
        if ($pageId === 0) {
            return;
        }

        if (!($configuration = $this->getTriggerableConfiguration($pageId, $trigger)) || !$configuration->skipNoSearchPages) {
            return;
        }

        $this->bus->dispatch(new DeIndexDocumentMessage($pageId, $languageId));
    }

    protected function triggerPartialIndexProcessForPage(int $pageId, IndexPartialTrigger $trigger): void
    {
        if ($pageId === 0) {
            return;
        }

        if (!$configuration = $this->getTriggerableConfiguration($pageId, $trigger)) {
            return;
        }

        $this->runInLiveWorkspace(
            fn() => $this->activeIndexing->fillQueue($configuration->modifyForPartialIndexing($pageId), true)
        );
    }

    /**
     * Resolve the record the frontend actually renders.
     *
     * The DataHandler calls these hooks for workspace versions too. Their uid is
     * the version's own, so it is mapped back to the live record the version
     * belongs to. A draft that has no live counterpart yet is skipped: it is not
     * part of the live site, and looking it up would fail - SiteFinder finds no
     * site in its root line.
     *
     * @return array<string, mixed>|null
     */
    private function resolveLiveRecord(string $table, int $uid): ?array
    {
        $record = $this->genericRepository->setTableName($table)->findByUid($uid);
        if (!$record) {
            return null;
        }

        if ((int) ($record['t3ver_wsid'] ?? 0) === 0) {
            return $record;
        }

        $liveUid = (int) ($record['t3ver_oid'] ?? 0);
        if ($liveUid === 0) {
            return null;
        }

        return $this->genericRepository->setTableName($table)->findByUid($liveUid) ?: null;
    }

    /**
     * Index the live tree, even when the editor who triggered the hook works in
     * a workspace.
     *
     * Only live records are ever rendered, so a workspace has nothing to
     * describe for the index. It also breaks: with a workspace aspect the page
     * lookup resolves version records, and for a page whose translation has no
     * live counterpart yet PageRepository::checkIfPageIsHidden() passes `false`
     * into getWorkspaceVersionOfRecord() and fails with a TypeError - which
     * aborts the DataHandler operation that triggered the hook.
     */
    private function runInLiveWorkspace(callable $callback): void
    {
        $workspaceAspect = $this->context->getAspect('workspace');
        if ($workspaceAspect->getId() === 0) {
            $callback();

            return;
        }

        $this->context->setAspect('workspace', new WorkspaceAspect(0));
        try {
            $callback();
        } finally {
            $this->context->setAspect('workspace', $workspaceAspect);
        }
    }

    private function getTriggerableConfiguration(int $pageId, IndexPartialTrigger $trigger): ?Configuration
    {
        $alreadyTriggered = (array) $this->cache->get('index-already-triggered');
        $configuration = $this->configurationLoader->loadByPageTraversing($pageId);

        if (!$configuration instanceof Configuration || !in_array($trigger->value, $configuration->partialIndexing, true)) {
            return null;
        }

        if (in_array($pageId, $alreadyTriggered, true)) {
            return null;
        }

        $alreadyTriggered[] = $pageId;
        $this->cache->set('index-already-triggered', $alreadyTriggered);

        return $configuration;
    }

}
