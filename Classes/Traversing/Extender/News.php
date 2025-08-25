<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class News implements ExtenderInterface
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
        foreach ($this->recordSelection->findRecordsOnPage('tx_news_domain_model_news', $extenderConfiguration['recordStorages'] ?? [], $siteLanguage->getLanguageId()) as $record) {
            if ($record->getRecordType() === '0') {
                $arguments = [
                    '_language' => $siteLanguage,
                    'tx_news_pi1' => [
                        'action' => 'detail',
                        'controller' => 'News',
                        'news' => $record->getUid(),
                    ],
                ];

                yield new FrontendInformationDto(
                    uri: $router->generateUri($pageUid, $arguments),
                    arguments: $arguments,
                    pageUid: $pageUid,
                    language: $siteLanguage,
                    row: $row,
                );
            }
        }
    }

    public function getName(): string
    {
        return 'news';
    }

}
