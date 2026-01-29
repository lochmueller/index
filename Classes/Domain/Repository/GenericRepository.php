<?php

declare(strict_types=1);

namespace Lochmueller\Index\Domain\Repository;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GenericRepository extends AbstractRepository
{
    private string $tableName = '';

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;

        return $this;
    }

    protected function getTableName(): string
    {
        return $this->tableName;
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable($this->getTableName());
    }

    public function createFrontendQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        return $queryBuilder;
    }

    /**
     * Find records by parent content element UID.
     *
     * @param array<int> $languages
     * @return iterable<array<string, mixed>>
     */
    public function findByParentContentElement(int $parentUid, array $languages): iterable
    {
        $queryBuilder = $this->createFrontendQueryBuilder();

        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('tt_content', $parentUid),
                $queryBuilder->expr()->in('sys_language_uid', $languages),
            )
            ->orderBy('sorting', 'ASC');

        return $queryBuilder->executeQuery()->iterateAssociative();
    }

    /**
     * Find records on pages with language filtering.
     *
     * @param array<int> $pageUids
     * @param array<int> $languages
     * @param array<class-string<QueryRestrictionInterface>|QueryRestrictionInterface> $restrictions
     * @return iterable<array<string, mixed>>
     */
    public function findRecordsOnPages(
        array $pageUids,
        string $languageField,
        array $languages,
        array $restrictions = [],
    ): iterable {
        $queryBuilder = $this->createQueryBuilder();

        foreach ($restrictions as $restriction) {
            /** @var QueryRestrictionInterface $restrictionInstance */
            $restrictionInstance = is_object($restriction) ? $restriction : GeneralUtility::makeInstance($restriction);
            $queryBuilder->getRestrictions()->add($restrictionInstance);
        }

        // No empty storages
        $pageUids[] = -99;

        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->in('pid', $pageUids),
                $queryBuilder->expr()->in($languageField, $languages),
            );

        return $queryBuilder->executeQuery()->iterateAssociative();
    }
}
