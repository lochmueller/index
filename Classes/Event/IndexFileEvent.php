<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class IndexFileEvent
{
    public function __construct(
        /** Meta information */
        public SiteInterface   $site,
        public IndexTechnology $technology,
        public IndexType       $type,
        public int             $indexConfigurationRecordId,
        /** Content data */
        public string          $title,
        public string          $content,
        public string          $fileIdentifier,
        public SiteLanguage    $language,
    ) {}
}
