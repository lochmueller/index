<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

use Lochmueller\Index\Configuration\Configuration;

final readonly class RecordSourceResolver
{
    /**
     * @return list<RecordSource>
     */
    public function resolve(Configuration $configuration): array
    {
        $sources = $configuration->configuration['records'] ?? [];
        if (!is_array($sources) || ($sources !== [] && !array_is_list($sources))) {
            throw new \InvalidArgumentException('The "records" index configuration must be a list.');
        }

        $resolvedSources = [];
        $configuredTables = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new \InvalidArgumentException('Each entry in the "records" index configuration must be an object.');
            }

            $table = $source['table'] ?? null;
            $recordStorages = $source['recordStorages'] ?? null;
            if (!is_string($table) || $table === '' || !is_array($recordStorages) || $recordStorages === []) {
                throw new \InvalidArgumentException('Each record source requires a table and non-empty recordStorages list.');
            }
            if (isset($configuredTables[$table])) {
                throw new \InvalidArgumentException('Configure each record table only once in the "records" index configuration.');
            }
            $configuredTables[$table] = true;

            $storageIds = [];
            foreach ($recordStorages as $storage) {
                if (!is_int($storage) && (!is_string($storage) || !ctype_digit($storage))) {
                    throw new \InvalidArgumentException('Record storage identifiers must be integers.');
                }
                $storageIds[] = (int) $storage;
            }

            $fields = $source['fields'] ?? [];
            if (!is_array($fields) || ($fields !== [] && array_is_list($fields))) {
                throw new \InvalidArgumentException('Record source fields must be an object.');
            }

            $recordTypes = $source['recordTypes'] ?? [];
            if (!is_array($recordTypes) || ($recordTypes !== [] && !array_is_list($recordTypes))) {
                throw new \InvalidArgumentException('Record source recordTypes must be a list.');
            }
            $normalizedRecordTypes = [];
            foreach ($recordTypes as $recordType) {
                if (!is_string($recordType) && !is_int($recordType)) {
                    throw new \InvalidArgumentException('Record source recordTypes must contain strings or integers.');
                }
                $normalizedRecordTypes[] = (string) $recordType;
            }

            $constraints = $source['constraints'] ?? [];
            if (!is_array($constraints) || ($constraints !== [] && array_is_list($constraints))) {
                throw new \InvalidArgumentException('Record source constraints must be an object.');
            }
            foreach (array_keys($constraints) as $field) {
                if (!is_string($field)) {
                    throw new \InvalidArgumentException('Record source constraint fields must be strings.');
                }
            }

            $resolvedSources[] = new RecordSource(
                table: $table,
                recordStorages: $storageIds,
                fields: $fields,
                recordTypes: $normalizedRecordTypes,
                constraints: $constraints,
            );
        }

        return $resolvedSources;
    }

    public function findForTable(Configuration $configuration, string $table): ?RecordSource
    {
        foreach ($this->resolve($configuration) as $source) {
            if ($source->table === $table) {
                return $source;
            }
        }

        return null;
    }

    public function findForRecord(Configuration $configuration, string $table, int $pid): ?RecordSource
    {
        $source = $this->findForTable($configuration, $table);
        if ($source === null || !in_array($pid, $source->recordStorages, true)) {
            return null;
        }

        return $source;
    }
}
