<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final class FinishProcessMessage
{
    public function __construct(
        /** Meta information */
        public string   $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        public string          $indexProcessId,
    ) {}

}
