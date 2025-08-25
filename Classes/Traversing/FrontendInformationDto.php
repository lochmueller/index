<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

readonly class FrontendInformationDto
{
    public function __construct(
        public UriInterface $uri,
        public array        $arguments,
        public int          $pageUid,
        public SiteLanguage $language,
        public array        $row,
    ) {}
}
