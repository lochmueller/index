<?php

declare(strict_types=1);

namespace Lochmueller\Index\Event;

use TYPO3\CMS\Core\Site\Entity\SiteInterface;

final readonly class DeletePageEvent
{
    public function __construct(
        public SiteInterface $site,
        public string        $uri
    ) {}
}
