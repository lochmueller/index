<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final readonly class IndexRecordReadyEvent
{
    /**
     * @param array<string, mixed> $fields Type-normalized record values
     */
    public function __construct(
        public SiteInterface $site,
        public IndexTechnology $technology,
        public IndexType $type,
        public int $indexConfigurationRecordId,
        public string $indexProcessId,
        public string $table,
        public int $recordUid,
        public int $language,
        public array $fields,
    ) {}
}
