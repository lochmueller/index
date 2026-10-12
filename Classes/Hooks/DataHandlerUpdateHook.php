<?php

declare(strict_types=1);

namespace Lochmueller\Index\Hooks;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Indexing\ActiveIndexing;
use Lochmueller\Index\Queue\Bus;
use Lochmueller\Index\Queue\DeIndexDocumentMessageFactory;
use Lochmueller\Index\Queue\Message\DeIndexDocumentMessage;
use Lochmueller\Index\Utility\PageVisibility;
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
    /**
     * Documents of pages before a move, a delete or a slug change - the URIs are gone afterward.
     *
     * @var array<int, array{configurationId: int, documents: array<int, DeIndexDocumentMessage>}>
     */
    private array $documentsBeforeChange = [];

    public function __construct(
        protected ConfigurationLoader      $configurationLoader,
        protected ActiveIndexing           $activeIndexing,
        #[Autowire(service: 'cache.runtime')]
        private readonly FrontendInterface $cache,
        private readonly GenericRepository $genericRepository,
        private readonly Bus $bus,
        private readonly Context $context,
        private readonly DeIndexDocumentMessageFactory $deIndexDocumentMessageFactory,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !isset($incomingFieldArray['slug']) || !MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        // A slug change only affects the language of the record
        $this->rememberDocuments((int) $id, IndexPartialTrigger::Datamap, false);
    }

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
        if ($status === 'new') {
            $id = $dataHandler->substNEWwithIDs[$id] ?? $id;
        }
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        $record = $this->resolveLiveRecord($table, (int) $id);
        if (!$record) {
            return;
        }

        if ($table !== 'pages') {
            $this->triggerPartialIndexProcessForPage((int) $record['pid'], IndexPartialTrigger::Datamap);
            return;
        }

        [$pageUid, $languageId] = $this->getPageIdentity($record);
        $documentsBeforeChange = $this->takeDocumentsBeforeChange((int) $id);

        $configuration = $this->getConfigurationWithTrigger($pageUid, IndexPartialTrigger::Datamap);
        if ($configuration instanceof Configuration) {
            $removedLanguageId = $this->getRemovedLanguage($record, $configuration);
            if ($removedLanguageId !== false) {
                // Old URIs of a changed slug and the current URIs of the removed languages
                $this->dispatchDeIndex(array_merge(
                    array_values($documentsBeforeChange['documents'] ?? []),
                    array_values($this->deIndexDocumentMessageFactory->createForPage($pageUid, $removedLanguageId)),
                ));
                return;
            }

            if ($documentsBeforeChange !== null) {
                $this->deIndexChangedDocuments($documentsBeforeChange['documents'], $pageUid, $languageId);
            }
        }

        $this->triggerPartialIndexProcessForPage($pageUid, IndexPartialTrigger::Datamap);
    }

    public function processCmdmap_preProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        DataHandler $dataHandler,
        mixed $pasteUpdate,
    ): void {
        if ($table !== 'pages' || !in_array($command, ['move', 'delete'], true) || !MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        // Moving or deleting a page in the default language affects all translations
        $this->rememberDocuments((int) $id, IndexPartialTrigger::Cmdmap, true);
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
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        $record = $this->resolveLiveRecord($table, (int) $id);

        if ($table === 'pages') {
            $documentsBeforeChange = $this->takeDocumentsBeforeChange((int) $id);
            if (!$record) {
                // The page is deleted
                if ($documentsBeforeChange !== null) {
                    $this->dispatchDeIndex($documentsBeforeChange['documents']);
                }
                return;
            }

            [$pageUid, $languageId] = $this->getPageIdentity($record);
            if ($documentsBeforeChange !== null) {
                $configuration = $this->configurationLoader->loadByPageTraversing($pageUid);
                if (!$configuration instanceof Configuration || $configuration->configurationId !== $documentsBeforeChange['configurationId']) {
                    // Moved out of the indexed tree
                    $this->dispatchDeIndex($documentsBeforeChange['documents']);
                } else {
                    $this->deIndexChangedDocuments($documentsBeforeChange['documents'], $pageUid, $languageId === 0 ? null : $languageId);
                }
            }

            $this->triggerPartialIndexProcessForPage($pageUid, IndexPartialTrigger::Cmdmap);
            return;
        }

        if (!$record && $command === 'delete') {
            // The page of a deleted record has to be indexed again without the record
            $record = $this->genericRepository->setTableName($table)->findByUid((int) $id, true);
        }
        if ($record) {
            $this->triggerPartialIndexProcessForPage((int) $record['pid'], IndexPartialTrigger::Cmdmap);
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
     * Checks if the page record is no longer part of the index.
     *
     * @param array<string, mixed> $record
     * @return int|false|null The language that is removed, null for all languages, false if nothing is removed
     */
    private function getRemovedLanguage(array $record, Configuration $configuration): int|false|null
    {
        [, $languageId] = $this->getPageIdentity($record);

        if (!PageVisibility::isRenderable($record, time())) {
            // Translations are not rendered without the page in the default language
            return $languageId === 0 ? null : $languageId;
        }

        if ($configuration->skipNoSearchPages && ($record['no_search'] ?? false)) {
            return $languageId;
        }

        return false;
    }

    private function rememberDocuments(int $uid, IndexPartialTrigger $trigger, bool $allLanguages): void
    {
        $record = $this->resolveLiveRecord('pages', $uid);
        if (!$record) {
            return;
        }

        [$pageUid, $languageId] = $this->getPageIdentity($record);
        $configuration = $this->getConfigurationWithTrigger($pageUid, $trigger);
        if (!$configuration instanceof Configuration) {
            return;
        }

        $this->documentsBeforeChange[$uid] = [
            'configurationId' => $configuration->configurationId,
            'documents' => $this->deIndexDocumentMessageFactory->createForPage($pageUid, $allLanguages && $languageId === 0 ? null : $languageId),
        ];
    }

    /**
     * @return array{configurationId: int, documents: array<int, DeIndexDocumentMessage>}|null
     */
    private function takeDocumentsBeforeChange(int $uid): ?array
    {
        $documents = $this->documentsBeforeChange[$uid] ?? null;
        unset($this->documentsBeforeChange[$uid]);

        return $documents;
    }

    /**
     * De-index the documents whose URI changed (e.g. a new slug or a new parent page).
     *
     * @param array<int, DeIndexDocumentMessage> $documentsBeforeChange
     */
    private function deIndexChangedDocuments(array $documentsBeforeChange, int $pageUid, ?int $languageId): void
    {
        $currentDocuments = $this->deIndexDocumentMessageFactory->createForPage($pageUid, $languageId);
        $this->dispatchDeIndex(array_filter(
            $documentsBeforeChange,
            static fn(DeIndexDocumentMessage $document): bool => ($currentDocuments[$document->languageId] ?? null)?->uri !== $document->uri,
        ));
    }

    /**
     * @param array<int, DeIndexDocumentMessage> $documents
     */
    private function dispatchDeIndex(array $documents): void
    {
        $alreadyDeIndexed = (array) $this->cache->get('index-already-deindexed');
        foreach ($documents as $document) {
            if (in_array($document->uri, $alreadyDeIndexed, true)) {
                continue;
            }
            $alreadyDeIndexed[] = $document->uri;
            $this->bus->dispatch($document);
        }
        $this->cache->set('index-already-deindexed', $alreadyDeIndexed);
    }

    /**
     * Uid of the page in the default language and the language of the record.
     *
     * @param array<string, mixed> $record
     * @return array{int, int}
     */
    private function getPageIdentity(array $record): array
    {
        $languageId = (int) ($record['sys_language_uid'] ?? 0);
        $pageUid = $languageId > 0 && (int) ($record['l10n_parent'] ?? 0) > 0 ? (int) $record['l10n_parent'] : (int) $record['uid'];

        return [$pageUid, $languageId];
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

    private function getConfigurationWithTrigger(int $pageId, IndexPartialTrigger $trigger): ?Configuration
    {
        if ($pageId === 0) {
            return null;
        }

        $configuration = $this->configurationLoader->loadByPageTraversing($pageId);
        if (!$configuration instanceof Configuration || !in_array($trigger->value, $configuration->partialIndexing, true)) {
            return null;
        }

        return $configuration;
    }

    private function getTriggerableConfiguration(int $pageId, IndexPartialTrigger $trigger): ?Configuration
    {
        $configuration = $this->getConfigurationWithTrigger($pageId, $trigger);
        if (!$configuration instanceof Configuration) {
            return null;
        }

        $alreadyTriggered = (array) $this->cache->get('index-already-triggered');
        if (in_array($pageId, $alreadyTriggered, true)) {
            return null;
        }

        $alreadyTriggered[] = $pageId;
        $this->cache->set('index-already-triggered', $alreadyTriggered);

        return $configuration;
    }

}
