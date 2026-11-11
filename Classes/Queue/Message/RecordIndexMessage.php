<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final readonly class RecordIndexMessage
{
    public function __construct(
        public string $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType $type,
        public int $indexConfigurationRecordId,
        public string $indexProcessId,
        public string $table,
        public int $recordUid,
        public int $language,
    ) {}
}
