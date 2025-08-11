<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class Address extends AbstractExtender
{
    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        SiteInterface $site,
        int $pageUid,
    ): iterable {
        // TODO: Implement getItems() method.
    }

    public function getName(): string
    {
        return 'address';
        ;
    }

}
