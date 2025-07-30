<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final class StartIndexProcessEvent
{
    public function __construct(
        public IndexTechnology $technology,
        public IndexType       $type,
        public string          $indexProcessId,
        public float           $startTime,
    ) {}

}
