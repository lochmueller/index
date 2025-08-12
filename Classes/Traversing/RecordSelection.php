<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordSelection
{
    public function __construct(
        private RecordFactory $recordFactory,
    ) {}

    /**
     * @return Record[]
     */
    public function findRecordsOnPage(string $table, array $pageUids): iterable
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        // No empty storages
        $pageUids[] = -99;

        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in('pid', $pageUids),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );

        foreach ($queryBuilder->executeQuery()->iterateAssociative() as $row) {
            yield $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
        }
    }

}
