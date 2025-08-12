<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

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
    ): iterable {
        /** @var PageRouter $router */
        $router = $site->getRouter();
        foreach ($this->recordSelection->findRecordsOnPage('tt_address', $extenderConfiguration['recordStorages'] ?? []) as $record) {
            yield [
                'uri' => $router->generateUri(BackendUtility::getRecord('pages', $pageUid), [
                    'tx_ttaddress_listview' => [
                        'action' => 'show',
                        'controller' => 'Address',
                        'address' => $record->getUid(),
                    ],
                ]),
                'pageUid' => $pageUid,
            ];
        }
    }

    public function getName(): string
    {
        return 'address';
    }

}
