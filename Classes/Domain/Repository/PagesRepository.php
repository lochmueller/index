<?php

declare(strict_types=1);

namespace Lochmueller\Index\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class PagesRepository extends AbstractRepository
{
    protected function getTableName(): string
    {
        return 'pages';
    }

    /**
     * @return iterable<int>
     */
    public function findChildPageUids(int $parentId): iterable
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->getTableName());
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $queryBuilder->select('uid')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );

        $statement = $queryBuilder->executeQuery();
        foreach ($statement->iterateAssociative() as $row) {
            yield (int) $row['uid'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRootline(int $pageUid): array
    {
        $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid);
        return $rootLineUtility->get();
    }

    /**
     * @return array<int>
     */
    public function getRootlineIds(int $pageUid): array
    {
        return array_map(
            fn(array $entry): int => (int) $entry['uid'],
            $this->getRootline($pageUid),
        );
    }
}
