<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database;

use TYPO3\CMS\Core\Site\Entity\Site;

class DatabaseIndexingDto
{
    public function __construct(
        public string       $title,
        public string       $content,
        public readonly int $pageUid,
        public readonly int $languageUid,
        public array        $arguments,
        public readonly Site $site,
    ) {}

}
