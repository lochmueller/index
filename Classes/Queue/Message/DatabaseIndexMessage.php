<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Http\Uri;

final readonly class DatabaseIndexMessage
{
    public function __construct(
        /** Meta information */
        public string          $siteIdentifier,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        public Uri $uri,
        public int $pageUid,
        public string $indexProcessId,
    ) {}

}
