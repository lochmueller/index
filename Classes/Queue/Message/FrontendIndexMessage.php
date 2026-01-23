<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Psr\Http\Message\UriInterface;

final readonly class FrontendIndexMessage
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
        public UriInterface    $uri,
        public int             $pageUid,
        public string          $indexProcessId,
        public array           $accessGroups = [],
    ) {}
}
