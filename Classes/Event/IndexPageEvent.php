<?php

declare(strict_types=1);

namespace Lochmueller\Indexing\Event;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final class IndexPageEvent
{
    public function __construct(
        public SiteInterface $site,
        public $language,
        public string $title,
        public string $content,
        public int $pageUid,
        public array $accessGroups,
    ) {}
}
