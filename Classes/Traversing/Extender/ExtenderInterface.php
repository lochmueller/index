<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

#[AutoconfigureTag(name: 'index.extender')]
interface ExtenderInterface
{
    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        SiteInterface $site,
        int $pageUid,
        SiteLanguage $siteLanguage,
        array $row,
    ): iterable;

    public function getName(): string;
}
