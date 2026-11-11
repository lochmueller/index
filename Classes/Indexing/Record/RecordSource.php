<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Record;

final readonly class RecordSource
{
    /**
     * @param list<int> $recordStorages
     * @param array<string|int, mixed> $fields
     * @param list<string> $recordTypes
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        public string $table,
        public array $recordStorages,
        public array $fields,
        public array $recordTypes,
        public array $constraints,
    ) {}
}
