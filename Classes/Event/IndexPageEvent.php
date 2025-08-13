<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final class IndexPageEvent
{
    public function __construct(
        /** Meta information */
        public readonly SiteInterface   $site,
        public readonly IndexTechnology $technology,
        public readonly IndexType       $type,
        public readonly int             $indexConfigurationRecordId,
        public string          $indexProcessId,
        /** Content data */
        public readonly int             $language,
        public string                   $title,
        public string                   $content,
        public readonly int             $pageUid,
        public readonly array           $accessGroups,
    ) {}
}
