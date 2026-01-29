<?php

declare(strict_types=1);

namespace Lochmueller\Index\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;

class LogRepository extends AbstractRepository
{
    protected function getTableName(): string
    {
        return 'tx_index_domain_model_log';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIndexProcessId(string $indexProcessId): ?array
    {
        $qb = $this->getConnection()->createQueryBuilder();
        $record = $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('index_process_id', $qb->expr()->literal($indexProcessId)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $record === false ? null : $record;
    }

    public function deleteOlderThan(int $timestamp): void
    {
        $this->getConnection()->delete($this->getTableName(), ['start_time < ?' => $timestamp], [Connection::PARAM_INT]);
    }
}
