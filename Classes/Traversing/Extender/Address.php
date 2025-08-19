<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class Address extends AbstractExtender
{
    public function __construct(
        private readonly RecordSelection $recordSelection,
    ) {}

    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        SiteInterface $site,
        int           $pageUid,
        SiteLanguage  $siteLanguage,
        array         $row,
    ): iterable {
        /** @var PageRouter $router */
        $router = $site->getRouter();
        foreach ($this->recordSelection->findRecordsOnPage('tt_address', $extenderConfiguration['recordStorages'] ?? [], $siteLanguage->getLanguageId()) as $record) {
            $arguments = [
                '_language' => $siteLanguage,
                'tx_ttaddress_listview' => [
                    'action' => 'show',
                    'controller' => 'Address',
                    'address' => $record->getUid(),
                ],
            ];

            yield [
                'uri' => $router->generateUri($pageUid, $arguments),
                'arguments' => $arguments,
                'pageUid' => $pageUid,
                'language' => $siteLanguage,
                'row' => $row,
            ];
        }
    }

    public function getName(): string
    {
        return 'address';
    }

}
