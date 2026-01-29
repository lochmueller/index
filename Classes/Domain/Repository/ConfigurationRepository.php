<?php

declare(strict_types=1);

namespace Lochmueller\Index\Domain\Repository;

use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationRepository extends AbstractRepository
{
    protected function getTableName(): string
    {
        return 'tx_index_domain_model_configuration';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function findAll(): iterable
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->getTableName());
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $result = $queryBuilder->select('*')
            ->from($this->getTableName())
            ->executeQuery();

        return $result->iterateAssociative();
    }
}
