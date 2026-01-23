<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing\Extender;

use Lochmueller\Index\Configuration\Configuration;
use Lochmueller\Index\Traversing\FrontendInformationDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class Calendarize implements ExtenderInterface
{
    public function __construct(
        private readonly RecordSelection $recordSelection,
    ) {}

    /**
     * @param array<string, mixed> $extenderConfiguration
     * @param array<string, mixed> $row
     * @return iterable<FrontendInformationDto>
     */
    public function getItems(
        Configuration $configuration,
        array         $extenderConfiguration,
        Site $site,
        int           $pageUid,
        SiteLanguage  $siteLanguage,
        array         $row,
    ): iterable {
        /** @var PageRouter $router */
        $router = $site->getRouter();
        foreach ($this->recordSelection->findRecordsOnPage('tx_calendarize_domain_model_index', $extenderConfiguration['recordStorages'] ?? [], $siteLanguage->getLanguageId()) as $record) {
            if ($record->getRecordType() === '0') {
                $arguments = [
                    '_language' => $siteLanguage,
                    'tx_calendarize_calendar' => [
                        'action' => 'detail',
                        'controller' => 'Calendar',
                        'index' => $record->getUid(),
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
        return 'calendarize';
    }

}
