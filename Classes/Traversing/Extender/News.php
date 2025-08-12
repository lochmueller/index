<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class News extends AbstractExtender
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
        foreach ($this->recordSelection->findRecordsOnPage('tx_news_domain_model_news', $extenderConfiguration['recordStorages'] ?? []) as $record) {
            if ($record->getRecordType() === '0') {
                yield [
                    'uri' => $router->generateUri(BackendUtility::getRecord('pages', $pageUid), [
                        'tx_news_pi1' => [
                            'action' => 'detail',
                            'controller' => 'News',
                            'news' => $record->getUid(),
                        ],
                    ]),
                    'pageUid' => $pageUid,
                ];
            }
        }
    }

    public function getName(): string
    {
        return 'news';
    }

}
