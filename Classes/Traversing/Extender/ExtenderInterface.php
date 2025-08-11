<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

#[AutoconfigureTag(name: 'index.extender')]
interface ExtenderInterface
{
    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        SiteInterface $site,
        int $pageUid,
    ): iterable;

    public function getName(): string;
}
