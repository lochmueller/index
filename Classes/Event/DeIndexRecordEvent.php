<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final readonly class DeIndexRecordEvent
{
    public function __construct(
        public SiteInterface $site,
        public IndexTechnology $technology,
        public int $indexConfigurationRecordId,
        public string $indexProcessId,
        public string $table,
        public int $recordUid,
        public int $language,
    ) {}
}
