<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final readonly class ExternalFileIndexMessage
{
    public function __construct(
        /** Meta information */
        public string          $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType       $type,
        public string $uri,
        public string $title,
        public string $content,
        public string $indexProcessId,
    ) {}

}
