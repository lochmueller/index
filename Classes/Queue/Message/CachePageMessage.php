<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;

final readonly class CachePageMessage
{
    /**
     * @param int[] $accessGroups
     */
    public function __construct(
        /** Meta information */
        public string          $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        /** Content data */
        public int             $language,
        public string          $title,
        public string          $content,
        public int             $pageUid,
        public array           $accessGroups,
        public string          $indexProcessId,
    ) {}

}
