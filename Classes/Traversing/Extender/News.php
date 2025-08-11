<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class News extends AbstractExtender
{
    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        SiteInterface $site,
        int $pageUid,
    ): iterable {

        // @todo index configuration
        // Type (News, Address)

        yield [
            'uri' => $site->getRouter()->generateUri(BackendUtility::getRecord('pages', $pageUid)),
            'pageUid' => $pageUid,
        ];
    }
    public function getName(): string
    {
        return 'news';
        ;
    }
}
