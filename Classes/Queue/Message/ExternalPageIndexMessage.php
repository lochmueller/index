<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final readonly class ExternalPageIndexMessage
{
    /**
     * @param int[] $accessGroups
     */
    public function __construct(
        /** Meta information */
        public string          $siteIdentifier,
        public int             $language,
        public IndexTechnology $technology,
        public IndexType       $type,
        public string          $uri,
        public string          $title,
        public string          $content,
        public string          $indexProcessId,
        public array           $accessGroups = [],
    ) {}

}
