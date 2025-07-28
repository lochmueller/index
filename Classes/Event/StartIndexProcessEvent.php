<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Event;

use Lochmueller\Indexing\Enums\IndexTechnology;
use Lochmueller\Indexing\Enums\IndexType;

final class StartIndexProcessEvent
{
    public function __construct(
        public IndexTechnology $technology,
        public IndexType       $type,
        public string          $indexProcessId,
        public float           $startTime,
    ) {}

}
