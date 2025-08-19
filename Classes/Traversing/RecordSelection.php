<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordSelection
{
    public function __construct(
        private RecordFactory    $recordFactory,
        protected PageRepository $pageRepository,
    ) {}

    /**
     * @return Record[]
     */
    public function findRecordsOnPage(string $table, array $pageUids, int $languageUid = 0): iterable
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        // No empty storages
        $pageUids[] = -99;

        $languages = [
            0,
            -1,
            $languageUid,
        ];

        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in('pid', $pageUids),
                $queryBuilder->expr()->in('sys_language_uid', $languages),
            );

        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, GeneralUtility::makeInstance(Context::class));

        foreach ($queryBuilder->executeQuery()->iterateAssociative() as $row) {
            if ($languageUid) {
                $overlay = $pageRepository->getLanguageOverlay('tx_myext_domain_model_foo', $row, new LanguageAspect($languageUid, $languageUid));
                if ($overlay !== null) {
                    $record = $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $overlay);
                    /** @var \TYPO3\CMS\Core\Domain\Record\LanguageInfo $langInfo */
                    $langInfo = $record->getLanguageInfo();
                    if (in_array($langInfo->getLanguageId(), [-1, $languageUid])) {
                        yield $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $overlay);
                    }
                }
            } else {
                yield $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
            }
        }
    }

    public function findPage(int $pageUid, int $language = 0): ?array
    {
        $row = BackendUtility::getRecord('pages', $pageUid);
        if ($language !== 0) {
            $languageAspect = new LanguageAspect($language, $language);
            $row = $this->pageRepository->getPageOverlay($row, $languageAspect);
            if (empty($row) || $this->pageRepository->checkIfPageIsHidden($pageUid, $languageAspect)) {
                return null;
            }
        }
        return $row;
    }

}
