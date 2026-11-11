<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final class IndexRecordEvent
{
    /**
     * @param array<string, array{
     *     source: string,
     *     type: string,
     *     multiple: bool,
     *     searchable: bool,
     *     filterable: bool,
     *     sortable: bool,
     *     distinct: bool,
     *     facet: bool,
     *     options: array<string, mixed>
     * }> $fieldDefinitions
     * @param array<string, mixed> $fields Configured record values before SEAL type normalization
     */
    public function __construct(
        public readonly SiteInterface $site,
        public readonly IndexTechnology $technology,
        public readonly IndexType $type,
        public readonly int $indexConfigurationRecordId,
        public readonly string $indexProcessId,
        public readonly string $table,
        public readonly int $recordUid,
        public readonly int $language,
        public readonly ?string $recordType,
        public readonly Record $record,
        public readonly TcaSchema $tcaSchema,
        public readonly array $fieldDefinitions,
        public array $fields,
    ) {}
}
