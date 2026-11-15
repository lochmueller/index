<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Configuration\ConfigurationLoader;
use Lochmueller\Index\Event\DeIndexRecordEvent;
use Lochmueller\Index\Event\IndexRecordEvent;
use Lochmueller\Index\Event\IndexRecordReadyEvent;
use Lochmueller\Index\Indexing\Field\Value\FieldValueResolver;
use Lochmueller\Index\Queue\Message\RecordIndexMessage;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

final class RecordIndexingHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly ConfigurationLoader $configurationLoader,
        private readonly RecordSelection $recordSelection,
        private readonly RecordFieldDefinitionResolver $fieldDefinitionResolver,
        private readonly RecordSourceResolver $recordSourceResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FieldValueResolver $fieldValueResolver,
    ) {}

    #[AsMessageHandler]
    public function __invoke(RecordIndexMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);
            $configuration = $this->configurationLoader->loadByUid($message->indexConfigurationRecordId);
            if (!$configuration instanceof Configuration) {
                $this->logger?->warning('Record indexing configuration no longer exists', [
                    'configurationId' => $message->indexConfigurationRecordId,
                ]);
                $this->dispatchDeIndex($message, $site);
                return;
            }

            if (
                !$configuration->technology->supportsRecordIndexing()
                || $configuration->technology !== $message->technology
            ) {
                $this->dispatchDeIndex($message, $site);
                return;
            }

            $source = $this->recordSourceResolver->findForTable($configuration, $message->table);
            $recordRow = $this->recordSelection->findVisibleRecordDataByUid($message->table, $message->recordUid);
            $record = $recordRow === null ? null : $this->recordSelection->mapRecord($message->table, $recordRow);
            if ($source === null || $recordRow === null || $record === null || !$this->isEligible($record, $source)) {
                $this->dispatchDeIndex($message, $site);
                return;
            }

            if ($this->recordSelection->getRecordLanguageId($message->table, $recordRow) !== $message->language) {
                $this->dispatchDeIndex($message, $site);
                return;
            }

            $tcaSchema = $this->fieldDefinitionResolver->getTcaSchema($message->table);
            $definitions = $this->fieldDefinitionResolver->resolve($message->table, $source->fields, $tcaSchema);
            if (!isset($definitions['title'])) {
                throw new \UnexpectedValueException('Record source "' . $message->table . '" must configure a "title" search field.');
            }

            $recordEvent = new IndexRecordEvent(
                site: $site,
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                table: $message->table,
                recordUid: $message->recordUid,
                language: $message->language,
                recordType: $record->getRecordType(),
                record: $record,
                tcaSchema: $tcaSchema,
                fieldDefinitions: $definitions,
                fields: $this->fieldValueResolver->resolve(
                    $record,
                    $tcaSchema,
                    $source->fields,
                    $definitions,
                    $this->fieldDefinitionResolver->extractValues($recordRow, $definitions),
                    $site,
                    $message->language,
                ),
            );
            $this->eventDispatcher->dispatch($recordEvent);

            $fields = $this->fieldDefinitionResolver->normalizeValues($recordEvent->fields, $definitions);
            if (!is_string($fields['title'] ?? null)) {
                throw new \UnexpectedValueException('Record source "' . $message->table . '" must resolve a text "title" field.');
            }

            $this->eventDispatcher->dispatch(new IndexRecordReadyEvent(
                site: $site,
                technology: $message->technology,
                type: $message->type,
                indexConfigurationRecordId: $message->indexConfigurationRecordId,
                indexProcessId: $message->indexProcessId,
                table: $message->table,
                recordUid: $message->recordUid,
                language: $message->language,
                fields: $fields,
            ));
        } catch (\Throwable $exception) {
            $this->logger?->error($exception->getMessage(), [
                'exception' => $exception,
                'table' => $message->table,
                'recordUid' => $message->recordUid,
            ]);
            throw $exception;
        }
    }

    private function dispatchDeIndex(RecordIndexMessage $message, SiteInterface $site): void
    {
        $this->eventDispatcher->dispatch(new DeIndexRecordEvent(
            site: $site,
            technology: $message->technology,
            indexConfigurationRecordId: $message->indexConfigurationRecordId,
            indexProcessId: $message->indexProcessId,
            table: $message->table,
            recordUid: $message->recordUid,
            language: $message->language,
        ));
    }

    private function isEligible(Record $record, RecordSource $source): bool
    {
        if (!in_array($record->getPid(), $source->recordStorages, true)) {
            return false;
        }

        if ($source->recordTypes !== []) {
            $recordType = $record->getRecordType();
            if ($recordType === null || !in_array($recordType, $source->recordTypes, true)) {
                return false;
            }
        }

        foreach ($source->constraints as $field => $expected) {
            if (!$record->has($field) || !$this->matchesConstraint($record->get($field), $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matchesConstraint(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected)) {
            foreach ($expected as $expectedItem) {
                if ($this->matchesConstraint($actual, $expectedItem)) {
                    return true;
                }
            }
            return false;
        }

        if (is_array($actual)) {
            foreach ($actual as $actualItem) {
                if ($this->matchesConstraint($actualItem, $expected)) {
                    return true;
                }
            }
            return false;
        }

        if ($actual === null || $expected === null || is_object($actual) || is_object($expected)) {
            return false;
        }

        return (string) $actual === (string) $expected;
    }
}
