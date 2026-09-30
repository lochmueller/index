<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final readonly class FetchIndexMessage
{
    public function __construct(
        /** Meta information */
        public string          $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        public string          $indexProcessId,
        public int             $language,
        /** Fetch information */
        public string          $uri,
        public int             $depth,
        public bool            $skipFiles = false,
    ) {}
}
