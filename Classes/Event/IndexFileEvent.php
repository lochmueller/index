<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final class IndexFileEvent
{
    public function __construct(
        /** Meta information */
        public readonly SiteInterface   $site,
        public readonly int    $indexConfigurationRecordId,
        public string          $indexProcessId,
        /** Content data */
        public string $title,
        public string $content,
        public readonly string $fileIdentifier,
        public readonly string $uri = '',
    ) {}
}
